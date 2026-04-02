<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        // ✅ Simpan user
        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        // ✅ Response JSON (penting buat Next.js)
        return response()->json([
            'message' => 'Register berhasil',
            'user' => $user,
        ], 201);
    }
}
