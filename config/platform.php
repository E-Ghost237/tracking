<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand (placeholder until the client confirms name, logo and colours)
    |--------------------------------------------------------------------------
    */
    'brand' => [
        'name' => env('BRAND_NAME', 'Corvane'),
        'legal_name' => env('BRAND_LEGAL_NAME', 'Corvane Logistics (placeholder entity)'),
        'support_email' => env('BRAND_SUPPORT_EMAIL', 'support@corvane.test'),
        'support_phone' => env('BRAND_SUPPORT_PHONE', '+1 000 000 0000'),
        'whatsapp' => env('BRAND_WHATSAPP', ''),
        'data_contact' => env('BRAND_DATA_CONTACT', 'privacy@corvane.test'),
        'address' => env('BRAND_ADDRESS', 'Registered address to be confirmed'),
    ],

    'locales' => ['en', 'fr'],

    /*
    |--------------------------------------------------------------------------
    | Defaults for admin-editable settings (FR-129). Values in the settings
    | table override these.
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'currency' => 'USD',
        'exchange_rates' => ['USD' => 1, 'EUR' => 0.92, 'XAF' => 605, 'GBP' => 0.79, 'CAD' => 1.37],
        'quote_validity_days' => 7,
        'quote_range_percent' => 8,
        'payment_expiry_hours' => 48,
        'payment_reminder_hours' => [24, 2],
        'review_target_minutes' => 30,
        'review_alert_minutes' => 60,
        'two_person_threshold' => 100000,
        'max_rejected_attempts' => 5,
        'road_max_km' => 3500,
        'minimum_charge' => 1500,
        'payment_details_change_delay_hours' => 0,
        'gift_card_min_account_age_days' => 30,
        'maintenance_mode' => false,
        'staffed_hours' => 'Mon–Sat, 08:00–20:00 (UTC+1)',
        'hero_scene_default' => 'air',
    ],

    /*
    |--------------------------------------------------------------------------
    | Package categories. Prohibited categories are blocked at booking (R8).
    |--------------------------------------------------------------------------
    */
    'package_categories' => [
        'documents' => ['prohibited' => false],
        'clothing' => ['prohibited' => false],
        'electronics' => ['prohibited' => false],
        'food_dry' => ['prohibited' => false],
        'cosmetics' => ['prohibited' => false],
        'household' => ['prohibited' => false],
        'auto_parts' => ['prohibited' => false],
        'medicine' => ['prohibited' => false, 'restricted' => true],
        'batteries' => ['prohibited' => false, 'restricted' => true],
        'cash' => ['prohibited' => true],
        'weapons' => ['prohibited' => true],
        'explosives' => ['prohibited' => true],
        'narcotics' => ['prohibited' => true],
        'perishables' => ['prohibited' => true],
        'live_animals' => ['prohibited' => true],
        'counterfeit' => ['prohibited' => true],
    ],

    'upload' => [
        'proof_max_kb' => 8192,
        'proof_max_files' => 3,
        'proof_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'application/pdf'],
        'signed_url_minutes' => 5,
    ],

    'admin' => [
        'path' => env('ADMIN_PATH', 'admin'),
        'ip_allowlist' => array_filter(array_map('trim', explode(',', (string) env('ADMIN_IP_ALLOWLIST', '')))),
    ],

    'captcha' => [
        'driver' => env('CAPTCHA_DRIVER', 'builtin'),
        'turnstile_site_key' => env('TURNSTILE_SITE_KEY'),
        'turnstile_secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'tracking' => [
        'provider' => env('TRACKING_PROVIDER', 'null'),
        'cache_minutes' => 15,
        'aftership_key' => env('AFTERSHIP_API_KEY'),
        'webhook_secret' => env('TRACKING_WEBHOOK_SECRET'),
        'captcha_after_failures' => 10,
        'max_numbers' => 20,
    ],

    'geocoding' => [
        'driver' => env('GEOCODER', 'local'),
        'mapbox_token' => env('MAPBOX_TOKEN'),
        'opencage_key' => env('OPENCAGE_KEY'),
        'cache_days' => 30,
    ],

    'security' => [
        'field_encryption_key' => env('FIELD_ENCRYPTION_KEY'),
        'lockout_attempts' => 5,
        'lockout_minutes' => 15,
        'login_captcha_after' => 3,
        'malware_scanner' => env('MALWARE_SCANNER', 'heuristic'),
        'clamav_host' => env('CLAMAV_HOST', '127.0.0.1'),
        'clamav_port' => (int) env('CLAMAV_PORT', 3310),
        'hsts' => env('SECURITY_HSTS', env('APP_ENV') === 'production'),
        'global_rate_per_minute' => (int) env('GLOBAL_RATE_PER_MINUTE', 240),
    ],
];
