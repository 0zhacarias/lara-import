<?php

namespace Kuteka\LaravelImport\Enums;

enum ModoImporte: string
{
    case Parcial = 'partial';
    case Estrito = 'strict';
}
