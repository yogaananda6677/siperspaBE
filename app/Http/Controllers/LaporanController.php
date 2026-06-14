<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    public function pendapatan(Request $request): JsonResponse
    {
        $request->validate([
            'periode' => 'required|in:harian,mingguan,bulanan,tahunan',
            'tanggal' => 'nullable|date',
            'bulan'   => 'nullable|integer|min:1|max:12',
            'tahun'   => 'nullable|integer|min:2000',
        ]);

        $periode = $request->input('periode');
        $tahun   = (int) ($request->input('tahun', now()->year));
        $bulan   = (int) ($request->input('bulan', now()->month));
        $tanggal = $request->input('tanggal', now()->toDateString());

        [$dari, $sampai, $rows] = match ($periode) {
            'harian'   => $this->harian($tanggal),
            'mingguan' => $this->mingguan($tahun, $bulan),
            'bulanan'  => $this->bulanan($tahun),
            'tahunan'  => $this->tahunan(),
        };

        $totalPendapatan = collect($rows)->sum('total_pendapatan');
        $totalTransaksi  = collect($rows)->sum('jumlah_transaksi');

        return response()->json([
            'periode'          => $periode,
            'dari'             => $dari,
            'sampai'           => $sampai,
            'total_pendapatan' => $totalPendapatan,
            'total_transaksi'  => $totalTransaksi,
            'data'             => $rows,
        ]);
    }

    private function baseQuery()
    {
        return Transaksi::where('status_transaksi', Transaksi::STATUS_SELESAI);
    }

    private function harian(string $tanggal): array
    {
        $dari   = Carbon::parse($tanggal)->startOfDay();
        $sampai = Carbon::parse($tanggal)->endOfDay();

        $rows = $this->baseQuery()
            ->whereBetween('tanggal', [$dari, $sampai])
            ->selectRaw('HOUR(tanggal) as jam, COUNT(*) as jumlah_transaksi, SUM(total_harga) as total_pendapatan')
            ->groupBy('jam')
            ->orderBy('jam')
            ->get()
            ->map(fn ($r) => [
                'label'            => sprintf('%02d:00', $r->jam),
                'jumlah_transaksi' => (int) $r->jumlah_transaksi,
                'total_pendapatan' => (float) $r->total_pendapatan,
            ]);

        return [$dari->toDateString(), $sampai->toDateString(), $rows];
    }

    private function mingguan(int $tahun, int $bulan): array
    {
        $dari   = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
        $sampai = $dari->copy()->endOfMonth();

        $rows = $this->baseQuery()
            ->whereBetween('tanggal', [$dari, $sampai])
            ->selectRaw('WEEK(tanggal, 1) as minggu, MIN(DATE(tanggal)) as dari, MAX(DATE(tanggal)) as sampai, COUNT(*) as jumlah_transaksi, SUM(total_harga) as total_pendapatan')
            ->groupBy('minggu')
            ->orderBy('minggu')
            ->get()
            ->map(fn ($r) => [
                'label'            => "Minggu {$r->minggu} ({$r->dari} s/d {$r->sampai})",
                'jumlah_transaksi' => (int) $r->jumlah_transaksi,
                'total_pendapatan' => (float) $r->total_pendapatan,
            ]);

        return [$dari->toDateString(), $sampai->toDateString(), $rows];
    }

    private function bulanan(int $tahun): array
    {
        $dari   = Carbon::createFromDate($tahun, 1, 1)->startOfYear();
        $sampai = $dari->copy()->endOfYear();

        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $rows = $this->baseQuery()
            ->whereBetween('tanggal', [$dari, $sampai])
            ->selectRaw('MONTH(tanggal) as bulan, COUNT(*) as jumlah_transaksi, SUM(total_harga) as total_pendapatan')
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->get()
            ->map(fn ($r) => [
                'label'            => $namaBulan[$r->bulan] . " $tahun",
                'jumlah_transaksi' => (int) $r->jumlah_transaksi,
                'total_pendapatan' => (float) $r->total_pendapatan,
            ]);

        return [$dari->toDateString(), $sampai->toDateString(), $rows];
    }
    private function tahunan(): array
    {
        $rows = $this->baseQuery()
            ->selectRaw('
                YEAR(tanggal) as tahun,
                COUNT(*) as jumlah_transaksi,
                SUM(total_harga) as total_pendapatan
            ')
            ->groupBy('tahun')
            ->orderBy('tahun')
            ->get()
            ->map(fn ($r) => [
                'label'            => (string) $r->tahun,
                'jumlah_transaksi' => (int) $r->jumlah_transaksi,
                'total_pendapatan' => (float) $r->total_pendapatan,
            ]);

        $dari = $rows->first()['label'] ?? now()->year;
        $sampai = $rows->last()['label'] ?? now()->year;

        return [$dari, $sampai, $rows];
    }
}