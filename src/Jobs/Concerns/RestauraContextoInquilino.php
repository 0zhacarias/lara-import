<?php

namespace Kuteka\LaravelImport\Jobs\Concerns;

use Kuteka\LaravelImport\Contracts\ContextoInquilino;

/**
 * Restaura o contexto de inquilino dentro dos workers de fila. Crítico
 * para aplicações multi-inquilino: o worker precisa de saber a que
 * inquilino uma importação pertence muito depois de o pedido HTTP
 * original ter terminado.
 */
trait RestauraContextoInquilino
{
    protected function restaurarContextoInquilino(?string $inquilinoId): void
    {
        if ($inquilinoId === null || $inquilinoId === '') {
            return;
        }

        app(ContextoInquilino::class)->inicializar($inquilinoId);
    }
}
