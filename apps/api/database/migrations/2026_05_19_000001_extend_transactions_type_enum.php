<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT transactions_type_check');
            DB::statement(
                "ALTER TABLE transactions ADD CONSTRAINT transactions_type_check "
                . "CHECK (type IN ('fee', 'in_game_purchase', 'payout', 'owner_revenue', 'exchange'))"
            );
        }
        // SQLite stores enum as a plain VARCHAR with no constraint, no migration needed.
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT transactions_type_check');
            DB::statement(
                "ALTER TABLE transactions ADD CONSTRAINT transactions_type_check "
                . "CHECK (type IN ('fee', 'payout', 'owner_revenue', 'exchange'))"
            );
        }
    }
};
