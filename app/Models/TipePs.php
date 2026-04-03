<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipePs extends Model
{
    use HasFactory;

    protected $table = 'tipe_ps';

    protected $primaryKey = 'id_tipe';

    protected $fillable = [
        'nama_tipe',
        'harga_sewa',
    ];

    protected $casts = [
        'harga_sewa' => 'decimal:2',
    ];

    // ── Relasi ──────────────────────────────────────────

    public function playstation(): HasMany
    {
        return $this->hasMany(Playstation::class, 'id_tipe', 'id_tipe');
    }
}
