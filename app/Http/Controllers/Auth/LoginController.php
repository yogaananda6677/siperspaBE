<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class LoginController extends Controller
{
    // ✅ LOGIN
    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $validated['username'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Username atau password salah.',
            ], 401);
        }

        // // ✅ Cek verifikasi hanya untuk pelanggan, admin & kasir bebas
        // // if ($user->role === 'pelanggan' && $user->email_verified_at === null) {
        // //     return response()->json([
        // //         'message' => 'Email belum diverifikasi. Silakan verifikasi OTP terlebih dahulu.',
        // //     ], 403);
        // }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'    => 'Login berhasil',
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $user,
        ], 200);
    }

    // ✅ FORGOT PASSWORD — kirim OTP ke email
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'Email tidak ditemukan.',
            ], 404);
        }

        // Generate OTP 6 digit
        $otp = rand(100000, 999999);

        $user->update([
            'reset_token'            => $otp,
            'reset_token_expired_at' => now()->addMinutes(10),
        ]);

        // Kirim OTP ke email
        Mail::raw(
            "Halo {$user->name},\n\nKode OTP reset password akun InfinityPS kamu adalah: {$otp}\n\nKode ini berlaku selama 10 menit.\n\nJika kamu tidak merasa meminta reset password, abaikan email ini.\n\nTerima kasih.",
            function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Kode OTP Reset Password InfinityPS');
            }
        );

        return response()->json([
            'message' => 'Kode OTP reset password sudah dikirim ke email.',
        ], 200);
    }

    // ✅ VERIFY RESET OTP — cek OTP sebelum reset password
    public function verifyResetOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'digits:6'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        if ((string) $user->reset_token !== (string) $validated['otp']) {
            return response()->json(['message' => 'Kode OTP salah.'], 400);
        }

        if ($user->reset_token_expired_at < now()) {
            return response()->json(['message' => 'Kode OTP sudah kedaluwarsa.'], 400);
        }

        return response()->json([
            'message' => 'OTP valid. Silakan reset password.',
        ], 200);
    }

    // ✅ RESET PASSWORD — update password baru
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email'                 => ['required', 'email'],
            'otp'                   => ['required', 'digits:6'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        if ((string) $user->reset_token !== (string) $validated['otp']) {
            return response()->json(['message' => 'Kode OTP salah.'], 400);
        }

        if ($user->reset_token_expired_at < now()) {
            return response()->json(['message' => 'Kode OTP sudah kedaluwarsa.'], 400);
        }

        // Update password & hapus token
        $user->update([
            'password'               => Hash::make($validated['password']),
            'reset_token'            => null,
            'reset_token_expired_at' => null,
        ]);

        return response()->json([
            'message' => 'Password berhasil direset. Silakan login.',
        ], 200);
    }

    // ✅ UPDATE PASSWORD (dari dalam app)
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Password lama tidak sesuai'], 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json(['message' => 'Password berhasil diubah']);
    }

    // ✅ LOGOUT
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil'], 200);
    }
}