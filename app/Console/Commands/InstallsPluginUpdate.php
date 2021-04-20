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
        {--install= : Only update the specified install}
		{--force : Run the update, even if an install was recently updated.}
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
		if (!empty($this->option('install'))) {
			$installs = Install::where('name', $this->option('install'))->get();
		} else {
			$installs = Install::installsToUpdate();
		}

		$installSkipList = [
			'benmarcum',
			'chefedwardlee',
			'customwigco',
			'guestlist',
			'kpr',
			'leadcolin',
			'lpm01',
			'lpmcampaign',
			'lpmimpact',
			'ovoinc',
			'ovr',
			'starbusiness',
			'viastudio',
			'viapress',
			'wfpk',
			'wfpl',
			'wfplarchives',
			'wfplatrisk',
			'wfplenergy',
			'wfplnextlou',
			'wfplsick',
			'wuol',
		];

		foreach ($installs as $install) {
			if (in_array($install->name, $installSkipList)) {
				continue;
			}

			try {
				\App\Actions\UpdateInstallPlugins::execute($install, $this);
			} catch (\Exception $e) {
				$this->error(
					"Error updating install {$install->name}: " .
						$e->getMessage(),
				);

				Log::channel('plugins')->error('Error updating install', [
					'install' => $install,
					'error' => $e->getMessage(),
				]);
			}
		}
	}
}
