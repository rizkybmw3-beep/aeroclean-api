<?php

use App\Http\Controllers\Api\AkunController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceSyncController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\PerangkatController;
use Illuminate\Support\Facades\Route;

// Publik
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/device/sync', [DeviceSyncController::class, 'sync'])->middleware('throttle:600,1');

// Aplikasi Flutter (Bearer token Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/perangkat', [PerangkatController::class, 'index']);
    Route::post('/perangkat', [PerangkatController::class, 'store']);
    Route::patch('/perangkat/{id}', [PerangkatController::class, 'ubah'])->whereNumber('id');
    Route::delete('/perangkat/{id}', [PerangkatController::class, 'hapus'])->whereNumber('id');
    Route::get('/perangkat/{id}/status', [PerangkatController::class, 'status'])->whereNumber('id');
    Route::post('/perangkat/{id}/perintah', [PerangkatController::class, 'perintah'])->whereNumber('id');
    Route::get('/perangkat/{id}/jejak', [PerangkatController::class, 'jejak'])->whereNumber('id');
    Route::get('/perangkat/{id}/riwayat', [PerangkatController::class, 'riwayat'])->whereNumber('id');
    Route::get('/perangkat/{id}/riwayat/{sesi}/jejak', [PerangkatController::class, 'jejakSesi'])
        ->whereNumber('id')->whereNumber('sesi');

    // Akun: hapus akun beserta seluruh datanya (diminta Google Play).
    Route::delete('/akun', [AkunController::class, 'hapus'])->middleware('throttle:5,1');

    // Tambahan Firebase (FCM)
    Route::post('/fcm-token', [FcmTokenController::class, 'simpan']);
    Route::delete('/fcm-token', [FcmTokenController::class, 'hapus']);
});