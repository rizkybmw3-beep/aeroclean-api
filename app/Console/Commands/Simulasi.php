<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/** Meniru ESP32 untuk menguji aplikasi tanpa robot fisik. */
class Simulasi extends Command
{
    protected $signature = 'aeroclean:simulasi {token} {--url=http://127.0.0.1:8000}';
    protected $description = 'Simulator robot: mengirim telemetri ke /api/device/sync tiap 300 ms dan menjalankan perintah';

    public function handle(): int
    {
        $mode = 'manual'; $kondisi = 'IDLE'; $blower = 0; $arah = 90.0; $x = 0.0; $y = 0.0;
        $gerak = 'stop'; $tempuh = 0.0; $mulai = null; $tick = 0;
        $this->info('Simulator berjalan. Tekan Ctrl+C untuk berhenti.');

        while (true) {
            $tick++;
            $jarak = 100 - (25 * abs(sin($tick / 40)));
            $r = deg2rad($arah);
            $v = 0;
            if ($kondisi === 'CLEANING' || $kondisi === 'AVOIDING') { $v = 4.0; }
            if ($mode === 'manual') { $v = $gerak === 'forward' ? 4.0 : ($gerak === 'backward' ? -4.0 : 0); }
            if ($gerak === 'left' && $mode === 'manual') { $arah += 8; }
            if ($gerak === 'right' && $mode === 'manual') { $arah -= 8; }
            if ($kondisi === 'CLEANING') { $arah += sin($tick / 15) * 3; }
            if ($kondisi === 'RETURNING') {
                $arah = rad2deg(atan2(-$y, -$x)); $v = 5.0;
                if (hypot($x, $y) < 4) { $kondisi = 'IDLE'; $blower = 0; $mulai = null; }
            }
            $x += cos($r) * $v; $y += sin($r) * $v; $tempuh += abs($v);
            if ($mode === 'manual') { $kondisi = $v != 0 ? 'MANUAL' : 'IDLE'; }

            try {
                $res = Http::withToken($this->argument('token'))->timeout(2)->post($this->option('url').'/api/device/sync', [
                    'mode' => $mode, 'kondisi' => $kondisi, 'jarak' => round($jarak, 1),
                    'x' => round($x, 1), 'y' => round($y, 1), 'arah' => round(fmod($arah + 360, 360), 1),
                    'blower' => $blower, 'sinyal' => -55, 'tempuh' => round($tempuh), 'durasi' => $mulai ? time() - $mulai : 0,
                ]);
                foreach ($res->json('perintah') ?? [] as $c) {
                    $this->line("perintah: {$c['perintah']} {$c['param']}");
                    switch ($c['perintah']) {
                        case 'mode': $mode = $c['param']; $gerak = 'stop'; if ($mode === 'manual') { $kondisi = 'IDLE'; } break;
                        case 'gerak': $gerak = $c['param']; break;
                        case 'blower': $blower = (int) $c['param']; $mulai = $blower ? ($mulai ?? time()) : null; break;
                        case 'auto': $mode = 'auto'; $kondisi = 'CLEANING'; $blower = 1; $x = $y = 0; $tempuh = 0; $mulai = time(); break;
                        case 'home': if ($kondisi === 'CLEANING' || $kondisi === 'AVOIDING') { $kondisi = 'RETURNING'; } break;
                        case 'stop': $kondisi = 'IDLE'; $blower = 0; $gerak = 'stop'; $mulai = null; break;
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('sync gagal: '.$e->getMessage());
            }
            usleep(300000);
        }
    }
}
