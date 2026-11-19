<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\{DB,Schema,Storage};
use Illuminate\Http\UploadedFile;

class WaiterWorkspaceTest extends OperationsFixtureTestCase
{
    protected function setUp():void
    {
        parent::setUp();
        foreach(['zonas'=>['descripcion TEXT','imagen_url TEXT','color TEXT'],'zona_meseros'=>['id_servicio INTEGER'],'atenciones'=>['observaciones TEXT'],'incidencias'=>['id_servicio INTEGER','id_zona INTEGER','id_mesa INTEGER','evidencia_url TEXT'],'servicios'=>['cover_url TEXT'],'menus'=>['id_servicio INTEGER','publicado INTEGER','disponible INTEGER','precio_venta NUMERIC'],'platillos'=>['id_sede INTEGER','id_categoria INTEGER','descripcion TEXT','imagen_url TEXT']] as $table=>$columns)foreach($columns as $column)DB::statement('ALTER TABLE '.$table.' ADD COLUMN '.$column);
        DB::statement('CREATE TABLE categorias (id_categoria INTEGER PRIMARY KEY,nombre TEXT)');
        DB::table('zona_meseros')->update(['id_servicio'=>1]);
        DB::table('categorias')->insert(['id_categoria'=>1,'nombre'=>'Platos principales']);
        DB::table('platillos')->insert(['id_platillo'=>1,'id_sede'=>1,'id_categoria'=>1,'nombre'=>'Platillo publicado','descripcion'=>'Descripción real']);
        DB::table('menus')->insert(['id_menu'=>1,'id_servicio'=>1,'id_platillo'=>1,'publicado'=>1,'disponible'=>1,'precio_venta'=>95]);
        $this->actingAs(User::findOrFail(6))->withSession(['active_site_id'=>1]);
    }
    public function test_seven_views_render_with_real_scoped_data():void
    {
        foreach(['panel','servicios','mesas','atenciones','menu','historial','incidencias'] as $module)$this->get(route('waiter.index',['modulo'=>$module,'id_servicio'=>1]))->assertOk()->assertSee('ww-workspace',false)->assertSee('Reportar incidencias')->assertDontSee('Crear mesa');
        $this->get(route('waiter.index',['modulo'=>'menu']))->assertSee('Platillo publicado')->assertSee('Platos principales');
        $this->get(route('waiter.index',['modulo'=>'mesas']))->assertSee('Mesa 3')->assertSee('Mantenimiento');
        $this->post(route('waiter.table.store'),[])->assertForbidden();
    }
    public function test_opening_attention_checks_jornada_capacity_conflicts_and_saves_notes():void
    {
        $url=route('waiter.attention.store');
        $this->postJson($url,['id_servicio'=>1,'id_mesa'=>3,'comensales'=>5])->assertUnprocessable();
        DB::table('servicios')->where('id_servicio',1)->update(['estado'=>'Programado']);
        $this->postJson($url,['id_servicio'=>1,'id_mesa'=>3,'comensales'=>2])->assertUnprocessable();
        DB::table('servicios')->where('id_servicio',1)->update(['estado'=>'En curso']);
        $this->postJson($url,['id_servicio'=>1,'id_mesa'=>2,'comensales'=>2])->assertUnprocessable();
        $this->postJson($url,['id_servicio'=>1,'id_mesa'=>3,'comensales'=>2,'observaciones'=>'Alergia informada al cacahuate'])->assertRedirect();
        $this->assertDatabaseHas('atenciones',['id_mesa'=>3,'observaciones'=>'Alergia informada al cacahuate','comensales'=>2]);
        $this->postJson($url,['id_servicio'=>1,'id_mesa'=>3,'comensales'=>2])->assertUnprocessable();
        $this->assertSame(1,DB::table('atenciones')->where('id_mesa',3)->count());
    }
    public function test_incidents_validate_zone_and_persist_evidence():void
    {
        Storage::fake('public');$url=route('waiter.incidents.store');
        $this->postJson($url,['id_servicio'=>1,'id_zona'=>999,'id_mesa'=>3,'descripcion'=>'Mobiliario dañado'])->assertUnprocessable()->assertJsonValidationErrors('id_zona');
        $this->postJson($url,['id_servicio'=>1,'id_mesa'=>3,'descripcion'=>'Mobiliario dañado'])->assertUnprocessable()->assertJsonValidationErrors('id_mesa');
        $this->post($url,['id_servicio'=>1,'id_zona'=>1,'id_mesa'=>3,'descripcion'=>'Mobiliario dañado','evidencia'=>UploadedFile::fake()->image('evidencia.jpg')])->assertRedirect();
        $record=DB::table('incidencias')->where('descripcion','Mobiliario dañado')->first();Storage::disk('public')->assertExists($record->evidencia_url);
        $this->get(route('waiter.index',['modulo'=>'incidencias']))->assertSee($record->evidencia_url)->assertSee('Mobiliario dañado');
        $this->postJson($url,['id_servicio'=>1,'descripcion'=>'Faltan cubiertos en el servicio'])->assertRedirect();
        $this->assertDatabaseHas('incidencias',['descripcion'=>'Faltan cubiertos en el servicio','id_mesa'=>null,'id_zona'=>null]);
        $this->postJson($url,['id_servicio'=>1,'tipo'=>'Tipo no permitido','descripcion'=>'Demora durante el servicio'])->assertUnprocessable()->assertJsonValidationErrors('tipo');
        $this->postJson($url,['id_servicio'=>1,'tipo'=>'Problema operativo','descripcion'=>'Demora durante el servicio'])->assertRedirect();
        $this->assertDatabaseHas('incidencias',['descripcion'=>'[Problema operativo] Demora durante el servicio']);
    }
    public function test_foreign_site_and_unassigned_jornada_are_not_accessible():void
    {
        DB::table('servicios')->insert(['id_servicio'=>2,'id_sede'=>1,'nombre'=>'Sin asignación','fecha'=>now()->toDateString(),'estado'=>'En curso','estado_aprobacion'=>'Aprobado']);
        $this->get(route('waiter.index',['id_servicio'=>2]))->assertForbidden();
        $this->postJson(route('waiter.attention.store'),['id_servicio'=>2,'id_mesa'=>3,'comensales'=>1])->assertForbidden();
        $this->get(route('capitan.index'))->assertForbidden();
        $this->get(route('waiter.notifications.index'))->assertOk()->assertJsonPath('unread',1);
    }
    public function test_new_forms_preserve_offered_service_and_cash_handoff_without_duplicate_accounts():void
    {
        DB::table('servicios_ofrecidos')->insert([
            'id_atencion' => 1, 'descripcion' => 'Servicio de comida', 'comensales' => 2,
            'precio_unitario' => 95.25, 'total' => 190.50, 'estado' => 'Registrado',
        ]);
        $this->assertDatabaseHas('servicios_ofrecidos',['id_atencion'=>1,'total'=>190.50,'estado'=>'Registrado']);
        $this->patchJson(route('waiter.offered-service.finalize',1))->assertRedirect();
        $this->patchJson(route('waiter.offered-service.finalize',1))->assertUnprocessable();
        $this->assertSame(1,DB::table('cuentas')->where('id_atencion',1)->count());
        $this->assertDatabaseHas('cuentas',['id_atencion'=>1,'estado'=>'pendiente','total'=>190.50]);
        $this->assertDatabaseHas('atenciones',['id_atencion'=>1,'estado'=>'Pendiente de pago']);
        $this->postJson(route('waiter.notifications.read'),['id'=>'all'])->assertOk()->assertJsonPath('unread',0);
        $this->get(route('waiter.index',['modulo'=>'mesas']))->assertSee('Ocupada');
        // Export isolated, synthetic fixture views for a single desktop/mobile visual check.
        $directory=storage_path('app/private/waiter-preview');if(!is_dir($directory))mkdir($directory,0700,true);
        foreach(['panel','servicios','mesas','atenciones','menu','historial','incidencias'] as $module)file_put_contents($directory.'/'.$module.'.html',$this->get(route('waiter.index',['modulo'=>$module]))->assertOk()->getContent());
    }

