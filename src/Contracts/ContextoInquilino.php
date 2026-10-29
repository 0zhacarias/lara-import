<?php

namespace Kuteka\LaravelImport\Contracts;

/**
 * Resolve o contexto de inquilino das importações e restaura-o dentro
 * dos workers de fila. O package não assume nenhuma biblioteca
 * específica de multi-inquilino.
 */
interface ContextoInquilino
{
    /**
     * Identificador do inquilino atual (null em aplicações de inquilino único).
     */
    public function id(): mixed;

    /**
     * Restaura o contexto de inquilino dentro de um worker. Deve ser seguro
     * chamar quando a biblioteca do adaptador não está instalada.
     */
    public function inicializar(mixed $inquilinoId): void;
}
