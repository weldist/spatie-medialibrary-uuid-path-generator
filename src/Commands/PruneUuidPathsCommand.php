<?php

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Contracts\Filesystem\Factory;
use Spatie\MediaLibrary\MediaCollections\Exceptions\DiskDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators\AbstractUuidPathGenerator;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Support\ShardPath;

class PruneUuidPathsCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'media-library:prune-uuid-paths
        {disk? : Disk to scan for orphaned UUID directories (default: media-library.disk_name)}
        {--shard=* : Limit the scan to the given first-level shards (e.g. --shard=55 --shard=ab)}
        {--dry-run : List directories that will be removed without removing them}
        {--force : Skip the production confirmation prompt}';

    protected $description = 'Clean orphaned UUID media directories left behind by the UUID path generator.';

    public function handle(Factory $fileSystem): void
    {
        if (! $this->confirmToProceed()) {
            return;
        }

        $diskName = $this->argument('disk') ?: config('media-library.disk_name');

        if (is_null(config("filesystems.disks.{$diskName}"))) {
            throw DiskDoesNotExist::create($diskName);
        }

        $isDryRun = $this->option('dry-run');

        $prefix = config('media-library.prefix', '');
        $prefix = $prefix !== '' ? trim($prefix, '/') . '/' : '';

        /** @var class-string<Media> $mediaModel */
        $mediaModel = config('media-library.media_model', Media::class);

        $existingUuids = $mediaModel::query()
            ->whereNotNull('uuid')
            ->pluck('uuid')
            ->flip();

        $shards = $this->resolveShards();

        $orphaned = collect();

        foreach ($shards as $shard) {
            $scanPrefix = $prefix . $shard;

            $directories = $fileSystem->disk($diskName)->allDirectories($scanPrefix);

            $orphaned = $orphaned->concat(
                collect($directories)
                    ->filter(fn (string $dir) => (bool) preg_match(
                        '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                        $dir
                    ))
                    ->reject(fn (string $dir) => $existingUuids->has(basename($dir)))
            );
        }

        if ($orphaned->isEmpty()) {
            $this->info('No orphaned UUID directories found.');

            return;
        }

        $orphaned->each(function (string $dir) use ($diskName, $isDryRun, $fileSystem, $prefix) {
            if (! $isDryRun) {
                $fileSystem->disk($diskName)->deleteDirectory($dir);
                $this->cleanEmptyShardParents($dir, $diskName, $prefix, $fileSystem);
            }

            $this->info("Orphaned UUID directory `{$dir}` " . ($isDryRun ? 'found' : 'has been removed'));
        });

        $this->info('All done!');
    }

    /**
     * Resolve which first-level shards to scan. Defaults to all 256 (`00`..`ff`)
     * so a single command sweep walks the entire UUID tree, but bounded to the
     * shard prefix instead of the disk root — this keeps remote-disk LIST calls
     * focused and allows splitting the work via `--shard=`.
     *
     * @return list<string>
     */
    protected function resolveShards(): array
    {
        $shards = $this->option('shard');

        if (! empty($shards)) {
            return array_values(array_map(
                fn (string $shard) => strtolower(trim($shard, '/')),
                $shards
            ));
        }

        return array_map(fn (int $i) => sprintf('%02x', $i), range(0, 255));
    }

    protected function cleanEmptyShardParents(string $uuidDir, string $disk, string $prefix, Factory $fileSystem): void
    {
        $depth = $this->resolveDepth();

        if ($depth === 0) {
            return;
        }

        $relative = $prefix !== '' ? substr($uuidDir, strlen($prefix)) : $uuidDir;
        $parts = explode('/', $relative);

        // Expected structure: shard_1/.../shard_n/uuid
        if (count($parts) < $depth + 1) {
            return;
        }

        $uuid = basename($uuidDir);

        foreach (ShardPath::parentsFor($uuid, $depth, $prefix) as $shard) {
            if (! $fileSystem->disk($disk)->allFiles($shard)) {
                $fileSystem->disk($disk)->deleteDirectory($shard);
            }
        }
    }

    protected function resolveDepth(): int
    {
        $generator = config('media-library.path_generator');

        if (is_string($generator) && is_subclass_of($generator, AbstractUuidPathGenerator::class)) {
            return (new $generator())->depth();
        }

        return 0;
    }
}
