<?php

// database/migrations/xxxx_xx_xx_create_report_summaries_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportSummariesTable extends Migration
{
    public function up(): void
    {
        Schema::create('report_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->onDelete('cascade'); // Foreign key to reports table
            $table->longText('content'); // Long text for the styled content
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_summaries');
    }
}
