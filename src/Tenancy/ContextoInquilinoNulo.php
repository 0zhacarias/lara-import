<?php

namespace Kuteka\LaravelImport\Tenancy;

use Kuteka\LaravelImport\Contracts\ContextoInquilino;

/**
 * Adaptador de inquilino único. Não faz nada.
 */
class ContextoInquilinoNulo implements ContextoInquilino
{
    public function id(): mixed
    {
        return null;
    }

    public function inicializar(mixed $inquilinoId): void
    {
        //
    }
}
