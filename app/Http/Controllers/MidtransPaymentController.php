<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use App\Models\Transaksi;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MidtransPaymentController extends Controller
{
    public function __construct(
        private MidtransService $midtransService
    ) {}

    private function transaksiRelations(): array
    {
        return [
            'user:id_user,name,username,email',
            'detailSewa.playstation.tipe',
            'detailProduk.produk',
            'pembayaran',
        ];
    }

    public function createQris(Request $request, string $id): JsonResponse
    {
        $transaksi = Transaksi::with($this->transaksiRelations())->findOrFail($id);

        if (! in_array($transaksi->status_transaksi, [
            Transaksi::STATUS_AKTIF,
            Transaksi::STATUS_MENUNGGU_PEMBAYARAN,
        ], true)) {
            return response()->json([
                'message' => 'Status transaksi ini tidak bisa dibuatkan QRIS.',
            ], 422);
        }

        if ($transaksi->pembayaran && $transaksi->pembayaran->sudahLunas()) {
            return response()->json([
                'message' => 'Transaksi ini sudah lunas.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $transaksi->hitungUlangTotal();

            $grossAmount = (int) round((float) $transaksi->total_harga);
            $orderId = 'TRX-'.$transaksi->id_transaksi.'-'.now()->timestamp;

            $payload = [
                'payment_type' => 'qris',
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $grossAmount,
                ],
                'qris' => [
                    'acquirer' => 'gopay',
                ],
                'customer_details' => [
                    'first_name' => $transaksi->user->name ?? 'Pelanggan',
                    'email' => $transaksi->user->email ?? 'customer@example.com',
                ],
                'custom_expiry' => [
                    'expiry_duration' => 15,
                    'unit' => 'minute',
                ],
            ];

            $result = $this->midtransService->createQrisTransaction($payload);

            $actions = collect($result['actions'] ?? []);
            $generateQrAction = $actions->firstWhere('name', 'generate-qr-code');

            $qrUrl = is_array($generateQrAction)
                ? ($generateQrAction['url'] ?? null)
                : null;

            $qrString = $result['qr_string'] ?? null;

            $pembayaran = Pembayaran::firstOrCreate(
                ['id_transaksi' => $transaksi->id_transaksi],
                [
                    'metode_pembayaran' => 'cash',
                    'total_bayar' => 0,
                    'kembalian' => 0,
                    'waktu_bayar' => null,
                    'status_bayar' => Pembayaran::STATUS_MENUNGGU,
                ]
            );

            $pembayaran->update([
                'metode_pembayaran' => 'online',
                'provider' => 'midtrans',
                'provider_order_id' => $orderId,
                'provider_transaction_id' => $result['transaction_id'] ?? null,
                'provider_payment_type' => $result['payment_type'] ?? 'qris',
                'provider_transaction_status' => $result['transaction_status'] ?? 'pending',
                'provider_fraud_status' => $result['fraud_status'] ?? null,
                'payment_payload' => json_encode($result),
                'qr_string' => $qrString,
                'qr_url' => $qrUrl,
                'expired_at' => now()->addMinutes(15),
                'total_bayar' => $grossAmount,
                'kembalian' => 0,
                'waktu_bayar' => null,
                'status_bayar' => Pembayaran::STATUS_LUNAS,
            ]);

            DB::commit();

            $transaksi->refresh()->load($this->transaksiRelations());

            return response()->json([
                'message' => 'QRIS berhasil dibuat.',
                'data' => [
                    'transaksi' => $transaksi,
                    'payment' => $transaksi->pembayaran,
                    'midtrans' => $result,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal membuat pembayaran QRIS: '.$e->getMessage(),
            ], 500);
        }
    }

    public function notification(Request $request): JsonResponse
    {
        $serverKey = config('services.midtrans.server_key');

        $orderId = (string) $request->input('order_id');
        $statusCode = (string) $request->input('status_code');
        $grossAmount = (string) $request->input('gross_amount');
        $signatureKey = (string) $request->input('signature_key');

        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if (! hash_equals($expectedSignature, $signatureKey)) {
            return response()->json([
                'message' => 'Invalid signature.',
            ], 403);
        }

        $pembayaran = Pembayaran::where('provider_order_id', $orderId)->first();

        if (! $pembayaran) {
            return response()->json([
                'message' => 'Pembayaran tidak ditemukan.',
            ], 404);
        }

        $transaksi = Transaksi::with([
            'detailSewa.playstation',
            'pembayaran',
        ])->findOrFail($pembayaran->id_transaksi);

        DB::beginTransaction();

        try {
            $transactionStatus = (string) $request->input('transaction_status');
            $fraudStatus = (string) $request->input('fraud_status');
            $paymentType = (string) $request->input('payment_type');
            $transactionId = (string) $request->input('transaction_id');

            $statusBayar = Pembayaran::STATUS_MENUNGGU;
            $waktuBayar = null;

            if (in_array($transactionStatus, ['capture', 'settlement'], true)) {
                if ($transactionStatus === 'capture' && $fraudStatus !== 'accept') {
                    $statusBayar = Pembayaran::STATUS_MENUNGGU;
                } else {
                    $statusBayar = Pembayaran::STATUS_LUNAS;
                    $waktuBayar = now();
                }
            } elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true)) {
                $statusBayar = Pembayaran::STATUS_GAGAL;
            }

            $pembayaran->update([
                'metode_pembayaran' => 'online',
                'provider' => 'midtrans',
                'provider_transaction_id' => $transactionId ?: $pembayaran->provider_transaction_id,
                'provider_payment_type' => $paymentType ?: $pembayaran->provider_payment_type,
                'provider_transaction_status' => $transactionStatus,
                'provider_fraud_status' => $fraudStatus ?: null,
                'payment_payload' => json_encode($request->all()),
                'waktu_bayar' => $waktuBayar,
                'status_bayar' => $statusBayar,
                'kembalian' => 0,
            ]);

            // Penting: jangan ubah transaksi jadi selesai di sini.
            // Biarkan tetap aktif, selesai nanti oleh schedule / waktu habis.
            if ($statusBayar === Pembayaran::STATUS_LUNAS) {
                if ($transaksi->isAplikasi() && $transaksi->isMenungguPembayaran()) {
                    foreach ($transaksi->detailSewa as $sewa) {
                        if ($sewa->playstation) {
                            $sewa->playstation->updateStatus('digunakan');
                        }
                    }

                    $transaksi->update([
                        'status_transaksi' => Transaksi::STATUS_AKTIF,
                    ]);
                }
            }

            if ($statusBayar === Pembayaran::STATUS_GAGAL) {
                // gagal bayar online jangan dipaksa selesai
                // biarkan status transaksi sesuai kebutuhan bisnis Anda
            }

            DB::commit();

            return response()->json([
                'message' => 'Notification processed.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Notification failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
