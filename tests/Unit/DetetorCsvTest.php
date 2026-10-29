<?php

use Kuteka\LaravelImport\Support\DetetorCsv;
use Tests\TestCase;

uses(TestCase::class);

test('deteta o delimitador vírgula', function () {
    $detetor = new DetetorCsv;

    expect($detetor->detetarDelimitador('nome,idade,cidade'))->toBe(',');
});

test('deteta o delimitador ponto e vírgula', function () {
    $detetor = new DetetorCsv;

    expect($detetor->detetarDelimitador('nome;idade;cidade'))->toBe(';');
});

test('deteta o delimitador tabulação', function () {
    $detetor = new DetetorCsv;

    expect($detetor->detetarDelimitador("nome\tidade\tcidade"))->toBe("\t");
});

test('deteta o delimitador pipe', function () {
    $detetor = new DetetorCsv;

    expect($detetor->detetarDelimitador('nome|idade|cidade'))->toBe('|');
});

test('deteta o bom utf-8 e remove-o', function () {
    $detetor = new DetetorCsv;

    $amostra = "\xEF\xBB\xBFnome";

    expect($detetor->detetarCodificacao($amostra))->toBe('UTF-8')
        ->and($detetor->removerBom($amostra))->toBe('nome');
});

test('deteta o bom utf-16 le', function () {
    $detetor = new DetetorCsv;

    expect($detetor->detetarCodificacao("\xFF\xFEa\x00"))->toBe('UTF-16LE');
});

test('converte windows-1252 para utf-8', function () {
    $detetor = new DetetorCsv;

    $valor = mb_convert_encoding('José', 'Windows-1252', 'UTF-8');

    expect($detetor->converter($valor, 'Windows-1252'))->toBe('José');
});

test('mantém utf-8 intacto', function () {
    $detetor = new DetetorCsv;

    expect($detetor->converter('José', 'UTF-8'))->toBe('José');
});
