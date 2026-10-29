<?php

namespace Kuteka\LaravelImport;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Kuteka\LaravelImport\Commands\PodarImportesCommand;
use Kuteka\LaravelImport\Contracts\AutorizacaoImporte;
use Kuteka\LaravelImport\Contracts\ContextoInquilino;
use Kuteka\LaravelImport\Contracts\ResolvedorUsuario;
use Kuteka\LaravelImport\Models\Importe;
use Kuteka\LaravelImport\Services\GerenciadorImporte;
use Kuteka\LaravelImport\Support\AutorizacaoImportePadrao;
use Kuteka\LaravelImport\Support\ResolvedorUsuarioPadrao;
use Kuteka\LaravelImport\Tenancy\ContextoInquilinoNulo;

class LaravelImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/importe.php', 'importe');

        $this->app->singleton(GerenciadorImporte::class);

        $this->app->singleton(
            AutorizacaoImporte::class,
            fn () => $this->app->make(config('importe.authorization', AutorizacaoImportePadrao::class))
        );

        $this->app->singleton(ResolvedorUsuario::class, fn () => $this->app->make(ResolvedorUsuarioPadrao::class));

        $this->app->singleton(
            ContextoInquilino::class,
            fn () => $this->app->make(config('importe.tenancy.adapter') ?: ContextoInquilinoNulo::class)
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ((bool) config('importe.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/importe.php');
        }

        $this->commands([PodarImportesCommand::class]);

        $this->publishes([
            __DIR__.'/../config/importe.php' => config_path('importe.php'),
        ], 'importe-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'importe-migrations');

        $this->registrarCanalTransmissao();

        $this->agendarLimpeza();
    }

    private function registrarCanalTransmissao(): void
    {
        if (! (bool) config('importe.broadcast.enabled', true)) {
            return;
        }

        Broadcast::channel('importes.{uuid}', function ($usuario, string $uuid) {
            if ($usuario === null) {
                return false;
            }

            $importe = Importe::query()->where('uuid', $uuid)->first();

            return $importe !== null
                && $this->app->make(AutorizacaoImporte::class)->podeVer($usuario, $importe);
        });
    }

    private function agendarLimpeza(): void
    {
        if (! (bool) config('importe.retention.schedule', false)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $agenda): void {
            $agenda->command('importes:podar')
                ->dailyAt((string) config('importe.retention.schedule_time', '02:30'));
        });
    }
}
