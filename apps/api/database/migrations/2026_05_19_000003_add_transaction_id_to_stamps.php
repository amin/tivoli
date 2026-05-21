<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stamps', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_id')->nullable()->after('stamptype_id');
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stamps', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropColumn('transaction_id');
        });
    }
};
