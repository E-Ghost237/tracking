<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Photography library
    |--------------------------------------------------------------------------
    |
    | Every photograph used by the public site is registered here with both a
    | JPEG (also used for og:image, where scrapers expect JPEG) and a WebP
    | served to browsers. Templates reference entries through the media() helper
    | and the <x-photo> component, so no view hard-codes an image path and the
    | whole library can be replaced in one place.
    |
    | The library intentionally keeps the photography that shows real carrier
    | operations and liveries: an apron loading scene, express handling at an
    | airport, road haulage and a distribution hub. They describe the services
    | accurately and are used with the operator's permission (see the trademark
    | note in the footer).
    |
    | Each entry carries the widths of the responsive variants produced for it by
    | `scripts/optimize-images.sh` (`<name>-<width>.webp`). The helper turns the list
    | into the srcset, so a photo used in a quarter-width card downloads a
    | quarter-width file. `width`/`height` are the intrinsic size of the full-size
    | WebP and are written onto the <img>, which keeps the layout from shifting.
    |
    | Run `scripts/optimize-images.sh` after adding or replacing a photograph to
    | regenerate the WebP files next to it.
    |
    | @see resources/views/components/photo.blade.php
    */

    /* Home hero: wide apron crop, loaded eagerly as it is above the fold. */
    'hero_air' => [
        'webp' => 'images/hero-freight-air.webp',
        'variants' => [480, 800, 1100],
        'jpg' => 'images/hero-freight-air.jpg',
        'width' => 1600,
        'height' => 640,
        'sizes' => '100vw',
        'alt' => 'media.hero_air',
    ],

    'service_air' => [
        'webp' => 'images/freight-air.webp',
        'variants' => [480, 800, 1100],
        'jpg' => 'images/freight-air.jpg',
        'width' => 1376,
        'height' => 768,
        'alt' => 'media.service_air',
    ],

    'service_sea' => [
        'webp' => 'images/freight-sea.webp',
        'variants' => [480, 800, 1100],
        'jpg' => 'images/freight-sea.jpg',
        'width' => 1376,
        'height' => 768,
        'alt' => 'media.service_sea',
    ],

    'service_road' => [
        'webp' => 'images/freight-road.webp',
        'variants' => [480, 800, 1100],
        'jpg' => 'images/freight-road.jpg',
        'width' => 1376,
        'height' => 768,
        'alt' => 'media.service_road',
    ],

    'service_express' => [
        'webp' => 'images/freight-express.webp',
        'variants' => [480, 800, 1100],
        'jpg' => 'images/freight-express.jpg',
        'width' => 1376,
        'height' => 768,
        'alt' => 'media.service_express',
    ],

    /* Distribution hub: used in the coverage section and on network pages. */
    'editorial_hub' => [
        'webp' => 'images/warehouse-hub.webp',
        'variants' => [480, 800, 1100],
        'jpg' => 'images/warehouse-hub.jpg',
        'width' => 1376,
        'height' => 768,
        'alt' => 'media.editorial_hub',
        'caption' => 'media.caption_hub',
    ],

    /* Shipping paperwork: rates, quote and the closing call to action. */
    'editorial_documents' => [
        'webp' => 'images/customs-documents.webp',
        'variants' => [480, 800, 1100],
        'jpg' => 'images/customs-documents.jpg',
        'width' => 1400,
        'height' => 764,
        'alt' => 'media.editorial_documents',
        'caption' => 'media.caption_documents',
    ],

    /* Route map artwork, used behind the network panel and as the WebGL fallback. */
    'editorial_network' => [
        'webp' => 'images/global-globe-logistics.webp',
        'variants' => [480, 800, 1100],
        'jpg' => 'images/global-globe-logistics.jpg',
        'width' => 1376,
        'height' => 768,
        'alt' => 'media.editorial_network',
    ],

];
