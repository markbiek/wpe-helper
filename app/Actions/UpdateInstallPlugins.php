<?php

namespace App\Actions;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Filesystem\Filesystem;
use GitWrapper\{GitWrapper, GitWorkingCopy};
use GitWrapper\Exception\GitException;

use App\Helpers\Proc;
use App\Helpers\Notify;
use App\Notifications\PluginUpdateError;
use App\Notifications\InstallUpdateReport;
use App\Notifications\InstallNoUpdates;
use App\{Install, PluginUpdate};

class UpdateInstallPlugins {
	const GIT_USERNAME = 'viabot';
	const GIT_EMAIL = 'dev@viastudio.com';

	public static function info(
		string $msg,
		array $data = [],
		\Illuminate\Console\Command $cmd = null
	) {
		Log::channel('plugins')->info($msg, $data);

		if (!empty($cmd)) {
			$cmd->info($msg);
		}
	}

	public static function error(
		string $msg,
		array $data = [],
		\Illuminate\Console\Command $cmd = null
	) {
		Log::channel('plugins')->error($msg, $data);

		if (!empty($cmd)) {
			$cmd->error($msg);
		}
	}

	public static function debug(
		string $msg,
		array $data = [],
		\Illuminate\Console\Command $cmd = null
	) {
		Log::channel('plugins')->debug($msg, $data);

		if (!empty($cmd)) {
			$cmd->line($msg);
		}
	}

	public static function execute(
		Install $install,
		\Illuminate\Console\Command $cmd
	) {
		self::info("Updating {$install->name} plugins");

		// Make sure the plugin update entry exists
		$install->addPluginUpdate();
		$install->refresh();

		if (
			!$cmd->option('force') &&
			$install->plugin_update->recently_updated === true
		) {
			self::info(
				"{$install->name} was skipped because it was updated recently. Use --force to override",
			);

			return;
		}

		// First get a list of plugins that need updates
		[$plugins, $pluginsSkipped] = self::getUpdateablePlugins($install);

		// No plugins needed updating so we can stop processing this install
		if (count($plugins) <= 0) {
			$install->plugin_update->setSuccess();

			self::info("{$install->name} did not have any plugins to update");
			Notify::send(new InstallNoUpdates($install));
			return;
		}

		// Go through and update all remote plugins
		$remoteArgs = "--ssh={$install->name}@{$install->name}.ssh.wpengine.net";
		$pluginsUpdated = [];
		foreach ($plugins as $plugin) {
			try {
				self::info("{$install->name}: Updating {$plugin->name}");
				$process = Proc::exec(
					"wp plugin update {$plugin->name} $remoteArgs",
					__DIR__,
					$output,
				);

				$pluginsUpdated[] = $plugin;
			} catch (\Exception $e) {
				$install->plugin_update->setFailed();

				self::error('PluginUpdateError: ' . $e->getMessage());
				Notify::send(
					new PluginUpdateError($install, $plugin, $e->getMessage()),
				);
				return;
			}
		}

		self::info('Finished updating plugins', [
			'plugins' => $pluginsUpdated,
		]);

		$install->plugin_update->setSuccess();

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
					"wp plugin list --update=available --status=active --fields=name --format=json $remoteArgs",
					__DIR__,
					$output,
				);

				self::debug("output=($output)");

				break;
			} catch (\Exception $e) {
				if ($tries >= $max) {
					self::error(
						"Couldn't get plugin list for {$install->name} after $tries attempts",
						[
							'err' => $e->getMessage(),
							'output' => $output,
						],
					);

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
				self::info(
					"{$install->name}: Skipping {$plugin->name} because it was blacklisted",
				);
				continue;
			}

			$pluginsToUpdate[] = $plugin;
		}

		return [$pluginsToUpdate, $pluginsSkipped];
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
			'/advanced-custom-fields-pro/',
		];
	}
}
