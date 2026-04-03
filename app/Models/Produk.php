<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    use HasFactory;

    protected $table = 'produk';

    protected $primaryKey = 'id_produk';

    protected $fillable = [
        'nama',
        'jenis',
        'harga',
        'stock',
    ];

    protected $casts = [
        'harga' => 'decimal:2',
        'stock' => 'integer',
    ];

    // ── Relasi ──────────────────────────────────────────

    public function detailProduk(): HasMany
    {
        return $this->hasMany(DetailProduk::class, 'id_produk', 'id_produk');
    }

    // ── Helper ──────────────────────────────────────────

    public function kurangiStock(int $qty): bool
    {
        if ($this->stock < $qty) {
            return false; // stock tidak cukup
        }

        return $this->decrement('stock', $qty);
    }

    public function tambahStock(int $qty): bool
    {
        return $this->increment('stock', $qty);
    }

    public function stockTersedia(): bool
    {
        return $this->stock > 0;
    }
}
