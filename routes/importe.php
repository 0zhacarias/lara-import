<?php

use Illuminate\Support\Facades\Route;
use Kuteka\LaravelImport\Http\Controllers\ImporteController;

Route::middleware(config('importe.routes.middleware', ['web', 'auth']))
    ->prefix(config('importe.routes.prefix', 'importes'))
    ->as(config('importe.routes.as', 'importes.'))
    ->group(function (): void {
        Route::get('/', [ImporteController::class, 'index'])->name('index');
        Route::post('/', [ImporteController::class, 'store'])->name('store');
        Route::get('/{importe}', [ImporteController::class, 'show'])->name('show');
        Route::post('/{importe}/cancelar', [ImporteController::class, 'cancelar'])->name('cancelar');
        Route::post('/{importe}/repetir', [ImporteController::class, 'repetir'])->name('repetir');
        Route::get('/{importe}/erros', [ImporteController::class, 'erros'])->name('erros');
        Route::get('/{importe}/erros/exporte', [ImporteController::class, 'exportarErros'])->name('erros.exporte');
        Route::delete('/{importe}', [ImporteController::class, 'destroy'])->name('destroy');
    });
