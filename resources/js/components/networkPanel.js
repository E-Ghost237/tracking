import { api } from '../lib/api';

/**
 * Globe address finder panel (FR-42, FR-44): click the globe or enter coordinates to see the
 * delivery network, nearest hub and distance, then "Ship to this point".
 */
export default () => ({
    point: null,
    place: null,
    network: null,
    hub: null,
    loading: false,
    lat: '',
    lon: '',
    error: '',

    init() {
        window.addEventListener('globe:picked', (e) => this.lookup(e.detail.lat, e.detail.lon));
    },

    onPlace(detail) {
        this.lookup(detail.place.lat, detail.place.lon);
    },

    submitCoordinates() {
        const lat = parseFloat(this.lat);
        const lon = parseFloat(this.lon);
        if (Number.isNaN(lat) || Number.isNaN(lon) || lat < -90 || lat > 90 || lon < -180 || lon > 180) {
            this.error = this.$root.dataset.invalidCoordinates;
            return;
        }
        this.error = '';
        this.lookup(lat, lon);
    },

    async lookup(lat, lon) {
        this.loading = true;
        window.dispatchEvent(new CustomEvent('globe:pin', { detail: { lat, lon } }));
        try {
            const data = await api('/geocode/reverse', { query: { lat, lon } });
            this.point = data.point;
            this.place = data.place;
            this.network = data.network;
            this.hub = data.nearest_hub;
        } catch (e) {
            this.error = e.message;
        } finally {
            this.loading = false;
        }
    },

    shipUrl() {
        if (!this.place) {
            return this.$root.dataset.quoteUrl;
        }
        const params = new URLSearchParams({
            to_city: this.place.city,
            to_country: this.place.country,
            to_lat: this.place.lat,
            to_lon: this.place.lon,
        });
        return this.$root.dataset.quoteUrl + '?' + params.toString();
    },
});
