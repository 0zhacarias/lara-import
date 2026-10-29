<?php

namespace Kuteka\LaravelImport\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Kuteka\LaravelImport\Models\Importe;

class ImporteProgressoAtualizado extends EventoImporte implements ShouldBroadcast
{
    use TransmiteNoCanalImporte;

    public function __construct(
        Importe $importe,
        public readonly ?int $chunkAtual = null,
        public readonly ?int $totalChunks = null,
    ) {
        parent::__construct($importe);
    }

    protected function dados(): array
    {
        return [
            'importe_id' => $this->importe->uuid,
            'status' => $this->importe->status->value,
            'total_linhas' => $this->importe->total_rows,
            'linhas_processadas' => $this->importe->processed_rows,
            'linhas_sucesso' => $this->importe->successful_rows,
            'linhas_falhas' => $this->importe->failed_rows,
            'linhas_ignoradas' => $this->importe->skipped_rows,
            'progresso' => $this->importe->porcentagemProgresso(),
            'chunk_atual' => $this->chunkAtual,
            'total_chunks' => $this->totalChunks,
        ];
    }
}
