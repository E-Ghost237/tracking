import { api, newIdempotencyKey, t } from '../lib/api';

const emptyPackage = () => ({ weight_kg: '', length_cm: '', width_cm: '', height_cm: '' });

/**
 * Rate calculator (FR-20 to FR-25).
 */
export default () => ({
    origin: null,
    destination: null,
    packages: [emptyPackage()],
    mode: 'air',
    insurance: false,
    declaredValue: '',
    loading: false,
    result: null,
    error: '',
    errors: {},
    booking: false,

    init() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('weight')) {
            this.packages[0].weight_kg = params.get('weight');
            this.packages[0].length_cm = 30;
            this.packages[0].width_cm = 20;
            this.packages[0].height_cm = 15;
        }
        if (['air', 'sea', 'road', 'express'].includes(params.get('mode'))) {
            this.mode = params.get('mode');
        }
        const toLat = parseFloat(params.get('to_lat'));
        const toLon = parseFloat(params.get('to_lon'));
        if (params.get('to_country') && !Number.isNaN(toLat) && !Number.isNaN(toLon)) {
            this.destination = { city: params.get('to_city') || '', country: params.get('to_country'), lat: toLat, lon: toLon, label: params.get('to_city') || '' };
        }
    },

    onPlace(detail) {
        if (detail.field === 'origin') {
            this.origin = detail.place;
        } else if (detail.field === 'destination') {
            this.destination = detail.place;
        }
        if (this.origin && this.destination) {
            window.dispatchEvent(new CustomEvent('globe:route', { detail: { origin: this.origin, destination: this.destination } }));
        }
    },

    addPackage() {
        if (this.packages.length < 20) {
            this.packages.push(emptyPackage());
        }
    },

    removePackage(index) {
        if (this.packages.length > 1) {
            this.packages.splice(index, 1);
        }
    },

    setMode(mode) {
        this.mode = mode;
        if (this.result) {
            this.submit();
        }
    },

    fieldError(key) {
        return this.errors[key]?.[0] || '';
    },

    async submit() {
        this.errors = {};
        this.error = '';
        if (!this.origin || !this.destination) {
            this.error = t('choose_places');
            return;
        }
        this.loading = true;
        try {
            const data = await api('/quotes', {
                method: 'POST',
                body: {
                    origin: { city: this.origin.city, country: this.origin.country, lat: this.origin.lat, lon: this.origin.lon },
                    destination: { city: this.destination.city, country: this.destination.country, lat: this.destination.lat, lon: this.destination.lon },
                    packages: this.packages.map((p) => ({ weight_kg: Number(p.weight_kg), length_cm: Number(p.length_cm), width_cm: Number(p.width_cm), height_cm: Number(p.height_cm) })),
                    mode: this.mode,
                    insurance: this.insurance,
                    declared_value: this.declaredValue === '' ? 0 : Number(this.declaredValue),
                },
            });
            this.result = data.data;
            this.$nextTick(() => this.$refs.result?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
        } catch (e) {
            this.result = null;
            this.errors = e.fields || {};
            this.error = e.message;
        } finally {
            this.loading = false;
        }
    },

    async book() {
        if (!this.result) {
            return;
        }
        if (this.$root.dataset.auth !== 'true') {
            window.location.href = this.$root.dataset.loginUrl;
            return;
        }
        this.booking = true;
        try {
            const data = await api('/quotes/' + this.result.id + '/book', { method: 'POST', idempotencyKey: newIdempotencyKey() });
            window.location.href = data.wizard_url;
        } catch (e) {
            this.error = e.code === 'email_not_verified' ? t('verify_email_first') : e.message;
            this.booking = false;
        }
    },

    modeLabel(mode) {
        return t('mode_' + mode);
    },
});
