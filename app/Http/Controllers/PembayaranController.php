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

        if ($transaksi->pembayaran && $transaksi->pembayaran->status_bayar === 'lunas') {
            return response()->json([
                'message' => 'Transaksi ini sudah lunas.',
            ], 422);
        }

        $request->validate([
            'metode_pembayaran' => 'required|in:cash,online',
        ]);

        DB::beginTransaction();

        try {
            $transaksi->hitungUlangTotal();

            $metode = $request->input('metode_pembayaran');

            if ($metode === 'cash') {
                Pembayaran::updateOrCreate(
                    ['id_transaksi' => $transaksi->id_transaksi],
                    [
                        'metode_pembayaran' => 'cash',
                        'total_bayar' => 0,
                        'kembalian' => 0,
                        'waktu_bayar' => null,
                        'status_bayar' => Pembayaran::STATUS_MENUNGGU_VALIDASI,
                    ]
                );

                $transaksi->update([
                    'status_transaksi' => Transaksi::STATUS_AKTIF,
                ]);

                DB::commit();

                $transaksi->refresh()->load($this->transaksiRelations());

                return response()->json([
                    'message' => 'Permintaan pembayaran cash berhasil dikirim dan menunggu validasi admin.',
                    'data' => $transaksi,
                ]);
            }

            DB::rollBack();

            return response()->json([
                'message' => 'Pembayaran online pelanggan harus menggunakan QRIS Midtrans.',
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function konfirmasiCash(Request $request, string $id): JsonResponse
    {
        $transaksi = Transaksi::with($this->transaksiRelations())->findOrFail($id);

        if (! $transaksi->pembayaran) {
            return response()->json([
                'message' => 'Data pembayaran tidak ditemukan.',
            ], 404);
        }

        if ($transaksi->pembayaran->metode_pembayaran !== 'cash') {
            return response()->json([
                'message' => 'Hanya pembayaran cash yang bisa dikonfirmasi admin.',
            ], 422);
        }

        if ($transaksi->pembayaran->status_bayar === 'lunas') {
            return response()->json([
                'message' => 'Pembayaran cash ini sudah lunas.',
            ], 422);
        }

        $request->validate([
            'total_bayar' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $transaksi->hitungUlangTotal();

            $totalTagihan = (float) $transaksi->total_harga;
            $totalBayar = (float) $request->input('total_bayar');

            if ($totalBayar < $totalTagihan) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Nominal pembayaran cash kurang dari total tagihan.',
                ], 422);
            }

            $kembalian = $totalBayar - $totalTagihan;

            $transaksi->pembayaran->update([
                'total_bayar' => $totalBayar,
                'kembalian' => $kembalian,
                'waktu_bayar' => now(),
                'status_bayar' => Pembayaran::STATUS_LUNAS,
            ]);

            $transaksi->update([
                'status_transaksi' => Transaksi::STATUS_AKTIF,
            ]);

            DB::commit();

            $transaksi->refresh()->load($this->transaksiRelations());

            return response()->json([
                'message' => 'Pembayaran cash berhasil dikonfirmasi.',
                'data' => $transaksi,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function cashMenunggu(): JsonResponse
    {
        $data = Transaksi::with($this->transaksiRelations())
            ->whereHas('pembayaran', function ($q) {
                $q->where('metode_pembayaran', 'cash')
                    ->where('status_bayar', Pembayaran::STATUS_MENUNGGU_VALIDASI);
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $data,
        ]);
    }
}
