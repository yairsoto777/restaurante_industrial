<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Support\{SiteContext,WarehouseWorkspace as W};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\ValidationException;

class WarehouseIngredientController extends Controller
{
    public function index(Request $request, SiteContext $context)
    {
        $site=W::site($request,$context);
        $fields=$this->fields();
        $units=DB::table('unidades')->orderBy('nombre')->get();
        $base=DB::table('insumos as i')->leftJoin('unidades as u','u.id_unidad','=','i.id_unidad_base');
        $rows=(clone $base)->join('insumo_sedes as s','s.id_insumo','=','i.id_insumo')->where('s.id_sede',$site->id_sede)
            ->select('i.*','u.nombre as unidad','u.codigo','s.stock')->orderBy('i.nombre')->get();
        $unassigned=(clone $base)->whereNotExists(fn($q)=>$q->selectRaw('1')->from('insumo_sedes as s')->whereColumn('s.id_insumo','i.id_insumo')->where('s.id_sede',$site->id_sede))
            ->whereNotNull('u.id_unidad')->select('i.id_insumo','i.nombre','u.nombre as unidad')->orderBy('i.nombre')->get();
        $categories=$fields['categoria']?DB::table('insumos')->whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria'):collect();
        $metrics=[['Insumos asignados',$rows->count(),'Catálogo de esta sede','box','info'],['Con unidad válida',$rows->whereNotNull('unidad')->count(),'Disponibles para pedidos','check','ok'],['Sin existencias',$rows->filter(fn($r)=>$r->stock!==null&&(float)$r->stock===0.0)->count(),'Pendientes de abastecimiento','warehouse','waiting']];
        return view('almacen.workspace.ingredients',compact('site','fields','units','rows','unassigned','categories','metrics'));
    }

    public function store(Request $request, SiteContext $context)
    {
        $site=W::site($request,$context);$fields=$this->fields();
        $data=$request->validate([
            'nombre'=>'required|string|max:150','id_unidad_base'=>'required|integer|exists:unidades,id_unidad',
            'categoria'=>$fields['categoria']?'nullable|string|max:100':'prohibited',
            'descripcion'=>$fields['descripcion']?'nullable|string|max:1000':'prohibited',
            'activo'=>$fields['activo']?'required|boolean':'prohibited',
            'cantidad_inicial'=>'prohibited','stock'=>'prohibited',
        ],['required'=>'El campo :attribute es obligatorio.','nombre.max'=>'El nombre no debe superar 150 caracteres.','id_unidad_base.exists'=>'Selecciona una unidad del catálogo.','prohibited'=>'No se permite enviar :attribute desde el catálogo.'],['nombre'=>'nombre','id_unidad_base'=>'unidad de medida','cantidad_inicial'=>'existencia inicial','stock'=>'existencias']);
        DB::transaction(function()use($site,$data,$fields){
            DB::table('unidades')->where('id_unidad',$data['id_unidad_base'])->lockForUpdate()->first();
            $name=trim($data['nombre']);
            if($name==='')throw ValidationException::withMessages(['nombre'=>'Ingresa el nombre del insumo.']);
            if(DB::table('insumos')->whereRaw('LOWER(nombre) = LOWER(?)',[$name])->exists())throw ValidationException::withMessages(['nombre'=>'Este insumo ya existe. Utiliza «Asignar del catálogo» para vincularlo a tu sede sin duplicarlo.']);
            $values=['nombre'=>$name,'id_unidad_base'=>$data['id_unidad_base']];
            foreach($fields as $field=>$available)if($available)$values[$field]=$data[$field]??null;
            $id=DB::table('insumos')->insertGetId($values,'id_insumo');
            $this->attach($site->id_sede,$id);
        });
        return redirect()->route('warehouse.ingredients.index')->with('status','Insumo registrado y asignado a '.$site->nombre.' con existencia inicial en cero. Ya puedes incluirlo en un pedido.');
    }

    public function assign(Request $request, SiteContext $context)
    {
        $site=W::site($request,$context);
        $data=$request->validate(['id_insumo'=>'required|integer|exists:insumos,id_insumo','stock'=>'prohibited','cantidad_inicial'=>'prohibited']);
        DB::transaction(function()use($site,$data){
            $item=DB::table('insumos')->where('id_insumo',$data['id_insumo'])->lockForUpdate()->first();
            if(!$item||!DB::table('unidades')->where('id_unidad',$item->id_unidad_base)->exists())throw ValidationException::withMessages(['id_insumo'=>'El insumo necesita una unidad válida para asignarlo.']);
            if(DB::table('insumo_sedes')->where('id_sede',$site->id_sede)->where('id_insumo',$item->id_insumo)->exists())throw ValidationException::withMessages(['id_insumo'=>'El insumo ya está asignado a esta sede. Se conservó su existencia.']);
            $this->attach($site->id_sede,$item->id_insumo);
        });
        return redirect()->route('warehouse.ingredients.index')->with('status','Insumo asignado a esta sede con existencia en cero, sin crear entradas de inventario.');
    }

    private function attach(int $site,int $ingredient): void
    {
        DB::table('insumo_sedes')->insert(['id_sede'=>$site,'id_insumo'=>$ingredient,'stock'=>0,'punto_de_reorden'=>null]);
    }

    private function fields(): array
    {
        return collect(['categoria','descripcion','activo'])->mapWithKeys(fn($f)=>[$f=>Schema::hasColumn('insumos',$f)])->all();
    }
}
