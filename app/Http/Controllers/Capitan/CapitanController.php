<?php

namespace App\Http\Controllers\Capitan;

use App\Http\Controllers\Controller;
use App\Models\AsignacionMesero;
use App\Models\Zona;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CapitanController extends Controller
{
    private const MODULOS = [
        'panel',
        'servicios',
        'mesas',
        'asignaciones',
        'atenciones',
        'comandas',
        'grupos',
        'incidencias',
        'reportes',
    ];

    /*
    |--------------------------------------------------------------------------
    | VALIDAR CAPITÁN Y SEDE ACTUAL
    |--------------------------------------------------------------------------
    */

    private function sede(Request $request, SiteContext $context)
    {
        $usuario = $request->user();

        abort_unless(
            $usuario && $usuario->hasRole('Capitan de meseros'),
            403,
            'No tienes permisos de Capitán de meseros.'
        );

        $sede = $context->currentFor($request, $usuario);

        abort_unless(
            $sede,
            403,
            'No tienes una sede asignada.'
        );

        $autorizado = DB::table('usuario_sede')
            ->where('id_usuario', $usuario->id_usuario)
            ->where('id_sede', $sede->id_sede)
            ->where('id_rol', 7)
            ->exists();

        abort_unless(
            $autorizado,
            403,
            'No tienes permisos para esta sede.'
        );

        return $sede;
    }

    /*
    |--------------------------------------------------------------------------
    | PANEL PRINCIPAL Y MÓDULOS
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request,
        SiteContext $context
    ): View {
        $sede = $this->sede($request, $context);
        $id = (int) $sede->id_sede;

        $modulo = $request->query('modulo', 'panel');

        if (!in_array($modulo, self::MODULOS, true)) {
            abort(404);
        }

        /*
        | ZONAS
        */

        $zonas = Zona::query()
            ->where('id_sede', $id)
            ->orderBy('nombre')
            ->get();

        /*
        | MESAS
        */

        $mesas = DB::table('mesas as m')
            ->join(
                'zonas as z',
                'z.id_zona',
                '=',
                'm.id_zona'
            )
            ->where('z.id_sede', $id)
            ->select(
                'm.id_mesa',
                'm.numero',
                'm.capacidad',
                'm.estado_fisico',
                'z.nombre as zona'
            )
            ->orderBy('z.nombre')
            ->orderBy('m.numero')
            ->get();

        /*
        | MESEROS ASIGNADOS A LA SEDE
        */

        $meseros = DB::table('usuarios as u')
            ->join(
                'usuario_sede as us',
                'us.id_usuario',
                '=',
                'u.id_usuario'
            )
            ->where('us.id_sede', $id)
            ->where('us.id_rol', 6)
            ->select(
                'u.id_usuario',
                'u.nombre',
                'u.apellido_p',
                'u.apellido_m'
            )
            ->distinct()
            ->orderBy('u.nombre')
            ->get();

        /*
        | ASIGNACIONES ACTIVAS
        */

        $asignaciones = DB::table('zona_meseros as zm')
            ->join(
                'zonas as z',
                'z.id_zona',
                '=',
                'zm.id_zona'
            )
            ->join(
                'usuarios as u',
                'u.id_usuario',
                '=',
                'zm.id_usuario_mesero'
            )
            ->where('z.id_sede', $id)
            ->where('zm.activo', 1)
            ->select(
                'zm.id_zona_mesero',
                'z.nombre as zona',
                'u.nombre',
                'u.apellido_p',
                'u.apellido_m',
                'zm.fecha_inicio'
            )
            ->orderBy('z.nombre')
            ->get();

        /*
        | FECHA Y SERVICIOS DE LA FECHA SELECCIONADA
        */

        $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $fechaOperacion = $request->query('fecha') ?: now()->toDateString();

        $servicios = DB::table('servicios')
            ->where('id_sede', $id)
            ->whereDate('fecha', $fechaOperacion)
            ->orderBy('inicio_hora')
            ->get();

        /*
        | SERVICIO EN VIVO
        | Solo se considera activo un servicio cuyo estado sea "En curso".
        | No se sustituye por un servicio programado o finalizado.
        */

        $servicioEnVivo = $servicios->firstWhere('estado', 'En curso');

        /*
        | INVENTARIO Y ESTADO DE MESAS
        | El inventario no depende de que exista un servicio en curso.
        | Si hay servicio activo, se muestran sus atenciones; si no lo hay,
        | se consultan las atenciones abiertas de los servicios de la fecha.
        */

        $mesasDisponiblesServicio = 0;
        $mesasOcupadasServicio = 0;
        $mesasAtendidasServicio = 0;
        $mesasPendientesPagoServicio = 0;
        $resumenZonasServicio = collect();

        $atencionesActivas = DB::table('atenciones as a')
            ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->select(
                'a.id_mesa',
                DB::raw('MAX(a.id_atencion) as id_atencion')
            )
            ->where('s.id_sede', $id)
            ->whereDate('s.fecha', $fechaOperacion)
            ->whereNotIn('a.estado', ['Cerrada', 'Cancelada'])
            ->when($servicioEnVivo, function ($query) use ($servicioEnVivo) {
                $query->where('a.id_servicio', $servicioEnVivo->id_servicio);
            })
            ->groupBy('a.id_mesa');

        $ultimaComanda = DB::table('comandas')
            ->select(
                'id_atencion',
                DB::raw('MAX(id_comanda) as id_comanda')
            )
            ->groupBy('id_atencion');

        // Esta consulta siempre devuelve las mesas registradas de la sede,
        // incluso cuando todavía no hay un servicio iniciado en esa fecha.
        $mesasServicio = DB::table('mesas as m')
            ->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
            ->leftJoinSub($atencionesActivas, 'aa', function ($join) {
                $join->on('aa.id_mesa', '=', 'm.id_mesa');
            })
            ->leftJoin('atenciones as a', 'a.id_atencion', '=', 'aa.id_atencion')
            ->leftJoinSub($ultimaComanda, 'uc', function ($join) {
                $join->on('uc.id_atencion', '=', 'a.id_atencion');
            })
            ->leftJoin('comandas as c', 'c.id_comanda', '=', 'uc.id_comanda')
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'a.id_usuario_mesero')
            ->where('z.id_sede', $id)
            ->select(
                'm.id_mesa',
                'm.numero',
                'm.capacidad',
                'm.estado_fisico',
                'z.nombre as zona',
                'a.id_atencion',
                'a.id_usuario_mesero',
                'a.estado as atencion_estado',
                'a.comensales',
                'c.id_comanda',
                'c.estado as comanda_estado',
                DB::raw("TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido_p, ''))) as mesero")
            )
            ->orderBy('z.nombre')
            ->orderBy('m.numero')
            ->get();

        // Indicadores calculados sobre el inventario completo de la sede.
        $mesasOcupadasServicio = $mesasServicio
            ->whereNotNull('id_atencion')
            ->count();

        $mesasDisponiblesServicio = $mesasServicio
            ->filter(function ($mesa) {
                return $mesa->id_atencion === null
                    && mb_strtolower(trim((string) $mesa->estado_fisico)) === 'disponible';
            })
            ->count();

        $mesasAtendidasServicio = $mesasServicio
            ->filter(function ($mesa) {
                return $mesa->id_atencion !== null
                    && $mesa->id_usuario_mesero !== null;
            })
            ->count();

        $mesasPendientesPagoServicio = $mesasServicio
            ->filter(function ($mesa) {
                return mb_strtolower(trim((string) $mesa->atencion_estado)) === 'pendiente de pago';
            })
            ->count();

        // Resumen por zona: total de mesas y estado operativo.
        $resumenZonasServicio = $mesasServicio
            ->groupBy('zona')
            ->map(function ($mesasZona, $zona) {
                return (object) [
                    'zona' => $zona,
                    'total' => $mesasZona->count(),
                    'disponibles' => $mesasZona->filter(function ($mesa) {
                        return $mesa->id_atencion === null
                            && mb_strtolower(trim((string) $mesa->estado_fisico)) === 'disponible';
                    })->count(),
                    'ocupadas' => $mesasZona->whereNotNull('id_atencion')->count(),
                    'por_cobrar' => $mesasZona->filter(function ($mesa) {
                        return mb_strtolower(trim((string) $mesa->atencion_estado)) === 'pendiente de pago';
                    })->count(),
                ];
            })
            ->values();

        // Se muestran las asignaciones activas aunque aún no haya servicio iniciado.
        $meserosAsignados = DB::table('zona_meseros as zm')
            ->join('zonas as z', 'z.id_zona', '=', 'zm.id_zona')
            ->join('usuarios as u', 'u.id_usuario', '=', 'zm.id_usuario_mesero')
            ->where('z.id_sede', $id)
            ->where('zm.activo', 1)
            ->select(
                'u.id_usuario',
                'u.nombre',
                'u.apellido_p',
                'u.apellido_m',
                'z.nombre as zona'
            )
            ->distinct()
            ->orderBy('u.nombre')
            ->get();

        $meserosServicio = $meserosAsignados
            ->groupBy('id_usuario')
            ->map(function ($registros, $idUsuario) use ($mesasServicio) {
                $mesero = $registros->first();

                $mesasDelMesero = $mesasServicio->filter(function ($mesa) use ($idUsuario) {
                    return (int) $mesa->id_usuario_mesero === (int) $idUsuario
                        && $mesa->id_atencion !== null;
                });

                return (object) [
                    'id_usuario' => (int) $idUsuario,
                    'nombre' => $mesero->nombre,
                    'apellido_p' => $mesero->apellido_p,
                    'apellido_m' => $mesero->apellido_m,
                    'zona' => $registros->pluck('zona')->unique()->implode(', '),
                    'atenciones' => $mesasDelMesero->count(),
                    'mesas' => $mesasDelMesero->pluck('numero')->values(),
                ];
            })
            ->values();

        /*
        | ATENCIONES
        */

        $atenciones = DB::table('atenciones as a')
            ->join(
                'servicios as s',
                's.id_servicio',
                '=',
                'a.id_servicio'
            )
            ->join(
                'mesas as m',
                'm.id_mesa',
                '=',
                'a.id_mesa'
            )
            ->where('s.id_sede', $id)
            ->select(
                'a.id_atencion',
                'a.estado',
                'a.comensales',
                'a.inicio_hora',
                'a.fin_hora',
                'm.numero as mesa',
                's.nombre as servicio'
            )
            ->orderByDesc('a.id_atencion')
            ->limit(100)
            ->get();

        /*
        | COMANDAS
        | Se conservan las tablas y los nombres de las rutas.
        | La interfaz muestra el nombre "Comidas".
        */

        $comandas = DB::table('comandas as c')
            ->join(
                'atenciones as a',
                'a.id_atencion',
                '=',
                'c.id_atencion'
            )
            ->join(
                'servicios as s',
                's.id_servicio',
                '=',
                'a.id_servicio'
            )
            ->where('s.id_sede', $id)
            ->select(
                'c.id_comanda',
                'c.id_atencion',
                'c.estado',
                'c.fecha_apertura',
                'c.fecha_cierre'
            )
            ->orderByDesc('c.id_comanda')
            ->limit(100)
            ->get();

        /*
        | GRUPOS
        */

        $grupos = DB::table('grupos as g')
            ->join(
                'servicios as s',
                's.id_servicio',
                '=',
                'g.id_servicio'
            )
            ->where('s.id_sede', $id)
            ->select(
                'g.id_grupo',
                'g.nombre',
                'g.inicio_hora',
                'g.fin_hora',
                'g.comensales_pre',
                's.nombre as servicio'
            )
            ->get();

        /*
        | INCIDENCIAS
        */

        $incidencias = DB::table('incidencias')
            ->where('id_sede', $id)
            ->orderByDesc('id_incidencia')
            ->limit(100)
            ->get();

        /*
        | MÉTRICAS ORIGINALES
        */

        $metricas = [
            'servicios' => $servicios->count(),

            'mesas' => $mesas->count(),

            'meseros' => $meseros->count(),

            'atenciones' => DB::table('atenciones as a')
                ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
                ->where('s.id_sede', $id)
                ->where(function ($query) {
                    $query->whereNull('a.estado')
                        ->orWhereNotIn('a.estado', ['Cerrada', 'Finalizada', 'Cancelada']);
                })
                ->count(),

            'incidencias' => $incidencias
                ->whereNull('fecha_cierre')
                ->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | GRÁFICA: COMENSALES POR HORA
        |--------------------------------------------------------------------------
        | Consulta compatible con ONLY_FULL_GROUP_BY.
        | No modifica registros.
        | Solo incluye servicios de la fecha actual y sede actual.
        */

        $comensalesPorHora = DB::table('atenciones as a')
            ->join(
                'servicios as s',
                's.id_servicio',
                '=',
                'a.id_servicio'
            )
            ->where('s.id_sede', $id)
            ->whereDate('s.fecha', $fechaOperacion)
            ->whereNotNull('a.inicio_hora')
            ->selectRaw("
                DATE_FORMAT(a.inicio_hora, '%H:00') AS hora,
                SUM(COALESCE(a.comensales, 0)) AS total
            ")
            ->groupByRaw(
                "DATE_FORMAT(a.inicio_hora, '%H:00')"
            )
            ->orderByRaw(
                "DATE_FORMAT(a.inicio_hora, '%H:00')"
            )
            ->get();

        /*
        | VISTA
        */

        return view('capitan.index', compact(
            'sede',
            'modulo',
            'zonas',
            'mesas',
            'meseros',
            'asignaciones',
            'servicios',
            'atenciones',
            'comandas',
            'grupos',
            'incidencias',
            'metricas',
            'comensalesPorHora',
            'fechaOperacion',
            'servicioEnVivo',
            'mesasServicio',
            'meserosServicio',
            'mesasDisponiblesServicio',
            'mesasOcupadasServicio',
            'mesasAtendidasServicio',
            'mesasPendientesPagoServicio',
            'resumenZonasServicio'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | ASIGNAR MESERO
    | Se conserva la operación que ya probamos.
    |--------------------------------------------------------------------------
    */

    public function asignar(
        Request $request,
        SiteContext $context
    ) {
        $sede = $this->sede($request, $context);
        $idSede = (int) $sede->id_sede;

        $datos = $request->validate([
            'id_zona' => [
                'required',
                'integer',
                Rule::exists('zonas', 'id_zona')
                    ->where('id_sede', $idSede),
            ],
            'id_usuario_mesero' => [
                'required',
                'integer',
                Rule::exists('usuario_sede', 'id_usuario')
                    ->where('id_sede', $idSede)
                    ->where('id_rol', 6),
            ],
        ]);

        DB::transaction(function () use ($datos) {
            AsignacionMesero::query()->updateOrCreate(
                [
                    'id_zona' => $datos['id_zona'],
                    'id_usuario_mesero' => $datos['id_usuario_mesero'],
                ],
                [
                    'activo' => 1,
                    'fecha_inicio' => now(),
                    'fecha_fin' => null,
                ]
            );
        });

        return redirect()
            ->route('capitan.index', [
                'modulo' => 'asignaciones',
            ])
            ->with(
                'status',
                'Mesero asignado correctamente.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | RETIRAR MESERO
    | Se conserva la operación que ya probamos.
    |--------------------------------------------------------------------------
    */

    public function retirar(
        Request $request,
        SiteContext $context,
        int $asignacion
    ) {
        $sede = $this->sede($request, $context);

        $registro = AsignacionMesero::query()
            ->whereKey($asignacion)
            ->whereHas('zona', function ($query) use ($sede) {
                $query->where(
                    'id_sede',
                    $sede->id_sede
                );
            })
            ->firstOrFail();

        $registro->update([
            'activo' => 0,
            'fecha_fin' => now(),
        ]);

        return redirect()
            ->route('capitan.index', [
                'modulo' => 'asignaciones',
            ])
            ->with(
                'status',
                'Asignación retirada correctamente.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | REGISTRAR INCIDENCIA
    | Se conserva la operación que ya probamos.
    |--------------------------------------------------------------------------
    */

    public function incidencia(
        Request $request,
        SiteContext $context
    ) {
        $sede = $this->sede($request, $context);

        $datos = $request->validate([
            'descripcion' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
        ]);

        DB::table('incidencias')->insert([
            'id_sede' => $sede->id_sede,
            'id_usuario' => $request->user()->id_usuario,
            'fecha' => now(),
            'descripcion' => $datos['descripcion'],
            'estado' => 'abierta',
        ]);

        return redirect()
            ->route('capitan.index', [
                'modulo' => 'incidencias',
            ])
            ->with(
                'status',
                'Incidencia registrada correctamente.'
            );
    }
}
