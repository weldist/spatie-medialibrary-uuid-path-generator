<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\TestCase;

class MigratePathsToUuidCommandTest extends TestCase
{
    use CreatesMedia;

    private string $disk = 'public';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake($this->disk);
    }

    private function uuidPath(string $uuid, string $filename): string
    {
        return substr($uuid, 0, 2).'/'.substr($uuid, 2, 2).'/'.$uuid.'/'.$filename;
    }

    public function test_moves_main_file_from_id_path_to_uuid_path(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid, 'file_name' => 'photo.jpg']);

        Storage::disk($this->disk)->put($media->id.'/photo.jpg', 'content');

        $this->artisan('media-library:migrate-paths-to-uuid')->assertSuccessful();

        Storage::disk($this->disk)->assertExists($this->uuidPath($uuid, 'photo.jpg'));
        Storage::disk($this->disk)->assertMissing($media->id.'/photo.jpg');
    }

    public function test_moves_conversion_files(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid, 'file_name' => 'photo.jpg']);

        Storage::disk($this->disk)->put($media->id.'/photo.jpg', 'content');
        Storage::disk($this->disk)->put($media->id.'/conversions/photo-thumb.jpg', 'thumb');

        $this->artisan('media-library:migrate-paths-to-uuid')->assertSuccessful();

        Storage::disk($this->disk)->assertExists($this->uuidPath($uuid, 'conversions/photo-thumb.jpg'));
        Storage::disk($this->disk)->assertMissing($media->id.'/conversions/photo-thumb.jpg');
    }

    public function test_moves_responsive_image_files(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid, 'file_name' => 'photo.jpg']);

        Storage::disk($this->disk)->put($media->id.'/photo.jpg', 'content');
        Storage::disk($this->disk)->put($media->id.'/responsive-images/photo___media_library_original_340_280.jpg', 'resp');

        $this->artisan('media-library:migrate-paths-to-uuid')->assertSuccessful();

        Storage::disk($this->disk)->assertExists($this->uuidPath($uuid, 'responsive-images/photo___media_library_original_340_280.jpg'));
        Storage::disk($this->disk)->assertMissing($media->id.'/responsive-images/photo___media_library_original_340_280.jpg');
    }

    public function test_deletes_old_id_directory_after_migration(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid, 'file_name' => 'photo.jpg']);

        Storage::disk($this->disk)->put($media->id.'/photo.jpg', 'content');

        $this->artisan('media-library:migrate-paths-to-uuid')->assertSuccessful();

        $this->assertFalse(Storage::disk($this->disk)->directoryExists((string) $media->id));
    }

    public function test_dry_run_does_not_move_files(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid, 'file_name' => 'photo.jpg']);

        Storage::disk($this->disk)->put($media->id.'/photo.jpg', 'content');

        $this->artisan('media-library:migrate-paths-to-uuid', ['--dry-run' => true])
            ->expectsOutput("Would move: {$media->id}/ → 55/0e/{$uuid}/")
            ->assertSuccessful();

        Storage::disk($this->disk)->assertExists($media->id.'/photo.jpg');
        Storage::disk($this->disk)->assertMissing($this->uuidPath($uuid, 'photo.jpg'));
    }

    public function test_skips_media_without_uuid(): void
    {
        $media = $this->persistMedia(['uuid' => null, 'file_name' => 'photo.jpg']);

        Storage::disk($this->disk)->put($media->id.'/photo.jpg', 'content');

        $this->artisan('media-library:migrate-paths-to-uuid')
            ->expectsOutput("Skipping Media[id={$media->id}]: no UUID.")
            ->assertSuccessful();

        Storage::disk($this->disk)->assertExists($media->id.'/photo.jpg');
    }

    public function test_skips_already_migrated_media(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->persistMedia(['uuid' => $uuid, 'file_name' => 'photo.jpg']);

        // Only new path exists, old path is gone
        Storage::disk($this->disk)->put($this->uuidPath($uuid, 'photo.jpg'), 'content');

        $this->artisan('media-library:migrate-paths-to-uuid')
            ->expectsOutput('Done! Migrated: 0, Skipped: 1.')
            ->assertSuccessful();
    }

    public function test_moves_files_with_media_library_prefix(): void
    {
        config()->set('media-library.prefix', 'media');

        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid, 'file_name' => 'photo.jpg']);

        Storage::disk($this->disk)->put("media/{$media->id}/photo.jpg", 'content');
        Storage::disk($this->disk)->put("media/{$media->id}/conversions/photo-thumb.jpg", 'thumb');
        Storage::disk($this->disk)->put("media/{$media->id}/responsive-images/photo___r.jpg", 'resp');

        $this->artisan('media-library:migrate-paths-to-uuid')->assertSuccessful();

        Storage::disk($this->disk)->assertExists('media/'.$this->uuidPath($uuid, 'photo.jpg'));
        Storage::disk($this->disk)->assertExists('media/'.$this->uuidPath($uuid, 'conversions/photo-thumb.jpg'));
        Storage::disk($this->disk)->assertExists('media/'.$this->uuidPath($uuid, 'responsive-images/photo___r.jpg'));
        Storage::disk($this->disk)->assertMissing("media/{$media->id}/photo.jpg");
        $this->assertFalse(Storage::disk($this->disk)->directoryExists("media/{$media->id}"));
    }

    public function test_dry_run_output_includes_prefix(): void
    {
        config()->set('media-library.prefix', 'media');

        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid, 'file_name' => 'photo.jpg']);

        Storage::disk($this->disk)->put("media/{$media->id}/photo.jpg", 'content');

        $this->artisan('media-library:migrate-paths-to-uuid', ['--dry-run' => true])
            ->expectsOutput("Would move: media/{$media->id}/ → media/55/0e/{$uuid}/")
            ->assertSuccessful();

        Storage::disk($this->disk)->assertExists("media/{$media->id}/photo.jpg");
        Storage::disk($this->disk)->assertMissing('media/'.$this->uuidPath($uuid, 'photo.jpg'));
    }

    public function test_trailing_slash_in_prefix_is_normalized(): void
    {
        config()->set('media-library.prefix', 'media/');

        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid, 'file_name' => 'photo.jpg']);

        Storage::disk($this->disk)->put("media/{$media->id}/photo.jpg", 'content');

        $this->artisan('media-library:migrate-paths-to-uuid')->assertSuccessful();

        Storage::disk($this->disk)->assertExists('media/'.$this->uuidPath($uuid, 'photo.jpg'));
        Storage::disk($this->disk)->assertMissing("media/{$media->id}/photo.jpg");
    }
}
