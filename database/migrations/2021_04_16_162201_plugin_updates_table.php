<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PluginUpdatesTable extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		Schema::create('plugin_updates', function (Blueprint $table) {
			$table->id();
			$table
				->uuid('install_wpe_id')
				->nullable(false)
				->unique();
			$table->boolean('success')->nullable(true);
			$table->timestamp('last_update')->nullable(true);
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		Schema::dropIfExists('plugin_updates');
	}
}
