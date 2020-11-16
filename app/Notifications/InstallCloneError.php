<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

use App\Install;

class InstallCloneError extends Notification {
	public $install;
	public $reason;
	public $repoUrl;

	/**
	 * Create a new notification instance.
	 *
	 * @return void
	 */
	public function __construct(
		Install $install,
		string $reason,
		string $repoUrl
	) {
		$this->install = $install;
		$this->reason = $reason;
		$this->repoUrl = $repoUrl;
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
			->content(
				"Unable to clone repo for {$this->install->name}: {$this->reason}\n({$this->repoUrl})",
			);
	}
}
