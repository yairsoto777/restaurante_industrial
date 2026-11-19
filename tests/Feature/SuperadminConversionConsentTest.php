<?php

namespace Tests\Feature;

use App\Mail\SuperadminActivationMail;
use App\Models\User;
use Illuminate\Support\Facades\{DB, Mail};
use Tests\TestCase;

class SuperadminConversionConsentTest extends TestCase
{
    public function test_conversion_consent_and_duplicate_guards_with_isolated_data(): void
    {
        // No RefreshDatabase or migrations. Never connect to the operational database.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutVite();
        Mail::fake();
        $definitions = [
            'usuarios'=>'id_usuario INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, apellido_p TEXT, apellido_m TEXT, correo TEXT UNIQUE, password TEXT, temporary_password_expires_at TEXT, temporary_password_used_at TEXT, remember_token TEXT',
            'roles'=>'id_rol INTEGER PRIMARY KEY, nombre TEXT',
            'sedes'=>'id_sede INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, direccion TEXT, telefono TEXT, id_tipo INTEGER, id_cliente INTEGER',
            'usuario_sede'=>'id_usuario_sede INTEGER PRIMARY KEY AUTOINCREMENT, id_usuario INTEGER, id_sede INTEGER, id_rol INTEGER',
            'contactos'=>'id_contacto INTEGER PRIMARY KEY, nombre TEXT, apellido_p TEXT, apellido_m TEXT, empresa TEXT, correo TEXT, mensaje TEXT, estado TEXT, fecha_contacto TEXT, fecha_respuesta TEXT, id_superadmin_responde INTEGER, id_usuario_gerente INTEGER',
            'clientes'=>'id_cliente INTEGER PRIMARY KEY AUTOINCREMENT, id_usuario INTEGER, nombre TEXT, telefono TEXT, correo TEXT, activo INTEGER, fecha_registro TEXT, id_contacto INTEGER UNIQUE, razon_social TEXT, direccion TEXT, id_tipo INTEGER, logo_url TEXT',
            'tipo_sedes'=>'id_tipo INTEGER PRIMARY KEY, nombre TEXT',
            'password_reset_tokens'=>'email TEXT PRIMARY KEY, token TEXT, created_at TEXT',
        ];
        foreach ($definitions as $table=>$columns) DB::connection()->getPdo()->exec('CREATE TABLE '.$table.' ('.$columns.')');
        DB::table('roles')->insert([['id_rol'=>1,'nombre'=>'Superadministrador'],['id_rol'=>2,'nombre'=>'Gerente de sede'],['id_rol'=>3,'nombre'=>'Cajero']]);
        DB::table('usuarios')->insert([
            ['id_usuario'=>1,'nombre'=>'Administrador','apellido_p'=>'Prueba','correo'=>'admin@example.test','password'=>'unchanged'],
            ['id_usuario'=>2,'nombre'=>'Cuenta','apellido_p'=>'Existente','correo'=>'existing@example.test','password'=>'unchanged'],
        ]);
        DB::table('sedes')->insert(['id_sede'=>1,'nombre'=>'Sede de control','direccion'=>'Solo memoria']);
        DB::table('usuario_sede')->insert([['id_usuario'=>1,'id_sede'=>1,'id_rol'=>1],['id_usuario'=>2,'id_sede'=>1,'id_rol'=>3]]);
        foreach ([1,2,3] as $id) DB::table('contactos')->insert(['id_contacto'=>$id,'nombre'=>'Contacto '.$id,'correo'=>'contact'.$id.'@example.test','empresa'=>'Solicitud '.$id,'mensaje'=>'Texto original','estado'=>'contactado']);
        $untouched=(array)DB::table('contactos')->where('id_contacto',3)->first();
        $admin=User::findOrFail(1);
        $existingBefore=(array)DB::table('usuarios')->where('id_usuario',2)->first();
        $originalRole=(array)DB::table('usuario_sede')->where('id_usuario',2)->first();
        $origin=route('superadmin.index',['modulo'=>'solicitudes','seleccion'=>1]);
        $data=[
            '_sa_drawer'=>'contact-convert','empresa_nombre'=>'Empresa aislada nueva',
            'empresa_correo'=>'company@example.test','sede_nombre'=>'Primera sede aislada',
            'sede_direccion'=>'Dirección capturada','gerente_nombre'=>'Gerente',
            'gerente_apellido_p'=>'Prueba','gerente_correo'=>'new@example.test','confirmar'=>'1',
        ];
        $send=fn($id,$payload)=>$this->actingAs($admin)->from($origin)->post(route('admin.contacts.convert.store',$id),$payload);
        $noConfirmation=$data;
        unset($noConfirmation['confirmar']);
        $send(1,$noConfirmation)->assertRedirect($origin)->assertSessionHasErrors('confirmar')
            ->assertSessionDoesntHaveErrors(['vincular_existente'])
            ->assertSessionHasInput('sede_direccion',$data['sede_direccion'])
            ->assertSessionHasInput('gerente_correo',$data['gerente_correo'])
            ->assertSessionHasInput('_sa_drawer','contact-convert');
        $this->assertStringContainsString('Debes confirmar',session('errors')->first('confirmar'));
        $this->assertDatabaseCount('clientes',0);

        // New email: the omitted existing-account checkbox must not be required.
        $send(1,$data)->assertRedirect(route('superadmin.index',['modulo'=>'clientes']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('usuarios',['correo'=>'new@example.test']);
        $client=DB::table('clientes')->where('id_contacto',1)->first();
        $site=DB::table('sedes')->where('id_cliente',$client->id_cliente)->first();
        $this->assertDatabaseHas('usuario_sede',['id_usuario'=>$client->id_usuario,'id_sede'=>$site->id_sede,'id_rol'=>2]);
        $this->assertDatabaseHas('contactos',['id_contacto'=>1,'estado'=>'aprobado','id_usuario_gerente'=>$client->id_usuario]);
        Mail::assertSent(SuperadminActivationMail::class,1);
        $counts=['usuarios'=>3,'clientes'=>1,'sedes'=>2,'usuario_sede'=>3];
        $send(1,$data)->assertSessionHasErrors('conversion');
        foreach($counts as $table=>$count) $this->assertDatabaseCount($table,$count);

        // Existing email: explicit consent is required, and failing leaves no partial data.
        $existing=array_replace($data,['empresa_nombre'=>'Empresa aislada vinculada','gerente_correo'=>'existing@example.test']);
        $send(2,$existing)->assertRedirect($origin)->assertSessionHasErrors('vincular_existente')
            ->assertSessionHasInput('empresa_nombre',$existing['empresa_nombre']);
        $this->assertStringContainsString('Autoriza expresamente',session('errors')->first('vincular_existente'));
        foreach($counts as $table=>$count) $this->assertDatabaseCount($table,$count);
        $this->assertDatabaseHas('contactos',['id_contacto'=>2,'estado'=>'contactado']);
        $send(2,$existing+['vincular_existente'=>'1'])->assertRedirect(route('superadmin.index',['modulo'=>'clientes']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('usuarios',3);
        $this->assertSame($existingBefore,(array)DB::table('usuarios')->where('id_usuario',2)->first());
        $this->assertDatabaseHas('usuario_sede',$originalRole);
        $this->assertDatabaseHas('contactos',['id_contacto'=>2,'id_usuario_gerente'=>2,'estado'=>'aprobado']);
        Mail::assertSentCount(1);

        // A different request cannot duplicate the existing company.
        $send(3,$data)->assertSessionHasErrors('empresa_nombre');
        $this->assertSame($untouched,(array)DB::table('contactos')->where('id_contacto',3)->first());
        $this->assertDatabaseCount('clientes',2);
        $this->assertDatabaseCount('sedes',3);
        // Operational roles must not gain commercial conversion access.
        $this->actingAs(User::findOrFail(2))->post(route('admin.contacts.convert.store',3),$data)->assertForbidden();
        $this->assertSame($untouched,(array)DB::table('contactos')->where('id_contacto',3)->first());
    }
}
