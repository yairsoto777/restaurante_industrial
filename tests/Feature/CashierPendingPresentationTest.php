<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashierPendingPresentationTest extends OperationsFixtureTestCase
{
    private function pendingFixture():void
    {
        $this->actingAs(User::findOrFail(8))->withSession(['current_site_id'=>1]);
        DB::table('caja_turnos')->insert(['id_turno_caja'=>1,'id_sede'=>1,'id_usuario_apertura'=>8,'estado'=>'abierto','fondo_inicial'=>500,'abierta_at'=>now()->subHours(2)]);
        DB::table('atenciones')->where('id_atencion',1)->update(['estado'=>'Pendiente de pago','comensales'=>3]);
        DB::table('servicios_ofrecidos')->insert(['id_servicio_ofrecido'=>1,'id_atencion'=>1,'descripcion'=>'Comida general real','comensales'=>3,'precio_unitario'=>95,'total'=>285,'estado'=>'Pendiente de cobro','finalizado_en'=>now()->subMinutes(35)]);
        DB::table('cuentas')->insert(['id_cuenta'=>1,'id_atencion'=>1,'subtotal'=>285,'total'=>285,'estado'=>'pendiente','fecha_apertura'=>now()->subMinutes(35)]);
        DB::table('servicios')->insert(['id_servicio'=>2,'id_sede'=>1,'nombre'=>'Jornada anterior','fecha'=>now()->subDay()->toDateString(),'estado'=>'Finalizado','estado_aprobacion'=>'Aprobado']);
        DB::table('atenciones')->insert(['id_atencion'=>2,'id_servicio'=>2,'id_mesa'=>3,'id_usuario_mesero'=>6,'estado'=>'Pendiente de pago','comensales'=>2]);
        DB::table('servicios_ofrecidos')->insert(['id_servicio_ofrecido'=>2,'id_atencion'=>2,'descripcion'=>'Servicio de ayer','comensales'=>2,'precio_unitario'=>95,'total'=>190,'estado'=>'Pendiente de cobro','finalizado_en'=>now()->subDay()]);
        DB::table('cuentas')->insert(['id_cuenta'=>2,'id_atencion'=>2,'subtotal'=>190,'total'=>190,'estado'=>'pendiente','fecha_apertura'=>now()->subDay()]);
    }
    public function test_pending_desk_filters_previous_dates_and_uses_recorded_waits_and_historic_unit_prices():void
    {
        $this->pendingFixture();
        DB::table('atenciones')->insert(['id_atencion'=>3,'id_servicio'=>1,'id_mesa'=>2,'id_usuario_mesero'=>6,'estado'=>'Pendiente de pago','comensales'=>1]);
        DB::table('cuentas')->insert(['id_cuenta'=>3,'id_atencion'=>3,'subtotal'=>84,'total'=>84,'estado'=>'pendiente','fecha_apertura'=>now()]);
        DB::table('platillos')->insert(['id_platillo'=>1,'nombre'=>'Platillo histórico']);DB::table('menus')->insert(['id_menu'=>1,'id_platillo'=>1]);
        DB::table('comandas')->insert(['id_comanda'=>3,'id_atencion'=>3,'estado'=>'Pendiente de pago']);
        DB::table('comanda_detalles')->insert(['id_comanda'=>3,'id_menu'=>1,'cantidad'=>1,'precio_unitario'=>84,'estado'=>'Entregado']);
        $url=route('cash.index',['modulo'=>'pendientes']);
        $response=$this->get($url)->assertOk()->assertSee('Atenciones pendientes')->assertSee('Cobro de cuenta')->assertSee('Sin registro')->assertSee('Platillo histórico')->assertSee('84.00')->assertSee('Sin validez como comprobante de pago')->assertDontSee('data-cr-payment',false);
        $response->assertViewHas('pending',fn($p)=>$p['urgent']===2&&$p['knownWait']===2&&$p['accounts']->count()===3);
        $this->get(route('cash.index',['modulo'=>'pendientes','desde'=>now()->subDay()->toDateString(),'hasta'=>now()->subDay()->toDateString(),'servicio'=>2]))->assertOk()->assertViewHas('accounts',fn($a)=>$a->count()===1&&$a->first()->id_atencion===2);
        $this->get(route('cash.index',['modulo'=>'pendientes','q'=>'Comida general','orden'=>'importe_desc']))->assertOk()->assertViewHas('accounts',fn($a)=>$a->count()===1&&$a->first()->total==285);
        DB::table('servicios')->insert(['id_servicio'=>9,'id_sede'=>2,'nombre'=>'Servicio de otra sede','fecha'=>now()->toDateString()]);
        $this->get(route('cash.index',['modulo'=>'pendientes','servicio'=>9]))->assertOk()->assertViewHas('accounts',fn($a)=>$a->isEmpty());
        $directory=storage_path('app/private/cashier-preview');if(!is_dir($directory))mkdir($directory,0700,true);file_put_contents($directory.'/pendientes.html',$response->getContent());
        DB::table('caja_pagos')->insert(['id_pago'=>55,'id_atencion'=>3,'id_turno_caja'=>1,'id_sede'=>1,'id_usuario'=>8,'importe'=>84,'metodo'=>'tarjeta','estado'=>'pagado','created_at'=>now()]);
        $this->get(route('cash.ticket',55))->assertOk()->assertSee('Platillo histórico')->assertSee('84.00');
    }
    public function test_payment_refresh_exposes_only_a_persisted_ticket_and_keeps_original_amount_and_duplicate_guards():void
    {
        $this->pendingFixture();
        $this->postJson(route('cash.charge',1),['metodo'=>'efectivo','recibido'=>200,'total'=>1])->assertUnprocessable();
        $this->assertSame(0,DB::table('caja_pagos')->count());
        $this->postJson(route('cash.charge',1),['metodo'=>'efectivo','recibido'=>300,'total'=>1])->assertOk()->assertJsonPath('redirect',route('cash.index',['modulo'=>'pendientes']))->assertSessionHas('cash_payment_id',1);
        $this->assertDatabaseHas('caja_pagos',['id_atencion'=>1,'importe'=>285,'recibido'=>300,'cambio'=>15]);
        $this->get(route('cash.index',['modulo'=>'pendientes']))->assertOk()->assertSee('Cobro registrado correctamente.')->assertSee('data-cp-paid-ticket',false)->assertViewHas('accounts',fn($a)=>!$a->contains('id_atencion',1));
        $this->postJson(route('cash.charge',1),['metodo'=>'efectivo','recibido'=>300])->assertNotFound();$this->assertSame(1,DB::table('caja_pagos')->count());
        $this->postJson(route('cash.charge',999),['metodo'=>'tarjeta'])->assertNotFound();
        $this->get(route('cash.index',['modulo'=>'pendientes','q'=>'inexistente']))->assertOk()->assertSee('Estás al día')->assertSee('Selecciona una cuenta pendiente para comenzar el cobro.')->assertSee('data-cp-confirm disabled',false);
        $this->assertDatabaseHas('caja_turnos',['estado'=>'abierto','fondo_inicial'=>500]);
    }
}
