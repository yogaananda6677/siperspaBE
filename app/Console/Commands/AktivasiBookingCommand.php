<?php

namespace App\Console\Commands;

use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AktivasiBookingCommand extends Command
{
    protected $signature = 'booking:aktivasi';

    protected $description = 'Mengaktifkan booking yang sudah masuk jam mulai';

    public function handle(): int
    {
        $now = now();

        $transaksis = Transaksi::with(['detailSewa.playstation', 'pembayaran'])
            ->where('status_transaksi', Transaksi::STATUS_DIJADWALKAN)
            ->get();

        foreach ($transaksis as $transaksi) {
            $siapAktif = $transaksi->detailSewa->every(function ($sewa) use ($now) {
                return $now->gte(Carbon::parse($sewa->jam_mulai));
            });

            if (! $siapAktif) {
                continue;
            }

            DB::transaction(function () use ($transaksi) {
                foreach ($transaksi->detailSewa as $sewa) {
                    if ($sewa->playstation && $sewa->playstation->status_ps !== 'digunakan') {
                        $sewa->playstation->updateStatus('digunakan');
                    }
                }

                $transaksi->update([
                    'status_transaksi' => Transaksi::STATUS_AKTIF,
                ]);
            });
        }

        return self::SUCCESS;
    }
}
