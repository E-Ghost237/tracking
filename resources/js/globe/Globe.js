import {
    AdditiveBlending,
    BackSide,
    BufferAttribute,
    BufferGeometry,
    CanvasTexture,
    Color,
    DoubleSide,
    Group,
    Line,
    LineBasicMaterial,
    Mesh,
    MeshBasicMaterial,
    PerspectiveCamera,
    Points,
    PointsMaterial,
    QuadraticBezierCurve3,
    Raycaster,
    RingGeometry,
    Scene,
    ShaderMaterial,
    SphereGeometry,
    Vector2,
    Vector3,
    WebGLRenderer,
} from 'three';

const RADIUS = 1;
const DEG = Math.PI / 180;

const MODE_COLORS = {
    air: '#7fb3e0',
    express: '#ef9a68',
    sea: '#4a8ab5',
    road: '#e2703a',
    route: '#e2703a',
};

/** Radius of the atmosphere shell. The camera fit below keeps it fully in frame. */
const ATMOSPHERE_RADIUS = 1.12;

/** lat/lon in degrees to a point on the sphere (three-globe convention). */
export function toVector(lat, lon, radius = RADIUS) {
    const phi = (90 - lat) * DEG;
    const theta = (lon + 180) * DEG;
    return new Vector3(-radius * Math.sin(phi) * Math.cos(theta), radius * Math.cos(phi), radius * Math.sin(phi) * Math.sin(theta));
}

export function toLatLon(vector) {
    const v = vector.clone().normalize();
    const lat = 90 - Math.acos(v.y) / DEG;
    let lon = Math.atan2(v.z, -v.x) / DEG - 180;
    if (lon < -180) {
        lon += 360;
    }
    return { lat, lon };
}

function dotTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 64;
    const ctx = canvas.getContext('2d');
    const gradient = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
    gradient.addColorStop(0, 'rgba(255,255,255,1)');
    gradient.addColorStop(0.55, 'rgba(255,255,255,0.95)');
    gradient.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = gradient;
    ctx.beginPath();
    ctx.arc(32, 32, 32, 0, Math.PI * 2);
    ctx.fill();
    return new CanvasTexture(canvas);
}

/**
 * Interactive WebGL globe. Emits:
 *  - onPick({lat, lon}) when the user clicks the globe (FR-42)
 */
export class Globe {
    constructor(container, options = {}) {
        this.container = container;
        this.options = { autoRotate: true, interactive: true, dotColor: '#5d7fa6', ...options };
        this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.lanes = [];
        this.markers = [];
        this.running = false;
        this.visible = true;
        this.velocity = { x: 0, y: 0 };
        this.targetRotation = null;
        this.distance = options.distance ?? 3.1;
        this.targetDistance = this.distance;
        this.onPick = options.onPick || null;

        this.scene = new Scene();
        this.camera = new PerspectiveCamera(42, 1, 0.1, 100);
        this.camera.position.set(0, 0, this.distance);

        this.renderer = new WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        this.renderer.setClearColor(0x000000, 0);
        this.renderer.domElement.setAttribute('aria-hidden', 'true');
        this.renderer.domElement.style.touchAction = 'pan-y';
        container.appendChild(this.renderer.domElement);

        this.root = new Group();
        this.root.rotation.set(12 * DEG, -100 * DEG, 0);
        this.scene.add(this.root);

        this.buildSphere();
        this.resize();
        this.bindEvents();
    }

