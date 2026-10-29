<?php

namespace Kuteka\LaravelImport\Enums;

enum StatusChunk: string
{
    case Pendente = 'pending';
    case Processando = 'processing';
    case Concluido = 'completed';
    case Falhou = 'failed';
    case Cancelado = 'cancelled';
    case Ignorado = 'skipped';
}
