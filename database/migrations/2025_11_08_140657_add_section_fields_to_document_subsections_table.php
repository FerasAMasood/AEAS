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
        Schema::table('document_subsections', function (Blueprint $table) {
            $table->foreignId('section_id')
                ->nullable()
                ->after('document_id')
                ->constrained('document_sections')
                ->cascadeOnDelete();
            $table->string('subsection_type')->default('text')->after('slug'); // 'text', 'images', 'pdf'
            $table->string('pdf_file')->nullable()->after('images'); // Path to uploaded PDF file
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_subsections', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropColumn(['section_id', 'subsection_type', 'pdf_file']);
        });
    }
};
