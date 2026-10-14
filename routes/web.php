<?php

use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Manager\ModuleController;
use App\Http\Controllers\OperationalModuleController;
use App\Http\Controllers\SiteSelectionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.forgot');
    Route::post('/forgot-password', [AuthController::class, 'sendTemporaryPassword'])
        ->middleware('throttle:5,1')
        ->name('password.send-temporary');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/sedes/seleccionar', [SiteSelectionController::class, 'index'])->name('sites.select');
    Route::post('/sedes/seleccionar', [SiteSelectionController::class, 'update'])->name('sites.select.update');

    Route::middleware('role:administrator,manager')->group(function () {
        Route::get('/recepciones', [OperationalModuleController::class, 'show'])->name('receptions.index');
        Route::get('/inventario', [OperationalModuleController::class, 'show'])->name('inventory.index');
        Route::get('/servicios', [OperationalModuleController::class, 'show'])->name('services.index');
        Route::get('/servicios/en-vivo', [OperationalModuleController::class, 'show'])->name('services.live');
        Route::get('/mesas-y-zonas', [OperationalModuleController::class, 'show'])->name('tables.index');
        Route::get('/grupos-y-entregas', [OperationalModuleController::class, 'show'])->name('groups.index');
        Route::get('/menus', [OperationalModuleController::class, 'show'])->name('menus.index');
        Route::get('/platillos', [OperationalModuleController::class, 'show'])->name('dishes.index');
        Route::get('/incidencias', [OperationalModuleController::class, 'show'])->name('incidents.index');
        Route::get('/reportes', [OperationalModuleController::class, 'show'])->name('reports.index');
    });

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
