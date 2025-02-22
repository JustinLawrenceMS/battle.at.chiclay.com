<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('race');
            $table->string('class');
            $table->integer('level')->default(1);
            $table->integer('experience')->default(0);
            $table->string('alignment')->nullable();
            $table->string('background')->nullable();
            $table->integer('strength');
            $table->integer('dexterity');
            $table->integer('constitution');
            $table->integer('intelligence');
            $table->integer('wisdom');
            $table->integer('charisma');
            $table->integer('armor_class')->default(10);
            $table->integer('hit_points')->default(10);
            $table->integer('speed')->default(30);
            $table->json('inventory')->nullable(); // Store items as JSON
            $table->json('abilities')->nullable(); // Store abilities and spells as JSON
            $table->json('features')->nullable(); // Feats, racial traits, etc.
            $table->text('notes')->nullable();
i//            $table->foreignId('player_id')->constrained('users')->onDelete('cascade'); // Assuming players are users
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
