<?php

namespace Kuteka\LaravelImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Kuteka\LaravelImport\Enums\StatusChunk;
use Kuteka\LaravelImport\Enums\StatusImporte;
use Kuteka\LaravelImport\Events\ImporteCancelado;
use Kuteka\LaravelImport\Events\ImporteIniciado;
use Kuteka\LaravelImport\Exceptions\ExcecaoMaximoLinhasExcedido;
use Kuteka\LaravelImport\Jobs\Concerns\RestauraContextoInquilino;
use Kuteka\LaravelImport\Jobs\Concerns\TrataFalhaImporte;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Models\ImporteChunk;
use Kuteka\LaravelImport\Services\InspetorEstrutura;
use Kuteka\LaravelImport\Services\NotificadorImporte;
use Kuteka\LaravelImport\Support\ClassificadorRepeticao;
use Throwable;

/**
 * Primeiro job do pipeline: valida o ficheiro, conta as linhas, cria os
 * registos de chunks e despacha o primeiro ProcessarImporteChunk.
 */
class ProcessarImporte implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RestauraContextoInquilino;
    use SerializesModels;
    use TrataFalhaImporte;

    public function __construct(public readonly string $importeUuid) {}

    public function handle(
        InspetorEstrutura $inspetor,
        DespachadorJobImporte $despachador,
        NotificadorImporte $notificador,
        ClassificadorRepeticao $classificadorRepeticao,
    ): void {
        $importe = $this->encontrarImporte();

        if ($importe === null) {
            return;
        }

        $this->restaurarContextoInquilino($importe->tenant_id);

        $bloqueio = Cache::lock("importe:{$importe->uuid}", 86400);

        if (! $bloqueio->get()) {
            $this->release(10);

            return;
        }

        $manterBloqueio = false;

        try {
            if ($importe->status->ehFinal()) {
                return;
            }

            if ($importe->status === StatusImporte::Cancelando) {
                $importe->update([
                    'status' => StatusImporte::Cancelado,
                    'cancelled_at' => now(),
                    'completed_at' => now(),
                ]);

                event(new ImporteCancelado($importe->fresh()));
                $notificador->notificar($importe->fresh());

                return;
            }

            $opcoes = $importe->obterOpcoes();

            $importe->update([
                'status' => StatusImporte::Processando,
                'started_at' => now(),
            ]);

            event(new ImporteIniciado($importe->fresh()));

            $importador = app($importe->importer);
            $importador->aoIniciarImporte();

            // Repetição de chunks falhados: os registos de chunks já existem.
            if ($importe->chunks()->exists()) {
                $primeiro = $importe->chunks()
                    ->where('status', StatusChunk::Pendente)
                    ->orderBy('chunk_number')
                    ->first();

                $manterBloqueio = true;

                if ($primeiro !== null) {
                    $despachador->despacharChunk($importe, $primeiro->chunk_number);
                } else {
                    $despachador->despacharFinalizacao($importe);
                }

                return;
            }

            $caminho = Storage::disk($importe->disk)->path($importe->file_path);

            $estrutura = $inspetor->inspecionar($caminho, $importe->extension, $importador, $opcoes);

            if ($opcoes->maximoLinhas !== null && $estrutura->totalLinhas > $opcoes->maximoLinhas) {
                throw new ExcecaoMaximoLinhasExcedido(sprintf(
                    'O ficheiro contém %d linhas, excedendo o limite de %d linhas.',
                    $estrutura->totalLinhas,
                    $opcoes->maximoLinhas
                ));
            }

            $importe->update(['total_rows' => $estrutura->totalLinhas]);

            if ($estrutura->totalLinhas === 0) {
                $manterBloqueio = true;
                $despachador->despacharFinalizacao($importe);

                return;
            }

            $agora = now();
            $numeroChunk = 1;
            $linhas = [];

            for ($inicio = 1; $inicio <= $estrutura->totalLinhas; $inicio += $opcoes->tamanhoChunk) {
                $linhas[] = [
                    'import_id' => $importe->id,
                    'chunk_number' => $numeroChunk,
                    'start_row' => $inicio,
                    'end_row' => min($inicio + $opcoes->tamanhoChunk - 1, $estrutura->totalLinhas),
                    'status' => StatusChunk::Pendente->value,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
                $numeroChunk++;
            }

            ImporteChunk::insert($linhas);

            $manterBloqueio = true;
            $despachador->despacharChunk($importe, 1);
        } catch (Throwable $excecao) {
            if ($this->attempts() < $this->maximoTentativas() - 1 && $classificadorRepeticao->deveRepetir($excecao)) {
                $manterBloqueio = true;

                throw $excecao;
            }

            $this->falharImporte($importe, $excecao, $notificador);
        } finally {
            if (! $manterBloqueio) {
                $bloqueio->release();
            }
        }
    }

    public function failed(?Throwable $excecao): void
    {
        if ($excecao === null) {
            return;
        }

        $importe = $this->encontrarImporte();

        if ($importe === null || $importe->status->ehFinal()) {
            return;
        }

        $this->falharImporte($importe, $excecao, app(NotificadorImporte::class));
    }

    private function maximoTentativas(): int
    {
        return $this->job?->maxTries() ?? 1;
    }

    private function encontrarImporte(): ?Importe
    {
        return Importe::query()->where('uuid', $this->importeUuid)->first();
    }
}
