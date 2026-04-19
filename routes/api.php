<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\MonitoringPlaystationController;
use App\Http\Controllers\Pelanggan\MonitoringPelangganController;
use App\Http\Controllers\PelangganController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PlaystationController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TipePsController;
use App\Http\Controllers\TransaksiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// =======================
// PUBLIC
// =======================
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::get('/test', fn () => response()->json(['message' => 'API jalan']));
Route::get('/produk', [ProdukController::class, 'index']);
Route::apiResource('playstation', PlaystationController::class);
Route::apiResource('tipe-ps', TipePsController::class);

// monitoring khusus pelanggan -> public
Route::get('/monitoring/pelanggan', [MonitoringPelangganController::class, 'index']);
Route::get('/transaksi', [TransaksiController::class, 'index']);
Route::post('/transaksi', [TransaksiController::class, 'store']);
Route::get('/transaksi/{id}', [TransaksiController::class, 'show']);
Route::patch('/transaksi/{id}/tambah-produk', [TransaksiController::class, 'tambahProduk']);
Route::patch('/transaksi/{id}/tambah-waktu', [TransaksiController::class, 'tambahWaktu']);
Route::patch('/transaksi/{id}/selesai', [TransaksiController::class, 'selesai']);
Route::patch('/transaksi/{id}/batal', [TransaksiController::class, 'batal']);
Route::patch('/transaksi/{id}/bayar', [TransaksiController::class, 'bayar']);

// =======================
// AUTH (SEMUA USER LOGIN)
// =======================
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/me', fn (Request $request) => response()->json($request->user()));
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::get('/transaksi-saya', [TransaksiController::class, 'transaksiSaya']);

    Route::patch('/transaksi/{id}/bayar', [PembayaranController::class, 'bayar']);
    Route::get('/pembayaran/cash-menunggu', [PembayaranController::class, 'cashMenunggu']);
    Route::patch('/pembayaran/{id}/konfirmasi-cash', [PembayaranController::class, 'konfirmasiCash']);
});

// =======================
// ADMIN ONLY
// =======================
Route::middleware(['auth:sanctum', 'role.admin'])->group(function () {

    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);

    Route::get('/monitoring/playstation', [MonitoringPlaystationController::class, 'index']);

    Route::apiResource('playstation', PlaystationController::class);

    Route::post('/produk', [ProdukController::class, 'store']);
    Route::get('/produk/{id}', [ProdukController::class, 'show']);
    Route::put('/produk/{id}', [ProdukController::class, 'update']);
    Route::delete('/produk/{id}', [ProdukController::class, 'destroy']);
    Route::patch('/produk/{id}/stock', [ProdukController::class, 'updateStock']);

    Route::get('/pelanggan', [PelangganController::class, 'index']);
    Route::post('/pelanggan', [PelangganController::class, 'store']);
    Route::put('/pelanggan/{id}', [PelangganController::class, 'update']);
    Route::delete('/pelanggan/{id}', [PelangganController::class, 'destroy']);

    Route::get('/admin/admins', [AdminController::class, 'index']);
    Route::post('/admin/admins', [AdminController::class, 'store']);
    Route::put('/admin/admins/{user}', [AdminController::class, 'update']);
    Route::delete('/admin/admins/{user}', [AdminController::class, 'destroy']);

    Route::patch('/transaksi/{id}/approve', [TransaksiController::class, 'approve']);
    Route::patch('/transaksi/{id}/reject', [TransaksiController::class, 'reject']);
});

// =======================
// PELANGGAN ONLY
// =======================
Route::middleware(['auth:sanctum', 'role.pelanggan'])->group(function () {
    //
});
