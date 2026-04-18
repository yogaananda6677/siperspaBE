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
        'sumber_transaksi',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'total_harga' => 'decimal:2',
    ];

    public const SUMBER_ADMIN = 'admin';

    public const SUMBER_APLIKASI = 'aplikasi';

    public const STATUS_MENUNGGU_PEMBAYARAN = 'menunggu_pembayaran';

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_WAITING = 'waiting';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_DITOLAK = 'dibatalkan';

    public const STATUS_DIJADWALKAN = 'dijadwalkan';

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
        $total = $totalSewa + $totalProduk;

        $this->forceFill([
            'total_harga' => $total,
        ])->save();

        return $total;
    }

    public function isAdmin(): bool
    {
        return $this->sumber_transaksi === self::SUMBER_ADMIN;
    }

    public function isAplikasi(): bool
    {
        return $this->sumber_transaksi === self::SUMBER_APLIKASI;
    }

    public function isAktif(): bool
    {
        return $this->status_transaksi === self::STATUS_AKTIF;
    }

    public function isMenungguPembayaran(): bool
    {
        return $this->status_transaksi === self::STATUS_MENUNGGU_PEMBAYARAN;
    }

    public function isSelesai(): bool
    {
        return $this->status_transaksi === self::STATUS_SELESAI;
    }

    public function isDibatalkan(): bool
    {
        return $this->status_transaksi === self::STATUS_DIBATALKAN;
    }
}
