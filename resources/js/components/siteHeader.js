export default () => ({
    scrolled: false,
    open: false,

    init() {
        const update = () => {
            this.scrolled = window.scrollY > 12;
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('keydown', (e) => e.key === 'Escape' && this.close());
    },

    toggle() {
        this.open = !this.open;
        document.body.classList.toggle('overflow-hidden', this.open);
    },

    close() {
        this.open = false;
        document.body.classList.remove('overflow-hidden');
    },
});
