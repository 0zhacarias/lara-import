<?php

namespace Kuteka\LaravelImport\Services;

use Kuteka\LaravelImport\Contracts\Importador;
use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\DTOs\ResultadoSimulacao;
use Kuteka\LaravelImport\Readers\FabricaLeitor;

/**
 * Simulação síncrona: lê e valida o ficheiro sem persistir nada.
 */
class ServicoSimulacao
{
    public function __construct(
        private readonly FabricaLeitor $leitores,
        private readonly InspetorEstrutura $inspetor,
    ) {}

    public function executar(
        string $caminho,
        string $extensao,
        Importador $importador,
        OpcoesImporte $opcoes,
        int $maximoErros = 500,
        int $maximoAmostras = 5,
    ): ResultadoSimulacao {
        $inicio = microtime(true);

        $estrutura = $this->inspetor->inspecionar($caminho, $extensao, $importador, $opcoes);
        $processador = new LinhaProcessar($importador, $opcoes, $estrutura->cabecalhos);

        $erros = [];
        $amostras = [];
        $linhasValidas = 0;
        $linhasInvalidas = 0;

        foreach ($estrutura->leitor->ler($caminho) as $linha) {
            [$mapeada, $errosLinha] = $processador->processar($linha);

            if (count($amostras) < $maximoAmostras && $mapeada !== null) {
                $amostras[] = ['row_number' => $linha->numero, 'data' => $mapeada];
            }

            if ($errosLinha !== []) {
                $linhasInvalidas++;

                if (count($erros) < $maximoErros) {
                    $erros = [...$erros, ...$errosLinha];
                }

                continue;
            }

            $linhasValidas++;
        }

        return new ResultadoSimulacao(
            cabecalhos: $estrutura->cabecalhos,
            totalLinhas: $estrutura->totalLinhas,
            linhasAmostra: $amostras,
            erros: $erros,
            linhasValidas: $linhasValidas,
            linhasInvalidas: $linhasInvalidas,
            duracao: round(microtime(true) - $inicio, 2),
        );
    }
}
