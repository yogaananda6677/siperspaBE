<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Auth\LoginController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reset-password/{token}', [LoginController::class, 'showResetPasswordForm']);

Route::post('/reset-password', [LoginController::class, 'resetPassword']);

Route::get('/test-email', function () {

    Mail::raw('Tes email berhasil', function ($message) {
        $message->to('emailkamu@gmail.com')
                ->subject('Tes Email Laravel');
    });

    return 'Email terkirim';
});