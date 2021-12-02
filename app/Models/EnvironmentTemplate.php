<?php

namespace App\Models;

class EnvironmentTemplate {
	protected $template = <<<EOT
	APP_NAME="WPE Helper"
	APP_ENV=prod
	APP_DEBUG=false

	LOG_CHANNEL=stack

	DB_CONNECTION=mysql
	DB_HOST=
	DB_DATABASE=
	DB_USERNAME=
	DB_PASSWORD=

	BROADCAST_DRIVER=log
	CACHE_DRIVER=file
	QUEUE_CONNECTION=sync
	SESSION_DRIVER=file
	SESSION_LIFETIME=120

	WPE_USER_NAME=
	WPE_PASSWORD=
	EOT;

	public function render(
		string $dbHost,
		string $dbName,
		string $dbUser,
		string $dbPass,
		string $wpeUser,
		string $wpePass
	) {
		$data = [
			'DB_HOST=' => "DB_HOST=$dbHost",
			'DB_DATABASE=' => "DB_DATABASE=$dbName",
			'DB_USERNAME=' => "DB_USERNAME=$dbUser",
			'DB_PASSWORD=' => "DB_PASSWORD=$dbPass",
			'WPE_USER_NAME=' => "WPE_USER_NAME=$wpeUser",
			'WPE_PASSWORD=' => "WPE_PASSWORD=$wpePass",
		];
		$template = $this->template;

		foreach ($data as $search => $replace) {
			$template = str_replace($search, $replace, $template);
		}

		return $template;
	}
}
