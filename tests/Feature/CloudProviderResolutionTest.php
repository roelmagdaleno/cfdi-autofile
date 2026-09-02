<?php

use App\Cloud\Contracts\CloudDestinations;
use App\Cloud\Contracts\CloudFiles;
use App\Cloud\Contracts\CloudOAuth;
use App\Cloud\Data\CloudProvider;
use App\Cloud\Google\GoogleCloudFiles;
use App\Cloud\Google\GoogleCloudOAuth;
use App\Cloud\Google\GoogleDestinations;
use App\Models\Setting;
use App\Support\AppSettings;

/**
 * What makes adding a provider a case in the enum rather than an edit to the
 * container: every seam is resolved from the saved provider.
 *
 * The global fake has to be forgotten first, since the whole point here is what
 * the container builds on its own.
 */
beforeEach(function (): void {
    app()->forgetInstance(CloudFiles::class);
    app()->forgetInstance(GoogleCloudFiles::class);
});

it('resolves every seam from the saved provider', function (): void {
    Setting::query()->create(['key' => AppSettings::KEY_CLOUD_PROVIDER, 'value' => CloudProvider::GoogleDrive->value]);

    expect(app(CloudFiles::class))->toBeInstanceOf(GoogleCloudFiles::class)
        ->and(app(CloudDestinations::class))->toBeInstanceOf(GoogleDestinations::class)
        ->and(app(CloudOAuth::class))->toBeInstanceOf(GoogleCloudOAuth::class);
});

// An install that never opened the settings screen still has to resolve.
it('resolves the default provider when none was ever saved', function (): void {
    expect(app(CloudFiles::class))->toBeInstanceOf(CloudProvider::default()->filesClass());
});
