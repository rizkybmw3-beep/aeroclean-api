<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    /** POST /api/fcm-token : aplikasi mendaftarkan token Firebase perangkat ponselnya. */
    public function simpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => 'required|string|max:512',
            'platform' => 'nullable|string|max:20',
        ]);

        // Satu token ponsel hanya milik satu pengguna (pengguna terakhir yang login).
        FcmToken::updateOrCreate(
            ['token' => $data['token']],
            ['id_user' => $request->user()->id_user, 'platform' => $data['platform'] ?? 'android'],
        );

        return response()->json(['message' => 'Token notifikasi tersimpan.']);
    }

    /** DELETE /api/fcm-token : dipanggil saat keluar agar ponsel tidak menerima notifikasi akun lama. */
    public function hapus(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);
        FcmToken::where('token', $request->input('token'))
            ->where('id_user', $request->user()->id_user)->delete();
        return response()->json(['message' => 'Token notifikasi dihapus.']);
    }
}
