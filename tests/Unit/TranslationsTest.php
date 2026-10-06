<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every customer-facing string must exist in French (section 11.4, definition of done 13.3).
 * The Filament back-office is staff-only and English.
 */
class TranslationsTest extends TestCase
{
    #[Test]
    public function every_customer_facing_string_has_a_french_translation(): void
    {
        $root = dirname(__DIR__, 2);
        $french = json_decode((string) file_get_contents($root.'/lang/fr.json'), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];

        foreach (['app', 'resources/views', 'bootstrap'] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory));
            foreach ($files as $file) {
                $path = $file->getPathname();
                if (! str_ends_with($path, '.php') || str_contains($path, '/Filament/') || str_contains($path, '/views/filament/') || str_contains($path, '/cache/')) {
                    continue;
                }

                preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents($path), $matches);
                foreach ($matches[1] as $key) {
                    $key = str_replace("\\'", "'", $key);
                    if (preg_match('/^[a-z_]+\.[a-z_]*$/', $key) === 1) {
                        continue;
                    }
                    if (! array_key_exists($key, $french)) {
                        $missing[$key] = str_replace($root.'/', '', $path);
                    }
                }
            }
        }

        $this->assertSame([], $missing, 'Missing French translations: '.json_encode($missing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    #[Test]
    public function french_translations_keep_every_placeholder(): void
    {
        $french = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/lang/fr.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($french as $english => $translation) {
            preg_match_all('/:[a-z_]+/', $english, $expected);
            preg_match_all('/:[a-z_]+/', $translation, $actual);
            sort($expected[0]);
            sort($actual[0]);
            $this->assertSame($expected[0], $actual[0], "Placeholder mismatch in: $english");
        }
    }
}
