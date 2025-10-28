<?php

// database/migrations/2024_07_24_create_abbreviations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAbbreviationsTable extends Migration
{
    public function up()
    {
        Schema::create('abbreviations', function (Blueprint $table) {
            $table->id();
            $table->string('abbreviation')->unique();
            $table->string('meaning');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('abbreviations');
    }
}
