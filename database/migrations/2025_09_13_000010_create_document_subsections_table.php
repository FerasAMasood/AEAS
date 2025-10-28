<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('document_subsections', function (Blueprint $table) {
            $table->id();

            // 👇 Add this here
            $table->foreignId('document_id')
                ->constrained('documents')
                ->cascadeOnDelete();

            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->longText('content_html');
            $table->json('images')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('document_subsections');
    }
};
