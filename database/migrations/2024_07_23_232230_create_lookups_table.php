<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLookupsTable extends Migration
{
    public function up()
    {
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('lookup_key', 3)->index();
            $table->string('lookup_table')->index();
            $table->string('lookup_field')->index();
            $table->string('category')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lookups');
    }
}
