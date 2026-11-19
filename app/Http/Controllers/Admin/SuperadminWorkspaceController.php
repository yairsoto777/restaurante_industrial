<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{CommercialContact,Role,Site,User};
use App\Support\{SuperadminWorkspace as W,SuperadminCommercialFlow as Flow,EmploymentRoles,ChefWorkspace};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema,Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule,ValidationException};

class SuperadminWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        W::authorize($request);$module=$request->query('modulo','panel');abort_unless(in_array($module,['panel','solicitudes','clientes','sedes','usuarios','configuracion']),404);
        $request->validate(['q'=>'nullable|string|max:120','estado'=>'nullable|string|max:40','desde'=>'nullable|date_format:Y-m-d','hasta'=>'nullable|date_format:Y-m-d','cliente'=>'nullable|integer','sede'=>'nullable|integer','rol'=>'nullable|integer','tipo'=>'nullable|integer','responsable'=>'nullable|integer','seleccion'=>'nullable|integer','page'=>'nullable|integer|min:1']);
        $contacts=CommercialContact::orderByDesc('fecha_contacto')->get();$sites=Site::with(['users.roles'])->orderBy('nombre')->get();$users=User::with(['roles','sites'])->orderBy('nombre')->get();$roles=Role::orderBy('nombre')->get();
        $clients=DB::table('clientes')->orderBy('nombre')->get();$types=Schema::hasTable('tipo_sedes')?DB::table('tipo_sedes')->orderBy('nombre')->get():collect();
        $managers=$users->filter(fn($u)=>$u->roles->contains(fn($r)=>EmploymentRoles::isManager($r->nombre)));
        foreach($clients as $c){$c->responsable=$users->firstWhere('id_usuario',$c->id_usuario);$c->sedes=$sites->filter(fn($s)=>isset($s->id_cliente)&&(int)$s->id_cliente===(int)$c->id_cliente);$c->tipo=$types->firstWhere('id_tipo',$c->id_tipo??null)?->nombre??'Sin tipo registrado';}
        $selection=$request->integer('seleccion');$selected=$module==='solicitudes'?($contacts->firstWhere('id_contacto',$selection)??$contacts->first()):null;
        $q=mb_strtolower(trim($request->query('q','')));$state=$request->query('estado','');
        $contactRows=$contacts->filter(fn($c)=>(!$q||str_contains(mb_strtolower($c->empresa.' '.$c->fullName().' '.$c->correo),$q))&&(!$state||$c->estado===$state)&&(!$request->filled('desde')||$c->fecha_contacto?->toDateString()>=$request->query('desde'))&&(!$request->filled('hasta')||$c->fecha_contacto?->toDateString()<=$request->query('hasta')));
        $clientRows=$clients->filter(fn($c)=>(!$q||str_contains(mb_strtolower($c->nombre.' '.$c->correo.' '.$c->responsable?->name),$q))&&(!$state||($state==='activo'?(bool)$c->activo:!(bool)$c->activo))&&(!$request->filled('tipo')||(int)($c->id_tipo??0)===$request->integer('tipo')));
        $siteRows=$sites->filter(fn($s)=>(!$request->filled('cliente')||(int)($s->id_cliente??0)===$request->integer('cliente'))&&(!$q||str_contains(mb_strtolower($s->nombre.' '.$s->direccion),$q)));
        $userRows=$users->filter(fn($u)=>(!$q||str_contains(mb_strtolower($u->name.' '.$u->correo),$q))&&(!$request->filled('sede')||$u->sites->contains('id_sede',$request->integer('sede')))&&(!$request->filled('rol')||$u->roles->contains('id_rol',$request->integer('rol')))&&(!$state||W::userState($u)===$state)&&(!$request->filled('cliente')||$u->sites->contains(fn($s)=>(int)($s->id_cliente??0)===$request->integer('cliente'))||$clients->contains(fn($c)=>(int)$c->id_usuario===(int)$u->id_usuario&&(int)$c->id_cliente===$request->integer('cliente'))));
        $link=fn($mod,$extra=[])=>route('superadmin.index',array_merge(['modulo'=>$mod],$extra));$tone=fn($s)=>W::tone($s);$date=fn($d)=>$d?\Carbon\Carbon::parse($d)->locale('es')->translatedFormat('d M Y · H:i'):'Sin fecha registrada';
        if ($request->filled('responsable')) $clientRows=$clientRows->where('id_usuario',$request->integer('responsable'));
        // Paginate the existing filtered collections without changing commercial records.
        $paginate=fn($rows)=>new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->values()->forPage(max(1,$request->integer('page',1)),10)->values(),$rows->count(),10,
            max(1,$request->integer('page',1)),['path'=>$request->url(),'query'=>$request->query()]);
        $rows=$paginate(match($module){'solicitudes'=>$contactRows,'clientes'=>$clientRows,'sedes'=>$siteRows,'usuarios'=>$userRows,default=>collect()});
        if($module==='solicitudes'&&!$selection) $selected=$rows->first();
        $selectedClient=$clients->firstWhere('id_cliente',$request->integer('cliente'));
        $months=collect(range(5,0))->map(fn($n)=>now()->startOfMonth()->subMonths($n));
        $chartData=[
            'labels'=>$months->map(fn($d)=>$d->locale('es')->translatedFormat('M Y'))->all(),
            'requests'=>$months->map(fn($d)=>$contacts->filter(fn($c)=>$c->fecha_contacto?->format('Y-m')===$d->format('Y-m'))->count())->all(),
            'growth'=>$months->map(fn($d)=>$clients->filter(fn($c)=>$c->fecha_registro&&\Carbon\Carbon::parse($c->fecha_registro)->lt($d->copy()->addMonth()))->count())->all(),
            'distribution'=>[$clients->where('activo',1)->count(),$clients->filter(fn($c)=>!$c->activo)->count()],
        ];
        return view('superadmin.workspace',compact('module','contacts','sites','users','roles','clients','types','managers','selected','contactRows','clientRows','siteRows','userRows','link','tone','date','rows','selectedClient','chartData'));
    }
    public function saveClient(Request $request,?int $client=null)
    {
        W::authorize($request);$data=$request->validate(['nombre'=>['required','string','max:100',Rule::unique('clientes','nombre')->ignore($client,'id_cliente')],'razon_social'=>'nullable|string|max:200','correo'=>'required|email|max:150','telefono'=>'nullable|string|max:30','direccion'=>'nullable|string|max:255','id_tipo'=>'nullable|integer|exists:tipo_sedes,id_tipo','id_usuario'=>'required|integer|exists:usuarios,id_usuario','activo'=>'required|boolean','logo'=>ChefWorkspace::mediaRules(true)]);
        $manager=User::findOrFail($data['id_usuario']);if(!$manager->roles()->get()->contains(fn($r)=>EmploymentRoles::isManager($r->nombre)))throw ValidationException::withMessages(['id_usuario'=>'Selecciona un gerente autorizado.']);
        ChefWorkspace::withMedia($request,['logo'=>'logo_url'],function($media)use($data,$client){unset($data['logo']);$data=array_merge($data,$media);if($client){abort_unless(DB::table('clientes')->where('id_cliente',$client)->exists(),404);DB::table('clientes')->where('id_cliente',$client)->update($data);}else{$data['fecha_registro']=now();DB::table('clientes')->insert($data);}});
        return back()->with('status','Información comercial guardada correctamente.');
    }
    public function saveSite(Request $request,?int $site=null)
    {
        W::authorize($request);$data=$request->validate(['nombre'=>'required|string|max:100','id_cliente'=>'required|integer|exists:clientes,id_cliente','direccion'=>'required|string|max:255','telefono'=>'nullable|string|max:20','capacidad'=>'nullable|integer|min:0','id_usuario'=>'nullable|integer|exists:usuarios,id_usuario','logo'=>ChefWorkspace::mediaRules(true)]);
        if(!empty($data['id_usuario'])&&!User::findOrFail($data['id_usuario'])->roles()->get()->contains(fn($r)=>EmploymentRoles::isManager($r->nombre)))throw ValidationException::withMessages(['id_usuario'=>'El responsable debe tener el rol Gerente de sede.']);
        ChefWorkspace::withMedia($request,['logo'=>'logo_url'],function($media)use($data,$site){DB::table('clientes')->where('id_cliente',$data['id_cliente'])->lockForUpdate()->first();if(DB::table('sedes')->where('id_cliente',$data['id_cliente'])->where('nombre',$data['nombre'])->when($site,fn($q)=>$q->where('id_sede','!=',$site))->exists())throw ValidationException::withMessages(['nombre'=>'Ya existe una sede con ese nombre para el cliente.']);$manager=$data['id_usuario']??null;unset($data['id_usuario'],$data['logo']);$values=array_merge($data,$media);if($site){abort_unless(DB::table('sedes')->where('id_sede',$site)->exists(),404);DB::table('sedes')->where('id_sede',$site)->update($values);}else{$site=DB::table('sedes')->insertGetId($values,'id_sede');}if($manager){$role=Role::all()->first(fn($r)=>EmploymentRoles::isManager($r->nombre));User::whereKey($manager)->lockForUpdate()->first();if(!DB::table('usuario_sede')->where('id_usuario',$manager)->where('id_sede',$site)->where('id_rol',$role->id_rol)->exists())DB::table('usuario_sede')->insert(['id_usuario'=>$manager,'id_sede'=>$site,'id_rol'=>$role->id_rol]);}});
        return back()->with('status','Sede guardada. Se conservaron los usuarios, roles e historial existentes.');
    }
    public function saveUser(Request $request,?int $user=null)
    {
        W::authorize($request);$target=$user?User::findOrFail($user):null;
        if($target&&!$target->roles()->get()->contains(fn($r)=>EmploymentRoles::isManager($r->nombre)||EmploymentRoles::isSuperAdministrator($r->nombre)||$r->nombre==='Administrador'))abort(403);
        $data=$request->validate(['nombre'=>'required|string|max:100','apellido_p'=>'required|string|max:100','apellido_m'=>'nullable|string|max:100','correo'=>['required','email','max:150',Rule::unique('usuarios','correo')->ignore($user,'id_usuario')]]);
        if($target){$target->update($data);return back()->with('status','Perfil actualizado sin modificar sus roles ni asignaciones.');}
        $role=Role::all()->first(fn($r)=>EmploymentRoles::isManager($r->nombre));abort_unless($role,422);
        $target=DB::transaction(function()use($data,$role){$u=User::create(array_merge($data,['password'=>Hash::make(Str::random(64)),'temporary_password_expires_at'=>now()->addMinutes((int)config('auth.passwords.users.expire',60))]));DB::table('usuario_sede')->insert(['id_usuario'=>$u->id_usuario,'id_sede'=>null,'id_rol'=>$role->id_rol]);return $u;});
        try{Flow::activation($target);}catch(\Throwable $error){report($error);return back()->with('status','Cuenta creada.')->withErrors(['activacion'=>'No fue posible enviar el enlace. Reintenta la activación.']);}
        return back()->with('status','Gerente registrado. Se envió un enlace para definir su contraseña.');
    }
    public function activation(Request $request,int $user)
    {
        W::authorize($request);$target=User::findOrFail($user);if(!$target->roles()->get()->contains(fn($r)=>EmploymentRoles::isManager($r->nombre)||EmploymentRoles::isSuperAdministrator($r->nombre)||$r->nombre==='Administrador'))abort(403);
        $data=$request->validate([
            'metodo'=>['sometimes',Rule::in(['enlace','temporal'])],
            'generar'=>['sometimes','boolean'],
            'password'=>[Rule::requiredIf($request->input('metodo')==='temporal'&&!$request->boolean('generar')),'nullable','string','min:12','max:72',
                function($attribute,$value,$fail){if(is_string($value)&&strlen($value)>72)$fail('La contraseña temporal no debe superar 72 bytes.');}],
        ],[
            'metodo.in'=>'Selecciona un método de activación válido.',
            'generar.boolean'=>'La opción para generar la contraseña no es válida.',
            'password.required'=>'Ingresa una contraseña temporal o genera una automáticamente.',
            'password.string'=>'La contraseña temporal debe ser texto.',
            'password.min'=>'La contraseña temporal debe tener al menos 12 caracteres.',
            'password.max'=>'La contraseña temporal no debe superar 72 caracteres.',
        ]);
        if(($data['metodo']??'enlace')==='temporal'){
            $temporary=$request->boolean('generar')?Str::password(20):$data['password'];
            DB::transaction(function()use($target,$temporary){
                $locked=User::whereKey($target->getKey())->lockForUpdate()->firstOrFail();
                $locked->password=Hash::make($temporary);
                $locked->temporary_password_expires_at=now()->addMinutes(30);
                $locked->temporary_password_used_at=null;
                $locked->remember_token=Str::random(60);
                $locked->save();
                \Illuminate\Support\Facades\Password::broker()->deleteToken($locked);
            });
            // Direct no-store response: the cleartext is never flashed or persisted.
            return response()->view('superadmin.activation-result',['target'=>$target,'temporary'=>$temporary])
                ->header('Cache-Control','no-store, no-cache, must-revalidate, private, max-age=0');
        }
        try{Flow::activation($target);}catch(\Throwable $e){report($e);throw ValidationException::withMessages(['activacion'=>'No se pudo enviar el enlace de acceso.']);}return back()->with('status','Enlace de acceso enviado; no se enviaron contraseñas.');
    }
    public function notifications(Request $request)
    {
        W::authorize($request);$items=CommercialContact::whereIn('estado',['nuevo','contactado'])->orderByDesc('fecha_contacto')->get()->map(fn($c)=>['id'=>'solicitud-'.$c->id_contacto,'title'=>$c->empresa?:$c->fullName(),'description'=>$c->statusLabel().' · Solicitud comercial','url'=>route('superadmin.index',['modulo'=>'solicitudes','seleccion'=>$c->id_contacto]),'tone'=>'waiting','date'=>$c->fecha_contacto?->format('d/m/Y H:i')??'Sin fecha','read'=>in_array('solicitud-'.$c->id_contacto,$request->session()->get('sa_reads',[]),true)])->values();
        $pending=User::whereNotNull('temporary_password_expires_at')->whereNull('temporary_password_used_at')->get();foreach($pending as $u)if($u->roles()->get()->contains(fn($r)=>EmploymentRoles::isManager($r->nombre)))$items->push(['id'=>'acceso-'.$u->id_usuario,'title'=>'Activación pendiente','description'=>$u->name,'url'=>route('superadmin.index',['modulo'=>'usuarios','q'=>$u->correo]),'tone'=>'waiting','date'=>'Pendiente','read'=>in_array('acceso-'.$u->id_usuario,$request->session()->get('sa_reads',[]),true)]);
        return response()->json(['items'=>$items,'unread'=>$items->where('read',false)->count()]);
    }
    public function readNotifications(Request $request){W::authorize($request);$data=$request->validate(['id'=>'required|string|max:100']);$available=array_column($this->notifications($request)->getData(true)['items'],'id');$ids=$request->session()->get('sa_reads',[]);if($data['id']==='all')$ids=array_merge($ids,$available);elseif(in_array($data['id'],$available,true))$ids[]=$data['id'];$request->session()->put('sa_reads',array_slice(array_unique($ids),-500));return $this->notifications($request);}
}
