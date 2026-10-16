<?php

namespace App\Http\Controllers;

use App\Models\CommercialContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicContactController extends Controller
{
    public function index(): View
    {
        return view('welcome');
    }

    public function store(Request $request): RedirectResponse
    {
        $successMessage = 'Gracias por contactarnos. Un especialista revisará tu solicitud.';

        if ($request->filled('website')) {
            return redirect()->to(route('home').'#contacto')->with('contact_status', $successMessage);
        }

        $request->merge([
            'nombre' => $this->singleLine($request->input('nombre')),
            'correo' => is_string($request->input('correo'))
                ? Str::lower(trim($request->input('correo')))
                : $request->input('correo'),
            'mensaje' => $this->multiline($request->input('mensaje')),
        ]);

        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:150'],
            'correo' => ['required', 'email:rfc', 'max:150'],
            'mensaje' => ['required', 'string', 'max:2000'],
            'website' => ['nullable', 'max:0'],
        ], [
            'nombre.required' => 'Indica tu nombre.',
            'correo.required' => 'Indica un correo de contacto.',
            'correo.email' => 'Ingresa un correo válido.',
            'mensaje.required' => 'Cuéntanos brevemente qué necesitas.',
        ]);

        if ($validator->fails()) {
            return redirect()->to(route('home').'#contacto')
                ->withErrors($validator, 'contact')
                ->withInput($request->only([
                    'nombre',
                    'correo',
                    'mensaje',
                ]));
        }

        CommercialContact::create([
            ...$validator->safe()->except('website'),
            'estado' => CommercialContact::STATUS_NEW,
            'fecha_contacto' => now(),
        ]);

        return redirect()->to(route('home').'#contacto')->with('contact_status', $successMessage);
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
