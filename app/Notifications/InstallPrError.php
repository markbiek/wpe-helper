<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

use App\Install;

class InstallPrError extends Notification {
	public $install;
	public $msg;

	/**
	 * Create a new notification instance.
	 *
	 * @return void
	 */
	public function __construct(Install $install, string $msg) {
		$this->install = $install;
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
			->content("Unable to create PR for {$this->install->name}")
			->attachment(function ($attachment) use ($msg) {
				$attachment->title($msg)->content('');
			});
	}
}
