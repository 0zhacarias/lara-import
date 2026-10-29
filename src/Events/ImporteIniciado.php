<?php

namespace Kuteka\LaravelImport\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ImporteIniciado extends EventoImporte implements ShouldBroadcast
{
    use TransmiteNoCanalImporte;

    protected function dados(): array
    {
        return [
            'importe_id' => $this->importe->uuid,
            'status' => $this->importe->status->value,
            'total_linhas' => $this->importe->total_rows,
            'iniciado_em' => $this->importe->started_at?->toIso8601String(),
        ];
    }
}
