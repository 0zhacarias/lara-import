<?php

namespace Kuteka\LaravelImport\Enums;

enum EstrategiaDuplicado: string
{
    case Inserir = 'insert';
    case Atualizar = 'update';
    case Upsert = 'upsert';
}
