<?php

namespace Kuteka\LaravelImport\Readers;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Limita o carregamento de células a uma janela de linhas para que a
 * planilha seja lida em modo streaming.
 */
class FiltroJanelaLeitura implements IReadFilter
{
    public function __construct(
        private readonly int $linhaInicio,
        private readonly ?int $linhaFim = null,
    ) {}

    public function readCell($colunaEndereco, $linha, $nomePlanilha = ''): bool
    {
        return $linha >= $this->linhaInicio && ($this->linhaFim === null || $linha <= $this->linhaFim);
    }
}
