<?php
namespace App\Http\Controllers;
use App\Models\SitePage;
use Illuminate\View\View;
use Illuminate\Http\Request;
class PublicSiteController extends Controller
{
    public function show(string $slug): View
    {
        $page = SitePage::with('site')->where('slug', $slug)->where('status', 'published')->firstOrFail();
        return view('manager.site-page-template', ['site' => $page->site, 'content' => $page->published, 'isSitePage' => true]);
    }

    public function preview(Request $request, string $slug): View
    {
        abort_unless($request->user()?->isManager(), 403);
        $page = SitePage::with('site')->where('slug', $slug)->firstOrFail();
        abort_unless($request->user()->isManagerAtSite((int) $page->id_sede), 403);
        return view('manager.site-page-template', ['site' => $page->site, 'content' => $page->draft ?: [], 'isSitePage' => true, 'isPreview' => true]);
    }
}
