<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
		return ['mail'];
	}

	public function toMail($notifiable) {
		$pluginsUpdated = $this->pluginsUpdated;
		$pluginsSkipped = $this->pluginsSkipped;

		return (new MailMessage())
			->subject(
				"WPE Plugin Updates: Update report for {$this->install->name}",
			)
			->markdown('mail.plugin-updates.update-report', [
				'install' => $this->install,
				'updated' => $pluginsUpdated,
				'skipped' => $pluginsSkipped,
			]);
	}
}
