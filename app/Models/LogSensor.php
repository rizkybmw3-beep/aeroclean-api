<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogSensor extends Model
{
    protected $table = 'log_sensor';
    protected $primaryKey = 'id_log';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['timestamp' => 'datetime'];
    }
}
