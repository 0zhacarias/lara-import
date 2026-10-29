<?php

namespace Kuteka\LaravelImport\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kuteka\LaravelImport\Enums\EscopoRepeticao;
use Kuteka\LaravelImport\Exporters\ErrosExporte;
use Kuteka\LaravelImport\Exporters\FluxoCsvErros;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Services\GerenciadorImporte;
use Maatwebsite\Excel\Facades\Excel;

class ImporteController extends Controller
{
    public function index(Request $pedido): JsonResponse
    {
        $importes = Importe::query()
            ->where('user_id', $pedido->user()?->getAuthIdentifier())
            ->latest()
            ->paginate($pedido->integer('per_page', 25));

        return response()->json([
            'data' => $importes->map(fn (Importe $importe) => $this->dados($importe)),
            'meta' => [
                'current_page' => $importes->currentPage(),
                'last_page' => $importes->lastPage(),
                'total' => $importes->total(),
            ],
        ]);
    }

    public function store(Request $pedido): JsonResponse
    {
        $pedido->validate([
            'file' => ['required', 'file'],
            'importer' => ['required', 'string'],
            'options' => ['nullable', 'array'],
        ]);

        $importe = GerenciadorImporte::criar(
            arquivo: $pedido->file('file'),
            importador: $pedido->string('importer')->toString(),
            opcoes: $pedido->input('options') ?? [],
        );

        return response()->json($this->dados($importe), 201);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json($this->dados(GerenciadorImporte::status($uuid)));
    }

    public function cancelar(string $uuid): JsonResponse
    {
        return response()->json($this->dados(GerenciadorImporte::cancelar($uuid)));
    }

    public function repetir(Request $pedido, string $uuid): JsonResponse
    {
        $escopo = EscopoRepeticao::tryFrom((string) $pedido->input('scope', EscopoRepeticao::Inteiro->value))
            ?? EscopoRepeticao::Inteiro;

        return response()->json($this->dados(GerenciadorImporte::repetir($uuid, $escopo)));
    }

    public function erros(Request $pedido, string $uuid): JsonResponse
    {
        $importe = GerenciadorImporte::encontrar($uuid);

        $erros = $importe->erros()
            ->orderBy('row_number')
            ->paginate($pedido->integer('per_page', 100));

        return response()->json([
            'data' => $erros->items(),
            'meta' => [
                'current_page' => $erros->currentPage(),
                'last_page' => $erros->lastPage(),
                'total' => $erros->total(),
            ],
        ]);
    }

    public function exportarErros(Request $pedido, string $uuid): mixed
    {
        $importe = GerenciadorImporte::encontrar($uuid);

        if (strtolower((string) $pedido->query('format')) === 'xlsx') {
            return Excel::download(
                new ErrosExporte($importe),
                "importe-{$importe->uuid}-erros.xlsx",
                \Maatwebsite\Excel\Excel::XLSX
            );
        }

        return app(FluxoCsvErros::class)->resposta($importe);
    }

    public function destroy(string $uuid): JsonResponse
    {
        GerenciadorImporte::excluir($uuid);

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function dados(Importe $importe): array
    {
        return [
            'uuid' => $importe->uuid,
            'nome_arquivo' => $importe->original_name,
            'importador' => $importe->importer,
            ...$importe->paraResultado()->toArray(),
        ];
    }
}
