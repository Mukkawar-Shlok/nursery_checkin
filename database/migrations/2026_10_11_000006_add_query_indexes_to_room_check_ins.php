<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries the services actually run. tenant_id leads because
 * the tenant scope adds it to every query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_check_ins', function (Blueprint $table) {
            // OccupantService: open check-ins of one room.
            $table->index(['tenant_id', 'room_id', 'checked_out_at']);
            // CheckInService: "does this child already have an open check-in?"
            $table->index(['tenant_id', 'child_id', 'checked_out_at']);
        });
    }

    public function down(): void
    {
        Schema::table('room_check_ins', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'room_id', 'checked_out_at']);
            $table->dropIndex(['tenant_id', 'child_id', 'checked_out_at']);
        });
    }
};
