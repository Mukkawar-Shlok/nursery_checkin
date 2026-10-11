# Plan: Nursery Room Check-In API (Track A, Laravel 13)

## Context
Fresh Laravel 13 skeleton (PHP 8.4, PHPUnit 12, Sail with Postgres 18; Sail auto-creates the `testing` DB). Build the 3 tenant-scoped endpoints from the fixed spec with tests that prove isolation, and keep the code minimal. The structure follows the spec's unit list, with each unit kept thin. Routes have no `/api` prefix. Laravel Boost is **not** installed. Indexes go in one final migration, written once the queries exist.

## Decisions (beyond the spec)
- Routes live in `routes/api.php`, registered via `withRouting(api: ..., apiPrefix: '')`. Laravel Sanctum is not installed.
- Route params are constrained with `whereUuid('room')` / `whereUuid('checkIn')`. Without this, a non-UUID would hit Postgres and return a 500 "invalid uuid" error instead of a 404.
- UUID v7 comes from Laravel's `HasUuids` (it generates v7 by default in 12+).
- `TenantContext` is registered as a `scoped()` singleton in `AppServiceProvider`.
- The fail-closed global scope throws `LogicException('No tenant in context')`. Factories set `tenant_id` explicitly, so seeders work without a context. Tests assert on the DB with `assertDatabaseHas` (query builder, so no scope applies).
- Error codes: `tenant_header_invalid` (400), `tenant_not_found` (404), `not_found` (404, from ModelNotFound/NotFoundHttp), `child_not_found` (404), `child_already_checked_in` (409), `child_in_another_room` (409, adds `room_id` + `room_title`), `already_checked_out` (409), `validation_failed` (422, adds `fields`).

## Files

**Migrations** (`database/migrations/`), one per table: `tenants`, `nurseries`, `rooms`, `children`, `room_check_ins`, matching the spec columns. They use `uuid('id')->primary()`, `foreignUuid(...)->constrained()` and `softDeletes()` (not on `room_check_ins`). The default users/cache/jobs migrations stay.
**Final migration** `add_query_indexes`: `room_check_ins(tenant_id, room_id, checked_out_at)` and `(tenant_id, child_id, checked_out_at)`, added last once the queries are final. The partial unique index is only listed in the README's "more time" section.

**Models** (`app/Models/`): `Tenant`, `Nursery`, `Room`, `Child`, `RoomCheckIn`. They all use `HasUuids` and `HasFactory`, with SoftDeletes where the table has it. `Room`, `Child` and `RoomCheckIn` use `BelongsToTenant`. Relations: `RoomCheckIn::child()` / `room()` (child relation `withTrashed` not needed; occupants filter it out).

**Tenancy**
- `app/Support/TenantContext.php`: `set(Tenant)`, `get(): Tenant` (throws if unset), `id()`, `has()`.
- `app/Models/Concerns/BelongsToTenant.php`: `bootBelongsToTenant()` adds a global scope `where(table.tenant_id, ctx->id())` that throws if there is no context, plus `creating` that fills `tenant_id` from the context when it's empty.
- `app/Http/Middleware/ResolveTenant.php`: header missing or `!Str::isUuid` → 400; `Tenant::find` null → 404; otherwise `TenantContext::set`.
- `bootstrap/app.php`: alias/append ResolveTenant to the `api` group and call `$middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class)`. The exception renderer maps `DomainException` → its status/code/extra, `ModelNotFoundException|NotFoundHttpException` → 404 `not_found`, and `ValidationException` → 422. Everything uses one `{ "error": {...} }` shape.

**HTTP**
- `routes/api.php`: the 3 routes with UUID constraints.
- `Http/Requests/CheckInChildRequest`: `child_id` is `required|uuid`.
- `Http/Controllers/CheckInController`: `store(Room $room, request, CheckInService)` → 201 `CheckInResource`; `checkOut(string $checkIn, CheckOutService)` → 200.
- `Http/Controllers/RoomOccupantController::index(Room $room, OccupantService)` → paginated `OccupantResource::collection`.
- `Http/Resources/CheckInResource` (id, child_id, room_id, checked_in_at, checked_out_at) and `OccupantResource` (check_in_id, child_id, full_name, guardian_name, checked_in_at).

**Services** (`app/Services/`)
- `CheckInService::checkIn(Room, childId)`: the spec code (transaction + `lockForUpdate` on the child, then the open-check-in lookup, then 409s, then create). `tenant_id` comes from `$room->tenant_id`.
- `CheckOutService::checkOut(id)`: a conditional `update()`. If 0 rows are updated, `RoomCheckIn::findOrFail($id)` (404), otherwise throw `CheckInAlreadyClosed`. Return `fresh()`.
- `OccupantService::forRoom(Room, perPage)`: open check-ins for the room, `whereHas('child')` (excludes soft-deleted children), `with('child')`, `orderBy(checked_in_at)->orderBy(id)`, `paginate(min(max(per_page,1),100) default 25)`.

