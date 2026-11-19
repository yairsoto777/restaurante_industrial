<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WarehouseWorkspaceTest extends RoleAccessTest
{
    private function warehouseFixture()
    {
        // No migrations and no MySQL connection, including when the local .env points to production data.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        config(['database.connections'=>['sqlite'=>config('database.connections.sqlite')]]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-14 12:00:00','America/Mexico_City'));
        $this->createOrderSchema();
        Schema::table('proveedores', function (Blueprint $t) { $t->string('telefono')->nullable(); $t->string('correo')->nullable(); });
        Schema::table('pedidos', fn (Blueprint $t) => $t->integer('id_usuario')->nullable());
        Schema::table('insumos', function (Blueprint $t) { $t->integer('id_unidad_base')->nullable(); $t->string('categoria')->nullable(); $t->string('imagen_url')->nullable(); });
        Schema::create('unidades', function (Blueprint $t) { $t->increments('id_unidad'); $t->string('nombre'); $t->string('codigo'); $t->boolean('permite_fracciones'); });
        DB::table('unidades')->insert([['id_unidad'=>1,'nombre'=>'Kilogramo','codigo'=>'kg','permite_fracciones'=>true],['id_unidad'=>2,'nombre'=>'Litro','codigo'=>'L','permite_fracciones'=>true]]);
        DB::table('insumos')->where('id_insumo',1)->update(['id_unidad_base'=>1,'categoria'=>'Granos']);
        DB::table('insumos')->insert([['id_insumo'=>2,'nombre'=>'Aceite de prueba','id_unidad_base'=>2,'categoria'=>'Aceites'],['id_insumo'=>3,'nombre'=>'Producto exclusivo sur','id_unidad_base'=>1,'categoria'=>'Privado']]);
        Schema::create('insumo_sedes', function (Blueprint $t) { $t->increments('id_insumo_sede'); $t->integer('id_sede'); $t->integer('id_insumo'); $t->decimal('stock',12,3)->nullable(); $t->decimal('punto_de_reorden',12,3)->nullable(); });
        DB::table('insumo_sedes')->insert([['id_sede'=>1,'id_insumo'=>1,'stock'=>7,'punto_de_reorden'=>10],['id_sede'=>1,'id_insumo'=>2,'stock'=>20,'punto_de_reorden'=>5],['id_sede'=>2,'id_insumo'=>3,'stock'=>99,'punto_de_reorden'=>100]]);
        Schema::create('ubicacion_inventarios', function (Blueprint $t) { $t->increments('id_ubicacion_inv'); $t->integer('id_sede'); $t->string('nombre'); });
        DB::table('ubicacion_inventarios')->insert([['id_ubicacion_inv'=>1,'id_sede'=>1,'nombre'=>'Almacén norte'],['id_ubicacion_inv'=>2,'id_sede'=>2,'nombre'=>'Ubicación exclusiva sur']]);
        Schema::create('lotes', function (Blueprint $t) { $t->increments('id_lote'); $t->integer('id_insumo'); $t->integer('id_proveedor')->nullable(); $t->string('codigo_lote'); $t->date('fecha_caducidad'); });
        DB::table('lotes')->insert([['id_lote'=>1,'id_insumo'=>1,'id_proveedor'=>1,'codigo_lote'=>'NORTE-1','fecha_caducidad'=>'2026-12-01'],['id_lote'=>2,'id_insumo'=>3,'id_proveedor'=>2,'codigo_lote'=>'SUR-PRIVADO','fecha_caducidad'=>'2026-12-01']]);
        Schema::create('recepciones', function (Blueprint $t) { $t->increments('id_recepcion'); foreach(['id_pedido','id_proveedor','id_usuario'] as $c) $t->integer($c); $t->dateTime('fecha_recepcion'); $t->dateTime('fecha_confirmacion')->nullable(); $t->string('estado'); $t->text('observaciones')->nullable(); $t->string('idempotency_key')->nullable()->unique(); });
        Schema::create('recepcion_detalles', function (Blueprint $t) { $t->increments('id_recepcion_detalle'); foreach(['id_recepcion','id_detalle_pedido','id_lote'] as $c) $t->integer($c); $t->date('caducidad'); $t->decimal('cantidad_entregada',12,3); $t->decimal('cantidad_aceptada',12,3); $t->decimal('precio_unitario',12,2)->nullable(); $t->string('resultado_revision'); $t->text('observaciones')->nullable(); });
        Schema::create('incidencias', function (Blueprint $t) { $t->increments('id_incidencia'); foreach(['id_sede','id_usuario','id_recepcion_detalle'] as $c) $t->integer($c); $t->dateTime('fecha'); $t->string('descripcion'); $t->string('estado'); $t->text('respuesta')->nullable(); });
        Schema::create('inventarios_finales', function (Blueprint $t) { $t->increments('id_inventario_final'); foreach(['id_ubicacion','id_lote','id_usuario'] as $c) $t->integer($c); $t->integer('id_recepcion_detalle')->nullable(); $t->dateTime('fecha'); $t->string('tipo'); $t->decimal('cantidad_firmada',12,3); $t->decimal('costo_unitario',12,2)->nullable(); $t->string('referencia')->nullable(); $t->text('observaciones')->nullable(); });
        $user = $this->createUser('Jefe de almacen',13,1);
        $receiver = $this->createUser('Recepcion de insumos',10,1);
        DB::table('recepciones')->insert([['id_recepcion'=>1,'id_pedido'=>1,'id_proveedor'=>1,'id_usuario'=>$receiver->id_usuario,'fecha_recepcion'=>'2026-11-14 17:00:00','estado'=>config('receptions.pending')],['id_recepcion'=>2,'id_pedido'=>2,'id_proveedor'=>2,'id_usuario'=>$receiver->id_usuario,'fecha_recepcion'=>'2026-11-14 17:00:00','estado'=>config('receptions.pending')]]);
        DB::table('recepcion_detalles')->insert(['id_recepcion_detalle'=>1,'id_recepcion'=>1,'id_detalle_pedido'=>1,'id_lote'=>1,'caducidad'=>'2026-12-01','cantidad_entregada'=>5,'cantidad_aceptada'=>4,'resultado_revision'=>'Aceptación parcial','observaciones'=>json_encode(['captura'=>['incidencia'=>'Faltante y un saco dañado','observaciones'=>'Empaque revisado'],'faltante_entrega'=>'7.500'])]);
        DB::table('incidencias')->insert(['id_sede'=>1,'id_usuario'=>$receiver->id_usuario,'id_recepcion_detalle'=>1,'fecha'=>'2026-11-14 17:00:00','descripcion'=>'Saco dañado en recepción','estado'=>'Pendiente']);
        foreach ([[1,1,10,'entrada'],[1,1,-3,'salida'],[2,2,99,'entrada']] as [$location,$lot,$quantity,$type]) DB::table('inventarios_finales')->insert(['id_ubicacion'=>$location,'id_lote'=>$lot,'id_usuario'=>$user->id_usuario,'fecha'=>'2026-11-14 16:00:00','tipo'=>$type,'cantidad_firmada'=>$quantity,'observaciones'=>'Movimiento aislado']);
        $this->actingAs($user)->withSession(['current_site_id'=>1,'active_role_id'=>13]);
        return $user;
    }

    public function test_warehouse_workspace_seven_views_render_real_scoped_data_and_navigation(): void
    {
        $this->warehouseFixture();
        foreach (['/dashboard','/pedidos/proveedores','/pedidos','/recepciones','/inventario','/almacen/movimientos','/almacen/alertas'] as $url) {
            $this->get($url)->assertOk()->assertSee('warehouse-workspace')->assertSee('Alertas de existencias')
                ->assertDontSee('Producto exclusivo sur')->assertDontSee('Ubicación exclusiva sur')->assertDontSee('SUR-PRIVADO')
                ->assertDontSee('href="'.route('dishes.index').'"',false)->assertDontSee('href="'.route('manager.employees.index').'"',false);
        }
        $this->get('/dashboard')->assertViewHas('distribution',fn ($d) => $d->sum() === 2)->assertViewHas('movements',fn ($m) => $m->count() === 2);
        $this->get('/inventario')->assertSee('7 kg')->assertDontSee('7.000 kg')->assertSee('20 L');
        $this->get('/recepciones/revision/1')->assertOk()->assertSee('NORTE-1')->assertSee('7.5')->assertSee('Saco dañado');
        $this->get('/pedidos/1')->assertOk()->assertSee(route('receptions.review.show',1),false);
        $this->get('/pedidos/proveedores?proveedor=2')->assertOk()->assertDontSee('Recepción #2');
        $this->get('/pedidos/nuevo?insumo=1')->assertOk()->assertViewHas('prefill',fn ($i) => $i->id_insumo === 1);
        $this->get('/pedidos/nuevo?insumo=3')->assertNotFound();
    }

    public function test_warehouse_workspace_forbids_other_modules_capture_and_cross_site_records(): void
    {
        $user = $this->warehouseFixture();
        foreach (['/platillos','/cocina','/reportes','/recepciones/pedidos/1/nueva','/operacion/empleados'] as $url) $this->get($url)->assertForbidden();
        $this->post('/inventario/movimientos',[])->assertForbidden();
        $this->post('/recepciones/pedidos/1/capturas',[])->assertForbidden();
        $this->get('/recepciones/revision/2')->assertNotFound(); $this->get('/pedidos/2')->assertNotFound();
        DB::table('usuario_sede')->insert(['id_usuario'=>$user->id_usuario,'id_sede'=>1,'id_rol'=>10]);
        $this->withSession(['active_role_id'=>10])->get('/almacen/alertas')->assertForbidden();
        $this->post('/pedidos',['id_proveedor'=>1])->assertForbidden();
        $this->withSession(['active_role_id'=>13])->get('/dashboard')->assertOk();
        DB::table('usuario_sede')->insert(['id_usuario'=>$user->id_usuario,'id_sede'=>2,'id_rol'=>13]);
        $this->withSession(['current_site_id'=>2])->get('/inventario')->assertOk()->assertSee('PRODUCTO EXCLUSIVO SUR')->assertDontSee('ARROZ PRUEBA');
        $this->get('/recepciones/revision/1')->assertNotFound();
    }

    public function test_warehouse_workspace_order_creation_does_not_change_inventory_and_rejects_foreign_ingredients(): void
    {
        $this->warehouseFixture(); $before=DB::table('inventarios_finales')->count();
        $payload=['id_proveedor'=>1,'fecha_entrega'=>'2026-11-16','observaciones'=>'Antes de las 8','detalles'=>[['id_insumo'=>1,'cantidad'=>'2.5','especificaciones'=>'Saco sellado'],['id_insumo'=>2,'cantidad'=>'20','especificaciones'=>'Envase original']]];
        $this->post('/pedidos',$payload)->assertRedirect();
        $this->assertDatabaseHas('pedidos',['id_sede'=>1,'estado'=>'Programado','observaciones'=>'Antes de las 8']);
        $this->assertDatabaseHas('insumo_sedes',['id_sede'=>1,'id_insumo'=>1,'stock'=>7]);
        $this->assertDatabaseCount('inventarios_finales',$before);
        $payload['detalles'][0]['id_insumo']=3;
        $this->post('/pedidos',$payload)->assertSessionHasErrors('detalles.0.id_insumo');
        $this->assertDatabaseCount('inventarios_finales',$before);
    }

    public function test_warehouse_workspace_confirmation_enters_only_accepted_once_and_keeps_partial_pending(): void
    {
        $this->warehouseFixture();
        $payload=['id_ubicacion'=>1,'observacion_revision'=>'Aceptado después de inspección de almacén'];
        $this->post('/recepciones/revision/1/confirmar',array_replace($payload,['id_ubicacion'=>2]))->assertSessionHasErrors('id_ubicacion');
        $this->assertDatabaseHas('insumo_sedes',['id_sede'=>1,'id_insumo'=>1,'stock'=>7]);
        $this->post('/recepciones/revision/1/confirmar',$payload)->assertRedirect(route('receptions.review.show',1));
        $this->assertDatabaseHas('recepciones',['id_recepcion'=>1,'estado'=>'Confirmada']);
        $this->assertDatabaseHas('insumo_sedes',['id_sede'=>1,'id_insumo'=>1,'stock'=>11]);
        $this->assertDatabaseHas('inventarios_finales',['id_recepcion_detalle'=>1,'cantidad_firmada'=>4]);
        $this->assertDatabaseCount('inventarios_finales',4);
        $this->post('/recepciones/revision/1/confirmar',$payload)->assertSessionHasErrors('recepcion');
        $this->assertDatabaseCount('inventarios_finales',4);
        $this->assertCount(0,\App\Support\WarehouseWorkspace::receivedOrderIds(1));
        $this->get('/almacen/movimientos')->assertOk()->assertSee(route('receptions.review.show',1),false);
    }

    public function test_warehouse_location_catalog_registers_for_active_site_and_only_active_locations_allow_receipt_entries(): void
    {
        $user = $this->warehouseFixture();
        $url = route('warehouse.locations.index');
        $this->get($url)->assertOk()->assertViewHas('available', false);

        $migration = require database_path('migrations/2026_11_17_000001_add_metadata_to_inventory_locations.php');
        $migration->up();
        $data = ['nombre' => 'Cámara fría', 'codigo' => 'frio-1', 'tipo' => 'Refrigeración', 'estado' => 'Inactiva', 'id_sede' => 2];
        $this->post($url, $data)->assertRedirect($url);
        $this->assertDatabaseHas('ubicacion_inventarios', ['id_sede' => 1, 'codigo' => 'FRIO-1', 'estado' => 'Inactiva']);
        $this->post($url, $data)->assertSessionHasErrors('codigo');
        $this->get($url)->assertOk()->assertSee('Cámara fría')->assertDontSee('Ubicación exclusiva sur');
        $this->get('/recepciones/revision/1')->assertOk()->assertDontSee('Cámara fría');

        $inactive = DB::table('ubicacion_inventarios')->where('codigo', 'FRIO-1')->value('id_ubicacion_inv');
        $this->post('/recepciones/revision/1/confirmar', [
            'id_ubicacion' => $inactive,
            'observacion_revision' => 'Verificación de ubicación inactiva',
        ])->assertSessionHasErrors('id_ubicacion');
        $this->assertDatabaseCount('inventarios_finales', 3);

        DB::table('usuario_sede')->insert(['id_usuario' => $user->id_usuario, 'id_sede' => 1, 'id_rol' => 10]);
        $this->withSession(['active_role_id' => 10])->get($url)->assertForbidden();
        $this->post($url, $data)->assertForbidden();
    }

    public function test_warehouse_workspace_expired_lots_and_duplicate_existing_movements_block_confirmation(): void
    {
        $this->warehouseFixture(); $payload=['id_ubicacion'=>1,'observacion_revision'=>'Verificación del lote aceptado'];
        DB::table('lotes')->where('id_lote',1)->update(['fecha_caducidad'=>'2026-11-13']);
        $this->post('/recepciones/revision/1/confirmar',$payload)->assertSessionHasErrors('recepcion');
        $this->assertDatabaseHas('insumo_sedes',['id_sede'=>1,'id_insumo'=>1,'stock'=>7]);
        DB::table('lotes')->where('id_lote',1)->update(['fecha_caducidad'=>'2026-12-01']);
        DB::table('inventarios_finales')->where('id_inventario_final',1)->update(['id_recepcion_detalle'=>1]);
        $this->post('/recepciones/revision/1/confirmar',$payload)->assertSessionHasErrors('recepcion');
        $this->assertDatabaseCount('inventarios_finales',3);
    }

    private function supplierLogoUpload(): \Illuminate\Http\UploadedFile
    {
        return \Illuminate\Http\UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aT1kAAAAASUVORK5CYII='));
    }

    public function test_warehouse_supplier_cover_and_logo_are_optional_independent_and_saved_atomically(): void
    {
        $user = $this->warehouseFixture();
        \Illuminate\Support\Facades\Storage::fake('public');
        Schema::table('proveedores', fn (Blueprint $t) => $t->string('logo_url')->nullable());
        $this->get(route('orders.suppliers.index'))->assertOk()->assertViewHas('supplierCoverAvailable',false)
            ->assertSee('Identidad visual del proveedor')->assertSee('img/ui/warehouse-logistics.jpg',false);
        $this->post(route('orders.suppliers.store'),['nombre'=>'Portada no habilitada','portada'=>$this->supplierLogoUpload()])->assertSessionHasErrors('portada');
        Schema::table('proveedores', fn (Blueprint $t) => $t->string('cover_url')->nullable());
        $this->post(route('orders.suppliers.store'),['nombre'=>'Sin imágenes'])->assertRedirect();
        $this->post(route('orders.suppliers.store'),['nombre'=>'Identidad completa','imagen'=>$this->supplierLogoUpload(),'portada'=>$this->supplierLogoUpload()])->assertRedirect();
        $supplier = DB::table('proveedores')->where('nombre','Identidad completa')->first();
        $this->assertNotSame($supplier->logo_url, $supplier->cover_url);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists([$supplier->logo_url,$supplier->cover_url]);
        $this->get(route('orders.suppliers.index',['proveedor'=>$supplier->id_proveedor]))->assertOk()
            ->assertViewHas('supplierCoverAvailable',true)->assertSee('name="portada"',false)
            ->assertSee(asset('storage/'.$supplier->cover_url),false)->assertSee(asset('storage/'.$supplier->logo_url),false);
        $this->put(route('orders.suppliers.update',$supplier->id_proveedor),['nombre'=>'Identidad completa','portada'=>$this->supplierLogoUpload()])->assertRedirect();
        $updated = DB::table('proveedores')->where('id_proveedor',$supplier->id_proveedor)->first();
        $this->assertSame($supplier->logo_url,$updated->logo_url);
        $this->assertNotSame($supplier->cover_url,$updated->cover_url);
        $this->put(route('orders.suppliers.update',$supplier->id_proveedor),['nombre'=>'Identidad completa','imagen'=>$this->supplierLogoUpload()])->assertRedirect();
        $newLogo = DB::table('proveedores')->where('id_proveedor',$supplier->id_proveedor)->value('logo_url');
        $this->assertNotSame($supplier->logo_url,$newLogo);
        $this->put(route('orders.suppliers.update',$supplier->id_proveedor),['nombre'=>'Identidad completa'])->assertRedirect();
        $this->assertDatabaseHas('proveedores',['id_proveedor'=>$supplier->id_proveedor,'logo_url'=>$newLogo,'cover_url'=>$updated->cover_url]);
        $this->post('/logout')->assertRedirect();
        $this->actingAs($user)->withSession(['current_site_id'=>1,'active_role_id'=>13]);
        $this->get(route('orders.suppliers.index',['proveedor'=>$supplier->id_proveedor]))->assertOk()
            ->assertSee(asset('storage/'.$updated->cover_url),false)
            ->assertSee('wh-supplier-details',false)->assertSee('<span>Ver detalles</span>',false)
            ->assertDontSee('Vista previa de portada. Su guardado está pendiente de habilitación.');
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists([$newLogo,$updated->cover_url]);
        $files = \Illuminate\Support\Facades\Storage::disk('public')->allFiles();
        $this->put(route('orders.suppliers.update',$supplier->id_proveedor),['nombre'=>'Identidad completa','imagen'=>$this->supplierLogoUpload(),'portada'=>$this->supplierLogoUpload()->size(4097)])->assertSessionHasErrors('portada');
        $this->assertSame($files,\Illuminate\Support\Facades\Storage::disk('public')->allFiles());
        $this->post(route('orders.suppliers.store'),['nombre'=>'Identidad completa','imagen'=>$this->supplierLogoUpload(),'portada'=>$this->supplierLogoUpload()])->assertSessionHasErrors('nombre');
        $this->assertSame($files,\Illuminate\Support\Facades\Storage::disk('public')->allFiles());
    }

    public function test_warehouse_supplier_images_use_installation_base_path_in_all_three_presentations(): void
    {
        $this->warehouseFixture();
        Schema::table('proveedores', fn (Blueprint $t) => $t->string('logo_url')->nullable());
        DB::table('proveedores')->where('id_proveedor',1)->update(['logo_url'=>'proveedores/garis.jpg']);
        // Reproduce this installation: APP_URL/public disk omit the subdirectory.
        config(['filesystems.disks.public.url'=>'http://localhost/storage']);
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost/restaurante_industrial/public');
        try {
            $response = $this->get('http://localhost/pedidos/proveedores?proveedor=1')->assertOk();
            $html = $response->getContent();
            $expected = 'http://localhost/restaurante_industrial/public/storage/proveedores/garis.jpg';
            $this->assertSame(3, substr_count($html, ' src="'.$expected.'"'));
            $this->assertStringNotContainsString('src="http://localhost/storage/proveedores/garis.jpg"', $html);
            $this->assertStringContainsString('enctype="multipart/form-data"', $html);
            $this->assertStringContainsString('name="imagen"', $html);
        } finally {
            \Illuminate\Support\Facades\URL::forceRootUrl(null);
        }
    }

    public function test_warehouse_supplier_images_without_column_keep_contact_operations_available(): void
    {
        $this->warehouseFixture();
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->get(route('orders.suppliers.index',['proveedor'=>1]))->assertOk()
            ->assertViewHas('supplierImageAvailable', false)->assertSee('data-supplier-file', false)
            ->assertSee('La imagen aún no se puede guardar')->assertSee('wh-supplier-detail-identity', false)
            ->assertDontSee('name="imagen"', false);
        $this->post(route('orders.suppliers.store'), ['nombre'=>'Aliado nuevo','correo'=>'nuevo@example.com'])->assertRedirect();
        $this->assertDatabaseHas('proveedores',['nombre'=>'Aliado nuevo','correo'=>'nuevo@example.com']);
        $this->post(route('orders.suppliers.store'), ['nombre'=>'Sin columna','imagen'=>$this->supplierLogoUpload()])->assertSessionHasErrors('imagen');
        $this->assertDatabaseMissing('proveedores',['nombre'=>'Sin columna']);
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('public')->allFiles());
    }

    public function test_warehouse_supplier_images_save_reuse_preserve_and_clean_up_failed_uploads(): void
    {
        $this->warehouseFixture();
        \Illuminate\Support\Facades\Storage::fake('public');
        Schema::table('proveedores', fn (Blueprint $t) => $t->string('logo_url')->nullable());
        $this->post(route('orders.suppliers.store'), ['nombre'=>'Aliado con logo','imagen'=>$this->supplierLogoUpload()])->assertRedirect();
        $supplier = DB::table('proveedores')->where('nombre','Aliado con logo')->first();
        $this->assertNotEmpty($supplier->logo_url);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($supplier->logo_url);
        $this->get(route('orders.suppliers.index',['proveedor'=>$supplier->id_proveedor]))->assertOk()
            ->assertViewHas('supplierImageAvailable', true)
            ->assertSee(\Illuminate\Support\Facades\Storage::disk('public')->url($supplier->logo_url), false)
            ->assertSee('Conservar imagen actual');
        $this->put(route('orders.suppliers.update',$supplier->id_proveedor), ['nombre'=>'Aliado renombrado'])->assertRedirect();
        $this->assertDatabaseHas('proveedores',['id_proveedor'=>$supplier->id_proveedor,'logo_url'=>$supplier->logo_url]);
        $this->put(route('orders.suppliers.update',$supplier->id_proveedor), ['nombre'=>'Aliado renombrado','imagen'=>$this->supplierLogoUpload()])->assertRedirect();
        $replacement = DB::table('proveedores')->where('id_proveedor',$supplier->id_proveedor)->value('logo_url');
        $this->assertNotSame($supplier->logo_url, $replacement);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($replacement);
        $before = \Illuminate\Support\Facades\Storage::disk('public')->allFiles();
        $this->post(route('orders.suppliers.store'), ['nombre'=>'Aliado renombrado','imagen'=>$this->supplierLogoUpload()])->assertSessionHasErrors('nombre');
        $this->assertSame($before, \Illuminate\Support\Facades\Storage::disk('public')->allFiles());
    }

    public function test_warehouse_supplier_images_survive_logout_and_shared_catalog_edits_require_active_warehouse_role(): void
    {
        $user = $this->warehouseFixture();
        \Illuminate\Support\Facades\Storage::fake('public');
        Schema::table('proveedores', fn (Blueprint $t) => $t->string('logo_url')->nullable());
        // Supplier 2 has orders only in another site, but its catalog identity is global.
        $this->put(route('orders.suppliers.update',2), ['nombre'=>'Proveedor compartido','imagen'=>$this->supplierLogoUpload()])->assertRedirect();
        $path = DB::table('proveedores')->where('id_proveedor',2)->value('logo_url');
        $this->assertNotEmpty($path);
        $this->post('/logout')->assertRedirect();
        $this->get(route('orders.suppliers.index'))->assertRedirect('/login');
        $this->actingAs($user)->withSession(['current_site_id'=>1,'active_role_id'=>13]);
        $this->get(route('orders.suppliers.index',['proveedor'=>2]))->assertOk()
            ->assertSee(\Illuminate\Support\Facades\Storage::disk('public')->url($path),false)
            ->assertDontSee('Vista previa disponible. La imagen aún no se puede guardar');
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($path);
        DB::table('usuario_sede')->insert(['id_usuario'=>$user->id_usuario,'id_sede'=>1,'id_rol'=>10]);
        $before = \Illuminate\Support\Facades\Storage::disk('public')->allFiles();
        $this->withSession(['active_role_id'=>10])->put(route('orders.suppliers.update',2), ['nombre'=>'Cambio no autorizado','imagen'=>$this->supplierLogoUpload()])->assertForbidden();
        $this->assertDatabaseHas('proveedores',['id_proveedor'=>2,'logo_url'=>$path,'nombre'=>'Proveedor compartido']);
        $this->assertSame($before, \Illuminate\Support\Facades\Storage::disk('public')->allFiles());
    }

    public function test_warehouse_supplier_images_reject_files_over_four_megabytes_without_changing_current_image(): void
    {
        $this->warehouseFixture();
        \Illuminate\Support\Facades\Storage::fake('public');
        Schema::table('proveedores', fn (Blueprint $t) => $t->string('logo_url')->nullable());
        DB::table('proveedores')->where('id_proveedor',1)->update(['logo_url'=>'proveedores/original.png']);
        $file = $this->supplierLogoUpload()->size(4097);
        $this->put(route('orders.suppliers.update',1), ['nombre'=>'Proveedor Norte','imagen'=>$file])->assertSessionHasErrors('imagen');
        $this->assertDatabaseHas('proveedores',['id_proveedor'=>1,'logo_url'=>'proveedores/original.png']);
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('public')->allFiles());
    }

    public function test_warehouse_supplier_images_reuse_legacy_column_and_reject_invalid_files(): void
    {
        $this->warehouseFixture();
        \Illuminate\Support\Facades\Storage::fake('public');
        Schema::table('proveedores', fn (Blueprint $t) => $t->string('imagen_url')->nullable());
        DB::table('proveedores')->where('id_proveedor',1)->update(['imagen_url'=>'https://example.com/logo.webp']);
        $this->get(route('orders.suppliers.index'))->assertOk()->assertSee('https://example.com/logo.webp',false);
        $this->post(route('orders.suppliers.store'), ['nombre'=>'Archivo inválido','imagen'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('logo.png','texto sin imagen')])->assertSessionHasErrors('imagen');
        $this->assertDatabaseMissing('proveedores',['nombre'=>'Archivo inválido']);
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('public')->allFiles());
        $this->assertSame('ÁN', \App\Support\WarehouseSupplierImage::initials('Álamo Norte'));
    }

    public function test_warehouse_workspace_filters_notifications_and_returns_receipts_without_stock_mutation(): void
    {
        $this->warehouseFixture();
        $this->get('/almacen/movimientos?tipo=salida')->assertOk()->assertViewHas('rows',fn ($rows) => $rows->total() === 1);
        $this->get('/inventario?estado=bajo')->assertOk()->assertViewHas('rows',fn ($rows) => $rows->total() === 1);
        $response=$this->getJson('/almacen/notificaciones')->assertOk()->assertJsonPath('unread',5);
        $this->assertStringNotContainsString('Proveedor Sur',$response->getContent());
        $this->postJson('/almacen/notificaciones/leer',['id'=>'all'])->assertOk()->assertJsonPath('unread',0);
        $this->post('/recepciones/revision/1/devolver',['motivo'=>'Se debe corregir el lote en la captura física'])->assertRedirect();
        $this->assertDatabaseHas('recepciones',['id_recepcion'=>1,'estado'=>'Rechazada por almacén']);
        $this->assertDatabaseHas('insumo_sedes',['id_sede'=>1,'id_insumo'=>1,'stock'=>7]);
        $this->assertDatabaseCount('inventarios_finales',3);
    }
}
