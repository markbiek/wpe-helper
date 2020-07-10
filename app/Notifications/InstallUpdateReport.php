<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

use App\Install;

class InstallUpdateReport extends Notification {
	public $install;
	public $pluginsUpdated;
	public $pluginsSkipped;

	/**
	 * Create a new notification instance.
	 *
	 * @return void
	 */
	public function __construct(
		Install $install,
		array $pluginsUpdated,
		array $pluginsSkipped
	) {
		$this->install = $install;
		$this->pluginsUpdated = $pluginsUpdated;
		$this->pluginsSkipped = $pluginsSkipped;
	}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @param  mixed  $notifiable
	 * @return array
	 */
	public function via($notifiable) {
		return ['slack'];
	}

	public function toSlack($notifiable) {
		$pluginsUpdated = $this->pluginsUpdated;
		$pluginsSkipped = $this->pluginsSkipped;

		return (new SlackMessage())
			->error()
			->content("Plugin update report for: {$this->install->name}")
			->attachment(function ($attachment) use ($pluginsUpdated) {
				$content = '';
				foreach ($pluginsUpdated as $plugin) {
					$content .= "* {$plugin->name}\n";
				}
				$attachment->title('Updated:')->content($content);
			})
			->attachment(function ($attachment) use ($pluginsSkipped) {
				$content = '';
				foreach ($pluginsSkipped as $plugin) {
					$content .= "* {$plugin->name}\n";
				}
				$attachment->title('Skipped:')->content($content);
			});
	}
}
