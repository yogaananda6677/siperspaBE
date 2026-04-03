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
        'jam_selesai',
        'harga_perjam',
        'tipe_ps',
        'subtotal',
    ];

    protected $casts = [
        'jam_mulai' => 'datetime',
        'jam_selesai' => 'datetime',
        'harga_perjam' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // ── Relasi ──────────────────────────────────────────

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }

    public function playstation(): BelongsTo
    {
        return $this->belongsTo(Playstation::class, 'id_ps', 'id_ps');
    }

    // ── Helper ──────────────────────────────────────────

    /**
     * Hitung durasi dalam jam (desimal).
     * Contoh: 1.5 = 1 jam 30 menit.
     */
    public function durasiJam(): float
    {
        $mulai = Carbon::parse($this->jam_mulai);
        $selesai = Carbon::parse($this->jam_selesai ?? now());

        return round($mulai->diffInMinutes($selesai) / 60, 2);
    }

    /**
     * Hitung dan simpan subtotal berdasarkan durasi × harga per jam.
     */
    public function hitungSubtotal(): void
    {
        $subtotal = $this->durasiJam() * $this->harga_perjam;
        $this->update(['subtotal' => $subtotal]);
    }

    /**
     * Akhiri sesi sewa: set jam_selesai, hitung subtotal,
     * update status PS jadi tersedia.
     */
    public function selesaikan(): void
    {
        $this->update(['jam_selesai' => now()]);
        $this->hitungSubtotal();
        $this->playstation->updateStatus('tersedia');
        $this->transaksi->hitungUlangTotal();
    }
}
