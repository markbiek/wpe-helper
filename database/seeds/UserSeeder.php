<?php

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder {
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run() {
		if (
			DB::table('users')
				->select('email')
				->where('email', 'support@viastudio.com')
				->count() > 0
		) {
			echo 'The default notification user already exists.\n';
			return;
		}

		DB::table('users')->insert([
			'name' => 'VIA Support',
			'email' => 'support@viastudio.com',
			'password' => Hash::make('support@viastudio.com'),
		]);
	}
}
