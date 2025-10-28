<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyDevicesTable extends Migration
{
    public function up()
    {
        Schema::create('property_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('lookups')->onDelete('cascade');
            $table->text('device_key');
            $table->text('description')->nullable();
            $table->float('factor');
            $table->float('power');
            $table->integer('quantity');
            $table->float('operation_hours');
            $table->float('total_consumption');
            $table->timestamps();

            // Foreign key constraint for device_key
            $table->foreign('device_key')
                ->references('lookup_key')
                ->on('lookups')
                ->where('lookups.lookup_field', 'devices')
                ->where('lookups.lookup_table', 'property_devices')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('property_devices');
    }
}

