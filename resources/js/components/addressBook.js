import { api, newIdempotencyKey, t } from '../lib/api';

const blank = () => ({
    public_id: null, label: '', name: '', company: '', line1: '', line2: '', city: '', region: '', postal_code: '',
    country: '', phone: '', email: '', lat: null, lon: null, is_default_sender: false, is_default_recipient: false,
});

/**
 * Address book with globe-validated pins (FR-103, FR-45).
 */
export default () => ({
    addresses: [],
    loading: true,
    editing: null,
    saving: false,
    errors: {},
    error: '',

    async init() {
        window.addEventListener('globe:picked', (e) => {
            if (this.editing) {
                this.editing.lat = e.detail.lat;
                this.editing.lon = e.detail.lon;
                window.dispatchEvent(new CustomEvent('globe:pin', { detail: e.detail }));
            }
        });
        await this.load();
    },

    async load() {
        this.loading = true;
        try {
            this.addresses = (await api('/addresses')).data;
        } finally {
            this.loading = false;
        }
    },

    create() {
        this.editing = blank();
        this.errors = {};
    },

    edit(address) {
        this.editing = { ...blank(), ...address };
        this.errors = {};
        setTimeout(() => {
            window.dispatchEvent(new CustomEvent('place-label', { detail: { field: 'address', label: address.city + ', ' + address.country } }));
            if (address.lat !== null) {
                window.dispatchEvent(new CustomEvent('globe:pin', { detail: { lat: address.lat, lon: address.lon } }));
            }
        }, 50);
    },

    cancel() {
        this.editing = null;
    },

    onPlace(detail) {
        if (!this.editing) {
            return;
        }
        this.editing.city = detail.place.city;
        this.editing.country = detail.place.country;
        this.editing.region = detail.place.region || this.editing.region;
        this.editing.lat = detail.place.lat;
        this.editing.lon = detail.place.lon;
    },

    fieldError(key) {
        return this.errors[key]?.[0] || '';
    },

    async save() {
        this.saving = true;
        this.errors = {};
        this.error = '';
        const { public_id: id, ...body } = this.editing;
        try {
            if (id) {
                await api('/addresses/' + id, { method: 'PUT', body });
            } else {
                await api('/addresses', { method: 'POST', body, idempotencyKey: newIdempotencyKey() });
            }
            this.editing = null;
            await this.load();
        } catch (e) {
            this.errors = e.fields || {};
            this.error = e.message;
        } finally {
            this.saving = false;
        }
    },

    async remove(address) {
        if (!window.confirm(t('confirm_delete_address'))) {
            return;
        }
        await api('/addresses/' + address.public_id, { method: 'DELETE' });
        await this.load();
    },
});
