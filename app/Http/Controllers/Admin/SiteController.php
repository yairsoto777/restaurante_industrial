<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        return view('admin.sites.index', ['sites' => Site::withCount('users')->orderBy('name')->paginate(15)]);
    }

    public function store(Request $request): RedirectResponse
    {
        Site::create($this->validated($request));

        return back()->with('status', 'Sede creada correctamente.');
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $site->update($this->validated($request, $site));

        return back()->with('status', 'Sede actualizada correctamente.');
    }

    private function validated(Request $request, ?Site $site = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', Rule::unique('sites')->ignore($site)],
            'address' => ['nullable', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ]);
    }
}
