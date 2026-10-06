<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * XML sitemap with hreflang alternates, and robots.txt (section 11.4). Tracking pages are not listed.
 */
class SeoController extends Controller
{
    private const ROUTES = [
        'home', 'quote', 'services', 'network', 'rates', 'locations', 'help', 'status', 'contact',
        'page.customs', 'page.packing', 'page.about', 'page.terms', 'page.privacy', 'page.cookies',
        'page.shipping-policy', 'page.claims-policy', 'page.prohibited-items', 'page.refund-policy', 'page.payment-terms',
    ];

    public function sitemap(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        $entries = [];
        foreach (self::ROUTES as $name) {
            $entries[] = ['en' => route('en.'.$name), 'fr' => route('fr.'.$name)];
        }
        foreach (['air', 'sea', 'road', 'express'] as $mode) {
            $entries[] = [
                'en' => route('en.services.show', ['mode' => trans('routes.mode_'.$mode, [], 'en')]),
                'fr' => route('fr.services.show', ['mode' => trans('routes.mode_'.$mode, [], 'fr')]),
            ];
        }

        foreach ($entries as $urls) {
            foreach ($urls as $url) {
                $xml .= '  <url><loc>'.e($url).'</loc>';
                foreach ($urls as $lang => $alt) {
                    $xml .= '<xhtml:link rel="alternate" hreflang="'.$lang.'" href="'.e($alt).'"/>';
                }
                $xml .= "</url>\n";
            }
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        // The admin path is deliberately not listed here: robots.txt must not advertise it (FR-149).
        $lines = [
            'User-agent: *',
            'Disallow: /account',
            'Disallow: /fr/compte',
            'Disallow: /api/',
            'Disallow: /track/',
            'Disallow: /fr/suivi/',
            'Disallow: /files/',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
