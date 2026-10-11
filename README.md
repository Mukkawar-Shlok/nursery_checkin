# Nursery Room Check-In API

Three tenant-scoped endpoints for checking children in and out of nursery rooms, with tests that prove tenant isolation. Laravel 13, PostgreSQL 18.

| Endpoint | Body | Success | Errors |
|---|---|---|---|
| `POST /rooms/{room}/check-ins` | `{ "child_id": "<uuid>" }` | 201 check-in | 404 room / child, 409 `child_already_checked_in`, 409 `child_in_another_room`, 422 |
| `POST /check-ins/{id}/check-out` | none | 200 check-in | 404, 409 `already_checked_out` |
| `GET /rooms/{room}/occupants?page=&per_page=` | none | 200 paginated | 404 |

Every request needs `X-Tenant-ID: <tenant uuid>`. Every error has one shape:

```json
{ "error": { "code": "child_in_another_room", "message": "Child is already checked in to Sunflowers. Check them out first.", "room_id": "...", "room_title": "Sunflowers" } }
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

The seeder creates **Little Stars Group** (nurseries Bandra and Andheri) and **Happy Kids Group** (Pune). Each nursery has two rooms, and each tenant has 10 children.

**Debugging:** [Telescope](https://laravel.com/docs/telescope) is installed as a dev dependency and loads only when `APP_ENV=local`. Open http://localhost/telescope to see requests, queries (including the `FOR UPDATE`) and exceptions. It is disabled in tests (`TELESCOPE_ENABLED=false` in `phpunit.xml`).

## How it is built

| Unit | Job |
|---|---|
| `Http/Middleware/ResolveTenant` | Reads `X-Tenant-ID` (400 if missing or not a UUID, 404 if unknown) and stores the tenant in `Support/TenantContext` |
| `Models/Concerns/BelongsToTenant` | Global scope `where tenant_id = current tenant` on `Room`, `Child`, `RoomCheckIn`, and fills `tenant_id` on create |
| `Services/CheckInService` | Check-in rules inside a transaction with a row lock on the child |
| `Services/CheckOutService` | One conditional `UPDATE` |
| `Services/OccupantService` | Paginated open check-ins with their children |
| `Exceptions/Domain/*` | Each carries its HTTP status and a stable `code`. `bootstrap/app.php` maps them to the error shape |
| Controllers, `CheckInChildRequest`, Resources | Thin HTTP translation |

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
- **Weakness:** anything that writes `room_check_ins` without going through the service (tinker, a future importer) can break the invariant. The backstop is a partial unique index (see "With more time").
- **Why PostgreSQL for tests:** SQLite ignores `FOR UPDATE`, so a broken guard would still pass there. `tests/Feature/CheckInLockTest` holds a lock on the child row from a second connection and asserts the check-in blocks (hits `lock_timeout`). That connection uses `FOR NO KEY UPDATE` rather than `FOR UPDATE`. Otherwise the foreign-key check on the insert (`FOR KEY SHARE`) would block too, and the test would pass even with `lockForUpdate()` removed. I checked: it now fails when the lock is removed.

## Soft deletes

Soft-deleted rooms and children resolve as 404. A child soft-deleted while checked in keeps the open check-in row and is excluded from occupants.

## Assumptions

- Authentication is already handled. There is no login, permission or role system.
- A tenant is a nursery group. `X-Tenant-ID` carries the tenant id, not a nursery id.
- A missing or malformed `X-Tenant-ID` returns 400. A well-formed id with no matching tenant returns 404.
- Each child belongs to exactly one tenant. The same real child attending two groups is two records.
- A child can use any room of any nursery of their own tenant. Nursery is not checked. To bring back nursery-level membership, add `children.nursery_id` and compare it with `rooms.nursery_id`.
- Cross-tenant ids return 404, never 403, so the API does not reveal whether a record exists.
- `checked_in_at` and `checked_out_at` are set by the server at request time, never sent by the client.
- Check-in takes the room from the URL and `child_id` from the body. Check-out takes the check-in id from the URL and has no body.
- A child can have at most one open check-in. A second check-in is rejected with 409, not moved.
- Soft-deleted rooms and children resolve as 404 (see above).
- Guardian contact numbers are 10-digit Indian mobile numbers (`[6-9]` followed by 9 digits). They are generated in the factory only, since there is no register-child endpoint.
- All ids are UUID v7. Timestamps are stored in UTC.
- Occupants are paginated with 25 per page by default and at most 100, ordered by `checked_in_at` then `id`.
- Non-UUID path ids return 404 (route constraint), not a database error.
- Data comes from seeders and factories. PostgreSQL is the datastore, and tests run against a separate PostgreSQL `testing` database.

## Left out on purpose

- Authentication, permissions and roles. The brief says auth is already handled.
- Endpoints for nursery listing, children listing, registering a child, and `GET /rooms`. Seeders and factories create the data instead.
- Admin UI, notifications, reporting.

## With more time

- **Partial unique index** `room_check_ins (child_id) WHERE checked_out_at IS NULL` as a database backstop for writers that bypass the service.
- A true parallel-request concurrency test, beyond the lock test.
- Rate limiting.
- An idempotency key on check-in, so a retried tap can't produce a confusing 409.
- An audit log of check-in changes. Check-out would then wrap the conditional update and the log insert in one transaction.

## AI use

Claude Code drafted the implementation from my written spec. It caught that the first version of the lock test passed even with `lockForUpdate()` removed (the FK's key-share lock blocked instead), and fixed it with `FOR NO KEY UPDATE`. _TODO (owner): add what you changed or rejected yourself._
# nursery_checkin
