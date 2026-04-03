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
            $table->date('tanggal');
            $table->decimal('total_harga', 12, 2)->default(0);
            $table->enum('status_transaksi', ['pending', 'aktif', 'selesai', 'dibatalkan'])
                ->default('pending');
            $table->timestamps();

            $table->index('id_user');
            $table->index('tanggal');
            $table->index('status_transaksi');
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
