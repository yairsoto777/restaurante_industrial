<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashierTicketsPresentationTest extends OperationsFixtureTestCase
{
    private function ticketFixture():void
    {
        $this->actingAs(User::findOrFail(8))->withSession(['current_site_id'=>1]);
        DB::table('caja_turnos')->insert(['id_turno_caja'=>1,'id_sede'=>1,'id_usuario_apertura'=>8,'fondo_inicial'=>500,'estado'=>'abierto','abierta_at'=>now()->subHours(3)]);
        DB::table('caja_turnos')->insert(['id_turno_caja'=>2,'id_sede'=>2,'id_usuario_apertura'=>8,'fondo_inicial'=>9999,'estado'=>'cerrado','abierta_at'=>now()->subDay()]);
        DB::table('caja_turnos')->insert(['id_turno_caja'=>3,'id_sede'=>1,'id_usuario_apertura'=>8,'fondo_inicial'=>300,'estado'=>'cerrado','abierta_at'=>now()->subDay(),'cerrada_at'=>now()->subHours(5)]);
        for($i=1;$i<=22;$i++){
            if($i===1)DB::table('atenciones')->where('id_atencion',1)->update(['estado'=>'Cerrada','comensales'=>2]);else DB::table('atenciones')->insert(['id_atencion'=>$i,'id_servicio'=>1,'id_mesa'=>1,'id_usuario_mesero'=>6,'estado'=>'Cerrada','comensales'=>2]);
            if($i!==3)DB::table('servicios_ofrecidos')->insert(['id_atencion'=>$i,'descripcion'=>'Comida registrada '.$i,'comensales'=>2,'precio_unitario'=>95,'total'=>190,'estado'=>'Pagado']);
            DB::table('caja_pagos')->insert(['id_pago'=>$i,'id_atencion'=>$i,'id_turno_caja'=>$i===4?3:1,'id_usuario'=>8,'id_sede'=>1,'metodo'=>$i===4?'tarjeta':'efectivo','importe'=>$i===3?84:190,'recibido'=>200,'cambio'=>$i===3?116:10,'estado'=>$i===2?'cancelado':'pagado','created_at'=>now()->subMinutes(30)->addSeconds($i)]);
        }
        DB::table('platillos')->insert(['id_platillo'=>1,'nombre'=>'Concepto histórico original']);DB::table('menus')->insert(['id_menu'=>1,'id_platillo'=>1]);
        DB::table('comandas')->insert(['id_comanda'=>3,'id_atencion'=>3,'estado'=>'Pagada']);DB::table('comanda_detalles')->insert(['id_comanda'=>3,'id_menu'=>1,'cantidad'=>2,'precio_unitario'=>42,'estado'=>'Entregado']);
        DB::table('caja_pagos')->insert(['id_pago'=>999,'id_atencion'=>999,'id_turno_caja'=>2,'id_usuario'=>8,'id_sede'=>2,'metodo'=>'efectivo','importe'=>99999,'estado'=>'pagado','created_at'=>now()]);
    }
    public function test_ticket_ledger_has_read_only_pagination_filters_and_original_receipt_details():void
    {
        $this->ticketFixture();$before=DB::table('caja_pagos')->orderBy('id_pago')->get()->toJson();
        $response=$this->get(route('cash.index',['modulo'=>'tickets']))->assertOk()->assertSee('Emisión, búsqueda y reimpresión de comprobantes.')->assertSee('Listado de tickets')->assertSee('Vista del ticket')->assertSee('TICKET DE VENTA')->assertSee('Concepto histórico original')->assertSee('42.00')->assertSee('84.00')->assertSee('Descargar PDF')->assertDontSee('99,999.00')->assertDontSee('Pendientes de entrega');
        $response->assertViewHas('records',fn($r)=>$r->total()===22&&$r->count()===20);
        $response->assertViewHas('ticketDesk',fn($d)=>$d['recent']->count()===4&&$d['previews']->firstWhere('id_pago',3)->conceptos->first()->precio_unitario==42);
        $this->get(route('cash.index',['modulo'=>'tickets','estado'=>'cancelado','orden'=>'antiguo']))->assertOk()->assertViewHas('records',fn($r)=>$r->total()===1&&$r->first()->id_pago===2);
        $this->get(route('cash.index',['modulo'=>'tickets','turno'=>3,'metodo'=>'tarjeta']))->assertOk()->assertViewHas('records',fn($r)=>$r->total()===1&&$r->first()->id_pago===4);
        $this->get(route('cash.index',['modulo'=>'tickets','orden'=>'importe_asc']))->assertOk()->assertViewHas('records',fn($r)=>$r->first()->id_pago===3);
        $this->get(route('cash.index',['modulo'=>'tickets','page'=>2]))->assertOk()->assertViewHas('ticketDesk',fn($d)=>$d['previews']->contains('id_pago',22)&&$d['previews']->contains('id_pago',1));
        $this->get(route('cash.index',['modulo'=>'tickets','turno'=>2]))->assertNotFound();
        $this->get(route('cash.index',['modulo'=>'tickets','q'=>'no-existe']))->assertOk()->assertSee('No hay comprobantes disponibles para el periodo seleccionado.')->assertSee('Selecciona un ticket para visualizar su comprobante.')->assertSee('data-tk-action="print" disabled',false);
        $this->assertSame($before,DB::table('caja_pagos')->orderBy('id_pago')->get()->toJson());
        $directory=storage_path('app/private/cashier-preview');if(!is_dir($directory))mkdir($directory,0700,true);file_put_contents($directory.'/tickets.html',$response->getContent());file_put_contents($directory.'/tickets-empty.html',$this->get(route('cash.index',['modulo'=>'tickets','q'=>'no-existe']))->getContent());
    }
}
