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
        Schema::table('general_recommendations', function (Blueprint $table) {
            $table->json('selected_status')->nullable()->after('recommendations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_recommendations', function (Blueprint $table) {
            $table->dropColumn('selected_status');
        });
    }
};
