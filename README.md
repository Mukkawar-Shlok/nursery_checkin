# Nursery Room Check-In API

Three tenant-scoped endpoints for checking children in and out of nursery rooms, with tests that prove tenant isolation. Laravel 13, PostgreSQL 18.

| Endpoint | Body | Success | Errors |
|---|---|---|---|
| `POST /rooms/{room}/check-ins` | `{ "child_id": "<uuid>" }` | 201 `S201` check-in | 404 `ERR_404` room / child, 409 `ERR_409` already in this room / in another room, 422 `E1000` |
| `POST /check-ins/{id}/check-out` | none | 200 `S200` check-in | 404 `ERR_404`, 409 `ERR_409` already checked out |
| `GET /rooms/{room}/occupants?page=&per_page=` | none | 200 `S200` paginated | 404 `ERR_404` |

Every request needs `X-Tenant-ID: <tenant uuid>` (400 `ERR_400` if missing or invalid, 404 `ERR_404` if unknown).

Every response uses one envelope, built by `App\Traits\ApiResponse`. Codes are in `App\Constants\APIResponseCodes`, and the title, message, HTTP status and icon for each code are in `config/api-response-codes.php`.

```json
{ "success": true, "code": "S201", "data": { "id": "...", "child_id": "...", "room_id": "...", "checked_in_at": "...", "checked_out_at": null },
  "title": "Created", "message": "The resource was created successfully.", "icon": "check-circle-outline", "status_code": 201 }

{ "success": false, "code": "ERR_409", "title": "Conflict", "message": "Child is already checked in to Sunflowers. Check them out first.",
  "errors": [], "icon": "error", "status_code": 409 }

{ "success": false, "code": "ERR_404", "title": "Not Found", "message": "The requested resource was not found. Please check the URL and try again.",
  "errors": { "model": "No query results for model [App\\Models\\Room] 01a1..." }, "icon": "error", "status_code": 404 }
```

Occupants put `pagination` and `items` inside `data`:

```json
{ "success": true, "code": "S200", "data": { "pagination": { "current_page": 1, "per_page": 25, "total_count": 30, "total_pages": 2 }, "items": [ ... ] }, ... }
```

## Run it

Requires Docker. Sail runs PHP and PostgreSQL. The Postgres image creates the `testing` database on first start (`vendor/laravel/sail/database/pgsql/create-testing-database.sql`).

```sh
composer install
cp .env.example .env            # DB_CONNECTION=pgsql, DB_HOST=pgsql
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan test  # runs against the `testing` Postgres database
```

If the volume existed before, so the init script never ran, create the test database by hand:

```sh
./vendor/bin/sail psql -c 'CREATE DATABASE testing;'
```

Try it, using ids from the seeded data:

```sh
./vendor/bin/sail psql -c 'select t.title, t.id tenant, r.id room from rooms r join tenants t on t.id = r.tenant_id;'
./vendor/bin/sail psql -c 'select tenant_id, id, full_name from children limit 5;'

curl -X POST localhost/rooms/$ROOM/check-ins \
  -H "X-Tenant-ID: $TENANT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"child_id\":\"$CHILD\"}"
```

**Postman:** a collection with every endpoint and saved example responses (success, 400, 404, 409) is in [`docs/Meta Design.postman_collection.json`](docs/Meta%20Design.postman_collection.json). Import it into Postman. The tenant, room and child ids in it come from one seed run, so after `migrate:fresh --seed`, replace them with fresh ids from the queries above.

The seeder creates **Little Stars Group** (nurseries Bandra and Andheri) and **Happy Kids Group** (Pune). Each nursery has two rooms, and each tenant has 10 children.

## Tests

```sh
./vendor/bin/sail artisan test                          # all 24 tests
```

All tests are **feature tests**: they send real HTTP requests to the API (with the `X-Tenant-ID` header) and check the response and the database. This tests what a client actually sees, including routing, middleware, validation and the error shape. They run on PostgreSQL, not SQLite, because SQLite ignores row locks.

The tests are split **by what could go wrong**, one file per concern:

| File | What it proves | Tests |
|---|---|---|
| `TenantHeaderTest` | No request gets in without a valid group (tenant) | Header missing → 400. Not a UUID → 400. Unknown tenant → 404 |
| `TenantIsolationTest` | **One group can never see or change another group's data** (the main security rule) | Listing another group's room → 404. Checking out another group's check-in → 404, and the row is left unchanged. Checking in another group's child → 404. Checking into another group's room → 404. Two groups with a room of the same name: each sees only its own children |
| `CheckInTest` | The check-in rules | Happy path → 201, stored with the right tenant. Same room twice → 409. Already in another room → 409, naming that room. Checked out of room A, then into room B → 201. Two check-ins in a row for one child → only one succeeds. Missing or invalid `child_id` → 422. Deleted child or room → 404 |
| `CheckOutTest` | The check-out rules | Happy path → 200 with `checked_out_at` set. Second check-out → 409. Unknown check-in → 404 |
| `OccupantsTest` | The room list | Oldest check-in first. Checked-out and deleted children are left out. Pages: 25 by default, page 2, `per_page` capped at 100, `total_pages`. Empty room → empty list |
| `CheckInLockTest` | The concurrency guard really locks | A second database connection holds a lock on the child, and a check-in must wait for it (it times out). Once the lock is released, the check-in succeeds. **This test fails if `lockForUpdate()` is removed**, which was checked |

