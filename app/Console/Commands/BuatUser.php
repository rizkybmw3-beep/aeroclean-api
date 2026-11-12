<?php

namespace App\Console\Commands;

use App\Models\Pengguna;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class BuatUser extends Command
{
    protected $signature = 'aeroclean:buat-user {username} {password} {nama?}';
    protected $description = 'Membuat atau memperbarui akun pengguna AeroClean';

    public function handle(): int
    {
        $u = Pengguna::updateOrCreate(
            ['username' => $this->argument('username')],
            [
                'password_hash' => Hash::make($this->argument('password')),
                'nama_lengkap'  => $this->argument('nama') ?? $this->argument('username'),
            ],
        );
        $this->info("Akun siap: {$u->username} (id {$u->id_user})");
        return self::SUCCESS;
    }
}
