<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLookupValueToLookupsTable extends Migration
{
    public function up()
    {
        Schema::table('lookups', function (Blueprint $table) {
            $table->string('lookup_value')->after('lookup_field');
        });
    }

    public function down()
    {
        Schema::table('lookups', function (Blueprint $table) {
            $table->dropColumn('lookup_value');
        });
    }
}
