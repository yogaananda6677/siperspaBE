<?php

namespace App\Http\Controllers;

use App\Models\Playstation;
use App\Models\Produk;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $transaksi = Transaksi::with([
            'user:id_user,name,username,email',
            'pembayaran',
            'detailSewa.playstation.tipe',
            'detailProduk.produk',
        ])->get();

        $playstations = Playstation::query()->get();
        $produk = Produk::query()->get();

        $bookingWaiting = $transaksi->where('status_transaksi', Transaksi::STATUS_WAITING)->values();
        $bookingRejected = $transaksi->where('status_transaksi', Transaksi::STATUS_DITOLAK)->values();
        $transaksiAktif = $transaksi->where('status_transaksi', Transaksi::STATUS_AKTIF)->values();

        $cashPending = $transaksi->filter(function ($item) {
            $statusBayar = strtolower($item->pembayaran->status_bayar ?? '');
            $metode = strtolower($item->pembayaran->metode_pembayaran ?? '');

            return $metode === 'cash'
                && in_array($statusBayar, ['menunggu_verifikasi', 'menunggu_validasi'], true);
        })->values();

        $psRusak = $playstations->where('status_ps', 'maintenance')->count();
        $psDigunakan = $playstations->where('status_ps', 'digunakan')->count();

        $produkHabis = $produk->filter(function ($item) {
            return (int) $item->stock <= 0;
        })->count();

        $totalPendapatan = (float) $transaksi->filter(function ($item) {
            return strtolower($item->pembayaran->status_bayar ?? '') === 'lunas';
        })->sum('total_harga');

        $notifications = collect();

        if ($bookingWaiting->count() > 0) {
            $notifications->push([
                'type' => 'booking',
                'title' => 'Ada booking menunggu persetujuan',
                'message' => $bookingWaiting->count().' booking dari pelanggan aplikasi perlu dicek admin.',
                'count' => $bookingWaiting->count(),
                'href' => '/admin/approve-booking',
            ]);
        }

        if ($cashPending->count() > 0) {
            $notifications->push([
                'type' => 'cash',
                'title' => 'Ada pembayaran cash menunggu validasi',
                'message' => $cashPending->count().' transaksi cash perlu diverifikasi admin.',
                'count' => $cashPending->count(),
                'href' => '/admin/pembayaran-cash',
            ]);
        }

        $informativeCards = [
            [
                'key' => 'transaksi_aktif',
                'label' => 'Transaksi Aktif',
                'value' => $transaksiAktif->count(),
                'sub' => 'Transaksi yang sedang berjalan',
                'color' => '#38bdf8',
                'href' => '/admin/transaksi?status=aktif',
                'is_currency' => false,
            ],
            [
                'key' => 'booking_rejected',
                'label' => 'Booking Ditolak',
                'value' => $bookingRejected->count(),
                'sub' => 'Jumlah booking yang sudah ditolak',
                'color' => '#f87171',
                'href' => '/admin/transaksi?status=ditolak',
                'is_currency' => false,
            ],
            [
                'key' => 'ps_rusak',
                'label' => 'PS Rusak',
                'value' => $psRusak,
                'sub' => 'Unit yang sedang maintenance',
                'color' => '#fb7185',
                'href' => '/admin/playstation?status=maintenance',
                'is_currency' => false,
            ],
            [
                'key' => 'ps_digunakan',
                'label' => 'PS Digunakan',
                'value' => $psDigunakan,
                'sub' => 'Unit yang sedang dipakai',
                'color' => '#60a5fa',
                'href' => '/admin/monitoring',
                'is_currency' => false,
            ],
            [
                'key' => 'produk_habis',
                'label' => 'Produk Habis',
                'value' => $produkHabis,
                'sub' => 'Produk makanan/minuman dengan stok 0',
                'color' => '#f59e0b',
                'href' => '/admin/produk?stock=habis',
                'is_currency' => false,
            ],
            [
                'key' => 'total_pendapatan',
                'label' => 'Total Pendapatan',
                'value' => $totalPendapatan,
                'sub' => 'Akumulasi transaksi yang sudah lunas',
                'color' => '#4ade80',
                'href' => '/admin/transaksi',
                'is_currency' => true,
            ],
        ];

        return response()->json([
            'message' => 'Dashboard admin berhasil dimuat.',
            'data' => [
                'notifications' => $notifications->values(),
                'informative_cards' => $informativeCards,
                'omzet_chart' => [
                    'week' => $this->buildOmzetDailySeries(7),
                    'month' => $this->buildOmzetDailySeries(30),
                    'year' => $this->buildOmzetMonthlySeries(12),
                ],
                'last_updated_at' => now()->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    private function buildOmzetDailySeries(int $days): array
    {
        $startDate = Carbon::now()->startOfDay()->subDays($days - 1);

        $transaksi = Transaksi::with('pembayaran')
            ->whereDate('created_at', '>=', $startDate)
            ->get()
            ->filter(function ($item) {
                return strtolower($item->pembayaran->status_bayar ?? '') === 'lunas';
            });

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);

            $total = (float) $transaksi
                ->filter(fn ($trx) => optional($trx->created_at)->isSameDay($date))
                ->sum('total_harga');

            $series[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $days <= 7
                    ? $date->translatedFormat('D')
                    : $date->translatedFormat('d M'),
                'total' => $total,
            ];
        }

        return $series;
    }

    private function buildOmzetMonthlySeries(int $months): array
    {
        $startMonth = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $transaksi = Transaksi::with('pembayaran')
            ->whereDate('created_at', '>=', $startMonth)
            ->get()
            ->filter(function ($item) {
                return strtolower($item->pembayaran->status_bayar ?? '') === 'lunas';
            });

        $series = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $startMonth->copy()->addMonths($i);

            $total = (float) $transaksi
                ->filter(function ($trx) use ($month) {
                    return optional($trx->created_at)?->format('Y-m') === $month->format('Y-m');
                })
                ->sum('total_harga');

            $series[] = [
                'date' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M y'),
                'total' => $total,
            ];
        }

        return $series;
    }
}
