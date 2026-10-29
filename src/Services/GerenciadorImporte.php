<?php

namespace Kuteka\LaravelImport\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kuteka\LaravelImport\Contracts\AutorizacaoImporte;
use Kuteka\LaravelImport\Contracts\ContextoInquilino;
use Kuteka\LaravelImport\Contracts\Importador;
use Kuteka\LaravelImport\Contracts\ResolvedorUsuario;
use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\DTOs\ResultadoSimulacao;
use Kuteka\LaravelImport\Enums\EscopoRepeticao;
use Kuteka\LaravelImport\Enums\StatusChunk;
use Kuteka\LaravelImport\Enums\StatusImporte;
use Kuteka\LaravelImport\Events\ImporteCriado;
use Kuteka\LaravelImport\Exceptions\ExcecaoImporte;
use Kuteka\LaravelImport\Exceptions\ExcecaoImporteNaoEncontrado;
use Kuteka\LaravelImport\Exceptions\ExcecaoImporteNaoRepetivel;
use Kuteka\LaravelImport\Jobs\ProcessarImporte;
use Kuteka\LaravelImport\Models\Importe;

/**
 * Ponto de entrada principal do package.
 *
 *     $importe = GerenciadorImporte::criar(
 *         arquivo: $pedido->file('arquivo'),
 *         importador: ImportadorProduto::class,
 *         opcoes: OpcoesImporte::criar()->tamanhoChunk(1000),
 *     );
 */
class GerenciadorImporte
{
    public function __construct(
        private readonly ValidadorArquivo $validador,
        private readonly AutorizacaoImporte $autorizacao,
        private readonly ResolvedorUsuario $usuarios,
        private readonly ContextoInquilino $inquilinato,
        private readonly ServicoSimulacao $simulacao,
    ) {}

    public static function criar(UploadedFile|string $arquivo, Importador|string $importador, OpcoesImporte|array|null $opcoes = null): Importe
    {
        return app(static::class)->criarImporte($arquivo, $importador, $opcoes);
    }

    public static function despachar(Importe $importe): void
    {
        app(static::class)->despacharImporte($importe);
    }

    public static function status(string $uuid): Importe
    {
        return app(static::class)->encontrar($uuid);
    }

    public static function encontrar(string $uuid): Importe
    {
        return app(static::class)->encontrarImporte($uuid);
    }

    public static function cancelar(string $uuid): Importe
    {
        return app(static::class)->cancelarImporte($uuid);
    }

    public static function repetir(string $uuid, EscopoRepeticao $escopo = EscopoRepeticao::Inteiro): Importe
    {
        return app(static::class)->repetirImporte($uuid, $escopo);
    }

    public static function excluir(string $uuid): void
    {
        app(static::class)->excluirImporte($uuid);
    }

    public static function simular(UploadedFile|string $arquivo, Importador|string $importador, OpcoesImporte|array|null $opcoes = null): ResultadoSimulacao
    {
        return app(static::class)->simularImporte($arquivo, $importador, $opcoes);
    }

