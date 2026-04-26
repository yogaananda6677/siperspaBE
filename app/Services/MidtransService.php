<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MidtransService
{
    private function getBaseUrl(): string
    {
        $baseUrl = config('services.midtrans.base_url');

        if (! $baseUrl) {
            throw new \RuntimeException('Config services.midtrans.base_url belum diatur.');
        }

        return rtrim($baseUrl, '/');
    }

    private function getServerKey(): string
    {
        $serverKey = config('services.midtrans.server_key');

        if (! $serverKey) {
            throw new \RuntimeException('Config services.midtrans.server_key belum diatur.');
        }

        return $serverKey;
    }

    public function createQrisTransaction(array $payload): array
    {
        $baseUrl = $this->getBaseUrl();
        $serverKey = $this->getServerKey();

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->post($baseUrl.'/v2/charge', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Midtrans error: '.$response->body());
        }

        return $response->json();
    }

    public function getTransactionStatus(string $orderId): array
    {
        $baseUrl = $this->getBaseUrl();
        $serverKey = $this->getServerKey();

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->get($baseUrl.'/v2/'.$orderId.'/status');

        if (! $response->successful()) {
            throw new \RuntimeException('Midtrans status request gagal: '.$response->body());
        }

        return $response->json();
    }
}
