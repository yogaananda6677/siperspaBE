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
        Schema::create('playstation', function (Blueprint $table) {
            $table->id('id_ps');
            $table->foreignId('id_tipe')
                ->constrained('tipe_ps', 'id_tipe')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('nomor_ps')->unique();    // misal: PS5-01, PS4-03
            $table->enum('status_ps', ['tersedia', 'digunakan', 'maintenance'])
                ->default('tersedia');
            $table->timestamps();

            $table->index('id_tipe');
            $table->index('status_ps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playstation');
    }
};
