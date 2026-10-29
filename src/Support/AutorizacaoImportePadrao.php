<?php

namespace Kuteka\LaravelImport\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Kuteka\LaravelImport\Contracts\AutorizacaoImporte;
use Kuteka\LaravelImport\Models\Importe;

/**
 * Autorização padrão: utilizadores autenticados podem criar importações
 * e apenas o dono (ou a CLI) pode ver/cancelar/baixar/excluir as suas.
 */
class AutorizacaoImportePadrao implements AutorizacaoImporte
{
    public function podeCriar(?Authenticatable $usuario): bool
    {
        return $usuario !== null;
    }

    public function podeVer(?Authenticatable $usuario, Importe $importe): bool
    {
        return $this->ehProprietario($usuario, $importe);
    }

    public function podeCancelar(?Authenticatable $usuario, Importe $importe): bool
    {
        return $this->ehProprietario($usuario, $importe);
    }

    public function podeBaixar(?Authenticatable $usuario, Importe $importe): bool
    {
        return $this->ehProprietario($usuario, $importe);
    }

    public function podeExcluir(?Authenticatable $usuario, Importe $importe): bool
    {
        return $this->ehProprietario($usuario, $importe);
    }

    private function ehProprietario(?Authenticatable $usuario, Importe $importe): bool
    {
        if ($usuario === null) {
            return true; // contexto de CLI / background
        }

        return (string) $importe->user_id === (string) $usuario->getAuthIdentifier();
    }

    public static function usuario(): ?Authenticatable
    {
        return Auth::user();
    }
}
