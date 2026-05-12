<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\TestCase;

class PruneUuidPathsCommandTest extends TestCase
{
    use CreatesMedia;

    private string $disk = 'public';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake($this->disk);
    }

    private function createUuidDirectory(string $uuid, ?string $file = null): void
    {
        $path = substr($uuid, 0, 2).'/'.substr($uuid, 2, 2).'/'.$uuid;

        if ($file !== null) {
            Storage::disk($this->disk)->put("{$path}/{$file}", 'content');
        } else {
            Storage::disk($this->disk)->makeDirectory($path);
        }
    }

    public function test_deletes_orphaned_uuid_directories(): void
    {
        $orphanedUuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->createUuidDirectory($orphanedUuid, 'photo.jpg');

        $this->artisan('media-library:prune-uuid-paths')
            ->expectsOutput("Orphaned UUID directory `55/0e/{$orphanedUuid}` has been removed")
            ->expectsOutput('All done!')
            ->assertSuccessful();

        $this->assertFalse(Storage::disk($this->disk)->directoryExists("55/0e/{$orphanedUuid}"));
    }

    public function test_does_not_delete_directories_with_existing_media(): void
    {
        $existingUuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->createUuidDirectory($existingUuid, 'photo.jpg');
        $this->persistMedia(['uuid' => $existingUuid]);

        $this->artisan('media-library:prune-uuid-paths')
            ->expectsOutput('No orphaned UUID directories found.')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk($this->disk)->directoryExists("55/0e/{$existingUuid}"));
    }

    public function test_dry_run_does_not_delete_directories(): void
    {
        $orphanedUuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->createUuidDirectory($orphanedUuid, 'photo.jpg');

        $this->artisan('media-library:prune-uuid-paths', ['--dry-run' => true])
            ->expectsOutput("Orphaned UUID directory `55/0e/{$orphanedUuid}` found")
            ->assertSuccessful();

        $this->assertTrue(Storage::disk($this->disk)->directoryExists("55/0e/{$orphanedUuid}"));
    }

    public function test_cleans_empty_shard_directories_after_deletion(): void
    {
        $orphanedUuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->createUuidDirectory($orphanedUuid, 'photo.jpg');

        $this->artisan('media-library:prune-uuid-paths')->assertSuccessful();

        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55/0e'));
        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55'));
    }

    public function test_reports_no_orphaned_directories_when_disk_is_empty(): void
    {
        $this->artisan('media-library:prune-uuid-paths')
            ->expectsOutput('No orphaned UUID directories found.')
            ->assertSuccessful();
    }
}
