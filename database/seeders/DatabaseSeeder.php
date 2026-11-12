<?php

namespace Database\Seeders;

use App\Models\Pengguna;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Pengguna::updateOrCreate(['username' => 'admin'], [
            'password_hash' => Hash::make('admin123'),
            'nama_lengkap'  => 'Administrator',
        ]);
    }
}
