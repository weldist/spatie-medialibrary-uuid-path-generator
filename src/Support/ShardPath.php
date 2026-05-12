<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\Support;

/**
 * Single source of truth for the UUID shard layout.
 *
 * A depth of N takes the first 2N characters of the UUID and turns each
 * pair into one directory level (e.g. depth=4 → `55/0e/84/00/<uuid>/`).
 * Every component that needs to compute or unwind a shard path (path
 * generators, file remover, orphan cleanup) routes through this helper
 * so the structure stays consistent across the codebase.
 */
final class ShardPath
{
    /**
     * Return `xx/.../xx/<uuid>/` with the given shard depth.
     *
     * @param  int<1, 16>  $depth
     */
    public static function pathFor(string $uuid, int $depth): string
    {
        $segments = [];

        for ($i = 0; $i < $depth; $i++) {
            $segments[] = substr($uuid, $i * 2, 2);
        }

        $segments[] = $uuid;

        return implode('/', $segments) . '/';
    }

    /**
     * Return the shard parent directories for a UUID, ordered deepest first.
     *
     * Used by cascade-cleanup routines that delete empty shard levels after
     * a UUID directory disappears. The list excludes the UUID directory
     * itself and starts at the deepest shard so callers can stop as soon
     * as they hit a non-empty level.
     *
     * @param  int<1, 16>  $depth
     * @return list<string>
     */
    public static function parentsFor(string $uuid, int $depth, string $prefix = ''): array
    {
        $parents = [];

        for ($level = $depth; $level >= 1; $level--) {
            $parts = [];
            for ($i = 0; $i < $level; $i++) {
                $parts[] = substr($uuid, $i * 2, 2);
            }
            $parents[] = $prefix . implode('/', $parts);
        }

        return $parents;
    }
}
