<?php

namespace Kuteka\LaravelImport\Enums;

enum StatusImporte: string
{
    case Pendente = 'pending';
    case Processando = 'processing';
    case Concluido = 'completed';
    case ConcluidoComErros = 'completed_with_errors';
    case Falhou = 'failed';
    case Cancelando = 'cancelling';
    case Cancelado = 'cancelled';

    public function ehFinal(): bool
    {
        return in_array($this, [self::Concluido, self::ConcluidoComErros, self::Falhou, self::Cancelado], true);
    }
}
