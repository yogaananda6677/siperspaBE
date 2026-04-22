<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function updateProfile(Request $request)
{
    $user = $request->user();

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'username' => 'required|string|max:255|unique:users,username,' . $user->id_user . ',id_user',
        'email' => 'required|email|max:255|unique:users,email,' . $user->id_user . ',id_user',
    ]);

    $user->update([
        'name' => $validated['name'],
        'username' => $validated['username'],
        'email' => $validated['email'],
    ]);

    return response()->json([
        'message' => 'Profil berhasil diperbarui',
        'user' => $user
    ]);
}
public function updatePassword(Request $request)
{
    $user = $request->user();

    $validated = $request->validate([
        'current_password' => 'required',
        'password' => 'required|string|min:8|confirmed',
    ]);

    if (!Hash::check($validated['current_password'], $user->password)) {
        return response()->json([
            'message' => 'Password lama tidak sesuai'
        ], 422);
    }

    $user->update([
        'password' => Hash::make($validated['password'])
    ]);

    return response()->json([
        'message' => 'Password berhasil diubah'
    ]);
}
}
