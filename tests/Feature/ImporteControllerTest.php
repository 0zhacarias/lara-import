<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Kuteka\LaravelImport\Enums\StatusImporte;
use Kuteka\LaravelImport\Services\GerenciadorImporte;
use Kuteka\LaravelImport\Tests\Stubs\ImportadorSimulado;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    ImportadorSimulado::reset();
    Storage::fake('local');
});

test('o endpoint store cria uma importação', function () {
    $usuario = User::factory()->create();

    $resposta = $this->actingAs($usuario)->post('/importes', [
        'importer' => ImportadorSimulado::class,
        'file' => UploadedFile::fake()->createWithContent('dados.csv', "nome\nJoão\n"),
    ]);

    $resposta->assertCreated()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('total', 1)
        ->assertJsonPath('sucesso', 1);
});

test('o endpoint store valida o pedido', function () {
    $usuario = User::factory()->create();

    $resposta = $this->actingAs($usuario)->post('/importes', []);

    $resposta->assertSessionHasErrors(['file', 'importer']);
});

test('o endpoint show devolve o estado da importação', function () {
    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importe = GerenciadorImporte::criar(
        UploadedFile::fake()->createWithContent('dados.csv', "nome\nJoão\n"),
        ImportadorSimulado::class
    );

    $resposta = $this->actingAs($usuario)->get("/importes/{$importe->uuid}");

    $resposta->assertOk()
        ->assertJsonPath('uuid', $importe->uuid)
        ->assertJsonPath('status', 'completed');
});

test('o endpoint cancelar cancela uma importação pendente', function () {
    Queue::fake();

    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importe = GerenciadorImporte::criar(
        UploadedFile::fake()->createWithContent('dados.csv', "nome\nJoão\n"),
        ImportadorSimulado::class
    );

    $resposta = $this->actingAs($usuario)->post("/importes/{$importe->uuid}/cancelar");

    $resposta->assertOk()
        ->assertJsonPath('status', StatusImporte::Cancelando->value);
});

test('o endpoint repetir repete uma importação falhada', function () {
    Queue::fake();

    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importe = GerenciadorImporte::criar(
        UploadedFile::fake()->createWithContent('dados.csv', "nome\nJoão\n"),
        ImportadorSimulado::class
    );

    $importe->update(['status' => StatusImporte::Falhou, 'error_message' => 'boom']);

    $resposta = $this->actingAs($usuario)->post("/importes/{$importe->uuid}/repetir");

    $resposta->assertOk()
        ->assertJsonPath('status', StatusImporte::Pendente->value);
});

test('o endpoint erros lista os erros', function () {
    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importe = GerenciadorImporte::criar(
        UploadedFile::fake()->createWithContent('dados.csv', "nome,nif\nJoão,1\n,2\n"),
        ImportadorSimulado::class
    );

    $resposta = $this->actingAs($usuario)->get("/importes/{$importe->uuid}/erros");

    $resposta->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.error_code', 'validation_failed');
});

test('o endpoint de exporte de erros transmite um csv', function () {
    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    $importe = GerenciadorImporte::criar(
        UploadedFile::fake()->createWithContent('dados.csv', "nome,nif\nJoão,1\n,2\n"),
        ImportadorSimulado::class
    );

    $resposta = $this->actingAs($usuario)->get("/importes/{$importe->uuid}/erros/exporte");

    $resposta->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

test('os endpoints exigem autenticação', function () {
    $this->get('/importes')->assertRedirect('/login');
    $this->post('/importes', [])->assertRedirect('/login');
});

test('utilizadores não podem aceder às importações de outros utilizadores', function () {
    $dono = User::factory()->create();
    $outro = User::factory()->create();

    $this->actingAs($dono);
    $importe = GerenciadorImporte::criar(
        UploadedFile::fake()->createWithContent('dados.csv', "nome\nJoão\n"),
        ImportadorSimulado::class
    );

    $this->actingAs($outro)->get("/importes/{$importe->uuid}")->assertForbidden();
});
