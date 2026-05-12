<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators;

use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Support\ShardPath;

/**
 * Base class for UUID shard path generators.
 *
 * Concrete generators only declare their shard depth via {@see depth()};
 * the actual path composition is delegated to {@see ShardPath} so the
 * layout stays uniform across all variants.
 */
abstract class AbstractUuidPathGenerator implements PathGenerator
{
    /**
     * Number of two-character shard levels prefixed to the UUID directory.
     *
     * @return int<1, 16>
     */
    abstract public function depth(): int;

    public function getPath(Media $media): string
    {
        $uuid = $media->uuid ?? throw new RuntimeException('Media UUID is not set.');

        return $this->prefix() . ShardPath::pathFor($uuid, $this->depth());
    }

    private function prefix(): string
    {
        $prefix = (string) config('media-library.prefix', '');

        return $prefix !== '' ? rtrim($prefix, '/') . '/' : '';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . 'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media) . 'responsive-images/';
    }
}
