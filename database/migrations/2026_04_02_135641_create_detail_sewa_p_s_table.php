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
        Schema::create('detail_sewa_ps', function (Blueprint $table) {
            $table->id('id_dt_booking');
            $table->foreignId('id_transaksi')
                ->constrained('transaksi', 'id_transaksi')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('id_ps')
                ->constrained('playstation', 'id_ps')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->datetime('jam_mulai');
            $table->datetime('jam_selesai')->nullable(); // nullable saat sesi belum selesai
            $table->decimal('harga_perjam', 10, 2);
            $table->string('tipe_ps');               // snapshot nama tipe saat booking
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();

            $table->index('id_transaksi');
            $table->index('id_ps');
            $table->index('jam_mulai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_sewa_p_s');
    }
};
