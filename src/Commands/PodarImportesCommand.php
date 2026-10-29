<?php

namespace Kuteka\LaravelImport\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Kuteka\LaravelImport\Enums\StatusImporte;
use Kuteka\LaravelImport\Models\Importe;

class PodarImportesCommand extends Command
{
    protected $signature = 'importes:podar
        {--dias= : Período de retenção em dias (padrão: config "importe.retention.days")}';

    protected $description = 'Remove importações antigas, os seus ficheiros, chunks e erros';

    public function handle(): int
    {
        $dias = (int) ($this->option('dias') ?: config('importe.retention.days', 30));
        $limite = now()->subDays(max(1, $dias));

        $this->info("A limpar importações com mais de {$dias} dias...");

        $contagem = 0;
        $libertado = 0;

        Importe::query()
            ->where('created_at', '<', $limite)
            ->whereNotIn('status', [
                StatusImporte::Pendente->value,
                StatusImporte::Processando->value,
                StatusImporte::Cancelando->value,
            ])
            ->lazyById(100)
            ->each(function (Importe $importe) use (&$contagem, &$libertado): void {
                if (Storage::disk($importe->disk)->delete($importe->file_path)) {
                    $libertado += $importe->file_size;
                }

                $importe->delete(); // em cascata: chunks + erros
                $contagem++;
            });

        $this->info("Limpas {$contagem} importação(ões), libertados ".round($libertado / 1048576, 2).' MB.');

        return self::SUCCESS;
    }
}
