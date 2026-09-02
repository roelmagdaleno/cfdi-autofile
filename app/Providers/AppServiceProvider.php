<?php

namespace App\Providers;

use App\Auth\NullUserProvider;
use App\Cfdi\Parsing\PdfTextExtractor;
use App\Cfdi\Parsing\PrinsFrankPdfTextExtractor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfTextExtractor::class, PrinsFrankPdfTextExtractor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureNullAuth();
    }

    /**
     * No hay login ni modelo de usuarios, pero el framework y Livewire resuelven
     * el guard `web` de todos modos (por ejemplo al autorizar una subida de
     * archivos). Sin un provider de verdad, SessionGuard revienta.
     */
    protected function configureNullAuth(): void
    {
        Auth::provider('none', fn (): NullUserProvider => new NullUserProvider);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

    }
}
