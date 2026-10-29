<?php

namespace Kuteka\LaravelImport\Readers;

use Kuteka\LaravelImport\Support\LinhaLeitor;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Leitor do formato legado .xls. O formato BIFF não suporta filtros de
 * leitura, pelo que o ficheiro inteiro é carregado em memória. Prefira
 * .xlsx ou .csv para ficheiros grandes.
 */
class LeitorXls extends LeitorAbstrato
{
    public function suporta(string $extensao): bool
    {
        return $extensao === 'xls';
    }

    public function ler(string $caminho): iterable
    {
        $planilha = $this->carregar($caminho);

        try {
            $folha = $planilha->getActiveSheet();
            $colunaMaxima = $folha->getHighestColumn();
            $inicio = max(1, $this->opcoes->linhaInicio ?? 1);

            foreach ($folha->getRowIterator($inicio) as $linha) {
                $indiceLinha = $linha->getRowIndex();

                if ($this->opcoes->linhaFim !== null && $indiceLinha > $this->opcoes->linhaFim) {
                    break;
                }

                if ($indiceLinha <= $this->limiteIgnorar()) {
                    continue;
                }

                $valores = [];

                foreach ($linha->getCellIterator('A', $colunaMaxima) as $celula) {
                    $valor = $celula->getValue();

                    if ($valor instanceof RichText) {
                        $valor = $valor->getPlainText();
                    }

                    $valores[] = is_string($valor) ? trim($valor) : $valor;
                }

                yield new LinhaLeitor($indiceLinha, $valores);
            }
        } finally {
            $planilha->disconnectWorksheets();
        }
    }

    public function cabecalhos(string $caminho): array
    {
        if (! $this->opcoes->temCabecalho) {
            return [];
        }

        $planilha = $this->carregar($caminho);

        try {
            $folha = $planilha->getActiveSheet();

            if ($folha->getHighestRow() < $this->linhaCabecalho()) {
                return [];
            }

            $linha = $folha->getRowIterator($this->linhaCabecalho(), $this->linhaCabecalho())->current();

            return array_map(function ($celula) {
                $valor = $celula->getValue();

                if ($valor instanceof RichText) {
                    $valor = $valor->getPlainText();
                }

                return is_string($valor) ? trim($valor) : $valor;
            }, array_values(iterator_to_array($linha->getCellIterator())));
        } finally {
            $planilha->disconnectWorksheets();
        }
    }

    public function contarLinhas(string $caminho): int
    {
        $planilha = $this->carregar($caminho);

        try {
            $total = (int) $planilha->getActiveSheet()->getHighestRow();
        } finally {
            $planilha->disconnectWorksheets();
        }

        return max(0, $total - $this->limiteIgnorar());
    }

    protected function carregar(string $caminho): Spreadsheet
    {
        $leitor = IOFactory::createReader('Xls');
        $leitor->setReadDataOnly(true);

        return $leitor->load($caminho);
    }
}
