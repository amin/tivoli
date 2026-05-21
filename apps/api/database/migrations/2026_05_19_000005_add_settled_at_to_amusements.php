<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amusements', function (Blueprint $table) {
            $table->timestamp('settled_at')->nullable()->after('amusement_balance');
        });
    }

    public function down(): void
    {
        Schema::table('amusements', function (Blueprint $table) {
            $table->dropColumn('settled_at');
        });
    }
};
