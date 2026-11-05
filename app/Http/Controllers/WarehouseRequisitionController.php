<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WarehouseRequisitionController extends Controller
{
    public function index(Request $request, SiteContext $siteContext): View
    {
        $site = $siteContext->currentFor($request, $request->user());
        abort_unless($site instanceof Site, 403);
        abort_unless($request->user()->canAdminister() || $request->user()->sites()->whereKey($site->id_sede)->exists(), 403);

        $available = Schema::hasTable('requisiciones')
            && Schema::hasColumn('requisiciones', 'id_sede');
        $requisitions = collect();

        if ($available) {
            $requisitions = DB::table('requisiciones')
                ->where('id_sede', $site->id_sede)
                ->orderByDesc('id_requisicion')
                ->paginate(20)
                ->withQueryString();
        }

        return view('modules.requisitions', compact('site', 'available', 'requisitions'));
    }
}
