<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

use App\Install;

class DumpInstallDatabase extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'installs:dump-db
        {install : The install name to copy}
        {--staging : Include staging installs}
        {--raw : Dump the database uncompressed. Output is gzipped by default}
    ';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Dump the database for an install';

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
		//This is so we can automatically use the SSH key in other places
		Config::set('app.ssh_key', $this->option('ssh-key'));

		$install = Install::matchQuery(
			$this->argument('install'),
			$this->option('staging'),
			false,
			true,
		)->first();
		if (empty($install)) {
			$this->error('That install could not be found');
			return;
		}

		echo $install->dumpDatabase(!$this->option('raw'));
	}
}
