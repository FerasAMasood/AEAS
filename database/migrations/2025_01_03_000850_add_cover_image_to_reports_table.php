<?php

// Add this in a new migration file
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->after('auditor_name');
        });
    }

    public function down(): void {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('cover_image');
        });
    }
};
