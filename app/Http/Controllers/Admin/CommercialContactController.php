<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ManagerApprovalMail;
use App\Models\CommercialContact;
use App\Models\Role;
use App\Models\User;
use App\Support\EmploymentRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommercialContactController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(array_keys(CommercialContact::statuses()))],
        ]);
        $search = isset($filters['buscar']) ? Str::squish(strip_tags($filters['buscar'])) : null;

        $contacts = CommercialContact::query()
            ->when($search, function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('nombre', 'like', "%{$search}%")
                        ->orWhere('apellido_p', 'like', "%{$search}%")
                        ->orWhere('apellido_m', 'like', "%{$search}%")
                        ->orWhere('correo', 'like', "%{$search}%")
                        ->orWhere('empresa', 'like', "%{$search}%")
                        ->orWhere('mensaje', 'like', "%{$search}%");
                });
            })
            ->when($filters['estado'] ?? null, fn ($query, string $status) => $query->where('estado', $status))
            ->orderByRaw("CASE estado WHEN 'nuevo' THEN 0 WHEN 'contactado' THEN 1 WHEN 'aprobado' THEN 2 WHEN 'rechazado' THEN 3 ELSE 4 END")
            ->orderByDesc('fecha_contacto')
            ->orderByDesc('id_contacto')
            ->paginate(15)
            ->withQueryString();

        return view('admin.contacts.index', [
            'contacts' => $contacts,
            'statuses' => CommercialContact::statuses(),
        ]);
    }

    public function show(CommercialContact $contact): View
    {
        return view('admin.contacts.show', [
            'contact' => $contact->load(['responder', 'manager.sites']),
            'statuses' => CommercialContact::statuses(),
        ]);
    }

    public function update(Request $request, CommercialContact $contact): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(array_keys(CommercialContact::statuses()))],
            'observaciones_respuesta' => ['nullable', 'string', 'max:3000'],
        ]);

        if ($data['estado'] === CommercialContact::STATUS_APPROVED && $contact->estado !== CommercialContact::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'estado' => 'Usa la acción Convertir a gerente para aprobar este contacto.',
            ]);
        }

        if ($contact->estado === CommercialContact::STATUS_APPROVED && $data['estado'] !== CommercialContact::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'estado' => 'Un contacto aprobado y convertido no puede regresar a un estado anterior.',
            ]);
        }

        $contact->estado = $data['estado'];
        if ($request->has('observaciones_respuesta')) {
            $contact->observaciones_respuesta = $this->multiline($data['observaciones_respuesta'] ?? null);
        }
        $contact->id_superadmin_responde = (int) $request->user()->getKey();

        if ($contact->estado !== CommercialContact::STATUS_NEW && ! $contact->fecha_respuesta) {
            $contact->fecha_respuesta = now();
        }

        $contact->save();

        return back()->with('status', 'Contacto actualizado correctamente.');
    }

    public function createConversion(CommercialContact $contact): View|RedirectResponse
    {
        return redirect()->route('superadmin.index',['modulo'=>'solicitudes','seleccion'=>$contact->id_contacto,'abrir'=>'convertir']);
    }

    private function multiline(?string $value): ?string
    {
        // Keep every line and all captured text; Blade escapes it when displayed.
        return $value === null ? null : str_replace(["\r\n", "\r"], "\n", $value);
    }

    public function convert(Request $request, CommercialContact $contact): RedirectResponse
    {
        return \App\Support\SuperadminCommercialFlow::convert($request,$contact);
    }

    public function resendActivation(CommercialContact $contact): RedirectResponse
    {
        \App\Support\SuperadminWorkspace::authorize(request());
        $manager=$contact->id_usuario_gerente?User::find($contact->id_usuario_gerente):User::where('correo',$contact->correo)->first();
        if(!$manager||!$manager->roles()->get()->contains(fn($r)=>EmploymentRoles::isManager($r->nombre)))throw ValidationException::withMessages(['activacion'=>'No existe un gerente vinculado a la solicitud.']);
        try{\App\Support\SuperadminCommercialFlow::activation($manager);}catch(\Throwable $error){report($error);throw ValidationException::withMessages(['activacion'=>'No se pudo enviar el enlace. Reintenta desde Usuarios del sistema.']);}
        return back()->with('status','Enlace de acceso reenviado sin enviar contraseñas.');
    }
}
