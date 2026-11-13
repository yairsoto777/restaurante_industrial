<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Isolated UI/route checks. No RefreshDatabase, migrations, seeders or MySQL. */
class OperationsPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutVite();
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('CONCAT', fn (...$parts) => implode('', $parts));
        $pdo->sqliteCreateFunction('DATE_FORMAT', fn ($value, $format) => substr($value ?? '', 0, 2).':00');
        $definitions = [
            'usuarios' => 'id_usuario INTEGER PRIMARY KEY, nombre TEXT, apellido_p TEXT, apellido_m TEXT, correo TEXT, password TEXT, remember_token TEXT',
            'roles' => 'id_rol INTEGER PRIMARY KEY, nombre TEXT',
            'sedes' => 'id_sede INTEGER PRIMARY KEY, nombre TEXT, direccion TEXT, capacidad INTEGER, logo_url TEXT, mesas_programadas INTEGER',
            'usuario_sede' => 'id_usuario_sede INTEGER PRIMARY KEY, id_usuario INTEGER, id_sede INTEGER, id_rol INTEGER',
            'zonas' => 'id_zona INTEGER PRIMARY KEY, id_sede INTEGER, nombre TEXT',
            'zona_meseros' => 'id_zona_mesero INTEGER PRIMARY KEY, id_zona INTEGER, id_usuario_mesero INTEGER, activo INTEGER, fecha_inicio TEXT',
            'mesas' => 'id_mesa INTEGER PRIMARY KEY, id_zona INTEGER, numero INTEGER, capacidad INTEGER, estado_fisico TEXT, observaciones TEXT',
            'servicios' => 'id_servicio INTEGER PRIMARY KEY, id_sede INTEGER, nombre TEXT, fecha TEXT, inicio_hora TEXT, fin_hora TEXT, capacidad INTEGER, estado TEXT, estado_aprobacion TEXT',
            'atenciones' => 'id_atencion INTEGER PRIMARY KEY, id_servicio INTEGER, id_mesa INTEGER, id_usuario_mesero INTEGER, estado TEXT, comensales INTEGER, inicio_hora TEXT, fin_hora TEXT',
            'comandas' => 'id_comanda INTEGER PRIMARY KEY, id_atencion INTEGER, estado TEXT, fecha_apertura TEXT, fecha_cierre TEXT',
            'comanda_detalles' => 'id_comanda_detalle INTEGER PRIMARY KEY, id_comanda INTEGER, id_menu INTEGER, cantidad INTEGER, precio_unitario NUMERIC, estado TEXT',
            'menus' => 'id_menu INTEGER PRIMARY KEY, id_platillo INTEGER',
            'platillos' => 'id_platillo INTEGER PRIMARY KEY, nombre TEXT',
            'grupos' => 'id_grupo INTEGER PRIMARY KEY, id_servicio INTEGER, nombre TEXT, inicio_hora TEXT, fin_hora TEXT, comensales_pre INTEGER',
            'entregas' => 'id_entrega INTEGER PRIMARY KEY, id_atencion INTEGER, cantidad INTEGER, fecha_entrega TEXT',
            'incidencias' => 'id_incidencia INTEGER PRIMARY KEY, id_sede INTEGER, id_usuario INTEGER, id_recepcion_detalle INTEGER, fecha TEXT, descripcion TEXT, estado TEXT, respuesta TEXT, fecha_cierre TEXT',
            'pedidos' => 'id_pedido INTEGER PRIMARY KEY, id_sede INTEGER, estado TEXT, fecha_pedido TEXT',
            'recepciones' => 'id_recepcion INTEGER PRIMARY KEY, id_pedido INTEGER, fecha_recepcion TEXT, folio_documento TEXT, estado TEXT',
            'recepcion_detalles' => 'id_recepcion_detalle INTEGER PRIMARY KEY, id_recepcion INTEGER, id_detalle_pedido INTEGER, cantidad_aceptada NUMERIC, caducidad TEXT',
            'detalle_pedidos' => 'id_detalle_pedido INTEGER PRIMARY KEY, id_insumo INTEGER',
            'insumos' => 'id_insumo INTEGER PRIMARY KEY, nombre TEXT',
            'servicios_ofrecidos' => 'id_servicio_ofrecido INTEGER PRIMARY KEY, id_atencion INTEGER UNIQUE, descripcion TEXT, comensales INTEGER, precio_unitario NUMERIC, total NUMERIC, estado TEXT, finalizado_en TEXT, pagado_en TEXT, created_at TEXT, updated_at TEXT',
            'cuentas' => 'id_cuenta INTEGER PRIMARY KEY, id_atencion INTEGER UNIQUE, subtotal NUMERIC, total NUMERIC, estado TEXT, fecha_apertura TEXT, fecha_cierre TEXT',
            'caja_turnos' => 'id_turno_caja INTEGER PRIMARY KEY, id_sede INTEGER, id_usuario_apertura INTEGER, fondo_inicial NUMERIC, efectivo_esperado NUMERIC, efectivo_contado NUMERIC, diferencia NUMERIC, estado TEXT, abierta_at TEXT, cerrada_at TEXT, created_at TEXT, updated_at TEXT',
            'caja_pagos' => 'id_pago INTEGER PRIMARY KEY, id_turno_caja INTEGER, id_atencion INTEGER UNIQUE, id_usuario INTEGER, id_sede INTEGER, metodo TEXT, importe NUMERIC, recibido NUMERIC, cambio NUMERIC, estado TEXT, created_at TEXT, updated_at TEXT',
        ];
        foreach ($definitions as $table => $columns) {
            $pdo->exec('CREATE TABLE '.$table.' ('.$columns.')');
        }
        DB::table('sedes')->insert([
            ['id_sede'=>1,'nombre'=>'Sede de prueba UI','direccion'=>'Domicilio de prueba','capacidad'=>100],
            ['id_sede'=>2,'nombre'=>'Sede no autorizada','direccion'=>'Otra sede','capacidad'=>30],
        ]);
        foreach ([6=>'Mesero',7=>'Capitan de meseros',8=>'Cajero',12=>'Jefe de cocina'] as $id=>$name) {
            DB::table('roles')->insert(['id_rol'=>$id,'nombre'=>$name]);
            DB::table('usuarios')->insert(['id_usuario'=>$id,'nombre'=>$name,'apellido_p'=>'Prueba','correo'=>'ui'.$id.'@example.test','password'=>'unused']);
            DB::table('usuario_sede')->insert(['id_usuario'=>$id,'id_sede'=>1,'id_rol'=>$id]);
        }
        DB::table('zonas')->insert(['id_zona'=>1,'id_sede'=>1,'nombre'=>'Zona de prueba']);
        DB::table('zona_meseros')->insert(['id_zona_mesero'=>1,'id_zona'=>1,'id_usuario_mesero'=>6,'activo'=>1,'fecha_inicio'=>now()]);
        DB::table('mesas')->insert([
            ['id_mesa'=>1,'id_zona'=>1,'numero'=>1,'capacidad'=>6,'estado_fisico'=>'Disponible','observaciones'=>'Observación conservada'],
            ['id_mesa'=>2,'id_zona'=>1,'numero'=>2,'capacidad'=>4,'estado_fisico'=>'Mantenimiento','observaciones'=>null],
            ['id_mesa'=>3,'id_zona'=>1,'numero'=>3,'capacidad'=>4,'estado_fisico'=>'Disponible','observaciones'=>null],
        ]);
        DB::table('servicios')->insert(['id_servicio'=>1,'id_sede'=>1,'nombre'=>'Servicio de prueba UI','fecha'=>now()->toDateString(),'inicio_hora'=>'08:00:00','fin_hora'=>'23:00:00','capacidad'=>100,'estado'=>'En curso','estado_aprobacion'=>'Aprobado']);
        DB::table('atenciones')->insert(['id_atencion'=>1,'id_servicio'=>1,'id_mesa'=>1,'id_usuario_mesero'=>6,'estado'=>'En atención','comensales'=>2,'inicio_hora'=>'09:00:00']);
        DB::table('comandas')->insert(['id_comanda'=>1,'id_atencion'=>1,'estado'=>'En preparación','fecha_apertura'=>now()]);
        DB::table('grupos')->insert(['id_grupo'=>1,'id_servicio'=>1,'nombre'=>'Grupo de prueba UI','comensales_pre'=>20,'inicio_hora'=>'09:00:00','fin_hora'=>'10:00:00']);
        DB::table('incidencias')->insert(['id_incidencia'=>1,'id_sede'=>1,'id_usuario'=>6,'fecha'=>now(),'descripcion'=>'Incidencia de prueba UI','estado'=>'Pendiente','respuesta'=>'Respuesta registrada']);
    }

    private function asRole(int $id): void
    {
        $this->actingAs(User::findOrFail($id))->withSession(['current_site_id'=>1]);
    }

    private function preview(string $name, string $html): void
    {
        // Optional local browser artifacts; fixture data never enters the application database.
        if (!getenv('OPS_EXPORT_PREVIEWS')) return;
        $base = 'file:///'.str_replace('\\','/',base_path()).'/';
        $html = preg_replace('~https?://localhost/(css/(?:operations|captain-visual|waiter-visual)\.css|js/(?:operations|captain-visual)\.js|img/ui/(?:captain-(?:dining|kitchen)\.webp|service-(?:breakfast|lunch|dinner)\.jpg))(\?[^"\s]*)?~', $base.'public/$1', $html);
        $manifestPath = public_path('build/manifest.json');
        $styles = '';
        if (is_file($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath),true);
            foreach (['resources/css/app.css','resources/css/capitan.css','resources/css/capitan-mesas.css','resources/css/dashboard_visual.css'] as $key) {
                if (isset($manifest[$key])) $styles .= '<link rel="stylesheet" href="'.$base.'public/build/'.$manifest[$key]['file'].'">';
            }
        }
        $styles .= '<link rel="stylesheet" href="'.$base.'public/css/operations.css">';
        if (str_contains($html, 'ops-captain')) $styles .= '<link rel="stylesheet" href="'.$base.'public/css/captain-visual.css">';
        if (str_contains($html, 'ops-waiter')) $styles .= '<link rel="stylesheet" href="'.$base.'public/css/waiter-visual.css">';
        if (isset($manifest['resources/js/app.js'])) $styles .= '<script type="module" src="'.$base.'public/build/'.$manifest['resources/js/app.js']['file'].'"></script>';
        $html = str_replace('</head>',$styles.'</head>',$html);
        $dir=storage_path('app/private/ui-preview');
        if (!is_dir($dir)) mkdir($dir,0700,true);
        file_put_contents($dir.'/'.$name.'.html',$html);
    }

    public function test_captain_menu_views_render_with_existing_forms_and_site_scope(): void
    {
        $this->asRole(7);
        foreach (['dashboard','sites.select'] as $route) {
            $response=$this->get(route($route));
            $response->assertOk()->assertSee('ops-ui')->assertDontSee('Sede no autorizada');
            $this->preview('captain-'.str_replace('.','-',$route),$response->getContent());
        }
        foreach (['panel','servicios','mesas','asignaciones','atenciones','comandas','grupos','incidencias','reportes'] as $module) {
            $response=$this->get(route('capitan.index',['modulo'=>$module]));
            $response->assertOk()->assertSee('ops-ui')->assertDontSee('Sede no autorizada');
            if ($module==='asignaciones') $response->assertSee('name="id_usuario_mesero"',false)->assertSee('name="_method" value="PATCH"',false);
            if ($module==='incidencias') $response->assertSee('name="descripcion"',false)->assertSee('Respuesta registrada');
            $this->preview('captain-'.$module,$response->getContent());
        }
        $response=$this->get(route('capitan.mesas.index'));
        $response->assertOk()->assertSee('Observación conservada')->assertSee('cz-zone-form')->assertSee('cz-table-form')->assertSee('mesas_programadas');
        $this->preview('captain-tables',$response->getContent());
    }

    public function test_waiter_menu_views_render_and_preserve_offered_service_forms(): void
    {
        $this->asRole(6);
        foreach (['dashboard','sites.select','services.index','waiter.index','tables.index','incidents.index'] as $route) {
            $response=$this->get(route($route));
            $response->assertOk()->assertSee('ops-ui')->assertDontSee('Sede no autorizada');
            $this->preview('waiter-'.str_replace('.','-',$route),$response->getContent());
        }
        $this->get(route('waiter.index'))->assertSee('name="precio_unitario"',false)->assertSee('name="comensales"',false)->assertSee(route('waiter.attention.store'),false);
        DB::table('servicios_ofrecidos')->insert(['id_servicio_ofrecido'=>1,'id_atencion'=>1,'descripcion'=>'Servicio registrado UI','comensales'=>2,'precio_unitario'=>85.50,'total'=>171,'estado'=>'Registrado']);
        $this->get(route('waiter.index'))->assertOk()->assertSee('Finalizar y enviar a Caja')->assertSee('Actualizar');
        $this->get(route('cash.index'))->assertForbidden();
        $this->get(route('capitan.index'))->assertForbidden();
    }

    public function test_cashier_views_preserve_payment_fields_totals_and_ticket(): void
    {
        $this->asRole(8);
        DB::table('atenciones')->where('id_atencion',1)->update(['estado'=>'Pendiente de pago']);
        DB::table('servicios_ofrecidos')->insert(['id_servicio_ofrecido'=>1,'id_atencion'=>1,'descripcion'=>'Servicio registrado UI','comensales'=>2,'precio_unitario'=>85.50,'total'=>171,'estado'=>'Pendiente de cobro']);
        DB::table('cuentas')->insert(['id_cuenta'=>1,'id_atencion'=>1,'subtotal'=>171,'total'=>171,'estado'=>'pendiente','fecha_apertura'=>now()]);
        $this->get(route('cash.index'))->assertOk()->assertSee('Abrir caja')->assertDontSee('data-payment-form');
        DB::table('caja_turnos')->insert(['id_turno_caja'=>1,'id_sede'=>1,'id_usuario_apertura'=>8,'fondo_inicial'=>100,'estado'=>'abierto','abierta_at'=>now()]);
        foreach (['dashboard','sites.select','cash.index','cash.history','incidents.index'] as $route) {
            $response=$this->get(route($route));
            $response->assertOk()->assertDontSee('Sede no autorizada');
            $this->preview('cashier-'.str_replace('.','-',$route),$response->getContent());
        }
        $this->get(route('cash.index'))->assertSee('171.00')->assertSee('name="metodo"',false)->assertSee('name="recibido"',false)->assertSee('Cerrar turno')->assertSee('Confirmar cobro');
        DB::table('caja_pagos')->insert(['id_pago'=>1,'id_turno_caja'=>1,'id_atencion'=>1,'id_usuario'=>8,'id_sede'=>1,'metodo'=>'efectivo','importe'=>171,'recibido'=>200,'cambio'=>29,'estado'=>'pagado','created_at'=>now()]);
        $ticket=$this->get(route('cash.ticket',1));
        $ticket->assertOk()->assertSee('171.00')->assertSee('29.00')->assertSee('window.print()',false);
        $this->preview('cashier-ticket',$ticket->getContent());
        $this->get(route('waiter.index'))->assertForbidden();
    }

    public function test_shared_views_do_not_apply_operational_theme_to_other_roles(): void
    {
        $this->asRole(12);
        $this->get(route('sites.select'))->assertOk()->assertDontSee('operations.css')->assertDontSee('ops-site-active');
        $this->get(route('incidents.index'))->assertOk()->assertDontSee('operations.css')->assertDontSee('Seguimiento reciente');
        $this->get(route('tables.index'))->assertOk()->assertDontSee('operations.css')->assertDontSee('ops-record');
    }

    public function test_captain_visual_assets_are_local_and_exclusive_to_the_role(): void
    {
        foreach (['captain-dining.webp','captain-kitchen.webp'] as $image) {
            $info=getimagesize(public_path('img/ui/'.$image));
            $this->assertSame('image/webp',$info['mime']);
            $this->assertGreaterThanOrEqual(1280,$info[0]);
        }
        $this->asRole(7);
        $this->get(route('capitan.index'))->assertOk()->assertSee('captain-visual.css')->assertSee('img/ui/service-breakfast.jpg');
        foreach ([6,8,12] as $role) {
            $this->asRole($role);
            $this->get(route('sites.select'))->assertOk()->assertDontSee('captain-visual.css')->assertDontSee('captain-visual.js');
        }
    }

    public function test_empty_states_and_filters_render_without_invented_activity(): void
    {
        $this->asRole(7);
        DB::table('servicios')->update(['estado'=>'Finalizado']);
        $this->get(route('capitan.index',['modulo'=>'servicios']))->assertOk()->assertSee('No hay un servicio en curso para esta fecha.');
        $this->asRole(6);
        $this->get(route('services.index',['q'=>'nonexistent']))->assertOk()->assertSee('Sin servicios en esta consulta');
        $this->get(route('incidents.index',['estado'=>'Pendiente']))->assertOk()->assertSee('Incidencia de prueba UI');
        DB::table('zona_meseros')->delete();
        $this->get(route('waiter.index'))->assertOk()->assertSee('No tienes mesas asignadas');
        $this->asRole(8);
        $this->get(route('cash.index'))->assertOk()->assertSee('No hay cuentas pendientes');
    }

    public function test_programming_uses_distinct_meal_photographs_and_preserves_service_details(): void
    {
        $hashes=[];
        foreach (['breakfast','lunch','dinner'] as $meal) {
            $file=public_path('img/ui/service-'.$meal.'.jpg');
            $this->assertSame('image/jpeg',getimagesize($file)['mime']);
            $hashes[]=hash_file('sha256',$file);
        }
        $this->assertCount(3,array_unique($hashes));
        DB::table('servicios')->where('id_servicio',1)->update(['nombre'=>'Desayuno']);
        foreach ([2=>'Comida',3=>'Cena'] as $id=>$name) DB::table('servicios')->insert(['id_servicio'=>$id,'id_sede'=>1,'nombre'=>$name,'fecha'=>now()->toDateString(),'inicio_hora'=>'12:00:00','fin_hora'=>'20:00:00','capacidad'=>40,'estado'=>'Programado','estado_aprobacion'=>'Aprobado']);
        $this->asRole(7);
        $response=$this->get(route('capitan.index'));
        $response->assertOk()->assertSee('service-breakfast.jpg')->assertSee('service-lunch.jpg')->assertSee('service-dinner.jpg')->assertSee('Desayuno')->assertSee('Comida')->assertSee('Cena')->assertSee('12:00')->assertSee('20:00')->assertSee('40 comensales');
        $this->preview('captain-programming',$response->getContent());
    }

    public function test_original_attention_payment_and_closing_flow_in_memory(): void
    {
        $this->asRole(6);
        $this->post(route('waiter.attention.store'),['id_mesa'=>3,'id_servicio'=>1,'comensales'=>2])->assertRedirect()->assertSessionHasNoErrors();
        $attention=(int) DB::table('atenciones')->where('id_mesa',3)->value('id_atencion');
        $this->post(route('waiter.offered-service.store',$attention),['descripcion'=>'Servicio de prueba','comensales'=>2,'precio_unitario'=>'85.50'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('waiter.index'))->assertOk()->assertSee('Actualizar')->assertSee('Finalizar y enviar a Caja');
        $this->patch(route('waiter.offered-service.finalize',$attention))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cuentas',['id_atencion'=>$attention,'total'=>171,'estado'=>'pendiente']);
        $this->asRole(8);
        $this->post(route('cash.open'),['fondo_inicial'=>'100.00'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('cash.index'))->assertOk()->assertSee('Cobrar servicio')->assertSee('171.00');
        $this->post(route('cash.charge',$attention),['metodo'=>'efectivo','recibido'=>'200.00'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('caja_pagos',['id_atencion'=>$attention,'importe'=>171,'recibido'=>200,'cambio'=>29]);
        $this->assertDatabaseHas('servicios_ofrecidos',['id_atencion'=>$attention,'estado'=>'Pagado']);
        $this->get(route('cash.index'))->assertOk()->assertSee('$271.00')->assertSee('Imprimir ticket');
        $this->post(route('cash.close'),['efectivo_contado'=>'271.00'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('caja_turnos',['efectivo_esperado'=>271,'efectivo_contado'=>271,'diferencia'=>0,'estado'=>'cerrado']);
    }

    public function test_waiter_visual_theme_is_isolated_and_reuses_local_service_photographs(): void
    {
        $this->asRole(6);
        $this->get(route('waiter.index'))->assertOk()->assertSee('waiter-visual.css')->assertSee('ops-waiter')->assertSee('captain-table-symbol')->assertSee('waiter-physical-status')->assertSee('name="observaciones"',false)->assertSee('name="id_servicio"',false);
        $this->get(route('incidents.index'))->assertOk()->assertSee('Estados de esta consulta')->assertSee('En la página actual')->assertSee('Respuesta registrada');
        DB::table('servicios')->where('id_servicio',1)->update(['nombre'=>'Desayuno']);
        foreach ([2=>'Comida',3=>'Cena'] as $id=>$name) DB::table('servicios')->insert(['id_servicio'=>$id,'id_sede'=>1,'nombre'=>$name,'fecha'=>now()->toDateString(),'inicio_hora'=>'12:00:00','fin_hora'=>'20:00:00','capacidad'=>40,'estado'=>'Programado','estado_aprobacion'=>'Aprobado']);
        $response=$this->get(route('services.index'));
        $response->assertOk()->assertSee('service-breakfast.jpg')->assertSee('service-lunch.jpg')->assertSee('service-dinner.jpg')->assertSee('name="q"',false)->assertSee('name="estado"',false);
        $this->preview('waiter-service-programming',$response->getContent());
        $this->get(route('services.index',['estado'=>'Finalizado']))->assertOk()->assertSee('Sin servicios en esta consulta');
        foreach ([7,8,12] as $role) {
            $this->asRole($role);
            $this->get(route('sites.select'))->assertOk()->assertDontSee('waiter-visual.css')->assertDontSee('ops-waiter');
        }
    }
}
