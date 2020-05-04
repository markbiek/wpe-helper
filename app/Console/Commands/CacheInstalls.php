<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

use App\Install;

class CacheInstalls extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'installs:cache';

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
        $this->call('installs:clear');

        $client = new Client();
        $url = "https://api.wpengineapi.com/v1/installs";
        $loop = 0;
        $max = 5;

        while (true) {
            $loop++;

            if ($loop >= $max) {
                throw new \Exception("Exceeded max pages");
            }

            $response = $client->get($url, [
                'auth' => [
                    getenv('WPENGINE_USER_NAME'),
                    getenv('WPENGINE_PASSWORD'),
                ]
            ]);

            $body = $stringBody = (string) $response->getBody();
            $data = json_decode($body);

            if ($loop == 1) {
                $bar = $this->output->createProgressBar($data->count);
                $bar->start();
            }

            foreach ($data->results as $item) {
                $bar->advance();
                $install = Install::make($item);

                if (!$install->exists) {
                    throw new \Exception("Error caching installs");
                }
            }

            if (empty($data->next)) {
                $bar->finish();
                break;
            }

            $url = $data->next;
        }
    }
}