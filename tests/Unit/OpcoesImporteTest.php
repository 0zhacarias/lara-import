<?php

use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\Enums\EstrategiaDuplicado;
use Kuteka\LaravelImport\Enums\ModoImporte;
use Tests\TestCase;

uses(TestCase::class);

test('tem padrões sensatos', function () {
    $opcoes = OpcoesImporte::criar();

    expect($opcoes->tamanhoChunk)->toBe(1000)
        ->and($opcoes->modo)->toBe(ModoImporte::Parcial)
        ->and($opcoes->estrategiaDuplicado)->toBe(EstrategiaDuplicado::Inserir)
        ->and($opcoes->tentativas)->toBe(3);
});

test('é imutável e fluente', function () {
    $opcoes = OpcoesImporte::criar();
    $modificadas = $opcoes->tamanhoChunk(500)->tentativas(5)->modo(ModoImporte::Estrito);

    expect($opcoes->tamanhoChunk)->toBe(1000)
        ->and($modificadas->tamanhoChunk)->toBe(500)
        ->and($modificadas->tentativas)->toBe(5)
        ->and($modificadas->modo)->toBe(ModoImporte::Estrito);
});

test('aceita enums como strings', function () {
    $opcoes = OpcoesImporte::criar()->modo('strict')->estrategiaDuplicado('upsert');

    expect($opcoes->modo)->toBe(ModoImporte::Estrito)
        ->and($opcoes->estrategiaDuplicado)->toBe(EstrategiaDuplicado::Upsert);
});

test('constrói a partir de um array', function () {
    $opcoes = OpcoesImporte::deArray([
        'tamanho_chunk' => 250,
        'fila' => 'importes-rapidos',
        'conexao_fila' => 'redis',
        'modo' => 'strict',
        'tem_cabecalho' => false,
        'mapeamento' => ['Nome Completo' => 'nome'],
    ]);

    expect($opcoes->tamanhoChunk)->toBe(250)
        ->and($opcoes->fila)->toBe('importes-rapidos')
        ->and($opcoes->conexaoFila)->toBe('redis')
        ->and($opcoes->modo)->toBe(ModoImporte::Estrito)
        ->and($opcoes->temCabecalho)->toBeFalse()
        ->and($opcoes->mapeamento)->toBe(['Nome Completo' => 'nome']);
});

test('ignora chaves desconhecidas no deArray', function () {
    $opcoes = OpcoesImporte::deArray(['desconhecido' => 'valor']);

    expect($opcoes->tamanhoChunk)->toBe(1000);
});

test('aplica os padrões do importador apenas a chaves não alteradas', function () {
    $opcoes = OpcoesImporte::criar()->tamanhoChunk(42);

    $unidas = $opcoes->aplicarPadroesImportador([
        'tem_cabecalho' => false,
        'mapeamento' => ['Coluna A' => 'campo_a'],
        'aliases' => ['campo' => ['Campo', 'campo']],
    ]);

    expect($unidas->tamanhoChunk)->toBe(42)
        ->and($unidas->temCabecalho)->toBeFalse()
        ->and($unidas->mapeamento)->toBe(['Coluna A' => 'campo_a'])
        ->and($unidas->aliases)->toBe(['campo' => ['Campo', 'campo']]);
});

test('valores explícitos vencem os padrões do importador', function () {
    $opcoes = OpcoesImporte::criar()->temCabecalho(false);

    $unidas = $opcoes->aplicarPadroesImportador(['tem_cabecalho' => true]);

    expect($unidas->temCabecalho)->toBeFalse();
});

test('exporta para array', function () {
    $array = OpcoesImporte::criar()->tamanhoChunk(100)->toArray();

    expect($array['tamanho_chunk'])->toBe(100)
        ->and($array)->toHaveKeys([
            'fila', 'conexao_fila', 'tentativas', 'tempo_limite', 'atraso', 'modo',
            'estrategia_duplicado', 'modo_validacao', 'parar_em_erro', 'transmitir_progresso',
            'notificar_usuario', 'tem_cabecalho', 'maximo_erros', 'maximo_linhas', 'tamanho_maximo_arquivo',
        ]);
});
