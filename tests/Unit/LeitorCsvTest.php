<?php

use Kuteka\LaravelImport\DTOs\OpcoesLeitor;
use Kuteka\LaravelImport\Readers\LeitorCsv;
use Kuteka\LaravelImport\Support\DetetorCsv;
use Tests\TestCase;

uses(TestCase::class);

function escreverCsv(string $conteudo, string $nome = 'teste.csv'): string
{
    $caminho = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-import-'.Str::random(8).'-'.$nome;
    file_put_contents($caminho, $conteudo);

    return $caminho;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-import-*.csv') ?: [] as $ficheiro) {
        @unlink($ficheiro);
    }
});

test('lê linhas ignorando o cabeçalho com números físicos de linha', function () {
    $caminho = escreverCsv("nome,nif\nJoão,123\nMaria,456\n");

    $leitor = (new LeitorCsv(new DetetorCsv))->definirOpcoes(new OpcoesLeitor(temCabecalho: true));

    $linhas = iterator_to_array($leitor->ler($caminho));

    expect($linhas)->toHaveCount(2)
        ->and($linhas[0]->numero)->toBe(2)
        ->and($linhas[0]->valores)->toBe(['João', '123'])
        ->and($linhas[1]->numero)->toBe(3)
        ->and($linhas[1]->valores)->toBe(['Maria', '456']);
});

test('lê uma janela de linhas', function () {
    $caminho = escreverCsv("nome\nA\nB\nC\nD\nE\n");

    $leitor = (new LeitorCsv(new DetetorCsv))->definirOpcoes(
        new OpcoesLeitor(temCabecalho: true, linhaInicio: 3, linhaFim: 5)
    );

    $linhas = iterator_to_array($leitor->ler($caminho));

    expect($linhas)->toHaveCount(3)
        ->and($linhas[0]->numero)->toBe(3)
        ->and($linhas[2]->numero)->toBe(5)
        ->and($linhas[2]->valores)->toBe(['D']);
});

test('lê ficheiros sem cabeçalho', function () {
    $caminho = escreverCsv("João,123\nMaria,456\n");

    $leitor = (new LeitorCsv(new DetetorCsv))->definirOpcoes(new OpcoesLeitor(temCabecalho: false));

    $linhas = iterator_to_array($leitor->ler($caminho));

    expect($linhas)->toHaveCount(2)
        ->and($linhas[0]->valores)->toBe(['João', '123']);
});

test('deteta automaticamente o delimitador ponto e vírgula', function () {
    $caminho = escreverCsv("nome;nif\nJoão;123\nMaria;456\n");

    $leitor = (new LeitorCsv(new DetetorCsv))->definirOpcoes(new OpcoesLeitor(temCabecalho: true));

    $linhas = iterator_to_array($leitor->ler($caminho));

    expect($linhas[0]->valores)->toBe(['João', '123']);
});

test('converte conteúdo windows-1252 para utf-8', function () {
    $conteudo = "nome\n".mb_convert_encoding('José', 'Windows-1252', 'UTF-8')."\n";
    $caminho = escreverCsv($conteudo);

    $leitor = (new LeitorCsv(new DetetorCsv))->definirOpcoes(new OpcoesLeitor(temCabecalho: true));

    $linhas = iterator_to_array($leitor->ler($caminho));

    expect($linhas[0]->valores[0])->toBe('José');
});

test('devolve os cabeçalhos', function () {
    $caminho = escreverCsv("nome,nif,telefone\nJoão,123,999\n");

    $leitor = (new LeitorCsv(new DetetorCsv))->definirOpcoes(new OpcoesLeitor(temCabecalho: true));

    expect($leitor->cabecalhos($caminho))->toBe(['nome', 'nif', 'telefone']);
});

test('devolve cabeçalhos vazios para ficheiros sem cabeçalho', function () {
    $caminho = escreverCsv("João,123\n");

    $leitor = (new LeitorCsv(new DetetorCsv))->definirOpcoes(new OpcoesLeitor(temCabecalho: false));

    expect($leitor->cabecalhos($caminho))->toBe([]);
});

test('conta as linhas de dados excluindo o cabeçalho', function () {
    $caminho = escreverCsv("nome,nif\nA,1\nB,2\nC,3\n");

    $leitor = (new LeitorCsv(new DetetorCsv))->definirOpcoes(new OpcoesLeitor(temCabecalho: true));

    expect($leitor->contarLinhas($caminho))->toBe(3);
});

test('remove o bom utf-8 da primeira célula do cabeçalho', function () {
    $caminho = escreverCsv("\xEF\xBB\xBFnome,nif\nJoão,123\n");

    $leitor = (new LeitorCsv(new DetetorCsv))->definirOpcoes(new OpcoesLeitor(temCabecalho: true));

    expect($leitor->cabecalhos($caminho))->toBe(['nome', 'nif']);
});
