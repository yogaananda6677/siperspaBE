<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\MonitoringPlaystationController;
use App\Http\Controllers\PelangganController;
use App\Http\Controllers\PlaystationController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\TipePsController;
use App\Http\Controllers\TransaksiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// PUBLIC
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::get('/test', fn () => response()->json(['message' => 'API jalan']));

// AUTH USER
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/me', fn (Request $request) => response()->json($request->user()));
});

// ADMIN ONLY
Route::middleware(['auth:sanctum', 'role.admin'])->group(function () {
    Route::apiResource('tipe-ps', TipePsController::class);
    Route::apiResource('playstation', PlaystationController::class);

    Route::get('/produk', [ProdukController::class, 'index']);
    Route::post('/produk', [ProdukController::class, 'store']);
    Route::get('/produk/{id}', [ProdukController::class, 'show']);
    Route::put('/produk/{id}', [ProdukController::class, 'update']);
    Route::delete('/produk/{id}', [ProdukController::class, 'destroy']);
    Route::patch('/produk/{id}/stock', [ProdukController::class, 'updateStock']);

    Route::get('/admin/admins', [AdminController::class, 'index']);
    Route::post('/admin/admins', [AdminController::class, 'store']);
    Route::put('/admin/admins/{user}', [AdminController::class, 'update']);
    Route::delete('/admin/admins/{user}', [AdminController::class, 'destroy']);

    Route::get('/pelanggan', [PelangganController::class, 'index']);
    Route::post('/pelanggan', [PelangganController::class, 'store']);
    Route::put('/pelanggan/{id}', [PelangganController::class, 'update']);
    Route::delete('/pelanggan/{id}', [PelangganController::class, 'destroy']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/monitoring/playstation', [MonitoringPlaystationController::class, 'index']);

        Route::get('/detail-produk', [DetailProdukController::class, 'index']);
        Route::get('/detail-produk/{id}', [DetailProdukController::class, 'show']);

        Route::get('/transaksi', [TransaksiController::class, 'index']);
        Route::post('/transaksi', [TransaksiController::class, 'store']);
        Route::get('/transaksi/{id}', [TransaksiController::class, 'show']);
        Route::patch('/transaksi/{id}/tambah-produk', [TransaksiController::class, 'tambahProduk']);
        Route::patch('/transaksi/{id}/tambah-waktu', [TransaksiController::class, 'tambahWaktu']);
        Route::patch('/transaksi/{id}/selesai', [TransaksiController::class, 'selesai']);
        Route::patch('/transaksi/{id}/batal', [TransaksiController::class, 'batal']);
    });

    Route::patch('/transaksi/{id}/bayar', [TransaksiController::class, 'bayar']);
});
