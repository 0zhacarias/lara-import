<?php

namespace Kuteka\LaravelImport\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\DTOs\ResultadoImporte;
use Kuteka\LaravelImport\Enums\ModoImporte;
use Kuteka\LaravelImport\Enums\StatusImporte;

/**
 * @property int $id
 * @property string $uuid
 * @property int|string|null $user_id
 * @property mixed $tenant_id
 * @property string $file_name
 * @property string $original_name
 * @property string $file_path
 * @property string $disk
 * @property string|null $mime_type
 * @property string $extension
 * @property int $file_size
 * @property string $importer
 * @property StatusImporte $status
 * @property ModoImporte $mode
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $successful_rows
 * @property int $failed_rows
 * @property int $skipped_rows
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $error_message
 * @property array $options
 * @property array $metadata
 */
class Importe extends Model
{
    protected $table = 'imports';

    protected $guarded = [];

    protected $casts = [
        'status' => StatusImporte::class,
        'mode' => ModoImporte::class,
        'options' => 'array',
        'metadata' => 'array',
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'successful_rows' => 'integer',
        'failed_rows' => 'integer',
        'skipped_rows' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Importe $importe): void {
            $importe->uuid ??= (string) Str::ulid();
            $importe->status ??= StatusImporte::Pendente;
            $importe->mode ??= ModoImporte::Parcial;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ImporteChunk::class, 'import_id');
    }

    public function erros(): HasMany
    {
        return $this->hasMany(ImporteErro::class, 'import_id');
    }

    public function obterOpcoes(): OpcoesImporte
    {
        return OpcoesImporte::deArray($this->options ?? []);
    }

    public function porcentagemProgresso(): float
    {
        if ($this->total_rows <= 0) {
            return $this->status !== null && $this->status->ehFinal() ? 100.0 : 0.0;
        }

        return min(100.0, round(($this->processed_rows / $this->total_rows) * 100, 1));
    }

    public function duracao(): float
    {
        $fim = $this->completed_at ?? ($this->cancelled_at ?? now());

        if ($this->started_at === null) {
            return 0.0;
        }

        return round((float) $this->started_at->diffInMilliseconds($fim) / 1000, 2);
    }

    public function cancelamentoSolicitado(): bool
    {
        return $this->status === StatusImporte::Cancelando;
    }

    public function estaProcessando(): bool
    {
        return in_array($this->status, [StatusImporte::Pendente, StatusImporte::Processando], true);
    }

    public function escopoParaInquilino(Builder $consulta, mixed $inquilinoId): Builder
    {
        return $consulta->where('tenant_id', $inquilinoId);
    }

    public function paraResultado(): ResultadoImporte
    {
        return ResultadoImporte::doImporte($this);
    }
}
