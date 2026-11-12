<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Pengguna extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'pengguna';
    protected $primaryKey = 'id_user';
    public const CREATED_AT = 'dibuat_pada';
    public const UPDATED_AT = null;

    protected $fillable = ['username', 'password_hash', 'nama_lengkap'];
    protected $hidden = ['password_hash'];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function perangkat(): HasMany
    {
        return $this->hasMany(Perangkat::class, 'id_user', 'id_user');
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class, 'id_user', 'id_user');
    }
}
