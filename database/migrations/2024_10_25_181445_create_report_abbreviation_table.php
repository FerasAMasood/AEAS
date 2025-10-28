<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_abbreviation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->unsignedBigInteger('abbreviation_id');
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('report_id')->references('id')->on('reports')->onDelete('cascade');
            $table->foreign('abbreviation_id')->references('id')->on('abbreviations')->onDelete('cascade');
            
            // Ensure uniqueness for each report and abbreviation pair
            $table->unique(['report_id', 'abbreviation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_abbreviation');
    }
};
