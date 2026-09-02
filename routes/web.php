<?php

use App\Http\Controllers\CloudCallbackController;
use App\Http\Controllers\ConnectCloudController;
use App\Http\Controllers\DownloadDocumentController;
use App\Http\Controllers\DownloadRunController;
use App\Livewire\BrowseDocuments;
use App\Livewire\EditSettings;
use App\Livewire\ManageCounterparties;
use App\Livewire\RunCfdis;
use Illuminate\Support\Facades\Route;

Route::livewire('/', RunCfdis::class)->name('home');
Route::livewire('documents', BrowseDocuments::class)->name('documents.index');
Route::livewire('settings', EditSettings::class)->name('settings.edit');
Route::livewire('counterparties', ManageCounterparties::class)->name('counterparties.index');

// The OAuth handshake for the Google Drive integration. The paths keep Drive's
// name on purpose: the callback URI has to match, byte for byte, the one
// registered on the OAuth client, and a provider added later brings its own
// pair of paths rather than renaming this one out from under Google.
Route::get('drive/connect', ConnectCloudController::class)->name('cloud.google_drive.connect');
Route::get('drive/callback', CloudCallbackController::class)->name('cloud.google_drive.callback');

Route::get('download/{run}', DownloadRunController::class)
    ->whereUuid('run')
    ->middleware('signed')
    ->name('runs.download');

Route::get('documents/{document}/download', DownloadDocumentController::class)
    ->middleware('signed')
    ->name('documents.download');
