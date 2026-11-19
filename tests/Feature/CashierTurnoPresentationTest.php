<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashierTurnoPresentationTest extends OperationsFixtureTestCase
{
    public function test_turno_presentation_uses_real_payment_totals_and_preserves_pending_or_recorded_audit():void
    {
        $this->actingAs(User::findOrFail(8))->withSession(['current_site_id'=>1]);
        $url=route('cash.index',['modulo'=>'turno']);
        $this->get($url)->assertOk()->assertSee('Abrir turno de caja')->assertSee('Pendiente de arqueo');
        DB::table('caja_turnos')->insert(['id_turno_caja'=>1,'id_sede'=>1,'id_usuario_apertura'=>8,'fondo_inicial'=>500,'estado'=>'abierto','abierta_at'=>now()->startOfDay()->addHours(8)]);
        foreach([['efectivo',190,200,10],['tarjeta',285,285,0],['transferencia',95,95,0]] as $i=>$p){
            DB::table('caja_pagos')->insert(['id_pago'=>$i+1,'id_turno_caja'=>1,'id_atencion'=>$i+1,'id_usuario'=>8,'id_sede'=>1,'metodo'=>$p[0],'importe'=>$p[1],'recibido'=>$p[2],'cambio'=>$p[3],'estado'=>'pagado','created_at'=>now()->startOfDay()->addHours(9+$i)]);
        }
        $response=$this->get($url)->assertOk()->assertSee('570.00 MXN')->assertSee('690.00 MXN')->assertSee('Pendiente de arqueo')->assertSee('Auditoría del turno')->assertSee('Flujo del turno por hora')->assertSee('33.3%')->assertSee('50.0%')->assertSee('16.7%')->assertSee('ct-flow-line',false)->assertSee('data-ct-print',false)->assertDontSee('Registrar movimiento');
        $response->assertViewHas('turnoPayments',fn($payments)=>$payments->count()===3);
        $directory=storage_path('app/private/cashier-preview');if(!is_dir($directory))mkdir($directory,0700,true);
        file_put_contents($directory.'/turno.html',$response->getContent());
        $this->assertSame(3,DB::table('caja_pagos')->count());
        $this->assertDatabaseHas('caja_turnos',['id_turno_caja'=>1,'estado'=>'abierto','efectivo_contado'=>null]);
        DB::table('caja_turnos')->where('id_turno_caja',1)->update(['estado'=>'cerrado','efectivo_esperado'=>690,'efectivo_contado'=>680,'diferencia'=>-10,'cerrada_at'=>now()->startOfDay()->addHours(12)]);
        $this->get($url)->assertOk()->assertSee('Resumen del turno cerrado #1')->assertSee('$-10.00 MXN')->assertSee('Revisar diferencia registrada')->assertSee('Cierre registrado')->assertSee('Abrir un nuevo turno');
        DB::table('caja_turnos')->insert(['id_turno_caja'=>2,'id_sede'=>2,'id_usuario_apertura'=>8,'fondo_inicial'=>9999,'estado'=>'cerrado','abierta_at'=>now()]);
        $this->get(route('cash.index',['modulo'=>'turno','turno'=>2]))->assertNotFound();
    }
}
