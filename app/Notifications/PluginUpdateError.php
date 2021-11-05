<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use App\Install;

class PluginUpdateError extends Notification {
	public $msg;
	public $plugin;
	public $install;

	/**
	 * Create a new notification instance.
	 *
	 * @return void
	 */
	public function __construct(Install $install, object $plugin, string $msg) {
		$this->install = $install;
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
		return ['mail'];
	}

	public function toMail($notifiable) {
		$msg = $this->msg;

		return (new MailMessage())
			->subject(
				"WPE Plugin Updates: Error updating {$this->install->name}",
			)
			->line($msg);
	}
}
