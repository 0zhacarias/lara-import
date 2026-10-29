<?php

namespace Kuteka\LaravelImport\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Kuteka\LaravelImport\Contracts\ResolvedorUsuario;

class ResolvedorUsuarioPadrao implements ResolvedorUsuario
{
    public function resolver(mixed $usuarioId): ?Authenticatable
    {
        if ($usuarioId === null || $usuarioId === '') {
            return null;
        }

        $modelo = config('importe.user.model');

        if (! is_string($modelo) || ! class_exists($modelo)) {
            return null;
        }

        $usuario = $modelo::query()->find($usuarioId);

        return $usuario instanceof Authenticatable ? $usuario : null;
    }

    public function atual(): ?Authenticatable
    {
        return Auth::user();
    }
}
