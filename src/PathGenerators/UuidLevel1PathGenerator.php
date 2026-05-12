<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators;

/**
 * One-level UUID shard layout: `xx/<uuid>/`.
 *
 * Spreads media across at most 256 top-level directories. Good for small
 * to medium catalogs (up to ~hundreds of thousands of files) where deeper
 * sharding would create more empty intermediate directories than it saves.
 */
final class UuidLevel1PathGenerator extends AbstractUuidPathGenerator
{
    public function depth(): int
    {
        return 1;
    }
}
