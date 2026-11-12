<?php

namespace App\Console\Commands;

use App\Models\Perangkat;
use App\Models\Riwayat;
use App\Services\Notifikasi;
use Illuminate\Console\Command;

class CekOffline extends Command
{
    protected $signature = 'aeroclean:cek-offline';
    protected $description = 'Menandai robot terputus dan mengirim notifikasi bila terputus saat membersihkan';

    public function handle(): int
    {
        $n = 0;
        foreach (Perangkat::where('status_koneksi', 'terhubung')->get() as $p) {
            if ($p->online()) {
                continue;
            }
            $p->update(['status_koneksi' => 'terputus']);

            $sesi = Riwayat::where('id_perangkat', $p->id_perangkat)->whereNull('waktu_selesai')->first();
            if ($sesi) {
                $sesi->update([
                    'waktu_selesai' => $p->last_seen,
                    'durasi_detik'  => (int) abs($p->last_seen->diffInSeconds($sesi->waktu_mulai, true)),
                    'status_akhir'  => 'dihentikan',
                ]);
                Notifikasi::kirim($p->id_user, 'Robot terputus',
                    $p->nama_robot.' terputus saat membersihkan.', ['id_perangkat' => $p->id_perangkat, 'jenis' => 'terputus']);
            }
            $n++;
        }
        $this->info("Robot ditandai terputus: $n");
        return self::SUCCESS;
    }
}
