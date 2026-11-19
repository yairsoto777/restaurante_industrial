<?php
namespace App\Support;
use App\Models\{CommercialContact,Role,User};
use App\Mail\SuperadminActivationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash,Mail,Password};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SuperadminCommercialFlow
{
    public static function activation(User $user):void
    {
        if($user->mustChangePassword()){$user->temporary_password_expires_at=now()->addMinutes((int)config('auth.passwords.users.expire',60));$user->save();}
        $token=Password::broker()->createToken($user);
        Mail::to($user->correo)->send(new SuperadminActivationMail($user->name,route('password.activation',['token'=>$token,'correo'=>$user->correo]),(int)config('auth.passwords.users.expire',60)));
    }
    public static function convert(Request $request,CommercialContact $contact)
    {
        SuperadminWorkspace::authorize($request);
        if(!SuperadminWorkspace::ready())throw ValidationException::withMessages(['conversion'=>'Faltan los vínculos comerciales autorizados.']);
        $data=$request->validate(['empresa_nombre'=>'required|string|max:100','razon_social'=>'nullable|string|max:200','empresa_correo'=>'required|email|max:150','empresa_telefono'=>'nullable|string|max:30','empresa_direccion'=>'nullable|string|max:255','id_tipo'=>'nullable|integer|exists:tipo_sedes,id_tipo','sede_nombre'=>'required|string|max:100','sede_direccion'=>'required|string|max:255','sede_telefono'=>'nullable|string|max:20','gerente_nombre'=>'required|string|max:100','gerente_apellido_p'=>'required|string|max:100','gerente_apellido_m'=>'nullable|string|max:100','gerente_correo'=>'required|email|max:150','vincular_existente'=>'sometimes|accepted','confirmar'=>'accepted'],[
            'required'=>'El campo :attribute es obligatorio.',
            'string'=>'El campo :attribute debe ser texto.',
            'max'=>'El campo :attribute no debe superar :max caracteres.',
            'email'=>'El campo :attribute debe contener un correo válido.',
            'integer'=>'El campo :attribute debe ser un número entero.',
            'exists'=>'El valor seleccionado para :attribute no es válido.',
            'vincular_existente.accepted'=>'Debes autorizar expresamente la vinculación de la cuenta existente.',
            'confirmar.accepted'=>'Debes confirmar los datos y la conversión de esta solicitud.',
        ],[
            'empresa_nombre'=>'nombre de empresa','razon_social'=>'razón social','empresa_correo'=>'correo comercial',
            'empresa_telefono'=>'teléfono comercial','empresa_direccion'=>'dirección comercial','id_tipo'=>'tipo de establecimiento',
            'sede_nombre'=>'nombre de sede','sede_direccion'=>'ubicación de sede','sede_telefono'=>'teléfono de sede',
            'gerente_nombre'=>'nombre del gerente','gerente_apellido_p'=>'apellido paterno del gerente',
            'gerente_apellido_m'=>'apellido materno del gerente','gerente_correo'=>'correo del gerente',
        ]);
        $data['gerente_correo']=Str::lower(trim($data['gerente_correo']));
        $new=false;
        $manager=DB::transaction(function()use($data,$request,$contact,&$new){
            $locked=CommercialContact::whereKey($contact->getKey())->lockForUpdate()->firstOrFail();
            if(!in_array($locked->estado,[CommercialContact::STATUS_NEW,CommercialContact::STATUS_CONTACTED],true)||DB::table('clientes')->where('id_contacto',$locked->id_contacto)->exists())throw ValidationException::withMessages(['conversion'=>'La solicitud ya fue procesada o no admite conversión.']);
            $role=Role::all()->first(fn($r)=>EmploymentRoles::isManager($r->nombre));
            if(!$role)throw ValidationException::withMessages(['conversion'=>'Falta el rol Gerente de sede.']);
            // Serializes commercial registrations without deleting or replacing existing records.
            DB::table('roles')->where('id_rol',$role->id_rol)->lockForUpdate()->first();
            if(DB::table('clientes')->whereRaw('LOWER(nombre) = ?',[mb_strtolower(trim($data['empresa_nombre']))])->exists())throw ValidationException::withMessages(['empresa_nombre'=>'La empresa ya está registrada. Utiliza Gestión de clientes y vincula su sede.']);
            $manager=User::where('correo',$data['gerente_correo'])->lockForUpdate()->first();
            if($manager&&!$request->boolean('vincular_existente'))throw ValidationException::withMessages(['vincular_existente'=>'El correo ya tiene una cuenta. Autoriza expresamente su vinculación para conservarla sin duplicarla.']);
            if(!$manager){$new=true;$manager=User::create(['nombre'=>$data['gerente_nombre'],'apellido_p'=>$data['gerente_apellido_p'],'apellido_m'=>$data['gerente_apellido_m']??null,'correo'=>$data['gerente_correo'],'password'=>Hash::make(Str::random(64)),'temporary_password_expires_at'=>now()->addMinutes((int)config('auth.passwords.users.expire',60))]);}
            $client=DB::table('clientes')->insertGetId(['id_usuario'=>$manager->id_usuario,'nombre'=>trim($data['empresa_nombre']),'telefono'=>$data['empresa_telefono']??null,'correo'=>mb_strtolower(trim($data['empresa_correo'])),'activo'=>1,'fecha_registro'=>now(),'id_contacto'=>$locked->id_contacto,'razon_social'=>$data['razon_social']??null,'direccion'=>$data['empresa_direccion']??null,'id_tipo'=>$data['id_tipo']??null],'id_cliente');
            $site=DB::table('sedes')->insertGetId(['nombre'=>$data['sede_nombre'],'direccion'=>$data['sede_direccion'],'telefono'=>$data['sede_telefono']??null,'id_tipo'=>$data['id_tipo']??null,'id_cliente'=>$client],'id_sede');
            DB::table('usuario_sede')->insert(['id_usuario'=>$manager->id_usuario,'id_sede'=>$site,'id_rol'=>$role->id_rol]);
            $locked->estado=CommercialContact::STATUS_APPROVED;$locked->fecha_respuesta??=now();$locked->id_superadmin_responde=$request->user()->id_usuario;$locked->id_usuario_gerente=$manager->id_usuario;$locked->save();return $manager;
        },3);
        if($new){try{self::activation($manager);}catch(\Throwable $error){report($error);return back()->with('status','Cliente, sede y cuenta registrados.')->withErrors(['activacion'=>'No se pudo enviar el enlace. Reintenta desde Usuarios del sistema.']);}}
        return redirect()->route('superadmin.index',['modulo'=>'clientes'])->with('status','Solicitud convertida en cliente, con sede y gerente vinculados. '.($new?'Se envió un enlace de activación.':'Se conservó la cuenta existente y sus demás roles.'));
    }
}
