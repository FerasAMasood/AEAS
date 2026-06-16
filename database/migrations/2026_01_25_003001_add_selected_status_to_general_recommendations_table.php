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
        if (Schema::hasColumn('general_recommendations', 'selected_status')) {
            return;
        }
        Schema::table('general_recommendations', function (Blueprint $table) {
            $table->json('selected_status')->nullable()->after('recommendations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('general_recommendations', 'selected_status')) {
            return;
        }
        Schema::table('general_recommendations', function (Blueprint $table) {
            $table->dropColumn('selected_status');
        });
    }
};
