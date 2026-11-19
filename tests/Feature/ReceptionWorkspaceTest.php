<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReceptionWorkspaceTest extends RoleAccessTest
{
    private function workspace()
    {
        // The parent explicitly forbids migrations and real database connections.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->createOrderSchema();
        $this->travelTo(now('America/Mexico_City')->setDate(2026,11,1)->setTime(12,0));
        Schema::create('unidades', function (Blueprint $t) { $t->increments('id_unidad'); $t->string('codigo'); $t->boolean('permite_fracciones'); });
        Schema::table('insumos', fn (Blueprint $t) => $t->integer('id_unidad_base')->nullable());
        DB::table('unidades')->insert(['id_unidad'=>1,'codigo'=>'kg','permite_fracciones'=>true]);
        DB::table('insumos')->update(['id_unidad_base'=>1]);
        Schema::create('recepciones', function (Blueprint $t) {
            $t->increments('id_recepcion');
            foreach (['id_pedido','id_proveedor','id_usuario'] as $column) $t->integer($column);
            $t->dateTime('fecha_recepcion'); $t->dateTime('fecha_confirmacion')->nullable();
            $t->string('estado'); $t->text('observaciones')->nullable(); $t->string('idempotency_key')->nullable()->unique();
        });
        Schema::create('lotes', function (Blueprint $t) { $t->increments('id_lote'); $t->string('codigo_lote'); });
        Schema::create('recepcion_detalles', function (Blueprint $t) {
            $t->increments('id_recepcion_detalle'); $t->integer('id_recepcion'); $t->integer('id_detalle_pedido');
            $t->integer('id_lote')->nullable(); $t->date('caducidad')->nullable(); $t->decimal('cantidad_entregada',12,3);
            $t->decimal('cantidad_aceptada',12,3); $t->string('resultado_revision'); $t->text('observaciones')->nullable();
        });
        Schema::create('incidencias', function (Blueprint $t) {
            $t->increments('id_incidencia');
            foreach (['id_sede','id_usuario','id_recepcion_detalle'] as $column) $t->integer($column);
            $t->dateTime('fecha'); $t->text('descripcion'); $t->string('estado');
            $t->text('respuesta')->nullable(); $t->dateTime('fecha_cierre')->nullable();
        });
        $user = $this->createUser('Recepcion de insumos',10,1);
        $other = $this->createUser('Recepcion de insumos',10,1);
        DB::table('detalle_pedidos')->insert(['id_detalle_pedido'=>2,'id_pedido'=>2,'id_insumo'=>1,'cantidad'=>12.5]);
        foreach ([[$user->id_usuario,1],[$other->id_usuario,1],[$user->id_usuario,2]] as $index => [$owner,$order]) {
            DB::table('recepciones')->insert(['id_recepcion'=>$index+1,'id_pedido'=>$order,'id_proveedor'=>$order,'id_usuario'=>$owner,'fecha_recepcion'=>'2026-11-01 10:00:00','estado'=>'Pendiente de revisión']);
            DB::table('recepcion_detalles')->insert(['id_recepcion_detalle'=>$index+1,'id_recepcion'=>$index+1,'id_detalle_pedido'=>$order,'cantidad_entregada'=>5,'cantidad_aceptada'=>4,'resultado_revision'=>'Aceptación parcial','observaciones'=>json_encode(['captura'=>['incidencia'=>'Saco dañado'],'faltante_entrega'=>'7.500'])]);
        }
        DB::table('incidencias')->insert(['id_sede'=>1,'id_usuario'=>$user->id_usuario,'id_recepcion_detalle'=>1,'fecha'=>'2026-11-01 10:00:00','descripcion'=>'Saco dañado','estado'=>'Pendiente']);
        $this->actingAs($user)->withSession(['current_site_id'=>1,'active_role_id'=>10]);
        return $user;
    }

    public function test_receiver_workspace_uses_real_own_site_data_and_five_views(): void
    {
        $this->workspace();
        $this->get('/dashboard')->assertOk()->assertViewIs('receptions.workspace.dashboard')
            ->assertViewHas('registered',1)->assertViewHas('scheduledToday',1)->assertViewHas('withIncidents',1)
            ->assertViewHas('week',fn ($week) => $week->sum('total') === 1)
            ->assertDontSee('href="'.route('inventory.index').'"',false);
        $this->get('/recepciones')->assertOk()->assertSee('Proveedor Norte')->assertDontSee('Proveedor Sur');
        $this->get('/recepciones/registrar')->assertOk()->assertSee('Seleccionar pedido');
        $this->get('/recepciones/pedidos/1/nueva')->assertOk()->assertSee('Resumen de la entrega')->assertSee('kg');
        $this->get('/recepciones/historial')->assertOk()->assertViewHas('receipts',fn ($r) => $r->total() === 1);
        $this->get('/incidencias')->assertOk()->assertSee('Saco dañado')->assertSee('Arroz prueba');
        $this->get('/recepciones/registradas/1')->assertOk()->assertSee('Solicitada')->assertSee('7.500');
        $this->get('/recepciones/registradas/2')->assertNotFound();
        $this->get('/recepciones/registradas/3')->assertNotFound();
        if (getenv('RECEPTION_UI_PREVIEW')) {
            $directory = storage_path('app/private/reception-preview');
            if (!is_dir($directory)) mkdir($directory,0750,true);
            $manifest = json_decode(file_get_contents(public_path('build/manifest.json')),true);
            $base = 'file:///'.str_replace('\\','/',base_path()).'/public/';
            foreach (['dashboard'=>'/dashboard','deliveries'=>'/recepciones','register'=>'/recepciones/registrar','capture'=>'/recepciones/pedidos/1/nueva','history'=>'/recepciones/historial','incidents'=>'/incidencias','receipt'=>'/recepciones/registradas/1'] as $name=>$url) {
                $html = $this->get($url)->assertOk()->getContent();
                $html = str_replace('</head>','<link rel="stylesheet" href="'.$base.'build/'.$manifest['resources/css/app.css']['file'].'"></head>',$html);
                $html = str_replace(['http://localhost/css/','http://localhost/js/'],[$base.'css/',$base.'js/'],$html);
                file_put_contents($directory.'/'.$name.'.html',$html);
            }
        }
    }

    public function test_receiver_workspace_filters_notifications_and_forbids_warehouse_actions(): void
    {
        $this->workspace();
        $this->get('/recepciones?proveedor=2')->assertOk()->assertViewHas('orders',fn ($orders) => $orders->total() === 0);
        $this->get('/recepciones/historial?estado=Confirmada')->assertOk()->assertViewHas('receipts',fn ($r) => $r->total() === 0);
        $this->get('/inventario')->assertForbidden();
        $this->get('/pedidos')->assertForbidden();
        $this->get('/recepciones/revision/1')->assertForbidden();
        // Confirmation exists but is exclusive to the active warehouse manager role.
        $this->post('/recepciones/revision/1/confirmar',[])->assertForbidden();
        DB::table('recepciones')->where('id_recepcion',1)->update(['estado'=>'Confirmada','fecha_confirmacion'=>'2026-11-01 11:00:00']);
        $feed = $this->getJson('/recepciones/notificaciones')->assertOk();
        $this->assertCount(2,$feed->json('items'));
        $this->assertSame(2,$feed->json('unread'));
        $this->postJson('/recepciones/notificaciones/leer',['id'=>'all'])->assertOk()->assertJsonPath('unread',0);
        $this->post('/incidencias',['id_recepcion_detalle'=>2,'descripcion'=>'No es mi recepción'])->assertNotFound();
        $this->post('/incidencias',['id_recepcion_detalle'=>1,'descripcion'=>'Empaque roto'])->assertRedirect();
        $this->assertDatabaseHas('incidencias',['descripcion'=>'Empaque roto','estado'=>'Pendiente']);
        config(['receptions.capture_enabled'=>false]);
        $this->get('/recepciones/historial')->assertOk();
        $this->get('/recepciones/registradas/1')->assertOk();
        $this->get('/recepciones/pedidos/1/nueva')->assertStatus(503);
    }

    public function test_receiver_workspace_respects_active_role_and_site_switches(): void
    {
        $user = $this->workspace();
        DB::table('roles')->insert(['id_rol'=>13,'nombre'=>'Jefe de almacen']);
        DB::table('usuario_sede')->insert(['id_usuario'=>$user->id_usuario,'id_sede'=>1,'id_rol'=>13]);
        $this->withSession(['active_role_id'=>13])->get('/recepciones/historial')->assertForbidden();
        $this->withSession(['active_role_id'=>13])->get('/recepciones/pedidos/1/nueva')->assertForbidden();
        DB::table('usuario_sede')->insert(['id_usuario'=>$user->id_usuario,'id_sede'=>2,'id_rol'=>10]);
        $this->withSession(['current_site_id'=>2,'active_role_id'=>10])->get('/recepciones/pedidos/1')->assertNotFound();
        $this->withSession(['current_site_id'=>2,'active_role_id'=>10])->get('/recepciones/registradas/1')->assertNotFound();
        $this->withSession(['current_site_id'=>2,'active_role_id'=>10])->get('/recepciones/historial')->assertOk()->assertViewHas('receipts',fn ($r) => $r->total() === 1);
    }
}
