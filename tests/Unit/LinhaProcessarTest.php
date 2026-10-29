<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\Enums\ModoImporte;
use Kuteka\LaravelImport\Enums\StatusChunk;
use Kuteka\LaravelImport\Exceptions\ExcecaoChunkAbortado;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Models\ImporteChunk;
use Kuteka\LaravelImport\Models\ImporteErro;
use Kuteka\LaravelImport\Services\ChunkProcessar;
use Kuteka\LaravelImport\Services\LinhaProcessar;
use Kuteka\LaravelImport\Support\ClassificadorRepeticao;
use Kuteka\LaravelImport\Support\CodigosErro;
use Kuteka\LaravelImport\Support\LinhaLeitor;
use Kuteka\LaravelImport\Tests\Stubs\ImportadorSimulado;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    ImportadorSimulado::reset();
});

test('mapeia cabeçalhos usando aliases', function () {
    $processador = new LinhaProcessar(
        new ImportadorSimulado,
        OpcoesImporte::criar()->aliases([
            'nome' => ['Nome Completo', 'full_name', 'nome'],
        ]),
        ['Nome Completo', 'nif'],
    );

    [$mapeada] = $processador->processar(new LinhaLeitor(2, ['João', '123']));

    expect($mapeada)->toBe(['nome' => 'João', 'nif' => '123']);
});

test('mapeia cabeçalhos usando mapeamento explícito', function () {
    $processador = new LinhaProcessar(
        new ImportadorSimulado,
        OpcoesImporte::criar()->mapeamento(['Nome Completo' => 'nome']),
        ['Nome Completo', 'NIF'],
    );

    [$mapeada] = $processador->processar(new LinhaLeitor(2, ['João', '123']));

    expect($mapeada)->toBe(['nome' => 'João']);
});

test('recolhe erros de validação por linha', function () {
    $processador = new LinhaProcessar(
        new ImportadorSimulado,
        OpcoesImporte::criar(),
        ['nome', 'nif'],
    );

    [$mapeada, $erros] = $processador->processar(new LinhaLeitor(4, ['', '123']));

    expect($mapeada)->toBeNull()
        ->and($erros)->toHaveCount(1)
        ->and($erros[0]['row_number'])->toBe(4)
        ->and($erros[0]['column'])->toBe('nome')
        ->and($erros[0]['error_code'])->toBe(CodigosErro::FALHA_VALIDACAO);
});

test('mapeia colunas posicionais quando o ficheiro não tem cabeçalho', function () {
    $processador = new LinhaProcessar(
        new ImportadorSimulado,
        OpcoesImporte::criar()->temCabecalho(false),
        [],
    );

    [$mapeada] = $processador->processar(new LinhaLeitor(1, ['João', '123', '999']));

    expect($mapeada)->toBe(['nome' => 'João', 'nif' => '123', 'telefone' => '999']);
});

test('o processador de chunks processa linhas dentro de uma transação', function () {
    $importe = Importe::create([
        'file_name' => 'x.csv',
        'original_name' => 'x.csv',
        'file_path' => 'imports/x.csv',
        'disk' => 'local',
        'extension' => 'csv',
        'file_size' => 10,
        'importer' => ImportadorSimulado::class,
    ]);

    $chunk = ImporteChunk::create([
        'import_id' => $importe->id,
        'chunk_number' => 1,
        'start_row' => 1,
        'end_row' => 3,
    ]);

    $processador = new ChunkProcessar(
        new LinhaProcessar(new ImportadorSimulado, OpcoesImporte::criar(), ['nome', 'nif']),
        new ImportadorSimulado,
        OpcoesImporte::criar(),
        new ClassificadorRepeticao,
    );

    $linhas = [
        new LinhaLeitor(2, ['João', '1']),
        new LinhaLeitor(3, ['Maria', '2']),
    ];

    $contadores = $processador->processar($linhas, $chunk);

    expect($contadores['processadas'])->toBe(2)
        ->and($contadores['sucesso'])->toBe(2)
        ->and($contadores['falhas'])->toBe(0)
        ->and(ImportadorSimulado::$importadas)->toHaveCount(2)
        ->and($chunk->status)->toBe(StatusChunk::Concluido);
});

test('o processador de chunks aborta no modo estrito com linhas inválidas', function () {
    $importe = Importe::create([
        'file_name' => 'x.csv',
        'original_name' => 'x.csv',
        'file_path' => 'imports/x.csv',
        'disk' => 'local',
        'extension' => 'csv',
        'file_size' => 10,
        'importer' => ImportadorSimulado::class,
    ]);

    $chunk = ImporteChunk::create([
        'import_id' => $importe->id,
        'chunk_number' => 1,
        'start_row' => 1,
        'end_row' => 3,
    ]);

    $processador = new ChunkProcessar(
        new LinhaProcessar(new ImportadorSimulado, OpcoesImporte::criar(), ['nome', 'nif']),
        new ImportadorSimulado,
        OpcoesImporte::criar()->modo(ModoImporte::Estrito),
        new ClassificadorRepeticao,
    );

    expect(fn () => $processador->processar([
        new LinhaLeitor(2, ['João', '1']),
        new LinhaLeitor(3, ['', '2']),
    ], $chunk))->toThrow(ExcecaoChunkAbortado::class);

    // Transação revertida: chunk intacto, nenhum erro persistido.
    // O efeito em memória da primeira linha no importador permanece.
    expect(ImportadorSimulado::$importadas)->toHaveCount(1)
        ->and($chunk->fresh()->status)->toBe(StatusChunk::Pendente)
        ->and(ImporteErro::query()->count())->toBe(0);
});

test('o processador de chunks conta linhas vazias ignoradas', function () {
    $importe = Importe::create([
        'file_name' => 'x.csv',
        'original_name' => 'x.csv',
        'file_path' => 'imports/x.csv',
        'disk' => 'local',
        'extension' => 'csv',
        'file_size' => 10,
        'importer' => ImportadorSimulado::class,
    ]);

    $chunk = ImporteChunk::create([
        'import_id' => $importe->id,
        'chunk_number' => 1,
        'start_row' => 1,
        'end_row' => 3,
    ]);

    $processador = new ChunkProcessar(
        new LinhaProcessar(new ImportadorSimulado, OpcoesImporte::criar(), ['nome', 'nif']),
        new ImportadorSimulado,
        OpcoesImporte::criar(),
        new ClassificadorRepeticao,
    );

    $contadores = $processador->processar([
        new LinhaLeitor(2, [null, null]),
        new LinhaLeitor(3, ['Maria', '2']),
    ], $chunk);

    expect($contadores['ignoradas'])->toBe(1)
        ->and($contadores['sucesso'])->toBe(1);
});
