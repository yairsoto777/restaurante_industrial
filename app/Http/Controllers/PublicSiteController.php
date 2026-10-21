<?php
namespace App\Http\Controllers;
use App\Models\SitePage;
use Illuminate\View\View;
class PublicSiteController extends Controller { public function show(string $slug): View { $page=SitePage::with('site')->where('slug',$slug)->where('status','published')->firstOrFail(); return view('welcome',['site'=>$page->site,'content'=>$page->published,'isSitePage'=>true]); } }
