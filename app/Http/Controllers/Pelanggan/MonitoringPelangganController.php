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
            'detailSewa',
            'detailProduk.produk',
        ])
            ->whereIn('status_transaksi', [
                Transaksi::STATUS_WAITING,
                Transaksi::STATUS_DIJADWALKAN,
                Transaksi::STATUS_AKTIF,
            ])
            ->whereHas('detailSewa')
            ->get();

        $transaksiByPs = [];

        foreach ($transaksiMonitoring as $transaksi) {
            foreach ($transaksi->detailSewa as $detailSewa) {
                $transaksiByPs[$detailSewa->id_ps] = [
                    'id_transaksi' => $transaksi->id_transaksi,
                    'status_transaksi' => $transaksi->status_transaksi,
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
                            'produk' => $detail->produk ? [
                                'id_produk' => $detail->produk->id_produk,
                                'nama_produk' => $detail->produk->nama_produk,
                                'harga' => (int) $detail->produk->harga,
                            ] : null,
                        ];
                    })->filter(fn ($item) => ! is_null($item['produk']))->values(),
                ];
            }
        }

        $data = $playstations->map(function ($ps) use ($transaksiByPs) {
            return [
                'id_ps' => $ps->id_ps,
                'nomor_ps' => (string) $ps->nomor_ps,
                'status_ps' => $ps->status_ps,
                'tipe' => $ps->tipe ? [
                    'id_tipe' => $ps->tipe->id_tipe,
                    'nama_tipe' => $ps->tipe->nama_tipe,
                    'harga_sewa' => (int) $ps->tipe->harga_sewa,
                ] : null,
                'active_transaksi' => $transaksiByPs[$ps->id_ps] ?? null,
            ];
        })->values();

        return response()->json([
            'data' => $data,
        ]);
    }
}
