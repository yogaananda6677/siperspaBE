<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id('id_pembayaran');
            $table->foreignId('id_transaksi')                    // 1 transaksi = 1 pembayaran
                ->constrained('transaksi', 'id_transaksi')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->enum('metode_pembayaran', ['cash', 'qris', 'transfer', 'debit', 'online'])
                ->default('cash');
            $table->decimal('total_bayar', 12, 2);
            $table->decimal('kembalian', 12, 2)->default(0);
            $table->datetime('waktu_bayar');
            $table->enum('status_bayar', ['menunggu', 'lunas', 'gagal'])
                ->default('menunggu');
            $table->timestamps();

            $table->index('status_bayar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
