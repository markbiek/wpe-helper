<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPluginUpdate extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('installs', function (Blueprint $table) {
            $table->boolean('plugin_updates')->after('php_version')->nullable(false)->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('installs', function (Blueprint $table) {
            $table->dropColumn('plugin_updates');
        });
    }
}
