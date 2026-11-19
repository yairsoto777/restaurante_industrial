<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashierWorkspaceTest extends OperationsFixtureTestCase
{
    protected function setUp():void
    {
        parent::setUp();
        $this->actingAs(User::findOrFail(8))->withSession(['current_site_id'=>1]);
        DB::table('atenciones')->where('id_atencion',1)->update(['estado'=>'Pendiente de pago','comensales'=>3]);
        DB::table('servicios_ofrecidos')->insert(['id_servicio_ofrecido'=>1,'id_atencion'=>1,'descripcion'=>'Comida general','comensales'=>3,'precio_unitario'=>95,'total'=>285,'estado'=>'Pendiente de cobro','created_at'=>now(),'updated_at'=>now()]);
        DB::table('cuentas')->insert(['id_cuenta'=>1,'id_atencion'=>1,'subtotal'=>285,'total'=>285,'estado'=>'pendiente','fecha_apertura'=>now()]);
    }
    public function test_six_views_are_scoped_and_keep_the_existing_open_charge_ticket_close_flow():void
    {
        $this->get(route('dashboard'))->assertRedirect(route('cash.index'));
        $this->get(route('cash.index',['modulo'=>'pendientes']))->assertOk()->assertSee('Abre un turno de caja para habilitar los cobros.');
        $this->postJson(route('cash.open'),['fondo_inicial'=>500])->assertOk()->assertJsonPath('redirect',route('cash.index',['modulo'=>'turno']));
        $this->get(route('cash.index',['modulo'=>'turno']))->assertSee('Caja abierta correctamente.');
        foreach(['panel','turno','pendientes','historial','tickets','cierre'] as $module){
            $response=$this->get(route('cash.index',['modulo'=>$module]));$response->assertOk()->assertSee('cashier-workspace.css')->assertDontSee('Sede no autorizada');
            preg_match('~<nav\s+class="sidebar-nav".*?</nav>~s',$response->getContent(),$navigation);
            $this->assertSame(6,substr_count($navigation[0]??'','class="nav-link'));
        }
        $this->postJson(route('cash.charge',1),['metodo'=>'efectivo','recibido'=>200])->assertUnprocessable();
        $this->assertSame(0,DB::table('caja_pagos')->count());
        $this->post(route('cash.charge',1),['metodo'=>'efectivo','recibido'=>300])->assertRedirect()->assertSessionHas('cash_payment_id',1);
        $this->assertDatabaseHas('caja_pagos',['id_pago'=>1,'importe'=>285,'recibido'=>300,'cambio'=>15,'estado'=>'pagado']);
        $this->get(route('cash.ticket',1))->assertOk()->assertSee('Comida general')->assertSee('285.00')->assertSee('15.00')->assertSee('Imprimir / Guardar PDF')->assertDontSee('data-cr-auto-print',false);
        $this->get(route('cash.ticket',[1,'imprimir'=>1]))->assertOk()->assertSee('data-cr-auto-print',false);
        $this->post(route('cash.close'),['efectivo_contado'=>785])->assertRedirect();
        $this->assertDatabaseHas('caja_turnos',['estado'=>'cerrado','efectivo_esperado'=>785,'efectivo_contado'=>785,'diferencia'=>0]);
        $this->get(route('cash.index',['modulo'=>'cierre']))->assertOk()->assertSee('No hay un turno abierto')->assertSee('785.00');
    }
    public function test_payment_filters_totals_and_tickets_never_include_other_sites():void
    {
        DB::table('caja_pagos')->insert(['id_pago'=>1,'id_turno_caja'=>1,'id_atencion'=>1,'id_usuario'=>8,'id_sede'=>1,'metodo'=>'tarjeta','importe'=>285,'recibido'=>285,'cambio'=>0,'estado'=>'pagado','created_at'=>now()]);
        DB::table('caja_pagos')->insert(['id_pago'=>2,'id_turno_caja'=>2,'id_atencion'=>999,'id_usuario'=>8,'id_sede'=>2,'metodo'=>'efectivo','importe'=>98765,'estado'=>'pagado','created_at'=>now()]);
        $this->get(route('cash.index',['modulo'=>'historial','metodo'=>'tarjeta','q'=>'Comida']))->assertOk()->assertSee('285.00')->assertDontSee('98,765.00');
        $this->get(route('cash.index',['modulo'=>'historial','metodo'=>'efectivo']))->assertOk()->assertSee('No hay cobros registrados para esta consulta.');
        $this->get(route('cash.index',['modulo'=>'tickets','hasta'=>now()->toDateString()]))->assertOk()->assertSee('Ticket #1')->assertDontSee('Ticket #2');
        $this->get(route('cash.ticket',2))->assertNotFound();
        $this->get(route('cash.history'))->assertOk()->assertSee('Movimientos de cobro');
        // These exports contain SQLite fixture data only and do not operate on the installation.
        $directory=storage_path('app/private/cashier-preview');if(!is_dir($directory))mkdir($directory,0700,true);
        DB::table('atenciones')->insert(['id_atencion'=>2,'id_servicio'=>1,'id_mesa'=>3,'id_usuario_mesero'=>6,'estado'=>'Pendiente de pago','comensales'=>2,'inicio_hora'=>'10:00:00']);
        DB::table('servicios_ofrecidos')->insert(['id_servicio_ofrecido'=>2,'id_atencion'=>2,'descripcion'=>'Comida pendiente de cobro','comensales'=>2,'precio_unitario'=>95,'total'=>190,'estado'=>'Pendiente de cobro']);
        DB::table('cuentas')->insert(['id_cuenta'=>2,'id_atencion'=>2,'subtotal'=>190,'total'=>190,'estado'=>'pendiente']);
        $this->post(route('cash.open'),['fondo_inicial'=>500])->assertRedirect();
        foreach(['panel','turno','pendientes','historial','tickets','cierre'] as $module)file_put_contents($directory.'/'.$module.'.html',$this->get(route('cash.index',['modulo'=>$module]))->assertOk()->getContent());
        file_put_contents($directory.'/ticket-preview.html',$this->get(route('cash.ticket',1))->assertOk()->getContent());
    }
}
