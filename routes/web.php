<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::prefix('api')->group(function () {
    Route::post('/uploads', [UploadController::class, 'store'])->name('api.uploads.store');
    Route::get('/uploads', [DashboardController::class, 'uploads'])->name('api.uploads.index');
    Route::get('/uploads/{upload}', [DashboardController::class, 'show'])->name('api.uploads.show');
    Route::get('/products', [DashboardController::class, 'products'])->name('api.products.index');
    Route::get('/logs', [DashboardController::class, 'logs'])->name('api.logs.index');
});
