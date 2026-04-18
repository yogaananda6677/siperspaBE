<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE pembayaran
            MODIFY status_bayar ENUM('menunggu', 'menunggu_validasi', 'lunas', 'gagal')
            NOT NULL DEFAULT 'menunggu'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE pembayaran
            MODIFY status_bayar ENUM('menunggu', 'lunas', 'gagal')
            NOT NULL DEFAULT 'menunggu'
        ");
    }
};
