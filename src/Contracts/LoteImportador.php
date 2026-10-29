<?php

namespace Kuteka\LaravelImport\Contracts;

/**
 * Capacidade opcional de processamento em lote. Quando um importador
 * implementa esta interface, o motor processa chunks inteiros numa única
 * chamada, em vez de linha a linha.
 */
interface LoteImportador extends Importador
{
    /**
     * @param  array<int, array<string, mixed>>  $linhas  linhas validadas e mapeadas
     */
    public function processarLote(array $linhas): void;
}
