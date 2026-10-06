/**
 * Builds the globe assets from Natural Earth (public domain) data via world-atlas:
 *  - public/data/land-dots.json : land sample points for the WebGL dotted globe
 *  - public/images/world-map.svg : equirectangular map, the no-WebGL fallback (FR-40)
 *
 * Run with: node scripts/build-globe-data.mjs
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { feature } from 'topojson-client';
import { geoContains, geoPath, geoEquirectangular } from 'd3-geo';

const topology = JSON.parse(readFileSync(new URL('../node_modules/world-atlas/land-110m.json', import.meta.url)));
const land = feature(topology, topology.objects.land);

const dots = [];
const step = 1.25;
for (let lat = -58; lat <= 82; lat += step) {
    // Keep dots evenly spaced on the sphere: fewer longitudes near the poles.
    const lonStep = step / Math.max(0.2, Math.cos((lat * Math.PI) / 180));
    for (let lon = -180; lon < 180; lon += lonStep) {
        if (geoContains(land, [lon, lat])) {
            dots.push(Math.round(lat * 10) / 10, Math.round(lon * 10) / 10);
        }
    }
}
writeFileSync(new URL('../public/data/land-dots.json', import.meta.url), JSON.stringify(dots));

const width = 1000;
const height = 500;
const projection = geoEquirectangular().scale(width / (2 * Math.PI)).translate([width / 2, height / 2]);
const path = geoPath(projection);
const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" role="img" aria-label="World map">`
    + `<rect width="${width}" height="${height}" fill="#0b1830"/>`
    + `<path d="${path(land)}" fill="#1d3557" stroke="#2b4a73" stroke-width="0.6"/></svg>`;
writeFileSync(new URL('../public/images/world-map.svg', import.meta.url), svg);

console.log(`land dots: ${dots.length / 2}, map: ${(svg.length / 1024).toFixed(0)} KB`);
