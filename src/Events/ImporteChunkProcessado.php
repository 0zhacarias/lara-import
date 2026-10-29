<?php

namespace Kuteka\LaravelImport\Events;

use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Models\ImporteChunk;

/**
 * Evento interno disparado após cada chunk processado. Não é transmitido.
 * Útil para auditoria, métricas e listeners personalizados da aplicação.
 */
class ImporteChunkProcessado extends EventoImporte
{
    public function __construct(
        Importe $importe,
        public readonly ImporteChunk $chunk,
        public readonly array $contadores,
    ) {
        parent::__construct($importe);
    }
}