    private function criarImporte(UploadedFile|string $arquivo, Importador|string $importador, OpcoesImporte|array|null $opcoes): Importe
    {
        $usuario = $this->usuarios->atual();

        $this->garantirPodeCriar($usuario);

        $importador = $this->resolverImportador($importador);
        $opcoes = $this->normalizarOpcoes($opcoes, $importador);

        $disco = (string) config('importe.disk', 'local');
        $diretorio = (string) config('importe.path', 'imports');

        if ($arquivo instanceof UploadedFile) {
            $extensao = $this->validador->extensaoDe($arquivo->getClientOriginalName());

            if ($extensao === '') {
                throw new ExcecaoImporte('O ficheiro enviado não tem uma extensão válida.');
            }

            $this->validador->validar($arquivo->getRealPath(), $extensao, $opcoes);

            $nomeGuardado = (string) Str::ulid().'.'.$extensao;
            $caminhoGuardado = $arquivo->storeAs($diretorio, $nomeGuardado, ['disk' => $disco]);

            $importe = $this->criarRegistro(
                usuario: $usuario?->getAuthIdentifier(),
                nomeOriginal: $arquivo->getClientOriginalName(),
                caminhoGuardado: $caminhoGuardado,
                disco: $disco,
                tipoMime: $arquivo->getClientMimeType(),
                extensao: $extensao,
                tamanho: (int) $arquivo->getSize(),
                importador: $importador,
                opcoes: $opcoes,
            );
        } else {
            $extensao = $this->validador->extensaoDe(basename($arquivo));
            $this->validador->validar($arquivo, $extensao, $opcoes);

            $nomeGuardado = (string) Str::ulid().'.'.$extensao;
            $caminhoGuardado = Storage::disk($disco)->putFileAs($diretorio, new File($arquivo), $nomeGuardado);

            $importe = $this->criarRegistro(
                usuario: $usuario?->getAuthIdentifier(),
                nomeOriginal: basename($arquivo),
                caminhoGuardado: $caminhoGuardado,
                disco: $disco,
                tipoMime: (new \finfo(FILEINFO_MIME_TYPE))->file($arquivo) ?: null,
                extensao: $extensao,
                tamanho: (int) filesize($arquivo),
                importador: $importador,
                opcoes: $opcoes,
            );
        }

        event(new ImporteCriado($importe));

        $this->despacharImporte($importe);

        return $importe->fresh();
    }

    private function despacharImporte(Importe $importe): void
    {
        $opcoes = $importe->obterOpcoes();

        $job = new ProcessarImporte($importe->uuid);
        $job->tries = $opcoes->tentativas;
        $job->timeout = $opcoes->tempoLimite;
        $job->backoff = $opcoes->atraso;
        $job->onConnection($opcoes->conexaoFila);
        $job->onQueue($opcoes->fila);

        dispatch($job);
    }

    private function encontrarImporte(string $uuid): Importe
    {
        $importe = Importe::query()->where('uuid', $uuid)->first();

        if ($importe === null) {
            throw new ExcecaoImporteNaoEncontrado("A importação [{$uuid}] não foi encontrada.");
        }

        if (! $this->autorizacao->podeVer($this->usuarios->atual(), $importe)) {
            throw new AuthorizationException('Não está autorizado a ver esta importação.');
        }

        return $importe;
    }

    private function cancelarImporte(string $uuid): Importe
    {
        $importe = $this->encontrarImporte($uuid);

        if (! $this->autorizacao->podeCancelar($this->usuarios->atual(), $importe)) {
            throw new AuthorizationException('Não está autorizado a cancelar esta importação.');
        }

        if ($importe->status === StatusImporte::Cancelando) {
            return $importe;
        }

        if ($importe->status->ehFinal()) {
            throw new ExcecaoImporteNaoRepetivel('Apenas importações pendentes ou em processamento podem ser canceladas.');
        }

        $importe->update(['status' => StatusImporte::Cancelando]);

        return $importe->fresh();
    }

    private function repetirImporte(string $uuid, EscopoRepeticao $escopo): Importe
    {
        $importe = $this->encontrarImporte($uuid);

        if (! $this->autorizacao->podeCriar($this->usuarios->atual())) {
            throw new AuthorizationException('Não está autorizado a repetir esta importação.');
        }

        if (! $importe->status->ehFinal()) {
            throw new ExcecaoImporteNaoRepetivel('Apenas importações terminadas podem ser repetidas.');
        }

        if ($escopo === EscopoRepeticao::Inteiro) {
            $importe->erros()->delete();
            $importe->chunks()->delete();

            $importe->update([
                'status' => StatusImporte::Pendente,
                'total_rows' => 0,
                'processed_rows' => 0,
                'successful_rows' => 0,
                'failed_rows' => 0,
                'skipped_rows' => 0,
                'started_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'error_message' => null,
            ]);
        } else {
            $importe->chunks()
                ->where('status', StatusChunk::Falhou)
                ->update(['status' => StatusChunk::Pendente, 'attempts' => 0, 'error_message' => null]);

            $importe->update([
                'status' => StatusImporte::Pendente,
                'completed_at' => null,
                'cancelled_at' => null,
                'error_message' => null,
            ]);
        }

        $this->despacharImporte($importe);

        return $importe->fresh();
    }

