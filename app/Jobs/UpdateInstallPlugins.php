<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Filesystem\Filesystem;
use GitWrapper\{GitWrapper, GitWorkingCopy};
use GitWrapper\Exception\GitException;

use App\Helpers\Proc;
use App\Helpers\Notify;
use App\Notifications\PluginUpdateError;
use App\Notifications\InstallCloneError;
use App\Notifications\InstallPrError;
use App\Notifications\InstallDeployError;
use App\Notifications\InstallTagError;
use App\Notifications\InstallUpdateReport;
use App\Notifications\InstallNoUpdates;
use App\Install;

class UpdateInstallPlugins implements ShouldQueue {
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	const GIT_USERNAME = 'viabot';
	const GIT_EMAIL = 'dev@viastudio.com';

	public $timout = 600;

	protected $installId;
	protected $opts;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct(int $installId, array $opts = []) {
		$this->installId = $installId;
		$this->opts = $opts;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle() {
		$install = Install::where('id', $this->installId)->firstOrFail();
		Log::debug("UpdateInstallPlugins: {$install->name}");

		$git = new GitWrapper();
		$git->setTimeout(600); //10 minutes

		if ($this->opts['git-output']) {
			$git->streamOutput();
		}

		$baseUrl = 'git@github.com:viastudio/%s.git';
		$repoUrl = sprintf($baseUrl, $install->repo_domain);
		Log::debug("repo: $repoUrl");

		try {
			$wcPath = "/tmp/{$install->name}";
			(new Filesystem())->remove($wcPath);
		} catch (\Exception $e) {
			Log::error("Error removing {$install->name}: " . $e->getMessage());
		}

		$max = 2;
		$tries = 0;
		while (true) {
			try {
				$tries++;
				if ($tries > $max) {
					Log::error(
						"{$install->name} exceeded number of clone retries",
					);
					break;
				}

				$wc = $git->cloneRepository($repoUrl, $wcPath);

				$wc->config('user.name', static::GIT_USERNAME);
				$wc->config('user.email', static::GIT_EMAIL);
				break;
			} catch (GitException $e) {
				if (stripos($e->getMessage(), 'not found')) {
					//Repo wasn't found, try stripping off any subdomains
					$domain = preg_replace(
						'/^(.*?)\./',
						'',
						$install->primary_domain,
					);
					$repoUrl = sprintf($baseUrl, $domain);
				} else {
					Log::error(
						"Error cloning {$install->name}: " . $e->getMessage(),
					);
				}
			}
		}

		//We exceeded the number of tries to clone so we abort updating this site
		if ($tries > $max) {
			Log::debug("Unable to clone {$install->name}");
			Log::debug($repoUrl);
			Notify::send(new InstallCloneError($install));
			return;
		}

		// Start updating plugins
		$today = new \DateTime();
		$tstamp = $today->format('Y-m-d-H-i-s');
		$branch = "auto-plugin-updates-{$tstamp}";
		$tagName = 'plugin-updates-' . $today->format('Y-m-d-H-i-s');

		$wc->checkoutNewBranch($branch);

		$output = '';
		// TODO - remove
		//$process = Proc::exec('npm install', $wcPath, $output);
		//$process = Proc::exec('grunt setup', $wcPath, $output);

		$wc->reset(['hard' => true]);

		// Get the list of plugins to update from the remote install
		$remoteArgs = "--ssh={$install->name}@{$install->name}.ssh.wpengine.net";

		$tries = 0;
		$max = 10;
		while (true) {
			try {
				$tries++;

				$process = Proc::exec(
					"wp plugin list --update=available --fields=name --format=json $remoteArgs",
					$wcPath,
					$output,
				);

				break;
			} catch (\Exception $e) {
				if ($tries >= $max) {
					Log::error(
						"Couldn't get plugin list for {$install->name} after $tries attempts",
					);
					throw $e;
				}
			}
		}
		$plugins = json_decode($output);

		$pluginSkipList = [
			'/js_composer/i',
			'/.*woo.*$/i', //woocommerce plugins
			'/quick-pagepost-redirect-plugin/i',
			'/gravityformshighrise/i',
			'/the-events-calendar/i',
			'/events-calendar-pro/i',
			'/healcode-mindbody-widget/i',
			'/elegantbuilder/i',
			'/keyring/i',
			'/event-list/i',
			'/wp-e-commerce/i',
		];
		$pluginsUpdated = [];
		$pluginsSkipped = [];
		foreach ($plugins as $plugin) {
			$skip = false;
			foreach ($pluginSkipList as $pattern) {
				if (preg_match($pattern, $plugin->name) > 0) {
					$skip = true;
					$pluginsSkipped[] = $plugin;
					break;
				}
			}

			if ($skip) {
				Log::info(
					"{$install->name}: Skipping {$plugin->name} because it was blacklisted",
				);
				continue;
			}

			try {
				Log::debug("{$install->name}: Updating {$plugin->name}");
				$process = Proc::exec(
					"wp plugin update {$plugin->name} $remoteArgs",
					$wcPath,
					$output,
				);

				$pluginPath = "$wcPath/wp-content/plugins/{$plugin->name}";
				Log::debug($pluginPath);

				/*
				$wc->add($pluginPath);
				$wc->commit($pluginPath, [
					'no-verify' => true,
					'm' => "Plugin update: {$plugin->name}",
				]);
                $wc->push('origin', $branch);
                */

				$pluginsUpdated[] = $plugin;
			} catch (\Exception $e) {
				Notify::send(new PluginUpdateError($e->getMessage()));
				return;
			}
		}

		Log::debug('Finished updating plugins');
		Log::debug($pluginsUpdated);
		Log::debug($pluginsSkipped);

		// No plugins were updated
		if (count($pluginsUpdated) <= 0) {
			Notify::send(new InstallNoUpdates($install));
			return;
		}

		// rsync files from production
		Log::debug('Syncing files from production');
		$process = Proc::exec(
			"rsync -avzu -e ssh {$install->name}@{$install->name}.ssh.wpengine.net:\"~/sites/{$install->name}/wp-content/plugins\" ./wp-content",
			$wcPath,
			$output,
		);
		Log::debug($wcPath);
		Log::debug($output);

		// Commit to Github and PR
		$wc->add($wcPath);
		$wc->commit($wcPath, [
			'no-verify' => true,
			'm' => 'Plugin updates',
		]);
		$wc->push('origin', $branch);

		try {
			$prCreated = false;
			// PR and merge
			$process = Proc::exec(
				[
					'gh',
					'pr',
					'create',
					'--title',
					'Automated plugin updates',
					'--body',
					'Automated plugin updates',
				],
				$wcPath,
			);

			sleep(30); // Give the PR time to finish setting up
			$process = Proc::exec(['gh', 'pr', 'merge', '--merge'], $wcPath);
			$prCreated = true;
			Log::debug('Finished creating PR');
		} catch (\Exception $e) {
			$msg = $e->getMessage();

			Log::error('PR error: ' . $e->getMessage());
			Notify::send(new InstallPrError($install, $e->getMessage()));
		}

		try {
			//Tag change
			$wc->tag($tagName);
			$wc->pushTag($tagName);
		} catch (\Exception $e) {
			Log::error('Tag error: ' . $e->getMessage());
			Notify::send(new InstallTagError($install, $e->getMessage()));
		}

		Notify::send(
			new InstallUpdateReport($install, $pluginsUpdated, $pluginsSkipped),
		);
	}
}
