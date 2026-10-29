<?php

namespace Kuteka\LaravelImport\Support;

/**
 * Uma única linha física lida de um ficheiro.
 */
class LinhaLeitor
{
    public function __construct(
        public readonly int $numero,
        public readonly array $valores,
    ) {}

    public function estaVazio(): bool
    {
        return count(array_filter($this->valores, fn ($valor) => $valor !== null && $valor !== '')) === 0;
    }
}
