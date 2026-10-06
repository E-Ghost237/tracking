import { readJson, t } from '../lib/api';

/**
 * Hero tracking box with carrier auto-detection (FR-04, FR-10, FR-11).
 * Formats come from the carriers table, so admins can change them without a deployment.
 */
export default () => ({
    tab: 'track',
    input: '',
    formats: [],
    error: '',
    quoteFrom: '',
    quoteTo: '',
    quoteWeight: '',

    init() {
        this.formats = (readJson('carrier-formats', []) || []).map((carrier) => ({
            name: carrier.name,
            patterns: carrier.patterns.map((p) => {
                try {
                    return new RegExp('^(?:' + p + ')$');
                } catch {
                    return null;
                }
            }).filter(Boolean),
        }));
    },

    numbers() {
        return this.input
            .split(/[,;\n]+/)
            .flatMap((chunk) => {
                const joined = chunk.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
                return this.carrierFor(joined) || !chunk.trim().includes(' ') ? [joined] : chunk.trim().split(/\s+/).map((p) => p.replace(/[^A-Za-z0-9]/g, '').toUpperCase());
            })
            .filter((n) => n.length > 0)
            .slice(0, 20);
    },

    carrierFor(compact) {
        const match = this.formats.find((carrier) => carrier.patterns.some((re) => re.test(compact)));
        return match ? match.name : null;
    },

    get detected() {
        const numbers = this.numbers();
        if (numbers.length === 0) {
            return '';
        }
        if (numbers.length > 1) {
            return t('numbers_count', { count: numbers.length });
        }
        const carrier = this.carrierFor(numbers[0]);
        return carrier ? t('detected_carrier', { carrier }) : '';
    },

    useExample(number) {
        this.input = number;
        this.$refs.trackInput?.focus();
    },

    submit() {
        const numbers = this.numbers();
        if (numbers.length === 0) {
            this.error = t('enter_number');
            return;
        }
        this.error = '';
        const base = this.$root.dataset.trackUrl;
        window.location.href = numbers.length === 1 ? base + '/' + encodeURIComponent(numbers[0]) : base + '?numbers=' + encodeURIComponent(numbers.join(','));
    },

    submitQuote() {
        const params = new URLSearchParams();
        if (this.quoteFrom) params.set('from', this.quoteFrom);
        if (this.quoteTo) params.set('to', this.quoteTo);
        if (this.quoteWeight) params.set('weight', this.quoteWeight);
        window.location.href = this.$root.dataset.quoteUrl + (params.toString() ? '?' + params.toString() : '');
    },
});
