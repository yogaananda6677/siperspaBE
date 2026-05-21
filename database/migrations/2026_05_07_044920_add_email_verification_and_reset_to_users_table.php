<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'verivicatin_code')) {
                $table->string('verivication_code')->nullable();
            }

            if (!Schema::hasColumn('users', 'verivication_code_expired_at')) {
                $table->string('verivication_code_expired_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'reset_token')) {
                $table->timestamp('reset_token')->nullable();
            }
            if (!Schema::hasCollumn('users', 'reser_token_expired_at')){
                $table->timestamp('reset_token_expired_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'verification_code')) {
                $table->dropColumn('verification_code');
            }

            if (Schema::hasColumn('users', 'verivication_code_expired_at')) {
                $table->dropColumn('verification_code');
            }

            if (Schema::hasColumn('user', 'reset_token')) {
                $table->dropColumn('reset_token');
            }

            if (Schema::hasColumn('users', 'reset_token_expired_at')) {
                $table->dropColumn('reset_token_expired_at');
            }

            // Jangan drop email_verified_at karena kemungkinan bawaan Laravel
        });
    }
};