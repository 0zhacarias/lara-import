<?php

namespace Kuteka\LaravelImport\Services;

use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\Exceptions\ExcecaoArquivoInvalido;
use Kuteka\LaravelImport\Exceptions\ExcecaoFormatoNaoSuportado;
use Kuteka\LaravelImport\Exceptions\ExcecaoTamanhoMaximoArquivoExcedido;

/**
 * Valida extensão, tipo MIME, tamanho e legibilidade.
 * Nunca confia apenas na extensão do ficheiro.
 */
class ValidadorArquivo
{
    public function extensaoDe(string $nomeFicheiro): string
    {
        $extensao = strtolower(pathinfo($nomeFicheiro, PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,10}$/', $extensao) === 1 ? $extensao : '';
    }

    public function validar(string $caminho, string $extensao, ?OpcoesImporte $opcoes = null): void
    {
        $opcoes ??= OpcoesImporte::criar();

        $extensoesPermitidas = (array) config('importe.allowed_extensions', ['csv', 'xlsx', 'xls']);

        if (! in_array(strtolower($extensao), $extensoesPermitidas, true)) {
            throw new ExcecaoFormatoNaoSuportado(sprintf(
                'A extensão do ficheiro [%s] não é permitida. Extensões permitidas: %s.',
                $extensao,
                implode(', ', $extensoesPermitidas)
            ));
        }

        if (! is_file($caminho) || ! is_readable($caminho)) {
            throw new ExcecaoArquivoInvalido('O ficheiro de importação está em falta ou não é legível.');
        }

        $tamanho = (int) filesize($caminho);

        if ($tamanho <= 0) {
            throw new ExcecaoArquivoInvalido('O ficheiro de importação está vazio.');
        }

        $tamanhoMaximo = $opcoes->tamanhoMaximoArquivo ?? (int) config('importe.max_file_size');

        if ($tamanho > $tamanhoMaximo) {
            throw new ExcecaoTamanhoMaximoArquivoExcedido(sprintf(
                'O ficheiro de importação excede o tamanho máximo permitido de %d bytes.',
                $tamanhoMaximo
            ));
        }

        $this->garantirTipoMime($caminho, $extensao);
    }

    private function garantirTipoMime(string $caminho, string $extensao): void
    {
        $permitidos = (array) config('importe.allowed_mime_types', []);

        if ($permitidos === []) {
            return;
        }

        $detetado = (new \finfo(FILEINFO_MIME_TYPE))->file($caminho);

        if ($detetado === false) {
            return;
        }

        $detetado = strtolower($detetado);

        if (in_array($detetado, $permitidos, true)) {
            return;
        }

        // O conteúdo de CSV textual é frequentemente reportado como outros tipos text/*.
        if ($extensao === 'csv' && (str_starts_with($detetado, 'text/') || $detetado === 'application/csv')) {
            return;
        }

        // xlsx é um contentor zip.
        if ($extensao === 'xlsx' && in_array($detetado, ['application/zip', 'application/octet-stream'], true)) {
            return;
        }

        throw new ExcecaoArquivoInvalido(sprintf(
            'O tipo MIME detetado [%s] não corresponde à extensão [%s].',
            $detetado,
            $extensao
        ));
    }
}
