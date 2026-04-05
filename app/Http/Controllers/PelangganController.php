<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PelangganController extends Controller
{
    /**
     * GET /api/pelanggan
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->input('search');

        $pelanggans = User::query()
            ->where('role', 'pelanggan')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->select('id_user', 'name', 'username', 'email', 'created_at')
            ->latest()
            ->paginate(10);

        return response()->json($pelanggans);
    }

    /**
     * POST /api/pelanggan
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $pelanggan = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'role' => 'pelanggan',
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Pelanggan berhasil ditambahkan',
            'data' => $pelanggan,
        ], 201);
    }

    /**
     * PUT /api/pelanggan/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::where('id_user', $id)->firstOrFail();

        if ($user->role !== 'pelanggan') {
            return response()->json([
                'message' => 'User ini bukan pelanggan.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'username' => ['sometimes', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id_user, 'id_user')],
            'email' => ['sometimes', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id_user, 'id_user')],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
        ]);

        if (empty($validated)) {
            return response()->json([
                'message' => 'Tidak ada data yang diupdate.',
            ], 422);
        }

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        unset($validated['password_confirmation']);

        $user->update($validated);

        return response()->json([
            'message' => 'Pelanggan berhasil diupdate',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * DELETE /api/pelanggan/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::where('id_user', $id)->firstOrFail();

        if ($user->role !== 'pelanggan') {
            return response()->json([
                'message' => 'User ini bukan pelanggan.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Pelanggan berhasil dihapus',
        ]);
    }
}
