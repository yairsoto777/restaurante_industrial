<?php

namespace Tests\Feature;

use App\Http\Controllers\Manager\SitePageController;
use App\Http\Controllers\PublicSiteController;
use App\Models\Site;
use App\Models\SitePage;
use App\Models\User;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

/** No migrations, seeders, production database or production media. */
class SitePageEditorTest extends TestCase
{
    private SitePageController $controller;
    private array $defaults;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutVite();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        DB::statement('CREATE TABLE sedes (id_sede INTEGER PRIMARY KEY, nombre TEXT)');
        DB::statement('CREATE TABLE site_pages (id_pagina INTEGER PRIMARY KEY, id_sede INTEGER, slug TEXT, template TEXT, draft TEXT, published TEXT, status TEXT, published_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE site_media (id INTEGER PRIMARY KEY, id_sede INTEGER, path TEXT, name TEXT, mime TEXT, size INTEGER, created_at TEXT, updated_at TEXT)');
        DB::table('sedes')->insert([['id_sede'=>1,'nombre'=>'Prueba'],['id_sede'=>2,'nombre'=>'Otra sede']]);
        $this->controller = new SitePageController;
        $this->defaults = (new \ReflectionMethod($this->controller, 'defaults'))->invoke($this->controller);
        foreach ([1,2] as $id) SitePage::create(['id_sede'=>$id,'slug'=>'prueba-'.$id,'template'=>'nutriliado','draft'=>$this->defaults]);
        Storage::fake('public');
    }

    private function request(array $input = [], bool $manager = true): Request
    {
        $request = Request::create('/operacion/mi-pagina-web/editor', 'POST', $input);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isManager')->andReturn($manager);
        $user->shouldReceive('isManagerAtSite')->with(1)->andReturn($manager);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    private function context(): SiteContext
    {
        $context = Mockery::mock(SiteContext::class);
        $context->shouldReceive('currentFor')->andReturn(Site::findOrFail(1));
        return $context;
    }

    public function test_upload_draft_reopen_publish_and_site_isolation(): void
    {
        $upload = $this->request();
        $upload->files->set('files', [UploadedFile::fake()->image('fotografia.png', 40, 30)]);
        $response = $this->controller->uploadMedia($upload, $this->context());
        $this->assertSame(201, $response->getStatusCode());
        $media = $response->getData(true)['media'][0];
        Storage::disk('public')->assertExists($media['path']);
        $this->assertStringContainsString('/storage/site-pages/1/media/', $media['url']);
        $content = $this->defaults;
        foreach (['hero_image','hero_poster','vision_image','experience_image'] as $key) $content[$key] = $media;
        foreach ($content['solutions'] as &$solution) $solution[3] = $media;
        unset($solution);
        $content['hero_title'] = 'Borrador de prueba';
        $this->controller->saveEditor($this->request(['content'=>$content]), $this->context());
        $page = SitePage::where('id_sede',1)->firstOrFail();
        $this->assertNull($page->published);
        $reopened = $this->controller->editor($this->request(), $this->context())->getData()['page']->draft;
        foreach (['hero_image','hero_poster','vision_image','experience_image'] as $key) {
            $this->assertSame($media['path'], $reopened[$key]['path']);
            $this->assertArrayNotHasKey('url', $reopened[$key]);
        }
        foreach ($reopened['solutions'] as $solution) $this->assertSame($media['path'], $solution[3]['path']);
        $this->assertSame($this->defaults, SitePage::where('id_sede',2)->firstOrFail()->draft);
        $this->controller->publish($this->request(), $this->context());
        $page->refresh();
        $this->assertSame($page->draft, $page->published);
        $content['hero_title'] = 'Cambio no publicado';
        $this->controller->saveEditor($this->request(['content'=>$content]), $this->context());
        $html = (new PublicSiteController)->show('prueba-1')->render();
        $this->assertStringContainsString('Borrador de prueba', $html);
        $this->assertStringNotContainsString('Cambio no publicado', $html);
        $this->assertStringContainsString($media['path'], $html);
    }

    public function test_foreign_media_is_rejected(): void
    {
        $content = $this->defaults;
        $content['vision_image'] = ['path'=>'site-pages/2/media/foreign.png','type'=>'image'];
        $this->expectException(ValidationException::class);
        $this->controller->saveEditor($this->request(['content'=>$content]), $this->context());
    }

    public function test_other_roles_are_rejected(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->controller->editor($this->request([], false), $this->context());
    }

    public function test_videos_render_in_all_supported_sections(): void
    {
        $content = $this->defaults;
        $video = ['path'=>'site-pages/1/media/test.webm','type'=>'video','mime'=>'video/webm'];
        foreach (['hero_image','vision_image','experience_image'] as $key) $content[$key] = $video;
        foreach ($content['solutions'] as &$solution) $solution[3] = $video;
        unset($solution);
        $html = view('manager.site-page-template', ['content'=>$content,'site'=>Site::find(1)])->render();
        $this->assertSame(9, substr_count($html, '<video '));
    }

    public function test_editor_view_renders_with_original_fields_and_actions(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        foreach (['hasRole','isCommonUser','isSuperAdministrator','canAdminister'] as $method) $user->shouldReceive($method)->andReturn(false);
        $user->shouldReceive('isManager')->andReturn(true);
        $user->shouldReceive('roleLabel')->andReturn('Gerente de sede');
        $this->actingAs($user);
        $site = Site::findOrFail(1);
        view()->share(['availableSites'=>collect([$site]), 'currentSite'=>$site]);
        $context = Mockery::mock(SiteContext::class);
        $context->shouldReceive('availableFor')->andReturn(new \Illuminate\Database\Eloquent\Collection([$site]));
        $context->shouldReceive('currentFor')->andReturn($site);
        $this->app->instance(SiteContext::class, $context);
        $html = view('manager.site-page-editor', ['site'=>$site,'page'=>$site->page,'media'=>collect()])->render();
        foreach (['save-page','publish-form','site-preview','property-panel','discard-page'] as $id) $this->assertStringContainsString('id="'.$id.'"', $html);
        if (getenv('SITE_EDITOR_EXPORT')) {
            $dir = storage_path('app/private/site-editor-preview');
            if (!is_dir($dir)) mkdir($dir, 0700, true);
            $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
            $editorCss = '<link rel="stylesheet" href="/build/'.$manifest['resources/css/app.css']['file'].'">';
            file_put_contents($dir.'/editor.html', str_replace('</head>', $editorCss.'</head>', $html));
            $preview = view('manager.site-page-template', ['content'=>$this->defaults,'site'=>$site])->render();
            $css = $editorCss.'<link rel="stylesheet" href="/build/'.$manifest['resources/css/nutriliado-public.css']['file'].'">';
            file_put_contents($dir.'/preview.html', str_replace('</head>', $css.'</head>', $preview));
        }
    }
}
