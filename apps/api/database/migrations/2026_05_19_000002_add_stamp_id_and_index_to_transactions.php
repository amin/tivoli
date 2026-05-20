<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('stamp_id')->nullable()->after('amusement_id');
            $table->foreign('stamp_id')->references('id')->on('stamps')->nullOnDelete();
            $table->index(['user_id', 'amusement_id', 'created_at'], 'transactions_user_amusement_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_amusement_created_idx');
            $table->dropForeign(['stamp_id']);
            $table->dropColumn('stamp_id');
        });
    }
};
