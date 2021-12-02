<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

use App\Models\Install;

class InstallSSHCommand extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'installs:ssh
        {install : The install to locate}
        {--staging : Include staging installs}
        {--development : Include development installs}
    ';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Outputs the command to ssh into an install';

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
		$install = $this->argument('install');

		$items = Install::matchQuery($install, [
			'includeDev' => $this->option('development'),
			'includeStaging' => $this->option('staging'),
		])->get();

		if (count($items) <= 0) {
			$this->info("No matches found for {$install}");
			return;
		}

		foreach ($items as $install) {
			$this->info(
				"ssh {$install->name}@{$install->name}.ssh.wpengine.net\n",
			);
		}
	}
}