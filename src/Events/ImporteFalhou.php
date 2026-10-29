<?php

namespace Kuteka\LaravelImport\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ImporteFalhou extends EventoImporte implements ShouldBroadcast
{
    use TransmiteNoCanalImporte;

    protected function dados(): array
    {
        return [
            'importe_id' => $this->importe->uuid,
            'status' => $this->importe->status->value,
            'mensagem_erro' => $this->importe->error_message,
            'total_linhas' => $this->importe->total_rows,
            'linhas_processadas' => $this->importe->processed_rows,
            'linhas_sucesso' => $this->importe->successful_rows,
            'linhas_falhas' => $this->importe->failed_rows,
            'linhas_ignoradas' => $this->importe->skipped_rows,
            'duracao' => $this->importe->duracao(),
        ];
    }
}
