<?php

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator;

use Illuminate\Support\ServiceProvider;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Commands\MigratePathsFromUuidCommand;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Commands\MigratePathsToUuidCommand;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Commands\PruneUuidPathsCommand;

class UuidPathGeneratorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MigratePathsToUuidCommand::class,
                MigratePathsFromUuidCommand::class,
                PruneUuidPathsCommand::class,
            ]);
        }
    }
}
