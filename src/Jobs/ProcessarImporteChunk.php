<?php

namespace Kuteka\LaravelImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Kuteka\LaravelImport\DTOs\OpcoesLeitor;
use Kuteka\LaravelImport\Enums\ModoImporte;
use Kuteka\LaravelImport\Enums\StatusChunk;
use Kuteka\LaravelImport\Enums\StatusImporte;
use Kuteka\LaravelImport\Events\ImporteChunkProcessado;
use Kuteka\LaravelImport\Events\ImporteFalhou;
use Kuteka\LaravelImport\Events\ImporteProgressoAtualizado;
use Kuteka\LaravelImport\Exceptions\ExcecaoChunkAbortado;
use Kuteka\LaravelImport\Jobs\Concerns\RestauraContextoInquilino;
use Kuteka\LaravelImport\Jobs\Concerns\TrataFalhaImporte;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Models\ImporteChunk;
use Kuteka\LaravelImport\Models\ImporteErro;
use Kuteka\LaravelImport\Readers\FabricaLeitor;
use Kuteka\LaravelImport\Services\ChunkProcessar;
use Kuteka\LaravelImport\Services\LinhaProcessar;
use Kuteka\LaravelImport\Services\NotificadorImporte;
use Kuteka\LaravelImport\Services\RelatorProgresso;
use Kuteka\LaravelImport\Support\ClassificadorRepeticao;
use Throwable;

/**
 * Processa um chunk dentro de uma única transação e despacha o próximo
 * chunk (ou FinalizarImporte) quando termina. Idempotente: um chunk cujo
 * estado já é terminal é ignorado.
 */
