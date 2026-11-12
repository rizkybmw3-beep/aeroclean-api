<?php

namespace Tests\Feature;

use App\Models\Perangkat;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AeroCleanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime(); // waktu dibekukan agar tes tidak bergantung pada pergantian detik
    }

    private function login(string $u = 'budi'): array
    {
        Pengguna::firstOrCreate(['username' => $u], ['password_hash' => Hash::make('rahasia'), 'nama_lengkap' => $u]);
        $r = $this->postJson('/api/login', ['username' => $u, 'password' => 'rahasia'])->assertOk();
        return ['Authorization' => 'Bearer '.$r->json('token')];
    }

    private function robot(array $h): array
    {
        $r = $this->postJson('/api/perangkat', ['nama_robot' => 'Robot 1', 'mac_address' => 'aa:bb:cc:dd:ee:ff'], $h)->assertCreated();
        return [$r->json('data.id_perangkat'), $r->json('token')];
    }

    private function sync(string $token, array $d = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/device/sync', array_merge(
            ['mode' => 'auto', 'kondisi' => 'CLEANING', 'jarak' => 50, 'x' => 1, 'y' => 2, 'arah' => 90, 'blower' => 1, 'sinyal' => -50, 'tempuh' => 10, 'durasi' => 3], $d),
            ['Authorization' => 'Bearer '.$token]);
    }

    public function test_login_salah_dan_benar(): void
    {
        Pengguna::create(['username' => 'a', 'password_hash' => Hash::make('x'), 'nama_lengkap' => 'A']);
        $this->postJson('/api/login', ['username' => 'a', 'password' => 'salah'])->assertStatus(401);
        $this->postJson('/api/login', ['username' => 'a', 'password' => 'x'])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_perlu_login_dan_logout_mencabut_token(): void
    {
        $this->getJson('/api/perangkat')->assertStatus(401);
        $h = $this->login();
        $this->getJson('/api/perangkat', $h)->assertOk();
        $this->postJson('/api/logout', [], $h)->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_tambah_robot_token_sekali_dan_tersimpan_sebagai_hash(): void
    {
        $h = $this->login();
        [$id, $token] = $this->robot($h);
        $this->assertSame(40, strlen($token));
        $this->assertSame(hash('sha256', $token), Perangkat::find($id)->token);
        $this->getJson('/api/perangkat', $h)->assertOk()->assertJsonMissingPath('data.0.token')->assertJsonPath('data.0.online', false);
    }

    public function test_robot_orang_lain_tidak_bisa_diakses(): void
    {
        [$id] = $this->robot($this->login('budi'));
        $siti = $this->login('siti');
        $this->app['auth']->forgetGuards(); // tes saja: guard menyimpan pengguna dari permintaan sebelumnya
        $this->getJson("/api/perangkat/$id/status", $siti)->assertStatus(404);
    }

    public function test_sync_menolak_token_salah(): void
    {
        $this->sync('salah')->assertStatus(401);
    }

    public function test_status_online_lalu_offline_setelah_5_detik(): void
    {
        $h = $this->login();
        [$id, $token] = $this->robot($h);
        $this->getJson("/api/perangkat/$id/status", $h)->assertJsonPath('kondisi', 'OFFLINE');
        $this->sync($token)->assertOk();
        $this->getJson("/api/perangkat/$id/status", $h)->assertJsonPath('online', true)->assertJsonPath('kondisi', 'CLEANING')->assertJsonPath('telemetri.x', 1);
        $this->travel(6)->seconds();
        $this->getJson("/api/perangkat/$id/status", $h)->assertJsonPath('online', false)->assertJsonPath('kondisi', 'OFFLINE');
    }

    public function test_perintah_ditolak_saat_offline_dan_diteruskan_saat_online(): void
    {
        $h = $this->login();
        [$id, $token] = $this->robot($h);
        $this->postJson("/api/perangkat/$id/perintah", ['perintah' => 'auto'], $h)->assertStatus(409);
        $this->sync($token, ['blower' => 0, 'kondisi' => 'IDLE'])->assertOk();
        $this->postJson("/api/perangkat/$id/perintah", ['perintah' => 'gerak', 'param' => 'forward'], $h)->assertCreated();
        $r = $this->sync($token, ['blower' => 0, 'kondisi' => 'IDLE'])->assertOk();
        $this->assertSame('gerak', $r->json('perintah.0.perintah'));
        $this->assertSame('forward', $r->json('perintah.0.param'));
        // Sudah terkirim, tidak dikirim dua kali.
        $this->assertCount(0, $this->sync($token)->json('perintah'));
    }

    public function test_validasi_perintah(): void
    {
        $h = $this->login();
        [$id, $token] = $this->robot($h);
        $this->sync($token)->assertOk();
        $this->postJson("/api/perangkat/$id/perintah", ['perintah' => 'terbang'], $h)->assertStatus(422);
        $this->postJson("/api/perangkat/$id/perintah", ['perintah' => 'gerak', 'param' => 'naik'], $h)->assertStatus(422);
        $this->postJson("/api/perangkat/$id/perintah", ['perintah' => 'blower', 'param' => '1'], $h)->assertCreated();
    }

    public function test_perintah_lebih_5_detik_dibuang(): void
    {
        $h = $this->login();
        [$id, $token] = $this->robot($h);
        $this->sync($token)->assertOk();
        $this->postJson("/api/perangkat/$id/perintah", ['perintah' => 'stop'], $h)->assertCreated();
        $this->travel(6)->seconds();
        $this->assertCount(0, $this->sync($token)->json('perintah'));
        $this->assertDatabaseHas('perintah', ['perintah' => 'stop', 'status' => 'kedaluwarsa']);
    }

    public function test_riwayat_satu_sesi_blower_dan_status_akhir(): void
    {
        $h = $this->login();
        [$id, $token] = $this->robot($h);
        $this->sync($token)->assertOk();                                   // blower nyala -> sesi dibuka
        $this->travel(10)->seconds();
        $this->sync($token, ['kondisi' => 'RETURNING'])->assertOk();
        $this->travel(5)->seconds();
        $this->sync($token, ['blower' => 0, 'kondisi' => 'IDLE'])->assertOk(); // blower mati -> sesi ditutup
        $r = $this->getJson("/api/perangkat/$id/riwayat", $h)->assertOk();
        $this->assertCount(1, $r->json('data'));
        $this->assertSame('kembali_ke_awal', $r->json('data.0.status_akhir'));
        $this->assertSame('otomatis', $r->json('data.0.mode_pembersihan'));
        $this->assertSame(15, $r->json('data.0.durasi_detik'));
    }

    public function test_dihentikan_dan_log_maks_satu_per_detik(): void
    {
        $h = $this->login();
        [$id, $token] = $this->robot($h);
        $this->sync($token)->assertOk();
        $this->sync($token)->assertOk();            // detik yang sama -> tidak dicatat lagi
        $this->assertDatabaseCount('log_sensor', 1);
        $this->travel(2)->seconds();
        $this->sync($token, ['x' => 5, 'y' => 6])->assertOk();
        $this->assertDatabaseCount('log_sensor', 2);
        $this->sync($token, ['blower' => 0, 'kondisi' => 'IDLE'])->assertOk();
        $this->getJson("/api/perangkat/$id/riwayat", $h)->assertJsonPath('data.0.status_akhir', 'dihentikan');
        $j = $this->getJson("/api/perangkat/$id/jejak", $h)->assertOk();
        $this->assertCount(2, $j->json('data'));
        $this->assertEquals(5, $j->json("data.1.x"));
    }

    public function test_fcm_token_dan_cek_offline(): void
    {
        $h = $this->login();
        [$id, $token] = $this->robot($h);
        $this->postJson('/api/fcm-token', ['token' => 'tokenfcm123'], $h)->assertOk();
        $this->postJson('/api/fcm-token', ['token' => 'tokenfcm123'], $h)->assertOk();
        $this->assertDatabaseCount('fcm_token', 1);

        $this->sync($token)->assertOk();
        $this->travel(10)->seconds();
        $this->artisan('aeroclean:cek-offline')->assertSuccessful();
        $this->assertDatabaseHas('perangkat', ['id_perangkat' => $id, 'status_koneksi' => 'terputus']);
        $this->assertDatabaseHas('riwayat_pembersihan', ['id_perangkat' => $id, 'status_akhir' => 'dihentikan']);

        $this->deleteJson('/api/fcm-token', ['token' => 'tokenfcm123'], $h)->assertOk();
        $this->assertDatabaseCount('fcm_token', 0);
    }

    public function test_artisan_buat_user_dan_robot(): void
    {
        $this->artisan('aeroclean:buat-user', ['username' => 'admin', 'password' => 'admin123'])->assertSuccessful();
        $this->postJson('/api/login', ['username' => 'admin', 'password' => 'admin123'])->assertOk();
        $this->artisan('aeroclean:robot-baru', ['username' => 'admin', 'nama_robot' => 'R1'])->assertSuccessful();
        $this->assertDatabaseCount('perangkat', 1);
    }
}