    buildSphere() {
        const sphere = new Mesh(
            new SphereGeometry(RADIUS * 0.995, 64, 64),
            new MeshBasicMaterial({ color: new Color('#0b1f36'), transparent: true, opacity: 0.96 }),
        );
        this.sphere = sphere;
        this.root.add(sphere);

        // Fresnel atmosphere glow.
        const atmosphere = new Mesh(
            new SphereGeometry(RADIUS * ATMOSPHERE_RADIUS, 64, 64),
            new ShaderMaterial({
                transparent: true,
                side: BackSide,
                blending: AdditiveBlending,
                depthWrite: false,
                uniforms: { glowColor: { value: new Color('#3b74a8') } },
                vertexShader: `
                    varying vec3 vNormal;
                    void main() {
                        vNormal = normalize(normalMatrix * normal);
                        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
                    }`,
                fragmentShader: `
                    uniform vec3 glowColor;
                    varying vec3 vNormal;
                    void main() {
                        float intensity = pow(0.5 - dot(vNormal, vec3(0.0, 0.0, 1.0)), 4.0);
                        gl_FragColor = vec4(glowColor, 1.0) * intensity;
                    }`,
            }),
        );
        this.scene.add(atmosphere);
    }

    setLand(flatLatLon) {
        const count = flatLatLon.length / 2;
        const positions = new Float32Array(count * 3);
        for (let i = 0; i < count; i++) {
            const v = toVector(flatLatLon[i * 2], flatLatLon[i * 2 + 1], RADIUS * 1.001);
            positions[i * 3] = v.x;
            positions[i * 3 + 1] = v.y;
            positions[i * 3 + 2] = v.z;
        }
        const geometry = new BufferGeometry();
        geometry.setAttribute('position', new BufferAttribute(positions, 3));
        const points = new Points(
            geometry,
            new PointsMaterial({
                color: new Color(this.options.dotColor),
                size: 0.025,
                map: dotTexture(),
                transparent: true,
                alphaTest: 0.3,
                sizeAttenuation: true,
                depthWrite: false,
            }),
        );
        this.root.add(points);
    }

    addHub(lat, lon, color = '#e2703a') {
        const position = toVector(lat, lon, RADIUS * 1.004);
        const group = new Group();
        group.position.copy(position);
        group.lookAt(position.clone().multiplyScalar(2));

        const dot = new Mesh(new SphereGeometry(0.012, 16, 16), new MeshBasicMaterial({ color }));
        const ring = new Mesh(
            new RingGeometry(0.016, 0.022, 32),
            new MeshBasicMaterial({ color, transparent: true, opacity: 0.8, side: DoubleSide, depthWrite: false }),
        );
        group.add(dot, ring);
        group.userData = { ring, phase: Math.random() * Math.PI * 2 };
        this.root.add(group);
        this.markers.push(group);
        return group;
    }

    addLane(from, to, mode = 'air', { emphasis = false } = {}) {
        const start = toVector(from.lat, from.lon, RADIUS);
        const end = toVector(to.lat, to.lon, RADIUS);
        const distance = start.distanceTo(end);
        const altitude = mode === 'sea' || mode === 'road' ? 0.06 + distance * 0.08 : 0.15 + distance * 0.35;
        const mid = start.clone().add(end).multiplyScalar(0.5).normalize().multiplyScalar(RADIUS + altitude);
        const curve = new QuadraticBezierCurve3(start, mid, end);
        const points = curve.getPoints(80);

        const color = new Color(MODE_COLORS[emphasis ? 'route' : mode] || MODE_COLORS.air);
        const line = new Line(
            new BufferGeometry().setFromPoints(points),
            new LineBasicMaterial({ color, transparent: true, opacity: emphasis ? 0.95 : 0.35, depthWrite: false }),
        );
        this.root.add(line);

        const head = new Mesh(new SphereGeometry(emphasis ? 0.016 : 0.009, 12, 12), new MeshBasicMaterial({ color }));
        this.root.add(head);

        const lane = { line, head, curve, mode, t: Math.random(), speed: (mode === 'sea' ? 0.05 : mode === 'road' ? 0.08 : 0.12) * (emphasis ? 0.6 : 1) };
        this.lanes.push(lane);
        return lane;
    }

