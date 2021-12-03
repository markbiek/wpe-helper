<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model {
	protected $fillable = ['key', 'value'];

	public static function set(string $key, string $value): void {
		Setting::updateOrCreate(
			[
				'key' => $key,
			],
			[
				'key' => $key,
				'value' => $value,
			],
		);
	}

	public static function get(string $key): ?string {
		if (!Setting::has($key)) {
			return null;
		}

		$setting = Setting::where('key', $key)->first();

		return $setting->value;
	}

	public static function has(string $key): bool {
		return Setting::where('key', $key)->exists();
	}

	public static function unset(string $key): ?string {
		$setting = Setting::where('key', $key)->first();
		if (!$setting) {
			return null;
		}

		$value = $setting->value;
		$setting->delete();

		return $value;
	}

	public static function iterable(): iterable {
		return Setting::all();
	}
}
