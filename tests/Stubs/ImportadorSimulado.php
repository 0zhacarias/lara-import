<?php

namespace Kuteka\LaravelImport\Tests\Stubs;

use Kuteka\LaravelImport\Importers\ImportadorAbstrato;

class ImportadorSimulado extends ImportadorAbstrato
{
    /** @var array<int, array<string, mixed>> */
    public static array $importadas = [];

    public static function reset(): void
    {
        static::$importadas = [];
    }

    public function regras(): array
    {
        return [
            'nome' => ['required', 'string'],
            'nif' => ['nullable', 'string'],
            'telefone' => ['nullable', 'string'],
        ];
    }

    public function processar(array $linha): void
    {
        static::$importadas[] = $linha;
    }

    public function chaveUnica(array $linha): mixed
    {
        return $linha['nif'] ?? null;
    }

    public function cabecalhos(): array
    {
        return ['nome', 'nif', 'telefone'];
    }
}
