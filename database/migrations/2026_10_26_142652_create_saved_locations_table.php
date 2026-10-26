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
        Schema::create('saved_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('city_id')->comment('Open-Meteo geocoding identifier');
            $table->string('name');
            $table->string('region')->nullable();
            $table->string('department')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 8, 5);
            $table->decimal('longitude', 8, 5);
            $table->timestamps();

            $table->unique(['user_id', 'city_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_locations');
    }
};