**How the tests are set up**

- **Fresh data per test:** each test builds its own groups, rooms and children with factories, using helpers in `tests/TestCase.php` (`roomFor()`, `childFor()`, `checkInRow()`, `asTenant()`). `RefreshDatabase` rolls everything back after each test, so tests never depend on each other.
- **Stable codes:** tests check the stable `code` (e.g. `ERR_409`), plus the translated message or `errors.model` where two errors share a code. Rewording a message in `lang/en/api.php` does not break them.
- **Isolation tests re-read the database:** they don't stop at the 404 response. They re-read the other group's row to confirm nothing was changed.
- **The lock test uses committed data:** it uses `DatabaseTruncation` instead of `RefreshDatabase`, because the second connection can't see uncommitted rows. It cleans up after itself so it doesn't affect the other tests.

## How it is built

| Unit | Job |
|---|---|
| `Http/Middleware/ResolveTenant` | Reads `X-Tenant-ID` (400 if missing or not a UUID, 404 if unknown) and stores the tenant in `Support/TenantContext` |
| `Models/Concerns/BelongsToTenant` | Global scope `where tenant_id = current tenant` on `Room`, `Child`, `RoomCheckIn`, and fills `tenant_id` on create |
| `Services/CheckInService` | Check-in rules inside a transaction with a row lock on the child |
| `Services/CheckOutService` | One conditional `UPDATE` |
| `Services/OccupantService` | Paginated open check-ins with their children |
| `Traits/ApiResponse` | `success()` / `fail()` build the envelope from `config/api-response-codes.php`. It is used by every controller via the base `Controller` |
| Errors | No custom exception classes. Models use `findOrFail()` (and route model binding). Rule failures throw built-in HTTP exceptions with messages from `lang/en/api.php`, e.g. `throw new ConflictHttpException(__('api.errors.already_checked_out'))` |
| `Exceptions/Handler` | Catches everything for API routes (wired in `bootstrap/app.php`): `ValidationException`, not-found (`errors.model` names the model), `ConflictHttpException` and `BadRequestHttpException` go to `fail()`, and anything else is logged and returned as `ERR_500` |
| `Http/Resources/*` | `data` payloads: `CheckInResource`, `OccupantListResource` (pagination + items of `OccupantResource`) |
| Controllers, `CheckInChildRequest` | Thin HTTP translation. A missing room comes from the route's `missing()` callback |

**Isolation.** `ResolveTenant` is in the middleware priority list ahead of `SubstituteBindings`, so `{room}` is resolved through the tenant scope. Another tenant's room or check-in is simply not found, and the API returns 404. The scope **fails closed**: querying a tenant-owned model with no tenant in context throws, so it never returns unscoped rows to tinker, a queued job or future code. `tenant_id` is never read from request input. A check-in copies it from the resolved room.

## The two-rooms decision

If a child already has an open check-in, a second check-in is **rejected with 409** and the response names the room they are in. Staff check the child out first.

- Check-out times stay accurate. A silent move would invent a leave time for the first room, and nurseries rely on these times for ratios and safeguarding records.
- No hidden data change. The caller always knows exactly what happened.
- One rule and one error code, so it is easy to test and explain.
- It catches mistakes. A second check-in is often the wrong child or the wrong room.

**Trade-off:** staff do one extra step when a child moves rooms. Auto-move with a warning would be faster for them, and it is the first thing to change if asked.

## Concurrency guard

Invariant: **a child has at most one open check-in.** It is enforced in `CheckInService`, not by a database constraint.

