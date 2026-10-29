<?php

namespace Kuteka\LaravelImport\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolve o modelo de utilizador notificável. O package nunca assume
 * App\Models\User.
 */
interface ResolvedorUsuario
{
    public function resolver(mixed $usuarioId): ?Authenticatable;

    public function atual(): ?Authenticatable;
}
