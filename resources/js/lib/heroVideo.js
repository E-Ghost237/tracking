import { prefersReducedMotion } from './api';

/**
 * Hero clip: never autoplay.
 *
 * The poster is the first paint, so the video stays at `preload="none"` until we
 * know the visitor welcomes motion. That keeps the clip off the critical path on
 * mobile connections and leaves the page completely still for anyone who has
 * asked for reduced motion — the same rule the globe and the reveal animations
 * follow.
 */
export function initHeroVideo() {
    const video = document.querySelector('video[data-hero-video]');
    if (!video || prefersReducedMotion()) {
        return;
    }

    // A slow or metered connection gets the still photograph instead.
    const connection = navigator.connection || navigator.webkitConnection;
    if (connection?.saveData || /^(slow-)?2g$/.test(connection?.effectiveType || '')) {
        return;
    }

    video.preload = 'auto';
    const play = () => video.play().catch(() => {});
    video.readyState >= 2 ? play() : video.addEventListener('loadeddata', play, { once: true });
}
