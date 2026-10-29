<?php

namespace Kuteka\LaravelImport\Exporters;

use Illuminate\Database\Eloquent\Builder;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Models\ImporteErro;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ErrosExporte implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly Importe $importe) {}

    public function query(): Builder
    {
        return ImporteErro::query()->where('import_id', $this->importe->id)->orderBy('id');
    }

    public function headings(): array
    {
        return ['linha', 'dados_originais', 'coluna', 'valor', 'codigo_erro', 'mensagem'];
    }

    /**
     * @param  ImporteErro  $erro
     * @return array<int, mixed>
     */
    public function map($erro): array
    {
        return [
            $erro->row_number,
            json_encode($erro->data['row'] ?? null),
            $erro->column,
            is_scalar($erro->value) ? $erro->value : json_encode($erro->value),
            $erro->error_code,
            $erro->message,
        ];
    }
}
