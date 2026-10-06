import { readJson } from '../lib/api';

const POSTERS = {
    air: '/images/freight-air.jpg',
    sea: '/images/freight-sea.jpg',
    road: '/images/freight-road.jpg',
};
const SCENES = ['air', 'sea', 'road'];
const ROTATION_MS = 6800;

/**
 * The home hero moves slowly through air, sea and road photography. Optional
 * CMS video still takes priority when supplied. Autoplay pauses for reduced
 * motion, a hidden tab, hover and keyboard focus.
 */
export default () => ({
    scene: 'air',
    media: {},
    videoAllowed: false,
    hasVideo: false,
    rotationTimer: null,
    rotationPaused: false,
    visibilityHandler: null,

    init() {
        this.media = readJson('hero-media', {}) || {};
        this.scene = this.$root.dataset.scene || 'air';

        const connection = navigator.connection || {};
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.videoAllowed = !reduced && !connection.saveData && window.innerWidth >= 768;

        const attach = () => this.loadVideo();
        if (document.readyState === 'complete') {
            setTimeout(attach, 300);
        } else {
            window.addEventListener('load', () => setTimeout(attach, 300), { once: true });
        }

        if (!reduced) {
            this.startRotation();
        }
        this.visibilityHandler = () => {
            if (document.hidden) {
                this.stopRotation();
            } else {
                this.startRotation();
            }
        };
        document.addEventListener('visibilitychange', this.visibilityHandler);
    },

    destroy() {
        this.stopRotation();
        if (this.visibilityHandler) {
            document.removeEventListener('visibilitychange', this.visibilityHandler);
        }
    },

    poster(scene = this.scene) {
        return this.media[scene]?.poster || POSTERS[scene] || POSTERS.air;
    },

    setScene(scene) {
        if (!SCENES.includes(scene)) return;

        this.scene = scene;
        this.loadVideo();
        this.resetRotation();
        window.dispatchEvent(new CustomEvent('hero:scene', { detail: { scene } }));
    },

    nextScene() {
        const current = SCENES.indexOf(this.scene);
        this.setScene(SCENES[(current + 1) % SCENES.length]);
    },

    startRotation() {
        this.stopRotation();
        if (this.rotationPaused || document.hidden || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        this.rotationTimer = window.setInterval(() => this.nextScene(), ROTATION_MS);
    },

    stopRotation() {
        if (this.rotationTimer) {
            window.clearInterval(this.rotationTimer);
            this.rotationTimer = null;
        }
    },

    resetRotation() {
        this.stopRotation();
        this.startRotation();
    },

    pauseRotation() {
        this.rotationPaused = true;
        this.stopRotation();
    },

    resumeRotation() {
        if (this.$root.matches(':hover') || this.$root.contains(document.activeElement)) return;
        this.rotationPaused = false;
        this.startRotation();
    },

    loadVideo() {
        const video = this.$refs.video;
        const sources = this.media[this.scene] || {};
        this.hasVideo = false;
        if (!video || !this.videoAllowed || (!sources.mp4 && !sources.webm)) {
            return;
        }

        video.replaceChildren();
        [['webm', 'video/webm'], ['mp4', 'video/mp4']].forEach(([key, type]) => {
            if (sources[key]) {
                const source = document.createElement('source');
                source.src = sources[key];
                source.type = type;
                video.appendChild(source);
            }
        });
        video.poster = sources.poster || this.poster();
        video.load();
        video.play().then(() => {
            this.hasVideo = true;
        }).catch(() => {
            this.hasVideo = false;
        });
    },
});
