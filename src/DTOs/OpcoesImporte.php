<?php

namespace Kuteka\LaravelImport\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Kuteka\LaravelImport\Enums\EstrategiaDuplicado;
use Kuteka\LaravelImport\Enums\ModoImporte;
use Kuteka\LaravelImport\Enums\ModoValidacao;

/**
 * Configuração (quase) imutável de uma importação.
 */
class OpcoesImporte implements Arrayable
{
    public function __construct(
        public readonly int $tamanhoChunk,
        public readonly string $fila,
        public readonly string $conexaoFila,
        public readonly int $tentativas,
        public readonly int $tempoLimite,
        public readonly int $atraso,
        public readonly ModoImporte $modo,
        public readonly EstrategiaDuplicado $estrategiaDuplicado,
        public readonly ModoValidacao $modoValidacao,
        public readonly bool $pararEmErro,
        public readonly bool $transmitirProgresso,
        public readonly bool $notificarUsuario,
        public readonly array $canaisNotificacao,
        public readonly bool $temCabecalho,
        public readonly int $linhasIgnorar,
        public readonly ?string $delimitador,
        public readonly ?string $codificacao,
        public readonly array $mapeamento,
        public readonly array $aliases,
        public readonly ?int $maximoErros,
        public readonly ?int $maximoLinhas,
        public readonly ?int $tamanhoMaximoArquivo,
        public readonly array $metadados,
    ) {}

    public static function criar(): self
    {
        return new self(
            tamanhoChunk: (int) config('importe.chunk_size', 1000),
            fila: (string) config('importe.queue.queue', 'imports'),
            conexaoFila: (string) config('importe.queue.connection', 'redis'),
            tentativas: (int) config('importe.tries', 3),
            tempoLimite: (int) config('importe.timeout', 600),
            atraso: (int) config('importe.backoff', 3),
            modo: ModoImporte::Parcial,
            estrategiaDuplicado: EstrategiaDuplicado::Inserir,
            modoValidacao: ModoValidacao::Tudo,
            pararEmErro: false,
            transmitirProgresso: (bool) config('importe.broadcast.enabled', true),
            notificarUsuario: (bool) config('importe.notifications.enabled', true),
            canaisNotificacao: (array) config('importe.notifications.channels', ['database', 'mail']),
            temCabecalho: true,
            linhasIgnorar: 0,
            delimitador: config('importe.csv.delimiter'),
            codificacao: config('importe.csv.encoding'),
            mapeamento: [],
            aliases: [],
            maximoErros: (int) config('importe.max_errors', 100_000),
            maximoLinhas: (int) config('importe.max_rows', 1_000_000),
            tamanhoMaximoArquivo: (int) config('importe.max_file_size', 100 * 1024 * 1024),
            metadados: [],
        );
    }

    public static function deArray(array $dados): self
    {
        $opcoes = self::criar();

        $mapa = [
            'tamanhoChunk' => 'tamanho_chunk',
            'fila' => 'fila',
            'conexaoFila' => 'conexao_fila',
            'tentativas' => 'tentativas',
            'tempoLimite' => 'tempo_limite',
            'atraso' => 'atraso',
            'estrategiaDuplicado' => 'estrategia_duplicado',
            'modoValidacao' => 'modo_validacao',
            'pararEmErro' => 'parar_em_erro',
            'transmitirProgresso' => 'transmitir_progresso',
            'notificarUsuario' => 'notificar_usuario',
            'canaisNotificacao' => 'canais_notificacao',
            'temCabecalho' => 'tem_cabecalho',
            'linhasIgnorar' => 'linhas_ignorar',
            'delimitador' => 'delimitador',
            'codificacao' => 'codificacao',
            'mapeamento' => 'mapeamento',
            'aliases' => 'aliases',
            'maximoErros' => 'maximo_erros',
            'maximoLinhas' => 'maximo_linhas',
            'tamanhoMaximoArquivo' => 'tamanho_maximo_arquivo',
        ];

        foreach ($mapa as $propriedade => $chave) {
            if (! array_key_exists($chave, $dados)) {
                continue;
            }

            $opcoes = $opcoes->{$propriedade}($dados[$chave]);
        }

        if (array_key_exists('modo', $dados)) {
            $opcoes = $opcoes->modo($dados['modo']);
        }

        return $opcoes;
    }

