<?php

namespace Kuteka\LaravelImport\Support;

use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Decide se uma exceção merece repetição na fila (falhas transitórias)
 * ou não (erros de validação/dados).
 */
class ClassificadorRepeticao
{
    private const ESTADOS_SQL_REPETIVEIS = [
        '40001', // falha de serialização / deadlock
        '40P01', // deadlock detetado
        '08003', '08006', '08001', '08004', // falhas de conexão
        'HY000', // erro geral (cobre MySQL 1205/1213)
        '57P01', '57P02', '57P03', // encerramento administrativo
    ];

    private const CODIGOS_MYSQL_REPETIVEIS = [1205, 1213, 2006, 2013];

    public function deveRepetir(Throwable $excecao): bool
    {
        $excecao = $this->causaRaiz($excecao);
        $mensagem = strtolower($excecao->getMessage());

        if (str_contains($mensagem, 'deadlock')
            || str_contains($mensagem, 'lock wait timeout')
            || str_contains($mensagem, 'server has gone away')
            || str_contains($mensagem, 'connection')
            || str_contains($mensagem, 'timed out')) {
            return true;
        }

        if ($excecao instanceof DeadlockException) {
            return true;
        }

        if ($excecao instanceof QueryException) {
            $estadoSql = $excecao->errorInfo[0] ?? null;
            $codigoErro = $excecao->errorInfo[1] ?? null;

            if (is_string($estadoSql) && in_array($estadoSql, self::ESTADOS_SQL_REPETIVEIS, true)) {
                return true;
            }

            return is_numeric($codigoErro) && in_array((int) $codigoErro, self::CODIGOS_MYSQL_REPETIVEIS, true);
        }

        return false;
    }

    private function causaRaiz(Throwable $excecao): Throwable
    {
        while ($excecao->getPrevious() !== null) {
            $excecao = $excecao->getPrevious();
        }

        return $excecao;
    }
}
