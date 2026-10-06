/**
 * Live countdown to a deadline (payment expiry).
 */
export default () => ({
    remaining: '',
    urgent: false,
    expired: false,
    timer: null,

    init() {
        this.tick();
        this.timer = setInterval(() => this.tick(), 1000);
    },

    destroy() {
        clearInterval(this.timer);
    },

    tick() {
        const end = new Date(this.$root.dataset.until).getTime();
        const diff = Math.max(0, end - Date.now());
        this.expired = diff === 0;
        this.urgent = diff < 2 * 3600 * 1000;
        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        this.remaining = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    },
});
