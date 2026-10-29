<?php

namespace Kuteka\LaravelImport\Tests;

use Kuteka\LaravelImport\Contracts\ContextoInquilino;

class ContextoInquilinoFalso implements ContextoInquilino
{
    public static mixed $idAtual = null;

    /** @var array<int, mixed> */
    public static array $inicializados = [];

    public static function reset(): void
    {
        static::$idAtual = null;
        static::$inicializados = [];
    }

    public function id(): mixed
    {
        return static::$idAtual;
    }

    public function inicializar(mixed $inquilinoId): void
    {
        static::$inicializados[] = $inquilinoId;
    }
}
