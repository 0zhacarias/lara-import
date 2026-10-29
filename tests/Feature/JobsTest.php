<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Kuteka\LaravelImport\Enums\StatusChunk;
use Kuteka\LaravelImport\Enums\StatusImporte;
use Kuteka\LaravelImport\Jobs\DespachadorJobImporte;
use Kuteka\LaravelImport\Jobs\ProcessarImporte;
use Kuteka\LaravelImport\Jobs\ProcessarImporteChunk;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Models\ImporteChunk;
use Kuteka\LaravelImport\Readers\FabricaLeitor;
use Kuteka\LaravelImport\Services\GerenciadorImporte;
use Kuteka\LaravelImport\Services\InspetorEstrutura;
use Kuteka\LaravelImport\Services\NotificadorImporte;
use Kuteka\LaravelImport\Services\RelatorProgresso;
use Kuteka\LaravelImport\Support\ClassificadorRepeticao;
use Kuteka\LaravelImport\Tests\Stubs\ImportadorSimulado;
use Kuteka\LaravelImport\Tests\Stubs\LoteImportadorSimulado;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    ImportadorSimulado::reset();
});

function executarImporte(string $csv, array $opcoes = [], string $importador = ImportadorSimulado::class): Importe
{
    Storage::fake('local');
    $usuario = User::factory()->create();
    auth()->login($usuario);

    $ficheiro = UploadedFile::fake()->createWithContent('dados.csv', $csv);

    return GerenciadorImporte::criar($ficheiro, $importador, $opcoes)->fresh();
}

test('divide o ficheiro em chunks', function () {
    Queue::fake();

    $csv = "nome,nif\n".implode("\n", array_map(fn ($i) => "Pessoa{$i},{$i}", range(1, 7)));

    $importe = executarImporte($csv, ['tamanho_chunk' => 3]);

    (new ProcessarImporte($importe->uuid))->handle(
        app(InspetorEstrutura::class),
        app(DespachadorJobImporte::class),
        app(NotificadorImporte::class),
        app(ClassificadorRepeticao::class),
    );

    $importe->refresh();

    expect($importe->chunks()->get())->toHaveCount(3)
        ->and($importe->chunks()->orderBy('chunk_number')->get()->map(fn ($c) => $c->toArray())->values())
        ->sequence(
            fn ($chunk) => $chunk->toHaveKey('start_row', 1)->toHaveKey('end_row', 3),
            fn ($chunk) => $chunk->toHaveKey('start_row', 4)->toHaveKey('end_row', 6),
            fn ($chunk) => $chunk->toHaveKey('start_row', 7)->toHaveKey('end_row', 7),
        );
});

test('processa todos os chunks sequencialmente e conclui', function () {
    $csv = "nome,nif\n".implode("\n", array_map(fn ($i) => "Pessoa{$i},{$i}", range(1, 7)));

    $importe = executarImporte($csv, ['tamanho_chunk' => 3]);

    expect($importe->status)->toBe(StatusImporte::Concluido)
        ->and($importe->total_rows)->toBe(7)
        ->and($importe->successful_rows)->toBe(7)
        ->and(ImportadorSimulado::$importadas)->toHaveCount(7)
        ->and($importe->chunks()->where('status', StatusChunk::Concluido)->count())->toBe(3);
});

test('conclui com erros no modo parcial', function () {
    $csv = "nome,nif\nJoão,1\n,2\nMaria,3\n";

    $importe = executarImporte($csv);

    expect($importe->status)->toBe(StatusImporte::ConcluidoComErros)
        ->and($importe->successful_rows)->toBe(2)
        ->and($importe->failed_rows)->toBe(1)
        ->and($importe->erros()->get())->toHaveCount(1)
        ->and($importe->erros()->first()->row_number)->toBe(3)
        ->and($importe->erros()->first()->column)->toBe('nome');
});

