/**
 * Preview-only entry point: mounts the real three.js globe from
 * resources/js/globe/Globe.js so the fixed camera fit can be reviewed in the
 * browser exactly as the application renders it.
 *
 * In the combined single-file preview the Network page starts hidden, so the
 * globe waits until its container actually has a size before constructing.
 */
import { Globe, webglAvailable } from '../resources/js/globe/Globe.js';

const readJson = (id, fallback) => {
    const node = document.getElementById(id);
    if (!node) return fallback;
    try {
        return JSON.parse(node.textContent);
    } catch {
        return fallback;
    }
};

const land = readJson('land-dots', []);
const hubs = readJson('globe-hubs', []);
const lanes = readJson('globe-lanes', []);

const mount = (container) => {
    const wrap = container.closest('[data-globe-wrap]');

    if (!webglAvailable()) {
        wrap?.classList.add('is-fallback');
        return;
    }

    const globe = new Globe(container, { distance: Number(container.dataset.distance || 3.1), autoRotate: true });
    globe.setLand(land);

    // A tracking page shows one journey, not the whole network.
    const route = (container.dataset.route || '').split(',').map(Number);
    if (route.length === 4 && route.every((value) => Number.isFinite(value))) {
        const [fromLat, fromLon, toLat, toLon] = route;
        globe.addHub(fromLat, fromLon);
        globe.addHub(toLat, toLon);
        globe.addLane([fromLat, fromLon], [toLat, toLon], 'air', { emphasis: true });
    } else {
        hubs.forEach((hub) => globe.addHub(hub.lat, hub.lon));
        lanes.forEach((lane) => globe.addLane(lane.from, lane.to, lane.mode));
    }

    globe.start();

    document.querySelectorAll('[data-globe-zoom]').forEach((button) => {
        button.addEventListener('click', () => globe.zoom(button.dataset.globeZoom === 'in' ? -0.35 : 0.35));
    });

    document.querySelectorAll('[data-globe-region]').forEach((button) => {
        button.addEventListener('click', () => globe.focus(Number(button.dataset.lat), Number(button.dataset.lon)));
    });

    // State the fit numerically rather than implying it.
    const readout = document.querySelector('[data-globe-fit]');
    const report = () => {
        if (readout && globe.minDistance) {
            readout.textContent = `closest camera distance ${globe.minDistance.toFixed(2)} (atmosphere radius 1.12, 42° field of view)`;
        }
    };
    report();
    window.addEventListener('resize', () => window.setTimeout(report, 120));
};

document.querySelectorAll('[data-preview-globe]').forEach((container) => {
    // Hidden pages report a zero width; wait for the page to be shown.
    const waitForLayout = () => {
        if (container.clientWidth > 0) {
            mount(container);
            return;
        }
        window.requestAnimationFrame(waitForLayout);
    };
    waitForLayout();
});
