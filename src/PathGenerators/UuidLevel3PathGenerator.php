<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators;

/**
 * Three-level UUID shard layout: `xx/xx/xx/<uuid>/`.
 *
 * Distributes media across up to ~16.7 million leaf directories. Suited
 * to large catalogs where Level2 leaves still risk thousands of UUID
 * directories per shard.
 */
final class UuidLevel3PathGenerator extends AbstractUuidPathGenerator
{
    public function depth(): int
    {
        return 3;
    }
}
