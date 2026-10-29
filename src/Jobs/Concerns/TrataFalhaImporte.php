<?php

namespace Kuteka\LaravelImport\Jobs\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Kuteka\LaravelImport\Enums\StatusImporte;
use Kuteka\LaravelImport\Events\ImporteFalhou;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Services\NotificadorImporte;
use Throwable;

trait TrataFalhaImporte
{
    protected function falharImporte(Importe $importe, Throwable $excecao, NotificadorImporte $notificador): void
    {
        $importe->update([
            'status' => StatusImporte::Falhou,
            'error_message' => Str::limit($excecao->getMessage(), 2000),
            'completed_at' => now(),
        ]);

        $importe = $importe->fresh();

        event(new ImporteFalhou($importe));
        $notificador->notificar($importe);

        Cache::lock("importe:{$importe->uuid}", 86400)->forceRelease();
    }
}
