<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Kuteka\LaravelImport\Events\ImporteConcluido;
use Kuteka\LaravelImport\Events\ImporteConcluidoComErros;
use Kuteka\LaravelImport\Events\ImporteFalhou;
use Kuteka\LaravelImport\Events\ImporteIniciado;
use Kuteka\LaravelImport\Events\ImporteProgressoAtualizado;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Services\GerenciadorImporte;
use Kuteka\LaravelImport\Tests\Stubs\ImportadorSimulado;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    ImportadorSimulado::reset();
});

function transmitirCsv(string $csv, array $opcoes = []): Importe
{
    Storage::fake('local');
    $usuario = User::factory()->create();
    auth()->login($usuario);

    $ficheiro = UploadedFile::fake()->createWithContent('dados.csv', $csv);

    return GerenciadorImporte::criar($ficheiro, ImportadorSimulado::class, $opcoes)->fresh();
}

test('dispara eventos de ciclo de vida durante uma importação bem-sucedida', function () {
    Event::fake([ImporteConcluido::class, ImporteIniciado::class]);

    $csv = "nome,nif\nJoão,1\nMaria,2\n";
    $importe = transmitirCsv($csv);

    Event::assertDispatched(ImporteIniciado::class, fn ($evento) => $evento->importe->uuid === $importe->uuid);

    Event::assertDispatched(ImporteConcluido::class, function ($evento) use ($importe) {
        return $evento->importe->uuid === $importe->uuid
            && $evento->broadcastOn()[0]->name === "private-importes.{$importe->uuid}";
    });
});

test('dispara o evento concluído com erros no modo parcial', function () {
    Event::fake([ImporteConcluidoComErros::class]);

    transmitirCsv("nome,nif\nJoão,1\n,2\n");

    Event::assertDispatched(ImporteConcluidoComErros::class);
});

test('dispara o evento falhou no modo estrito', function () {
    Event::fake([ImporteFalhou::class]);

    transmitirCsv("nome,nif\nJoão,1\n,2\n", ['modo' => 'strict']);

    Event::assertDispatched(ImporteFalhou::class);
});

test('os eventos de progresso são transmitidos no canal privado', function () {
    Event::fake([ImporteProgressoAtualizado::class]);

    $importe = transmitirCsv("nome\n".implode("\n", array_map(fn ($i) => "P{$i}", range(1, 6))), ['tamanho_chunk' => 2]);

    Event::assertDispatched(ImporteProgressoAtualizado::class, function ($evento) use ($importe) {
        $dados = $evento->broadcastWith();

        return $evento->broadcastOn()[0]->name === "private-importes.{$importe->uuid}"
            && $dados['importe_id'] === $importe->uuid
            && $dados['progresso'] > 0;
    });
});

test('não transmite quando desativado', function () {
    Event::fake([ImporteProgressoAtualizado::class]);

    config(['importe.broadcast.enabled' => false]);

    transmitirCsv("nome\nA\nB\nC\nD\n", ['tamanho_chunk' => 1]);

    Event::assertNotDispatched(ImporteProgressoAtualizado::class);
});
