<?php

namespace Kuteka\LaravelImport\Contracts;

use Kuteka\LaravelImport\DTOs\OpcoesLeitor;

/**
 * Lê um ficheiro em modo streaming. As implementações nunca devem
 * carregar o ficheiro inteiro em memória desnecessariamente.
 */
interface LeitorArquivo
{
    public function suporta(string $extensao): bool;

    public function definirOpcoes(OpcoesLeitor $opcoes): static;

    /**
     * Devolve instâncias de LinhaLeitor para a janela configurada
     * (número físico da linha + valores brutos).
     */
    public function ler(string $caminho): iterable;

    /**
     * Linha de cabeçalho do ficheiro (array vazio quando o ficheiro não
     * tem cabeçalho).
     */
    public function cabecalhos(string $caminho): array;

    /**
     * Número de linhas de dados (excluindo o cabeçalho quando temCabecalho é true).
     */
    public function contarLinhas(string $caminho): int;
}
