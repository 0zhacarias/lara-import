<?php

namespace Kuteka\LaravelImport\Tests\Stubs;

use Kuteka\LaravelImport\Contracts\LoteImportador;
use Kuteka\LaravelImport\Importers\ImportadorAbstrato;

class LoteImportadorSimulado extends ImportadorAbstrato implements LoteImportador
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
        ];
    }

    public function processar(array $linha): void
    {
        static::$importadas[] = $linha;
    }

    public function processarLote(array $linhas): void
    {
        foreach ($linhas as $linha) {
            static::$importadas[] = $linha;
        }
    }

    public function chaveUnica(array $linha): mixed
    {
        return $linha['nif'] ?? null;
    }
}
