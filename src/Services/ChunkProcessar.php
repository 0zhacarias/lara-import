<?php

namespace Kuteka\LaravelImport\Services;

use Illuminate\Support\Facades\DB;
use Kuteka\LaravelImport\Contracts\Importador;
use Kuteka\LaravelImport\Contracts\LoteImportador;
use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\Enums\ModoImporte;
use Kuteka\LaravelImport\Enums\StatusChunk;
use Kuteka\LaravelImport\Exceptions\ExcecaoChunkAbortado;
use Kuteka\LaravelImport\Models\ImporteChunk;
use Kuteka\LaravelImport\Models\ImporteErro;
use Kuteka\LaravelImport\Support\ClassificadorRepeticao;
use Kuteka\LaravelImport\Support\CodigosErro;
use Kuteka\LaravelImport\Support\LinhaLeitor;

/**
 * Processa um único chunk dentro de uma transação de base de dados:
 * BEGIN → validar/processar linhas → persistir erros → COMMIT.
 */
class ChunkProcessar
{
    public function __construct(
        private readonly LinhaProcessar $linhaProcessar,
        private readonly Importador $importador,
        private readonly OpcoesImporte $opcoes,
        private readonly ClassificadorRepeticao $classificadorRepeticao,
    ) {}

    /**
     * @param  iterable<int, LinhaLeitor>  $linhas
     * @return array{processadas: int, sucesso: int, falhas: int, ignoradas: int}
     */
    public function processar(iterable $linhas, ImporteChunk $chunk): array
    {
        return DB::transaction(function () use ($linhas, $chunk) {
            $processadas = 0;
            $sucesso = 0;
            $falhas = 0;
            $ignoradas = 0;
            $erros = [];
            $lote = [];
            $ehLote = $this->importador instanceof LoteImportador;
            $abortarEmErro = $this->opcoes->modo === ModoImporte::Estrito || $this->opcoes->pararEmErro;

            foreach ($linhas as $linha) {
                if ($linha->estaVazio()) {
                    $ignoradas++;

                    continue;
                }

                [$mapeada, $errosLinha] = $this->linhaProcessar->processar($linha);

                if ($errosLinha !== []) {
                    $falhas++;
                    $processadas++;
                    $erros = [...$erros, ...$errosLinha];

                    if ($abortarEmErro) {
                        throw new ExcecaoChunkAbortado($erros, $linha);
                    }

                    continue;
                }

                if ($ehLote) {
                    $lote[] = $mapeada;
                } else {
                    try {
                        $this->importador->processar($mapeada);
                        $sucesso++;
                    } catch (\Throwable $excecao) {
                        if ($this->classificadorRepeticao->deveRepetir($excecao)) {
                            throw $excecao;
                        }

                        $falhas++;
                        $erros[] = [
                            'row_number' => $linha->numero,
                            'column' => null,
                            'value' => null,
                            'error_code' => CodigosErro::FALHA_PROCESSAMENTO,
                            'message' => $excecao->getMessage(),
                            'data' => ['row' => $linha->valores],
                        ];

                        if ($abortarEmErro) {
                            throw new ExcecaoChunkAbortado($erros, $linha);
                        }
                    }
                }

                $processadas++;
            }

            if ($ehLote && $lote !== []) {
                $this->importador->processarLote($lote);
                $sucesso += count($lote);
            }

            if ($erros !== []) {
                $agora = now();

                ImporteErro::insert(array_map(fn (array $erro) => [
                    'import_id' => $chunk->import_id,
                    'chunk_id' => $chunk->id,
                    'created_at' => $agora,
                    ...$erro,
                    'data' => json_encode($erro['data'] ?? null),
                ], $erros));
            }

            $chunk->update([
                'status' => StatusChunk::Concluido,
                'processed_rows' => $processadas,
                'successful_rows' => $sucesso,
                'failed_rows' => $falhas,
                'completed_at' => now(),
            ]);

            return compact('processadas', 'sucesso', 'falhas', 'ignoradas');
        });
    }
}
