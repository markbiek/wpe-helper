<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use App\Install;

class InstallNoUpdates extends Notification {
	public $install;

	/**
	 * Create a new notification instance.
	 *
	 * @return void
	 */
	public function __construct(Install $install) {
		$this->install = $install;
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
		$msg = "{$this->install->name} did not require any plugin updates";

		return (new MailMessage())
			->subject("WPE Plugin Updates: $msg")
			->line($msg);
	}
}
