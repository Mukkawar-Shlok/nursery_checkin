<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\CheckInService;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Proves the check-in takes the child's row lock. A second connection holds
 * a row lock on the child; the service must then block (here it
 * hits lock_timeout) instead of reading and inserting past it.
 *
 * Uses committed data (DatabaseTruncation, not RefreshDatabase) so the
 * second connection can see the rows. PostgreSQL only: SQLite has no row locks.
 */
class CheckInLockTest extends TestCase
{
    use DatabaseTruncation;

    // DatabaseTruncation only clears tables before a test; clear after too so
    // committed rows don't leak into the RefreshDatabase tests that follow.
    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_check_in_waits_for_the_child_row_lock(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Row locks need PostgreSQL.');
        }

        $tenant = Tenant::factory()->create();
        $room = $this->roomFor($tenant);
        $child = $this->childFor($tenant);
        app(TenantContext::class)->set($tenant);

        config(['database.connections.pgsql_lock' => config('database.connections.pgsql')]);
        $other = DB::connection('pgsql_lock');
        $other->beginTransaction();
        // NO KEY UPDATE conflicts with the service's FOR UPDATE but not with the
        // KEY SHARE lock the room_check_ins FK check takes, so only the explicit
        // lock can block. (Plain FOR UPDATE here would pass even without it.)
        $other->select('select id from children where id = ? for no key update', [$child->id]);

        DB::statement("set lock_timeout = '200ms'");

        try {
            app(CheckInService::class)->checkIn($room, $child->id);
            $this->fail('Check-in did not wait for the child row lock.');
        } catch (QueryException $e) {
            $this->assertSame('55P03', $e->getCode()); // lock_not_available
        } finally {
            $other->rollBack();
            DB::statement('reset lock_timeout');
        }

        // Lock released: the same check-in now goes through.
        app(CheckInService::class)->checkIn($room, $child->id);
        $this->assertDatabaseCount('room_check_ins', 1);
    }
}
