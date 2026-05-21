<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'verification_token')) {
                $table->dropColumn('verification_token');
            }

            if (!Schema::hasColumn('users', 'verification_code')) {
                $table->string('verification_code')->nullable()->after('email_verified_at');
            }

            if (!Schema::hasColumn('users', 'verification_code_expired_at')) {
                $table->timestamp('verification_code_expired_at')->nullable()->after('verification_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'verification_code_expired_at')) {
                $table->dropColumn('verification_code_expired_at');
            }

            if (Schema::hasColumn('users', 'verification_code')) {
                $table->dropColumn('verification_code');
            }

            if (!Schema::hasColumn('users', 'verification_token')) {
                $table->string('verification_token')->nullable()->after('email_verified_at');
            }
        });
    }
};