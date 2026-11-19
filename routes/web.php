<?php
use App\Http\Controllers\Capitan\MesasZonasController;
use App\Http\Controllers\Admin\CommercialContactController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Capitan\CapitanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DishCategoryController;
use App\Http\Controllers\Manager\EmployeeController;
use App\Http\Controllers\Manager\EmploymentApplicationController as ManagerEmploymentApplicationController;
use App\Http\Controllers\Manager\ModuleController;
use App\Http\Controllers\Manager\SitePageController;
use App\Http\Controllers\OperationalModuleController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\KitchenWorkflowController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SiteSelectionController;
use App\Http\Controllers\User\EmploymentApplicationController as UserEmploymentApplicationController;
use App\Http\Controllers\User\ServiceController as UserServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PÁGINAS PÚBLICAS
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicContactController::class, 'index'])
    ->name('home');

Route::get('/empresas/{slug}', [PublicSiteController::class, 'show'])
    ->name('sites.public');

Route::get(
    '/operacion/mi-pagina-web/preview/{slug}',
    [PublicSiteController::class, 'preview']
)
    ->middleware(['auth', 'active', 'role:manager'])
    ->name('manager.site-page.preview');

Route::post(
    '/solicita-informacion',
    [PublicContactController::class, 'store']
)
    ->middleware('throttle:5,1')
    ->name('contact.store');

/*
|--------------------------------------------------------------------------
| AUTENTICACIÓN
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    Route::get('/login', [AuthController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'store'])
        ->name('login.store');

    Route::get(
        '/forgot-password',
        [AuthController::class, 'showForgotPassword']
    )->name('password.forgot');

    Route::post(
        '/forgot-password',
        [AuthController::class, 'sendTemporaryPassword']
    )
        ->middleware('throttle:5,1')
        ->name('password.send-temporary');

    Route::get(
        '/activar-cuenta/{token}',
        [AuthController::class, 'showActivation']
    )->name('password.activation');

    Route::post(
        '/activar-cuenta',
        [AuthController::class, 'activate']
    )
        ->middleware('throttle:5,1')
        ->name('password.activation.store');

    Route::get(
        '/restablecer-contrasena/{token}',
        [AuthController::class, 'showActivation']
    )->name('password.reset');

    Route::post(
        '/restablecer-contrasena',
        [AuthController::class, 'activate']
    )
        ->middleware('throttle:5,1')
        ->name('password.reset.store');

});

/*
|--------------------------------------------------------------------------
| CIERRE DE SESIÓN Y CAMBIO DE CONTRASEÑA
|--------------------------------------------------------------------------
*/

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {

    Route::get(
        '/cambiar-contrasena',
        [AuthController::class, 'showChangePassword']
    )->name('password.change');

    Route::post(
        '/cambiar-contrasena',
        [AuthController::class, 'changePassword']
    )
        ->middleware('throttle:5,1')
        ->name('password.change.store');

});

