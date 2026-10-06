/**
 * Header behaviour: hover/focus mega menus on desktop, a disclosure drawer on
 * mobile, and Escape to close everything. No scroll-driven styling — the bar is
 * solid on every page so it never flashes or shifts as the page moves.
 */
export default () => ({
    openMenu: '',
    mobileOpen: false,
    closeTimer: null,

    init() {
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeAll();
            }
        });

        document.addEventListener('click', (event) => {
            if (!this.$el.contains(event.target)) {
                this.closeAll();
            }
        });

        this.$watch('mobileOpen', (open) => {
            document.body.classList.toggle('overflow-hidden', open);
        });
    },

    isOpen(name) {
        return this.openMenu === name;
    },

    open(name) {
        window.clearTimeout(this.closeTimer);
        this.openMenu = name;
    },

    toggle(name) {
        window.clearTimeout(this.closeTimer);
        this.openMenu = this.openMenu === name ? '' : name;
    },

    /** A short delay keeps the menu open while the pointer crosses the gap. */
    scheduleClose(name) {
        window.clearTimeout(this.closeTimer);
        this.closeTimer = window.setTimeout(() => {
            if (this.openMenu === name) {
                this.openMenu = '';
            }
        }, 140);
    },

    toggleMobile() {
        this.mobileOpen = !this.mobileOpen;
        this.openMenu = '';
    },

    closeAll() {
        window.clearTimeout(this.closeTimer);
        this.openMenu = '';
        this.mobileOpen = false;

        /*
         * The desktop panels follow focus (:focus-within in app.css), so closing
         * them means giving the focus back. Escape therefore blurs whatever
         * inside the header holds it, which hides the panel again.
         */
        const active = document.activeElement;

        if (active && this.$el.contains(active) && typeof active.blur === 'function') {
            active.blur();
        }
    },
});
