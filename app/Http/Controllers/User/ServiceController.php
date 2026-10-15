<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Support\EmploymentRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        $services = DB::table('servicios as servicio')
            ->leftJoin('sedes as sede', 'sede.id_sede', '=', 'servicio.id_sede')
            ->select('servicio.*', 'sede.nombre as sede_nombre')
            ->orderByRaw('CASE WHEN servicio.fecha IS NULL THEN 1 ELSE 0 END')
            ->orderBy('servicio.fecha')
            ->orderBy('servicio.inicio_hora')
            ->paginate(15);

        return view('user.services.index', [
            'services' => $services,
            'opportunities' => EmploymentRoles::publicOpportunities(),
        ]);
    }
}
