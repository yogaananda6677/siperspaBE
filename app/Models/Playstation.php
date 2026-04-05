<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playstation extends Model
{
    use HasFactory;

    protected $table = 'playstation';

    protected $primaryKey = 'id_ps';

    protected $fillable = [
        'id_tipe',
        'nomor_ps',
        'status_ps',
    ];

    // ── Relasi ──────────────────────────────────────────

    public function tipe(): BelongsTo
    {
        return $this->belongsTo(TipePs::class, 'id_tipe', 'id_tipe');
    }

    public function detailSewa(): HasMany
    {
        return $this->hasMany(DetailSewaPS::class, 'id_ps', 'id_ps');
    }

    // ── Helper ──────────────────────────────────────────

    public function updateStatus(string $status): bool
    {
        return $this->update(['status_ps' => $status]);
    }

    public function tersedia(): bool
    {
        return $this->status_ps === 'tersedia';
    }
}
