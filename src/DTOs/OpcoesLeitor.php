<?php

namespace Kuteka\LaravelImport\DTOs;

/**
 * Configuração da janela de leitura passada às implementações de
 * LeitorArquivo através da FabricaLeitor.
 */
class OpcoesLeitor
{
    public function __construct(
        public readonly bool $temCabecalho = true,
        public readonly ?string $delimitador = null,
        public readonly ?string $codificacao = null,
        public readonly ?int $linhaInicio = null,
        public readonly ?int $linhaFim = null,
        public readonly int $linhasIgnorar = 0,
    ) {}

    public function com(
        ?bool $temCabecalho = null,
        ?string $delimitador = null,
        ?string $codificacao = null,
        ?int $linhaInicio = null,
        ?int $linhaFim = null,
        ?int $linhasIgnorar = null,
    ): self {
        return new self(
            temCabecalho: $temCabecalho ?? $this->temCabecalho,
            delimitador: $delimitador ?? $this->delimitador,
            codificacao: $codificacao ?? $this->codificacao,
            linhaInicio: $linhaInicio ?? $this->linhaInicio,
            linhaFim: $linhaFim ?? $this->linhaFim,
            linhasIgnorar: $linhasIgnorar ?? $this->linhasIgnorar,
        );
    }
}
