<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('property_name');
            $table->enum('property_type', ['warehouse', 'apartment', 'separate house', 'building']);
            $table->enum('property_usage', ['residential', 'industrial', 'managerial', 'commercial']);
            $table->integer('floor_number');
            $table->float('property_area');
            $table->integer('number_of_rooms');
            $table->string('property_isolation_type');
            $table->string('property_address');
            $table->string('property_description')->nullable();
            $table->integer('number_of_floors');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
