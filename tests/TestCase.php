<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators\UuidLevel2PathGenerator;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\UuidFileRemover;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\UuidPathGeneratorServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('media-library.disk_name', 'public');
        $app['config']->set('media-library.prefix', '');
        $app['config']->set('media-library.path_generator', UuidLevel2PathGenerator::class);
        $app['config']->set('media-library.file_remover_class', UuidFileRemover::class);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            MediaLibraryServiceProvider::class,
            UuidPathGeneratorServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
    }
}
