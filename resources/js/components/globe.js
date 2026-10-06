import { api, prefersReducedMotion } from '../lib/api';

/**
 * Lazily mounts the three.js globe (separate chunk) when its container comes into view.
 * Falls back to a static map when WebGL is unavailable (FR-40).
 */
export default () => ({
    ready: false,
    fallback: false,
    hubs: [],
    regions: [],
    instance: null,

    init() {
        const el = this.$refs.canvas;
        const io = new IntersectionObserver((entries) => {
            if (entries[0]?.isIntersecting) {
                io.disconnect();
                this.mount(el);
            }
        }, { rootMargin: '200px' });
        io.observe(this.$root);
    },

    async mount(el) {
        const { Globe, webglAvailable } = await import('../globe/Globe.js');
        const withNetwork = this.$root.dataset.network !== 'false';

        if (withNetwork) {
            try {
                const network = await api('/network');
                this.hubs = network.hubs;
                this.regions = network.regions;
                this.lanes = network.lanes;
            } catch {
                this.hubs = [];
            }
        }

        if (!webglAvailable()) {
            this.fallback = true;
            return;
        }

        const land = await fetch('/data/land-dots.json', { credentials: 'same-origin' }).then((r) => r.json()).catch(() => []);
        const picker = this.$root.dataset.picker === 'true';

        this.instance = new Globe(el, {
            autoRotate: this.$root.dataset.autorotate !== 'false',
            distance: Number(this.$root.dataset.distance || 3.1),
            onPick: picker ? (point) => window.dispatchEvent(new CustomEvent('globe:picked', { detail: point })) : null,
        });
        this.instance.setLand(land);

        if (withNetwork) {
            this.hubs.forEach((hub) => this.instance.addHub(hub.lat, hub.lon));
            (this.lanes || []).forEach((lane) => this.instance.addLane(lane.from, lane.to, lane.mode));
        }

        window.addEventListener('globe:route', (e) => this.instance.showRoute(e.detail.origin, e.detail.destination, e.detail.current));
        window.addEventListener('globe:focus', (e) => this.instance.focus(e.detail.lat, e.detail.lon));
        window.addEventListener('globe:pin', (e) => this.instance.setPin(e.detail.lat, e.detail.lon));

        this.instance.start();
        this.ready = true;

        const pending = window.__pendingGlobeRoute;
        if (pending) {
            this.instance.showRoute(pending.origin, pending.destination, pending.current);
        }
    },

    zoomIn() {
        this.instance?.zoom(-0.35);
    },

    zoomOut() {
        this.instance?.zoom(0.35);
    },

    focusRegion(region) {
        this.instance?.focus(region.lat, region.lon);
    },

    /** Position of a hub on the static fallback map (equirectangular), as CSS percentages. */
    fallbackLeft(hub) {
        return ((hub.lon + 180) / 360) * 100 + '%';
    },

    fallbackTop(hub) {
        return ((90 - hub.lat) / 180) * 100 + '%';
    },

    reducedMotion() {
        return prefersReducedMotion();
    },
});
