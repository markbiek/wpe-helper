<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Install extends Model {
    protected $primaryKey = 'wpe_id';

    protected $fillable = [
        'wpe_id',
        'name',
        'environment',
        'primary_domain',
        'active',
        'php_version',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute() {
        return "https://my.wpengine.com/installs/{$this->name}";
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
}
