<?php

namespace App\Actions;

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

class UpdateInstallPlugins {
	const GIT_USERNAME = 'viabot';
	const GIT_EMAIL = 'dev@viastudio.com';

	public static function execute(Install $install, array $opts) {
		Log::debug("UpdateInstallPlugins: {$install->name}");

		// Initalize git stuff
		$git = new GitWrapper();
		$git->setTimeout(600); //10 minutes

		if ($opts['git-output']) {
			$git->streamOutput();
		}

		$baseUrl = 'git@github.com:viastudio/%s.git';
		$repoUrl = sprintf($baseUrl, $install->repo_domain);
		Log::debug("repo: $repoUrl");

		// Remove the existing working copy
		try {
			$wcPath = "/tmp/{$install->name}";
			(new Filesystem())->remove($wcPath);
		} catch (\Exception $e) {
			Log::error("Error removing {$install->name}: " . $e->getMessage());
		}

		// First get a list of plugins that need updates
		$plugins = self::getUpdateablePlugins($install);

		// No plugins needed updating so we can stop processing this install
		if (count($plugins) <= 0) {
			Log::debug("{$install->name} did not have any plugins to update");
			Notify::send(new InstallNoUpdates($install));
			return;
		}

		// Go through and update all remote plugins
		$remoteArgs = "--ssh={$install->name}@{$install->name}.ssh.wpengine.net";
		$pluginsUpdated = [];
		foreach ($plugins as $plugin) {
			try {
				Log::debug("{$install->name}: Updating {$plugin->name}");
				$process = Proc::exec(
					"wp plugin update {$plugin->name} $remoteArgs",
					__DIR__,
					$output,
				);

				$pluginsUpdated[] = $plugin;
			} catch (\Exception $e) {
				Log::error('PluginUpdateError: ' . $e->getMessage());
				Notify::send(new PluginUpdateError($plugin, $e->getMessage()));
				return;
			}
		}

		Log::debug('Finished updating plugins');
		Log::debug($pluginsUpdated);

		// Once we've finished updating the plugins, we need to clone the repo and sync down the changes
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

		// Create a new branch for the plugin updates
		$today = new \DateTime();
		$tstamp = $today->format('Y-m-d-H-i-s');
		$branch = "auto-plugin-updates-{$tstamp}";
		$tagName = 'plugin-updates-' . $today->format('Y-m-d-H-i-s');

		$wc->checkoutNewBranch($branch);

		$output = '';

		$wc->reset(['hard' => true]);

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

	protected static function getUpdateablePlugins(Install $install): array {
		$remoteArgs = "--ssh={$install->name}@{$install->name}.ssh.wpengine.net";

		$output = '';
		$tries = 0;
		$max = 10;
		while (true) {
			try {
				$tries++;

				$process = Proc::exec(
					"wp plugin list --update=available --fields=name --format=json $remoteArgs",
					__DIR__,
					$output,
				);

				Log::debug("output=($output)");

				break;
			} catch (\Exception $e) {
				if ($tries >= $max) {
					Log::error(
						"Couldn't get plugin list for {$install->name} after $tries attempts",
					);
					Log::error($e->getMessage());
					Log::error($output);
					throw $e;
				}
			}
		}
		$plugins = json_decode($output);
		if (!$plugins) {
			$plugins = [];
		}

		$pluginSkipList = self::getPluginSkipList();
		$pluginsToUpdate = [];
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

			$pluginsToUpdate[] = $plugin;
		}

		return $pluginsToUpdate;
	}

	protected static function getPluginSkipList(): array {
		return [
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
	}
}
