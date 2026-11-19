<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class ChefWorkspaceTest extends RoleAccessTest
{
    private function recurrenceFixture(): array
    {
        $chef=$this->chefFixture(true);
        $this->assertSame('sqlite',DB::connection()->getDriverName());
        // The additive migration is exercised ONLY against this isolated in-memory fixture.
        (require database_path('migrations/2026_11_15_000009_create_service_schedules.php'))->up();
        $settings=['nombre'=>'Desayuno recurrente','descripcion'=>'Operación habitual','capacidad'=>120,'mode'=>'personalizado','desde'=>'2026-11-16','hasta'=>null,'days'=>[1,2,4,5],
            'start'=>'09:00','end'=>'11:00','hours'=>[1=>['start'=>'09:00','end'=>'11:00'],2=>['start'=>'08:00','end'=>'10:00'],4=>['start'=>'09:00','end'=>'11:00'],5=>['start'=>'08:00','end'=>'12:00']],'cover_url'=>'cocina/original.png'];
        return [$chef,$settings];
    }

    public function test_recurrence_approval_once_idempotence_custom_hours_and_historical_integrity(): void
    {
        [$chef,$settings]=$this->recurrenceFixture();$r=\App\Support\ServiceRecurrence::class;
        $before=DB::table('servicios')->orderBy('id_servicio')->get()->map(function($row){unset($row->schedule_id,$row->schedule_revision_id);return $row;})->toJson();
        $id=$r::submit(1,$chef->id_usuario,$settings);$revision=DB::table('service_schedule_revisions')->first();
        $r::ensure(1,'2026-11-16','2026-11-22');
        $this->assertSame(4,DB::table('servicios')->where('schedule_id',$id)->count());
        $this->assertDatabaseHas('servicios',['schedule_id'=>$id,'fecha'=>'2026-11-17 00:00:00','inicio_hora'=>'08:00','estado_aprobacion'=>'Pendiente']);
        $manager=$this->createUser('Gerente de sede',2,1);
        $this->actingAs($chef)->patch(route('manager.schedules.review',$revision->id),['decision'=>'approve'])->assertForbidden();
        $this->actingAs($manager)->withSession(['active_role_id'=>2,'current_site_id'=>1])->patch(route('manager.schedules.review',$revision->id),['decision'=>'approve'])->assertRedirect(route('manager.services.index'));
        $r::ensure(1,'2026-11-16','2026-11-22');$count=DB::table('servicios')->where('schedule_id',$id)->count();$r::ensure(1,'2026-11-16','2026-11-22');
        $this->assertSame($count,DB::table('servicios')->where('schedule_id',$id)->count());
        $this->assertSame(0,DB::table('servicios')->where('schedule_id',$id)->where('estado_aprobacion','!=','Aprobado')->count());
        $monday=\App\Models\Service::where('schedule_id',$id)->whereDate('fecha','2026-11-16')->first();$monday->update(['estado'=>'Finalizado']);
        $this->assertDatabaseHas('servicios',['schedule_id'=>$id,'fecha'=>'2026-11-17 00:00:00','estado'=>'Programado']);
        $this->assertSame($before,DB::table('servicios')->whereNull('schedule_id')->orderBy('id_servicio')->get()->map(function($row){unset($row->schedule_id,$row->schedule_revision_id);return $row;})->toJson());
        $this->assertSame(1,DB::table('service_schedule_revisions')->count());
        $this->assertDatabaseHas('comanda_detalles',['id_menu'=>1,'id_comanda'=>1]);
    }

    public function test_recurrence_pending_revision_exceptions_and_independent_menu_copy_are_scoped(): void
    {
        [$chef,$settings]=$this->recurrenceFixture();$r=\App\Support\ServiceRecurrence::class;$id=$r::submit(1,$chef->id_usuario,$settings);
        $manager=$this->createUser('Gerente de sede',2,1);$r::review(1,$manager->id_usuario,DB::table('service_schedule_revisions')->value('id'),true,null);$r::ensure(1,'2026-11-16','2026-11-22');
        $monday=\App\Models\Service::where('schedule_id',$id)->whereDate('fecha','2026-11-16')->first();$monday->update(['estado'=>'Finalizado']);
        $new=$settings;$new['capacidad']=80;$r::submit(1,$chef->id_usuario,$new,$id,'following','2026-11-17');
        $this->assertDatabaseHas('servicios',['schedule_id'=>$id,'fecha'=>'2026-11-17 00:00:00','capacidad'=>120]);
        $pending=DB::table('service_schedule_revisions')->latest('id')->first();$r::review(1,$manager->id_usuario,$pending->id,true,null);
        $this->assertDatabaseHas('servicios',['id_servicio'=>$monday->id_servicio,'capacidad'=>120,'estado'=>'Finalizado']);
        $this->assertDatabaseHas('servicios',['schedule_id'=>$id,'fecha'=>'2026-11-17 00:00:00','capacidad'=>80]);
        $exception=$new;$exception['suspended']=true;$exception['desde']='2026-11-19';$exception['hasta']='2026-11-19';$r::submit(1,$chef->id_usuario,$exception,$id,'one','2026-11-19');
        $r::review(1,$manager->id_usuario,DB::table('service_schedule_revisions')->latest('id')->value('id'),true,'Festivo');$r::ensure(1,'2026-11-16','2026-11-22');
        $this->assertDatabaseHas('servicios',['schedule_id'=>$id,'fecha'=>'2026-11-19 00:00:00','estado'=>'Cancelado']);
        $friday=\App\Models\Service::where('schedule_id',$id)->whereDate('fecha','2026-11-20')->first();$this->assertSame('Programado',$friday->estado);
        $this->actingAs($chef)->withSession(['active_role_id'=>12,'current_site_id'=>1])->post(route('chef.menus.copy',$friday),['source'=>1])->assertRedirect();
        $copy=DB::table('menus')->where('id_servicio',$friday->id_servicio)->first();$this->assertNotSame(1,$copy->id_menu);$this->assertSame(0,$copy->publicado);
        DB::table('menus')->where('id_menu',$copy->id_menu)->update(['precio_venta'=>99]);$this->assertDatabaseHas('menus',['id_menu'=>1,'precio_venta'=>55]);
        $this->post(route('chef.menus.copy',$friday),['source'=>3])->assertNotFound();
        $this->get(route('chef.schedules.edit',$id))->assertOk()->assertSee('Tipo de programación');
        $this->get(route('chef.services'))->assertOk()->assertSee('Desayuno recurrente');
        $this->get(route('chef.menus',['schedule'=>$id,'date'=>'2026-11-20']))->assertOk()->assertSee('Planificador de menús');
        $this->get(route('chef.generator',['schedule'=>$id,'desde'=>'2026-11-16','periodo'=>'semanal']))->assertOk();
    }

    public function test_recurrence_validates_custom_days_and_site_and_preserves_approved_menu_relations(): void
    {
        [$chef,$settings]=$this->recurrenceFixture();$r=\App\Support\ServiceRecurrence::class;
        $invalid=$settings;$invalid['hours'][2]['end']='07:00';
        $this->post(route('chef.schedules.store'),$invalid)->assertSessionHasErrors('hours');
        $this->post(route('chef.schedules.store'),$settings)->assertRedirect(route('chef.services'));
        $revision=DB::table('service_schedule_revisions')->first();$id=$revision->schedule_id;
        $foreign=$this->createUser('Gerente de sede',2,2);
        $this->actingAs($foreign)->withSession(['active_role_id'=>2,'current_site_id'=>2])->patch(route('manager.schedules.review',$revision->id),['decision'=>'approve'])->assertNotFound();
        $manager=$this->createUser('Gerente de sede',2,1);
        $this->actingAs($manager)->withSession(['active_role_id'=>2,'current_site_id'=>1])->patch(route('manager.schedules.review',$revision->id),['decision'=>'approve'])->assertRedirect();
        $this->get(route('manager.services.index'))->assertOk()->assertSee('Aprobado');
        $journey=\App\Models\Service::where('schedule_id',$id)->whereDate('fecha','2026-11-17')->first();
        DB::table('atenciones')->insert(['id_servicio'=>$journey->id_servicio]);
        $new=$settings;$new['capacidad']=20;$r::submit(1,$chef->id_usuario,$new,$id,'following','2026-11-17');$next=DB::table('service_schedule_revisions')->latest('id')->value('id');$r::review(1,$manager->id_usuario,$next,true,null);
        $this->assertDatabaseHas('servicios',['id_servicio'=>$journey->id_servicio,'capacidad'=>120]);
        $this->assertDatabaseHas('atenciones',['id_servicio'=>$journey->id_servicio]);
        $this->actingAs($chef)->withSession(['active_role_id'=>12,'current_site_id'=>1])->get(route('chef.schedules.show',$id))->assertOk();
        $this->put(route('chef.services.update',$journey),['nombre'=>'Cambio directo'])->assertUnprocessable();
    }

    private function chefFixture(bool $extended = false, bool $persistent = false)
    {
        $this->assertSame('sqlite',DB::connection()->getDriverName());
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        config(['database.connections'=>['sqlite'=>config('database.connections.sqlite')]]);
        if ($persistent) {
            // Clone only the isolated access fixture, never the configured real database.
            $directory=storage_path('framework/testing/chef-media-'.bin2hex(random_bytes(8)));
            mkdir($directory,0700,true);
            $path=$directory.'/database.sqlite';
            DB::statement('VACUUM INTO ?',[$path]);
            DB::purge('sqlite');
            config(['database.connections.sqlite.database'=>$path]);
        }
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-16 10:00:00','America/Mexico_City'));
        Schema::create('servicios',function (Blueprint $t) {
            $t->increments('id_servicio');$t->integer('id_sede');$t->string('nombre');$t->date('fecha');$t->time('inicio_hora');$t->time('fin_hora');$t->integer('capacidad');$t->string('estado');$t->string('estado_aprobacion');$t->integer('id_usuario_registro')->nullable();$t->integer('id_usuario_revision')->nullable();$t->dateTime('revisado_en')->nullable();$t->text('observaciones_revision')->nullable();
        });
        Schema::create('categorias',function (Blueprint $t) {$t->increments('id_categoria');$t->string('nombre');});
        Schema::create('platillos',function (Blueprint $t) {$t->increments('id_platillo');$t->integer('id_sede');$t->integer('id_categoria');$t->string('nombre');$t->text('descripcion')->nullable();$t->string('imagen_url')->nullable();});
        Schema::create('menus',function (Blueprint $t) {$t->increments('id_menu');$t->integer('id_servicio');$t->integer('id_platillo');$t->decimal('precio_venta',10,2)->nullable();$t->boolean('publicado');$t->boolean('disponible');});
        Schema::create('insumos',function (Blueprint $t) {$t->increments('id_insumo');$t->string('nombre');$t->integer('id_unidad_base');});
        Schema::create('unidades',function (Blueprint $t) {$t->increments('id_unidad');$t->string('nombre');$t->string('codigo');});
        Schema::create('insumo_sedes',function (Blueprint $t) {$t->increments('id_insumo_sede');$t->integer('id_sede');$t->integer('id_insumo');$t->decimal('stock',12,3)->nullable();$t->decimal('punto_de_reorden',12,3)->nullable();});
        Schema::create('receta_detalles',function (Blueprint $t) {$t->increments('id_receta_detalle');$t->integer('id_platillo');$t->integer('id_insumo');$t->decimal('cantidad_por_porcion',12,3);});
        Schema::create('mesas',function (Blueprint $t) {$t->increments('id_mesa');$t->integer('numero');});
        Schema::create('atenciones',function (Blueprint $t) {$t->increments('id_atencion');$t->integer('id_servicio');$t->integer('id_mesa')->nullable();});
        Schema::create('comandas',function (Blueprint $t) {$t->increments('id_comanda');$t->integer('id_atencion');$t->dateTime('fecha_apertura');$t->text('observaciones')->nullable();});
        Schema::create('comanda_detalles',function (Blueprint $t) {$t->increments('id_comanda_detalle');$t->integer('id_comanda');$t->integer('id_menu');$t->integer('cantidad');$t->string('estado');$t->text('observaciones')->nullable();});
        Schema::create('incidencias',function (Blueprint $t) {$t->increments('id_incidencia');$t->integer('id_sede');$t->integer('id_usuario');$t->integer('id_recepcion_detalle')->nullable();$t->dateTime('fecha');$t->text('descripcion');$t->string('estado')->nullable();$t->text('respuesta')->nullable();$t->dateTime('fecha_cierre')->nullable();});
        if ($extended) {
            Schema::table('servicios',function (Blueprint $t) {$t->text('cover_url')->nullable();$t->text('menu_cover_url')->nullable();$t->text('descripcion')->nullable();});
            Schema::table('platillos',function (Blueprint $t) {$t->decimal('precio_referencia',10,2)->nullable();$t->boolean('disponible')->nullable();});
            Schema::table('menus',fn (Blueprint $t)=>$t->boolean('especial_chef')->nullable());
            Schema::table('incidencias',function (Blueprint $t) {$t->integer('id_servicio')->nullable();$t->text('evidencia_url')->nullable();});
            Schema::create('kitchen_menu_designs',function (Blueprint $t) {$t->increments('id');$t->integer('id_sede');$t->integer('id_usuario');$t->text('settings');$t->text('logo_url')->nullable();$t->text('background_url')->nullable();$t->dateTime('updated_at')->nullable();$t->unique(['id_sede','id_usuario']);});
        }
        $user=$this->createUser('Jefe de cocina',12,1);
        DB::table('categorias')->insert(['id_categoria'=>1,'nombre'=>'Platos fuertes']);
        foreach ([1=>[1,'Desayuno norte','Aprobado'],2=>[1,'Comida pendiente','Pendiente'],3=>[2,'Servicio privado sur','Aprobado']] as $id=>[$site,$name,$approval]) {
            DB::table('servicios')->insert(['id_servicio'=>$id,'id_sede'=>$site,'nombre'=>$name,'fecha'=>'2026-11-16','inicio_hora'=>'08:00','fin_hora'=>'11:00','capacidad'=>100,'estado'=>'Programado','estado_aprobacion'=>$approval]);
        }
        foreach ([1=>[1,'Pollo del norte'],2=>[1,'Ensalada del norte'],3=>[2,'Platillo privado sur']] as $id=>[$site,$name]) DB::table('platillos')->insert(['id_platillo'=>$id,'id_sede'=>$site,'id_categoria'=>1,'nombre'=>$name,'descripcion'=>'Descripción de catálogo']);
        DB::table('menus')->insert([
            ['id_menu'=>1,'id_servicio'=>1,'id_platillo'=>1,'precio_venta'=>55,'publicado'=>1,'disponible'=>1],
            ['id_menu'=>2,'id_servicio'=>2,'id_platillo'=>2,'precio_venta'=>45,'publicado'=>0,'disponible'=>1],
            ['id_menu'=>3,'id_servicio'=>3,'id_platillo'=>3,'precio_venta'=>70,'publicado'=>1,'disponible'=>1],
        ]);
        DB::table('unidades')->insert(['id_unidad'=>1,'nombre'=>'Kilogramo','codigo'=>'kg']);
        DB::table('insumos')->insert(['id_insumo'=>1,'nombre'=>'Arroz','id_unidad_base'=>1]);
        DB::table('insumo_sedes')->insert(['id_sede'=>1,'id_insumo'=>1,'stock'=>5,'punto_de_reorden'=>10]);
        DB::table('receta_detalles')->insert(['id_platillo'=>1,'id_insumo'=>1,'cantidad_por_porcion'=>0.1]);
        DB::table('mesas')->insert(['id_mesa'=>1,'numero'=>8]);
        DB::table('atenciones')->insert(['id_atencion'=>1,'id_servicio'=>1,'id_mesa'=>1]);
        DB::table('comandas')->insert(['id_comanda'=>1,'id_atencion'=>1,'fecha_apertura'=>'2026-11-16 09:00:00']);
        DB::table('comanda_detalles')->insert(['id_comanda_detalle'=>1,'id_comanda'=>1,'id_menu'=>1,'cantidad'=>2,'estado'=>'pendiente','observaciones'=>'Sin picante']);
        DB::table('incidencias')->insert(['id_sede'=>1,'id_usuario'=>$user->id_usuario,'fecha'=>'2026-11-16 09:00:00','descripcion'=>'Retraso en preparación','estado'=>'Pendiente']);
        $this->actingAs($user)->withSession(['current_site_id'=>1,'active_role_id'=>12]);
        Storage::fake('public');
        return $user;
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aT1kAAAAASUVORK5CYII='));
    }

    public function test_chef_workspace_all_six_media_fields_persist_on_disk_after_reconnection(): void
    {
        $user=$this->chefFixture(false,true);
        // Exactly the media schema under review, not the optional metadata migrations.
        Schema::table('servicios',function (Blueprint $t) {$t->text('cover_url')->nullable();$t->text('menu_cover_url')->nullable();});
        Schema::table('incidencias',fn (Blueprint $t)=>$t->text('evidencia_url')->nullable());
        Schema::create('kitchen_menu_designs',function (Blueprint $t) {$t->bigIncrements('id');$t->integer('id_sede');$t->integer('id_usuario');$t->text('settings');$t->text('logo_url')->nullable();$t->text('background_url')->nullable();$t->dateTime('updated_at')->nullable();$t->unique(['id_sede','id_usuario']);});
        $database=DB::connection()->getDatabaseName();
        $root=dirname($database).'/public';
        Storage::forgetDisk('public');
        config(['filesystems.disks.public.root'=>$root,'filesystems.disks.public.throw'=>true]);

        $serviceData=['nombre'=>'Servicio de persistencia','fecha'=>'2026-11-18','inicio_hora'=>'08:30','fin_hora'=>'10:30','capacidad'=>30];
        $this->post(route('chef.services.store'),$serviceData+['service_cover'=>$this->photo()])->assertRedirect();
        $service=DB::table('servicios')->where('nombre',$serviceData['nombre'])->first();
        $this->put(route('chef.services.update',$service->id_servicio),$serviceData+['service_cover'=>$this->photo()])->assertRedirect();
        $serviceCover=DB::table('servicios')->where('id_servicio',$service->id_servicio)->value('cover_url');
        $this->assertNotSame($service->cover_url,$serviceCover);
        $this->put(route('chef.services.update',$service->id_servicio),$serviceData)->assertRedirect();
        $this->assertDatabaseHas('servicios',['id_servicio'=>$service->id_servicio,'cover_url'=>$serviceCover]);
        Storage::disk('public')->assertExists($service->cover_url);

        $menuData=['platillos'=>[1,2],'disponible'=>[1=>1,2=>1],'publicado'=>[1=>0,2=>0],'precio_venta'=>[1=>50,2=>45]];
        $this->post(route('chef.menus.store',$service->id_servicio),$menuData+['menu_cover'=>$this->photo()])->assertRedirect();
        $oldMenuCover=DB::table('servicios')->where('id_servicio',$service->id_servicio)->value('menu_cover_url');
        $this->post(route('chef.menus.store',$service->id_servicio),$menuData+['menu_cover'=>$this->photo()])->assertRedirect();
        $menuCover=DB::table('servicios')->where('id_servicio',$service->id_servicio)->value('menu_cover_url');
        $this->assertNotSame($oldMenuCover,$menuCover);
        $this->post(route('chef.menus.store',$service->id_servicio),$menuData)->assertRedirect();
        $this->assertDatabaseHas('servicios',['id_servicio'=>$service->id_servicio,'cover_url'=>$serviceCover,'menu_cover_url'=>$menuCover]);
        Storage::disk('public')->assertExists($oldMenuCover);

        $dishData=['nombre'=>'Platillo de persistencia','id_categoria'=>1];
        $this->put(route('chef.dishes.update',1),$dishData+['dish_photo'=>$this->photo()])->assertRedirect();
        $oldDishPhoto=DB::table('platillos')->where('id_platillo',1)->value('imagen_url');
        $this->put(route('chef.dishes.update',1),$dishData+['dish_photo'=>$this->photo()])->assertRedirect();
        $dishPhoto=DB::table('platillos')->where('id_platillo',1)->value('imagen_url');
        $this->assertNotSame($oldDishPhoto,$dishPhoto);
        $this->put(route('chef.dishes.update',1),$dishData)->assertRedirect();
        $this->assertDatabaseHas('platillos',['id_platillo'=>1,'imagen_url'=>$dishPhoto]);
        Storage::disk('public')->assertExists($oldDishPhoto);

        $this->post(route('chef.incidents.store'),['descripcion'=>'Evidencia de persistencia','evidence'=>$this->photo()])->assertRedirect();
        $incident=DB::table('incidencias')->where('descripcion','Evidencia de persistencia')->first();
        $this->put(route('chef.incidents.evidence',$incident->id_incidencia),['evidence'=>$this->photo()])->assertRedirect();
        $evidence=DB::table('incidencias')->where('id_incidencia',$incident->id_incidencia)->value('evidencia_url');
        $this->assertNotSame($incident->evidencia_url,$evidence);
        $this->put(route('chef.incidents.evidence',$incident->id_incidencia),[])->assertRedirect();
        $this->assertDatabaseHas('incidencias',['id_incidencia'=>$incident->id_incidencia,'evidencia_url'=>$evidence,'estado'=>'Pendiente']);
        Storage::disk('public')->assertExists($incident->evidencia_url);

        $designData=['desde'=>'2026-11-18','periodo'=>'diario','template'=>'gastronomica','titulo'=>'Diseño permanente','comedor'=>'Comedor de prueba','color'=>'#17375e','fotos'=>1];
        $this->post(route('chef.generator.save'),$designData+['design_logo'=>$this->photo(),'design_background'=>$this->photo()])->assertRedirect();
        $design=DB::table('kitchen_menu_designs')->first();
        $this->post(route('chef.generator.save'),['design_logo'=>$this->photo()])->assertRedirect();
        $logo=DB::table('kitchen_menu_designs')->value('logo_url');
        $this->assertNotSame($design->logo_url,$logo);
        $this->assertDatabaseHas('kitchen_menu_designs',['id'=>$design->id,'logo_url'=>$logo,'background_url'=>$design->background_url]);
        $this->post(route('chef.generator.save'),['design_background'=>$this->photo()])->assertRedirect();
        $background=DB::table('kitchen_menu_designs')->value('background_url');
        $this->assertNotSame($design->background_url,$background);
        $this->assertDatabaseHas('kitchen_menu_designs',['id'=>$design->id,'logo_url'=>$logo,'background_url'=>$background]);
        $this->post(route('chef.generator.save'),[])->assertRedirect();
        $this->assertDatabaseHas('kitchen_menu_designs',['id'=>$design->id,'logo_url'=>$logo,'background_url'=>$background]);
        Storage::disk('public')->assertExists([$design->logo_url,$design->background_url]);
        $settings=DB::table('kitchen_menu_designs')->value('settings');

        $paths=[$serviceCover,$dishPhoto,$menuCover,$logo,$background,$evidence];
        $this->assertCount(6,array_unique($paths));
        $hashes=[];
        foreach($paths as $path) {
            $physical=Storage::disk('public')->path($path);
            $this->assertTrue(is_file($physical));
            $this->assertStringStartsWith(str_replace('\\','/',realpath($root)),str_replace('\\','/',realpath($physical)));
            $hashes[$path]=hash_file('sha256',$physical);
        }
        $oldPdo=DB::connection()->getPdo();
        $this->post('/logout')->assertRedirect();
        DB::purge('sqlite');Storage::forgetDisk('public');
        $this->assertNotSame($oldPdo,DB::connection()->getPdo());
        $this->assertSame($database,DB::connection()->getDatabaseName());
        $this->actingAs($user)->withSession(['current_site_id'=>1,'active_role_id'=>12]);
        $this->assertDatabaseHas('servicios',['id_servicio'=>$service->id_servicio,'cover_url'=>$serviceCover,'menu_cover_url'=>$menuCover]);
        $this->assertDatabaseHas('platillos',['id_platillo'=>1,'imagen_url'=>$dishPhoto]);
        $this->assertDatabaseHas('incidencias',['id_incidencia'=>$incident->id_incidencia,'evidencia_url'=>$evidence]);
        $this->assertDatabaseHas('kitchen_menu_designs',['id'=>$design->id,'logo_url'=>$logo,'background_url'=>$background,'settings'=>$settings]);
        foreach($paths as $path)$this->assertSame($hashes[$path],hash_file('sha256',Storage::disk('public')->path($path)));
        foreach ([['chef.services',[],$serviceCover],['chef.dishes',[],$dishPhoto],['chef.menus',['service'=>$service->id_servicio],$menuCover],['chef.generator',[],$logo],['chef.generator',[],$background],['chef.incidents',[],$evidence]] as [$route,$query,$path]) {
            $response=$this->get(route($route,$query))->assertOk()->assertSee(asset('storage/'.$path),false);
            $this->assertStringNotContainsString('class="cw-media-pending"',$response->getContent());
        }
        $this->get(route('chef.generator'))->assertSee('Diseño permanente')->assertSee('cw-template-gastronomica',false);
        $this->assertDatabaseCount('receta_detalles',1);
        $this->assertDatabaseCount('kitchen_menu_designs',1);
    }

    public function test_chef_workspace_circular_clock_preserves_form_values_and_server_validation(): void
    {
        $this->chefFixture();
        $this->get(route('chef.services.create'))->assertOk()->assertSee('data-cw-time-field',false)
            ->assertSee('data-cw-time-dial',false)->assertSee('Hora de inicio')->assertSee('Hora de finalización');
        $response=$this->get(route('chef.services.edit',2))->assertOk();
        $html=$response->getContent();
        $this->assertSame(1,substr_count($html,'id="cw-time-dialog"'));
        $this->assertStringContainsString('name="inicio_hora" value="08:00"',$html);
        $this->assertStringContainsString('name="fin_hora" value="11:00"',$html);
        $payload=['nombre'=>'Horario revisado','fecha'=>'2026-11-17','capacidad'=>20,'inicio_hora'=>'13:45','fin_hora'=>'13:30'];
        $this->put(route('chef.services.update',2),$payload)->assertSessionHasErrors('fin_hora');
        $this->put(route('chef.services.update',2),array_merge($payload,['fin_hora'=>'13:45']))->assertSessionHasErrors('fin_hora');
        $this->post(route('chef.services.store'),array_merge($payload,['fin_hora'=>'14:30']))->assertRedirect();
        $this->assertDatabaseHas('servicios',['nombre'=>'Horario revisado','inicio_hora'=>'13:45','fin_hora'=>'14:30','estado_aprobacion'=>'Pendiente']);
        // Isolated fixture for the single browser interaction check; no real session or data.
        $directory=storage_path('app/private/chef-clock-preview');
        if(!is_dir($directory))mkdir($directory,0777,true);
        $html=str_replace('http://localhost/','file:///'.str_replace('\\','/',public_path()).'/',$html);
        file_put_contents($directory.'/clock.html',$html);
    }

    public function test_chef_workspace_eight_views_render_scoped_data_and_exclusive_navigation(): void
    {
        $this->chefFixture();
        foreach (['dashboard','chef.services','chef.dishes','chef.menus','chef.generator','chef.commands','chef.stock','chef.incidents','chef.services.create','chef.dishes.create'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('chef-workspace')->assertSee('Generador de menús')
                ->assertDontSee('Servicio privado sur')->assertDontSee('Platillo privado sur')
                ->assertDontSee('href="'.route('orders.suppliers.index').'"',false)->assertDontSee('href="'.route('manager.employees.index').'"',false);
        }
        $this->get(route('chef.dishes.show',1))->assertOk()->assertSee('0.1 kg');
        $this->get(route('chef.services.edit',2))->assertOk();
        $this->get(route('chef.commands'))->assertSee('Sin picante')->assertSee('Mesa 8');
        $this->get(route('chef.stock'))->assertSee('5 kg')->assertDontSee('Ajustar existencia');
        $this->get('/inventario')->assertRedirect(route('chef.stock'));
    }

    public function test_chef_workspace_denies_inventory_administration_approval_other_roles_and_foreign_ids(): void
    {
        $user=$this->chefFixture();
        foreach (['/pedidos','/pedidos/proveedores','/caja','/reportes','/operacion/empleados','/almacen/alertas'] as $url) $this->get($url)->assertForbidden();
        $this->post('/inventario/movimientos',[])->assertForbidden();
        $this->post('/inventario/catalogo',[])->assertForbidden();
        $this->get(route('chef.dishes.show',3))->assertNotFound();
        $this->get(route('chef.services.edit',3))->assertNotFound();
        $this->get(route('chef.menus',['service'=>3]))->assertNotFound();
        $this->post(route('chef.menus.store',3),[])->assertNotFound();
        DB::table('roles')->insert(['id_rol'=>9,'nombre'=>'Gerente de sede']);
        DB::table('usuario_sede')->insert(['id_usuario'=>$user->id_usuario,'id_sede'=>2,'id_rol'=>9]);
        $this->withSession(['current_site_id'=>2,'active_role_id'=>9])->get(route('chef.services'))->assertForbidden();
    }

    public function test_chef_workspace_service_approval_and_menu_publication_rules_are_preserved(): void
    {
        $this->chefFixture();
        $payload=['nombre'=>'Cena nueva','fecha'=>'2026-11-18','inicio_hora'=>'18:00','fin_hora'=>'20:00','capacidad'=>80,'estado'=>'En curso','estado_aprobacion'=>'Aprobado'];
        $this->post(route('chef.services.store'),$payload)->assertRedirect();
        $this->assertDatabaseHas('servicios',['nombre'=>'Cena nueva','id_sede'=>1,'estado'=>'Programado','estado_aprobacion'=>'Pendiente']);
        $this->post(route('chef.menus.store',2),['platillos'=>[1,2],'disponible'=>[1=>1,2=>1],'publicado'=>[1=>1,2=>1]])->assertRedirect();
        $this->assertSame(0,DB::table('menus')->where('id_servicio',2)->sum('publicado'));
        $this->assertDatabaseCount('platillos',3);
        $this->post(route('chef.menus.store',2),['platillos'=>[3]])->assertSessionHasErrors('platillos');
        $this->patch(route('kitchen.services.status',2),['estado'=>'En curso'])->assertStatus(422);
        $this->patch(route('kitchen.services.status',1),['estado'=>'En curso'])->assertRedirect();
        $this->assertDatabaseHas('servicios',['id_servicio'=>1,'estado'=>'En curso']);
        $this->put(route('chef.services.update',1),$payload)->assertStatus(422);
        $this->post(route('chef.notifications.read'),['id'=>'all'])->assertOk()->assertJsonPath('unread',0);
    }

    public function test_chef_workspace_images_persist_independently_and_invalid_files_do_not_change_records(): void
    {
        $user=$this->chefFixture(true);
        $payload=['nombre'=>'Cena con foto','fecha'=>'2026-11-18','inicio_hora'=>'18:00','fin_hora'=>'20:00','capacidad'=>80];
        $this->post(route('chef.services.store'),$payload+['service_cover'=>$this->photo()])->assertRedirect();
        $service=DB::table('servicios')->where('nombre','Cena con foto')->first();
        Storage::disk('public')->assertExists($service->cover_url);
        $this->post(route('chef.menus.store',$service->id_servicio),['platillos'=>[1,2],'disponible'=>[1=>1,2=>1],'menu_cover'=>$this->photo(),'especial_chef'=>[1=>1]])->assertRedirect();
        $menuCover=DB::table('servicios')->where('id_servicio',$service->id_servicio)->value('menu_cover_url');
        $this->assertNotSame($service->cover_url,$menuCover);Storage::disk('public')->assertExists($menuCover);
        $this->put(route('chef.dishes.update',1),['nombre'=>'Pollo actualizado','id_categoria'=>1,'dish_photo'=>$this->photo()])->assertRedirect();
        $photo=DB::table('platillos')->where('id_platillo',1)->value('imagen_url');Storage::disk('public')->assertExists($photo);
        $this->assertDatabaseCount('receta_detalles',1);
        $oldPhoto=$photo;
        $this->put(route('chef.dishes.update',1),['nombre'=>'Pollo actualizado','id_categoria'=>1,'dish_photo'=>$this->photo()])->assertRedirect();
        $photo=DB::table('platillos')->where('id_platillo',1)->value('imagen_url');
        $this->assertNotSame($oldPhoto,$photo);Storage::disk('public')->assertExists([$oldPhoto,$photo]);
        $this->put(route('chef.dishes.update',1),['nombre'=>'Pollo actualizado','id_categoria'=>1])->assertRedirect();
        $this->assertDatabaseHas('platillos',['id_platillo'=>1,'imagen_url'=>$photo]);
        $this->put(route('chef.dishes.update',1),['nombre'=>'Inválido','id_categoria'=>1,'dish_photo'=>$this->photo()->size(4097)])->assertSessionHasErrors('dish_photo');
        $this->post(route('chef.incidents.store'),['descripcion'=>'Calidad revisada','id_servicio'=>1,'evidence'=>$this->photo()])->assertRedirect();
        $evidence=DB::table('incidencias')->where('descripcion','Calidad revisada')->value('evidencia_url');Storage::disk('public')->assertExists($evidence);
        $incident=DB::table('incidencias')->where('descripcion','Calidad revisada')->value('id_incidencia');
        $this->put(route('chef.incidents.evidence',$incident),['evidence'=>$this->photo()])->assertRedirect();
        $this->assertNotSame($evidence,DB::table('incidencias')->where('id_incidencia',$incident)->value('evidencia_url'));
        $this->assertDatabaseHas('incidencias',['id_incidencia'=>$incident,'estado'=>'Pendiente','descripcion'=>'Calidad revisada']);
        $this->post('/logout')->assertRedirect();$this->actingAs($user)->withSession(['current_site_id'=>1,'active_role_id'=>12]);
        $this->get(route('chef.dishes'))->assertOk()->assertSee(asset('storage/'.$photo),false);
        $this->get(route('chef.menus',['service'=>$service->id_servicio]))->assertOk()->assertSee(asset('storage/'.$menuCover),false);
    }

    public function test_chef_workspace_generator_templates_filter_publication_and_persist_designs(): void
    {
        $this->chefFixture(true);
        foreach (['corporativa','gastronomica','elegante'] as $template) {
            $this->get(route('chef.generator',['desde'=>'2026-11-16','hasta'=>'2026-11-22','template'=>$template,'publico'=>1]))->assertOk()
                ->assertSee('cw-template-'.$template,false)->assertSee('Pollo del norte')->assertDontSee('Ensalada del norte')
                ->assertSee('17/11/2026')->assertSee('22/11/2026');
        }
        $this->get(route('chef.generator',['desde'=>'2026-11-16','periodo'=>'diario','publico'=>0]))->assertSee('Ensalada del norte');
        $this->post(route('chef.generator.save'),['desde'=>'2026-11-16','template'=>'elegante','titulo'=>'Nuestra cocina','design_background'=>$this->photo(),'design_logo'=>$this->photo()])->assertRedirect();
        $design=DB::table('kitchen_menu_designs')->first();Storage::disk('public')->assertExists([$design->background_url,$design->logo_url]);
        $this->get(route('chef.generator'))->assertSee('Nuestra cocina')->assertSee(asset('storage/'.$design->background_url),false);
        $this->post(route('chef.generator.save'),['titulo'=>'Otro título'])->assertRedirect();
        $this->assertDatabaseHas('kitchen_menu_designs',['id'=>$design->id,'background_url'=>$design->background_url,'logo_url'=>$design->logo_url]);
        DB::table('platillos')->where('id_platillo',1)->update(['disponible'=>false]);
        $this->get(route('chef.generator',['desde'=>'2026-11-16','periodo'=>'diario','publico'=>1]))->assertOk()->assertDontSee('Pollo del norte');
    }

    public function test_chef_generator_redesign_six_compositions_and_final_export_are_scoped_and_persistent(): void
    {
        $this->chefFixture(true);
        $photo='cocina/dish_photo/registered.jpg';
        Storage::disk('public')->put($photo,file_get_contents(public_path('img/ui/service-lunch.jpg')));
        DB::table('platillos')->where('id_platillo',1)->update(['imagen_url'=>$photo,'descripcion'=>'Preparación registrada de la sede.']);
        DB::table('menus')->where('id_menu',1)->update(['especial_chef'=>true]);
        $options=['desde'=>'2026-11-16','hasta'=>'2026-11-30','periodo'=>'quincenal','template'=>'elegante','titulo'=>'La mesa de nuestra cocina','subtitulo'=>'Un encuentro para disfrutar','color'=>'#171813','color_secundario'=>'#c3a36a','tamano_titulo'=>48,'orientacion'=>'horizontal','papel'=>'Carta','precios'=>1,'descripciones'=>1,'horarios'=>1,'destacar_especial'=>1,'fotos'=>1,'dias'=>[1,2,3,4,5],'dias_presentes'=>1];
        $this->post(route('chef.generator.save'),$options+['design_logo'=>$this->photo(),'design_background'=>UploadedFile::fake()->createWithContent('fondo.jpg',file_get_contents(public_path('img/ui/service-breakfast.jpg')))])->assertRedirect();
        $saved=DB::table('kitchen_menu_designs')->first();
        $stored=json_decode($saved->settings,true);
        foreach(['subtitulo','color_secundario','tamano_titulo','orientacion','papel','descripciones','horarios','destacar_especial'] as $key)$this->assertSame($options[$key],$stored[$key]);
        $page=$this->get(route('chef.generator'))->assertOk()->assertSee('Descargar PDF')->assertSee('gm-preview-stage',false);
        $page->assertViewHas('settings',fn($settings)=>$settings['hasta']==='2026-11-30');
        $response=$this->postJson(route('chef.generator.preview'),$options+['publico'=>0])->assertOk();
        $templates=$response->json('templates');$this->assertCount(6,$templates);$compositions=[];
        foreach(\App\Support\ChefMenuDocument::TEMPLATES as $key=>$name){
            $this->assertStringContainsString('gm-template-'.$key,$templates[$key]['preview']);
            $this->assertStringContainsString('Comida pendiente',$templates[$key]['preview']);
            $this->assertStringNotContainsString('Comida pendiente',$templates[$key]['final']);
            $this->assertStringNotContainsString('Ensalada del norte',$templates[$key]['final']);
            $this->assertStringNotContainsString('Platillo privado sur',$templates[$key]['final']);
            $this->assertStringNotContainsString('gm-draft-tag',$templates[$key]['final']);
            $this->assertStringContainsString('Especial del chef',$templates[$key]['final']);
            $compositions[]=hash('sha256',$templates[$key]['final']);
        }
        $this->assertCount(6,array_unique($compositions));
        $final=$this->post(route('chef.generator.print'),$options+['publico'=>0])->assertOk();
        $html=$final->getContent();
        $readableHtml=preg_replace('~data:image/[^;]+;base64,[A-Za-z0-9+/=]+~','[embedded-image]',$html);
        $this->assertStringContainsString('size:letter landscape',$readableHtml);
        $this->assertTrue(str_contains($html,'data:image/jpeg;base64,'),'Images must be embedded in the exported document.');
        $this->assertStringContainsString('lunes',$readableHtml);
        $this->assertStringContainsString('16 de noviembre de 2026',$readableHtml);
        foreach(['Comida pendiente','Ensalada del norte','Servicio privado sur','gm-draft-tag">','class="gm-editor"','app-sidebar','data-cw-generator','warehouse-food.jpg','Menú pendiente de configurar'] as $forbidden)$this->assertStringNotContainsString($forbidden,$readableHtml);
        $this->assertStringContainsString('Preparación registrada de la sede.',$readableHtml);
        $this->assertStringContainsString('$55.00',$readableHtml);
        $this->postJson(route('chef.generator.print'),array_merge($options,['desde'=>'2027-01-01','hasta'=>'2027-01-02']))->assertStatus(422);
        $this->postJson(route('chef.generator.preview'),array_merge($options,['template'=>'../../otra-vista']))->assertUnprocessable();
        $this->assertDatabaseHas('kitchen_menu_designs',['id'=>$saved->id,'logo_url'=>$saved->logo_url,'background_url'=>$saved->background_url]);
        $directory=storage_path('app/private/chef-generator-preview');if(!is_dir($directory))mkdir($directory,0700,true);
        $fixture=str_replace('http://localhost/','file:///'.str_replace('\\','/',public_path()).'/',$page->getContent());
        // Map only fixture uploads to their isolated disk for the browser review.
        $fixture=preg_replace_callback('~file:///[^"<> ]*/public/storage/([^"<> ]+)~',fn($match)=>'file:///'.str_replace('\\','/',Storage::disk('public')->path(html_entity_decode($match[1]))),$fixture);
        file_put_contents($directory.'/editor.html',$fixture);
        file_put_contents($directory.'/final.html',$html);
        if(\App\Support\ChefMenuPdf::browser()) {
            $pdf=$this->post(route('chef.generator.pdf'),$options+['publico'=>0])->assertOk()->assertHeader('Content-Type','application/pdf');
            $this->assertStringStartsWith('%PDF-',$pdf->getContent());
            file_put_contents($directory.'/menu.pdf',$pdf->getContent());
        }
        DB::table('roles')->insert(['id_rol'=>9,'nombre'=>'Gerente de sede']);
        DB::table('usuario_sede')->insert(['id_usuario'=>auth()->id(),'id_sede'=>1,'id_rol'=>9]);
        $this->withSession(['active_role_id'=>9])->post(route('chef.generator.print'),$options)->assertForbidden();
    }
}
