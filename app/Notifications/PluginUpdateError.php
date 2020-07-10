<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class PluginUpdateError extends Notification {
	public $msg;
	public $plugin;

	/**
	 * Create a new notification instance.
	 *
	 * @return void
	 */
	public function __construct(object $plugin, string $msg) {
		$this->plugin = $plugin;
		$this->msg = $msg;
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
		$msg = $this->msg;

		return (new SlackMessage())
			->error()
			->content("Error updating plugin: {$this->plugin->name}")
			->attachment(function ($attachment) use ($msg) {
				$attachment->title($msg)->content('');
			});
	}
}
