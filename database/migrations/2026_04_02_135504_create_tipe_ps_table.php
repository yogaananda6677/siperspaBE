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
        Schema::create('tipe_ps', function (Blueprint $table) {
            $table->id('id_tipe');
            $table->string('nama_tipe');            // contoh: PS4, PS5, PS4 Pro
            $table->decimal('harga_sewa', 10, 2);   // harga per jam
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipe_ps');
    }
};