**Exceptions** (`app/Exceptions/Domain/`): an abstract `DomainException` (`status`, `errorCode`, `extra(): array`) plus `ChildNotFound`, `ChildAlreadyCheckedIn`, `ChildInAnotherRoom(Room)` and `CheckInAlreadyClosed`.

**Factories/Seeders**: factories for all 5 models. Each child factory takes `tenant_id` from its parent (room ← nursery ← tenant). Guardian numbers are generated as `[6-9]` followed by 9 digits. `DatabaseSeeder` creates 2 tenants: Little Stars (Bandra, Andheri) and Happy Kids (Pune), each with rooms and children.

**Tests** (`tests/Feature/`, `RefreshDatabase`, a helper that sends `X-Tenant-ID`). Every row of the spec's section 8 table is covered:
- `TenantHeaderTest`: missing → 400, not a UUID → 400, unknown UUID → 404.
- `TenantIsolationTest`: the 5 isolation cases (the B check-out row is re-read via `assertDatabaseHas` with `checked_out_at` null).
- `CheckInTest`: happy path, same room twice, another room (asserts room_id), out of A then into B, 422 cases, soft-deleted child or room → 404, sequential two-room concurrency case.
- `CheckOutTest`: happy path, double check-out → 409, unknown id → 404.
- `OccupantsTest`: excludes checked-out, excludes soft-deleted child, pagination (default 25, page 2, cap 100), empty room.
- `CheckInLockTest` (Postgres only, skipped on other drivers): a second connection (`DB::connection('pgsql_lock')`, a cloned config) runs `BEGIN; SELECT ... FOR UPDATE` on the child with `SET lock_timeout`, while the main request's check-in runs with a short `lock_timeout`. The test asserts the request is blocked (lock timeout error), which proves the service takes the row lock. It doesn't use `RefreshDatabase` because the data must be committed and visible across connections, so it uses `DatabaseMigrations`/truncate instead.
- Delete the example tests.

**README**: replace it with every section in the spec's section 9 checklist, using Sail commands (`sail up -d`, `sail artisan migrate --seed`, `sail artisan test`) and a sample curl.

## Order of work
1. Migrations (tables) → models + tenancy → middleware/bootstrap → exceptions → services → controllers/resources/routes.
2. Factories/seeders → tests → final index migration → README.

## Verification
- `./vendor/bin/sail up -d && ./vendor/bin/sail artisan migrate:fresh --seed`
- `./vendor/bin/sail artisan test` with all green on Postgres (including the lock test).
- Manual curl: check in a seeded child (201), check in again (409), check in with the other tenant's header (404), list occupants, check out (200), check out again (409).

## Status (2026-10-11): implemented

Everything above is built. All 24 tests pass on Postgres, and the curl smoke test passes. Where the code differs from the plan, or a detail is worth knowing:
- The `CheckInLockTest` second connection uses `FOR NO KEY UPDATE`, not `FOR UPDATE`. With `FOR UPDATE`, the FK check on the insert into `room_check_ins` (`FOR KEY SHARE`) also blocks, so the test passed even with `lockForUpdate()` removed. This was verified by mutation: removing the lock makes the test fail.
- `CheckInLockTest` uses `DatabaseTruncation` and also truncates in `tearDown()`. Otherwise its committed rows leak into the `RefreshDatabase` tests that follow.
- Two extra domain exceptions: `TenantHeaderInvalid` (400) and `TenantNotFound` (404), thrown by `ResolveTenant`.
- `bootstrap/app.php` sets `shouldRenderJsonWhen(fn () => true)` so every error is JSON.
- Test helpers live in `tests/TestCase.php`: `asTenant`, `roomFor`, `childFor`, `checkInRow`, `softDelete`. Tests run outside a request, so they have no tenant context. Use `assertDatabaseHas` and `softDelete()` (query builder), never `$model->refresh()` or `->delete()`, because the tenant scope throws without a context.
- `RoomCheckInFactory` reads the room's `tenant_id` via `DB::table` so it doesn't need `withoutGlobalScopes()`.
- Open item: the README's "AI use" section has a TODO for the owner.
- Source spec: "Nursery Room Check-In API: Fixed Spec (Track A, Laravel)". README sections follow its section 9 checklist.
- Telescope (added 2026-10-11): `laravel/telescope` is a dev dependency and in `dont-discover`. It is registered from `AppServiceProvider::register()` only when the environment is local, and it is not listed in `bootstrap/providers.php`. Dashboard: `/telescope`. Disabled in tests via `phpunit.xml`.
