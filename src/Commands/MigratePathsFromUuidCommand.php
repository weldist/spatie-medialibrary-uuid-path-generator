<?php

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Contracts\Filesystem\Factory;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators\AbstractUuidPathGenerator;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Support\ShardPath;

class MigratePathsFromUuidCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'media-library:migrate-paths-from-uuid
        {disk? : Disk to scan for files to migrate (default: media-library.disk_name)}
        {--dry-run : Preview changes without moving any files}
        {--force : Skip the production confirmation prompt}';

    protected $description = 'Migrate media files from the configured UUID path generator back to DefaultPathGenerator (ID-based).';

    private AbstractUuidPathGenerator $oldGenerator;

    private DefaultPathGenerator $newGenerator;

    public function handle(Factory $fileSystem): void
    {
        if (! $this->confirmToProceed()) {
            return;
        }

        $diskName = $this->argument('disk') ?: config('media-library.disk_name');
        $isDryRun = $this->option('dry-run');

        $this->oldGenerator = $this->resolveUuidGenerator();
        $this->newGenerator = new DefaultPathGenerator();

        $migrated = 0;
        $skipped = 0;

        /** @var class-string<Media> $mediaModel */
        $mediaModel = config('media-library.media_model', Media::class);

        $mediaModel::query()->lazy()->each(function (Media $media) use (
            $diskName, $isDryRun, $fileSystem, &$migrated, &$skipped
        ) {
            if (! $media->uuid) {
                $this->warn("Skipping Media[id={$media->id}]: no UUID.");
                $skipped++;

                return;
            }

            $disk = $media->disk ?: $diskName;
            $conversionsDisk = $media->conversions_disk ?: $disk;

            $oldPath = $this->oldGenerator->getPath($media);
            $newPath = $this->newGenerator->getPath($media);

            if (! $fileSystem->disk($disk)->directoryExists($oldPath)) {
                $skipped++;

                return;
            }

            if ($isDryRun) {
                $this->line("Would move: {$oldPath} → {$newPath}");
                $migrated++;

                return;
            }

            $this->moveFile($fileSystem, $disk, $oldPath . $media->file_name, $newPath . $media->file_name);

            $this->moveDirectory(
                $fileSystem,
                $conversionsDisk,
                $this->oldGenerator->getPathForConversions($media),
                $this->newGenerator->getPathForConversions($media),
            );

            $this->moveDirectory(
                $fileSystem,
                $conversionsDisk,
                $this->oldGenerator->getPathForResponsiveImages($media),
                $this->newGenerator->getPathForResponsiveImages($media),
            );

            if (! $fileSystem->disk($disk)->allFiles($oldPath)) {
                $fileSystem->disk($disk)->deleteDirectory($oldPath);
            }

            $this->cleanEmptyShardDirectories($fileSystem, $disk, $media->uuid);

            if ($conversionsDisk !== $disk) {
                $this->cleanEmptyShardDirectories($fileSystem, $conversionsDisk, $media->uuid);
            }

            $this->line("Moved: {$oldPath} → {$newPath}");
            $migrated++;
        });

        $this->info("Done! Migrated: {$migrated}, Skipped: {$skipped}.");
    }

    /**
     * Instantiate the UUID generator declared in `media-library.path_generator`.
     *
     * Refuses to run unless that config points at one of this package's shard
     * generators — otherwise the command has no way to locate the source files.
     */
    private function resolveUuidGenerator(): AbstractUuidPathGenerator
    {
        $generator = config('media-library.path_generator');

        if (! is_string($generator) || ! is_subclass_of($generator, AbstractUuidPathGenerator::class)) {
            throw new RuntimeException(
                'media-library.path_generator must be a subclass of ' . AbstractUuidPathGenerator::class
                . ' before running media-library:migrate-paths-from-uuid.'
            );
        }

        return new $generator();
    }

    private function cleanEmptyShardDirectories(Factory $fileSystem, string $disk, string $uuid): void
    {
        $depth = $this->oldGenerator->depth();
        $prefix = $this->prefix();

        foreach (ShardPath::parentsFor($uuid, $depth, $prefix) as $shard) {
            if (! $fileSystem->disk($disk)->allFiles($shard)) {
                $fileSystem->disk($disk)->deleteDirectory($shard);
            }
        }
    }

    private function prefix(): string
    {
        $prefix = (string) config('media-library.prefix', '');

        return $prefix !== '' ? rtrim($prefix, '/') . '/' : '';
    }

    private function moveFile(Factory $fileSystem, string $disk, string $from, string $to): void
    {
        if (! $fileSystem->disk($disk)->exists($from)) {
            return;
        }

        $stream = $fileSystem->disk($disk)->readStream($from);
        $fileSystem->disk($disk)->put($to, $stream);
        $fileSystem->disk($disk)->delete($from);
    }

    private function moveDirectory(Factory $fileSystem, string $disk, string $from, string $to): void
    {
        if (! $fileSystem->disk($disk)->directoryExists($from)) {
            return;
        }

        foreach ($fileSystem->disk($disk)->allFiles($from) as $file) {
            $relative = substr($file, strlen($from));
            $this->moveFile($fileSystem, $disk, $file, $to . $relative);
        }

        if (! $fileSystem->disk($disk)->allFiles($from)) {
            $fileSystem->disk($disk)->deleteDirectory($from);
        }
    }
}