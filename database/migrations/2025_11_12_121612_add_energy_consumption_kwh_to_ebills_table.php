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
        Schema::table('ebills', function (Blueprint $table) {
            $table->decimal('energy_consumption_kwh', 10, 2)->nullable()->after('value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebills', function (Blueprint $table) {
            $table->dropColumn('energy_consumption_kwh');
        });
    }
};
