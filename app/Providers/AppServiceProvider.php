<?php

namespace App\Providers;

use App\Auth\NullUserProvider;
use App\Cfdi\Parsing\PdfTextExtractor;
use App\Cfdi\Parsing\PrinsFrankPdfTextExtractor;
use App\Cloud\Contracts\CloudOAuth;
use App\Cloud\Contracts\CloudFiles;
use App\Cloud\Google\GoogleAccessToken;
use App\Cloud\Google\GoogleCloudOAuth;
use App\Cloud\Google\GoogleCloudFiles;
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

        // Scoped, not bound: `GoogleAccessToken` memoizes the access token, so a
        // run of twenty invoices performs one token exchange instead of twenty.
        // `GoogleCloudFiles` follows so it shares that one instance.
        $this->app->scoped(GoogleAccessToken::class);
        $this->app->scoped(CloudFiles::class, GoogleCloudFiles::class);
        $this->app->bind(CloudOAuth::class, GoogleCloudOAuth::class);
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
     * There is no login and no user model, but the framework and Livewire resolve
     * the `web` guard anyway (for example when authorizing a file upload).
     * Without a real provider, SessionGuard blows up.
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
