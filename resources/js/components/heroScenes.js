import { readJson } from '../lib/api';

/**
 * Hero scene switcher (FR-01, FR-02, FR-05). The video is attached only after first paint,
 * and never on small screens, with data saver, or with reduced motion: the poster stays.
 */
export default () => ({
    scene: 'air',
    media: {},
    videoAllowed: false,
    hasVideo: false,

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
    },

    poster() {
        return this.media[this.scene]?.poster || '';
    },

    setScene(scene) {
        this.scene = scene;
        this.loadVideo();
        window.dispatchEvent(new CustomEvent('hero:scene', { detail: { scene } }));
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
        video.poster = sources.poster || '';
        video.load();
        video.play().then(() => {
            this.hasVideo = true;
        }).catch(() => {
            this.hasVideo = false;
        });
    },
});
