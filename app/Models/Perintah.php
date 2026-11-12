<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perintah extends Model
{
    public const MASA_BERLAKU_DETIK = 5;

    /** Perintah yang diizinkan beserta parameter yang sah (Bab III, Tabel 3.15). */
    public const DAFTAR = [
        'mode'   => ['manual', 'auto'],
        'gerak' => [
        'forward', 'backward', 'left', 'right', 'stop',
        'forward_left', 'forward_right', 'backward_left', 'backward_right',
        ],
        'blower' => ['0', '1'],
        'auto'   => [''],
        'home'   => [''],
        'stop'   => [''],
    ];

    protected $table = 'perintah';
    public $timestamps = true;
    public const UPDATED_AT = null;
    protected $guarded = [];

    /** created_at disimpan dengan presisi milidetik agar watchdog 1500 ms akurat. */
    protected $dateFormat = 'Y-m-d H:i:s.v';
}