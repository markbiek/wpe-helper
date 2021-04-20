<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Notification;

use App\User;

class Notify {
	public static function send($notification) {
		$users = User::all()->toArray();
		if (empty($users)) {

		}

		Notification::send(User::all(), $notification);
	}
}
