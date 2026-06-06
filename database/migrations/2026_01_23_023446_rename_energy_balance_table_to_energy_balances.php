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
        Schema::rename('energy_balance', 'energy_balances');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('energy_balances', 'energy_balance');
    }
};
