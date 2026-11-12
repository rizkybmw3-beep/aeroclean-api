<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SEMENTARA: untuk mengecek server mana yang menjawab. Hapus setelah selesai.
Route::get('/siapa', fn () => [
    'db'     => config('database.default').':'.config('database.connections.mysql.database'),
    'pid'    => getmypid(),
    'server' => php_sapi_name(),
    'waktu'  => now()->toDateTimeString(),
]);