    public function tamanhoChunk(int $valor): self
    {
        return $this->com(tamanhoChunk: max(1, $valor));
    }

    public function fila(string $valor): self
    {
        return $this->com(fila: $valor);
    }

    public function conexaoFila(string $valor): self
    {
        return $this->com(conexaoFila: $valor);
    }

    public function tentativas(int $valor): self
    {
        return $this->com(tentativas: max(1, $valor));
    }

    public function tempoLimite(int $valor): self
    {
        return $this->com(tempoLimite: max(1, $valor));
    }

    public function atraso(int $valor): self
    {
        return $this->com(atraso: max(0, $valor));
    }

    public function modo(ModoImporte|string $valor): self
    {
        return $this->com(modo: is_string($valor) ? ModoImporte::from($valor) : $valor);
    }

    public function estrategiaDuplicado(EstrategiaDuplicado|string $valor): self
    {
        return $this->com(estrategiaDuplicado: is_string($valor) ? EstrategiaDuplicado::from($valor) : $valor);
    }

    public function modoValidacao(ModoValidacao|string $valor): self
    {
        return $this->com(modoValidacao: is_string($valor) ? ModoValidacao::from($valor) : $valor);
    }

    public function pararEmErro(bool $valor = true): self
    {
        return $this->com(pararEmErro: $valor);
    }

    public function transmitirProgresso(bool $valor = true): self
    {
        return $this->com(transmitirProgresso: $valor);
    }

    public function notificarUsuario(bool $valor = true): self
    {
        return $this->com(notificarUsuario: $valor);
    }

    public function canaisNotificacao(array $valor): self
    {
        return $this->com(canaisNotificacao: $valor);
    }

    public function temCabecalho(bool $valor = true): self
    {
        return $this->com(temCabecalho: $valor);
    }

    public function linhasIgnorar(int $valor): self
    {
        return $this->com(linhasIgnorar: max(0, $valor));
    }

    public function delimitador(?string $valor): self
    {
        return $this->com(delimitador: $valor);
    }

    public function codificacao(?string $valor): self
    {
        return $this->com(codificacao: $valor);
    }

    public function mapeamento(array $valor): self
    {
        return $this->com(mapeamento: $valor);
    }

    public function aliases(array $valor): self
    {
        return $this->com(aliases: $valor);
    }

    public function maximoErros(?int $valor): self
    {
        return $this->com(maximoErros: $valor);
    }

    public function maximoLinhas(?int $valor): self
    {
        return $this->com(maximoLinhas: $valor);
    }

    public function tamanhoMaximoArquivo(?int $valor): self
    {
        return $this->com(tamanhoMaximoArquivo: $valor);
    }

    public function metadados(array $valor): self
    {
        return $this->com(metadados: $valor);
    }

    /**
     * Aplica os padrões fornecidos pelo importador às chaves que o chamador
     * não alterou explicitamente (ou seja, que ainda têm o padrão do package).
     */
    public function aplicarPadroesImportador(array $dados): self
    {
        $padroes = self::criar();
        $resultado = $this;

        $mapa = [
            'temCabecalho' => 'tem_cabecalho',
            'linhasIgnorar' => 'linhas_ignorar',
            'delimitador' => 'delimitador',
            'codificacao' => 'codificacao',
            'mapeamento' => 'mapeamento',
            'aliases' => 'aliases',
        ];

        foreach ($mapa as $propriedade => $chave) {
            if (array_key_exists($chave, $dados) && $this->{$propriedade} === $padroes->{$propriedade}) {
                $resultado = $resultado->{$propriedade}($dados[$chave]);
            }
        }

        return $resultado;
    }

