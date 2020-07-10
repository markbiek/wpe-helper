<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use App\Install;
use App\Jobs\UpdateInstallPlugins;

class InstallsPluginUpdate extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'installs:plugin-updates
    ';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Update all public plugins on all sites';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {
		$installs = Install::installsToUpdate();

		foreach ($installs as $install) {
			$this->info($install->name);

			UpdateInstallPlugins::dispatch($install->id);
		}
	}
}
