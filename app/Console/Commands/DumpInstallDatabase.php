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
        {--development : Include development installs}
        {--staging : Include staging installs}
        {--raw : Dump the database uncompressed. Output is gzipped by default}
		{--ssh-key= : The ssh key to use for connections}
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
		$defaultKey = env('HOME') . '/.ssh/id_rsa';

		if ($this->option('ssh-key')) {
			$sshKey = $this->option('ssh-key');
		} elseif (file_exists($defaultKey)) {
			$sshKey = $defaultKey;
		} else {
			$this->error('Could not locate a valid ssh key to connect with.');
			return 1;
		}

		//This is so we can automatically use the SSH key in other places
		Config::set('app.ssh_key', $sshKey);

		$install = Install::matchQuery($this->argument('install'), [
			'includeDev' => $this->option('development'),
			'includeStaging' => $this->option('staging'),
			'includeInactive' => false,
			'nameOnly' => true,
		])->first();
		if (empty($install)) {
			$this->error('That install could not be found');
			return;
		}

		echo $install->dumpDatabase(!$this->option('raw'));
	}
}
