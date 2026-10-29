<?php

namespace Kuteka\LaravelImport\Readers;

use Kuteka\LaravelImport\Contracts\LeitorArquivo;
use Kuteka\LaravelImport\DTOs\OpcoesLeitor;

abstract class LeitorAbstrato implements LeitorArquivo
{
    protected OpcoesLeitor $opcoes;

    public function __construct()
    {
        $this->opcoes = new OpcoesLeitor;
    }

    public function definirOpcoes(OpcoesLeitor $opcoes): static
    {
        $this->opcoes = $opcoes;

        return $this;
    }

    public function cabecalhos(string $caminho): array
    {
        return [];
    }

    /**
     * Número da linha física onde está o cabeçalho (1-indexado), contando as
     * linhas de metadados ignoradas no topo do ficheiro.
     */
    protected function linhaCabecalho(): int
    {
        return 1 + $this->opcoes->linhasIgnorar;
    }

    /**
     * Quantidade de linhas a ignorar no início do ficheiro: as linhas de
     * metadados mais, quando aplicável, a própria linha de cabeçalho.
     */
    protected function limiteIgnorar(): int
    {
        return $this->opcoes->linhasIgnorar + ($this->opcoes->temCabecalho ? 1 : 0);
    }
}
