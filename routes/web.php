<?php

use App\Http\Controllers\ConnectDriveController;
use App\Http\Controllers\DownloadDocumentController;
use App\Http\Controllers\DownloadRunController;
use App\Http\Controllers\DriveCallbackController;
use App\Livewire\BrowseDocuments;
use App\Livewire\EditSettings;
use App\Livewire\ManageCounterparties;
use App\Livewire\RunCfdis;
use Illuminate\Support\Facades\Route;

Route::livewire('/', RunCfdis::class)->name('home');
Route::livewire('documents', BrowseDocuments::class)->name('documents.index');
Route::livewire('settings', EditSettings::class)->name('settings.edit');
Route::livewire('counterparties', ManageCounterparties::class)->name('counterparties.index');

// The OAuth handshake for the Google Drive integration. The callback URI has to
// match, byte for byte, the one registered on the OAuth client.
Route::get('drive/connect', ConnectDriveController::class)->name('drive.connect');
Route::get('drive/callback', DriveCallbackController::class)->name('drive.callback');

Route::get('download/{run}', DownloadRunController::class)
    ->whereUuid('run')
    ->middleware('signed')
    ->name('runs.download');

Route::get('documents/{document}/download', DownloadDocumentController::class)
    ->middleware('signed')
    ->name('documents.download');
