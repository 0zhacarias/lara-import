<?php

use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\Exceptions\ExcecaoArquivoInvalido;
use Kuteka\LaravelImport\Exceptions\ExcecaoFormatoNaoSuportado;
use Kuteka\LaravelImport\Exceptions\ExcecaoTamanhoMaximoArquivoExcedido;
use Kuteka\LaravelImport\Services\ValidadorArquivo;
use Tests\TestCase;

uses(TestCase::class);

test('rejeita extensões não suportadas', function () {
    $validador = new ValidadorArquivo;

    $caminho = tempnam(sys_get_temp_dir(), 'importe');
    file_put_contents($caminho, 'conteudo');

    expect(fn () => $validador->validar($caminho, 'exe'))
        ->toThrow(ExcecaoFormatoNaoSuportado::class);

    @unlink($caminho);
});

test('rejeita ficheiros em falta', function () {
    $validador = new ValidadorArquivo;

    expect(fn () => $validador->validar('/caminho/inexistente.csv', 'csv'))
        ->toThrow(ExcecaoArquivoInvalido::class);
});

test('rejeita ficheiros vazios', function () {
    $validador = new ValidadorArquivo;

    $caminho = tempnam(sys_get_temp_dir(), 'importe');
    file_put_contents($caminho, '');

    expect(fn () => $validador->validar($caminho, 'csv'))
        ->toThrow(ExcecaoArquivoInvalido::class);

    @unlink($caminho);
});

test('rejeita ficheiros demasiado grandes', function () {
    $validador = new ValidadorArquivo;

    $caminho = tempnam(sys_get_temp_dir(), 'importe');
    file_put_contents($caminho, str_repeat('a', 1024));

    $opcoes = OpcoesImporte::criar()->tamanhoMaximoArquivo(512);

    expect(fn () => $validador->validar($caminho, 'csv', $opcoes))
        ->toThrow(ExcecaoTamanhoMaximoArquivoExcedido::class);

    @unlink($caminho);
});

test('aceita ficheiros csv válidos', function () {
    $validador = new ValidadorArquivo;

    $caminho = tempnam(sys_get_temp_dir(), 'importe');
    file_put_contents($caminho, "nome,nif\nJoão,123\n");

    expect(fn () => $validador->validar($caminho, 'csv'))->not->toThrow(Throwable::class);

    @unlink($caminho);
});

test('extrai extensões seguras de nomes de ficheiros', function () {
    $validador = new ValidadorArquivo;

    expect($validador->extensaoDe('produtos.csv'))->toBe('csv')
        ->and($validador->extensaoDe('PLANILHA.XLSX'))->toBe('xlsx')
        ->and($validador->extensaoDe('sem-extensao'))->toBe('')
        ->and($validador->extensaoDe('estranho.a!b'))->toBe('');
});
