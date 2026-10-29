<?php

namespace Kuteka\LaravelImport\Jobs;

use Kuteka\LaravelImport\Enums\StatusChunk;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Models\ImporteChunk;

/**
 * Despacha os jobs de chunk sequencialmente: cada job de chunk despacha o
 * seguinte quando termina, mantendo os payloads da fila pequenos e
 * permitindo cancelamento seguro entre chunks.
 */
class DespachadorJobImporte
{
    public function despacharChunk(Importe $importe, int $numeroChunk): void
    {
        $opcoes = $importe->obterOpcoes();

        $job = new ProcessarImporteChunk($importe->uuid, $numeroChunk);
        $job->tries = $opcoes->tentativas;
        $job->timeout = $opcoes->tempoLimite;
        $job->backoff = $opcoes->atraso;
        $job->onConnection($opcoes->conexaoFila);
        $job->onQueue($opcoes->fila);

        dispatch($job);
    }

    public function despacharFinalizacao(Importe $importe): void
    {
        $opcoes = $importe->obterOpcoes();

        $job = new FinalizarImporte($importe->uuid);
        $job->tries = max(1, $opcoes->tentativas);
        $job->timeout = $opcoes->tempoLimite;
        $job->backoff = $opcoes->atraso;
        $job->onConnection($opcoes->conexaoFila);
        $job->onQueue($opcoes->fila);

        dispatch($job);
    }

    public function despacharProximoOuFinalizar(Importe $importe, ImporteChunk $atual): void
    {
        $proximo = ImporteChunk::query()
            ->where('import_id', $importe->id)
            ->where('chunk_number', '>', $atual->chunk_number)
            ->orderBy('chunk_number')
            ->first();

        if ($proximo === null) {
            $this->despacharFinalizacao($importe);

            return;
        }

        while (in_array($proximo->status, [StatusChunk::Concluido, StatusChunk::Ignorado, StatusChunk::Cancelado], true)) {
            $proximo = ImporteChunk::query()
                ->where('import_id', $importe->id)
                ->where('chunk_number', '>', $proximo->chunk_number)
                ->orderBy('chunk_number')
                ->first();

            if ($proximo === null) {
                $this->despacharFinalizacao($importe);

                return;
            }
        }

        $this->despacharChunk($importe, $proximo->chunk_number);
    }
}
