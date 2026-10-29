<?php

namespace Kuteka\LaravelImport\Contracts;

/**
 * Importador específico da aplicação. O núcleo do package apenas executa
 * este contrato e nada sabe sobre o domínio que o consome.
 */
interface Importador
{
    /**
     * Regras de validação do Laravel aplicadas a cada linha mapeada.
     */
    public function regras(): array;

    /**
     * Persiste/processa uma única linha validada.
     */
    public function processar(array $linha): void;

    /**
     * Chave única opcional usada pelas estratégias de duplicados (upsert/update).
     */
    public function chaveUnica(array $linha): mixed;

    /**
     * Cabeçalhos esperados (usados na validação de colunas obrigatórias).
     */
    public function cabecalhos(): array;

    /**
     * Opções extras do importador (mapeamento, aliases, tem_cabecalho, ...).
     */
    public function opcoes(): array;
}
