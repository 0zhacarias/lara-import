<?php

namespace Kuteka\LaravelImport\Support;

use Kuteka\LaravelImport\Contracts\LeitorArquivo;

/**
 * Resultado de uma inspeção de estrutura: um leitor aberto mais os
 * cabeçalhos do ficheiro e o total de linhas.
 */
class Estrutura
{
    public function __construct(
        public readonly LeitorArquivo $leitor,
        public readonly array $cabecalhos,
        public readonly int $totalLinhas,
    ) {}
}
