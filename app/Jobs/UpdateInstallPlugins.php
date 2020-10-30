<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Filesystem\Filesystem;
use GitWrapper\{GitWrapper, GitWorkingCopy};
use GitWrapper\Exception\GitException;

class UpdateInstallPlugins implements ShouldQueue {
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $timout = 600;

	protected $installId;
	protected $opts;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct(int $installId, array $opts = []) {
		$this->installId = $installId;
		$this->opts = $opts;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle() {
		$install = Install::where('id', $this->installId)->firstOrFail();

		\App\Actions\UpdateInstallPlugins::execute($install, $this->opts);
	}
}
