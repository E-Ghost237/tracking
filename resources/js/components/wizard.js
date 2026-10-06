import { api, newIdempotencyKey, readJson, t } from '../lib/api';

const STEPS = ['route', 'packages', 'service', 'parties', 'review'];

const emptyAddress = () => ({ line1: '', line2: '', city: '', region: '', postal_code: '', country: '', lat: null, lon: null });
const emptyParty = () => ({ name: '', company: '', phone: '', email: '' });
const emptyPackage = () => ({ description: '', weight_kg: '', length_cm: '', width_cm: '', height_cm: '', value: '', category: '' });

/**
 * Booking wizard (section 4.4): the draft is saved on the server after each step (FR-30)
 * and the final step creates the order awaiting payment (FR-31).
 */
export default () => ({
    draftId: null,
    step: 1,
    furthest: 1,
    saving: false,
    errors: {},
    error: '',
    options: [],
    pricing: false,
    booking: false,
    idempotencyKey: null,
    quoteReference: '',
    addresses: [],
    form: {
        route: { origin: emptyAddress(), destination: emptyAddress() },
        packages: [emptyPackage()],
        service: { mode: '', insurance: false },
        parties: { sender: emptyParty(), recipient: emptyParty(), customs: { contents: '', hs_code: '', reason: 'personal' } },
        review: { accepted_terms: false },
    },

    init() {
        this.addresses = readJson('address-book', []) || [];
        const draft = readJson('draft-data', null);
        if (draft) {
            this.draftId = draft.id;
            this.hydrate(draft.data || {});
            this.furthest = draft.step || 1;
            this.step = Math.min(draft.step || 1, 5);
        }
        if (this.step === 3) {
            this.loadOptions();
        }
    },

    hydrate(data) {
        if (data.prefill) {
            const p = data.prefill;
            this.form.route.origin = { ...emptyAddress(), city: p.origin.city, country: p.origin.country, lat: p.origin.lat, lon: p.origin.lon };
            this.form.route.destination = { ...emptyAddress(), city: p.destination.city, country: p.destination.country, lat: p.destination.lat, lon: p.destination.lon };
            this.form.packages = p.packages.map((pkg) => ({ ...emptyPackage(), ...pkg }));
            this.form.service.mode = p.mode;
            this.form.service.insurance = !!p.insurance;
            this.quoteReference = p.quote_reference;
        }
        if (data.route) this.form.route = { origin: { ...emptyAddress(), ...data.route.origin }, destination: { ...emptyAddress(), ...data.route.destination } };
        if (data.packages) this.form.packages = data.packages.map((pkg) => ({ ...emptyPackage(), ...pkg }));
        if (data.service) this.form.service = { ...data.service };
        if (data.parties) {
            this.form.parties = {
                sender: { ...emptyParty(), ...data.parties.sender },
                recipient: { ...emptyParty(), ...data.parties.recipient },
                customs: { contents: '', hs_code: '', reason: 'personal', ...(data.parties.customs || {}) },
            };
        }
        if (data.review) this.form.review = { ...data.review };
    },

    stepName(index = this.step) {
        return STEPS[index - 1];
    },

    isInternational() {
        return this.form.route.origin.country && this.form.route.destination.country && this.form.route.origin.country !== this.form.route.destination.country;
    },

    onPlace(detail) {
        const target = detail.field === 'origin' ? this.form.route.origin : this.form.route.destination;
        target.city = detail.place.city;
        target.country = detail.place.country;
        target.region = detail.place.region || target.region;
        target.lat = detail.place.lat;
        target.lon = detail.place.lon;
    },

    useAddress(field, id) {
        const address = this.addresses.find((a) => a.public_id === id);
        if (!address) {
            return;
        }
        const target = field === 'origin' ? this.form.route.origin : this.form.route.destination;
        Object.assign(target, {
            line1: address.line1, line2: address.line2 || '', city: address.city, region: address.region || '',
            postal_code: address.postal_code || '', country: address.country, lat: address.lat, lon: address.lon,
        });
        window.dispatchEvent(new CustomEvent('place-label', { detail: { field, label: address.city + ', ' + address.country } }));
        const party = field === 'origin' ? this.form.parties.sender : this.form.parties.recipient;
        if (!party.name) {
            Object.assign(party, { name: address.name, company: address.company || '', phone: address.phone || '', email: address.email || '' });
        }
    },

    addPackage() {
        if (this.form.packages.length < 20) {
            this.form.packages.push(emptyPackage());
        }
    },

    removePackage(index) {
        if (this.form.packages.length > 1) {
            this.form.packages.splice(index, 1);
        }
    },

    fieldError(key) {
        return this.errors[key]?.[0] || '';
    },

    payload(name) {
        const f = this.form;
        switch (name) {
            case 'route':
                return { origin: f.route.origin, destination: f.route.destination };
            case 'packages':
                return { packages: f.packages.map((p) => ({ ...p, weight_kg: Number(p.weight_kg), length_cm: Number(p.length_cm), width_cm: Number(p.width_cm), height_cm: Number(p.height_cm), value: Number(p.value || 0) })) };
            case 'service':
                return { mode: f.service.mode, insurance: !!f.service.insurance };
            case 'parties':
                return { sender: f.parties.sender, recipient: f.parties.recipient, customs: this.isInternational() ? f.parties.customs : null };
            default:
                return { accepted_terms: !!f.review.accepted_terms };
        }
    },

    async ensureDraft() {
        if (this.draftId) {
            return;
        }
        const draft = await api('/shipment-drafts', { method: 'POST', idempotencyKey: newIdempotencyKey() });
        this.draftId = draft.id;
        const url = new URL(window.location.href);
        url.searchParams.set('draft', draft.id);
        window.history.replaceState({}, '', url);
    },

    async next() {
        const name = this.stepName();
        if (name === 'route' && (this.form.route.origin.lat === null || this.form.route.destination.lat === null)) {
            this.error = t('choose_places');
            return;
        }
        if (name === 'service' && !this.form.service.mode) {
            this.error = t('choose_service');
            return;
        }

        this.saving = true;
        this.errors = {};
        this.error = '';
        try {
            await this.ensureDraft();
            const draft = await api('/shipment-drafts/' + this.draftId, { method: 'PUT', body: { step: name, data: this.payload(name) } });
            this.furthest = Math.max(this.furthest, draft.step);
            if (this.step < 5) {
                this.goTo(this.step + 1);
            }
        } catch (e) {
            this.errors = Object.fromEntries(Object.entries(e.fields || {}).map(([k, v]) => [k.replace(/^data\./, ''), v]));
            this.error = e.message;
        } finally {
            this.saving = false;
        }
    },

    back() {
        if (this.step > 1) {
            this.goTo(this.step - 1);
        }
    },

    goTo(step) {
        if (step > this.furthest) {
            return;
        }
        this.step = step;
        this.error = '';
        window.scrollTo({ top: this.$root.offsetTop - 90, behavior: 'smooth' });
        if (step === 3) {
            this.loadOptions();
        }
    },

    async loadOptions() {
        if (!this.draftId) {
            return;
        }
        this.pricing = true;
        try {
            const data = await api('/shipment-drafts/' + this.draftId + '/price', { method: 'POST', body: { insurance: !!this.form.service.insurance } });
            this.options = data.options;
            const current = this.options.find((o) => o.mode === this.form.service.mode && o.available);
            if (!current) {
                this.form.service.mode = this.options.find((o) => o.available)?.mode || '';
            }
        } catch (e) {
            this.error = e.message;
        } finally {
            this.pricing = false;
        }
    },

    selectedOption() {
        return this.options.find((o) => o.mode === this.form.service.mode) || null;
    },

    toggleInsurance() {
        this.form.service.insurance = !this.form.service.insurance;
        this.loadOptions();
    },

    async book() {
        if (!this.form.review.accepted_terms) {
            this.error = t('accept_terms');
            return;
        }
        this.booking = true;
        this.error = '';
        this.idempotencyKey ??= newIdempotencyKey();
        try {
            await api('/shipment-drafts/' + this.draftId, { method: 'PUT', body: { step: 'review', data: { accepted_terms: true } } });
            const order = await api('/shipments', { method: 'POST', body: { draft_id: this.draftId }, idempotencyKey: this.idempotencyKey });
            window.location.href = order.pay_url;
        } catch (e) {
            this.error = e.message;
            this.booking = false;
        }
    },

    modeLabel(mode) {
        return t('mode_' + mode);
    },

    categoryLabel(code) {
        return t('category_' + code);
    },

    totalWeight() {
        return this.form.packages.reduce((sum, p) => sum + (Number(p.weight_kg) || 0), 0).toFixed(1);
    },

    totalValue() {
        return this.form.packages.reduce((sum, p) => sum + (Number(p.value) || 0), 0).toFixed(2);
    },
});
