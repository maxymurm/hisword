<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerseImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_endpoint_returns_templates(): void
    {
        $response = $this->getJson('/verse-image/templates');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.id', 'minimal')
            ->assertJsonPath('data.1.id', 'nature')
            ->assertJsonPath('data.2.id', 'gradient')
            ->assertJsonPath('data.3.id', 'dark')
            ->assertJsonPath('data.4.id', 'watercolor');
    }

    public function test_generate_requires_verse_text(): void
    {
        $response = $this->postJson('/verse-image/generate', [
            'reference' => 'John 3:16',
            'template' => 'minimal',
            'aspect_ratio' => '1:1',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['verse_text']);
    }

    public function test_generate_requires_reference(): void
    {
        $response = $this->postJson('/verse-image/generate', [
            'verse_text' => 'For God so loved the world...',
            'template' => 'minimal',
            'aspect_ratio' => '1:1',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);
    }

    public function test_generate_validates_template(): void
    {
        $response = $this->postJson('/verse-image/generate', [
            'verse_text' => 'Test verse',
            'reference' => 'Gen 1:1',
            'template' => 'invalid',
            'aspect_ratio' => '1:1',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['template']);
    }

    public function test_generate_validates_aspect_ratio(): void
    {
        $response = $this->postJson('/verse-image/generate', [
            'verse_text' => 'Test verse',
            'reference' => 'Gen 1:1',
            'template' => 'minimal',
            'aspect_ratio' => '4:3',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['aspect_ratio']);
    }

    public function test_generate_creates_image_minimal(): void
    {
        $response = $this->postJson('/verse-image/generate', [
            'verse_text' => 'In the beginning God created the heavens and the earth.',
            'reference' => 'Genesis 1:1',
            'template' => 'minimal',
            'aspect_ratio' => '1:1',
            'font_size' => 32,
            'retina' => false,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['image', 'width', 'height', 'format'])
            ->assertJsonPath('format', 'png')
            ->assertJsonPath('width', 1080)
            ->assertJsonPath('height', 1080);

        $this->assertStringStartsWith('data:image/png;base64,', $response->json('image'));
    }

    public function test_generate_creates_image_gradient(): void
    {
        $response = $this->postJson('/verse-image/generate', [
            'verse_text' => 'For God so loved the world that He gave His only begotten Son.',
            'reference' => 'John 3:16',
            'template' => 'gradient',
            'aspect_ratio' => '9:16',
            'retina' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('width', 1080)
            ->assertJsonPath('height', 1920);
    }

    public function test_generate_creates_image_dark(): void
    {
        $response = $this->postJson('/verse-image/generate', [
            'verse_text' => 'The Lord is my shepherd; I shall not want.',
            'reference' => 'Psalm 23:1',
            'template' => 'dark',
            'aspect_ratio' => '16:9',
            'retina' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('width', 1920)
            ->assertJsonPath('height', 1080);
    }

    public function test_generate_with_watermark(): void
    {
        $response = $this->postJson('/verse-image/generate', [
            'verse_text' => 'Trust in the Lord with all your heart.',
            'reference' => 'Proverbs 3:5',
            'template' => 'nature',
            'aspect_ratio' => '1:1',
            'watermark' => 'HisWord',
            'retina' => false,
        ]);

        $response->assertOk();
    }

    public function test_generate_with_all_templates(): void
    {
        $templates = ['minimal', 'nature', 'gradient', 'dark', 'watercolor'];

        foreach ($templates as $template) {
            $response = $this->postJson('/verse-image/generate', [
                'verse_text' => 'Test verse text for image generation.',
                'reference' => 'Test 1:1',
                'template' => $template,
                'aspect_ratio' => '1:1',
                'retina' => false,
            ]);

            $response->assertOk();
            $this->assertStringStartsWith('data:image/png;base64,', $response->json('image'));
        }
    }

    public function test_generate_with_custom_font_options(): void
    {
        $response = $this->postJson('/verse-image/generate', [
            'verse_text' => 'Be strong and courageous.',
            'reference' => 'Joshua 1:9',
            'template' => 'gradient',
            'aspect_ratio' => '1:1',
            'font_size' => 48,
            'font_family' => 'sans',
            'text_color' => '#ffffff',
            'retina' => false,
        ]);

        $response->assertOk();
    }

    public function test_generate_validates_font_size_range(): void
    {
        $response = $this->postJson('/verse-image/generate', [
            'verse_text' => 'Test',
            'reference' => 'Test 1:1',
            'template' => 'minimal',
            'aspect_ratio' => '1:1',
            'font_size' => 200,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['font_size']);
    }
}
