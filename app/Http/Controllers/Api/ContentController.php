<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Content\MarkdownRenderer;
use Illuminate\Http\JsonResponse;

class ContentController extends Controller
{
    public function __invoke(string $slug, MarkdownRenderer $markdown): JsonResponse
    {
        $page = Page::query()->published()->where('slug', $slug)->where('locale', app()->getLocale())->first()
            ?? Page::query()->published()->where('slug', $slug)->where('locale', 'en')->firstOrFail();

        return response()->json(['data' => [
            'slug' => $page->slug,
            'locale' => $page->locale,
            'title' => $page->title,
            'summary' => $page->summary,
            'html' => $markdown->toHtml($page->body),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ]]);
    }
}
