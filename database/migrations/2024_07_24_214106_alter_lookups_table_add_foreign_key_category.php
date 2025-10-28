<?php

// database/migrations/YYYY_MM_DD_HHMMSS_alter_lookups_table_add_foreign_key_category.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterLookupsTableAddForeignKeyCategory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lookups', function (Blueprint $table) {
            $table->unsignedBigInteger('category')->nullable()->change();
            $table->foreign('category')->references('id')->on('lookups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lookups', function (Blueprint $table) {
            $table->dropForeign(['category']);
            $table->string('category')->nullable()->change();
        });
    }
}
