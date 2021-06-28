<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Install;

class ListInstalls extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'installs:list
        {--name-only : Only display the WPE install name}
        {--output=pretty : Options are json or pretty. This option is ignored if --name-only is set}
        {--development : Include development installs}
        {--staging : Include staging installs}
        {--inactive : Include inactive installs}
    ';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Display a list of installs.';

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
		if (Install::count() <= 0) {
			$this->info('Loading initial list of installs');

			$this->call('installs:cache');
		}

		$items = Install::matchQuery('', [
			'includeDev' => $this->option('development'),
			'includeStaging' => $this->option('staging'),
			'includeInactive' => $this->option('inactive'),
			'nameOnly' => $this->option('name-only'),
		])->get();

		foreach ($items as $item) {
			if (empty($item)) {
				$this->info("No matches found for {$install}");
				return;
			}

			if ($this->option('name-only')) {
				echo $item->name . "\n";
			} elseif ($this->option('output') == 'pretty') {
				$this->info("\nname:\t{$item->name}");
				$this->info("domain:\t{$item->primary_domain}");
				$this->info("environment:\t{$item->environment}");
				$this->info("url:\t{$item->url}");
			} else {
				echo json_encode($item->toArray());
			}
		}
	}
}
