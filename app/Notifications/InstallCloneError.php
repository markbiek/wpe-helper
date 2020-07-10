<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

use App\Install;

class InstallCloneError extends Notification {
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
		return ['slack'];
	}

	public function toSlack($notifiable) {
		return (new SlackMessage())
			->error()
			->content("Unable to clone repo for {$this->install->name}");
	}
}
