<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kuteka\LaravelImport\Tests\Stubs\ImportadorSimulado;
use Kuteka\LaravelImport\Tests\Stubs\LoteImportadorSimulado;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    ImportadorSimulado::reset();
    LoteImportadorSimulado::reset();
});
