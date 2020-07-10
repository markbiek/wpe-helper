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
		//TODO - disable this when we're done or make it an option?
		$git->streamOutput();

		$baseUrl = 'git@github.com:viastudio/%s.git';
		$repoUrl = sprintf($baseUrl, $install->repo_domain);
		Log::debug($repoUrl);

		$wcPath = "/tmp/{$install->name}";
		(new Filesystem())->remove($wcPath);

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
		$process = Proc::exec('npm install', $wcPath, $output);
		$process = Proc::exec('grunt setup', $wcPath, $output);

		$wc->reset(['hard' => true]);

		$process = Proc::exec(
			'wp plugin list --update=available --fields=name --format=json',
			$wcPath,
			$output,
		);
		$plugins = json_decode($output);

		$pluginBlacklist = [
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
			foreach ($pluginBlacklist as $pattern) {
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
					"wp plugin update {$plugin->name}",
					$wcPath,
					$output,
				);

				$pluginPath = "$wcPath/wp-content/plugins/{$plugin->name}";
				Log::debug($pluginPath);

				$wc->add($pluginPath);
				$wc->commit($pluginPath, [
					'no-verify' => true,
					'm' => "Plugin update: {$plugin->name}",
				]);
				$wc->push('origin', $branch);

				$pluginsUpdated[] = $plugin;
			} catch (\Exception $e) {
				Notify::send(new PluginUpdateError($e->getMessage()));
				return;
			}
		}

		Log::debug('Finished updating plugins');
		Log::debug($pluginsUpdated);
		Log::debug($pluginsSkipped);

		if (count($pluginsUpdated) <= 0) {
			Notify::send(new InstallNoUpdates($install));
			return;
		}

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

		if ($prCreated) {
			try {
				//Push to WPE
				$wc->remote(
					'add',
					'production',
					"git@git.wpengine.com:production/{$install->name}.git",
				);
				Proc::exec(['git', 'clean', '-d', '-fx'], $wcPath);
				Proc::exec(
					[
						'git',
						'pull',
						'-X',
						'theirs',
						'--no-edit',
						'production',
						'master',
					],
					$wcPath,
				);
				$wc->push('production', 'master', [
					'force' => true,
					'no-verify' => true,
				]);
				Log::debug('Finished deploying to production');
			} catch (\Exception $e) {
				Log::error('Production push error: ' . $e->getMessage());
				Notify::send(
					new InstallDeployError($install, $e->getMessage()),
				);
			}
		} else {
			Log::debug('Skipping production deploy because there was no PR');
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
