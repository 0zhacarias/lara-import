<?php

namespace Kuteka\LaravelImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Kuteka\LaravelImport\Enums\StatusImporte;
use Kuteka\LaravelImport\Events\ImporteCancelado;
use Kuteka\LaravelImport\Events\ImporteConcluido;
use Kuteka\LaravelImport\Events\ImporteConcluidoComErros;
use Kuteka\LaravelImport\Jobs\Concerns\RestauraContextoInquilino;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Services\NotificadorImporte;

/**
 * Finaliza o pipeline: calcula o estado final, dispara o evento final,
 * notifica o utilizador e liberta o bloqueio da importação.
 */
class FinalizarImporte implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RestauraContextoInquilino;
    use SerializesModels;

    public function __construct(public readonly string $importeUuid) {}

    public function handle(NotificadorImporte $notificador): void
    {
        $importe = Importe::query()->where('uuid', $this->importeUuid)->first();

        if ($importe === null) {
            return;
        }

        $this->restaurarContextoInquilino($importe->tenant_id);

        if ($importe->status->ehFinal()) {
            $this->liberarBloqueio($importe);

            return;
        }

        $importador = app($importe->importer);

        try {
            $importador->aoFinalizarImporte();
        } catch (\Throwable $excecao) {
            report($excecao);
        }

        if ($importe->status === StatusImporte::Cancelando) {
            $importe->update([
                'status' => StatusImporte::Cancelado,
                'cancelled_at' => now(),
                'completed_at' => now(),
            ]);

            $importe = $importe->fresh();

            event(new ImporteCancelado($importe));
            $notificador->notificar($importe);
            $this->liberarBloqueio($importe);

            return;
        }

        $importe->update([
            'status' => $importe->failed_rows > 0 ? StatusImporte::ConcluidoComErros : StatusImporte::Concluido,
            'completed_at' => now(),
        ]);

        $importe = $importe->fresh();

        event($importe->failed_rows > 0
            ? new ImporteConcluidoComErros($importe)
            : new ImporteConcluido($importe));

        $notificador->notificar($importe);
        $this->liberarBloqueio($importe);
    }

    public function failed(?\Throwable $excecao): void
    {
        $importe = Importe::query()->where('uuid', $this->importeUuid)->first();

        if ($importe !== null && ! $importe->status->ehFinal()) {
            $importe->update([
                'status' => StatusImporte::Falhou,
                'error_message' => 'A finalização falhou: '.($excecao?->getMessage() ?? 'erro desconhecido'),
                'completed_at' => now(),
            ]);
        }

        if ($importe !== null) {
            $this->liberarBloqueio($importe);
        }
    }

    private function liberarBloqueio(Importe $importe): void
    {
        Cache::lock("importe:{$importe->uuid}", 86400)->forceRelease();
    }
}
