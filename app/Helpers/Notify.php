<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Notification;

use App\User;

class Notify {
	public static function send($notification) {
		Notification::send(User::all(), $notification);
	}
}
