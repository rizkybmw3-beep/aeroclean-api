<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogSensor;
use App\Models\Perangkat;
use App\Models\Perintah;
use App\Models\Riwayat;
use App\Services\Notifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceSyncController extends Controller
{
    /** Bila tidak ada detak "gerak" baru selama ini (ms), server memerintahkan robot berhenti. */
    private const GERAK_TANPA_DETAK_MS = 1500;

    /**
     * POST /api/device/sync (dipanggil ESP32 sekitar tiap 300 ms, Tabel 3.17).
     * Header: Authorization: Bearer <token perangkat>
     * Body  : mode, kondisi, jarak, x, y, arah, blower, sinyal, tempuh, durasi, ip
     */
    public function sync(Request $request): JsonResponse
    {
        $p = Perangkat::dariToken($request->bearerToken() ?? $request->header('X-Device-Token'));
        if (!$p) {
            return response()->json(['message' => 'Token perangkat tidak valid.'], 401);
        }

        $d = $request->validate([
            'mode'    => 'nullable|string|max:10',
            'kondisi' => 'nullable|string|max:20',
            'jarak'   => 'nullable|numeric',
            'x'       => 'nullable|numeric',
            'y'       => 'nullable|numeric',
            'arah'    => 'nullable|numeric',
            'blower'  => 'nullable|in:0,1,true,false',
            'sinyal'  => 'nullable|integer',
            'tempuh'  => 'nullable|numeric',
            'durasi'  => 'nullable|integer',
            'ip'      => 'nullable|string|max:45',
        ]);
        $d['blower'] = in_array($d['blower'] ?? 0, [1, '1', true, 'true'], true) ? 1 : 0;

        $sekarang = now();
        $kondisiLama = $p->telemetri['kondisi'] ?? null;

        // 1) Perbarui status terakhir.
        $p->update([
            'ip_address'     => $d['ip'] ?? $request->ip(),
            'status_koneksi' => 'terhubung',
            'last_seen'      => $sekarang,
            'telemetri'      => $d,
        ]);

        // 2) Riwayat: satu sesi = satu periode blower menyala.
        $this->catatRiwayat($p, $d, $kondisiLama, $sekarang);

        // 3) Log sensor: paling banyak satu baris per detik, saat blower menyala ATAU robot bergerak
        //    (agar setir manual tanpa blower pun tergambar di peta). Arah hadap ikut disimpan.
        $bergerak = in_array($d['kondisi'] ?? '', ['MANUAL', 'CLEANING', 'AVOIDING', 'RETURNING'], true);
        $terakhir = LogSensor::where('id_perangkat', $p->id_perangkat)->max('timestamp');
        if (($d['blower'] === 1 || $bergerak) && (!$terakhir || $sekarang->diffInSeconds($terakhir, true) >= 1)) {
            LogSensor::create([
                'id_perangkat'      => $p->id_perangkat,
                'jarak_halangan_cm' => $d['jarak'] ?? 0,
                'pos_x'             => $d['x'] ?? 0,
                'pos_y'             => $d['y'] ?? 0,
                'arah'              => $d['arah'] ?? null,
                'timestamp'         => $sekarang,
            ]);
        }

        // 4) Balas dengan perintah yang masih menunggu; yang lebih dari 5 detik dibuang.
        Perintah::where('id_perangkat', $p->id_perangkat)->where('status', 'menunggu')
            ->where('created_at', '<', $sekarang->copy()->subSeconds(Perintah::MASA_BERLAKU_DETIK))
            ->update(['status' => 'kedaluwarsa']);

        // 4a) Watchdog gerak: aplikasi mengirim detak "gerak" tiap ~250 ms selama tombol arah
        //     ditahan. Bila perintah terakhir adalah gerak (bukan stop), sudah terkirim ke robot,
        //     dan tidak ada detak baru dalam GERAK_TANPA_DETAK_MS (aplikasi mati/koneksi ponsel
        //     putus), antrekan "stop" supaya robot tidak berjalan terus.
        $cmdTerakhir = Perintah::where('id_perangkat', $p->id_perangkat)->orderByDesc('id')->first();
        if ($cmdTerakhir
            && $cmdTerakhir->perintah === 'gerak'
            && (string) $cmdTerakhir->param !== 'stop'
            && $cmdTerakhir->status === 'terkirim'
            && $sekarang->diffInMilliseconds($cmdTerakhir->created_at, true) > self::GERAK_TANPA_DETAK_MS) {
            Perintah::create([
                'id_perangkat' => $p->id_perangkat,
                'perintah'     => 'gerak',
                'param'        => 'stop',
                'status'       => 'menunggu',
            ]);
        }

        // 4b) Bersihkan detak gerak lama (sesekali saja agar murah): sekitar 1 dari 50 sync.
        if (random_int(1, 50) === 1) {
            Perintah::where('id_perangkat', $p->id_perangkat)
                ->where('perintah', 'gerak')
                ->where('status', '!=', 'menunggu')
                ->where('created_at', '<', $sekarang->copy()->subMinutes(10))
                ->delete();
        }

        $antre = Perintah::where('id_perangkat', $p->id_perangkat)->where('status', 'menunggu')
            ->orderBy('id')->get();
        if ($antre->isNotEmpty()) {
            Perintah::whereIn('id', $antre->pluck('id'))->update(['status' => 'terkirim']);
        }

        return response()->json([
            'perintah' => $antre->map(fn ($c) => ['id' => $c->id, 'perintah' => $c->perintah, 'param' => $c->param])->values(),
            'waktu'    => $sekarang->toIso8601String(),
        ]);
    }

    private function catatRiwayat(Perangkat $p, array $d, ?string $kondisiLama, $sekarang): void
    {
        $terbuka = Riwayat::where('id_perangkat', $p->id_perangkat)->whereNull('waktu_selesai')
            ->orderByDesc('id_riwayat')->first();

        if ($d['blower'] === 1 && !$terbuka) {
            Riwayat::create([
                'id_perangkat'     => $p->id_perangkat,
                'waktu_mulai'      => $sekarang,
                'mode_pembersihan' => ($d['mode'] ?? 'auto') === 'manual' ? 'manual' : 'otomatis',
                'status_akhir'     => 'berjalan',
            ]);
        } elseif ($d['blower'] === 0 && $terbuka) {
            $selesaiDiAwal = in_array('RETURNING', [$kondisiLama, $d['kondisi'] ?? null], true);
            $status = $selesaiDiAwal ? 'kembali_ke_awal' : 'dihentikan';
            $terbuka->update([
                'waktu_selesai' => $sekarang,
                'durasi_detik'  => (int) abs($sekarang->diffInSeconds($terbuka->waktu_mulai, true)),
                'status_akhir'  => $status,
            ]);

            Notifikasi::kirim(
                $p->id_user,
                $selesaiDiAwal ? 'Pembersihan selesai' : 'Pembersihan dihentikan',
                $p->nama_robot.($selesaiDiAwal ? ' sudah kembali ke titik awal.' : ' berhenti membersihkan.'),
                ['id_perangkat' => $p->id_perangkat, 'jenis' => $status],
            );
        }
    }
}