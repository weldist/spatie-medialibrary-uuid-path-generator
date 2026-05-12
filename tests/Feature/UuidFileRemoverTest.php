<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\TestCase;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\UuidFileRemover;

class UuidFileRemoverTest extends TestCase
{
    use CreatesMedia;

    private string $disk = 'public';

    private UuidFileRemover $remover;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake($this->disk);

        $this->remover = app(UuidFileRemover::class);
    }

    private function uuidPath(string $uuid): string
    {
        return substr($uuid, 0, 2).'/'.substr($uuid, 2, 2).'/'.$uuid;
    }

    public function test_removes_all_empty_shard_directories_after_media_deletion(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid, 'file_name' => 'photo.jpg']);

        Storage::disk($this->disk)->put($this->uuidPath($uuid).'/photo.jpg', 'content');

        $this->remover->removeAllFiles($media);

        $this->assertFalse(Storage::disk($this->disk)->directoryExists($this->uuidPath($uuid)));
        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55/0e'));
        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55'));
    }

    public function test_does_not_remove_shard_when_sibling_uuid_in_same_leaf_shard(): void
    {
        // Same first 4 hex chars → both UUIDs live under 55/0e/
        $uuid1 = '550e8400-aaaa-41d4-a716-446655440000';
        $uuid2 = '550e8400-bbbb-41d4-a716-446655440001';

        $media = $this->persistMedia(['uuid' => $uuid1, 'file_name' => 'photo.jpg']);

        Storage::disk($this->disk)->put($this->uuidPath($uuid1).'/photo.jpg', 'content');
        Storage::disk($this->disk)->put($this->uuidPath($uuid2).'/photo.jpg', 'content');

        $this->remover->removeAllFiles($media);

        $this->assertFalse(Storage::disk($this->disk)->directoryExists($this->uuidPath($uuid1)));
        $this->assertTrue(Storage::disk($this->disk)->directoryExists('55/0e'));
        $this->assertTrue(Storage::disk($this->disk)->exists($this->uuidPath($uuid2).'/photo.jpg'));
    }

    public function test_stops_cascade_at_non_empty_shard_level(): void
    {
        // uuid1 lives under 55/0e, uuid2 under 55/ff — they share only the top-level `55/`.
        $uuid1 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid2 = '55ffffff-e29b-41d4-a716-446655440001';

        $media = $this->persistMedia(['uuid' => $uuid1, 'file_name' => 'photo.jpg']);

        Storage::disk($this->disk)->put($this->uuidPath($uuid1).'/photo.jpg', 'content');
        Storage::disk($this->disk)->put($this->uuidPath($uuid2).'/photo.jpg', 'content');

        $this->remover->removeAllFiles($media);

        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55/0e'));
        $this->assertTrue(Storage::disk($this->disk)->directoryExists('55'));
        $this->assertTrue(Storage::disk($this->disk)->exists($this->uuidPath($uuid2).'/photo.jpg'));
    }
}