class ProcessarImporteChunk implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RestauraContextoInquilino;
    use SerializesModels;
    use TrataFalhaImporte;

    public function __construct(
        public readonly string $importeUuid,
        public readonly int $numeroChunk,
    ) {}

    public function handle(
        FabricaLeitor $leitores,
        DespachadorJobImporte $despachador,
        NotificadorImporte $notificador,
        RelatorProgresso $relator,
        ClassificadorRepeticao $classificadorRepeticao,
    ): void {
        $importe = Importe::query()->where('uuid', $this->importeUuid)->first();

        if ($importe === null) {
            return;
        }

        $this->restaurarContextoInquilino($importe->tenant_id);

        $chunk = ImporteChunk::query()
            ->where('import_id', $importe->id)
            ->where('chunk_number', $this->numeroChunk)
            ->first();

        if ($chunk === null) {
            return;
        }

        if ($importe->status === StatusImporte::Cancelando) {
            $importe->chunks()
                ->whereIn('status', [StatusChunk::Pendente, StatusChunk::Processando])
                ->update(['status' => StatusChunk::Cancelado->value]);

            $despachador->despacharFinalizacao($importe);

            return;
        }

        if ($importe->status->ehFinal()) {
            return;
        }

        // Idempotência: chunks já processados são ignorados.
        if (in_array($chunk->status, [StatusChunk::Concluido, StatusChunk::Ignorado, StatusChunk::Cancelado], true)) {
            $despachador->despacharProximoOuFinalizar($importe, $chunk);

            return;
        }

        // Serializa o acesso à linha do chunk para evitar processamento duplo.
        $chunk = ImporteChunk::query()->whereKey($chunk->id)->lockForUpdate()->first();

        if ($chunk === null) {
            return;
        }

        if (in_array($chunk->status, [StatusChunk::Concluido, StatusChunk::Ignorado, StatusChunk::Cancelado], true)) {
            $despachador->despacharProximoOuFinalizar($importe, $chunk);

            return;
        }

        $opcoes = $importe->obterOpcoes();

        $chunk->update([
            'status' => StatusChunk::Processando,
            'attempts' => $chunk->attempts + 1,
            'started_at' => now(),
            'error_message' => null,
        ]);

        $importador = app($importe->importer);

        $opcoesLeitor = new OpcoesLeitor(
            temCabecalho: $opcoes->temCabecalho,
            delimitador: $opcoes->delimitador,
            codificacao: $opcoes->codificacao,
            linhaInicio: $chunk->start_row + ($opcoes->temCabecalho ? 1 : 0) + $opcoes->linhasIgnorar,
            linhaFim: $chunk->end_row + ($opcoes->temCabecalho ? 1 : 0) + $opcoes->linhasIgnorar,
            linhasIgnorar: $opcoes->linhasIgnorar,
        );

        $leitor = $leitores->criar($importe->extension, $opcoesLeitor);
        $cabecalhos = $leitor->cabecalhos(Storage::disk($importe->disk)->path($importe->file_path));

        $processador = new ChunkProcessar(
            new LinhaProcessar($importador, $opcoes, $cabecalhos),
            $importador,
            $opcoes,
            $classificadorRepeticao,
        );

        try {
            $contadores = $processador->processar(
                $leitor->ler(Storage::disk($importe->disk)->path($importe->file_path)),
                $chunk,
            );
        } catch (ExcecaoChunkAbortado $excecao) {
            $this->persistirErrosAbortados($importe, $excecao);
            $this->falharImporte($importe, $excecao, $notificador);

            return;
        } catch (Throwable $excecao) {
            if ($this->attempts() < $this->maximoTentativas() - 1 && $classificadorRepeticao->deveRepetir($excecao)) {
                throw $excecao;
            }

            $chunk->update([
                'status' => StatusChunk::Falhou,
                'error_message' => (string) $excecao->getMessage(),
            ]);

            if ($opcoes->modo === ModoImporte::Estrito) {
                $this->falharImporte($importe, $excecao, $notificador);

                return;
            }

            $despachador->despacharProximoOuFinalizar($importe, $chunk);

            return;
        }

        DB::table('imports')->where('id', $importe->id)->incrementEach([
            'processed_rows' => $contadores['processadas'],
            'successful_rows' => $contadores['sucesso'],
            'failed_rows' => $contadores['falhas'],
            'skipped_rows' => $contadores['ignoradas'],
        ]);

        $importe->refresh();

        event(new ImporteChunkProcessado($importe, $chunk, $contadores));

        if ($relator->deveTransmitir($importe)) {
            event(new ImporteProgressoAtualizado(
                $importe,
                $this->numeroChunk,
                (int) ceil($importe->total_rows / max(1, $opcoes->tamanhoChunk)),
            ));
        }

        if ($opcoes->maximoErros !== null && $importe->failed_rows > $opcoes->maximoErros) {
            $importe->update([
                'status' => StatusImporte::Falhou,
                'error_message' => sprintf('Número máximo de erros (%d) atingido.', $opcoes->maximoErros),
                'completed_at' => now(),
            ]);

            event(new ImporteFalhou($importe->fresh()));
            $notificador->notificar($importe->fresh());

            return;
        }

        $despachador->despacharProximoOuFinalizar($importe, $chunk);
    }

    public function failed(?Throwable $excecao): void
    {
        if ($excecao === null) {
            return;
        }

        $importe = Importe::query()->where('uuid', $this->importeUuid)->first();

        if ($importe === null || $importe->status->ehFinal()) {
            return;
        }

        $chunk = ImporteChunk::query()
            ->where('import_id', $importe->id)
            ->where('chunk_number', $this->numeroChunk)
            ->first();

        if ($chunk !== null && ! in_array($chunk->status, [StatusChunk::Concluido, StatusChunk::Ignorado], true)) {
            $chunk->update([
                'status' => StatusChunk::Falhou,
                'error_message' => (string) $excecao->getMessage(),
            ]);
        }

        if ($importe->obterOpcoes()->modo === ModoImporte::Estrito) {
            $this->falharImporte($importe, $excecao, app(NotificadorImporte::class));

            return;
        }

        if ($chunk !== null) {
            app(DespachadorJobImporte::class)->despacharProximoOuFinalizar($importe, $chunk);
        }
    }

    private function persistirErrosAbortados(Importe $importe, ExcecaoChunkAbortado $excecao): void
    {
        $agora = now();

        ImporteErro::insert(array_map(fn (array $erro) => [
            'import_id' => $importe->id,
            'chunk_id' => null,
            'created_at' => $agora,
            ...$erro,
            'data' => json_encode($erro['data'] ?? null),
        ], $excecao->erros));
    }

    private function maximoTentativas(): int
    {
        return $this->job?->maxTries() ?? 1;
    }
}
