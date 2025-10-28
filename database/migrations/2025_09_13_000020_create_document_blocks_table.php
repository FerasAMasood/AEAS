<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('document_blocks', function (Blueprint $table) {
            $table->id();

            // 👇 Add this here
            $table->foreignId('document_id')
                ->constrained('documents')
                ->cascadeOnDelete();

            $table->enum('block_type', ['introduction','abbreviations','summary','tariffs','subsection']);
            $table->foreignId('subsection_id')
                ->nullable()
                ->constrained('document_subsections')
                ->cascadeOnDelete();

            $table->unsignedInteger('position');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('document_blocks');
    }
};
