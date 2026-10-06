<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Content\MarkdownRenderer;
use App\Support\Permissions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Staff preview of unpublished CMS pages (FR-128).
 */
class PagePreviewController extends Controller
{
    public function __invoke(Request $request, Page $page, MarkdownRenderer $markdown): View
    {
        abort_unless($request->user()?->hasPermission(Permissions::CONTENT_MANAGE), 404);
        app()->setLocale($page->locale);

        return view('pages.cms', ['page' => $page, 'html' => $markdown->toHtml($page->body), 'slug' => $page->slug, 'preview' => true]);
    }
}
