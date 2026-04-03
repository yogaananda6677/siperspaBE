<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaksi extends Model
{
    use HasFactory;

    protected $table = 'transaksi';

    protected $primaryKey = 'id_transaksi';

    protected $fillable = [
        'id_user',
        'tanggal',
        'total_harga',
        'status_transaksi',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total_harga' => 'decimal:2',
    ];

    // ── Relasi ──────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function pembayaran(): HasOne
    {
        return $this->hasOne(Pembayaran::class, 'id_transaksi', 'id_transaksi');
    }

    public function detailSewa(): HasMany
    {
        return $this->hasMany(DetailSewaPs::class, 'id_transaksi', 'id_transaksi');
    }

    public function detailProduk(): HasMany
    {
        return $this->hasMany(DetailProduk::class, 'id_transaksi', 'id_transaksi');
    }

    // ── Helper ──────────────────────────────────────────

    /**
     * Hitung ulang total dari semua detail sewa + produk
     * lalu simpan ke kolom total_harga.
     */
    public function hitungUlangTotal(): void
    {
        $totalSewa = $this->detailSewa()->sum('subtotal');
        $totalProduk = $this->detailProduk()->sum('subtotal');

        $this->update(['total_harga' => $totalSewa + $totalProduk]);
    }

    public function sudahDibayar(): bool
    {
        return $this->pembayaran?->status_bayar === 'lunas';
    }
}
