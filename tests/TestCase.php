<?php

namespace Mmoollllee\FilamentBuilderExtensions\Tests;

use Illuminate\Support\Facades\View;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use WithWorkbench;

    /**
     * Filament, Livewire and Blade Icons register themselves as in an app.
     *
     * @var bool
     */
    protected $enablesPackageDiscoveries = true;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(dirname(__DIR__).'/workbench/resources/views');
    }
}
