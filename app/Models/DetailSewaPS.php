<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailSewaPS extends Model
{
    use HasFactory;

    protected $table = 'detail_sewa_ps';

    protected $primaryKey = 'id_dt_booking';

    protected $fillable = [
        'id_transaksi',
        'id_ps',
        'jam_mulai',
        'durasi_jam',
        'durasi_menit',
        'jam_selesai',
        'harga_perjam',
        'tipe_ps',
        'subtotal',
    ];

    protected $casts = [
        'jam_mulai' => 'datetime',
        'jam_selesai' => 'datetime',
        'durasi_jam' => 'integer',
        'durasi_menit' => 'integer',
        'harga_perjam' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }

    public function playstation(): BelongsTo
    {
        return $this->belongsTo(Playstation::class, 'id_ps', 'id_ps');
    }

    public function durasiMenitEfektif(): int
    {
        if (! empty($this->durasi_menit)) {
            return (int) $this->durasi_menit;
        }

        if (! empty($this->durasi_jam)) {
            return (int) $this->durasi_jam * 60;
        }

        if ($this->jam_mulai && $this->jam_selesai) {
            return Carbon::parse($this->jam_mulai)->diffInMinutes($this->jam_selesai);
        }

        return 0;
    }

    public function hitungSubtotal(): float
    {
        return round(((float) $this->harga_perjam / 60) * $this->durasiMenitEfektif(), 2);
    }

    public function estimasiSelesai(): Carbon
    {
        return Carbon::parse($this->jam_mulai)->addMinutes($this->durasiMenitEfektif());
    }

    public function sisaDetik(): int
    {
        if (! $this->jam_selesai) {
            return 0;
        }

        return max(0, now()->diffInSeconds($this->jam_selesai, false));
    }

    public function tambahWaktu(int $menitTambahan): void
    {
        $basisAkhir = $this->jam_selesai && $this->jam_selesai->isFuture()
            ? $this->jam_selesai->copy()
            : now();

        $jamSelesaiBaru = $basisAkhir->copy()->addMinutes($menitTambahan);
        $durasiMenitBaru = Carbon::parse($this->jam_mulai)->diffInMinutes($jamSelesaiBaru);
        $durasiJamBaru = max(1, (int) ceil($durasiMenitBaru / 60));

        $this->update([
            'durasi_menit' => $durasiMenitBaru,
            'durasi_jam' => $durasiJamBaru,
            'jam_selesai' => $jamSelesaiBaru,
            'subtotal' => round(((float) $this->harga_perjam / 60) * $durasiMenitBaru, 2),
        ]);
    }

    public function selesaikan(): void
    {
        if ($this->playstation) {
            $this->playstation->updateStatus('tersedia');
        }
    }
}
