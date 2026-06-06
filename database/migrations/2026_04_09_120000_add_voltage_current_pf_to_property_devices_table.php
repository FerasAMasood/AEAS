<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_devices', function (Blueprint $table) {
            $table->decimal('voltage', 12, 4)->nullable()->after('factor');
            $table->decimal('current_amps', 12, 4)->nullable()->after('voltage');
            $table->decimal('power_factor', 8, 6)->nullable()->after('current_amps');
        });
    }

    public function down(): void
    {
        Schema::table('property_devices', function (Blueprint $table) {
            $table->dropColumn(['voltage', 'current_amps', 'power_factor']);
        });
    }
};