    clearRoute() {
        if (!this.route) {
            return;
        }
        this.route.objects.forEach((o) => {
            this.root.remove(o);
            o.geometry?.dispose();
            o.material?.dispose();
        });
        this.lanes = this.lanes.filter((l) => !this.route.objects.includes(l.line));
        this.markers = this.markers.filter((m) => !this.route.objects.includes(m));
        this.route = null;
    }

    /** Draws a shipment route and current position (FR-15). */
    showRoute(origin, destination, current = null) {
        this.clearRoute();
        const lane = this.addLane(origin, destination, 'route', { emphasis: true });
        const a = this.addHub(origin.lat, origin.lon, '#7fb3e0');
        const b = this.addHub(destination.lat, destination.lon, '#e2703a');
        const objects = [lane.line, lane.head, a, b];
        if (current) {
            const c = this.addHub(current.lat, current.lon, '#f5bb9a');
            objects.push(c);
        }
        this.route = { objects };
        const focus = current || { lat: (origin.lat + destination.lat) / 2, lon: (origin.lon + destination.lon) / 2 };
        this.focus(focus.lat, focus.lon);
    }

    /** Places (or moves) the user's pin (FR-41, FR-42). */
    setPin(lat, lon) {
        if (this.pin) {
            this.root.remove(this.pin);
            this.markers = this.markers.filter((m) => m !== this.pin);
        }
        this.pin = this.addHub(lat, lon, '#ffffff');
        this.focus(lat, lon);
    }

    focus(lat, lon) {
        let y = -(lon + 90) * DEG;
        const current = this.root.rotation.y;
        // Take the shortest way around.
        while (y - current > Math.PI) {
            y -= Math.PI * 2;
        }
        while (y - current < -Math.PI) {
            y += Math.PI * 2;
        }
        this.targetRotation = { x: Math.max(-60, Math.min(60, lat)) * DEG, y };
        this.autoRotatePausedUntil = performance.now() + 6000;
    }

    /**
     * Closest distance at which the atmosphere shell still fits inside the frame.
     * Without this the glow is cut off by the canvas edges, which reads as a
     * rectangular frame around the globe.
     */
    fitDistance() {
        const halfFov = (this.camera.fov * DEG) / 2;
        const vertical = ATMOSPHERE_RADIUS / Math.tan(halfFov);
        const horizontal = vertical / Math.max(this.camera.aspect, 0.2);

        return Math.max(vertical, horizontal) * 1.04;
    }

    zoom(delta) {
        const min = this.fitDistance();
        this.targetDistance = Math.max(min, Math.min(min + 1.6, this.targetDistance + delta));
    }

    bindEvents() {
        const el = this.renderer.domElement;
        let dragging = false;
        let moved = 0;
        let last = { x: 0, y: 0 };

        if (this.options.interactive) {
            el.addEventListener('pointerdown', (e) => {
                dragging = true;
                moved = 0;
                last = { x: e.clientX, y: e.clientY };
                el.setPointerCapture(e.pointerId);
                this.targetRotation = null;
            });
            el.addEventListener('pointermove', (e) => {
                if (!dragging) {
                    return;
                }
                const dx = e.clientX - last.x;
                const dy = e.clientY - last.y;
                moved += Math.abs(dx) + Math.abs(dy);
                last = { x: e.clientX, y: e.clientY };
                this.velocity.y = dx * 0.005;
                this.velocity.x = dy * 0.005;
                this.root.rotation.y += this.velocity.y;
                this.root.rotation.x = Math.max(-1.1, Math.min(1.1, this.root.rotation.x + this.velocity.x));
                this.autoRotatePausedUntil = performance.now() + 5000;
            });
            el.addEventListener('pointerup', (e) => {
                dragging = false;
                if (moved < 6) {
                    this.pick(e);
                }
            });
            el.addEventListener('pointercancel', () => {
                dragging = false;
            });
        }

        this.resizeObserver = new ResizeObserver(() => this.resize());
        this.resizeObserver.observe(this.container);

        // Pause rendering when off screen or in a background tab (section 11.1).
        this.visibilityObserver = new IntersectionObserver((entries) => {
            this.visible = entries[0]?.isIntersecting ?? true;
            this.visible ? this.start() : this.stop();
        });
        this.visibilityObserver.observe(this.container);
        document.addEventListener('visibilitychange', () => (document.hidden ? this.stop() : this.visible && this.start()));
    }

