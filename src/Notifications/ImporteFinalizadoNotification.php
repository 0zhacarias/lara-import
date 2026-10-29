<?php

namespace Kuteka\LaravelImport\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Kuteka\LaravelImport\Models\Importe;

class ImporteFinalizadoNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Importe $importe) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notificavel): array
    {
        $canais = $this->importe->obterOpcoes()->canaisNotificacao;

        if ($canais === []) {
            $canais = (array) config('importe.notifications.channels', ['database', 'mail']);
        }

        return $canais;
    }

    public function toMail(object $notificavel): MailMessage
    {
        $mensagem = (new MailMessage)
            ->subject("Importação: {$this->importe->original_name}")
            ->greeting('Olá!')
            ->line('A sua importação terminou.')
            ->line("Arquivo: {$this->importe->original_name}")
            ->line("Status: {$this->importe->status->value}")
            ->line("Total: {$this->importe->total_rows}")
            ->line("Sucesso: {$this->importe->successful_rows}")
            ->line("Erros: {$this->importe->failed_rows}")
            ->line('Data: '.$this->importe->completed_at?->format('d/m/Y H:i'));

        $url = $this->link();

        return $url === null
            ? $mensagem
            : $mensagem->action('Ver detalhes', $url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notificavel): array
    {
        return [
            'importe_id' => $this->importe->uuid,
            'nome_arquivo' => $this->importe->original_name,
            'status' => $this->importe->status->value,
            'total' => $this->importe->total_rows,
            'sucesso' => $this->importe->successful_rows,
            'falhas' => $this->importe->failed_rows,
            'concluido_em' => $this->importe->completed_at?->toIso8601String(),
            'link' => $this->link(),
        ];
    }

    public function toDatabase(object $notificavel): array
    {
        return $this->toArray($notificavel);
    }

    private function link(): ?string
    {
        $nomeRota = (string) config('importe.notifications.link_route', 'importes.show');

        if ($nomeRota === '' || ! Route::has($nomeRota)) {
            return null;
        }

        return route($nomeRota, ['importe' => $this->importe->uuid]);
    }
}
