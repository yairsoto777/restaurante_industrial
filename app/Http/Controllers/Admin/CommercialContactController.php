<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommercialContact;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Support\EmploymentRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                        ->orWhere('correo', 'like', "%{$search}%")
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
            'contact' => $contact->load('responder'),
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
        if ($contact->estado === CommercialContact::STATUS_APPROVED) {
            return redirect()->route('admin.contacts.show', $contact)
                ->with('status', 'Este contacto ya fue aprobado y convertido.');
        }

        if (! in_array($contact->estado, [CommercialContact::STATUS_NEW, CommercialContact::STATUS_CONTACTED], true)) {
            return redirect()->route('admin.contacts.show', $contact)
                ->withErrors(['conversion' => 'El contacto debe estar nuevo o contactado antes de aprobarlo.']);
        }

        return view('admin.contacts.convert', [
            'contact' => $contact,
            'sites' => Site::query()->orderBy('nombre')->get(),
            'managerDefaults' => $this->managerDefaults($contact->nombre),
        ]);
    }

    public function convert(Request $request, CommercialContact $contact): RedirectResponse
    {
        if (! in_array($contact->estado, [CommercialContact::STATUS_NEW, CommercialContact::STATUS_CONTACTED], true)) {
            throw ValidationException::withMessages([
                'conversion' => $contact->estado === CommercialContact::STATUS_APPROVED
                    ? 'Este contacto ya fue aprobado y convertido.'
                    : 'El contacto debe estar nuevo o contactado antes de aprobarlo.',
            ]);
        }

        $request->merge([
            'sede_nombre' => $this->singleLine($request->input('sede_nombre')),
            'sede_direccion' => $this->singleLine($request->input('sede_direccion')),
            'sede_telefono' => $this->singleLine($request->input('sede_telefono')),
            'gerente_nombre' => $this->singleLine($request->input('gerente_nombre')),
            'gerente_apellido_p' => $this->singleLine($request->input('gerente_apellido_p')),
            'gerente_apellido_m' => $this->singleLine($request->input('gerente_apellido_m')),
            'gerente_correo' => is_string($request->input('gerente_correo'))
                ? Str::lower(trim($request->input('gerente_correo')))
                : $request->input('gerente_correo'),
        ]);

        $data = $request->validate([
            'site_mode' => ['required', Rule::in(['existing', 'new'])],
            'id_sede' => [Rule::requiredIf(fn (): bool => $request->input('site_mode') === 'existing'), 'nullable', 'integer', 'exists:sedes,id_sede'],
            'sede_nombre' => [Rule::requiredIf(fn (): bool => $request->input('site_mode') === 'new'), 'nullable', 'string', 'max:100'],
            'sede_direccion' => ['nullable', 'string', 'max:255'],
            'sede_telefono' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s().-]{7,20}$/'],
            'sede_capacidad' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'gerente_nombre' => ['required', 'string', 'max:100'],
            'gerente_apellido_p' => ['required', 'string', 'max:100'],
            'gerente_apellido_m' => ['nullable', 'string', 'max:100'],
            'gerente_correo' => ['required', 'email:rfc', 'max:150', Rule::unique('usuarios', 'correo')],
            'gerente_password' => ['required', 'string', 'min:8', 'max:255'],
        ], [
            'id_sede.required' => 'Selecciona la sede existente.',
            'sede_nombre.required' => 'Indica el nombre de la nueva sede.',
            'sede_telefono.regex' => 'Ingresa un teléfono de sede válido.',
            'gerente_correo.unique' => 'Ya existe un usuario con ese correo.',
        ]);

        [$site, $manager] = DB::transaction(function () use ($data, $contact, $request): array {
            $lockedContact = CommercialContact::query()
                ->whereKey($contact->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedContact->estado, [CommercialContact::STATUS_NEW, CommercialContact::STATUS_CONTACTED], true)) {
                throw ValidationException::withMessages([
                    'conversion' => $lockedContact->estado === CommercialContact::STATUS_APPROVED
                        ? 'Este contacto ya fue aprobado y convertido.'
                        : 'El contacto ya no está disponible para aprobación.',
                ]);
            }

            $managerRole = Role::query()
                ->lockForUpdate()
                ->get()
                ->first(fn (Role $role): bool => EmploymentRoles::isManager($role->nombre));

            if (! $managerRole) {
                throw ValidationException::withMessages([
                    'gerente_correo' => 'No existe el rol Gerente de sede en el catálogo de roles.',
                ]);
            }

            $site = $data['site_mode'] === 'existing'
                ? Site::query()->whereKey($data['id_sede'])->lockForUpdate()->firstOrFail()
                : $this->createSite($data);

            $manager = User::create([
                'nombre' => $data['gerente_nombre'],
                'apellido_p' => $data['gerente_apellido_p'],
                'apellido_m' => $data['gerente_apellido_m'] ?? null,
                'correo' => $data['gerente_correo'],
                'password' => $data['gerente_password'],
            ]);

            DB::table('usuario_sede')->insert([
                'id_usuario' => (int) $manager->getKey(),
                'id_sede' => (int) $site->getKey(),
                'id_rol' => (int) $managerRole->getKey(),
            ]);

            $lockedContact->estado = CommercialContact::STATUS_APPROVED;
            $lockedContact->fecha_respuesta ??= now();
            $lockedContact->id_superadmin_responde = (int) $request->user()->getKey();
            $lockedContact->save();

            return [$site, $manager];
        }, 3);

        return redirect()->route('admin.contacts.show', $contact)
            ->with('status', "Contacto aprobado. Se asignó {$manager->correo} como Gerente de {$site->nombre}.");
    }

    private function createSite(array $data): Site
    {
        return Site::create([
            'nombre' => $data['sede_nombre'],
            'direccion' => $data['sede_direccion'] ?? null,
            'telefono' => $data['sede_telefono'] ?? null,
            'capacidad' => isset($data['sede_capacidad']) ? (int) $data['sede_capacidad'] : null,
        ]);
    }

    private function managerDefaults(string $fullName): array
    {
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) <= 1) {
            return ['nombre' => $parts[0] ?? '', 'apellido_p' => '', 'apellido_m' => ''];
        }

        if (count($parts) === 2) {
            return ['nombre' => $parts[0], 'apellido_p' => $parts[1], 'apellido_m' => ''];
        }

        $apellidoMaterno = array_pop($parts);
        $apellidoPaterno = array_pop($parts);

        return [
            'nombre' => implode(' ', $parts),
            'apellido_p' => $apellidoPaterno,
            'apellido_m' => $apellidoMaterno,
        ];
    }

    private function singleLine(mixed $value): mixed
    {
        return is_string($value) ? Str::squish(strip_tags($value)) : $value;
    }

    private function multiline(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;

        return trim(preg_replace("/\R{3,}/u", PHP_EOL.PHP_EOL, $value) ?? $value);
    }
}
