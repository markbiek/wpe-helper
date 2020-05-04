<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use App\Install;

class FindInstall extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'installs:find
        {install : The install to find}
        {--name-only : Only display the WPE install name}
        {--output=pretty : Options are json or pretty. This option is ignored if --name-only is set}
        {--staging : Include staging installs}
        {--inactive : Include inactive installs}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find the details of an install.';

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
        $includeStaging = $this->option('staging');
        $includeInactive = $this->option('inactive');
        $nameOnly = $this->option('name-only');

        $query = Install::where(DB::raw('true'), true);
        if (!$includeStaging) {
            $query = $query->where('environment', 'production');
        }
        if (!$includeInactive) {
            $query = $query->where('active', true);
        }

        $query->where(function ($query) use ($install) {
            $query
                ->where('name', 'LIKE', "%{$install}%")
                ->orWhere('primary_domain', 'LIKE', "%{$install}%");
        });

        $item = $query->first();

        if (empty($item)) {
            $this->info("No matches found for {$install}");
            return;
        }

        if ($this->option('name-only')) {
            echo $item->name;
        } else if ($this->option('output') == 'pretty') {
            $this->info("name:\t{$item->name}");
            $this->info("domain:\t{$item->primary_domain}");
            $this->info("environment:\t{$item->environment}");
            $this->info("url:\t{$item->url}");
        } else {
            echo json_encode($item->toArray());
        }
    }
}
