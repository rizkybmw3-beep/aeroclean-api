<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AkunController extends Controller
{
    /**
     * DELETE /api/akun : menghapus akun pengguna beserta seluruh datanya.
     * Robot, riwayat, log sensor, perintah, dan token FCM ikut terhapus (ON DELETE CASCADE).
     * Body: password (konfirmasi). Salah password menghasilkan 422 (bukan 401) agar aplikasi tidak keluar sendiri.
     */
    public function hapus(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => 'required|string']);
        $user = $request->user();

        if (!Hash::check($data['password'], $user->password_hash)) {
            return response()->json(['message' => 'Password salah.'], 422);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();   // token login (Sanctum) tidak punya relasi cascade
            $user->delete();
        });

        return response()->json(['message' => 'Akun dan seluruh datanya telah dihapus.']);
    }
}