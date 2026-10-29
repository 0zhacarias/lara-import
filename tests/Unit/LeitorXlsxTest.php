<?php

use Kuteka\LaravelImport\DTOs\OpcoesLeitor;
use Kuteka\LaravelImport\Readers\LeitorXlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as EscritorXlsx;
use Tests\TestCase;

uses(TestCase::class);

function escreverXlsx(array $linhas): string
{
    $caminho = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-import-'.Str::random(8).'.xlsx';

    $planilha = new Spreadsheet;
    $folha = $planilha->getActiveSheet();
    $folha->fromArray($linhas, null, 'A1');

    (new EscritorXlsx($planilha))->save($caminho);
    $planilha->disconnectWorksheets();

    return $caminho;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-import-*.xlsx') ?: [] as $ficheiro) {
        @unlink($ficheiro);
    }
});

test('lê linhas ignorando o cabeçalho', function () {
    $caminho = escreverXlsx([
        ['nome', 'nif'],
        ['João', '123'],
        ['Maria', '456'],
    ]);

    $leitor = (new LeitorXlsx)->definirOpcoes(new OpcoesLeitor(temCabecalho: true));

    $linhas = iterator_to_array($leitor->ler($caminho));

    expect($linhas)->toHaveCount(2)
        ->and($linhas[0]->numero)->toBe(2)
        ->and($linhas[0]->valores[0])->toBe('João')
        ->and($linhas[1]->numero)->toBe(3)
        ->and($linhas[1]->valores)->toBe(['Maria', 456]);
});

test('lê uma janela de linhas', function () {
    $caminho = escreverXlsx([
        ['nome'],
        ['A'],
        ['B'],
        ['C'],
        ['D'],
        ['E'],
    ]);

    $leitor = (new LeitorXlsx)->definirOpcoes(new OpcoesLeitor(temCabecalho: true, linhaInicio: 3, linhaFim: 5));

    $linhas = iterator_to_array($leitor->ler($caminho));

    expect($linhas)->toHaveCount(3)
        ->and($linhas[0]->numero)->toBe(3)
        ->and($linhas[2]->numero)->toBe(5)
        ->and($linhas[2]->valores)->toBe(['D']);
});

test('devolve os cabeçalhos', function () {
    $caminho = escreverXlsx([
        ['nome', 'nif', 'telefone'],
        ['João', '123', '999'],
    ]);

    $leitor = (new LeitorXlsx)->definirOpcoes(new OpcoesLeitor(temCabecalho: true));

    expect($leitor->cabecalhos($caminho))->toBe(['nome', 'nif', 'telefone']);
});

test('conta as linhas de dados', function () {
    $caminho = escreverXlsx([
        ['nome', 'nif'],
        ['A', '1'],
        ['B', '2'],
        ['C', '3'],
    ]);

    $leitor = (new LeitorXlsx)->definirOpcoes(new OpcoesLeitor(temCabecalho: true));

    expect($leitor->contarLinhas($caminho))->toBe(3);
});

test('lê células numéricas como números', function () {
    $caminho = escreverXlsx([
        ['nome', 'preco'],
        ['Produto', 99.5],
    ]);

    $leitor = (new LeitorXlsx)->definirOpcoes(new OpcoesLeitor(temCabecalho: true));

    $linhas = iterator_to_array($leitor->ler($caminho));

    expect($linhas[0]->valores)->toBe(['Produto', 99.5]);
});
