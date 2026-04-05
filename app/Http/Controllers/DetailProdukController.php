<?php

namespace App\Http\Controllers;

use App\Models\DetailProduk;
use Illuminate\Http\JsonResponse;

class DetailProdukController extends Controller
{
    public function index(): JsonResponse
    {
        $data = DetailProduk::with([
            'transaksi:id_transaksi,id_user,tanggal,total_harga,status_transaksi',
            'produk:id_produk,nama,jenis,harga,stock',
        ])
            ->orderByDesc('id_detail_produk')
            ->get();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $detail = DetailProduk::with([
            'transaksi:id_transaksi,id_user,tanggal,total_harga,status_transaksi',
            'produk:id_produk,nama,jenis,harga,stock',
        ])
            ->where('id_detail_produk', $id)
            ->firstOrFail();

        return response()->json([
            'data' => $detail,
        ]);
    }
}
