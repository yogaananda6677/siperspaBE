<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('metode_pembayaran');
            $table->string('provider_order_id')->nullable()->unique()->after('provider');
            $table->string('provider_transaction_id')->nullable()->after('provider_order_id');
            $table->string('provider_payment_type')->nullable()->after('provider_transaction_id');
            $table->string('provider_transaction_status')->nullable()->after('provider_payment_type');
            $table->string('provider_fraud_status')->nullable()->after('provider_transaction_status');
            $table->text('payment_payload')->nullable()->after('provider_fraud_status');
            $table->text('qr_string')->nullable()->after('payment_payload');
            $table->string('qr_url')->nullable()->after('qr_string');
            $table->timestamp('expired_at')->nullable()->after('qr_url');
        });
    }

    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'provider_order_id',
                'provider_transaction_id',
                'provider_payment_type',
                'provider_transaction_status',
                'provider_fraud_status',
                'payment_payload',
                'qr_string',
                'qr_url',
                'expired_at',
            ]);
        });
    }
};
