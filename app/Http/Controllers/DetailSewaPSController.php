<?php

namespace App\Http\Controllers;

use App\Models\DetailSewaPS;
use Illuminate\Http\JsonResponse;

class DetailSewaPSController extends Controller
{
    public function index(): JsonResponse
    {
        $data = DetailSewaPS::with(['transaksi', 'playstation.tipe'])
            ->latest('id_dt_booking')
            ->get();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $detail = DetailSewaPS::with(['transaksi', 'playstation.tipe'])
            ->findOrFail($id);

        return response()->json([
            'data' => $detail,
        ]);
    }

    public function selesai(string $id): JsonResponse
    {
        $detail = DetailSewaPS::with(['transaksi', 'playstation'])->findOrFail($id);

        if ($detail->jam_selesai) {
            return response()->json([
                'message' => 'Sesi sewa ini sudah selesai.',
            ], 422);
        }

        $detail->selesaikan();

        return response()->json([
            'message' => 'Detail sewa berhasil diselesaikan.',
            'data' => $detail->fresh(),
        ]);
    }
}
