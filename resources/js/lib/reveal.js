/**
 * Scroll reveal (FR-06). Sections are visible without JavaScript; this only animates them in.
 */
export function initReveal() {
    const items = document.querySelectorAll('[data-reveal]');
    if (!('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.08 },
    );

    items.forEach((el) => {
        const delay = el.getAttribute('data-reveal');
        if (delay) {
            el.style.setProperty('--reveal-delay', delay + 'ms');
        }
        observer.observe(el);
    });
}
