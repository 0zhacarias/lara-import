<?php

namespace Kuteka\LaravelImport\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ImporteConcluidoComErros extends ImporteConcluido implements ShouldBroadcast
{
    use TransmiteNoCanalImporte;

    protected function dados(): array
    {
        return $this->resumo();
    }
}
