<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators;

/**
 * Four-level UUID shard layout: `xx/xx/xx/xx/<uuid>/`.
 *
 * Distributes media across up to ~4.3 billion leaf directories. The
 * deepest variant this package ships; appropriate for very large media
 * pools or object stores where listing flat prefixes would be expensive.
 */
final class UuidLevel4PathGenerator extends AbstractUuidPathGenerator
{
    public function depth(): int
    {
        return 4;
    }
}
