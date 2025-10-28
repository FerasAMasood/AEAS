<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_introductions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntroductionsTable extends Migration
{
    public function up()
    {
        Schema::create('introductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->onDelete('cascade');
            $table->text('content'); // Storing long text for introduction
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('introductions');
    }
}
