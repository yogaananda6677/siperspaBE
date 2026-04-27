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
        Schema::create('pengaduans', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_pengadu');
            $table->unsignedBigInteger('id_admin')->nullable();

            $table->string('judul_pengaduan');
            $table->enum('kategori_aduan', [
                'ps_rusak',
                'pelayanan',
                'kebersihan',
                'pembayaran',
                'fasilitas',
                'lainnya',
            ]);

            $table->text('isi_pengaduan');

            $table->string('foto_bukti')->nullable();

            $table->enum('status_pengaduan', [
                'pending',
                'proses',
                'selesai',
                'dibatalkan',
            ])->default('pending');

            $table->text('catatan_admin')->nullable();
            $table->timestamp('ditangani_pada')->nullable();
            $table->timestamp('diselesaikan_pada')->nullable();

            $table->timestamps();

            $table->foreign('id_pengadu')
                ->references('id_user')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('id_admin')
                ->references('id_user')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaduans');
    }
};
