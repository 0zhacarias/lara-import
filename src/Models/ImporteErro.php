<?php

namespace Kuteka\LaravelImport\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $import_id
 * @property int|null $chunk_id
 * @property int $row_number
 * @property string|null $column
 * @property mixed $value
 * @property string $error_code
 * @property string $message
 * @property array $data
 */
class ImporteErro extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'import_errors';

    protected $guarded = [];

    protected $casts = [
        'row_number' => 'integer',
        'data' => 'array',
    ];

    public function importe(): BelongsTo
    {
        return $this->belongsTo(Importe::class, 'import_id');
    }

    public function escopoParaImporte(Builder $consulta, Importe $importe): Builder
    {
        return $consulta->where('import_id', $importe->id);
    }
}
