<?php

namespace Kuteka\LaravelImport\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Queue\SerializesModels;

/**
 * Transmite o estado da importação no canal privado importes.{uuid}.
 * Depende apenas do Broadcasting do Laravel — nunca de uma implementação
 * específica de servidor WebSocket.
 */
trait TransmiteNoCanalImporte
{
    use SerializesModels;

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("importes.{$this->importe->uuid}")];
    }

    public function broadcastAs(): string
    {
        return class_basename(static::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->dados();
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('importe.broadcast.enabled', true)
            && $this->importe->obterOpcoes()->transmitirProgresso;
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function dados(): array;
}
