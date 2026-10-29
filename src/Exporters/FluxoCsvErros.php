<?php

namespace Kuteka\LaravelImport\Exporters;

use Illuminate\Support\Facades\Response;
use Kuteka\LaravelImport\Models\Importe;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Transmite os erros de importação em CSV sem carregar tudo em memória.
 */
class FluxoCsvErros
{
    public function resposta(Importe $importe): StreamedResponse
    {
        return Response::streamDownload(function () use ($importe): void {
            $saida = fopen('php://output', 'wb');
            fputcsv($saida, ['linha', 'dados_originais', 'coluna', 'valor', 'codigo_erro', 'mensagem']);

            foreach ($importe->erros()->lazyById(500) as $erro) {
                fputcsv($saida, [
                    $erro->row_number,
                    json_encode($erro->data['row'] ?? null),
                    (string) $erro->column,
                    is_scalar($erro->value) ? (string) $erro->value : json_encode($erro->value),
                    $erro->error_code,
                    $erro->message,
                ]);
            }

            fclose($saida);
        }, "importe-{$importe->uuid}-erros.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
