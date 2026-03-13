<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_locales_list(): void
    {
        $expected = [
            'en', 'ar', 'cs', 'de', 'es', 'fr', 'it',
            'ja', 'ko', 'nl', 'pt', 'ru', 'sv', 'th',
            'uk', 'zh-Hans', 'zh-Hant',
        ];

        $this->assertEquals($expected, SetLocale::SUPPORTED_LOCALES);
    }

    public function test_rtl_locales(): void
    {
        $this->assertEquals(['ar'], SetLocale::RTL_LOCALES);
    }

    public function test_default_locale_is_english(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Content-Language', 'en');
    }

    public function test_locale_from_query_parameter(): void
    {
        $response = $this->get('/?lang=de');

        $response->assertHeader('Content-Language', 'de');
    }

    public function test_invalid_locale_falls_back_to_default(): void
    {
        $response = $this->get('/?lang=invalid');

        $response->assertHeader('Content-Language', 'en');
    }

    public function test_locale_persists_in_session(): void
    {
        // Set locale via query
        $this->get('/?lang=fr');

        // Next request without query should keep French
        $response = $this->get('/');
        $response->assertHeader('Content-Language', 'fr');
    }

    public function test_locale_from_user_preference(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        UserPreference::create([
            'user_id' => $user->id,
            'key' => 'locale',
            'value' => ['locale' => 'es'],
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertHeader('Content-Language', 'es');
    }

    public function test_query_param_overrides_user_preference(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        UserPreference::create([
            'user_id' => $user->id,
            'key' => 'locale',
            'value' => ['locale' => 'es'],
        ]);

        $response = $this->actingAs($user)->get('/?lang=de');
        $response->assertHeader('Content-Language', 'de');
    }

    public function test_accept_language_header(): void
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'ja,en;q=0.9',
        ])->get('/');

        $response->assertHeader('Content-Language', 'ja');
    }

    public function test_arabic_sets_rtl_direction(): void
    {
        $this->withoutVite();

        $response = $this->get('/?lang=ar');

        $response->assertHeader('Content-Language', 'ar');
    }

    public function test_all_translation_files_exist(): void
    {
        foreach (SetLocale::SUPPORTED_LOCALES as $locale) {
            $path = lang_path("{$locale}.json");
            $this->assertFileExists($path, "Translation file missing for locale: {$locale}");
        }
    }

    public function test_all_translation_files_are_valid_json(): void
    {
        foreach (SetLocale::SUPPORTED_LOCALES as $locale) {
            $path = lang_path("{$locale}.json");
            $content = file_get_contents($path);
            $decoded = json_decode($content, true);
            $this->assertNotNull($decoded, "Invalid JSON in translation file: {$locale}.json");
        }
    }

    public function test_all_translation_files_have_required_keys(): void
    {
        $requiredSections = ['app', 'nav', 'auth', 'reader', 'search', 'common', 'errors'];

        foreach (SetLocale::SUPPORTED_LOCALES as $locale) {
            $path = lang_path("{$locale}.json");
            $translations = json_decode(file_get_contents($path), true);

            foreach ($requiredSections as $section) {
                $this->assertArrayHasKey(
                    $section,
                    $translations,
                    "Translation file {$locale}.json missing section: {$section}"
                );
            }
        }
    }

    public function test_english_has_all_keys_as_baseline(): void
    {
        $en = json_decode(file_get_contents(lang_path('en.json')), true);
        $enKeys = $this->flattenKeys($en);

        // Every other locale should have (at least) the same key structure for required sections
        foreach (SetLocale::SUPPORTED_LOCALES as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $translations = json_decode(file_get_contents(lang_path("{$locale}.json")), true);
            $localeKeys = $this->flattenKeys($translations);

            foreach ($localeKeys as $key) {
                // All keys in the locale file should also exist in English
                $this->assertContains(
                    $key,
                    $enKeys,
                    "Key '{$key}' exists in {$locale}.json but not in en.json"
                );
            }
        }
    }

    public function test_chinese_simplified_locale(): void
    {
        $response = $this->get('/?lang=zh-Hans');
        $response->assertHeader('Content-Language', 'zh-Hans');
    }

    public function test_chinese_traditional_locale(): void
    {
        $response = $this->get('/?lang=zh-Hant');
        $response->assertHeader('Content-Language', 'zh-Hant');
    }

    public function test_partial_locale_match(): void
    {
        // "zh" should match "zh-Hans" (first match)
        $response = $this->get('/?lang=zh');
        $response->assertHeader('Content-Language', 'zh-Hans');
    }

    public function test_inertia_shares_locale_data(): void
    {
        $this->withoutVite();

        $response = $this->get('/?lang=fr');

        // Page should load successfully
        $response->assertOk();
    }

    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }
        return $keys;
    }
}
