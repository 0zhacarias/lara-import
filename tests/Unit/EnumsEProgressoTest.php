<?php

use Illuminate\Database\QueryException;
use Kuteka\LaravelImport\Enums\StatusImporte;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Support\ClassificadorRepeticao;
use Tests\TestCase;

uses(TestCase::class);

test('o estado tem estados finais', function () {
    expect(StatusImporte::Concluido->ehFinal())->toBeTrue()
        ->and(StatusImporte::ConcluidoComErros->ehFinal())->toBeTrue()
        ->and(StatusImporte::Falhou->ehFinal())->toBeTrue()
        ->and(StatusImporte::Cancelado->ehFinal())->toBeTrue()
        ->and(StatusImporte::Pendente->ehFinal())->toBeFalse()
        ->and(StatusImporte::Processando->ehFinal())->toBeFalse()
        ->and(StatusImporte::Cancelando->ehFinal())->toBeFalse();
});

test('o classificador de repetição repete deadlocks', function () {
    $classificador = new ClassificadorRepeticao;

    $deadlock = new QueryException(
        'sqlite',
        'select 1',
        [],
        new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found')
    );

    expect($classificador->deveRepetir($deadlock))->toBeTrue();
});

test('o classificador de repetição repete timeouts de lock do mysql', function () {
    $classificador = new ClassificadorRepeticao;

    $timeout = new QueryException(
        'mysql',
        'update x',
        [],
        new PDOException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded')
    );

    expect($classificador->deveRepetir($timeout))->toBeTrue();
});

test('o classificador de repetição não repete erros de validação', function () {
    $classificador = new ClassificadorRepeticao;

    expect($classificador->deveRepetir(new InvalidArgumentException('dados inválidos')))->toBeFalse();
});

test('o classificador de repetição repete perda de conexão', function () {
    $classificador = new ClassificadorRepeticao;

    $perdida = new PDOException('SQLSTATE[HY000] [2006] MySQL server has gone away');

    expect($classificador->deveRepetir($perdida))->toBeTrue();
});

test('cálculo da percentagem de progresso', function () {
    $importe = new Importe([
        'total_rows' => 1000,
        'processed_rows' => 720,
        'successful_rows' => 700,
        'failed_rows' => 20,
    ]);

    expect($importe->porcentagemProgresso())->toBe(72.0);
});

test('o progresso nunca excede 100', function () {
    $importe = new Importe(['total_rows' => 10, 'processed_rows' => 999]);

    expect($importe->porcentagemProgresso())->toBe(100.0);
});

test('o progresso é zero quando o total de linhas é desconhecido', function () {
    $importe = new Importe(['total_rows' => 0, 'processed_rows' => 0]);

    expect($importe->porcentagemProgresso())->toBe(0.0);
});
