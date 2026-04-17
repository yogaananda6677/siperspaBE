<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use App\Models\Transaksi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PembayaranController extends Controller
{
    private function transaksiRelations(): array
    {
        return [
            'user:id_user,name,username,email',
            'detailSewa.playstation.tipe',
            'detailProduk.produk',
            'pembayaran',
        ];
    }

    public function bayar(Request $request, string $id): JsonResponse
    {
        $transaksi = Transaksi::with($this->transaksiRelations())->findOrFail($id);

        if (! in_array($transaksi->status_transaksi, [
            Transaksi::STATUS_AKTIF,
            Transaksi::STATUS_MENUNGGU_PEMBAYARAN,
        ], true)) {
            return response()->json([
                'message' => 'Status transaksi ini tidak bisa dibayar.',
            ], 422);
        }

        if ($transaksi->pembayaran && $transaksi->pembayaran->sudahLunas()) {
            return response()->json([
                'message' => 'Transaksi ini sudah lunas.',
            ], 422);
        }

        $request->validate([
            'metode_pembayaran' => 'required|in:cash,online',
            'total_bayar' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $transaksi->hitungUlangTotal();

            $totalTagihan = (float) $transaksi->total_harga;
            $metode = $request->input('metode_pembayaran');
            $totalBayarInput = (float) ($request->input('total_bayar') ?? 0);

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

            // kalau transaksi aplikasi, setelah lunas ubah jadi aktif dan PS dipakai
            if ($transaksi->isAplikasi() && $transaksi->isMenungguPembayaran()) {
                foreach ($transaksi->detailSewa as $sewa) {
                    if ($sewa->playstation) {
                        $sewa->playstation->updateStatus('digunakan');
                    }
                }

                $transaksi->update([
                    'status_transaksi' => Transaksi::STATUS_AKTIF,
                ]);
            }

            DB::commit();

            $transaksi->refresh()->load($this->transaksiRelations());

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
