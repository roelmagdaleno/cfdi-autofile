<?php

use App\Http\Controllers\DownloadDocumentController;
use App\Http\Controllers\DownloadRunController;
use App\Livewire\BrowseDocuments;
use App\Livewire\EditSettings;
use App\Livewire\ManageCounterparties;
use App\Livewire\RunCfdis;
use Illuminate\Support\Facades\Route;

Route::livewire('/', RunCfdis::class)->name('home');
Route::livewire('documentos', BrowseDocuments::class)->name('documents.index');
Route::livewire('ajustes', EditSettings::class)->name('settings.edit');
Route::livewire('contrapartes', ManageCounterparties::class)->name('counterparties.index');

Route::get('descargar/{run}', DownloadRunController::class)
    ->whereUuid('run')
    ->middleware('signed')
    ->name('runs.download');

Route::get('documentos/{document}/descargar', DownloadDocumentController::class)
    ->middleware('signed')
    ->name('documents.download');
