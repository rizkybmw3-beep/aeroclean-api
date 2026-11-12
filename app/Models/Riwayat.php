<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Riwayat extends Model
{
    protected $table = 'riwayat_pembersihan';
    protected $primaryKey = 'id_riwayat';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['waktu_mulai' => 'datetime', 'waktu_selesai' => 'datetime'];
    }
}
