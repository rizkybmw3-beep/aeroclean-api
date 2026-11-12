<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel sesuai Bab III (Tabel 3.11 sampai 3.15) ditambah tabel fcm_token untuk notifikasi Firebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $t) {
            $t->id('id_user');
            $t->string('username')->unique();
            $t->string('password_hash');
            $t->string('nama_lengkap');
            $t->timestamp('dibuat_pada')->useCurrent();
        });

        Schema::create('perangkat', function (Blueprint $t) {
            $t->id('id_perangkat');
            $t->foreignId('id_user')->constrained('pengguna', 'id_user')->cascadeOnDelete();
            $t->string('nama_robot');
            $t->string('mac_address', 17)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('status_koneksi', 16)->default('terputus');
            $t->char('token', 64)->unique();
            $t->timestamp('last_seen')->nullable();
            $t->json('telemetri')->nullable();
        });

        Schema::create('riwayat_pembersihan', function (Blueprint $t) {
            $t->id('id_riwayat');
            $t->foreignId('id_perangkat')->constrained('perangkat', 'id_perangkat')->cascadeOnDelete();
            $t->dateTime('waktu_mulai');
            $t->dateTime('waktu_selesai')->nullable();
            $t->unsignedInteger('durasi_detik')->nullable();
            $t->string('mode_pembersihan', 16);
            $t->string('status_akhir', 24)->default('berjalan');
        });

        Schema::create('log_sensor', function (Blueprint $t) {
            $t->id('id_log');
            $t->foreignId('id_perangkat')->constrained('perangkat', 'id_perangkat')->cascadeOnDelete();
            $t->float('jarak_halangan_cm');
            $t->float('pos_x');
            $t->float('pos_y');
            $t->dateTime('timestamp');
            $t->index(['id_perangkat', 'timestamp']);
        });

        Schema::create('perintah', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_perangkat')->constrained('perangkat', 'id_perangkat')->cascadeOnDelete();
            $t->string('perintah', 16);
            $t->string('param', 16)->nullable();
            $t->string('status', 16)->default('menunggu');
            $t->timestamp('created_at')->nullable();
            $t->index(['id_perangkat', 'status']);
        });

        // Tambahan untuk Firebase Cloud Messaging: satu pengguna bisa punya beberapa ponsel.
        Schema::create('fcm_token', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_user')->constrained('pengguna', 'id_user')->cascadeOnDelete();
            $t->string('token', 255)->unique();
            $t->string('platform', 16)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['fcm_token', 'perintah', 'log_sensor', 'riwayat_pembersihan', 'perangkat', 'pengguna'] as $n) {
            Schema::dropIfExists($n);
        }
    }
};
