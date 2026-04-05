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
        'tanggal' => 'datetime',
        'total_harga' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function detailSewa(): HasMany
    {
        return $this->hasMany(DetailSewaPS::class, 'id_transaksi', 'id_transaksi');
    }

    public function detailProduk(): HasMany
    {
        return $this->hasMany(DetailProduk::class, 'id_transaksi', 'id_transaksi');
    }

    public function pembayaran(): HasOne
    {
        return $this->hasOne(Pembayaran::class, 'id_transaksi', 'id_transaksi');
    }

    public function hitungUlangTotal(): float
    {
        $this->loadMissing(['detailSewa', 'detailProduk']);

        $totalSewa = $this->detailSewa->sum(fn ($item) => (float) $item->subtotal);
        $totalProduk = $this->detailProduk->sum(fn ($item) => (float) $item->subtotal);
        $total = (float) $totalSewa + (float) $totalProduk;

        $this->forceFill([
            'total_harga' => $total,
        ])->save();

        return $total;
    }
}