    private function excluirImporte(string $uuid): void
    {
        $importe = Importe::query()->where('uuid', $uuid)->first();

        if ($importe === null) {
            throw new ExcecaoImporteNaoEncontrado("A importação [{$uuid}] não foi encontrada.");
        }

        if (! $this->autorizacao->podeExcluir($this->usuarios->atual(), $importe)) {
            throw new AuthorizationException('Não está autorizado a excluir esta importação.');
        }

        Storage::disk($importe->disk)->delete($importe->file_path);

        $importe->delete();
    }

    private function simularImporte(UploadedFile|string $arquivo, Importador|string $importador, OpcoesImporte|array|null $opcoes): ResultadoSimulacao
    {
        $importador = $this->resolverImportador($importador);
        $opcoes = $this->normalizarOpcoes($opcoes, $importador);

        if ($arquivo instanceof UploadedFile) {
            return $this->simulacao->executar(
                $arquivo->getRealPath(),
                $this->validador->extensaoDe($arquivo->getClientOriginalName()),
                $importador,
                $opcoes
            );
        }

        return $this->simulacao->executar(
            $arquivo,
            $this->validador->extensaoDe(basename($arquivo)),
            $importador,
            $opcoes
        );
    }

    private function criarRegistro(
        mixed $usuario,
        string $nomeOriginal,
        string $caminhoGuardado,
        string $disco,
        ?string $tipoMime,
        string $extensao,
        int $tamanho,
        Importador $importador,
        OpcoesImporte $opcoes,
    ): Importe {
        $this->aplicarLimiteConcorrencia($usuario);

        return Importe::create([
            'user_id' => $usuario,
            'tenant_id' => $this->inquilinato->id(),
            'file_name' => basename($caminhoGuardado),
            'original_name' => $nomeOriginal,
            'file_path' => $caminhoGuardado,
            'disk' => $disco,
            'mime_type' => $tipoMime,
            'extension' => $extensao,
            'file_size' => $tamanho,
            'importer' => $importador::class,
            'status' => StatusImporte::Pendente,
            'mode' => $opcoes->modo,
            'options' => $opcoes->toArray(),
            'metadata' => $opcoes->metadados,
        ]);
    }

    private function aplicarLimiteConcorrencia(mixed $usuario): void
    {
        $limite = (int) config('importe.max_concurrent_imports', 0);

        if ($limite <= 0) {
            return;
        }

        $ativas = Importe::query()
            ->whereIn('status', [StatusImporte::Pendente->value, StatusImporte::Processando->value])
            ->when($usuario !== null, fn ($consulta) => $consulta->where('user_id', $usuario))
            ->count();

        if ($ativas >= $limite) {
            throw new ExcecaoImporte('Demasiadas importações em simultâneo. Aguarde que as importações atuais terminem.');
        }
    }

    private function garantirPodeCriar(mixed $usuario): void
    {
        if (! $this->autorizacao->podeCriar($usuario)) {
            throw new AuthorizationException('Não está autorizado a criar importações.');
        }
    }

    private function resolverImportador(Importador|string $importador): Importador
    {
        if ($importador instanceof Importador) {
            return $importador;
        }

        $classe = class_exists($importador)
            ? $importador
            : (config("importe.importers.{$importador}"));

        if (! is_string($classe) || ! class_exists($classe)) {
            throw new InvalidArgumentException("Importador desconhecido [{$importador}]. Registe-o na config 'importe.importers'.");
        }

        try {
            $instancia = app($classe);
        } catch (BindingResolutionException $excecao) {
            throw new InvalidArgumentException("O importador [{$classe}] não pôde ser resolvido a partir do container.", previous: $excecao);
        }

        if (! $instancia instanceof Importador) {
            throw new InvalidArgumentException("O importador [{$classe}] deve implementar ".Importador::class.'.');
        }

        return $instancia;
    }

    private function normalizarOpcoes(OpcoesImporte|array|null $opcoes, Importador $importador): OpcoesImporte
    {
        if (is_array($opcoes)) {
            $opcoes = OpcoesImporte::deArray($opcoes);
        }

        return ($opcoes ?? OpcoesImporte::criar())->aplicarPadroesImportador($importador->opcoes());
    }
}
