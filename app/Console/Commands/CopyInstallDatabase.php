<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

use App\Install;

class CopyInstallDatabase extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'installs:copy-db
        {install : The install name to copy}
        {destination : dev|local}
        {-k | --ssh-key=/Users/mark/.ssh/id_rsa}
    ';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command description';

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
		$dest = $this->argument('destination');

		if (!in_array($dest, ['local', 'dev'])) {
			$this->error('Invalid destination (must be local or dev)');
			return;
		}

		//This is so we can automatically use the SSH key in other places
		Config::set('app.ssh_key', $this->option('ssh-key'));

		$install = Install::matchQuery(
			$this->argument('install'),
			false,
			false,
			true,
		)->first();

		$install->copyDatabase($dest);
	}
}
