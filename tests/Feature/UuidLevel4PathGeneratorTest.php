<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\Feature;

use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators\UuidLevel4PathGenerator;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\TestCase;

class UuidLevel4PathGeneratorTest extends TestCase
{
    use CreatesMedia;

    private UuidLevel4PathGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new UuidLevel4PathGenerator();
    }

    public function test_getPath_returns_four_level_nested_uuid_path(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid]);

        $this->assertSame("55/0e/84/00/{$uuid}/", $this->generator->getPath($media));
    }

    public function test_getPath_throws_when_uuid_is_null(): void
    {
        $media = new Media();
        $media->uuid = null;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Media UUID is not set.');

        $this->generator->getPath($media);
    }

    public function test_getPathForConversions_appends_conversions_directory(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid]);

        $this->assertSame("55/0e/84/00/{$uuid}/conversions/", $this->generator->getPathForConversions($media));
    }

    public function test_getPathForResponsiveImages_appends_responsive_images_directory(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid]);

        $this->assertSame("55/0e/84/00/{$uuid}/responsive-images/", $this->generator->getPathForResponsiveImages($media));
    }

    public function test_getPath_segments_are_derived_from_uuid_first_eight_chars(): void
    {
        $uuid = 'abcdef12-0000-0000-0000-000000000000';
        $media = $this->persistMedia(['uuid' => $uuid]);

        $this->assertStringStartsWith('ab/cd/ef/12/', $this->generator->getPath($media));
    }
}
