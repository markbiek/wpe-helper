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
}
