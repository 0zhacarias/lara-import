<?php

namespace Kuteka\LaravelImport\Exceptions;

use Kuteka\LaravelImport\Support\LinhaLeitor;

/**
 * Lançada quando um chunk é abortado (modo estrito / parar_em_erro).
 * A transação do chunk é revertida; os erros viajam com a exceção para
 * poderem ser persistidos depois.
 */
class ExcecaoChunkAbortado extends ExcecaoImporte
{
    /**
     * @param  array<int, array<string, mixed>>  $erros
     */
    public function __construct(
        public readonly array $erros,
        public readonly LinhaLeitor $linha,
    ) {
        parent::__construct(sprintf('Importação abortada: linha inválida %d.', $linha->numero));
    }
}
