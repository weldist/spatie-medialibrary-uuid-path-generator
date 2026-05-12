<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators\AbstractUuidPathGenerator;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Support\ShardPath;

class UuidFileRemover extends DefaultFileRemover
{
    public function removeAllFiles(Media $media): void
    {
        parent::removeAllFiles($media);

        $this->cleanEmptyShardDirectories($media, $media->disk);

        if ($media->conversions_disk && $media->disk !== $media->conversions_disk) {
            $this->cleanEmptyShardDirectories($media, $media->conversions_disk);
        }
    }

    protected function cleanEmptyShardDirectories(Media $media, string $disk): void
    {
        $uuid = $media->uuid;

        if (! $uuid) {
            return;
        }

        $depth = $this->resolveDepth();

        if ($depth === 0) {
            return;
        }

        $prefix = config('media-library.prefix', '');
        $prefix = $prefix !== '' ? rtrim($prefix, '/') . '/' : '';

        foreach (ShardPath::parentsFor($uuid, $depth, $prefix) as $shard) {
            if (! $this->filesystem->disk($disk)->allFiles($shard)) {
                $this->filesystem->disk($disk)->deleteDirectory($shard);
            }
        }
    }

    /**
     * Resolve the shard depth from the configured path generator.
     *
     * Returns 0 when the configured generator is not one of this package's
     * UUID shard generators — in which case the cascade cleanup is skipped
     * entirely (there are no shard parents to remove).
     */
    protected function resolveDepth(): int
    {
        $generator = config('media-library.path_generator');

        if (is_string($generator) && is_subclass_of($generator, AbstractUuidPathGenerator::class)) {
            return (new $generator())->depth();
        }

        return 0;
    }
}
