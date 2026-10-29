<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kuteka\LaravelImport\Contracts\ContextoInquilino;
use Kuteka\LaravelImport\Services\GerenciadorImporte;
use Kuteka\LaravelImport\Tests\ContextoInquilinoFalso;
use Kuteka\LaravelImport\Tests\Stubs\ImportadorSimulado;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    ImportadorSimulado::reset();
    ContextoInquilinoFalso::reset();
    app()->singleton(ContextoInquilino::class, fn () => new ContextoInquilinoFalso);
});

test('guarda o id do inquilino ao criar uma importação', function () {
    ContextoInquilinoFalso::$idAtual = 'inquilino-a';

    Storage::fake('local');

    $usuario = User::factory()->create();
    auth()->login($usuario);

    $ficheiro = UploadedFile::fake()->createWithContent('dados.csv', "nome\nJoão\n");
    $importe = GerenciadorImporte::criar($ficheiro, ImportadorSimulado::class);

    expect($importe->tenant_id)->toBe('inquilino-a');
});

test('restaura o contexto de inquilino dentro dos jobs', function () {
    ContextoInquilinoFalso::$idAtual = 'inquilino-a';

    Storage::fake('local');

    $usuario = User::factory()->create();
    auth()->login($usuario);

    $ficheiro = UploadedFile::fake()->createWithContent('dados.csv', "nome\nJoão\n");
    GerenciadorImporte::criar($ficheiro, ImportadorSimulado::class);

    expect(ContextoInquilinoFalso::$inicializados)->toContain('inquilino-a');
});

test('não restaura o contexto de inquilino para importações de inquilino único', function () {
    Storage::fake('local');

    $usuario = User::factory()->create();
    auth()->login($usuario);

    $ficheiro = UploadedFile::fake()->createWithContent('dados.csv', "nome\nJoão\n");
    GerenciadorImporte::criar($ficheiro, ImportadorSimulado::class);

    expect(ContextoInquilinoFalso::$inicializados)->toBeEmpty();
});
