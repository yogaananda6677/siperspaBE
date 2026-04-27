<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // ✅ tambah ini

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable; // ✅ tambah HasApiTokens di sini'

    protected $primaryKey = 'id_user';

    protected $fillable = [
        'name',
        'username',
        'email',
        'role',
        'password',
        'fcm_token', // untuk menyimpan token FCM
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'fcm_token', // sembunyikan juga token FCM saat serialisasi
    ];

    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'id_user', 'id_user');
    }

    public function pengaduan(): HasMany
    {
        return $this->hasMany(Pengaduan::class, 'id_pengadu', 'id_user');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
