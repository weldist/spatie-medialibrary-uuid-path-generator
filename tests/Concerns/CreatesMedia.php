<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\Concerns;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait CreatesMedia
{
    /**
     * Persist a Media row with sensible defaults. Pass `['uuid' => null]` to bypass
     * the HasUuid auto-fill (the row is updated after creation since Spatie's
     * `creating` listener replaces null/empty uuids).
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function persistMedia(array $overrides = []): Media
    {
        $forceNullUuid = array_key_exists('uuid', $overrides) && $overrides['uuid'] === null;

        $defaults = [
            'model_type' => 'TestModel',
            'model_id' => 1,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'default',
            'name' => 'photo',
            'file_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 0,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ];

        if ($forceNullUuid) {
            unset($overrides['uuid']);
        }

        /** @var Media $media */
        $media = Media::query()->create(array_merge($defaults, $overrides));

        if ($forceNullUuid) {
            Media::query()->where('id', $media->id)->update(['uuid' => null]);
            $media->refresh();
        }

        return $media;
    }
}
