<?php

namespace Kuteka\LaravelImport\Importers;

use Kuteka\LaravelImport\Contracts\Importador;

/**
 * Classe base com padrões sensatos para que os importadores da aplicação
 * implementem apenas o que precisam.
 */
abstract class ImportadorAbstrato implements Importador
{
    public function regras(): array
    {
        return [];
    }

    public function chaveUnica(array $linha): mixed
    {
        return null;
    }

    public function cabecalhos(): array
    {
        return [];
    }

    public function opcoes(): array
    {
        return [];
    }

    /**
     * Sobrescreva para transformar/normalizar uma linha antes da validação.
     */
    public function preparar(array $linha): array
    {
        return $linha;
    }

    /**
     * Chamado uma vez antes de a importação começar (registar hooks,
     * desativar observers, aquecer caches, ...).
     */
    public function aoIniciarImporte(): void
    {
        //
    }

    /**
     * Chamado uma vez depois de a importação terminar, independentemente do resultado.
     */
    public function aoFinalizarImporte(): void
    {
        //
    }
}
