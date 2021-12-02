<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;

class Install extends Model {
	protected $primaryKey = 'wpe_id';
	protected $keyType = 'string';

	protected $fillable = [
		'wpe_id',
		'name',
		'environment',
		'primary_domain',
		'active',
		'php_version',
	];

	protected $appends = ['url'];

	public function getRepoDomainAttribute() {
		return preg_replace('/^www\./', '', $this->primary_domain);
	}

	public function getUrlAttribute() {
		return "https://my.wpengine.com/installs/{$this->name}";
	}

	public function getSshHostAttribute() {
		return "{$this->name}.ssh.wpengine.net";
	}

	public function getDomainAttribute() {
		return preg_replace('/.[a-z]+$/', '', $this->primary_domain);
	}

	public function getLocalDbNameAttribute() {
		return preg_replace('/[^a-z0-9]{1,}/', '_', $this->domain);
	}

	public function getRemoteDbNameAttribute() {
		$dbName = null;

		\SSH::into($this->name)->run(
			["cd sites/{$this->name}", 'wp config get DB_NAME'],
			function ($line) use (&$dbName) {
				$dbName = trim($line);
			},
		);

		return $dbName;
	}

	public function getRemoteDbPasswordAttribute() {
		$dbPass = null;

		\SSH::into($this->name)->run(
			["cd sites/{$this->name}", 'wp config get DB_PASSWORD'],
			function ($line) use (&$dbPass) {
				$dbPass = trim($line);
			},
		);

		return $dbPass;
	}

	public function dumpDatabase(bool $gzip = true): ?string {
		$gzipCmd = $gzip ? '| gzip -c -' : '';
		$cmd = "ssh {$this->name}@{$this->ssh_host}";
		$cmd .= " \"mysqldump -u{$this->name} -p'{$this->remote_db_password}' --add-drop-table --no-tablespaces {$this->remote_db_name}";
		$cmd .= " $gzipCmd \"";

		return \passthru($cmd);
	}

	public function copyDatabase($dest) {
		$credentials = [
			'dev' => [
				'h' => 'sqldata',
				'u' => 'db',
				'p' => 'dbpass',
			],
			'local' => [
				'h' => 'mysql',
				'u' => 'db',
				'p' => 'dbpass',
			],
		];

		$dbHost = $credentials[$dest]['h'];
		$dbName = "{$this->local_db_name}_$dest";
		$dbUser = $credentials[$dest]['u'];
		$dbPass = $credentials[$dest]['p'];

		DB::select(DB::RAW("CREATE DATABASE IF NOT EXISTS $dbName"));

		$cmd = "ssh {$this->name}@{$this->ssh_host} \"mysqldump -u{$this->name} -p{$this->remote_db_password} {$this->remote_db_name} | gzip -c -\" | gunzip | mysql -h$dbHost -u$dbUser -p$dbPass $dbName";

		exec($cmd);
	}

	//Initialize the remote connection
	public static function initRemote(Install $install) {
		Config::set("remote.connections.{$install->name}", [
			'host' => $install->ssh_host,
			'username' => $install->name,
			'key' => Config::get('app.ssh_key'),
			'keyphrase' => '',
		]);
	}

	protected static function booted() {
		static::retrieved(function ($install) {
			Install::initRemote($install);
		});
	}

	public static function make(\stdClass $item) {
		return Install::create([
			'wpe_id' => $item->id,
			'name' => $item->name,
			'environment' => $item->environment,
			'primary_domain' => $item->primary_domain,
			'active' => $item->status === 'active',
			'php_version' => $item->php_version,
		]);
	}

	public static function matchQuery(string $install = '', array $opts = []) {
		$defaultOpts = [
			'includeDev' => false,
			'includeStaging' => false,
			'includeInactive' => false,
			'nameOnly' => false,
		];

		extract(array_merge($defaultOpts, $opts));

		$query = Install::where(DB::raw('true'), true);

		if ($includeInactive) {
			$query = $query->where('active', true);
			echo 'Including inactive installs';
		}

		$query->where(function ($query) use ($includeDev, $includeStaging) {
			// Always include production installs
			$query = $query->where('environment', 'production');

			// Optionally include dev/staging installs
			if ($includeDev) {
				$query = $query->orWhere('environment', 'development');
			}
			if ($includeStaging) {
				$query = $query->orWhere('environment', 'staging');
			}
		});

		if (!empty($install)) {
			$query->where(function ($query) use ($install) {
				$query
					->where('name', 'LIKE', "%{$install}%")
					->orWhere('primary_domain', 'LIKE', "%{$install}%");
			});
		}

		return $query;
	}
}