<?php

namespace App\Http\Controllers;

use App\Models\Playstation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaystationController extends Controller
{
    /**
     * GET /api/playstation
     * Bisa filter by status: ?status=tersedia
     */
    public function index(Request $request): JsonResponse
    {
        $query = Playstation::with('tipe');

        if ($request->filled('status')) {
            $query->where('status_ps', $request->status);
        }

        $ps = $query->orderBy('nomor_ps')->get();

        return response()->json(['data' => $ps]);
    }

    /**
     * POST /api/playstation
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_tipe' => 'required|exists:tipe_ps,id_tipe',
            'nomor_ps' => 'required|string|unique:playstation,nomor_ps|max:50',
            'status_ps' => 'sometimes|in:tersedia,digunakan,maintenance',
        ]);

        $ps = Playstation::create($validated);
        $ps->load('tipe');

        return response()->json([
            'message' => 'Unit PS berhasil ditambahkan.',
            'data' => $ps,
        ], 201);
    }

    /**
     * GET /api/playstation/{id}
     */
    public function show(string $id): JsonResponse
    {
        $ps = Playstation::with('tipe')->findOrFail($id);

        return response()->json(['data' => $ps]);
    }

    /**
     * PUT /api/playstation/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $ps = Playstation::findOrFail($id);

        $validated = $request->validate([
            'id_tipe' => 'sometimes|exists:tipe_ps,id_tipe',
            'nomor_ps' => 'sometimes|string|unique:playstation,nomor_ps,'.$id.',id_ps|max:50',
            'status_ps' => 'sometimes|in:tersedia,digunakan,maintenance',
        ]);

        $ps->update($validated);
        $ps->load('tipe');

        return response()->json([
            'message' => 'Unit PS berhasil diupdate.',
            'data' => $ps,
        ]);
    }

    /**
     * DELETE /api/playstation/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $ps = Playstation::findOrFail($id);

        if ($ps->status_ps === 'digunakan') {
            return response()->json([
                'message' => 'Tidak bisa dihapus. Unit PS sedang digunakan.',
            ], 422);
        }

        $ps->delete();

        return response()->json(['message' => 'Unit PS berhasil dihapus.']);
    }

    /**
     * PATCH /api/playstation/{id}/status
     * Update status PS saja (tersedia / maintenance)
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $ps = Playstation::findOrFail($id);

        $request->validate([
            'status_ps' => 'required|in:tersedia,digunakan,maintenance',
        ]);

        $ps->updateStatus($request->status_ps);

        return response()->json([
            'message' => 'Status PS berhasil diupdate.',
            'data' => $ps,
        ]);
    }
}
