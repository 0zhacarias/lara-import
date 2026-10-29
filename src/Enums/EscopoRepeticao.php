<?php

namespace Kuteka\LaravelImport\Enums;

enum EscopoRepeticao: string
{
    case Inteiro = 'entire';
    case ChunksFalhados = 'failed_chunks';
}
