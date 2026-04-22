<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MidtransService
{
    public function createQrisTransaction(array $payload): array
    {
        $baseUrl = config('services.midtrans.base_url');
        $serverKey = config('services.midtrans.server_key');

        if (! $baseUrl) {
            throw new \RuntimeException('Config services.midtrans.base_url belum diatur.');
        }

        if (! $serverKey) {
            throw new \RuntimeException('Config services.midtrans.server_key belum diatur.');
        }

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->post($baseUrl.'/v2/charge', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Midtrans error: '.$response->body());
        }

        return $response->json();
    }
}
