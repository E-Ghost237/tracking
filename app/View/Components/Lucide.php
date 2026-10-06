<?php

namespace App\View\Components;

use Illuminate\View\Component;

/**
 * Inline Lucide icon (ISC licence) from resources/icons. Icon names are fixed by templates,
 * never taken from user input.
 */
class Lucide extends Component
{
    /**
     * @var array<string, string>
     */
    private static array $cache = [];

    public function __construct(public string $name, public string $class = 'size-5') {}

    public function render(): string
    {
        $inner = self::$cache[$this->name] ??= $this->load();

        // Returned as an inline Blade template so extra attributes (x-show, :class...) pass through.
        // $inner comes from the trusted icon files only.
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="{{ $class }}" {{ $attributes }}>'.$inner.'</svg>';
    }

    private function load(): string
    {
        if (! preg_match('/^[a-z0-9-]+$/', $this->name)) {
            return '';
        }

        $path = resource_path('icons/'.$this->name.'.svg');
        if (! is_file($path)) {
            return '';
        }

        $svg = (string) file_get_contents($path);
        if (preg_match('/<svg[^>]*>(.*)<\/svg>/s', $svg, $m) !== 1) {
            return '';
        }

        return trim($m[1]);
    }
}
