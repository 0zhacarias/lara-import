<?php

namespace Kuteka\LaravelImport\Readers;

use Illuminate\Contracts\Container\Container;
use Kuteka\LaravelImport\Contracts\LeitorArquivo;
use Kuteka\LaravelImport\DTOs\OpcoesLeitor;
use Kuteka\LaravelImport\Exceptions\ExcecaoFormatoNaoSuportado;

class FabricaLeitor
{
    public function __construct(private readonly Container $container) {}

    public function criar(string $extensao, ?OpcoesLeitor $opcoes = null): LeitorArquivo
    {
        $extensao = strtolower(ltrim(trim($extensao), '.'));

        $leitor = match ($extensao) {
            'csv', 'txt' => $this->container->make(LeitorCsv::class),
            'xlsx' => $this->container->make(LeitorXlsx::class),
            'xls' => $this->container->make(LeitorXls::class),
            default => throw new ExcecaoFormatoNaoSuportado(
                sprintf('O formato de ficheiro [%s] não é suportado. Formatos suportados: csv, xlsx, xls.', $extensao)
            ),
        };

        return $leitor->definirOpcoes($opcoes ?? new OpcoesLeitor);
    }
}