    public function test_command_draft_kitchen_delivery_and_cash_use_the_original_line_prices(): void
    {
        foreach (['comandas' => ['id_usuario_mesero INTEGER', 'observaciones TEXT'],
            'comanda_detalles' => ['observaciones TEXT'], 'entregas' => ['id_menu INTEGER', 'id_usuario INTEGER']] as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("ALTER TABLE {$table} ADD COLUMN {$column}");
            }
        }
        DB::table('roles')->insert(['id_rol' => 13, 'nombre' => 'Personal de cocina']);
        DB::table('usuarios')->insert(['id_usuario' => 13, 'nombre' => 'Cocina', 'correo' => 'cocina@example.test']);
        DB::table('usuario_sede')->insert(['id_usuario' => 13, 'id_sede' => 1, 'id_rol' => 13]);
        DB::table('platillos')->insert(['id_platillo' => 2, 'id_sede' => 2, 'nombre' => 'Platillo de otra sede']);
        DB::table('menus')->insert(['id_menu' => 2, 'id_servicio' => 1, 'id_platillo' => 2, 'publicado' => 1, 'disponible' => 1, 'precio_venta' => 15]);
        DB::table('platillos')->insert(['id_platillo' => 3, 'id_sede' => 1, 'nombre' => 'Segunda orden publicada']);
        DB::table('menus')->insert(['id_menu' => 3, 'id_servicio' => 1, 'id_platillo' => 3, 'publicado' => 1, 'disponible' => 1, 'precio_venta' => 40]);
        $this->post(route('waiter.attention.store'), [
            'id_mesa' => 3, 'id_servicio' => 1, 'comensales' => 3, 'observaciones' => 'Alergia al camarón',
        ])->assertRedirect();
        $attention = DB::table('atenciones')->where('id_mesa', 3)->value('id_atencion');
        $url = route('waiter.command.save', $attention);
        $finalize = route('waiter.offered-service.finalize', $attention);
        $page = route('waiter.index', ['modulo' => 'mesas']);
        $this->get($page)->assertOk()->assertSee('Registrar comanda')->assertSee('Platillo publicado')->assertDontSee('Platillo de otra sede');
        $line = ['id' => 1, 'quantity' => 3, 'cents' => 9500];
        $items = [$line, ['id' => 3, 'quantity' => 1, 'cents' => 4000]];
        $this->postJson(route('waiter.offered-service.store', $attention), [
            'descripcion' => 'Cargo manual', 'comensales' => 3, 'precio_unitario' => '0.01',
        ])->assertUnprocessable();
        $this->postJson($url, ['action' => 'send', 'items' => [$line, $line]])->assertUnprocessable();
        $this->postJson($url, ['action' => 'send', 'items' => [array_replace($line, ['quantity' => 0])]])->assertUnprocessable();
        $this->postJson($url, ['action' => 'send', 'items' => [['id' => 2, 'quantity' => 1, 'cents' => 1500]]])->assertUnprocessable();
        $this->postJson($url, ['action' => 'send', 'items' => [array_replace($line, ['cents' => 1])]])->assertUnprocessable();
        $this->postJson($url, ['action' => 'draft', 'items' => $items, 'notes' => 'Verificar contaminación cruzada'])
            ->assertOk()->assertJsonPath('command', null);
        $this->get($page)->assertSee('Verificar contaminación cruzada');
        $this->assertDatabaseMissing('comandas', ['id_atencion' => $attention]);

