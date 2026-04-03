<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailProduk extends Model
{
    use HasFactory;

    protected $table = 'detail_produk';

    protected $primaryKey = 'id_dt_produk';

    protected $fillable = [
        'id_transaksi',
        'id_produk',
        'qty',
        'subtotal',
    ];

    protected $casts = [
        'qty' => 'integer',
        'subtotal' => 'decimal:2',
    ];

    // ── Relasi ──────────────────────────────────────────

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'id_produk', 'id_produk');
    }

    // ── Helper ──────────────────────────────────────────

    /**
     * Hitung subtotal dari harga produk × qty lalu simpan.
     */
    public function hitungSubtotal(): void
    {
        $subtotal = $this->produk->harga * $this->qty;
        $this->update(['subtotal' => $subtotal]);
    }
}
