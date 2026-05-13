<?php

namespace Tests\Unit\Services;

use App\Services\AboutNexusService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Pins down the pure (no-DB) parts of `AboutNexusService`:
 *
 *   - `versionInfo()` — reads the global `PROJECTNAME` / `NEXUSPHPURL`
 *     / `VERSION_NUMBER` / `RELEASE_DATE` constants and the site name
 *     from `Setting::getSiteName()`.
 *   - `resolveLanguageFolder()` — defends the `lang/<folder>/` path
 *     join against untrusted cookie input.
 *   - `loadTranslations()` — loads the per-locale
 *     `lang_aboutnexus.php` file in an isolated scope and overlays
 *     it on top of the English defaults.
 *
 * The DB-touching methods (`languages()`, `stylesheets()`) are pinned
 * separately in `Tests\Feature\Legacy\AboutNexusControllerTest`.
 */
class AboutNexusServiceTest extends TestCase
{
    private string $tempLangRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempLangRoot = storage_path(
            'app/aboutnexus-service-fixture-'.bin2hex(random_bytes(4))
        );
        File::makeDirectory($this->tempLangRoot, 0o755, recursive: true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempLangRoot)) {
            File::deleteDirectory($this->tempLangRoot);
        }
        parent::tearDown();
    }

    public function test_resolve_language_folder_falls_back_to_english_when_cookie_is_empty(): void
    {
        $this->writeLangFile('en', ['text_version' => 'Version']);
        $service = new AboutNexusService($this->tempLangRoot);

        $this->assertSame('en', $service->resolveLanguageFolder(null));
        $this->assertSame('en', $service->resolveLanguageFolder(''));
        $this->assertSame('en', $service->resolveLanguageFolder('   '));
    }

    public function test_resolve_language_folder_rejects_path_traversal_attempt(): void
    {
        $this->writeLangFile('en', ['text_version' => 'Version']);
        $service = new AboutNexusService($this->tempLangRoot);

        // `..`, `/`, and other punctuation must be dropped on the
        // floor — the cookie value is attacker-controlled and the
        // service uses it as a path component.
        $this->assertSame('en', $service->resolveLanguageFolder('../etc'));
        $this->assertSame('en', $service->resolveLanguageFolder('en/../etc'));
        $this->assertSame('en', $service->resolveLanguageFolder('en;rm -rf'));
        $this->assertSame('en', $service->resolveLanguageFolder('en.php'));
    }

    public function test_resolve_language_folder_returns_english_when_file_missing(): void
    {
        $this->writeLangFile('en', ['text_version' => 'Version']);
        $service = new AboutNexusService($this->tempLangRoot);

        // Syntactically valid folder name, but no
        // `lang_aboutnexus.php` exists under it — fall back rather
        // than letting `loadTranslations()` `require` a missing file.
        $this->assertSame('en', $service->resolveLanguageFolder('totally-not-a-locale'));
    }

    public function test_resolve_language_folder_returns_valid_folder_when_file_exists(): void
    {
        $this->writeLangFile('en', ['text_version' => 'Version']);
        $this->writeLangFile('ru', ['text_version' => 'Версия']);
        $service = new AboutNexusService($this->tempLangRoot);

        $this->assertSame('ru', $service->resolveLanguageFolder('ru'));
    }

    public function test_load_translations_returns_english_defaults_when_no_folder_given(): void
    {
        $this->writeLangFile('en', [
            'text_version' => 'Version',
            'text_language' => 'Language',
        ]);
        $service = new AboutNexusService($this->tempLangRoot);

        $labels = $service->loadTranslations(null);

        $this->assertSame('Version', $labels['text_version']);
        $this->assertSame('Language', $labels['text_language']);
    }

    public function test_load_translations_overlays_locale_on_top_of_english(): void
    {
        // English has full coverage; Russian only has one key — the
        // service must overlay it on the English defaults so the
        // missing keys still resolve.
        $this->writeLangFile('en', [
            'text_version' => 'Version',
            'text_language' => 'Language',
            'text_state' => 'State',
        ]);
        $this->writeLangFile('ru', [
            'text_version' => 'Версия',
        ]);
        $service = new AboutNexusService($this->tempLangRoot);

        $labels = $service->loadTranslations('ru');

        $this->assertSame('Версия', $labels['text_version']);
        $this->assertSame('Language', $labels['text_language']);
        $this->assertSame('State', $labels['text_state']);
    }

    public function test_load_translations_returns_empty_array_when_english_file_missing(): void
    {
        $service = new AboutNexusService($this->tempLangRoot);

        $this->assertSame([], $service->loadTranslations('en'));
        $this->assertSame([], $service->loadTranslations(null));
    }

    public function test_load_translations_drops_non_string_scalar_keys_and_values(): void
    {
        // Hand-write the fixture so we can include malformed entries.
        File::makeDirectory($this->tempLangRoot.'/en', 0o755);
        File::put(
            $this->tempLangRoot.'/en/lang_aboutnexus.php',
            "<?php\n\$lang_aboutnexus = [\n"
            ."    'text_version' => 'Version',\n"
            ."    'text_count' => 42,\n"            // scalar → cast to '42'
            ."    'text_nested' => ['oops' => 1],\n" // array → dropped
            ."    7 => 'numeric-key',\n"            // numeric key → dropped
            ."];\n",
        );
        $service = new AboutNexusService($this->tempLangRoot);

        $labels = $service->loadTranslations('en');

        $this->assertSame('Version', $labels['text_version']);
        $this->assertSame('42', $labels['text_count']);
        $this->assertArrayNotHasKey('text_nested', $labels);
        $this->assertArrayNotHasKey(7, $labels);
    }

    /**
     * @param  array<string,string>  $entries
     */
    private function writeLangFile(string $folder, array $entries): void
    {
        $dir = $this->tempLangRoot.DIRECTORY_SEPARATOR.$folder;
        File::makeDirectory($dir, 0o755, recursive: true, force: true);
        $body = "<?php\n\$lang_aboutnexus = array(\n";
        foreach ($entries as $key => $value) {
            $body .= sprintf("    %s => %s,\n", var_export($key, true), var_export($value, true));
        }
        $body .= ");\n";
        File::put($dir.DIRECTORY_SEPARATOR.'lang_aboutnexus.php', $body);
    }
}
