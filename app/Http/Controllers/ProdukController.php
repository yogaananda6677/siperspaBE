<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProdukController extends Controller
{
    /**
     * GET /api/produk
     * Optional filter:
     * ?jenis=minuman
     * ?tersedia=1
     */
    public function index(Request $request): JsonResponse
    {
        $query = Produk::query();

        if ($request->filled('jenis')) {
            $query->where('jenis', $request->input('jenis'));
        }

        if ($request->boolean('tersedia')) {
            $query->where('stock', '>', 0);
        }

        $produk = $query->orderBy('nama', 'asc')->get();

        return response()->json([
            'data' => $produk,
        ]);
    }

    /**
     * POST /api/produk
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'jenis' => 'required|string|max:100',
            'harga' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        $produk = Produk::create($validated);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'data' => $produk,
        ], 201);
    }

    /**
     * GET /api/produk/{id}
     */
    public function show(string $id): JsonResponse
    {
        $produk = Produk::findOrFail($id);

        return response()->json([
            'data' => $produk,
        ]);
    }

    /**
     * PUT /api/produk/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $produk = Produk::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'sometimes|string|max:255',
            'jenis' => 'sometimes|string|max:100',
            'harga' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
        ]);

        if (empty($validated)) {
            return response()->json([
                'message' => 'Tidak ada data yang diupdate.',
            ], 422);
        }

        $produk->update($validated);

        return response()->json([
            'message' => 'Produk berhasil diupdate.',
            'data' => $produk->fresh(),
        ]);
    }

    /**
     * DELETE /api/produk/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $produk = Produk::findOrFail($id);
        $produk->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }

    /**
     * PATCH /api/produk/{id}/stock
     * Body:
     * {
     *   "aksi": "tambah" | "kurangi",
     *   "jumlah": number
     * }
     */
    public function updateStock(Request $request, string $id): JsonResponse
    {
        $produk = Produk::findOrFail($id);

        $validated = $request->validate([
            'aksi' => 'required|in:tambah,kurangi',
            'jumlah' => 'required|integer|min:1',
        ]);

        if ($validated['aksi'] === 'tambah') {
            $produk->tambahStock($validated['jumlah']);
            $message = "Stock berhasil ditambah {$validated['jumlah']}.";
        } else {
            $berhasil = $produk->kurangiStock($validated['jumlah']);

            if (! $berhasil) {
                return response()->json([
                    'message' => 'Stock tidak mencukupi.',
                ], 422);
            }

            $message = "Stock berhasil dikurangi {$validated['jumlah']}.";
        }

        $produk->refresh();

        return response()->json([
            'message' => $message,
            'stock_saat_ini' => (int) $produk->stock,
        ]);
    }
}
