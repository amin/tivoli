<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stamps', function (Blueprint $table) {
            $table->unsignedBigInteger('source_amusement_id')->nullable()->after('stamptype_id');
            $table->foreign('source_amusement_id')->references('id')->on('amusements');
        });
    }

    public function down(): void
    {
        Schema::table('stamps', function (Blueprint $table) {
            $table->dropForeign(['source_amusement_id']);
            $table->dropColumn('source_amusement_id');
        });
    }
};
