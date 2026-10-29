<?php

namespace Kuteka\LaravelImport\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Kuteka\LaravelImport\Enums\StatusImporte;
use Kuteka\LaravelImport\Models\Importe;

/**
 * Resumo de uma importação, seguro para expor em APIs e eventos WebSocket.
 */
class ResultadoImporte implements Arrayable
{
    public function __construct(
        public readonly StatusImporte $status,
        public readonly int $total,
        public readonly int $processadas,
        public readonly int $sucesso,
        public readonly int $falhas,
        public readonly int $ignoradas,
        public readonly float $progresso,
        public readonly float $duracao,
    ) {}

    public static function doImporte(Importe $importe): self
    {
        return new self(
            status: $importe->status,
            total: $importe->total_rows ?? 0,
            processadas: $importe->processed_rows ?? 0,
            sucesso: $importe->successful_rows ?? 0,
            falhas: $importe->failed_rows ?? 0,
            ignoradas: $importe->skipped_rows ?? 0,
            progresso: $importe->porcentagemProgresso(),
            duracao: $importe->duracao(),
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'total' => $this->total,
            'processadas' => $this->processadas,
            'sucesso' => $this->sucesso,
            'falhas' => $this->falhas,
            'ignoradas' => $this->ignoradas,
            'progresso' => $this->progresso,
            'duracao' => $this->duracao,
        ];
    }
}
