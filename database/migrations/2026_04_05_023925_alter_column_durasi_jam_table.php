<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_sewa_ps', function (Blueprint $table) {
            if (! Schema::hasColumn('detail_sewa_ps', 'durasi_menit')) {
                $table->integer('durasi_menit')->default(60)->after('jam_mulai');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detail_sewa_ps', function (Blueprint $table) {
            if (Schema::hasColumn('detail_sewa_ps', 'durasi_menit')) {
                $table->dropColumn('durasi_menit');
            }
        });
    }
};
