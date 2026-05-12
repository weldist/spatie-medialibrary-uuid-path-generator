<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\Feature;

use Weldist\Spatie\MediaLibrary\UuidPathGenerator\PathGenerators\UuidLevel2PathGenerator;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\UuidPathGenerator\Tests\TestCase;

/**
 * Sanity-check that the abstract generator's depth dispatch works for a
 * non-default depth as well. Covers the generic Level1..N contract without
 * exhaustively duplicating every Level4 assertion.
 */
class UuidLevel2PathGeneratorTest extends TestCase
{
    use CreatesMedia;

    private UuidLevel2PathGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new UuidLevel2PathGenerator();
    }

    public function test_getPath_returns_two_level_nested_uuid_path(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid]);

        $this->assertSame("55/0e/{$uuid}/", $this->generator->getPath($media));
    }

    public function test_conversions_and_responsive_images_inherit_depth(): void
    {
        $uuid = 'abcdef12-0000-0000-0000-000000000000';
        $media = $this->persistMedia(['uuid' => $uuid]);

        $this->assertSame("ab/cd/{$uuid}/conversions/", $this->generator->getPathForConversions($media));
        $this->assertSame("ab/cd/{$uuid}/responsive-images/", $this->generator->getPathForResponsiveImages($media));
    }

    public function test_depth_reports_two(): void
    {
        $this->assertSame(2, $this->generator->depth());
    }

    public function test_getPath_prepends_media_library_prefix(): void
    {
        config()->set('media-library.prefix', 'media');

        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid]);

        $this->assertSame("media/55/0e/{$uuid}/", $this->generator->getPath($media));
        $this->assertSame("media/55/0e/{$uuid}/conversions/", $this->generator->getPathForConversions($media));
        $this->assertSame("media/55/0e/{$uuid}/responsive-images/", $this->generator->getPathForResponsiveImages($media));
    }

    public function test_getPath_normalizes_trailing_slash_in_prefix(): void
    {
        config()->set('media-library.prefix', 'media/');

        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->persistMedia(['uuid' => $uuid]);

        $this->assertSame("media/55/0e/{$uuid}/", $this->generator->getPath($media));
    }
}
