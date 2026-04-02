<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'register']);

Route::get('/test', function () {
    return response()->json([
        'message' => 'API jalan',
    ]);
});
Route::post('/login', [LoginController::class,   'login']);

// Protected routes (butuh token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);

    // Contoh route protected lainnya
    Route::get('/me', function (Illuminate\Http\Request $request) {
        return response()->json($request->user());
    });
});

// ✅ Protected routes — khusus admin
Route::middleware(['auth:sanctum', 'role.admin'])->group(function () {
    // Contoh: Route::get('/admin/users', [UserController::class, 'index']);
});