        DB::table('zona_meseros')->update(['activo' => 0]);
        $this->postJson($url, ['action' => 'send', 'items' => $items])->assertNotFound();
        DB::table('zona_meseros')->update(['activo' => 1]);
        $this->postJson($url, ['action' => 'send', 'items' => $items, 'notes' => 'Verificar contaminación cruzada'])->assertOk();
        $this->postJson($url, ['action' => 'send', 'items' => [$line]])->assertUnprocessable();
        $this->patchJson($finalize)->assertUnprocessable();
        DB::table('menus')->where('id_menu', 1)->update(['precio_venta' => 120]);

        $command = DB::table('comandas')->where('id_atencion', $attention)->value('id_comanda');
        $kitchen = fn ($module, $params = []) => route('kitchen.index', ['modulo' => $module] + $params);
        $this->actingAs(User::findOrFail(13));
        $this->get(route('dashboard'))->assertRedirect(route('kitchen.index'));
        $this->get(route('menus.index'))->assertForbidden();
        $this->get($kitchen('panel'))->assertOk()->assertSee('Comandas pendientes')->assertSee('Alergia al camarón');
        $this->get($kitchen('servicio'))->assertOk()->assertSee('Carga de preparación')->assertSee('Segunda orden publicada');
        $this->get($kitchen('comandas', ['q' => '#'.$command, 'estado' => 'pendiente', 'comanda' => $command]))->assertOk()
            ->assertSee('Platillo publicado')->assertSee('Alergia al camarón')->assertSee('Verificar contaminación cruzada')
            ->assertSee('Iniciar preparación');
        $this->get($kitchen('comandas', ['comanda' => 999999]))->assertNotFound();
        $firstDetail = DB::table('comanda_detalles')->where('id_comanda', $command)->value('id_comanda_detalle');
        DB::table('servicios')->where('id_servicio', 1)->update(['id_sede' => 2]);
        $this->get($kitchen('comandas', ['comanda' => $command]))->assertNotFound();
        $this->patchJson(route('kitchen.order-status.update', $firstDetail), ['estado' => 'preparando'])->assertNotFound();
        DB::table('servicios')->where('id_servicio', 1)->update(['id_sede' => 1]);
        $this->get($kitchen('preparacion'))->assertOk()->assertSee('Sin partidas en preparación');
        $this->get($kitchen('listos'))->assertOk()->assertSee('Sin platillos listos');

