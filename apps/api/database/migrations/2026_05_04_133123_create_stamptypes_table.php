<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stamptypes', function (Blueprint $table) {
            $table->id();
            $table->enum('animal', ['lion', 'dolphin', 'toucan', 'beetlebug', 'snake']);
            $table->enum('metal', ['silver', 'gold', 'platinum'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamptypes');
    }
};
