<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PembayaranController extends Controller
{
    public function bayar(Request $request, string $id): JsonResponse
    {
        $transaksi = Transaksi::with([
            'user:id_user,name,username,email',
            'detailSewa.playstation.tipe',
            'detailProduk.produk',
            'pembayaran',
        ])->findOrFail($id);

        if ($transaksi->status_transaksi !== 'aktif') {
            return response()->json([
                'message' => 'Hanya transaksi aktif yang bisa dibayar.',
            ], 422);
        }

        $request->validate([
            'metode_pembayaran' => 'required|in:cash,online',
            'total_bayar' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $totalTagihan = (float) $transaksi->total_harga;
            $metode = $request->metode_pembayaran;
            $totalBayarInput = (float) ($request->total_bayar ?? 0);

            if ($metode === 'cash') {
                if ($totalBayarInput < $totalTagihan) {
                    DB::rollBack();

                    return response()->json([
                        'message' => 'Nominal pembayaran cash kurang dari total tagihan.',
                    ], 422);
                }

                $totalBayar = $totalBayarInput;
                $kembalian = $totalBayar - $totalTagihan;
                $statusBayar = 'lunas';
            } else {
                // online payment manual
                $totalBayar = $totalTagihan;
                $kembalian = 0;
                $statusBayar = 'lunas';
            }

            Pembayaran::updateOrCreate(
                ['id_transaksi' => $transaksi->id_transaksi],
                [
                    'metode_pembayaran' => $metode,
                    'total_bayar' => $totalBayar,
                    'kembalian' => $kembalian,
                    'waktu_bayar' => now(),
                    'status_bayar' => $statusBayar,
                ]
            );

            DB::commit();

            $transaksi->refresh()->load([
                'user:id_user,name,username,email',
                'detailSewa.playstation.tipe',
                'detailProduk.produk',
                'pembayaran',
            ]);

            return response()->json([
                'message' => 'Pembayaran berhasil disimpan.',
                'data' => $transaksi,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }
}
