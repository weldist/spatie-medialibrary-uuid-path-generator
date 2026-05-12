<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators;

/**
 * Two-level UUID shard layout: `xx/xx/<uuid>/`.
 *
 * Distributes media across up to 65 536 leaf directories. Reasonable
 * sweet spot for catalogs in the low millions on modern filesystems.
 */
final class UuidLevel2PathGenerator extends AbstractUuidPathGenerator
{
    public function depth(): int
    {
        return 2;
    }
}
