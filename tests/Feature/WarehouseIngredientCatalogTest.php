<?php
namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

class WarehouseIngredientCatalogTest extends RoleAccessTest
{
    public function test_catalog_zero_stock_assignment_and_order_flow_are_isolated(): void
    {
        $this->assertSame('sqlite',DB::connection()->getDriverName());
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        $this->createOrderSchema();
        Schema::table('insumos',fn(Blueprint $t)=>$t->integer('id_unidad_base')->nullable());
        Schema::table('pedidos',fn(Blueprint $t)=>$t->integer('id_usuario')->nullable());
        Schema::create('unidades',function(Blueprint $t){$t->increments('id_unidad');$t->string('nombre');$t->string('codigo');$t->boolean('permite_fracciones');});
        Schema::create('insumo_sedes',function(Blueprint $t){$t->increments('id_insumo_sede');$t->integer('id_insumo');$t->integer('id_sede');$t->decimal('stock',12,3);$t->decimal('punto_de_reorden',12,3)->nullable();});
        Schema::create('lotes',fn(Blueprint $t)=>$t->increments('id_lote'));
        Schema::create('inventarios_finales',fn(Blueprint $t)=>$t->increments('id_inventario_final'));
        DB::table('unidades')->insert(['id_unidad'=>1,'nombre'=>'Kilogramos','codigo'=>'kg','permite_fracciones'=>1]);
        DB::table('insumos')->where('id_insumo',1)->update(['id_unidad_base'=>1]);
        DB::table('insumo_sedes')->insert(['id_insumo'=>1,'id_sede'=>2,'stock'=>7]);
        $warehouse=$this->createUser('Jefe de almacen',13,1);
        $this->actingAs($warehouse)->withSession(['current_site_id'=>1,'active_role_id'=>13]);
        $this->get(route('orders.create'))->assertOk()->assertSee('Registrar o asignar insumos')->assertViewHas('ingredients',fn($i)=>$i->isEmpty());
        $this->get(route('warehouse.ingredients.index'))->assertOk()->assertSee('Registrar insumo')->assertSee('Kilogramos')->assertViewHas('fields',['categoria'=>false,'descripcion'=>false,'activo'=>false]);
        $payload=['nombre'=>'Producto aislado de prueba','id_unidad_base'=>1,'id_sede'=>2];
        $this->post(route('inventory.ingredients.store'),$payload+['stock'=>90])->assertSessionHasErrors('stock');
        $this->assertDatabaseCount('insumos',1);
        $this->post(route('inventory.ingredients.store'),array_replace($payload,['id_unidad_base'=>999]))->assertSessionHasErrors('id_unidad_base');
        $this->post(route('inventory.ingredients.store'),$payload)->assertRedirect(route('warehouse.ingredients.index'));
        $id=DB::table('insumos')->where('nombre',$payload['nombre'])->value('id_insumo');
        $this->assertDatabaseHas('insumo_sedes',['id_insumo'=>$id,'id_sede'=>1,'stock'=>0]);
        $this->assertDatabaseMissing('insumo_sedes',['id_insumo'=>$id,'id_sede'=>2]);
        $this->post(route('inventory.ingredients.store'),$payload)->assertSessionHasErrors('nombre');
        $this->assertDatabaseCount('insumos',2);
        $this->post(route('inventory.assignments.store'),['id_insumo'=>1])->assertRedirect(route('warehouse.ingredients.index'));
        $this->assertDatabaseHas('insumo_sedes',['id_insumo'=>1,'id_sede'=>1,'stock'=>0]);
        $this->post(route('inventory.assignments.store'),['id_insumo'=>1])->assertSessionHasErrors('id_insumo');
        $this->assertDatabaseHas('insumo_sedes',['id_insumo'=>1,'id_sede'=>2,'stock'=>7]);
        $this->get(route('orders.create'))->assertOk()->assertSee('PRODUCTO AISLADO DE PRUEBA')->assertViewHas('ingredients',fn($i)=>$i->count()===2)->assertDontSee('Registrar o asignar insumos');
        $this->post(route('orders.store'),['id_proveedor'=>1,'fecha_entrega'=>now()->addDay()->toDateString(),'detalles'=>[['id_insumo'=>$id,'cantidad'=>'1.500']]])->assertRedirect();
        $this->assertDatabaseHas('detalle_pedidos',['id_insumo'=>$id,'cantidad'=>1.5]);
        $this->assertDatabaseHas('insumo_sedes',['id_insumo'=>$id,'id_sede'=>1,'stock'=>0]);
        $this->assertDatabaseCount('lotes',0);
        $this->assertDatabaseCount('inventarios_finales',0);
        $this->post('/inventario/movimientos',[])->assertForbidden();
        $this->post('/recepciones/pedidos/1/capturas',[])->assertForbidden();
        $receiver=$this->createUser('Recepcion de insumos',10,1);
        $this->actingAs($receiver)->withSession(['active_role_id'=>10])->get(route('warehouse.ingredients.index'))->assertForbidden();
        $this->post(route('inventory.ingredients.store'),$payload)->assertForbidden();
    }
}
