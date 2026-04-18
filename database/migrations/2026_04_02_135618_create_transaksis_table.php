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
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id('id_transaksi');

            $table->foreignId('id_user')
                ->constrained('users', 'id_user')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->dateTime('tanggal');
            $table->decimal('total_harga', 12, 2)->default(0);

            $table->enum('status_transaksi', [
                'menunggu_pembayaran',
                'dijadwalkan',
                'waiting',
                'aktif',
                'selesai',
                'dibatalkan',
            ])->default('waiting');

            $table->string('sumber_transaksi')
                ->default('admin');

            $table->timestamps();

            $table->index('id_user');
            $table->index('tanggal');
            $table->index('status_transaksi');
            $table->index('sumber_transaksi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
