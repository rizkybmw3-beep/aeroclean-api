<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogSensor;
use App\Models\Perangkat;
use App\Models\Perintah;
use App\Models\Riwayat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerangkatController extends Controller
{
    /** Mengambil robot milik pengguna yang login; 404 bila bukan miliknya. */
    private function milik(Request $request, int|string $id): Perangkat
    {
        return Perangkat::where('id_perangkat', $id)
            ->where('id_user', $request->user()->id_user)
            ->firstOrFail();
    }

    private function ringkas(Perangkat $p): array
    {
        return [
            'id_perangkat' => $p->id_perangkat,
            'nama_robot'   => $p->nama_robot,
            'mac_address'  => $p->mac_address,
            'ip_address'   => $p->ip_address,
            'online'       => $p->online(),
            'last_seen'    => $p->last_seen?->toIso8601String(),
        ];
    }

    /** Titik jejak untuk aplikasi: posisi (cm), jarak halangan (cm), dan arah hadap (derajat). */
    private function petakan($koleksi)
    {
        return $koleksi->map(fn (LogSensor $l) => [
            'x'     => (float) $l->pos_x,
            'y'     => (float) $l->pos_y,
            'jarak' => (float) $l->jarak_halangan_cm,
            'arah'  => $l->arah === null ? null : (float) $l->arah,
        ])->values();
    }

    /** GET /api/perangkat : daftar robot milik pengguna. */
    public function index(Request $request): JsonResponse
    {
        $daftar = Perangkat::where('id_user', $request->user()->id_user)
            ->orderBy('id_perangkat')->get()
            ->map(fn (Perangkat $p) => $this->ringkas($p));

        return response()->json(['data' => $daftar]);
    }

    /** POST /api/perangkat : mendaftarkan robot; token asli hanya ditampilkan sekali di sini. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_robot'  => 'required|string|max:100',
            'mac_address' => ['nullable', 'string', 'max:17', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/'],
        ]);

        [$asli, $hash] = Perangkat::buatToken();
        $p = Perangkat::create([
            'id_user'     => $request->user()->id_user,
            'nama_robot'  => $data['nama_robot'],
            'mac_address' => isset($data['mac_address']) ? strtoupper(str_replace('-', ':', $data['mac_address'])) : null,
            'token'       => $hash,
        ]);

        return response()->json([
            'data'  => $this->ringkas($p),
            'token' => $asli,
            'pesan' => 'Simpan token ini. Token tidak akan ditampilkan lagi.',
        ], 201);
    }

    /** PATCH /api/perangkat/{id} : mengubah nama robot. */
    public function ubah(Request $request, int $id): JsonResponse
    {
        $p = $this->milik($request, $id);
        $data = $request->validate(['nama_robot' => 'required|string|max:100']);
        $p->update(['nama_robot' => trim($data['nama_robot'])]);

        return response()->json(['data' => $this->ringkas($p->fresh())]);
    }

    /** DELETE /api/perangkat/{id} : menghapus robot beserta riwayat, jejak, dan perintahnya (cascade). */
    public function hapus(Request $request, int $id): JsonResponse
    {
        $p = $this->milik($request, $id);
        $p->delete();

        return response()->json(['message' => 'Robot dihapus.']);
    }

    /** GET /api/perangkat/{id}/status : status terakhir robot untuk dashboard. */
    public function status(Request $request, int $id): JsonResponse
    {
        $p = $this->milik($request, $id);
        $online = $p->online();
        $t = $p->telemetri ?? [];

        return response()->json([
            'id_perangkat' => $p->id_perangkat,
            'nama_robot'   => $p->nama_robot,
            'ip_address'   => $p->ip_address,
            'online'       => $online,
            'last_seen'    => $p->last_seen?->toIso8601String(),
            // Bila offline, kondisi ditampilkan OFFLINE dan blower dianggap mati.
            'kondisi'      => $online ? ($t['kondisi'] ?? 'IDLE') : 'OFFLINE',
            'telemetri'    => $online ? $t : array_merge($t, ['blower' => 0]),
        ]);
    }

    /** POST /api/perangkat/{id}/perintah : memasukkan perintah ke antrean. */
    public function perintah(Request $request, int $id): JsonResponse
    {
        $p = $this->milik($request, $id);

        $data = $request->validate([
            'perintah' => ['required', 'string', Rule::in(array_keys(Perintah::DAFTAR))],
            'param'    => 'nullable|string|max:30',
        ]);
        $param = (string) ($data['param'] ?? '');

        if (!in_array($param, Perintah::DAFTAR[$data['perintah']], true)) {
            return response()->json(['message' => 'Parameter perintah tidak valid.'], 422);
        }
        if (!$p->online()) {
            return response()->json(['message' => 'Robot sedang offline. Perintah ditolak.'], 409);
        }

        $cmd = Perintah::create([
            'id_perangkat' => $p->id_perangkat,
            'perintah'     => $data['perintah'],
            'param'        => $param,
            'status'       => 'menunggu',
        ]);

        return response()->json(['id' => $cmd->id, 'status' => 'menunggu'], 201);
    }

    /**
     * GET /api/perangkat/{id}/jejak : titik odometri untuk peta langsung.
     * - Ada sesi berjalan: titik sejak sesi dimulai.
     * - Tidak ada sesi berjalan: gerakan 15 menit terakhir (mis. setir manual tanpa blower);
     *   bila tidak ada, jejak sesi terakhir.
     */
    public function jejak(Request $request, int $id): JsonResponse
    {
        $p = $this->milik($request, $id);

        $buka = Riwayat::where('id_perangkat', $p->id_perangkat)->whereNull('waktu_selesai')
            ->orderByDesc('id_riwayat')->first();

        $q = LogSensor::where('id_perangkat', $p->id_perangkat);
        if ($buka) {
            $q->where('timestamp', '>=', $buka->waktu_mulai);
        } else {
            $batas = now()->subMinutes(15);
            $baru = LogSensor::where('id_perangkat', $p->id_perangkat)->where('timestamp', '>=', $batas)->exists();
            if ($baru) {
                $q->where('timestamp', '>=', $batas);
            } else {
                $sesi = Riwayat::where('id_perangkat', $p->id_perangkat)->orderByDesc('id_riwayat')->first();
                if ($sesi) {
                    $q->where('timestamp', '>=', $sesi->waktu_mulai);
                    if ($sesi->waktu_selesai) {
                        $q->where('timestamp', '<=', $sesi->waktu_selesai);
                    }
                } else {
                    $q->whereRaw('1 = 0');
                }
            }
        }

        $titik = $this->petakan($q->orderByDesc('id_log')->limit(1500)->get()->reverse());

        return response()->json(['berjalan' => (bool) $buka, 'data' => $titik]);
    }

    /** GET /api/perangkat/{id}/riwayat : sesi pembersihan terbaru. */
    public function riwayat(Request $request, int $id): JsonResponse
    {
        $p = $this->milik($request, $id);
        $daftar = Riwayat::where('id_perangkat', $p->id_perangkat)
            ->orderByDesc('id_riwayat')->limit(20)->get()
            ->map(fn (Riwayat $r) => [
                'id_riwayat'       => $r->id_riwayat,
                'waktu_mulai'      => $r->waktu_mulai?->toIso8601String(),
                'waktu_selesai'    => $r->waktu_selesai?->toIso8601String(),
                'durasi_detik'     => $r->durasi_detik,
                'mode_pembersihan' => $r->mode_pembersihan,
                'status_akhir'     => $r->status_akhir,
            ]);

        return response()->json(['data' => $daftar]);
    }

    /** GET /api/perangkat/{id}/riwayat/{sesi}/jejak : jejak lengkap satu sesi pembersihan. */
    public function jejakSesi(Request $request, int $id, int $sesi): JsonResponse
    {
        $p = $this->milik($request, $id);
        $r = Riwayat::where('id_perangkat', $p->id_perangkat)->where('id_riwayat', $sesi)->firstOrFail();

        $q = LogSensor::where('id_perangkat', $p->id_perangkat)->where('timestamp', '>=', $r->waktu_mulai);
        if ($r->waktu_selesai) {
            $q->where('timestamp', '<=', $r->waktu_selesai);
        }

        return response()->json(['data' => $this->petakan($q->orderBy('id_log')->limit(3000)->get())]);
    }
}