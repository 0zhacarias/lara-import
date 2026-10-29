<?php

namespace Kuteka\LaravelImport\Services;

use Kuteka\LaravelImport\Contracts\Importador;
use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\DTOs\OpcoesLeitor;
use Kuteka\LaravelImport\Exceptions\ExcecaoColunasObrigatoriasFaltando;
use Kuteka\LaravelImport\Readers\FabricaLeitor;
use Kuteka\LaravelImport\Support\Estrutura;

/**
 * Valida a estrutura do ficheiro: formato, cabeçalhos, colunas obrigatórias
 * e total de linhas — sem carregar os dados para memória.
 */
class InspetorEstrutura
{
    public function __construct(
        private readonly FabricaLeitor $leitores,
        private readonly ValidadorArquivo $validador,
    ) {}

    public function inspecionar(string $caminho, string $extensao, Importador $importador, OpcoesImporte $opcoes): Estrutura
    {
        $this->validador->validar($caminho, $extensao, $opcoes);

        $leitor = $this->leitores->criar($extensao, new OpcoesLeitor(
            temCabecalho: $opcoes->temCabecalho,
            delimitador: $opcoes->delimitador,
            codificacao: $opcoes->codificacao,
            linhasIgnorar: $opcoes->linhasIgnorar,
        ));

        $cabecalhos = $leitor->cabecalhos($caminho);
        $totalLinhas = $leitor->contarLinhas($caminho);

        $this->garantirColunasObrigatorias($cabecalhos, $importador, $opcoes);

        return new Estrutura($leitor, $cabecalhos, $totalLinhas);
    }

    public function garantirColunasObrigatorias(array $cabecalhos, Importador $importador, OpcoesImporte $opcoes): void
    {
        if (! $opcoes->temCabecalho) {
            return;
        }

        $obrigatorias = [];

        foreach ($importador->regras() as $campo => $regras) {
            $lista = is_array($regras) ? $regras : explode('|', $regras);

            if (in_array('required', $lista, true)) {
                $obrigatorias[] = (string) $campo;
            }
        }

        if ($obrigatorias === []) {
            return;
        }

        $disponiveis = array_map(
            fn ($cabecalho) => LinhaProcessar::resolverNomeCampo((string) $cabecalho, $opcoes),
            array_filter($cabecalhos, fn ($cabecalho) => $cabecalho !== null && $cabecalho !== '')
        );

        $camposMapeados = array_values(array_filter(
            $opcoes->mapeamento,
            fn ($valor, $chave) => is_string($valor) && is_string($chave),
            ARRAY_FILTER_USE_BOTH
        ));

        $faltando = array_diff($obrigatorias, $disponiveis, $camposMapeados);

        if ($faltando !== []) {
            throw new ExcecaoColunasObrigatoriasFaltando(sprintf(
                'O ficheiro não tem as colunas obrigatórias: %s.',
                implode(', ', $faltando)
            ));
        }
    }
}
