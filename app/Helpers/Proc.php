<?php

namespace App\Helpers;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class Proc {
	public static function exec($cmd, $cwd, &$output = null, $timeout = 300) {
		if (!is_array($cmd)) {
			$cmd = explode(' ', $cmd);
		}
		Log::debug($cmd);
		$process = new Process($cmd, $cwd);
		$process->setTimeout($timeout);

		try {
			$process->mustRun();
		} catch (ProcessFailedException $e) {
			throw $e;
		}

		if ($process->isSuccessful() && $output !== null) {
			$output = $process->getOutput();
		}

		return $process;
	}
}
