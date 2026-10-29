<?php

namespace Kuteka\LaravelImport\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Resultado de uma simulação síncrona: valida a estrutura e as linhas
 * sem persistir nada.
 */
class ResultadoSimulacao implements Arrayable
{
    public function __construct(
        public readonly array $cabecalhos,
        public readonly int $totalLinhas,
        public readonly array $linhasAmostra,
        public readonly array $erros,
        public readonly int $linhasValidas,
        public readonly int $linhasInvalidas,
        public readonly float $duracao,
    ) {}

    public function toArray(): array
    {
        return [
            'cabecalhos' => $this->cabecalhos,
            'total_linhas' => $this->totalLinhas,
            'linhas_amostra' => $this->linhasAmostra,
            'erros' => $this->erros,
            'linhas_validas' => $this->linhasValidas,
            'linhas_invalidas' => $this->linhasInvalidas,
            'duracao' => $this->duracao,
        ];
    }
}
