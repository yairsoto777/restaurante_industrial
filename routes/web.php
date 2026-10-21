<?php

use App\Http\Controllers\Admin\CommercialContactController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Manager\EmployeeController;
use App\Http\Controllers\Manager\EmploymentApplicationController as ManagerEmploymentApplicationController;
use App\Http\Controllers\Manager\ModuleController;
use App\Http\Controllers\OperationalModuleController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SiteSelectionController;
use App\Http\Controllers\User\EmploymentApplicationController as UserEmploymentApplicationController;
use App\Http\Controllers\User\ServiceController as UserServiceController;
use App\Http\Controllers\Manager\SitePageController;
use App\Http\Controllers\PublicSiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicContactController::class, 'index'])->name('home');
Route::get('/empresas/{slug}', [PublicSiteController::class, 'show'])->name('sites.public');
Route::get('/operacion/mi-pagina-web/preview/{slug}', [PublicSiteController::class, 'preview'])
    ->middleware(['auth', 'active', 'role:manager'])
    ->name('manager.site-page.preview');
Route::post('/solicita-informacion', [PublicContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.forgot');
    Route::post('/forgot-password', [AuthController::class, 'sendTemporaryPassword'])
        ->middleware('throttle:5,1')
        ->name('password.send-temporary');
    Route::get('/activar-cuenta/{token}', [AuthController::class, 'showActivation'])->name('password.activation');
    Route::post('/activar-cuenta', [AuthController::class, 'activate'])
        ->middleware('throttle:5,1')
        ->name('password.activation.store');
    Route::get('/restablecer-contrasena/{token}', [AuthController::class, 'showActivation'])->name('password.reset');
    Route::post('/restablecer-contrasena', [AuthController::class, 'activate'])
        ->middleware('throttle:5,1')
        ->name('password.reset.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/cambiar-contrasena', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/cambiar-contrasena', [AuthController::class, 'changePassword'])->middleware('throttle:5,1')->name('password.change.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/sedes/seleccionar', [SiteSelectionController::class, 'index'])->name('sites.select');
    Route::post('/sedes/seleccionar', [SiteSelectionController::class, 'update'])->name('sites.select.update');

    Route::prefix('usuario')->name('user.')->group(function () {
        Route::get('solicitudes-empleo', [UserEmploymentApplicationController::class, 'index'])->name('applications.index');

        Route::middleware('role:common-user')->group(function () {
            Route::get('servicios-disponibles', [UserServiceController::class, 'index'])->name('services.index');
            Route::get('solicitudes-empleo/nueva', [UserEmploymentApplicationController::class, 'create'])->name('applications.create');
            Route::post('solicitudes-empleo', [UserEmploymentApplicationController::class, 'store'])->name('applications.store');
        });
    });

    Route::middleware('role:administrator,manager,Recepcion de insumos,Jefe de almacen')->group(function () {
        Route::get('/recepciones', [OperationalModuleController::class, 'show'])->name('receptions.index');
    });
    Route::middleware('role:administrator,manager,Personal de cocina,Jefe de cocina,Mesero,Capitan de meseros')->group(function () {
        Route::get('/servicios', [ServiceController::class, 'kitchenIndex'])->name('services.index');
        Route::get('/servicios/en-vivo', [ServiceController::class, 'live'])->name('services.live');
    });
    Route::middleware('role:Jefe de cocina')->group(function () {
        Route::get('/servicios/nuevo', [ServiceController::class, 'create'])->name('kitchen.services.create');
        Route::post('/servicios', [ServiceController::class, 'store'])->name('kitchen.services.store');
        Route::get('/servicios/{service}/editar', [ServiceController::class, 'edit'])->name('kitchen.services.edit');
        Route::put('/servicios/{service}', [ServiceController::class, 'update'])->name('kitchen.services.update');
    });
    Route::middleware('role:administrator,manager,Jefe de cocina,Jefe de almacen,Capitan de meseros')->get('/reportes', [OperationalModuleController::class, 'show'])->name('reports.index');

    Route::middleware('role:administrator,manager,Personal de cocina,Jefe de cocina,Recepcion de insumos,Jefe de almacen')->group(function () {
        Route::get('/inventario', [OperationalModuleController::class, 'show'])->name('inventory.index');
    });
    Route::middleware('role:administrator,manager,Personal de cocina,Jefe de cocina,Mesero,Capitan de meseros')->group(function () {
        Route::get('/mesas-y-zonas', [OperationalModuleController::class, 'show'])->name('tables.index');
        Route::get('/grupos-y-entregas', [OperationalModuleController::class, 'show'])->name('groups.index');
    });
    Route::middleware('role:administrator,manager,Personal de cocina,Jefe de cocina')->group(function () {
        Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
        Route::post('/menus/{service}', [MenuController::class, 'store'])->middleware('role:Jefe de cocina')->name('menus.store');
        Route::get('/platillos', [App\Http\Controllers\DishController::class, 'index'])->name('dishes.index');
        Route::get('/platillos/nuevo', [App\Http\Controllers\DishController::class, 'create'])->middleware('role:Jefe de cocina')->name('dishes.create');
        Route::post('/platillos', [App\Http\Controllers\DishController::class, 'store'])->middleware('role:Jefe de cocina')->name('dishes.store');
        Route::get('/platillos/{dish}', [App\Http\Controllers\DishController::class, 'show'])->name('dishes.show');
        Route::get('/platillos/{dish}/editar', [App\Http\Controllers\DishController::class, 'edit'])->middleware('role:Jefe de cocina')->name('dishes.edit');
        Route::put('/platillos/{dish}', [App\Http\Controllers\DishController::class, 'update'])->middleware('role:Jefe de cocina')->name('dishes.update');
    });
    Route::middleware('role:administrator,manager,Personal de cocina,Jefe de cocina,Recepcion de insumos,Jefe de almacen,Mesero,Capitan de meseros,Cajero')->group(function () {
        Route::get('/incidencias', [OperationalModuleController::class, 'show'])->name('incidents.index');
    });
    Route::middleware('role:administrator,manager,Jefe de almacen')->get('/pedidos', [OperationalModuleController::class, 'show'])->name('orders.index');
    Route::middleware('role:administrator,manager,Jefe de cocina,Jefe de almacen')->get('/requisiciones', [OperationalModuleController::class, 'show'])->name('requisitions.index');
    Route::middleware('role:administrator,manager,Cajero')->get('/caja', [OperationalModuleController::class, 'show'])->name('cash.index');
    Route::middleware('role:administrator,manager,Jefe de cocina')->get('/cocina', [OperationalModuleController::class, 'show'])->name('kitchen.index');

    Route::prefix('administracion')->name('admin.')->middleware('role:super-administrator,administrator')->group(function () {
        Route::patch('usuarios/{user}/hacer-administrador', [UserController::class, 'promote'])->name('users.promote');
        Route::patch('usuarios/{user}/hacer-gerente', [UserController::class, 'demote'])->name('users.demote');
        Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->except('show')->names('users');
        Route::get('sedes', [SiteController::class, 'index'])->name('sites.index');
        Route::view('configuracion', 'admin.settings')->name('settings');
        Route::get('empresas/{site}', [DashboardController::class, 'company'])->name('companies.show');
    });

    Route::prefix('administracion')->name('admin.')->middleware('role:administrator')->group(function () {
        Route::post('sedes', [SiteController::class, 'store'])->name('sites.store');
        Route::put('sedes/{site}', [SiteController::class, 'update'])->name('sites.update');
    });

    Route::prefix('administracion')->name('admin.')->middleware('role:super-administrator')->group(function () {
        Route::get('contactos', [CommercialContactController::class, 'index'])->name('contacts.index');
        Route::get('contactos/{contact}', [CommercialContactController::class, 'show'])->name('contacts.show');
        Route::patch('contactos/{contact}', [CommercialContactController::class, 'update'])->name('contacts.update');
        Route::get('contactos/{contact}/convertir', [CommercialContactController::class, 'createConversion'])->name('contacts.convert.create');
        Route::post('contactos/{contact}/convertir', [CommercialContactController::class, 'convert'])->name('contacts.convert.store');
        Route::post('contactos/{contact}/reenviar-activacion', [CommercialContactController::class, 'resendActivation'])->name('contacts.activation.resend');
    });

    Route::prefix('operacion')->name('manager.')->middleware('role:manager')->group(function () {
        Route::get('sedes', [ModuleController::class, 'sites'])->name('sites');
        Route::post('sedes', [ModuleController::class, 'saveSite'])->name('sites.save');
        Route::get('mi-pagina-web', [SitePageController::class, 'edit'])->name('site-page.edit');
        Route::get('mi-pagina-web/editor', [SitePageController::class, 'editor'])->name('site-page.editor');
        Route::post('mi-pagina-web/editor', [SitePageController::class, 'saveEditor'])->name('site-page.editor.save');
        Route::get('mi-pagina-web/media', [SitePageController::class, 'media'])->name('site-page.media');
        Route::post('mi-pagina-web/media', [SitePageController::class, 'uploadMedia'])->name('site-page.media.upload');
        Route::delete('mi-pagina-web/media/{media}', [SitePageController::class, 'deleteMedia'])->name('site-page.media.delete');
        Route::post('mi-pagina-web', [SitePageController::class, 'save'])->name('site-page.save');
        Route::post('mi-pagina-web/publicar', [SitePageController::class, 'publish'])->name('site-page.publish');
        Route::resource('empleados', EmployeeController::class)
            ->parameters(['empleados' => 'employee'])
            ->except(['show', 'destroy'])
            ->names('employees');
        Route::get('solicitudes-empleo', [ManagerEmploymentApplicationController::class, 'index'])->name('applications.index');
        Route::get('solicitudes-empleo/{application}', [ManagerEmploymentApplicationController::class, 'show'])->name('applications.show');
        Route::patch('solicitudes-empleo/{application}/aprobar', [ManagerEmploymentApplicationController::class, 'approve'])->name('applications.approve');
        Route::patch('solicitudes-empleo/{application}/rechazar', [ManagerEmploymentApplicationController::class, 'reject'])->name('applications.reject');
        Route::get('servicios', [ServiceController::class, 'managerIndex'])->name('services.index');
        Route::get('servicios/nuevo', [ServiceController::class, 'create'])->name('services.create');
        Route::post('servicios', [ServiceController::class, 'store'])->name('services.store');
        Route::get('servicios/{service}/editar', [ServiceController::class, 'edit'])->name('services.edit');
        Route::put('servicios/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::patch('servicios/{service}/aprobar', [ServiceController::class, 'approve'])->name('services.approve');
        Route::patch('servicios/{service}/rechazar', [ServiceController::class, 'reject'])->name('services.reject');
        Route::get('{module}', [ModuleController::class, 'show'])->whereIn('module', ['recepcion', 'servicios', 'reportes'])->name('module');
    });
});
