<?php

namespace Kuteka\LaravelImport\Readers;

use Kuteka\LaravelImport\Support\LinhaLeitor;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class LeitorXlsx extends LeitorAbstrato
{
    public function suporta(string $extensao): bool
    {
        return $extensao === 'xlsx';
    }

    public function ler(string $caminho): iterable
    {
        $planilha = $this->carregar($caminho, $this->opcoes->linhaInicio, $this->opcoes->linhaFim);

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
                    $valores[] = $this->normalizar($celula);
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

        $planilha = $this->carregar($caminho, 1, $this->linhaCabecalho());

        try {
            $folha = $planilha->getActiveSheet();

            if ($folha->getHighestRow() < $this->linhaCabecalho()) {
                return [];
            }

            $linha = $folha->getRowIterator($this->linhaCabecalho(), $this->linhaCabecalho())->current();

            return array_map(fn (Cell $celula) => $this->normalizar($celula), array_values(iterator_to_array($linha->getCellIterator())));
        } finally {
            $planilha->disconnectWorksheets();
        }
    }

    public function contarLinhas(string $caminho): int
    {
        try {
            $info = IOFactory::createReader('Xlsx')->listWorksheetInfo($caminho);

            $total = (int) ($info[0]['totalRows'] ?? 0);

            return max(0, $total - $this->limiteIgnorar());
        } catch (\Throwable) {
            $planilha = $this->carregar($caminho, 1, 1);

            try {
                $total = (int) $planilha->getActiveSheet()->getHighestRow();
            } finally {
                $planilha->disconnectWorksheets();
            }

            return max(0, $total - $this->limiteIgnorar());
        }
    }

    protected function carregar(string $caminho, ?int $linhaInicio, ?int $linhaFim): Spreadsheet
    {
        $leitor = IOFactory::createReader('Xlsx');
        $leitor->setReadDataOnly(true);
        $leitor->setReadEmptyCells(false);

        if ($linhaInicio !== null || $linhaFim !== null) {
            $leitor->setReadFilter(new FiltroJanelaLeitura($linhaInicio ?? 1, $linhaFim));
        }

        return $leitor->load($caminho);
    }

    protected function normalizar(Cell $celula): mixed
    {
        $valor = $celula->getValue();

        if ($valor instanceof RichText) {
            $valor = $valor->getPlainText();
        }

        return is_string($valor) ? trim($valor) : $valor;
    }
}
