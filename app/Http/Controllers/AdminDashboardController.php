<?php

namespace App\Http\Controllers;

use App\Models\Playstation;
use App\Models\Transaksi;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    private function transaksiRelations(): array
    {
        return [
            'user:id_user,name,username,email',
            'pembayaran',
            'detailSewa.playstation.tipe',
            'detailProduk.produk',
        ];
    }

    public function index(): JsonResponse
    {
        $transaksi = Transaksi::with($this->transaksiRelations())
            ->orderByDesc('created_at')
            ->get();

        $playstations = Playstation::with('tipe')
            ->orderBy('nomor_ps')
            ->get();

        $cashPending = Transaksi::with($this->transaksiRelations())
            ->whereHas('pembayaran', function ($q) {
                $q->where('metode_pembayaran', 'cash')
                    ->where('status_bayar', 'menunggu_validasi');
            })
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'total_transaksi' => $transaksi->count(),
            'waiting_approval' => $transaksi->where('status_transaksi', Transaksi::STATUS_WAITING)->count(),
            'aktif' => $transaksi->where('status_transaksi', Transaksi::STATUS_AKTIF)->count(),
            'selesai' => $transaksi->where('status_transaksi', Transaksi::STATUS_SELESAI)->count(),
            'menunggu_bayar' => $transaksi->filter(function ($item) {
                $statusBayar = strtolower($item->pembayaran->status_bayar ?? '');

                return in_array($statusBayar, ['menunggu', 'menunggu_validasi'], true);
            })->count(),
            'total_omzet' => (float) $transaksi->filter(function ($item) {
                return strtolower($item->pembayaran->status_bayar ?? '') === 'lunas';
            })->sum('total_harga'),
        ];

        $psStats = [
            'tersedia' => $playstations->where('status_ps', 'tersedia')->count(),
            'digunakan' => $playstations->where('status_ps', 'digunakan')->count(),
            'maintenance' => $playstations->where('status_ps', 'maintenance')->count(),
        ];

        $recentTransaksi = $transaksi->take(6)->values()->map(function ($item) {
            return $this->mapTransaksi($item);
        });

        $recentMonitoring = $playstations->take(6)->values()->map(function ($ps) {
            $transaksiAktif = Transaksi::with([
                'user:id_user,name,username,email',
                'pembayaran',
                'detailSewa',
            ])
                ->whereIn('status_transaksi', [
                    Transaksi::STATUS_AKTIF,
                    Transaksi::STATUS_MENUNGGU_PEMBAYARAN,
                    Transaksi::STATUS_WAITING,
                    Transaksi::STATUS_DIJADWALKAN,
                ])
                ->whereHas('detailSewa', function ($q) use ($ps) {
                    $q->where('id_ps', $ps->id_ps);
                })
                ->latest('created_at')
                ->first();

            return [
                'id_ps' => $ps->id_ps,
                'nomor_ps' => (string) $ps->nomor_ps,
                'status_ps' => $ps->status_ps,
                'tipe' => $ps->tipe ? [
                    'id_tipe' => $ps->tipe->id_tipe,
                    'nama_tipe' => $ps->tipe->nama_tipe,
                    'harga_sewa' => (float) $ps->tipe->harga_sewa,
                ] : null,
                'active_transaksi' => $transaksiAktif ? [
                    'id_transaksi' => $transaksiAktif->id_transaksi,
                    'status_transaksi' => $transaksiAktif->status_transaksi,
                    'user' => $transaksiAktif->user ? [
                        'id_user' => $transaksiAktif->user->id_user,
                        'name' => $transaksiAktif->user->name,
                        'username' => $transaksiAktif->user->username,
                        'email' => $transaksiAktif->user->email,
                    ] : null,
                    'pembayaran' => $transaksiAktif->pembayaran ? [
                        'status_bayar' => $transaksiAktif->pembayaran->status_bayar,
                    ] : null,
                ] : null,
            ];
        });

        $cashPendingData = $cashPending->take(6)->values()->map(function ($item) {
            return $this->mapTransaksi($item);
        });

        return response()->json([
            'message' => 'Dashboard admin berhasil dimuat.',
            'data' => [
                'stats' => $stats,
                'ps_stats' => $psStats,
                'cash_pending_count' => $cashPending->count(),
                'recent_transaksi' => $recentTransaksi,
                'recent_monitoring' => $recentMonitoring,
                'cash_pending' => $cashPendingData,
                'highlights' => [
                    'text' => $this->buildHighlightText(
                        $cashPending->count(),
                        $stats['waiting_approval'],
                        $stats['aktif']
                    ),
                ],
            ],
        ]);
    }

    private function buildHighlightText(int $cashPending, int $waitingApproval, int $aktif): string
    {
        if ($cashPending > 0) {
            return "{$cashPending} pembayaran cash sedang menunggu validasi admin.";
        }

        if ($waitingApproval > 0) {
            return "{$waitingApproval} booking pelanggan masih menunggu approval.";
        }

        if ($aktif > 0) {
            return "{$aktif} transaksi sedang berjalan saat ini.";
        }

        return 'Operasional hari ini terlihat stabil dan belum ada antrean validasi cash.';
    }

    private function mapTransaksi(Transaksi $item): array
    {
        return [
            'id_transaksi' => $item->id_transaksi,
            'tanggal' => optional($item->created_at)->format('Y-m-d H:i:s'),
            'total_harga' => (float) $item->total_harga,
            'status_transaksi' => $item->status_transaksi,
            'sumber_transaksi' => $item->sumber_transaksi,
            'user' => $item->user ? [
                'id_user' => $item->user->id_user,
                'name' => $item->user->name,
                'username' => $item->user->username,
                'email' => $item->user->email,
            ] : null,
            'pembayaran' => $item->pembayaran ? [
                'id_pembayaran' => $item->pembayaran->id_pembayaran,
                'metode_pembayaran' => $item->pembayaran->metode_pembayaran,
                'total_bayar' => (float) ($item->pembayaran->total_bayar ?? 0),
                'kembalian' => (float) ($item->pembayaran->kembalian ?? 0),
                'waktu_bayar' => optional($item->pembayaran->waktu_bayar)->format('Y-m-d H:i:s'),
                'status_bayar' => $item->pembayaran->status_bayar,
            ] : null,
            'detail_sewa' => $item->detailSewa->map(function ($sewa) {
                return [
                    'id_dt_booking' => $sewa->id_dt_booking,
                    'id_ps' => $sewa->id_ps,
                    'jam_mulai' => optional($sewa->jam_mulai)->format('Y-m-d H:i:s'),
                    'jam_selesai' => optional($sewa->jam_selesai)->format('Y-m-d H:i:s'),
                    'durasi_menit' => method_exists($sewa, 'durasiMenitEfektif')
                        ? $sewa->durasiMenitEfektif()
                        : $sewa->durasi_menit,
                    'playstation' => $sewa->playstation ? [
                        'id_ps' => $sewa->playstation->id_ps,
                        'nomor_ps' => (string) $sewa->playstation->nomor_ps,
                        'status_ps' => $sewa->playstation->status_ps,
                        'tipe' => $sewa->playstation->tipe ? [
                            'id_tipe' => $sewa->playstation->tipe->id_tipe,
                            'nama_tipe' => $sewa->playstation->tipe->nama_tipe,
                        ] : null,
                    ] : null,
                ];
            })->values(),
            'detail_produk' => $item->detailProduk->map(function ($detail) {
                return [
                    'id_detail_produk' => $detail->id_detail_produk,
                    'qty' => $detail->qty,
                    'subtotal' => (float) ($detail->subtotal ?? 0),
                    'produk' => $detail->produk ? [
                        'id_produk' => $detail->produk->id_produk,
                        'nama' => $detail->produk->nama_produk,
                        'harga' => (float) $detail->produk->harga,
                    ] : null,
                ];
            })->values(),
        ];
    }
}
