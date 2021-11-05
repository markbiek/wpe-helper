<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PluginUpdate extends Model {
	protected $fillable = ['install_wpe_id', 'success', 'last_update'];

	public function install() {
		return $this->hasOne('App\Install');
	}

	public function getRecentlyUpdatedAttribute(): ?bool {
		if (empty($this->success)) {
			return null;
		}
		if (empty($this->last_update)) {
			return null;
		}

		$today = new \DateTime(date('Y-m-d'));
		$lastUpdate = new \DateTime($this->last_update);
		$interval = $today->diff($lastUpdate);
		$days = (int) $interval->format('%a');

		return $this->success && $interval->days <= 0;
	}

	public function setSuccess() {
		$this->update([
			'success' => true,
			'last_update' => date('Y-m-d H:i:s'),
		]);
	}

	public function setFailed() {
		$this->update([
			'success' => false,
			'last_update' => null,
		]);
	}
}
