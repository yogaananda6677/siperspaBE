<?php

use App\Models\DetailSewaPS;
use App\Models\Transaksi;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Auto selesai transaksi
|--------------------------------------------------------------------------
| Rule:
| - hanya transaksi AKTIF
| - hanya transaksi yang pembayarannya SUDAH LUNAS
| - hanya detail sewa yang jam_selesai <= sekarang
| - jika semua detail sewa dalam transaksi sudah selesai waktunya,
|   maka transaksi otomatis diubah jadi SELESAI
*/

Schedule::command('booking:aktivasi')->everyMinute();
Schedule::call(function () {
    $now = now();

    $detailSewas = DetailSewaPS::with([
        'playstation',
        'transaksi.pembayaran',
        'transaksi.detailSewa',
    ])
        ->where('jam_selesai', '<=', $now)
        ->whereHas('transaksi', function ($q) {
            $q->where('status_transaksi', Transaksi::STATUS_AKTIF)
                ->whereHas('pembayaran', function ($pay) {
                    $pay->where('status_bayar', 'lunas');
                });
        })
        ->get();

    foreach ($detailSewas as $sewa) {
        DB::beginTransaction();

        try {
            $transaksi = $sewa->transaksi;

            if (! $transaksi) {
                DB::rollBack();

                continue;
            }

            // Bebaskan PS kalau masih digunakan
            if ($sewa->playstation && $sewa->playstation->status_ps === 'digunakan') {
                $sewa->playstation->updateStatus('tersedia');
            }

            // Reload detailSewa terbaru
            $transaksi->load('detailSewa', 'pembayaran');

            // Pastikan masih aktif dan masih lunas
            $sudahLunas = $transaksi->pembayaran
                && $transaksi->pembayaran->status_bayar === 'lunas';

            if (! $transaksi->isAktif() || ! $sudahLunas) {
                DB::rollBack();

                continue;
            }

            // Cek apakah masih ada detail sewa lain yang belum habis
            $masihAdaSewaAktif = $transaksi->detailSewa->contains(function ($item) use ($now) {
                return $item->jam_selesai && $item->jam_selesai > $now;
            });

            // Kalau semua sewa sudah habis, auto selesai transaksi
            if (! $masihAdaSewaAktif) {
                $transaksi->update([
                    'status_transaksi' => Transaksi::STATUS_SELESAI,
                ]);

                $transaksi->hitungUlangTotal();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Auto selesai transaksi gagal', [
                'id_dt_booking' => $sewa->id_dt_booking ?? null,
                'id_transaksi' => $sewa->id_transaksi ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }
})->everyMinute()->name('auto-selesai-transaksi-lunas');
