<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        // ✅ Validasi
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100', Rule::unique(User::class)],
            'email' => ['required', 'email', 'max:100', Rule::unique(User::class)],
            'role' => ['required', 'string'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $otp = rand(10000, 999999);

        // ✅ Simpan user
    $user = User::create([
        'name' => $validated['name'],
        'username' => $validated['username'],
        'email' => $validated['email'],
        'role' => $validated['role'],
        'password' => Hash::make($validated['password']),
        'verification_code' => $otp,
        'verification_code_expired_at' => now()->addMinutes(10),
    ]);

        Mail::raw(
            "Halo {$user->name},\n\nKode verifikasi akun InfinityPS kamu adalah: {$otp}\n\nKode ini berlaku selama 10 menit.\n\nTerima kasih.",
            function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Kode Verifikasi Akun InfinityPS');
        }
    );

        // ✅ Response JSON (penting buat Next.js)
        return response()->json([
            'message' => 'Register berhasil',
            'user' => $user,
        ], 201);
  
    }
        public function verifyOtp(Request $request)
    {
        // ✅ Validasi OTP
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        // ✅ Cari user berdasarkan email
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'Email tidak ditemukan',
            ], 404);
        }

        // ✅ Cek kalau email sudah diverifikasi
        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => 'Email sudah diverifikasi',
            ], 400);
        }

        // ✅ Cek OTP benar atau tidak
        if ((string) $user->verification_code !== (string) $validated['otp']) {            
            return response()->json([
                'message' => 'Kode OTP salah',
            ], 400);
        }

        // ✅ Cek OTP expired atau belum
        if ($user->verification_code_expired_at < now()) {
            return response()->json([
                'message' => 'Kode OTP sudah kedaluwarsa',
            ], 400);
        }

        // ✅ Verifikasi berhasil
        $user->update([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_code_expired_at' => null,
        ]);

        return response()->json([
            'message' => 'Email berhasil diverifikasi',
            'user' => $user,
        ], 200);
    }

    public function resendOtp(Request $request)
    {
        // ✅ Validasi email
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // ✅ Cari user
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'Email tidak ditemukan',
            ], 404);
        }

        // ✅ Kalau sudah verifikasi, tidak perlu kirim OTP lagi
        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => 'Email sudah diverifikasi',
            ], 400);
        }

        // ✅ Generate OTP baru
        $otp = rand(100000, 999999);

        $user->update([
            'verification_code' => $otp,
            'verification_code_expired_at' => now()->addMinutes(10),
        ]);

        // ✅ Kirim ulang OTP
        Mail::raw(
            "Halo {$user->name},\n\nKode verifikasi baru akun InfinityPS kamu adalah: {$otp}\n\nKode ini berlaku selama 10 menit.\n\nTerima kasih.",
            function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Kode Verifikasi Baru Akun InfinityPS');
            }
        );

        return response()->json([
            'message' => 'Kode OTP baru sudah dikirim ke email',
        ], 200);
    }
}

