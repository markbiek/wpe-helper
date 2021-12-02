<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\DB;

use App\Models\EnvironmentTemplate;

class Setup extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'setup';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Initial app configuration';

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
		$envFile = __DIR__ . '/../../.env';
		if (file_exists($envFile)) {
			$this->error(
				'A .env environment file already exists. Please remove it and try setup again.',
			);
			return;
		}

		$dbHost = $this->ask('Database host:', '127.0.0.1');
		$dbName = $this->ask('Database name:');
		$dbUser = $this->ask('Database user:');
		$dbPass = $this->secret('Database password:');
		$wpeUser = $this->ask('WPEngine user:');
		$wpePass = $this->secret('WPEngine password:');

		$template = new EnvironmentTemplate();
		file_put_contents(
			$envFile,
			$template->render($dbHost, $dbName, $dbUser, $dbPass, $wpeUser, $wpePass),
		);
	}
}
