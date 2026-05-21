<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transaksi MODIFY COLUMN status_transaksi 
            ENUM('menunggu_pembayaran','dijadwalkan','waiting','aktif','selesai','dibatalkan','ditolak') 
            NOT NULL DEFAULT 'waiting'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transaksi MODIFY COLUMN status_transaksi 
            ENUM('menunggu_pembayaran','dijadwalkan','waiting','aktif','selesai','dibatalkan') 
            NOT NULL DEFAULT 'waiting'");
    }
};
