<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class WifiAddBssidAlias extends Migration
{
    private $tableName = 'wifi';

    public function up()
    {
        $capsule = new Capsule();

        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->string('bssid_alias')->nullable();
        });

        // Create index
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->index('bssid_alias');
        });
    }

    public function down()
    {
        $capsule = new Capsule();
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->dropColumn('bssid_alias');
        });
    }
} 