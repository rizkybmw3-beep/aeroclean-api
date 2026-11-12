<?php

namespace App\Services;

use App\Models\FcmToken;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengirim notifikasi Firebase Cloud Messaging (FCM).
 * Bila paket kreait/laravel-firebase atau berkas kredensial belum disiapkan,
 * pengiriman dilewati secara diam-diam sehingga API tetap berjalan normal.
 */
class Notifikasi
{
    public static function siap(): bool
    {
        $kredensial = config('aeroclean.firebase_credentials');
        return class_exists(\Kreait\Laravel\Firebase\Facades\Firebase::class)
            && $kredensial
            && (is_file($kredensial) || is_file(base_path($kredensial)));
    }

    /** Mengirim ke semua ponsel milik pengguna. Mengembalikan jumlah yang berhasil. */
    public static function kirim(int $idUser, string $judul, string $isi, array $data = []): int
    {
        $daftar = FcmToken::where('id_user', $idUser)->pluck('token')->all();
        if (!$daftar || !static::siap()) {
            return 0;
        }

        try {
            $messaging = app('firebase.messaging');
        } catch (Throwable $e) {
            Log::warning('FCM tidak siap: '.$e->getMessage());
            return 0;
        }

        $berhasil = 0;
        foreach ($daftar as $token) {
            try {
                $pesan = \Kreait\Firebase\Messaging\CloudMessage::new()
                    ->toToken($token)
                    ->withNotification(\Kreait\Firebase\Messaging\Notification::create($judul, $isi))
                    ->withData(array_map('strval', $data));
                $messaging->send($pesan);
                $berhasil++;
            } catch (\Kreait\Firebase\Exception\Messaging\NotFound|\Kreait\Firebase\Exception\Messaging\InvalidMessage $e) {
                // Token sudah tidak berlaku (aplikasi dihapus / token diganti).
                FcmToken::where('token', $token)->delete();
            } catch (Throwable $e) {
                Log::warning('FCM gagal: '.$e->getMessage());
            }
        }
        return $berhasil;
    }
}