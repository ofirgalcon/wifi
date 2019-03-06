<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class WifiRewriteColumns extends Migration
{
    private $tableName = 'wifi';

    public function up()
    {
        $capsule = new Capsule();
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->integer('snr')->nullable();
            $table->mediumText('known_networks')->nullable();
            
            $table->index('agrctlrssi');
            $table->index('agrextrssi');
            $table->index('agrctlnoise');
            $table->index('agrextnoise');
            $table->index('op_mode');
            $table->index('lasttxrate');
            $table->index('lastassocstatus');
            $table->index('maxrate');
            $table->index('x802_11_auth');
            $table->index('link_auth');
            $table->index('mcs');
            $table->index('channel');
            $table->index('snr');
        });
    }
    
    public function down()
    {
        $capsule = new Capsule();
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->dropColumn('snr');
            $table->dropColumn('known_networks');       
        });
    }
}
