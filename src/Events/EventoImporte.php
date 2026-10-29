<?php

namespace Kuteka\LaravelImport\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Kuteka\LaravelImport\Models\Importe;

abstract class EventoImporte
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public readonly Importe $importe) {}
}
