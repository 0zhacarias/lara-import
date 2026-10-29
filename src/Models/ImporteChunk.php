<?php

namespace Kuteka\LaravelImport\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kuteka\LaravelImport\Enums\StatusChunk;

/**
 * @property int $id
 * @property int $import_id
 * @property int $chunk_number
 * @property int $start_row
 * @property int $end_row
 * @property StatusChunk $status
 * @property int $attempts
 * @property int $processed_rows
 * @property int $successful_rows
 * @property int $failed_rows
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property string|null $error_message
 */
class ImporteChunk extends Model
{
    protected $table = 'import_chunks';

    protected $guarded = [];

    protected $casts = [
        'status' => StatusChunk::class,
        'chunk_number' => 'integer',
        'start_row' => 'integer',
        'end_row' => 'integer',
        'attempts' => 'integer',
        'processed_rows' => 'integer',
        'successful_rows' => 'integer',
        'failed_rows' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ImporteChunk $chunk): void {
            $chunk->status ??= StatusChunk::Pendente;
        });
    }

    public function importe(): BelongsTo
    {
        return $this->belongsTo(Importe::class, 'import_id');
    }
}
