<?php

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Playstation;
use App\Models\Transaksi;
use Illuminate\Http\JsonResponse;

class MonitoringPelangganController extends Controller
{
    public function index(): JsonResponse
    {
        $playstations = Playstation::with('tipe')
            ->orderBy('nomor_ps')
            ->get();

        $transaksiMonitoring = Transaksi::with([
            'user:id_user,name,username,email',
            'pembayaran',
            'detailSewa.playstation.tipe',
            'detailProduk.produk',
        ])
            ->whereIn('status_transaksi', [
                Transaksi::STATUS_WAITING,
                Transaksi::STATUS_DIJADWALKAN,
                Transaksi::STATUS_AKTIF,
            ])
            ->whereHas('detailSewa')
            ->orderByDesc('created_at')
            ->get();

        $transaksiByPs = [];

        foreach ($transaksiMonitoring as $transaksi) {
            $priority = match ($transaksi->status_transaksi) {
                Transaksi::STATUS_WAITING => 3,
                Transaksi::STATUS_DIJADWALKAN => 2,
                Transaksi::STATUS_AKTIF => 1,
                default => 0,
            };

            foreach ($transaksi->detailSewa as $detailSewa) {
                $idPs = $detailSewa->id_ps;

                $mappedTransaksi = [
                    'id_transaksi' => $transaksi->id_transaksi,
                    'status_transaksi' => $transaksi->status_transaksi,
                    '_priority' => $priority,
                    'user' => $transaksi->user ? [
                        'id_user' => $transaksi->user->id_user,
                        'name' => $transaksi->user->name,
                        'username' => $transaksi->user->username,
                        'email' => $transaksi->user->email,
                    ] : null,
                    'pembayaran' => $transaksi->pembayaran ? [
                        'id_pembayaran' => $transaksi->pembayaran->id_pembayaran,
                        'status_bayar' => $transaksi->pembayaran->status_bayar,
                        'metode_pembayaran' => $transaksi->pembayaran->metode_pembayaran,
                        'total_bayar' => $transaksi->pembayaran->total_bayar,
                        'waktu_bayar' => optional($transaksi->pembayaran->waktu_bayar)->format('Y-m-d H:i:s'),
                    ] : null,
                    'detail_sewa' => $transaksi->detailSewa->map(function ($sewa) {
                        return [
                            'id_detail_sewa' => $sewa->id_dt_booking,
                            'id_ps' => $sewa->id_ps,
                            'jam_mulai' => optional($sewa->jam_mulai)->format('Y-m-d H:i:s'),
                            'jam_selesai' => optional($sewa->jam_selesai)->format('Y-m-d H:i:s'),
                            'durasi_menit' => $sewa->durasiMenitEfektif(),
                            'sisa_detik' => $sewa->sisaDetik(),
                        ];
                    })->values(),
                    'detail_produk' => $transaksi->detailProduk->map(function ($detail) {
                        return [
                            'id_detail_produk' => $detail->id_detail_produk,
                            'qty' => $detail->qty,
                            'subtotal' => $detail->subtotal,
                            'produk' => $detail->produk ? [
                                'id_produk' => $detail->produk->id_produk,
                                'nama_produk' => $detail->produk->nama_produk,
                                'harga' => (int) $detail->produk->harga,
                            ] : null,
                        ];
                    })->filter(fn ($item) => ! is_null($item['produk']))->values(),
                ];

                if (! isset($transaksiByPs[$idPs])) {
                    $transaksiByPs[$idPs] = $mappedTransaksi;

                    continue;
                }

                $currentPriority = $transaksiByPs[$idPs]['_priority'] ?? 0;

                if ($priority > $currentPriority) {
                    $transaksiByPs[$idPs] = $mappedTransaksi;
                }
            }
        }

        $data = $playstations->map(function ($ps) use ($transaksiByPs) {
            $activeTransaksi = $transaksiByPs[$ps->id_ps] ?? null;

            if ($activeTransaksi) {
                unset($activeTransaksi['_priority']);
            }

            return [
                'id_ps' => $ps->id_ps,
                'nomor_ps' => (string) $ps->nomor_ps,
                'status_ps' => $ps->status_ps,
                'tipe' => $ps->tipe ? [
                    'id_tipe' => $ps->tipe->id_tipe,
                    'nama_tipe' => $ps->tipe->nama_tipe,
                    'harga_sewa' => (int) $ps->tipe->harga_sewa,
                ] : null,
                'active_transaksi' => $activeTransaksi,
            ];
        })->values();

        return response()->json([
            'data' => $data,
        ]);
    }
}
