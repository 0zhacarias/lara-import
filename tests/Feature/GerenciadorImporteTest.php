<?php

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\Enums\ModoImporte;
use Kuteka\LaravelImport\Enums\StatusImporte;
use Kuteka\LaravelImport\Exceptions\ExcecaoImporte;
use Kuteka\LaravelImport\Exceptions\ExcecaoImporteNaoEncontrado;
use Kuteka\LaravelImport\Importers\ImportadorAbstrato;
use Kuteka\LaravelImport\Jobs\ProcessarImporte;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Services\GerenciadorImporte;
use Kuteka\LaravelImport\Tests\Stubs\ImportadorSimulado;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    ImportadorSimulado::reset();
});

function csvValido(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'pacientes.csv',
        "nome,nif,telefone\nJoão,123,999\nMaria,456,888\n"
    );
}

test('cria uma importação, guarda o ficheiro e despacha o pipeline', function () {
    Storage::fake('local');
    Queue::fake();

    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importe = GerenciadorImporte::criar(csvValido(), ImportadorSimulado::class);

    expect($importe)->toBeInstanceOf(Importe::class)
        ->and((int) $importe->user_id)->toBe($usuario->id)
        ->and($importe->status)->toBe(StatusImporte::Pendente)
        ->and($importe->extension)->toBe('csv')
        ->and($importe->uuid)->toHaveLength(26);

    Storage::disk('local')->assertExists($importe->file_path);

    Queue::assertPushed(ProcessarImporte::class, fn ($job) => $job->importeUuid === $importe->uuid);
});

test('processa um csv de ponta a ponta na fila síncrona', function () {
    Storage::fake('local');

    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importe = GerenciadorImporte::criar(csvValido(), ImportadorSimulado::class);
    $importe->refresh();

    expect($importe->status)->toBe(StatusImporte::Concluido)
        ->and($importe->total_rows)->toBe(2)
        ->and($importe->processed_rows)->toBe(2)
        ->and($importe->successful_rows)->toBe(2)
        ->and($importe->failed_rows)->toBe(0)
        ->and(ImportadorSimulado::$importadas)->toHaveCount(2)
        ->and(ImportadorSimulado::$importadas[0])->toBe(['nome' => 'João', 'nif' => '123', 'telefone' => '999']);
});

test('rejeita importações de visitantes', function () {
    expect(fn () => GerenciadorImporte::criar(csvValido(), ImportadorSimulado::class))
        ->toThrow(AuthorizationException::class);
});

test('status devolve o resultado da importação', function () {
    Storage::fake('local');
    Queue::fake();

    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importe = GerenciadorImporte::criar(csvValido(), ImportadorSimulado::class);

    expect(GerenciadorImporte::status($importe->uuid)->uuid)->toBe($importe->uuid);
});

test('status lança para importações desconhecidas', function () {
    expect(fn () => GerenciadorImporte::status('01J00000000000000000000000'))
        ->toThrow(ExcecaoImporteNaoEncontrado::class);
});

test('não pode ver importações de outros utilizadores', function () {
    Storage::fake('local');
    Queue::fake();

    $dono = User::factory()->create();
    $this->actingAs($dono);
    $importe = GerenciadorImporte::criar(csvValido(), ImportadorSimulado::class);

    $outro = User::factory()->create();
    $this->actingAs($outro);

    expect(fn () => GerenciadorImporte::status($importe->uuid))
        ->toThrow(AuthorizationException::class);
});

test('cancela uma importação pendente', function () {
    Storage::fake('local');
    Queue::fake();

    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importe = GerenciadorImporte::criar(csvValido(), ImportadorSimulado::class);

    $cancelado = GerenciadorImporte::cancelar($importe->uuid);

    expect($cancelado->status)->toBe(StatusImporte::Cancelando);
});

test('repete uma importação falhada do início', function () {
    Storage::fake('local');
    Queue::fake();

    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importe = GerenciadorImporte::criar(csvValido(), ImportadorSimulado::class);

    $importe->update([
        'status' => StatusImporte::Falhou,
        'total_rows' => 2,
        'processed_rows' => 1,
        'error_message' => 'boom',
    ]);

    $repetido = GerenciadorImporte::repetir($importe->uuid);

    expect($repetido->status)->toBe(StatusImporte::Pendente)
        ->and($repetido->total_rows)->toBe(0)
        ->and($repetido->processed_rows)->toBe(0)
        ->and($repetido->error_message)->toBeNull();

    Queue::assertPushed(ProcessarImporte::class);
});

test('exclui uma importação e o seu ficheiro', function () {
    Storage::fake('local');
    Queue::fake();

    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importe = GerenciadorImporte::criar(csvValido(), ImportadorSimulado::class);

    GerenciadorImporte::excluir($importe->uuid);

    expect(Importe::query()->whereKey($importe->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($importe->file_path);
});

test('a simulação valida sem persistir', function () {
    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $resultado = GerenciadorImporte::simular(csvValido(), ImportadorSimulado::class);

    expect($resultado->totalLinhas)->toBe(2)
        ->and($resultado->linhasValidas)->toBe(2)
        ->and($resultado->linhasInvalidas)->toBe(0)
        ->and($resultado->cabecalhos)->toBe(['nome', 'nif', 'telefone'])
        ->and(Importe::query()->count())->toBe(0);
});

test('a simulação reporta linhas inválidas', function () {
    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $ficheiro = UploadedFile::fake()->createWithContent(
        'pacientes.csv',
        "nome,nif\nJoão,123\n,456\n"
    );

    $resultado = GerenciadorImporte::simular($ficheiro, ImportadorSimulado::class);

    expect($resultado->linhasInvalidas)->toBe(1)
        ->and($resultado->erros[0]['column'])->toBe('nome')
        ->and($resultado->erros[0]['row_number'])->toBe(3);
});

test('rejeita demasiadas importações simultâneas por utilizador', function () {
    config(['importe.max_concurrent_imports' => 1]);

    Storage::fake('local');
    Queue::fake();

    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    Importe::create([
        'user_id' => $usuario->id,
        'file_name' => 'x.csv',
        'original_name' => 'x.csv',
        'file_path' => 'imports/x.csv',
        'disk' => 'local',
        'extension' => 'csv',
        'file_size' => 10,
        'importer' => ImportadorSimulado::class,
        'status' => StatusImporte::Processando,
        'mode' => ModoImporte::Parcial,
    ]);

    expect(fn () => GerenciadorImporte::criar(csvValido(), ImportadorSimulado::class))
        ->toThrow(ExcecaoImporte::class);
});

test('aceita arrays de opções', function () {
    Storage::fake('local');
    Queue::fake();

    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importe = GerenciadorImporte::criar(csvValido(), ImportadorSimulado::class, [
        'tamanho_chunk' => 250,
        'modo' => 'strict',
    ]);

    expect($importe->obterOpcoes()->tamanhoChunk)->toBe(250)
        ->and($importe->obterOpcoes()->modo)->toBe(ModoImporte::Estrito);
});

test('aplica os padrões do importador às opções', function () {
    Storage::fake('local');
    Queue::fake();

    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importador = new class extends ImportadorAbstrato
    {
        public function regras(): array
        {
            return [];
        }

        public function processar(array $linha): void {}

        public function opcoes(): array
        {
            return ['tem_cabecalho' => false];
        }
    };

    $importe = GerenciadorImporte::criar(csvValido(), $importador, OpcoesImporte::criar());

    expect($importe->obterOpcoes()->temCabecalho)->toBeFalse();
});
