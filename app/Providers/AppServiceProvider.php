<?php

namespace App\Providers;

use App\Support\SiteContext;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $user = request()->user();
            if (! $user) {
                return;
            }

            $siteContext = app(SiteContext::class);
            $view->with([
                'availableSites' => $siteContext->availableFor($user),
                'currentSite' => $siteContext->currentFor(request(), $user),
            ]);
        });
    }
}
