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
        Schema::create('detail_produk', function (Blueprint $table) {
            $table->id('id_dt_produk');
            $table->foreignId('id_transaksi')
                ->constrained('transaksi', 'id_transaksi')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('id_produk')
                ->constrained('produk', 'id_produk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('qty')->default(1);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();

            $table->index('id_transaksi');
            $table->index('id_produk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_produk');
    }
};