    pick(event) {
        if (!this.onPick) {
            return;
        }
        const rect = this.renderer.domElement.getBoundingClientRect();
        const pointer = new Vector2(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1);
        const raycaster = new Raycaster();
        raycaster.setFromCamera(pointer, this.camera);
        const hit = raycaster.intersectObject(this.sphere, false)[0];
        if (!hit) {
            return;
        }
        const local = this.root.worldToLocal(hit.point.clone());
        const { lat, lon } = toLatLon(local);
        this.onPick({ lat: Math.round(lat * 10000) / 10000, lon: Math.round(lon * 10000) / 10000 });
    }

    resize() {
        const width = this.container.clientWidth || 1;
        const height = this.container.clientHeight || 1;
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height, false);

        // Keep the globe (and its atmosphere) inside the frame at every size.
        const min = this.fitDistance();
        if (this.distance < min) {
            this.distance = min;
        }
        if (this.targetDistance < min) {
            this.targetDistance = min;
        }
        this.minDistance = min;
        this.maxDistance = min + 1.6;
    }

    start() {
        if (this.running) {
            return;
        }
        this.running = true;
        this.lastTime = performance.now();
        const loop = (time) => {
            if (!this.running) {
                return;
            }
            this.frame(time);
            this.raf = requestAnimationFrame(loop);
        };
        this.raf = requestAnimationFrame(loop);
    }

    stop() {
        this.running = false;
        cancelAnimationFrame(this.raf);
    }

    frame(time) {
        const dt = Math.min(0.05, (time - this.lastTime) / 1000);
        this.lastTime = time;

        if (this.targetRotation) {
            this.root.rotation.x += (this.targetRotation.x - this.root.rotation.x) * 0.06;
            this.root.rotation.y += (this.targetRotation.y - this.root.rotation.y) * 0.06;
            if (Math.abs(this.targetRotation.y - this.root.rotation.y) < 0.001) {
                this.targetRotation = null;
            }
        } else {
            this.velocity.x *= 0.92;
            this.velocity.y *= 0.92;
            const autoRotate = this.options.autoRotate && !this.reducedMotion && !(this.autoRotatePausedUntil > time);
            if (autoRotate) {
                this.root.rotation.y += dt * 0.06;
            }
        }

        this.distance += (this.targetDistance - this.distance) * 0.1;
        this.camera.position.z = this.distance;

        if (!this.reducedMotion) {
            this.lanes.forEach((lane) => {
                lane.t = (lane.t + dt * lane.speed) % 1;
                lane.head.position.copy(lane.curve.getPoint(lane.t));
            });
            this.markers.forEach((marker) => {
                const s = 1 + 0.5 * (0.5 + 0.5 * Math.sin(time / 600 + marker.userData.phase));
                marker.userData.ring.scale.setScalar(s);
                marker.userData.ring.material.opacity = 0.9 - (s - 1) * 1.2;
            });
        } else {
            this.lanes.forEach((lane) => lane.head.position.copy(lane.curve.getPoint(0.5)));
        }

        this.renderer.render(this.scene, this.camera);
    }

    destroy() {
        this.stop();
        this.resizeObserver?.disconnect();
        this.visibilityObserver?.disconnect();
        this.renderer.dispose();
        this.renderer.domElement.remove();
    }
}

export function webglAvailable() {
    try {
        const canvas = document.createElement('canvas');
        return !!(window.WebGLRenderingContext && (canvas.getContext('webgl2') || canvas.getContext('webgl')));
    } catch {
        return false;
    }
}
