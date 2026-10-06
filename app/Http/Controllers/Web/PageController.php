<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Content\MarkdownRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * CMS pages: customs and packing guides, about, legal pages (FR-128).
 */
class PageController extends Controller
{
    public function __invoke(Request $request, MarkdownRenderer $markdown): View
    {
        $slug = (string) $request->route()->defaults['slug'];
        $page = Page::query()->published()->where('slug', $slug)->where('locale', app()->getLocale())->first()
            ?? Page::query()->published()->where('slug', $slug)->where('locale', 'en')->firstOrFail();

        return view('pages.cms', ['page' => $page, 'html' => $markdown->toHtml($page->body), 'slug' => $slug]);
    }
}