test('falha a importação no modo estrito na primeira linha inválida', function () {
    $csv = "nome,nif\nJoão,1\n,2\nMaria,3\n";

    $importe = executarImporte($csv, ['modo' => 'strict']);

    expect($importe->status)->toBe(StatusImporte::Falhou)
        ->and($importe->error_message)->toContain('abortada');
});

test('ignora chunks já concluídos (idempotência)', function () {
    $importe = executarImporte("nome\nA\nB\n", ['tamanho_chunk' => 1]);

    $chunk = $importe->chunks()->first();

    (new ProcessarImporteChunk($importe->uuid, $chunk->chunk_number))->handle(
        app(FabricaLeitor::class),
        app(DespachadorJobImporte::class),
        app(NotificadorImporte::class),
        app(RelatorProgresso::class),
        app(ClassificadorRepeticao::class),
    );

    expect($importe->fresh()->successful_rows)->toBe(2)
        ->and(ImportadorSimulado::$importadas)->toHaveCount(2);
});

test('trata ficheiros vazios', function () {
    $importe = executarImporte("nome,nif\n");

    expect($importe->status)->toBe(StatusImporte::Concluido)
        ->and($importe->total_rows)->toBe(0);
});

test('falha a importação quando faltam colunas obrigatórias', function () {
    $importe = executarImporte("idade,nif\n30,1\n");

    expect($importe->status)->toBe(StatusImporte::Falhou)
        ->and($importe->error_message)->toContain('colunas obrigatórias');
});

test('falha a importação quando o máximo de linhas é excedido', function () {
    $csv = "nome\n".implode("\n", array_map(fn ($i) => "P{$i}", range(1, 5)));

    $importe = executarImporte($csv, ['maximo_linhas' => 3]);

    expect($importe->status)->toBe(StatusImporte::Falhou)
        ->and($importe->error_message)->toContain('excedendo o limite');
});

test('falha a importação quando o máximo de erros é atingido', function () {
    $csv = "nome,nif\n".implode("\n", array_map(fn ($i) => ",{$i}", range(1, 4)));

    $importe = executarImporte($csv, ['maximo_erros' => 2]);

    expect($importe->status)->toBe(StatusImporte::Falhou)
        ->and($importe->error_message)->toContain('Número máximo de erros');
});

test('cancela uma importação entre chunks', function () {
    $csv = "nome\n".implode("\n", array_map(fn ($i) => "Pessoa{$i}", range(1, 10)));

    $importe = executarImporte($csv, ['tamanho_chunk' => 1]);

    // Com a fila síncrona o pipeline já terminou; simula o estado de
    // cancelamento e executa o próximo chunk manualmente.
    $importe->update(['status' => StatusImporte::Cancelando]);

    $proximo = ImporteChunk::query()
        ->where('import_id', $importe->id)
        ->where('chunk_number', '>', 1)
        ->orderBy('chunk_number')
        ->first();

    (new ProcessarImporteChunk($importe->uuid, $proximo->chunk_number))->handle(
        app(FabricaLeitor::class),
        app(DespachadorJobImporte::class),
        app(NotificadorImporte::class),
        app(RelatorProgresso::class),
        app(ClassificadorRepeticao::class),
    );

    expect($importe->fresh()->status)->toBe(StatusImporte::Cancelado)
        ->and(ImportadorSimulado::$importadas)->toHaveCount(10);
});

test('usa processamento em lote quando o importador o suporta', function () {
    $csv = "nome,nif\nJoão,1\nMaria,2\n";

    $importe = executarImporte($csv, [], LoteImportadorSimulado::class);

    expect($importe->status)->toBe(StatusImporte::Concluido)
        ->and(LoteImportadorSimulado::$importadas)->toHaveCount(2);
});

test('guarda erros com os dados originais da linha', function () {
    $importe = executarImporte("nome,nif\nJoão,1\n,2\n");

    $erro = $importe->erros()->first();

    expect($erro->data)->toHaveKey('row')
        ->and($erro->error_code)->toBe('validation_failed')
        ->and($erro->value)->toBe('');
});
