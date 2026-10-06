<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\Content\MarkdownRenderer;
use Illuminate\Contracts\View\View;

class HelpController extends Controller
{
    public function __invoke(MarkdownRenderer $markdown): View
    {
        $faqs = Faq::query()->where('locale', app()->getLocale())->where('is_published', true)->orderBy('category')->orderBy('sort_order')->get()
            ->map(fn (Faq $faq) => ['category' => $faq->category, 'question' => $faq->question, 'html' => $markdown->toHtml($faq->answer), 'text' => strip_tags($markdown->toHtml($faq->answer))]);

        return view('pages.help', ['faqs' => $faqs->groupBy('category')]);
    }
}
