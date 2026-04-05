<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    protected $table = 'produk';

    protected $primaryKey = 'id_produk';

    protected $fillable = [
        'nama',
        'jenis',
        'harga',
        'stock',
    ];

    public function detailProduk(): HasMany
    {
        return $this->hasMany(DetailProduk::class, 'id_produk', 'id_produk');
    }

    public function tambahStock(int $jumlah): void
    {
        $this->increment('stock', $jumlah);
    }

    public function kurangiStock(int $jumlah): bool
    {
        if ($this->stock < $jumlah) {
            return false;
        }

        $this->decrement('stock', $jumlah);

        return true;
    }
}
