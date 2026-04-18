<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    use HasFactory;

    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_MENUNGGU_VALIDASI = 'menunggu_validasi';

    public const STATUS_LUNAS = 'lunas';

    public const STATUS_GAGAL = 'gagal';

    protected $table = 'pembayaran';

    protected $primaryKey = 'id_pembayaran';

    protected $fillable = [
        'id_transaksi',
        'metode_pembayaran',
        'total_bayar',
        'kembalian',
        'waktu_bayar',
        'status_bayar',
    ];

    protected $casts = [
        'total_bayar' => 'decimal:2',
        'kembalian' => 'decimal:2',
        'waktu_bayar' => 'datetime',
    ];

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }

    public function sudahLunas(): bool
    {
        return $this->status_bayar === 'lunas';
    }

    public function masihMenunggu(): bool
    {
        return $this->status_bayar === 'menunggu';
    }
}
