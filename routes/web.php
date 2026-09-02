<?php

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

Route::get('download/{run}', DownloadRunController::class)
    ->whereUuid('run')
    ->middleware('signed')
    ->name('runs.download');

Route::get('documents/{document}/download', DownloadDocumentController::class)
    ->middleware('signed')
    ->name('documents.download');
