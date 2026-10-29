<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Kuteka\LaravelImport\Enums\ModoImporte;
use Kuteka\LaravelImport\Enums\StatusImporte;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Notifications\ImporteFinalizadoNotification;
use Kuteka\LaravelImport\Services\GerenciadorImporte;
use Kuteka\LaravelImport\Services\NotificadorImporte;
use Kuteka\LaravelImport\Tests\Stubs\ImportadorSimulado;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    ImportadorSimulado::reset();
});

test('notifica o utilizador quando uma importação termina', function () {
    Notification::fake();

    Storage::fake('local');

    $usuario = User::factory()->create();
    auth()->login($usuario);

    $ficheiro = UploadedFile::fake()->createWithContent('dados.csv', "nome\nJoão\n");
    $importe = GerenciadorImporte::criar($ficheiro, ImportadorSimulado::class)->fresh();

    expect($importe->status)->toBe(StatusImporte::Concluido);

    Notification::assertSentTo(
        $usuario,
        ImporteFinalizadoNotification::class,
        fn ($notificacao, $canais) => $notificacao->toArray($usuario)['importe_id'] === $importe->uuid
    );
});

test('não notifica quando notificar_usuario está desativado', function () {
    Notification::fake();

    Storage::fake('local');

    $usuario = User::factory()->create();
    auth()->login($usuario);

    $ficheiro = UploadedFile::fake()->createWithContent('dados.csv', "nome\nJoão\n");
    GerenciadorImporte::criar($ficheiro, ImportadorSimulado::class, ['notificar_usuario' => false]);

    Notification::assertNothingSent();
});

test('não notifica quando o utilizador não pode ser resolvido', function () {
    Notification::fake();

    Storage::fake('local');

    $importe = Importe::create([
        'user_id' => '999999',
        'file_name' => 'x.csv',
        'original_name' => 'x.csv',
        'file_path' => 'imports/x.csv',
        'disk' => 'local',
        'extension' => 'csv',
        'file_size' => 10,
        'importer' => ImportadorSimulado::class,
        'status' => StatusImporte::Pendente,
        'mode' => ModoImporte::Parcial,
    ]);

    app(NotificadorImporte::class)->notificar($importe);

    Notification::assertNothingSent();
});
