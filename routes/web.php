<?php

use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Manager\ModuleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('administracion')->name('admin.')->middleware('role:administrator')->group(function () {
        Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->except(['show', 'destroy'])->names('users');
        Route::get('sedes', [SiteController::class, 'index'])->name('sites.index');
        Route::post('sedes', [SiteController::class, 'store'])->name('sites.store');
        Route::put('sedes/{site}', [SiteController::class, 'update'])->name('sites.update');
        Route::view('configuracion', 'admin.settings')->name('settings');
    });

    Route::prefix('operacion')->name('manager.')->middleware('role:manager')->group(function () {
        Route::get('sedes', [ModuleController::class, 'sites'])->name('sites');
        Route::get('{module}', [ModuleController::class, 'show'])->name('module');
    });
});
