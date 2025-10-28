<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id');
            $table->string('report_title');
            $table->string('auditor_name');
            $table->date('date');
            $table->timestamps();
            
            // Assuming a properties table exists with a relationship
            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
