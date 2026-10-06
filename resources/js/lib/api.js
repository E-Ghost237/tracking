/**
 * Small JSON client for /api/v1. Same-origin session cookie + CSRF header (Sanctum stateful),
 * and an Idempotency-Key for mutating requests so a double click never creates two records.
 */
export class ApiError extends Error {
    constructor(status, error) {
        super(error?.message || 'Request failed');
        this.status = status;
        this.code = error?.code || 'error';
        this.fields = error?.fields || {};
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

export function newIdempotencyKey() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID().replaceAll('-', '');
    }
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
}

export async function api(path, { method = 'GET', body, query, headers = {}, idempotencyKey } = {}) {
    const url = new URL('/api/v1' + path, window.location.origin);
    if (query) {
        Object.entries(query).forEach(([k, v]) => v !== undefined && v !== null && url.searchParams.set(k, v));
    }

    const options = {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Locale': document.documentElement.lang || 'en',
            ...headers,
        },
    };

    if (idempotencyKey) {
        options.headers['Idempotency-Key'] = idempotencyKey;
    }

    if (body instanceof FormData) {
        options.body = body;
    } else if (body !== undefined) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    let response;
    try {
        response = await fetch(url, options);
    } catch {
        throw new ApiError(0, { code: 'network', message: t('network_error') });
    }

    let data = null;
    if (response.status !== 204) {
        try {
            data = await response.json();
        } catch {
            data = null;
        }
    }

    if (!response.ok) {
        throw new ApiError(response.status, data?.error || { code: 'http_' + response.status, message: t('generic_error') });
    }

    return data;
}

let dictionary = null;

/** Translated UI strings provided by the server in <script type="application/json" id="i18n">. */
export function t(key, replacements = {}) {
    if (dictionary === null) {
        try {
            dictionary = JSON.parse(document.getElementById('i18n')?.textContent || '{}');
        } catch {
            dictionary = {};
        }
    }
    let text = dictionary[key] ?? key;
    Object.entries(replacements).forEach(([name, value]) => {
        text = text.replaceAll(':' + name, String(value));
    });
    return text;
}

export function readJson(id, fallback = null) {
    const node = document.getElementById(id);
    if (!node) {
        return fallback;
    }
    try {
        return JSON.parse(node.textContent);
    } catch {
        return fallback;
    }
}

export function locale() {
    return document.documentElement.lang === 'fr' ? 'fr-FR' : 'en-US';
}

export function formatDateTime(iso) {
    if (!iso) {
        return '';
    }
    return new Intl.DateTimeFormat(locale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

export function formatDate(iso) {
    if (!iso) {
        return '';
    }
    return new Intl.DateTimeFormat(locale(), { dateStyle: 'medium' }).format(new Date(iso));
}

export function formatMoney(minor, currency = 'USD') {
    const zeroDecimal = ['XAF', 'XOF', 'JPY'].includes(currency);
    return new Intl.NumberFormat(locale(), { style: 'currency', currency }).format(zeroDecimal ? minor : minor / 100);
}

export function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
