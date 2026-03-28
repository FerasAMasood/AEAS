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
        Schema::create('energy_balance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('source_id');
            $table->decimal('value', 15, 4)->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
            $table->foreign('source_id')->references('id')->on('energy_sources')->onDelete('cascade');
            
            // Unique constraint: one value per property per energy source
            $table->unique(['property_id', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('energy_balance');
    }
};
