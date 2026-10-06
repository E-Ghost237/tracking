<?php

namespace App\Services\Content;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\Table\TableExtension;

/**
 * Safe Markdown for CMS pages, FAQ answers and email templates: raw HTML is escaped
 * and javascript:/data: links are dropped, so stored content cannot carry XSS.
 */
class MarkdownRenderer
{
    private ?CommonMarkConverter $converter = null;

    public function toHtml(string $markdown): string
    {
        return (string) $this->converter()->convert($markdown);
    }

    private function converter(): CommonMarkConverter
    {
        if ($this->converter === null) {
            $this->converter = new CommonMarkConverter([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 20,
            ]);
            $this->converter->getEnvironment()->addExtension(new TableExtension);
        }

        return $this->converter;
    }
}
