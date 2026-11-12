<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Perangkat extends Model
{
    /** Robot dianggap offline bila tidak mengirim data selama lebih dari 5 detik (Bab III, Tabel 3.3). */
    public const BATAS_OFFLINE_DETIK = 10; // dilonggarkan: lewat ngrok (HTTPS) tiap kiriman bisa 1-3 detik

    protected $table = 'perangkat';
    protected $primaryKey = 'id_perangkat';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['telemetri' => 'array', 'last_seen' => 'datetime'];
    }

    public function pemilik(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'id_user', 'id_user');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(Riwayat::class, 'id_perangkat', 'id_perangkat');
    }

    public function online(): bool
    {
        return $this->last_seen !== null
            && (now()->timestamp - $this->last_seen->timestamp) <= self::BATAS_OFFLINE_DETIK;
    }

    /** Membuat token perangkat baru. Mengembalikan teks asli (ditampilkan sekali); yang disimpan hanya hash SHA-256. */
    public static function buatToken(): array
    {
        $asli = Str::random(40);
        return [$asli, hash('sha256', $asli)];
    }

    public static function dariToken(?string $tokenAsli): ?self
    {
        if (!$tokenAsli) {
            return null;
        }
        return static::where('token', hash('sha256', $tokenAsli))->first();
    }
}