- **Check-in:** `DB::transaction` + `Child::lockForUpdate()` (`SELECT ... FOR UPDATE`). Every check-in for a child goes through that child's row, so concurrent requests queue behind it. Under READ COMMITTED (the Postgres default) the second request then sees the first one's committed check-in and gets a 409. The lock only lives inside the transaction. Raising the isolation level to REPEATABLE READ or SERIALIZABLE would give the second request an older snapshot and break the guard. Different children never block each other.
- **Check-out:** a single `UPDATE ... WHERE id = ? AND tenant_id = ? AND checked_out_at IS NULL`. Two racing check-outs: the second updates 0 rows and gets 409 (or 404 if the row isn't in this tenant). It is deliberately *not* load-check-save, which would let both requests pass the check.
- **Why code, not a constraint:** the rule lives in one tested class, and 409s come from explicit domain exceptions instead of parsing driver-specific unique-violation errors.
- **Weakness:** anything that writes `room_check_ins` without going through the service (tinker, a future importer) can break the invariant. The backstop would be a partial unique index, `room_check_ins (child_id) WHERE checked_out_at IS NULL`.
- **Why PostgreSQL for tests:** SQLite ignores `FOR UPDATE`, so a broken guard would still pass there. `tests/Feature/CheckInLockTest` holds a lock on the child row from a second connection and asserts the check-in blocks (hits `lock_timeout`). That connection uses `FOR NO KEY UPDATE` rather than `FOR UPDATE`. Otherwise the foreign-key check on the insert (`FOR KEY SHARE`) would block too, and the test would pass even with `lockForUpdate()` removed. I checked: it now fails when the lock is removed.

## Soft deletes

Soft-deleted rooms and children resolve as 404. A child soft-deleted while checked in keeps the open check-in row and is excluded from occupants.

**Delete rules: deferred.** There is no delete endpoint or admin UI, so rooms and children can only be deleted by hand, and no delete rules are enforced yet. The intended rule is:

- **A room cannot be deleted while any child is still checked in to it.** Staff check every child out first, and then the room can be deleted.

Until that rule exists, deleting a room that still has children in it leaves their check-ins pointing at a deleted room. If one of those children is then checked into another room, the check-in fails with a 500 instead of a 409, because the service no longer reads deleted rooms to get the title for the "already checked in to …" message. The fix belongs in the future delete-room flow, not in the check-in service.

## Assumptions

- **Login is already handled elsewhere.** This system does not deal with usernames, passwords or staff roles.
- **A customer is a nursery group** (for example "Little Stars Group"). A group can run several nurseries, and each nursery has rooms. Every request says which group it is for.
- **A child belongs to only one nursery group.** If the same child attends two different groups, each group has its own separate record for that child.
- **A child can go into any room in their own group**, in any of that group's nurseries. A child can never go into another group's room.
- **Groups never see each other's data.** If a group asks for another group's room, child or check-in, the system answers "not found", so it does not even reveal that the record exists.
- **A child can only be in one room at a time.** To move a child, staff check them out of the first room and then check them into the new one. The system will not move them automatically.
- **The system records the check-in and check-out times itself**, at the moment staff press the button. Staff cannot type in a time.
- **Deleted rooms and children are treated as not found.** If a child is deleted while still checked in, that check-in stays on record but the child no longer shows in the room list.
- **Guardian phone numbers are 10-digit Indian mobile numbers.**
- **The list of children in a room comes in pages**, 25 children per page by default and at most 100, oldest check-in first.
- **Test data is created automatically** (two example groups with nurseries, rooms and children), since there are no screens for adding them.

### Technical notes

- `X-Tenant-ID` carries the group (tenant) id. Missing or malformed: 400. Unknown: 404.
- Cross-tenant ids return 404, never 403.
- Check-in takes the room from the URL and `child_id` from the body. Check-out takes the check-in id from the URL and has no body.
- All ids are UUID v7. Timestamps are stored in UTC. Non-UUID ids in the URL return 404.
- Pagination: `?page` and `?per_page`, with the response under `data.pagination` and `data.items`.
- PostgreSQL is the datastore, and tests use a separate PostgreSQL `testing` database.

## Left out on purpose

- Authentication, permissions and roles. The brief says auth is already handled.
- Endpoints for nursery listing, children listing, registering a child, and `GET /rooms`. Seeders and factories create the data instead.
- Admin UI, notifications, reporting.

## Running on AWS (ECS/Fargate)

Until now I have mostly deployed with Laravel Forge on EC2, with Slack alerts on server CPU, RAM, disk and IOPS. This note maps that experience onto ECS/Fargate.

**Container.** One FrankenPHP image (the Caddy web server is built in, so no Nginx), with the app installed using `composer install --no-dev` and no secrets baked in. The ALB routes traffic to the tasks and health-checks `/up`. Migrations run as a one-off ECS task before each rollout.

**Secrets.** The database password and `APP_KEY` live in AWS Secrets Manager, and other settings in SSM Parameter Store. The task definition references them, and ECS injects them as environment variables at start-up, so Laravel reads them through `env()` unchanged. Only the task execution role can read them.

**Database.** RDS PostgreSQL, Multi-AZ, in private subnets, reachable on port 5432 only from the ECS tasks' security group. The tasks connect through RDS Proxy as a connection pooler, because every FrankenPHP worker holds its own connection.

**Logs and alerts.** `LOG_CHANNEL=stderr` sends Laravel logs, with the tenant id in the context, to CloudWatch Logs. CloudWatch alarms post to Slack on: ALB 5xx rate and p95 latency, unhealthy targets, ECS CPU and memory, and RDS CPU, connections, storage and IOPS.

## Telescope

[Laravel Telescope](https://laravel.com/docs/telescope) is installed for local debugging. Open http://localhost/telescope to see each request with its `X-Tenant-ID` header, response and status, the SQL it ran (including the tenant `where` and the check-in's `SELECT ... FOR UPDATE`) and any exceptions.

- **Dev only:** it is in `require-dev` and excluded from package discovery. `AppServiceProvider` registers it only when `APP_ENV=local`, so it never loads in production.
- **Off in tests:** `TELESCOPE_ENABLED=false` in `phpunit.xml`.
- **Storage:** entries go to the `telescope_entries` table (created by `migrate`). Clear them with `./vendor/bin/sail artisan telescope:clear`.

# nursery_checkin
