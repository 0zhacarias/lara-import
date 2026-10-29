<?php

namespace Kuteka\LaravelImport\Services;

use Illuminate\Support\Facades\Notification as NotificationFacade;
use Kuteka\LaravelImport\Contracts\ResolvedorUsuario;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Notifications\ImporteFinalizadoNotification;

class NotificadorImporte
{
    public function __construct(private readonly ResolvedorUsuario $usuarios) {}

    public function notificar(Importe $importe): void
    {
        if (! $importe->obterOpcoes()->notificarUsuario) {
            return;
        }

        $usuario = $this->usuarios->resolver($importe->user_id);

        if ($usuario === null) {
            return;
        }

        NotificationFacade::send($usuario, new ImporteFinalizadoNotification($importe));
    }
}
