<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class WifiAddPrivateMac extends Migration
{
    private $tableName = 'wifi';

    public function up()
    {
        $capsule = new Capsule();

        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->string('private_mac_address')->nullable();
            $table->string('private_mac_mode_user')->nullable();
        });

        // Create indexes
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->index('private_mac_address');
            $table->index('private_mac_mode_user');
        });
    }

    public function down()
    {
        $capsule = new Capsule();
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->dropColumn('private_mac_address');
            $table->dropColumn('private_mac_mode_user');
        });
    }
}
