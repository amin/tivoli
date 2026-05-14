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
        Schema::create('amusements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups');

            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('player_payout', 8, 2)->nullable();
            $table->decimal('amusement_balance', 8, 2)->default(0);
            $table->decimal('buffer_required', 8, 2)->default(0);
            $table->decimal('buffer_locked', 8, 2)->default(0);
            $table->string('url');
            $table->string('image_url')->nullable();
            $table->string('api_key');
            $table->enum('type', ['game', 'attraction']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amusements');
    }
};
