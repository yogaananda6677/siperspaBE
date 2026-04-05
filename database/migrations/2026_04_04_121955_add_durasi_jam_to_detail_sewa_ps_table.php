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
        Schema::table('detail_sewa_ps', function (Blueprint $table) {
            $table->integer('durasi_jam')->default(1)->after('jam_mulai');
        });
    }

    public function down(): void
    {
        Schema::table('detail_sewa_ps', function (Blueprint $table) {
            $table->dropColumn('durasi_jam');
        });
    }
};
