<?php

namespace App\Console\Commands;

use App\Models\FcmToken;
use App\Models\Pengguna;
use App\Services\Notifikasi;
use Illuminate\Console\Command;

class TesNotif extends Command
{
    protected $signature = 'aeroclean:tes-notif {username}';
    protected $description = 'Mengirim notifikasi Firebase percobaan ke ponsel milik pengguna';

    public function handle(): int
    {
        $u = Pengguna::where('username', $this->argument('username'))->first();
        if (!$u) {
            $this->error('Pengguna tidak ditemukan.');
            return self::FAILURE;
        }
        $this->line('Token ponsel terdaftar : '.FcmToken::where('id_user', $u->id_user)->count());
        $this->line('Firebase siap          : '.(Notifikasi::siap() ? 'ya' : 'BELUM (paket kreait atau berkas kredensial belum ada)'));
        $n = Notifikasi::kirim($u->id_user, 'Tes AeroClean', 'Notifikasi Firebase berhasil.');
        $this->info("Terkirim ke $n ponsel.");
        return self::SUCCESS;
    }
}