        $observationUrl = route('waiter.command.observations.store', $command);
        $waiter = User::findOrFail(6);
        $cook = User::findOrFail(13);
        $observation = ['observacion' => 'Verificar una alergia informada en la prueba', 'prioridad' => 'alergenos',
            'solicitud' => (string) \Illuminate\Support\Str::uuid()];
        $this->actingAs($waiter)->get($page)->assertOk()->assertSee('El registro de nuevas observaciones se habilitará');
        $this->postJson($observationUrl, $observation)->assertStatus(503);
        DB::statement('CREATE TABLE comanda_observaciones (id_observacion INTEGER PRIMARY KEY,
            id_comanda INTEGER, id_autor INTEGER, solicitud TEXT, prioridad TEXT, observacion TEXT, creada_en TEXT,
            id_revisor INTEGER, revisada_en TEXT, UNIQUE (id_comanda, solicitud))');
        $this->actingAs($cook)->postJson($observationUrl, $observation)->assertForbidden();

        foreach (['preparacion_iniciada_en TEXT', 'preparacion_iniciada_por INTEGER', 'lista_en TEXT', 'lista_por INTEGER'] as $column) {
            DB::statement('ALTER TABLE comanda_detalles ADD COLUMN '.$column);
        }
        foreach (DB::table('comanda_detalles')->pluck('id_comanda_detalle') as $detail) {
            $action = route('kitchen.order-status.update', $detail);
            $this->patchJson($action, ['estado' => 'listo'])->assertUnprocessable();
            $this->patch($action, ['estado' => 'preparando'])->assertRedirect();
            $this->get($kitchen('preparacion'))->assertOk()->assertSee('Marcar como listo')->assertSee('Preparó Cocina');
            if ((int) $detail === (int) $firstDetail) {
                $this->actingAs($waiter)->get($page)->assertOk()->assertSee('Guardar y notificar a cocina');
                $this->postJson($observationUrl, $observation)->assertRedirect();
                $this->postJson($observationUrl, $observation)->assertRedirect();
                $note = DB::table('comanda_observaciones')->sole();
                $this->assertSame(6, (int) $note->id_autor);
                $review = route('kitchen.observations.acknowledge', [$command, $note->id_observacion]);
                $this->patchJson($review, ['confirmada' => '1'])->assertForbidden();
                $this->actingAs($cook)->get($kitchen('comandas', ['comanda' => $command]))->assertOk()
                    ->assertSee('Alérgenos por revisar')->assertSee($observation['observacion']);
                $this->getJson(route('kitchen.notifications.index'))->assertOk()->assertJsonPath('unread', 1);
                $this->patchJson($action, ['estado' => 'listo'])->assertUnprocessable()->assertJsonValidationErrors('estado');
                $this->patchJson($review)->assertUnprocessable()->assertJsonValidationErrors('confirmada');
                $this->patch($review, ['confirmada' => '1'])->assertRedirect();
                $this->assertDatabaseHas('comanda_observaciones', ['id_observacion' => $note->id_observacion, 'id_revisor' => 13]);
                $this->getJson(route('kitchen.notifications.index'))->assertOk()->assertJsonPath('unread', 0);
            }
            $this->patch($action, ['estado' => 'listo'])->assertRedirect();
            $this->get($kitchen('listos'))->assertOk()->assertSee('Registrar entrega')->assertSee('Listo desde');
            $this->patch($action, ['estado' => 'entregado'])->assertRedirect();
            $this->patchJson($action, ['estado' => 'entregado'])->assertUnprocessable();
        }
        $this->assertDatabaseCount('entregas', 2);
        $this->assertDatabaseHas('comanda_detalles', ['id_comanda' => $command, 'preparacion_iniciada_por' => 13, 'lista_por' => 13]);
        $this->get($kitchen('historial', ['estado' => 'entregado', 'desde' => now()->toDateString(), 'comanda' => $command]))
            ->assertOk()->assertSee('Trazabilidad por partida')->assertSee('Entregado')->assertDontSee('data-kp-action', false);
        $this->actingAs(User::findOrFail(6))->get($page)->assertOk()->assertSee('Entregado');
        $this->postJson($observationUrl, array_replace($observation, [
            'solicitud' => (string) \Illuminate\Support\Str::uuid(),
        ]))->assertUnprocessable();
        $this->assertDatabaseCount('comanda_observaciones', 1);
        $this->patchJson($finalize)->assertRedirect();
        $this->patchJson($finalize)->assertUnprocessable();
        $this->assertDatabaseHas('cuentas', ['id_atencion' => $attention, 'total' => 325, 'estado' => 'pendiente']);
        $this->postJson(route('cash.charge', $attention), ['metodo' => 'tarjeta'])->assertForbidden();

        $this->actingAs(User::findOrFail(8))->post(route('cash.open'), ['fondo_inicial' => 100])->assertRedirect();
        $this->get(route('cash.index', ['modulo' => 'pendientes']))->assertOk()
            ->assertSee('Platillo publicado')->assertSee('Segunda orden publicada')->assertSee('325.00');
        $this->post(route('cash.charge', $attention), ['metodo' => 'tarjeta'])->assertRedirect();
        $payment = DB::table('caja_pagos')->where('id_atencion', $attention)->value('id_pago');
        $this->get(route('cash.ticket', $payment))->assertOk()->assertSee('Platillo publicado')->assertSee('95.00')->assertSee('325.00');
        $this->assertDatabaseHas('atenciones', ['id_atencion' => $attention, 'estado' => 'Cerrada']);
    }
}
