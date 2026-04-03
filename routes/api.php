<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PlaystationController;
use App\Http\Controllers\TipePsController;
use Illuminate\Support\Facades\Route;

// =============================================
// PUBLIC — tidak butuh token
// =============================================
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class,   'login']);

Route::get('/test', function () {
    return response()->json(['message' => 'API jalan']);
});

// =============================================
// PROTECTED — semua user yang sudah login
// =============================================
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/me', fn (\Illuminate\Http\Request $r) => response()->json($r->user()));
});

// =============================================
// ADMIN ONLY — butuh token + role admin
// =============================================
Route::middleware(['auth:sanctum', 'role.admin'])->group(function () {
    // Data Master
    Route::apiResource('tipe-ps', TipePsController::class);

    // Tambahkan route admin lainnya di sini nanti:
    Route::apiResource('playstation', PlaystationController::class);
    // Route::apiResource('makanan',   MakananController::class);
    // Route::apiResource('transaksi', TransaksiController::class);
    // Route::apiResource('user',      UserController::class);
});