    public function toArray(): array
    {
        return [
            'tamanho_chunk' => $this->tamanhoChunk,
            'fila' => $this->fila,
            'conexao_fila' => $this->conexaoFila,
            'tentativas' => $this->tentativas,
            'tempo_limite' => $this->tempoLimite,
            'atraso' => $this->atraso,
            'modo' => $this->modo->value,
            'estrategia_duplicado' => $this->estrategiaDuplicado->value,
            'modo_validacao' => $this->modoValidacao->value,
            'parar_em_erro' => $this->pararEmErro,
            'transmitir_progresso' => $this->transmitirProgresso,
            'notificar_usuario' => $this->notificarUsuario,
            'canais_notificacao' => $this->canaisNotificacao,
            'tem_cabecalho' => $this->temCabecalho,
            'linhas_ignorar' => $this->linhasIgnorar,
            'delimitador' => $this->delimitador,
            'codificacao' => $this->codificacao,
            'mapeamento' => $this->mapeamento,
            'aliases' => $this->aliases,
            'maximo_erros' => $this->maximoErros,
            'maximo_linhas' => $this->maximoLinhas,
            'tamanho_maximo_arquivo' => $this->tamanhoMaximoArquivo,
            'metadados' => $this->metadados,
        ];
    }

    private function com(
        ?int $tamanhoChunk = null,
        ?string $fila = null,
        ?string $conexaoFila = null,
        ?int $tentativas = null,
        ?int $tempoLimite = null,
        ?int $atraso = null,
        ?ModoImporte $modo = null,
        ?EstrategiaDuplicado $estrategiaDuplicado = null,
        ?ModoValidacao $modoValidacao = null,
        ?bool $pararEmErro = null,
        ?bool $transmitirProgresso = null,
        ?bool $notificarUsuario = null,
        ?array $canaisNotificacao = null,
        ?bool $temCabecalho = null,
        ?int $linhasIgnorar = null,
        ?string $delimitador = null,
        ?string $codificacao = null,
        ?array $mapeamento = null,
        ?array $aliases = null,
        ?int $maximoErros = null,
        ?int $maximoLinhas = null,
        ?int $tamanhoMaximoArquivo = null,
        ?array $metadados = null,
    ): self {
        return new self(
            tamanhoChunk: $tamanhoChunk ?? $this->tamanhoChunk,
            fila: $fila ?? $this->fila,
            conexaoFila: $conexaoFila ?? $this->conexaoFila,
            tentativas: $tentativas ?? $this->tentativas,
            tempoLimite: $tempoLimite ?? $this->tempoLimite,
            atraso: $atraso ?? $this->atraso,
            modo: $modo ?? $this->modo,
            estrategiaDuplicado: $estrategiaDuplicado ?? $this->estrategiaDuplicado,
            modoValidacao: $modoValidacao ?? $this->modoValidacao,
            pararEmErro: $pararEmErro ?? $this->pararEmErro,
            transmitirProgresso: $transmitirProgresso ?? $this->transmitirProgresso,
            notificarUsuario: $notificarUsuario ?? $this->notificarUsuario,
            canaisNotificacao: $canaisNotificacao ?? $this->canaisNotificacao,
            temCabecalho: $temCabecalho ?? $this->temCabecalho,
            linhasIgnorar: $linhasIgnorar ?? $this->linhasIgnorar,
            delimitador: $delimitador ?? $this->delimitador,
            codificacao: $codificacao ?? $this->codificacao,
            mapeamento: $mapeamento ?? $this->mapeamento,
            aliases: $aliases ?? $this->aliases,
            maximoErros: $maximoErros ?? $this->maximoErros,
            maximoLinhas: $maximoLinhas ?? $this->maximoLinhas,
            tamanhoMaximoArquivo: $tamanhoMaximoArquivo ?? $this->tamanhoMaximoArquivo,
            metadados: $metadados ?? $this->metadados,
        );
    }
}
