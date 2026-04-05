<?php

namespace App\Http\Controllers;

use App\Models\Playstation;
use App\Models\Transaksi;
use Illuminate\Http\JsonResponse;

class MonitoringPlaystationController extends Controller
{
    public function index(): JsonResponse
    {
        $playstations = Playstation::with('tipe')
            ->orderBy('nomor_ps')
            ->get();

        $transaksiAktif = Transaksi::with([
            'user:id_user,name,username,email',
            'detailSewa.playstation.tipe',
            'detailProduk.produk',
        ])
            ->where('status_transaksi', 'aktif')
            ->whereHas('detailSewa')
            ->get();

        $transaksiByPs = [];

        foreach ($transaksiAktif as $transaksi) {
            foreach ($transaksi->detailSewa as $detailSewa) {
                $transaksiByPs[$detailSewa->id_ps] = $transaksi;
            }
        }

        $data = $playstations->map(function ($ps) use ($transaksiByPs) {
            return [
                'id_ps' => $ps->id_ps,
                'nomor_ps' => $ps->nomor_ps,
                'status_ps' => $ps->status_ps,
                'tipe' => $ps->tipe,
                'active_transaksi' => $transaksiByPs[$ps->id_ps] ?? null,
            ];
        })->values();

        return response()->json([
            'data' => $data,
        ]);
    }
}
