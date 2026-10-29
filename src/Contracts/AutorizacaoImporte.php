<?php

namespace Kuteka\LaravelImport\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Kuteka\LaravelImport\Models\Importe;

/**
 * Autorização ao nível da aplicação para as operações de importação.
 */
interface AutorizacaoImporte
{
    public function podeCriar(?Authenticatable $usuario): bool;

    public function podeVer(?Authenticatable $usuario, Importe $importe): bool;

    public function podeCancelar(?Authenticatable $usuario, Importe $importe): bool;

    public function podeBaixar(?Authenticatable $usuario, Importe $importe): bool;

    public function podeExcluir(?Authenticatable $usuario, Importe $importe): bool;
}
