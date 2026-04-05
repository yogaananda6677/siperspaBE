<?php

use App\Models\DetailSewaPS;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    // Ambil semua sewa yang jam_selesai sudah lewat
    // tapi transaksinya masih aktif
    $sewas = DetailSewaPS::where('jam_selesai', '<=', now())
        ->whereHas('transaksi', fn ($q) => $q->where('status_transaksi', 'aktif'))
        ->with(['transaksi.detailSewa', 'playstation'])
        ->get();

    foreach ($sewas as $sewa) {
        // Bebaskan unit PS
        $sewa->playstation?->updateStatus('tersedia');

        // Cek apakah semua sewa di transaksi ini sudah lewat jam_selesai
        $masihAda = $sewa->transaksi
            ->detailSewa
            ->where('jam_selesai', '>', now())
            ->isNotEmpty();

        if (! $masihAda) {
            $sewa->transaksi->update(['status_transaksi' => 'selesai']);
            $sewa->transaksi->hitungUlangTotal();
        }
    }
})->everyMinute()->name('auto-selesai-transaksi');
