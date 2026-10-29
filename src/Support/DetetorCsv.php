<?php

namespace Kuteka\LaravelImport\Support;

/**
 * Deteta o delimitador e a codificação de um CSV a partir de uma amostra bruta.
 */
class DetetorCsv
{
    public function detetarDelimitador(string $amostra, array $candidatos = [',', ';', "\t", '|']): string
    {
        $melhor = null;
        $melhorContagem = -1;

        foreach ($candidatos as $delimitador) {
            $contagem = substr_count($amostra, $delimitador);

            if ($contagem > $melhorContagem) {
                $melhorContagem = $contagem;
                $melhor = $delimitador;
            }
        }

        return $melhor ?? ',';
    }

    public function detetarCodificacao(string $amostra): string
    {
        if (str_starts_with($amostra, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }

        if (str_starts_with($amostra, "\xFF\xFE")) {
            return 'UTF-16LE';
        }

        if (str_starts_with($amostra, "\xFE\xFF")) {
            return 'UTF-16BE';
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($amostra, 'UTF-8')) {
            return 'UTF-8';
        }

        if (function_exists('mb_detect_encoding')) {
            $detetada = mb_detect_encoding($amostra, ['Windows-1252', 'ISO-8859-1'], true);

            if ($detetada !== false) {
                return $detetada;
            }
        }

        return 'UTF-8';
    }

    public function converter(string $valor, string $codificacao): string
    {
        $valor = $this->removerBom($valor);

        if (in_array($codificacao, ['UTF-8', 'ASCII'], true)) {
            return $valor;
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($valor, 'UTF-8', $codificacao);
        }

        if (function_exists('iconv')) {
            $convertido = @iconv($codificacao, 'UTF-8//TRANSLIT//IGNORE', $valor);

            if ($convertido !== false) {
                return $convertido;
            }
        }

        return $valor;
    }

    public function removerBom(string $valor): string
    {
        if (str_starts_with($valor, "\xEF\xBB\xBF")) {
            $valor = substr($valor, 3);
        }

        if (str_starts_with($valor, "\xFF\xFE") || str_starts_with($valor, "\xFE\xFF")) {
            $valor = substr($valor, 2);
        }

        return $valor;
    }
}
