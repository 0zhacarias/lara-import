<?php

namespace Kuteka\LaravelImport\Readers;

use Kuteka\LaravelImport\Exceptions\ExcecaoArquivoInvalido;
use Kuteka\LaravelImport\Support\DetetorCsv;
use Kuteka\LaravelImport\Support\LinhaLeitor;

class LeitorCsv extends LeitorAbstrato
{
    public function __construct(private readonly DetetorCsv $detetor)
    {
        parent::__construct();
    }

    public function suporta(string $extensao): bool
    {
        return in_array($extensao, ['csv', 'txt'], true);
    }

    public function ler(string $caminho): iterable
    {
        $manipulador = $this->abrir($caminho);
        [$delimitador, $codificacao] = $this->resolverFormato($manipulador);

        try {
            $numeroLinha = 0;

            while (($valores = fgetcsv($manipulador, 0, $delimitador, '"', '')) !== false) {
                $numeroLinha++;

                if (count($valores) === 1 && $valores[0] === null) {
                    continue;
                }

                if ($numeroLinha <= $this->limiteIgnorar()) {
                    continue;
                }

                if ($this->opcoes->linhaInicio !== null && $numeroLinha < $this->opcoes->linhaInicio) {
                    continue;
                }

                if ($this->opcoes->linhaFim !== null && $numeroLinha > $this->opcoes->linhaFim) {
                    break;
                }

                $valores = array_map(
                    fn ($valor) => $valor === null ? null : $this->detetor->converter((string) $valor, $codificacao),
                    $valores
                );

                yield new LinhaLeitor($numeroLinha, $valores);
            }
        } finally {
            fclose($manipulador);
        }
    }

    public function cabecalhos(string $caminho): array
    {
        if (! $this->opcoes->temCabecalho) {
            return [];
        }

        $manipulador = $this->abrir($caminho);
        [$delimitador, $codificacao] = $this->resolverFormato($manipulador);

        try {
            for ($i = 0; $i < $this->opcoes->linhasIgnorar; $i++) {
                fgetcsv($manipulador, 0, $delimitador, '"', '');
            }

            $valores = fgetcsv($manipulador, 0, $delimitador, '"', '');

            if ($valores === false) {
                return [];
            }

            return array_map(
                fn ($valor) => $valor === null ? null : $this->detetor->converter((string) $valor, $codificacao),
                $valores
            );
        } finally {
            fclose($manipulador);
        }
    }

    public function contarLinhas(string $caminho): int
    {
        $manipulador = $this->abrir($caminho);
        [$delimitador] = $this->resolverFormato($manipulador);

        try {
            $contagem = 0;

            while (($valores = fgetcsv($manipulador, 0, $delimitador, '"', '')) !== false) {
                if (count($valores) === 1 && $valores[0] === null) {
                    continue;
                }

                $contagem++;
            }

            return max(0, $contagem - $this->limiteIgnorar());
        } finally {
            fclose($manipulador);
        }
    }

    private function abrir(string $caminho)
    {
        $manipulador = @fopen($caminho, 'rb');

        if ($manipulador === false) {
            throw new ExcecaoArquivoInvalido('Não foi possível abrir o ficheiro de importação.');
        }

        return $manipulador;
    }

    private function resolverFormato($manipulador): array
    {
        $deteccaoAutomatica = (bool) config('importe.csv.auto_detect', true);

        $amostra = $deteccaoAutomatica ? (fread($manipulador, 16384) ?: '') : '';
        rewind($manipulador);

        $delimitador = $this->opcoes->delimitador;

        if ($delimitador === null && $deteccaoAutomatica && $amostra !== '') {
            $delimitador = $this->detetor->detetarDelimitador(
                $amostra,
                (array) config('importe.csv.candidate_delimiters', [',', ';', "\t", '|'])
            );
        }

        $codificacao = $this->opcoes->codificacao;

        if ($codificacao === null && $deteccaoAutomatica && $amostra !== '') {
            $codificacao = $this->detetor->detetarCodificacao($amostra);
        }

        return [$delimitador ?? ',', $codificacao ?? 'UTF-8'];
    }
}
