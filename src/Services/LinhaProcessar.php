<?php

namespace Kuteka\LaravelImport\Services;

use Illuminate\Support\Facades\Validator;
use Kuteka\LaravelImport\Contracts\Importador;
use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\Enums\ModoValidacao;
use Kuteka\LaravelImport\Support\CodigosErro;
use Kuteka\LaravelImport\Support\LinhaLeitor;

/**
 * Aplica o mapeamento de colunas e a validação, entregando as linhas
 * válidas ao importador. Reporta falhas por linha sem lançar exceções.
 */
class LinhaProcessar
{
    public function __construct(
        private readonly Importador $importador,
        private readonly OpcoesImporte $opcoes,
        private readonly array $cabecalhos,
    ) {}

    /**
     * Resolve um cabeçalho do ficheiro (ex.: "Nome Completo") num nome de
     * campo (ex.: "nome") aplicando o mapeamento e os aliases configurados.
     */
    public static function resolverNomeCampo(string $cabecalho, OpcoesImporte $opcoes): string
    {
        $cabecalho = trim($cabecalho);

        foreach ($opcoes->aliases as $campo => $listaAliases) {
            if (is_int($campo)) {
                $listaAliases = [$listaAliases];
            }

            foreach ((array) $listaAliases as $alias) {
                if (strcasecmp((string) $alias, $cabecalho) === 0) {
                    return $campo;
                }
            }
        }

        return $cabecalho;
    }

    public function mapear(LinhaLeitor $linha): array
    {
        $mapeada = [];

        if ($this->opcoes->temCabecalho) {
            foreach ($this->opcoes->mapeamento as $colunaFicheiro => $campo) {
                $indice = $this->indiceCabecalho($colunaFicheiro);

                if ($indice !== null) {
                    $mapeada[$campo] = $linha->valores[$indice] ?? null;
                }
            }

            if (count($mapeada) === 0) {
                foreach ($this->cabecalhos as $indice => $cabecalho) {
                    if ($cabecalho === null || $cabecalho === '') {
                        continue;
                    }

                    $campo = self::resolverNomeCampo((string) $cabecalho, $this->opcoes);
                    $mapeada[$campo] = $linha->valores[$indice] ?? null;
                }
            }
        } else {
            $campos = $this->camposSemCabecalho();

            foreach ($linha->valores as $indice => $valor) {
                $mapeada[$campos[$indice] ?? "column_$indice"] = $valor;
            }
        }

        return $mapeada;
    }

    /**
     * @return array{0: array|null, 1: array} [linhaValidada, erros]
     */
    public function processar(LinhaLeitor $linha): array
    {
        try {
            $mapeada = $this->mapear($linha);
            $mapeada = $this->importador->preparar($mapeada);
        } catch (\Throwable $excecao) {
            return [null, [[
                'row_number' => $linha->numero,
                'column' => null,
                'value' => null,
                'error_code' => CodigosErro::FALHA_MAPEAMENTO,
                'message' => $excecao->getMessage(),
                'data' => ['row' => $linha->valores],
            ]]];
        }

        if ($this->opcoes->modoValidacao !== ModoValidacao::Nenhum) {
            $regras = $this->importador->regras();

            if ($regras !== []) {
                $validador = Validator::make($mapeada, $regras);

                if ($validador->fails()) {
                    $erros = collect($validador->errors()->messages())
                        ->flatMap(function (array $mensagens, string $campo) use ($mapeada, $linha) {
                            return array_map(fn (string $mensagem) => [
                                'row_number' => $linha->numero,
                                'column' => $campo,
                                'value' => $mapeada[$campo] ?? null,
                                'error_code' => CodigosErro::FALHA_VALIDACAO,
                                'message' => $mensagem,
                                'data' => ['row' => $linha->valores],
                            ], $mensagens);
                        })
                        ->values()
                        ->all();

                    return [null, $erros];
                }
            }
        }

        return [$mapeada, []];
    }

    private function indiceCabecalho(string $colunaFicheiro): ?int
    {
        foreach ($this->cabecalhos as $indice => $cabecalho) {
            if ($cabecalho !== null && strcasecmp(trim((string) $cabecalho), trim($colunaFicheiro)) === 0) {
                return $indice;
            }
        }

        return null;
    }

    private function camposSemCabecalho(): array
    {
        return array_values(array_filter(
            array_map(fn ($campo) => is_string($campo) ? $campo : null, array_keys($this->importador->regras()))
        ));
    }
}
