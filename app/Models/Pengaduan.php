<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pengaduan extends Model
{
    use HasFactory;

    protected $table = 'pengaduans';

    protected $fillable = [
        'id_pengadu',
        'id_admin',
        'judul_pengaduan',
        'kategori_aduan',
        'isi_pengaduan',
        'foto_bukti',
        'status_pengaduan',
        'catatan_admin',
        'ditangani_pada',
        'diselesaikan_pada',
    ];

    protected $casts = [
        'ditangani_pada' => 'datetime',
        'diselesaikan_pada' => 'datetime',
    ];

    public function pengadu()
    {
        return $this->belongsTo(User::class, 'id_pengadu', 'id_user');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'id_admin', 'id_user');
    }

    public function getKategoriLabelAttribute()
    {
        return match ($this->kategori_aduan) {
            'ps_rusak' => 'PS Rusak',
            'pelayanan' => 'Pelayanan',
            'kebersihan' => 'Kebersihan',
            'pembayaran' => 'Pembayaran',
            'fasilitas' => 'Fasilitas',
            'lainnya' => 'Lainnya',
            default => '-',
        };
    }

    public function getStatusLabelAttribute()
    {
        return match ($this->status_pengaduan) {
            'pending' => 'Pending',
            'proses' => 'Diproses',
            'selesai' => 'Selesai',
            'dibatalkan' => 'Dibatalkan',
            default => '-',
        };
    }
}
