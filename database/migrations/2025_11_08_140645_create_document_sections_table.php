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
        Schema::create('document_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained('documents')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('section_type')->default('dynamic'); // 'fixed' or 'dynamic'
            $table->string('fixed_type')->nullable(); // 'summary', 'introduction', 'abbreviations', 'tariffs', 'bills'
            $table->unsignedInteger('position')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sections');
    }
};
