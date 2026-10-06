<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Carrier;
use App\Models\Faq;
use App\Models\Location;
use App\Models\Media;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\TransportMode;
use App\Services\Content\MarkdownRenderer;
use App\Services\Settings;
use App\Services\Pricing\TransitWindows;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(MarkdownRenderer $markdown, Settings $settings, TransitWindows $transit): View
    {
        $locale = app()->getLocale();

        return view('pages.home', [
            'defaultScene' => in_array($settings->get('hero_scene_default'), ['air', 'sea', 'road'], true) ? $settings->get('hero_scene_default') : 'air',
            'hubs' => Location::query()->where('is_active', true)->where('type', 'hub')->orderBy('sort_order')->get(),
            'carrierFormats' => Carrier::formatsForClient(),
            'hero' => Media::query()->where('key', 'like', 'hero_%')->pluck('path', 'key'),
            'alerts' => Alert::query()->active()->where('locale', $locale)->latest()->limit(2)->get(),
            'modes' => TransportMode::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'paymentMethods' => PaymentMethod::query()->enabled()->get(['name', 'kind']),
            'faqs' => Faq::query()->where('locale', $locale)->where('is_published', true)->orderBy('sort_order')->limit(5)->get()
                ->each(fn (Faq $faq) => $faq->setAttribute('html', $markdown->toHtml($faq->answer))),
            // Example numbers are real, released demo shipments, never invented (R6).
            'transit' => $transit->labels(),
            // Commitment figures come from settings, not from the template.
            'reviewTargetMinutes' => $settings->int('review_target_minutes'),
            'staffedHours' => $settings->get('staffed_hours'),
            'examples' => Shipment::query()->whereNotNull('released_at')->whereNotNull('tracking_number')->oldest('id')->limit(2)->pluck('tracking_number'),
        ]);
    }
}
