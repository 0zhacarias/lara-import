<?php

namespace Kuteka\LaravelImport\Services;

use Illuminate\Support\Facades\Cache;
use Kuteka\LaravelImport\Models\Importe;

/**
 * Limita as transmissões de progresso: no máximo uma transmissão a cada
 * N segundos / M registos por importação.
 */
class RelatorProgresso
{
    public function deveTransmitir(Importe $importe): bool
    {
        if (! $importe->obterOpcoes()->transmitirProgresso || ! (bool) config('importe.broadcast.enabled', true)) {
            return false;
        }

        $segundosLimitacao = (float) config('importe.broadcast.throttle_seconds', 1);
        $chave = "importe-progresso:{$importe->uuid}";
        $ultima = Cache::get($chave);

        if ($ultima !== null && (microtime(true) - (float) $ultima) < $segundosLimitacao) {
            return false;
        }

        Cache::put($chave, microtime(true), now()->addMinutes(10));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function dados(Importe $importe, ?int $chunkAtual = null, ?int $totalChunks = null): array
    {
        return [
            'importe_id' => $importe->uuid,
            'status' => $importe->status->value,
            'total_linhas' => $importe->total_rows,
            'linhas_processadas' => $importe->processed_rows,
            'linhas_sucesso' => $importe->successful_rows,
            'linhas_falhas' => $importe->failed_rows,
            'linhas_ignoradas' => $importe->skipped_rows,
            'progresso' => $importe->porcentagemProgresso(),
            'chunk_atual' => $chunkAtual,
            'total_chunks' => $totalChunks,
        ];
    }
}
