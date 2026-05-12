# weldist/spatie-medialibrary-uuid-path-generator

[![Tests](https://github.com/weldist/spatie-medialibrary-uuid-path-generator/actions/workflows/tests.yml/badge.svg)](https://github.com/weldist/spatie-medialibrary-uuid-path-generator/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12%20|%2013-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)

A UUID-based path generator for [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary).

> A [weld.ist](https://weld.ist) project.
>
> Unofficial plugin. Not affiliated with Spatie.

## The Problem

spatie/laravel-medialibrary's default `DefaultPathGenerator` stores media files in a flat structure based on the primary key (ID):

```
1/photo.jpg
2/photo.jpg
3/photo.jpg
...
```

This works fine for small applications, but causes serious issues as the number of media files grows:

- **File system performance degradation:** File systems like ext4 and NTFS slow down directory listings when thousands of subdirectories exist under a single parent.
- **Predictable URLs:** The sequential ID-based structure makes media file URLs trivially easy to enumerate.
- **Operational overhead:** Bulk-moving, backing up, or migrating files to a CDN becomes harder with a flat layout.

## The Solution

This package distributes files by turning the leading characters of each media UUID into a sharded directory hierarchy. You pick the shard depth that fits your catalog size:

```
# Level 2 (recommended default — see "Picking a shard depth" below)
55/0e/550e8400-e29b-41d4-a716-446655440000/photo.jpg
```

Conversions and responsive images are placed in dedicated subdirectories under the UUID folder:

```
55/0e/550e8400-e29b-41d4-a716-446655440000/conversions/
55/0e/550e8400-e29b-41d4-a716-446655440000/responsive-images/
```

Benefits:

- **Performance:** Each shard level holds at most 256 subdirectories, spreading the file system load evenly.
- **Security:** The UUID-based random structure makes file paths unpredictable and resistant to enumeration.
- **Uniqueness:** Every media file gets its own UUID directory, eliminating any risk of path collisions.

### Picking a shard depth

The package ships four path generators. They share the same layout — `xx/.../xx/<uuid>/` — and differ only in how many two-character shard levels they prepend. Pick the smallest depth that still keeps leaf directories under control for your catalog size:

| Generator | Layout | Max leaf directories | Suited to | |
|---|---|---|---|---|
| `UuidLevel1PathGenerator` | `xx/<uuid>/` | 256 | Small catalogs (≲ 250 k files) | |
| `UuidLevel2PathGenerator` | `xx/xx/<uuid>/` | 65 536 | Medium catalogs (low millions) | **recommended default** |
| `UuidLevel3PathGenerator` | `xx/xx/xx/<uuid>/` | ~16.7 M | Large catalogs / busy object stores | |
| `UuidLevel4PathGenerator` | `xx/xx/xx/xx/<uuid>/` | ~4.3 B | Very large pools or remote disks where flat LIST is expensive | |

When in doubt, start with **Level2** — it handles up to ~10 million files comfortably and keeps cascade cleanup, LIST traversal, and path readability all in a sensible range. Migrating to a deeper layout later is cheaper than overshooting now and dragging around millions of mostly-empty intermediate directories.

## Requirements

- PHP ^8.3
- spatie/laravel-medialibrary ^11.0

## Installation

```bash
composer require weldist/spatie-medialibrary-uuid-path-generator
```

## Setup

Publish the spatie/laravel-medialibrary config (if you haven't already) and set the `path_generator` option:

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="config"
```

In `config/media-library.php`, pick the depth you want and wire up the matching path generator together with the cascade-aware file remover:

```php
'path_generator' => Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators\UuidLevel2PathGenerator::class,

'file_remover_class' => Weldist\Spatie\MediaLibrary\UuidPathGenerator\UuidFileRemover::class,
```

Swap `UuidLevel2PathGenerator` for `UuidLevel1PathGenerator`, `UuidLevel3PathGenerator`, or `UuidLevel4PathGenerator` if you need a different shard depth. The file remover and the artisan commands shipped with this package introspect this config and automatically follow the depth you chose — there is no separate setting to keep in sync.

> **Why `UuidFileRemover`?**
> The default file remover deletes the UUID directory but leaves the empty shard parent directories (`55/0e/84/00/`) behind. `UuidFileRemover` cascades upward and removes each shard level when it becomes empty.

## Migrating from DefaultPathGenerator

If your project already has media files stored with spatie's default ID-based structure (`1/photo.jpg`, `2/photo.jpg`), you can migrate them to the UUID path structure.

**1.** Switch `media-library.path_generator` to one of this package's UUID generators (pick the depth that suits your catalog — see [Picking a shard depth](#picking-a-shard-depth)):

```php
'path_generator' => Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators\UuidLevel2PathGenerator::class,
```

The migration command reads this config to decide where files land and refuses to run unless it points to a UUID generator. Switching `file_remover_class` to `UuidFileRemover` is optional at this stage — it only affects future deletions (cascade-cleanup of empty shard parents) and can be flipped any time.

**2.** Run the migration:

```bash
php artisan media-library:migrate-paths-to-uuid
```

It moves all files (including conversions and responsive images), deletes the old ID directories, and is safe to re-run — it skips media whose old directory no longer exists.

> **Heads up — read window during migration.** As soon as Step 1 lands, the application starts resolving media URLs through the UUID generator while the actual files still sit at their ID-based paths. Any read between Step 1 and Step 2 will 404. Schedule the two steps back-to-back during low traffic, or use `--dry-run` first to estimate the move duration.

Options:

| Option | Description |
|---|---|
| `disk` | Disk to migrate (defaults to `media-library.disk_name` config) |
| `--dry-run` | Preview what would be moved without touching any files |
| `--force` | Skip the production confirmation prompt |

## Reverting to DefaultPathGenerator

If you need to undo the migration and put files back under spatie's ID-based layout, run the inverse command **before** switching the config away from the UUID generator:

**1.** With `media-library.path_generator` still pointing at a UUID generator, run:

```bash
php artisan media-library:migrate-paths-from-uuid
```

It moves each media's main file, conversions, and responsive images from the UUID shard path back to the ID-based path, then cascade-cleans empty shard parents.

**2.** Switch `media-library.path_generator` back to `Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator` (and `file_remover_class` back to `Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover` if you swapped that earlier).

> **Heads up — read window during reverse migration.** Symmetric to the forward migration: while files are being moved, URLs still resolved through the UUID generator (because the config hasn't flipped yet) keep working, but as soon as you flip the config in Step 2 any URL not yet served from the new ID path returns the new layout. Schedule the two steps back-to-back, ideally during low traffic.

Options:

| Option | Description |
|---|---|
| `disk` | Disk to migrate (defaults to `media-library.disk_name` config) |
| `--dry-run` | Preview what would be moved without touching any files |
| `--force` | Skip the production confirmation prompt |

## Cleaning Orphaned Directories

spatie's built-in `media-library:clean` command identifies orphaned directories using an `is_numeric()` check, which only works for the default ID-based path structure. This package ships a UUID-aware replacement:

```bash
php artisan media-library:prune-uuid-paths
```

Options:

| Option | Description |
|---|---|
| `disk` | Disk to clean (defaults to `media-library.disk_name` config) |
| `--shard=` | Limit the scan to the given first-level shards (e.g. `--shard=55 --shard=ab`). Repeatable. Useful for splitting a large sweep into chunks or rerunning a partial scan on a remote disk. Defaults to every shard (`00`..`ff`). |
| `--dry-run` | List orphaned directories without deleting them |
| `--force` | Skip the production confirmation prompt |

## Testing

```bash
# Build first (once per PHP version)
DOCKER_BUILDKIT=0 docker compose --profile php83 build

# Run tests
docker compose --profile php83 up
docker compose --profile php84 up
docker compose --profile php85 up
```

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/license/MIT).
