import { api } from '../lib/api';

/**
 * City/address autocomplete backed by the server geocoding proxy (FR-41).
 * Dispatches "place-selected" with { field, place } to the parent component.
 */
export default () => ({
    query: '',
    results: [],
    open: false,
    active: -1,
    loading: false,
    timer: null,

    init() {
        this.query = this.$root.dataset.initial || '';
        window.addEventListener('place-label', (e) => {
            if (e.detail.field === this.$root.dataset.field) {
                this.query = e.detail.label;
            }
        });
    },

    search() {
        clearTimeout(this.timer);
        if (this.query.trim().length < 2) {
            this.results = [];
            this.open = false;
            return;
        }
        this.timer = setTimeout(async () => {
            this.loading = true;
            try {
                const data = await api('/geocode', { query: { q: this.query.trim() } });
                this.results = data.results;
                this.open = true;
                this.active = this.results.length ? 0 : -1;
            } catch {
                this.results = [];
            } finally {
                this.loading = false;
            }
        }, 220);
    },

    move(step) {
        if (!this.open || this.results.length === 0) {
            return;
        }
        this.active = (this.active + step + this.results.length) % this.results.length;
    },

    choose(index) {
        const place = this.results[index ?? this.active];
        if (!place) {
            return;
        }
        this.query = place.label;
        this.open = false;
        this.$dispatch('place-selected', { field: this.$root.dataset.field, place });
        window.dispatchEvent(new CustomEvent('globe:pin', { detail: { lat: place.lat, lon: place.lon } }));
    },

    close() {
        setTimeout(() => (this.open = false), 150);
    },
});
