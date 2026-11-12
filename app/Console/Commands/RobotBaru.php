<?php

namespace App\Console\Commands;

use App\Models\Pengguna;
use App\Models\Perangkat;
use Illuminate\Console\Command;

class RobotBaru extends Command
{
    protected $signature = 'aeroclean:robot-baru {username} {nama_robot} {mac?}';
    protected $description = 'Mendaftarkan robot untuk pengguna dan menampilkan token perangkat (sekali)';

    public function handle(): int
    {
        $u = Pengguna::where('username', $this->argument('username'))->first();
        if (!$u) {
            $this->error('Pengguna tidak ditemukan.');
            return self::FAILURE;
        }
        [$asli, $hash] = Perangkat::buatToken();
        $p = Perangkat::create([
            'id_user' => $u->id_user, 'nama_robot' => $this->argument('nama_robot'),
            'mac_address' => $this->argument('mac'), 'token' => $hash,
        ]);
        $this->info("Robot #{$p->id_perangkat} terdaftar. TOKEN (simpan, tidak ditampilkan lagi):");
        $this->line($asli);
        return self::SUCCESS;
    }
}
