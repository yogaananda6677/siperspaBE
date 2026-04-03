<?php

namespace App\Http\Controllers;

use App\Models\TipePs;                    // ✅ import model
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;         // ✅ import JsonResponse

class TipePsController extends Controller
{
    public function index(): JsonResponse
    {
        $tipe = TipePs::withCount('playstation')
            ->orderBy('nama_tipe')
            ->get();

        return response()->json(['data' => $tipe]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_tipe' => 'required|string|max:100',
            'harga_sewa' => 'required|numeric|min:0',
        ]);

        $tipe = TipePs::create($validated);

        return response()->json([
            'message' => 'Tipe PS berhasil ditambahkan.',
            'data' => $tipe,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $tipe = TipePs::with('playstation')->findOrFail($id);

        return response()->json(['data' => $tipe]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tipe = TipePs::findOrFail($id);

        $validated = $request->validate([
            'nama_tipe' => 'sometimes|string|max:100',
            'harga_sewa' => 'sometimes|numeric|min:0',
        ]);

        $tipe->update($validated);

        return response()->json([
            'message' => 'Tipe PS berhasil diupdate.',
            'data' => $tipe,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $tipe = TipePs::findOrFail($id);

        if ($tipe->playstation()->exists()) {
            return response()->json([
                'message' => 'Tidak bisa dihapus. Masih ada unit PS yang menggunakan tipe ini.',
            ], 422);
        }

        $tipe->delete();

        return response()->json(['message' => 'Tipe PS berhasil dihapus.']);
    }
}
