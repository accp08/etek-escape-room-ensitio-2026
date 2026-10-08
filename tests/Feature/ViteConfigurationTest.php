<?php

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Tests\TestCase;

class ViteConfigurationTest extends TestCase
{
    public function test_uses_the_build_directory_configured_in_the_environment(): void
    {
        $vite = app(Vite::class);
        $buildDirectory = (new \ReflectionClass($vite))
            ->getProperty('buildDirectory');

        $buildDirectory->setAccessible(true);

        $this->assertSame('build', config('vite.build_directory'));
        $this->assertSame('build', $buildDirectory->getValue($vite));
    }
}
