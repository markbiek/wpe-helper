<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInstallsTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('installs', function (Blueprint $table) {
            $table->id();
            $table->uuid('wpe_id')->nullable(false);
            $table->string('name')->nullable(false);
            $table->string('environment')->nullable(false);
            $table->string('primary_domain')->nullable(false);
            $table->boolean('active')->nullable(false);
            $table->string('php_version')->nullable(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('installs');
    }
}
