<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
abstract class OperationsFixtureTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutVite();
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('CONCAT', fn (...$parts) => implode('', $parts));
        $pdo->sqliteCreateFunction('DATE_FORMAT', fn ($value, $format) => substr($value ?? '', 0, 2).':00');
        $definitions = [
            'usuarios' => 'id_usuario INTEGER PRIMARY KEY, nombre TEXT, apellido_p TEXT, apellido_m TEXT, correo TEXT, password TEXT, remember_token TEXT',
            'roles' => 'id_rol INTEGER PRIMARY KEY, nombre TEXT',
            'sedes' => 'id_sede INTEGER PRIMARY KEY, nombre TEXT, direccion TEXT, capacidad INTEGER, logo_url TEXT, mesas_programadas INTEGER',
            'usuario_sede' => 'id_usuario_sede INTEGER PRIMARY KEY, id_usuario INTEGER, id_sede INTEGER, id_rol INTEGER',
            'zonas' => 'id_zona INTEGER PRIMARY KEY, id_sede INTEGER, nombre TEXT',
            'zona_meseros' => 'id_zona_mesero INTEGER PRIMARY KEY, id_zona INTEGER, id_usuario_mesero INTEGER, activo INTEGER, fecha_inicio TEXT',
            'mesas' => 'id_mesa INTEGER PRIMARY KEY, id_zona INTEGER, numero INTEGER, capacidad INTEGER, estado_fisico TEXT, observaciones TEXT',
            'servicios' => 'id_servicio INTEGER PRIMARY KEY, id_sede INTEGER, nombre TEXT, fecha TEXT, inicio_hora TEXT, fin_hora TEXT, capacidad INTEGER, estado TEXT, estado_aprobacion TEXT',
            'atenciones' => 'id_atencion INTEGER PRIMARY KEY, id_servicio INTEGER, id_mesa INTEGER, id_usuario_mesero INTEGER, estado TEXT, comensales INTEGER, inicio_hora TEXT, fin_hora TEXT',
            'comandas' => 'id_comanda INTEGER PRIMARY KEY, id_atencion INTEGER, estado TEXT, fecha_apertura TEXT, fecha_cierre TEXT',
            'comanda_detalles' => 'id_comanda_detalle INTEGER PRIMARY KEY, id_comanda INTEGER, id_menu INTEGER, cantidad INTEGER, precio_unitario NUMERIC, estado TEXT',
            'menus' => 'id_menu INTEGER PRIMARY KEY, id_platillo INTEGER',
            'platillos' => 'id_platillo INTEGER PRIMARY KEY, nombre TEXT',
            'grupos' => 'id_grupo INTEGER PRIMARY KEY, id_servicio INTEGER, nombre TEXT, inicio_hora TEXT, fin_hora TEXT, comensales_pre INTEGER',
            'entregas' => 'id_entrega INTEGER PRIMARY KEY, id_atencion INTEGER, cantidad INTEGER, fecha_entrega TEXT',
            'incidencias' => 'id_incidencia INTEGER PRIMARY KEY, id_sede INTEGER, id_usuario INTEGER, id_recepcion_detalle INTEGER, fecha TEXT, descripcion TEXT, estado TEXT, respuesta TEXT, fecha_cierre TEXT',
            'pedidos' => 'id_pedido INTEGER PRIMARY KEY, id_sede INTEGER, estado TEXT, fecha_pedido TEXT',
            'recepciones' => 'id_recepcion INTEGER PRIMARY KEY, id_pedido INTEGER, fecha_recepcion TEXT, folio_documento TEXT, estado TEXT',
            'recepcion_detalles' => 'id_recepcion_detalle INTEGER PRIMARY KEY, id_recepcion INTEGER, id_detalle_pedido INTEGER, cantidad_aceptada NUMERIC, caducidad TEXT',
            'detalle_pedidos' => 'id_detalle_pedido INTEGER PRIMARY KEY, id_insumo INTEGER',
            'insumos' => 'id_insumo INTEGER PRIMARY KEY, nombre TEXT',
            'servicios_ofrecidos' => 'id_servicio_ofrecido INTEGER PRIMARY KEY, id_atencion INTEGER UNIQUE, descripcion TEXT, comensales INTEGER, precio_unitario NUMERIC, total NUMERIC, estado TEXT, finalizado_en TEXT, pagado_en TEXT, created_at TEXT, updated_at TEXT',
            'cuentas' => 'id_cuenta INTEGER PRIMARY KEY, id_atencion INTEGER UNIQUE, subtotal NUMERIC, total NUMERIC, estado TEXT, fecha_apertura TEXT, fecha_cierre TEXT',
            'caja_turnos' => 'id_turno_caja INTEGER PRIMARY KEY, id_sede INTEGER, id_usuario_apertura INTEGER, fondo_inicial NUMERIC, efectivo_esperado NUMERIC, efectivo_contado NUMERIC, diferencia NUMERIC, estado TEXT, abierta_at TEXT, cerrada_at TEXT, created_at TEXT, updated_at TEXT',
            'caja_pagos' => 'id_pago INTEGER PRIMARY KEY, id_turno_caja INTEGER, id_atencion INTEGER UNIQUE, id_usuario INTEGER, id_sede INTEGER, metodo TEXT, importe NUMERIC, recibido NUMERIC, cambio NUMERIC, estado TEXT, created_at TEXT, updated_at TEXT',
        ];
        foreach ($definitions as $table => $columns) {
            $pdo->exec('CREATE TABLE '.$table.' ('.$columns.')');
        }
        DB::table('sedes')->insert([
            ['id_sede'=>1,'nombre'=>'Sede de prueba UI','direccion'=>'Domicilio de prueba','capacidad'=>100],
            ['id_sede'=>2,'nombre'=>'Sede no autorizada','direccion'=>'Otra sede','capacidad'=>30],
        ]);
        foreach ([6=>'Mesero',7=>'Capitan de meseros',8=>'Cajero',12=>'Jefe de cocina'] as $id=>$name) {
            DB::table('roles')->insert(['id_rol'=>$id,'nombre'=>$name]);
            DB::table('usuarios')->insert(['id_usuario'=>$id,'nombre'=>$name,'apellido_p'=>'Prueba','correo'=>'ui'.$id.'@example.test','password'=>'unused']);
            DB::table('usuario_sede')->insert(['id_usuario'=>$id,'id_sede'=>1,'id_rol'=>$id]);
        }
        DB::table('zonas')->insert(['id_zona'=>1,'id_sede'=>1,'nombre'=>'Zona de prueba']);
        DB::table('zona_meseros')->insert(['id_zona_mesero'=>1,'id_zona'=>1,'id_usuario_mesero'=>6,'activo'=>1,'fecha_inicio'=>now()]);
        DB::table('mesas')->insert([
            ['id_mesa'=>1,'id_zona'=>1,'numero'=>1,'capacidad'=>6,'estado_fisico'=>'Disponible','observaciones'=>'Observación conservada'],
            ['id_mesa'=>2,'id_zona'=>1,'numero'=>2,'capacidad'=>4,'estado_fisico'=>'Mantenimiento','observaciones'=>null],
            ['id_mesa'=>3,'id_zona'=>1,'numero'=>3,'capacidad'=>4,'estado_fisico'=>'Disponible','observaciones'=>null],
        ]);
        DB::table('servicios')->insert(['id_servicio'=>1,'id_sede'=>1,'nombre'=>'Servicio de prueba UI','fecha'=>now()->toDateString(),'inicio_hora'=>'08:00:00','fin_hora'=>'23:00:00','capacidad'=>100,'estado'=>'En curso','estado_aprobacion'=>'Aprobado']);
        DB::table('atenciones')->insert(['id_atencion'=>1,'id_servicio'=>1,'id_mesa'=>1,'id_usuario_mesero'=>6,'estado'=>'En atención','comensales'=>2,'inicio_hora'=>'09:00:00']);
        DB::table('comandas')->insert(['id_comanda'=>1,'id_atencion'=>1,'estado'=>'En preparación','fecha_apertura'=>now()]);
        DB::table('grupos')->insert(['id_grupo'=>1,'id_servicio'=>1,'nombre'=>'Grupo de prueba UI','comensales_pre'=>20,'inicio_hora'=>'09:00:00','fin_hora'=>'10:00:00']);
        DB::table('incidencias')->insert(['id_incidencia'=>1,'id_sede'=>1,'id_usuario'=>6,'fecha'=>now(),'descripcion'=>'Incidencia de prueba UI','estado'=>'Pendiente','respuesta'=>'Respuesta registrada']);
    }

}
