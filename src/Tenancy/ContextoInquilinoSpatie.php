<?php

namespace Kuteka\LaravelImport\Tenancy;

use Kuteka\LaravelImport\Contracts\ContextoInquilino;
use Spatie\Multitenancy\Models\Tenant;

/**
 * Adaptador opcional para spatie/laravel-multitenancy. Todas as chamadas
 * são protegidas para o package funcionar sem a biblioteca instalada.
 */
class ContextoInquilinoSpatie implements ContextoInquilino
{
    public function id(): mixed
    {
        if (! class_exists(Tenant::class)) {
            return null;
        }

        $inquilino = Tenant::current();

        return $inquilino?->getKey();
    }

    public function inicializar(mixed $inquilinoId): void
    {
        if ($inquilinoId === null || $inquilinoId === '') {
            return;
        }

        if (! class_exists(Tenant::class)) {
            return;
        }

        $inquilinoAtual = Tenant::current();

        if ($inquilinoAtual !== null && (string) $inquilinoAtual->getKey() === (string) $inquilinoId) {
            return;
        }

        $classeInquilino = config('multitenancy.tenant_model') ?? Tenant::class;

        $inquilino = $classeInquilino::query()->find($inquilinoId);

        if ($inquilino !== null) {
            $inquilino->makeCurrent();
        }
    }
}
