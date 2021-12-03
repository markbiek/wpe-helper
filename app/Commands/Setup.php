<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\DB;

use App\Models\Setting;

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
		$path = config('database.connections.sqlite.database');
		$pathInfo = pathinfo($path);
		if (!file_exists($pathInfo['dirname'])) {
			mkdir($pathInfo['dirname']);

			$source = __DIR__ . '/../../database/database.sqlite';
			copy($source, $path);
		}

		$this->call('migrate');

		Setting::unset('WPE_USER_NAME');
		Setting::unset('WPE_PASSWORD');

		$wpeUser = $this->ask('WPEngine user:');
		$wpePass = $this->secret('WPEngine password:');

		Setting::set('WPE_USER_NAME', $wpeUser);
		Setting::set('WPE_PASSWORD', $wpePass);

		$this->call('installs:clear');
		$this->call('installs:cache');

		$this->info('Setup complete');
	}
}
