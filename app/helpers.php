<?php

use Illuminate\Support\Facades\Route;

if (! function_exists('lroute')) {
    /**
     * URL of a localised route in the current (or given) locale.
     *
     * @param  mixed  $parameters  route parameters or a model
     */
    function lroute(string $name, mixed $parameters = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return route($locale.'.'.$name, $parameters);
    }
}

if (! function_exists('alternate_url')) {
    /**
     * The current page in another language, for the language switcher and hreflang tags.
     */
    function alternate_url(string $locale): string
    {
        $route = Route::current();
        $name = $route?->getName();

        if ($name === null || ! preg_match('/^(en|fr)\.(.+)$/', $name, $m) || ! Route::has($locale.'.'.$m[2])) {
            return route($locale.'.home');
        }

        $parameters = $route->parameters();
        if ($m[2] === 'services.show' && isset($parameters['mode'])) {
            foreach (['air', 'sea', 'road', 'express'] as $mode) {
                if ($parameters['mode'] === trans('routes.mode_'.$mode, [], $m[1])) {
                    $parameters['mode'] = trans('routes.mode_'.$mode, [], $locale);
                }
            }
        }
        unset($parameters['slug']);

        $query = request()->query();

        return route($locale.'.'.$m[2], $parameters).($query && $m[2] !== 'password.reset' ? '?'.http_build_query($query) : '');
    }
}

if (! function_exists('js_strings')) {
    /**
     * UI strings used by the front-end components, in the current language.
     *
     * @return array<string, string>
     */
    function js_strings(): array
    {
        $strings = [
            'network_error' => __('We could not reach the server. Check your connection and try again.'),
            'generic_error' => __('Something went wrong. Please try again.'),
            'enter_number' => __('Enter at least one tracking number.'),
            'numbers_count' => __(':count tracking numbers'),
            'detected_carrier' => __('Detected: :carrier'),
            'choose_places' => __('Choose the origin and destination from the suggestions.'),
            'choose_service' => __('Choose a service to continue.'),
            'accept_terms' => __('Please accept the terms of service.'),
            'verify_email_first' => __('Please verify your email address first.'),
            'max_files' => __('You can upload up to 3 files.'),
            'file_too_large' => __('Each file must be 8 MB or smaller.'),
            'file_type' => __('Only JPG, PNG, WebP, HEIC images and PDF documents are accepted.'),
            'add_proof' => __('Add a screenshot or receipt of your payment.'),
            'confirm_delete_address' => __('Delete this address?'),
            'mode_air' => __('Air freight'),
            'mode_sea' => __('Sea freight'),
            'mode_road' => __('Road freight'),
            'mode_express' => __('Express'),
        ];

        foreach (array_keys(config('platform.package_categories')) as $category) {
            $strings['category_'.$category] = __('category.'.$category);
        }

        return $strings;
    }
}
