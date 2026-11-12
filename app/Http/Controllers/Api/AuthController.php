<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /** POST /api/login : otentikasi dan penerbitan token. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = Pengguna::where('username', $data['username'])->first();
        if (!$user || !Hash::check($data['password'], $user->password_hash)) {
            return response()->json(['message' => 'Username atau password salah.'], 401);
        }

        return response()->json([
            'token' => $user->createToken('aplikasi')->plainTextToken,
            'user'  => ['id_user' => $user->id_user, 'username' => $user->username, 'nama_lengkap' => $user->nama_lengkap],
        ]);
    }

    /**
     * POST /api/register : membuat akun baru lalu langsung menerbitkan token
     * (aplikasi Flutter langsung masuk setelah daftar).
     * Body: nama_lengkap, username, password, password_confirmation
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_lengkap' => 'required|string|max:100',
            'username'     => [
                'required', 'string', 'alpha_num', 'min:4', 'max:50',
                Rule::unique((new Pengguna())->getTable(), 'username'),
            ],
            'password'     => 'required|string|min:8|confirmed',
        ], [
            'username.unique'    => 'Username sudah dipakai.',
            'username.alpha_num' => 'Username hanya boleh huruf dan angka.',
            'username.min'       => 'Username minimal 4 karakter.',
            'password.min'       => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        // Diisi satu per satu agar tidak bergantung pada $fillable model.
        $user = new Pengguna();
        $user->username      = $data['username'];
        $user->nama_lengkap  = $data['nama_lengkap'];
        $user->password_hash = Hash::make($data['password']);
        $user->save();

        return response()->json([
            'token' => $user->createToken('aplikasi')->plainTextToken,
            'user'  => ['id_user' => $user->id_user, 'username' => $user->username, 'nama_lengkap' => $user->nama_lengkap],
        ], 201);
    }

    /** POST /api/logout : mencabut token yang sedang dipakai. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil keluar.']);
    }
}