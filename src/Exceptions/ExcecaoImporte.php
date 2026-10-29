<?php

namespace Kuteka\LaravelImport\Exceptions;

use RuntimeException;

class ExcecaoImporte extends RuntimeException
{
    public function __construct(string $message, public readonly array $contexto = [])
    {
        parent::__construct($message);
    }
}
