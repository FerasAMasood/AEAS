<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreatePropertyDevicesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('property_devices')) {
            Schema::create('property_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
                $table->foreignId('category_id')->constrained('lookups')->onDelete('cascade');
                $table->string('device_key', 3);
                $table->text('description')->nullable();
                $table->float('factor');
                $table->float('power');
                $table->integer('quantity');
                $table->float('operation_hours');
                $table->float('total_consumption');
                $table->timestamps();

                // Foreign key constraint for device_key
                $table->foreign('device_key')
                    ->references('lookup_key')
                    ->on('lookups')
                    ->onDelete('cascade');
            });
        } else {
            // Table already exists, just add the foreign key if it doesn't exist
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'property_devices' 
                AND COLUMN_NAME = 'device_key' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            if (empty($foreignKeys)) {
                // First, modify device_key column if it's TEXT
                $columnInfo = DB::select("
                    SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'property_devices'
                    AND COLUMN_NAME = 'device_key'
                ");

                if (!empty($columnInfo) && $columnInfo[0]->DATA_TYPE === 'text') {
                    // Legacy rows often stored lookup_value (e.g. "Oven") instead of lookup_key ("O").
                    DB::statement('
                        UPDATE property_devices pd
                        INNER JOIN lookups l ON l.category = pd.category_id
                            AND l.lookup_value = pd.device_key
                        SET pd.device_key = l.lookup_key
                    ');
                    // Any remaining free-text keys must match an existing lookup_key before VARCHAR(3).
                    DB::statement("
                        UPDATE property_devices pd
                        LEFT JOIN lookups l ON l.lookup_key = pd.device_key
                        SET pd.device_key = 'Ref'
                        WHERE l.id IS NULL
                    ");
                    DB::statement('ALTER TABLE `property_devices` MODIFY `device_key` VARCHAR(3) NOT NULL');
                }

                // Add the foreign key constraint
                Schema::table('property_devices', function (Blueprint $table) {
                    $table->foreign('device_key')
                        ->references('lookup_key')
                        ->on('lookups')
                        ->onDelete('cascade');
                });
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('property_devices');
    }
}

