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
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->json('messages'); // Store conversation messages as JSON
            $table->timestamps();
            
            $table->foreign('report_id')->references('id')->on('reports')->onDelete('cascade');
            $table->unique('report_id'); // One conversation per report
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
