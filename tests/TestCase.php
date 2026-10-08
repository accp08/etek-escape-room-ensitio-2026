<?php

namespace Tests;

use App\Support\Contenido;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Textos y ajustes se cachean por proceso: cada prueba parte de cero.
        Contenido::olvidar();
    }
}