/*
|--------------------------------------------------------------------------
| RUTAS PRIVADAS
| USUARIOS AUTENTICADOS Y ACTIVOS
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active'])->group(function () {
    Route::patch('gerente/programaciones/{revision}/revision',[\App\Http\Controllers\ServiceScheduleController::class,'review'])->whereNumber('revision')->middleware('role:manager')->name('manager.schedules.review');
    Route::middleware('role:Jefe de cocina')->prefix('jefe-cocina')->name('chef.')->group(function () {
        Route::get('programaciones/nueva',[\App\Http\Controllers\ServiceScheduleController::class,'form'])->name('schedules.create');
        Route::get('programaciones/{schedule}/editar',[\App\Http\Controllers\ServiceScheduleController::class,'form'])->whereNumber('schedule')->name('schedules.edit');
        Route::get('programaciones/{schedule}',[\App\Http\Controllers\ServiceScheduleController::class,'show'])->whereNumber('schedule')->name('schedules.show');
        Route::post('programaciones',[\App\Http\Controllers\ServiceScheduleController::class,'save'])->name('schedules.store');
        Route::put('programaciones/{schedule}',[\App\Http\Controllers\ServiceScheduleController::class,'save'])->whereNumber('schedule')->name('schedules.update');
        Route::post('menus/{service}/copiar',[\App\Http\Controllers\ServiceScheduleController::class,'copyMenu'])->whereNumber('service')->name('menus.copy');
        $controller = \App\Http\Controllers\ChefWorkspaceController::class;
        Route::get('servicios',[$controller,'servicesIndex'])->name('services');
        Route::get('servicios/nuevo',[$controller,'serviceForm'])->name('services.create');
        Route::get('servicios/{service}/editar',[$controller,'serviceForm'])->whereNumber('service')->name('services.edit');
        Route::post('servicios',[$controller,'saveService'])->name('services.store');
        Route::put('servicios/{service}',[$controller,'saveService'])->whereNumber('service')->name('services.update');
        Route::get('platillos',[$controller,'dishesIndex'])->name('dishes');
        Route::get('platillos/nuevo',[$controller,'dishForm'])->name('dishes.create');
        Route::get('platillos/{dish}/editar',[$controller,'dishForm'])->whereNumber('dish')->name('dishes.edit');
        Route::get('platillos/{dish}',[$controller,'dishShow'])->whereNumber('dish')->name('dishes.show');
        Route::post('platillos',[$controller,'saveDish'])->name('dishes.store');
        Route::put('platillos/{dish}',[$controller,'saveDish'])->whereNumber('dish')->name('dishes.update');
        Route::post('categorias',[$controller,'categoryStore'])->name('categories.store');
        Route::get('menus',[$controller,'menus'])->name('menus');
        Route::post('menus/{service}',[$controller,'saveMenu'])->whereNumber('service')->name('menus.store');
        Route::get('generador',[$controller,'generator'])->name('generator');
        Route::post('generador/diseno',[$controller,'saveDesign'])->name('generator.save');
        Route::post('generador/vista-previa',[$controller,'generatorPreview'])->name('generator.preview');
        Route::post('generador/imprimir',[$controller,'generatorPrint'])->name('generator.print');
        Route::post('generador/pdf',[$controller,'generatorPdf'])->name('generator.pdf');
        Route::get('comandas',[$controller,'commandsIndex'])->name('commands');
        Route::get('insumos',[$controller,'stockIndex'])->name('stock');
        Route::get('incidencias',[$controller,'incidentsIndex'])->name('incidents');
        Route::post('incidencias',[$controller,'incidentStore'])->name('incidents.store');
        Route::put('incidencias/{incident}/evidencia',[$controller,'incidentEvidence'])->whereNumber('incident')->name('incidents.evidence');
        Route::get('notificaciones',[$controller,'notifications'])->name('notifications.index');
        Route::post('notificaciones/leer',[$controller,'notifications'])->name('notifications.read');
    });
    Route::get('/roles/seleccionar', [\App\Http\Controllers\ActiveRoleController::class, 'index'])->name('roles.select');
    Route::post('/roles/seleccionar', [\App\Http\Controllers\ActiveRoleController::class, 'update'])->name('roles.update');

    /*
    |--------------------------------------------------------------------------
    | PANEL PRINCIPAL
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');

    Route::middleware('role:Jefe de almacen')->group(function () {
        Route::get('/almacen/ubicaciones', [\App\Http\Controllers\Almacen\WarehouseLocationController::class, 'index'])->name('warehouse.locations.index');
        Route::post('/almacen/ubicaciones', [\App\Http\Controllers\Almacen\WarehouseLocationController::class, 'store'])->name('warehouse.locations.store');
        Route::get('/almacen/movimientos', [\App\Http\Controllers\Almacen\WarehouseWorkspaceController::class, 'movements'])->name('warehouse.movements');
        Route::get('/almacen/alertas', [\App\Http\Controllers\Almacen\WarehouseWorkspaceController::class, 'alerts'])->name('warehouse.alerts');
        Route::get('/almacen/notificaciones', [\App\Http\Controllers\Almacen\WarehouseWorkspaceController::class, 'notifications'])->name('warehouse.notifications.index');
        Route::post('/almacen/notificaciones/leer', [\App\Http\Controllers\Almacen\WarehouseWorkspaceController::class, 'notifications'])->name('warehouse.notifications.read');
        Route::post('/recepciones/revision/{receipt}/confirmar', [\App\Http\Controllers\ReceptionOrderController::class, 'confirm'])->whereNumber('receipt')->name('receptions.review.confirm');
        Route::post('/recepciones/revision/{receipt}/devolver', [\App\Http\Controllers\ReceptionOrderController::class, 'returnForCorrection'])->whereNumber('receipt')->name('receptions.review.return');
    });

    /*
    |--------------------------------------------------------------------------
    | SELECCIÓN DE SEDE
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/sedes/seleccionar',
        [SiteSelectionController::class, 'index']
    )->name('sites.select');

    Route::post(
        '/sedes/seleccionar',
        [SiteSelectionController::class, 'update']
    )->name('sites.select.update');

    /*
    |--------------------------------------------------------------------------
    | USUARIO COMÚN
    |--------------------------------------------------------------------------
    */

    Route::prefix('usuario')
        ->name('user.')
        ->group(function () {

            Route::get(
                'solicitudes-empleo',
                [UserEmploymentApplicationController::class, 'index']
            )->name('applications.index');

            Route::middleware('role:common-user')
                ->group(function () {

                    Route::get(
                        'servicios-disponibles',
                        [UserServiceController::class, 'index']
                    )->name('services.index');

                    Route::get(
                        'solicitudes-empleo/nueva',
                        [UserEmploymentApplicationController::class, 'create']
                    )->name('applications.create');

                    Route::post(
                        'solicitudes-empleo',
                        [UserEmploymentApplicationController::class, 'store']
                    )->name('applications.store');

                });
        });

    /*
    |--------------------------------------------------------------------------
    | RECEPCIONES
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:administrator,manager,Recepcion de insumos,Jefe de almacen'
    )->group(function () {

        Route::get(
            '/recepciones',
            [\App\Http\Controllers\ReceptionOrderController::class, 'index']
        )->name('receptions.index');

        Route::middleware('role:Recepcion de insumos')->group(function () {
            Route::get('/recepciones/registrar', [\App\Http\Controllers\ReceptionWorkspaceController::class, 'deliveries'])->name('receptions.register');
            Route::get('/recepciones/historial', [\App\Http\Controllers\ReceptionWorkspaceController::class, 'history'])->name('receptions.history');
            Route::get('/recepciones/notificaciones', [\App\Http\Controllers\ReceptionWorkspaceController::class, 'notifications'])->name('receptions.notifications.index');
            Route::post('/recepciones/notificaciones/leer', [\App\Http\Controllers\ReceptionWorkspaceController::class, 'notifications'])->name('receptions.notifications.read');
            Route::get('/recepciones/registradas/{receipt}/corregir', [\App\Http\Controllers\ReceptionCaptureController::class, 'correct'])->whereNumber('receipt')->name('receptions.corrections.create');
        });

        Route::get('/recepciones/revision/{receipt}', [\App\Http\Controllers\ReceptionOrderController::class, 'supervise'])
            ->whereNumber('receipt')->name('receptions.review.show');

        Route::get('/recepciones/pedidos/{order}', [\App\Http\Controllers\ReceptionOrderController::class, 'show'])
            ->whereNumber('order')->name('receptions.orders.show');

        Route::get('/recepciones/pedidos/{order}/nueva', [\App\Http\Controllers\ReceptionCaptureController::class, 'create'])
            ->whereNumber('order')->name('receptions.create');
        Route::post('/recepciones/pedidos/{order}/capturas', [\App\Http\Controllers\ReceptionCaptureController::class, 'store'])
            ->whereNumber('order')->name('receptions.store');
        Route::get('/recepciones/registradas/{receipt}', [\App\Http\Controllers\ReceptionCaptureController::class, 'show'])
            ->whereNumber('receipt')->name('receptions.receipts.show');

    });

    /*
    |--------------------------------------------------------------------------
    | SERVICIOS
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:administrator,manager,Personal de cocina,Jefe de cocina,Mesero,Capitan de meseros'
    )->group(function () {

        Route::get('/mesero', [App\Http\Controllers\MeseroController::class, 'index'])->middleware('role:Mesero')->name('waiter.index');
        Route::post('/mesero/incidencias', [App\Http\Controllers\MeseroController::class, 'storeIncident'])->middleware('role:Mesero')->name('waiter.incidents.store');
        Route::get('/mesero/perfil/fotografia', [App\Http\Controllers\MeseroController::class, 'photo'])->middleware('role:Mesero')->name('waiter.photo');
        Route::get('/mesero/notificaciones', [App\Http\Controllers\MeseroController::class, 'notifications'])->middleware('role:Mesero')->name('waiter.notifications.index');
        Route::post('/mesero/notificaciones/leidas', [App\Http\Controllers\MeseroController::class, 'readNotifications'])->middleware('role:Mesero')->name('waiter.notifications.read');
        Route::post('/mesero/atenciones', [App\Http\Controllers\MeseroController::class, 'storeAttention'])->middleware('role:Mesero')->name('waiter.attention.store');
        Route::post('/mesero/mesas', [App\Http\Controllers\MeseroController::class, 'storeTable'])->middleware('role:Mesero')->name('waiter.table.store');
        Route::post('/mesero/atenciones/{attention}/comanda', [App\Http\Controllers\MeseroController::class, 'saveCommand'])->middleware('role:Mesero')->name('waiter.command.save');
        Route::post('/mesero/comandas/{command}/observaciones', [App\Http\Controllers\MeseroController::class, 'addCommandObservation'])
            ->middleware('role:Mesero')->whereNumber('command')->name('waiter.command.observations.store');
        Route::post('/mesero/atenciones/{attention}/servicio-ofrecido', [App\Http\Controllers\MeseroController::class, 'storeOfferedService'])->middleware('role:Mesero')->name('waiter.offered-service.store');
        Route::put('/mesero/atenciones/{attention}/servicio-ofrecido', [App\Http\Controllers\MeseroController::class, 'updateOfferedService'])->middleware('role:Mesero')->name('waiter.offered-service.update');
        Route::patch('/mesero/atenciones/{attention}/servicio-ofrecido/finalizar', [App\Http\Controllers\MeseroController::class, 'finalizeOfferedService'])->middleware('role:Mesero')->name('waiter.offered-service.finalize');

        Route::get(
            '/servicios',
            [ServiceController::class, 'kitchenIndex']
        )->name('services.index');

        Route::get(
            '/servicios/en-vivo',
            [ServiceController::class, 'live']
        )->name('services.live');

    });

    /*
    |--------------------------------------------------------------------------
    | ADMINISTRACIÓN DE SERVICIOS - JEFE DE COCINA
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:Jefe de cocina')
        ->group(function () {

            Route::get(
                '/servicios/nuevo',
                [ServiceController::class, 'create']
            )->name('kitchen.services.create');

            Route::post(
                '/servicios',
                [ServiceController::class, 'store']
            )->name('kitchen.services.store');

            Route::get(
                '/servicios/{service}/editar',
                [ServiceController::class, 'edit']
            )->name('kitchen.services.edit');

            Route::put(
                '/servicios/{service}',
                [ServiceController::class, 'update']
            )->name('kitchen.services.update');

            Route::patch(
                '/servicios/{service}/estado',
                [ServiceController::class, 'updateOperationalStatus']
            )->name('kitchen.services.status');

        });

    /*
    |--------------------------------------------------------------------------
    | REPORTES OPERATIVOS
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:administrator,manager,Jefe de cocina,Jefe de almacen,Capitan de meseros'
    )
        ->get(
            '/reportes',
            [OperationalModuleController::class, 'show']
        )
        ->name('reports.index');

    /*
    |--------------------------------------------------------------------------
    | INVENTARIO
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:administrator,manager,Personal de cocina,Jefe de cocina,Recepcion de insumos,Jefe de almacen'
    )->group(function () {

        Route::get(
            '/inventario',
            [InventoryController::class, 'index']
        )->name('inventory.index');

    });

    // Operaciones de inventario: Jefe de cocina y Jefe de almacén.
    Route::get('/almacen/catalogo-insumos',[\App\Http\Controllers\Almacen\WarehouseIngredientController::class,'index'])
        ->middleware('role:Jefe de almacen')->name('warehouse.ingredients.index');
    // Los controladores siguen validando el rol y la sede activa.
    Route::middleware('role:Jefe de cocina,Jefe de almacen')
        ->group(function () {

            Route::post(
                '/inventario/catalogo',
                [InventoryController::class, 'storeIngredient']
            )->name('inventory.ingredients.store');

            // Editar datos del alimento (nombre y categoría, si existe).
            // La autorización por sede se valida en InventoryController.
            Route::put(
                '/inventario/insumos/{ingredient}',
                [InventoryController::class, 'updateIngredient']
            )
                ->whereNumber('ingredient')
                ->name('inventory.ingredients.update');

            Route::post(
                '/inventario/asignaciones',
                [InventoryController::class, 'assignIngredient']
            )->name('inventory.assignments.store');

            Route::post(
                '/inventario/movimientos',
                [InventoryController::class, 'storeMovement']
            )->name('inventory.movements.store');

            Route::put(
                '/inventario/insumos/{ingredient}/punto-de-reorden',
                [InventoryController::class, 'updateReorderPoint']
            )
                ->whereNumber('ingredient')
                ->name('inventory.reorder.update');

        });

    /*
    |--------------------------------------------------------------------------
    | MESAS, ZONAS, GRUPOS Y ENTREGAS
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:administrator,manager,Personal de cocina,Jefe de cocina,Mesero,Capitan de meseros'
    )->group(function () {

        Route::get(
            '/mesas-y-zonas',
            [OperationalModuleController::class, 'show']
        )->name('tables.index');

        Route::get(
            '/grupos-y-entregas',
            [KitchenWorkflowController::class, 'groups']
        )->name('groups.index');

    });

    Route::middleware('role:Jefe de cocina')
        ->group(function () {

            Route::post(
                '/grupos-y-entregas/grupos',
                [KitchenWorkflowController::class, 'storeGroup']
            )->name('groups.store');

            Route::post(
                '/grupos-y-entregas/atenciones',
                [KitchenWorkflowController::class, 'storeAttention']
            )->name('groups.attentions.store');

            Route::post(
                '/grupos-y-entregas/entregas',
                [KitchenWorkflowController::class, 'storeDelivery']
            )->name('groups.deliveries.store');

        });

    /*
    |--------------------------------------------------------------------------
    | MENÚS Y PLATILLOS
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:administrator,manager,Personal de cocina,Jefe de cocina'
    )->group(function () {

        Route::get(
            '/menus',
            [MenuController::class, 'index']
        )->name('menus.index');

        Route::post(
            '/menus/{service}',
            [MenuController::class, 'store']
        )
            ->middleware('role:Jefe de cocina')
            ->name('menus.store');

        Route::get(
            '/platillos',
            [App\Http\Controllers\DishController::class, 'index']
        )->name('dishes.index');

        Route::get(
            '/platillos/nuevo',
            [App\Http\Controllers\DishController::class, 'create']
        )
            ->middleware('role:Jefe de cocina')
            ->name('dishes.create');

        Route::post(
            '/platillos',
            [App\Http\Controllers\DishController::class, 'store']
        )
            ->middleware('role:Jefe de cocina')
            ->name('dishes.store');

        Route::get(
            '/platillos/categorias',
            [DishCategoryController::class, 'index']
        )
            ->middleware('role:Jefe de cocina')
            ->name('dishes.categories.index');

        Route::post(
            '/platillos/categorias',
            [DishCategoryController::class, 'store']
        )
            ->middleware('role:Jefe de cocina')
            ->name('dishes.categories.store');

        Route::delete(
            '/platillos/categorias/{category}',
            [DishCategoryController::class, 'destroy']
        )
            ->whereNumber('category')
            ->middleware('role:Jefe de cocina')
            ->name('dishes.categories.destroy');

        Route::get(
            '/platillos/{dish}',
            [App\Http\Controllers\DishController::class, 'show']
        )->name('dishes.show');

        Route::get(
            '/platillos/{dish}/editar',
            [App\Http\Controllers\DishController::class, 'edit']
        )
            ->middleware('role:Jefe de cocina')
            ->name('dishes.edit');

        Route::put(
            '/platillos/{dish}',
            [App\Http\Controllers\DishController::class, 'update']
        )
            ->middleware('role:Jefe de cocina')
            ->name('dishes.update');

    });

    /*
    |--------------------------------------------------------------------------
    | INCIDENCIAS GENERALES
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:administrator,manager,Personal de cocina,Jefe de cocina,Recepcion de insumos,Jefe de almacen,Mesero,Capitan de meseros,Cajero'
    )->group(function () {

        Route::get(
            '/incidencias',
            [IncidentController::class, 'index']
        )->name('incidents.index');

        Route::post(
            '/incidencias',
            [IncidentController::class, 'store']
        )->name('incidents.store');

    });

    /*
    |--------------------------------------------------------------------------
    | PEDIDOS Y CAJA
    |--------------------------------------------------------------------------
    */

    // PEDIDOS: gestión de compras del Jefe de almacén.
    // Separa /pedidos de /recepciones y conserva los permisos de consulta previos.
    Route::middleware('role:administrator,manager,Jefe de almacen')
        ->group(function () {
            Route::get('/pedidos', [\App\Http\Controllers\Almacen\WarehouseOrderController::class, 'index'])
                ->name('orders.index');

            Route::get('/pedidos/{order}', [\App\Http\Controllers\Almacen\WarehouseOrderController::class, 'show'])
                ->whereNumber('order')
                ->name('orders.show');
        });

    // Proveedores: catálogo global administrado por el Jefe de almacén.
    // Las rutas deben quedar dentro del grupo auth + active.
    Route::middleware('role:Jefe de almacen')
        ->prefix('pedidos/proveedores')
        ->name('orders.suppliers.')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\Almacen\WarehouseSupplierController::class, 'index'])
                ->name('index');
            Route::post('/', [\App\Http\Controllers\Almacen\WarehouseSupplierController::class, 'store'])
                ->name('store');
            Route::put('/{supplier}', [\App\Http\Controllers\Almacen\WarehouseSupplierController::class, 'update'])
                ->whereNumber('supplier')->name('update');
        });

    // Las escrituras de pedidos son exclusivas del Jefe de almacén.
    Route::middleware('role:Jefe de almacen')
        ->group(function () {
            Route::get('/pedidos/nuevo', [\App\Http\Controllers\Almacen\WarehouseOrderController::class, 'create'])
                ->name('orders.create');

            Route::post('/pedidos', [\App\Http\Controllers\Almacen\WarehouseOrderController::class, 'store'])
                ->name('orders.store');
        });

    Route::middleware(
        'role:administrator,manager,Cajero'
    )->group(function () {
        Route::get('/caja', [App\Http\Controllers\CashierController::class, 'index'])->name('cash.index');
        Route::get('/caja/historial', [App\Http\Controllers\CashierController::class, 'history'])->name('cash.history');
        Route::post('/caja/abrir', [App\Http\Controllers\CashierController::class, 'open'])->name('cash.open');
        Route::post('/caja/cerrar', [App\Http\Controllers\CashierController::class, 'close'])->name('cash.close');
        Route::post('/caja/atenciones/{attention}/cobrar', [App\Http\Controllers\CashierController::class, 'charge'])->middleware('role:Cajero')->whereNumber('attention')->name('cash.charge');
        Route::get('/caja/pagos/{payment}/ticket', [App\Http\Controllers\CashierController::class, 'ticket'])->whereNumber('payment')->name('cash.ticket');
    });

    /*
    |--------------------------------------------------------------------------
    | COCINA
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:administrator,manager,Personal de cocina,Jefe de cocina'
    )->group(function () {

        Route::get(
            '/cocina',
            [KitchenWorkflowController::class, 'kitchen']
        )->name('kitchen.index');

    });

    Route::middleware('role:Personal de cocina')
        ->group(function () {
            Route::get('/cocina/notificaciones', [KitchenWorkflowController::class, 'observationNotifications'])->name('kitchen.notifications.index');
            Route::patch('/cocina/comandas/{command}/observaciones/{observation}/revision', [KitchenWorkflowController::class, 'acknowledgeObservation'])
                ->whereNumber(['command', 'observation'])->name('kitchen.observations.acknowledge');

            Route::patch(
                '/cocina/comandas/{detail}/estado',
                [KitchenWorkflowController::class, 'updateOrderStatus']
            )
                ->whereNumber('detail')
                ->name('kitchen.order-status.update');

        });

    /*
    |--------------------------------------------------------------------------
    | ADMINISTRACIÓN - USUARIOS Y SEDES
    |--------------------------------------------------------------------------
    */

    Route::prefix('administracion/general')->name('superadmin.')->middleware('role:super-administrator')->group(function(){
        Route::get('/',[\App\Http\Controllers\Admin\SuperadminWorkspaceController::class,'index'])->name('index');
        Route::post('/clientes',[\App\Http\Controllers\Admin\SuperadminWorkspaceController::class,'saveClient'])->name('clients.store');
        Route::put('/clientes/{client}',[\App\Http\Controllers\Admin\SuperadminWorkspaceController::class,'saveClient'])->whereNumber('client')->name('clients.update');
        Route::post('/sedes',[\App\Http\Controllers\Admin\SuperadminWorkspaceController::class,'saveSite'])->name('sites.store');
        Route::put('/sedes/{site}',[\App\Http\Controllers\Admin\SuperadminWorkspaceController::class,'saveSite'])->whereNumber('site')->name('sites.update');
        Route::post('/usuarios',[\App\Http\Controllers\Admin\SuperadminWorkspaceController::class,'saveUser'])->name('users.store');
        Route::put('/usuarios/{user}',[\App\Http\Controllers\Admin\SuperadminWorkspaceController::class,'saveUser'])->whereNumber('user')->name('users.update');
        Route::post('/usuarios/{user}/activacion',[\App\Http\Controllers\Admin\SuperadminWorkspaceController::class,'activation'])->whereNumber('user')->middleware('throttle:5,1')->name('users.activation');
        Route::get('/notificaciones',[\App\Http\Controllers\Admin\SuperadminWorkspaceController::class,'notifications'])->name('notifications.index');
        Route::post('/notificaciones/leidas',[\App\Http\Controllers\Admin\SuperadminWorkspaceController::class,'readNotifications'])->name('notifications.read');
    });

    Route::prefix('administracion')
        ->name('admin.')
        ->middleware('role:super-administrator,administrator')
        ->group(function () {

            Route::patch(
                'usuarios/{user}/hacer-administrador',
                [UserController::class, 'promote']
            )->name('users.promote');

            Route::patch(
                'usuarios/{user}/hacer-gerente',
                [UserController::class, 'demote']
            )->name('users.demote');

            Route::resource('usuarios', UserController::class)
                ->parameters(['usuarios' => 'user'])
                ->except('show')
                ->names('users');

            Route::get(
                'sedes',
                [SiteController::class, 'index']
            )->name('sites.index');

            Route::view(
                'configuracion',
                'admin.settings'
            )->name('settings');

            Route::get(
                'empresas/{site}',
                [DashboardController::class, 'company']
            )->name('companies.show');

        });

    /*
    |--------------------------------------------------------------------------
    | ADMINISTRACIÓN - CATÁLOGO DE UNIDADES
    |--------------------------------------------------------------------------
    */

    Route::prefix('administracion')
        ->name('admin.')
        ->middleware(
            'role:super-administrator,administrator,Jefe de cocina'
        )
        ->group(function () {

            Route::get(
                'unidades',
                [UnitController::class, 'index']
            )->name('units.index');

            Route::post(
                'unidades',
                [UnitController::class, 'store']
            )->name('units.store');

            Route::put(
                'unidades/{unit}',
                [UnitController::class, 'update']
            )
                ->whereNumber('unit')
                ->name('units.update');

            Route::delete(
                'unidades/{unit}',
                [UnitController::class, 'destroy']
            )
                ->whereNumber('unit')
                ->name('units.destroy');

        });

    /*
    |--------------------------------------------------------------------------
    | ADMINISTRACIÓN - MODIFICACIÓN DE SEDES
    |--------------------------------------------------------------------------
    */

    Route::prefix('administracion')
        ->name('admin.')
        ->middleware('role:administrator')
        ->group(function () {

            Route::post(
                'sedes',
                [SiteController::class, 'store']
            )->name('sites.store');

            Route::put(
                'sedes/{site}',
                [SiteController::class, 'update']
            )->name('sites.update');

        });

    /*
    |--------------------------------------------------------------------------
    | SUPERADMINISTRADOR - CONTACTOS
    |--------------------------------------------------------------------------
    */

    Route::prefix('administracion')
        ->name('admin.')
        ->middleware('role:super-administrator')
        ->group(function () {

            Route::get(
                'contactos',
                [CommercialContactController::class, 'index']
            )->name('contacts.index');

            Route::get(
                'contactos/{contact}',
                [CommercialContactController::class, 'show']
            )->name('contacts.show');

            Route::patch(
                'contactos/{contact}',
                [CommercialContactController::class, 'update']
            )->name('contacts.update');

            Route::get(
                'contactos/{contact}/convertir',
                [CommercialContactController::class, 'createConversion']
            )->name('contacts.convert.create');

            Route::post(
                'contactos/{contact}/convertir',
                [CommercialContactController::class, 'convert']
            )->name('contacts.convert.store');

            Route::post(
                'contactos/{contact}/reenviar-activacion',
                [CommercialContactController::class, 'resendActivation']
            )->name('contacts.activation.resend');

        });

    /*
    |--------------------------------------------------------------------------
    | GERENTE DE SEDE
    |--------------------------------------------------------------------------
    */

    Route::prefix('operacion')
        ->name('manager.')
        ->middleware('role:manager')
        ->group(function () {
            Route::get('notificaciones', [\App\Http\Controllers\Manager\NotificationController::class, 'index'])->name('notifications.index');
            Route::post('notificaciones/leer', [\App\Http\Controllers\Manager\NotificationController::class, 'read'])->name('notifications.read');

            Route::get(
                'sedes',
                [ModuleController::class, 'sites']
            )->name('sites');

            Route::post(
                'sedes',
                [ModuleController::class, 'saveSite']
            )->name('sites.save');

            Route::get(
                'mi-pagina-web',
                [SitePageController::class, 'edit']
            )->name('site-page.edit');

            Route::get(
                'mi-pagina-web/editor',
                [SitePageController::class, 'editor']
            )->name('site-page.editor');

            Route::post(
                'mi-pagina-web/editor',
                [SitePageController::class, 'saveEditor']
            )->name('site-page.editor.save');

            Route::get(
                'mi-pagina-web/media',
                [SitePageController::class, 'media']
            )->name('site-page.media');

            Route::post(
                'mi-pagina-web/media',
                [SitePageController::class, 'uploadMedia']
            )->name('site-page.media.upload');

            Route::delete(
                'mi-pagina-web/media/{media}',
                [SitePageController::class, 'deleteMedia']
            )->name('site-page.media.delete');

            Route::post(
                'mi-pagina-web',
                [SitePageController::class, 'save']
            )->name('site-page.save');

            Route::post(
                'mi-pagina-web/publicar',
                [SitePageController::class, 'publish']
            )->name('site-page.publish');

            Route::get('empleados/{employee}/foto', [EmployeeController::class, 'photo'])->name('employees.photo');
            Route::resource('empleados', EmployeeController::class)
                ->parameters(['empleados' => 'employee'])
                ->except(['show'])
                ->names('employees');

            Route::get(
                'solicitudes-empleo',
                [ManagerEmploymentApplicationController::class, 'index']
            )->name('applications.index');

            Route::get(
                'solicitudes-empleo/{application}',
                [ManagerEmploymentApplicationController::class, 'show']
            )->name('applications.show');

            Route::patch(
                'solicitudes-empleo/{application}/aprobar',
                [ManagerEmploymentApplicationController::class, 'approve']
            )->name('applications.approve');

            Route::patch(
                'solicitudes-empleo/{application}/rechazar',
                [ManagerEmploymentApplicationController::class, 'reject']
            )->name('applications.reject');

            Route::get(
                'servicios',
                [ServiceController::class, 'managerIndex']
            )->name('services.index');

            Route::get(
                'servicios/nuevo',
                [ServiceController::class, 'create']
            )->name('services.create');

            Route::post(
                'servicios',
                [ServiceController::class, 'store']
            )->name('services.store');

            Route::get(
                'servicios/{service}/editar',
                [ServiceController::class, 'edit']
            )->name('services.edit');

            Route::put(
                'servicios/{service}',
                [ServiceController::class, 'update']
            )->name('services.update');

            Route::patch(
                'servicios/{service}/aprobar',
                [ServiceController::class, 'approve']
            )->name('services.approve');

            Route::patch(
                'servicios/{service}/rechazar',
                [ServiceController::class, 'reject']
            )->name('services.reject');

            Route::get(
                '{module}',
                [ModuleController::class, 'show']
            )
                ->whereIn(
                    'module',
                    ['recepcion', 'servicios', 'reportes']
                )
                ->name('module');

        });

    /*
    |--------------------------------------------------------------------------
    | CAPITÁN DE MESEROS - ROL 7
    |--------------------------------------------------------------------------
    |
    | Panel de operación de piso.
    | Supervisión de servicios y mesas.
    | Asignación y retiro de meseros.
    | Control de atenciones y comidas.
    | Incidencias y reportes.
    |
    | El controlador verifica el rol y la sede.
    |--------------------------------------------------------------------------
    */

    Route::prefix('capitan')
        ->name('capitan.')
        ->group(function () {
            Route::get('/notificaciones',[CapitanController::class,'notifications'])->name('notifications.index');
            Route::post('/notificaciones/leer',[CapitanController::class,'notifications'])->name('notifications.read');

            Route::get('/empleados/{employee}/foto', function (\Illuminate\Http\Request $request, int $employee) {
                $site=\App\Support\CaptainWorkspace::site($request);
                $record=\App\Models\Employee::whereKey($employee)->where('id_sede',$site->id_sede)->firstOrFail();
                abort_unless(\Illuminate\Support\Facades\DB::table('usuario_sede')->where('id_usuario',$record->id_usuario)->where('id_sede',$site->id_sede)->where('id_rol',6)->exists(),404);
                $photo=app(\App\Support\EmployeePresentation::class)->photoLocation($record);
                abort_unless($photo,404);
                return \Illuminate\Support\Facades\Storage::disk($photo['disk'])->response($photo['path'],null,['Cache-Control'=>'private, max-age=300']);
            })->whereNumber('employee')->name('employee-photo');
            // PANEL PRINCIPAL
            Route::get('/', [
                CapitanController::class,
                'index'
            ])->name('index');

            // MESAS Y ZONAS - ADMINISTRACIÓN POR SEDE
            Route::get('/mesas', [MesasZonasController::class, 'index'])
                ->name('mesas.index');

            Route::post('/mesas/programar-total', [MesasZonasController::class, 'programarTotal'])
                ->name('mesas.programar-total');

            Route::post('/mesas/zonas', [MesasZonasController::class, 'guardarZona'])
                ->name('mesas.zonas.store');

            Route::put('/mesas/zonas/{zona}', [MesasZonasController::class, 'actualizarZona'])
                ->whereNumber('zona')
                ->name('mesas.zonas.update');

            Route::post('/mesas/mesas', [MesasZonasController::class, 'guardarMesa'])
                ->name('mesas.mesas.store');

            Route::put('/mesas/mesas/{mesa}', [MesasZonasController::class, 'actualizarMesa'])
                ->whereNumber('mesa')
                ->name('mesas.mesas.update');

            // ASIGNAR MESERO A UNA ZONA
            Route::post('/asignaciones', [
                CapitanController::class,
                'asignar'
            ])->name('asignaciones.store');

            // RETIRAR ASIGNACIÓN
            Route::patch(
                '/asignaciones/{asignacion}/retirar',
                [
                    CapitanController::class,
                    'retirar'
                ]
            )->name('asignaciones.retirar');

            // REGISTRAR INCIDENCIA
            Route::post('/incidencias', [
                CapitanController::class,
                'incidencia'
            ])->name('incidencias.store');

        });

});
