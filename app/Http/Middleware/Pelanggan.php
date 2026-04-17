<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Pelanggan
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // ✅ Cek apakah role = admin
        if ($request->user()->role !== 'pelanggan') {
            return response()->json([
                'message' => 'Unauthorized. Hanya admin yang dapat mengakses ini.',
            ], 403);
        }

        return $next($request);
    }
}
