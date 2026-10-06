import { api, formatDateTime, readJson, t } from '../lib/api';

/**
 * Tracking page: single and multi-number lookups, timeline, route on the globe,
 * share link with QR code, and email alerts (FR-10 to FR-18).
 */
export default () => ({
    input: '',
    loading: false,
    results: [],
    error: '',
    captcha: null,
    captchaAnswer: '',
    subscribeFor: null,
    subscribeEmail: '',
    subscribeMessage: '',
    copied: '',

    init() {
        const initial = readJson('track-result', null);
        const numbers = this.$root.dataset.numbers || '';
        if (initial) {
            this.results = [initial];
            this.input = initial.number || initial.queried || '';
            this.$nextTick(() => this.autoShowRoute());
        } else if (numbers) {
            this.input = numbers.split(',').join('\n');
            this.lookup();
        } else if (this.$root.dataset.number) {
            this.input = this.$root.dataset.number;
        }
    },

    async lookup() {
        if (!this.input.trim()) {
            this.error = t('enter_number');
            return;
        }
        this.loading = true;
        this.error = '';
        const headers = {};
        if (this.captcha && this.captchaAnswer) {
            headers['X-Captcha-Id'] = this.captcha.id;
            headers['X-Captcha-Answer'] = this.captchaAnswer;
        }

        try {
            const data = await api('/track', { query: { numbers: this.input.replace(/\n/g, ',') }, headers });
            this.results = data.results;
            this.captcha = null;
            this.captchaAnswer = '';
            if (this.results.length === 1 && this.results[0].found) {
                window.history.replaceState({}, '', this.$root.dataset.trackUrl + '/' + encodeURIComponent(this.results[0].number));
            }
            this.$nextTick(() => this.autoShowRoute());
        } catch (e) {
            if (e.code === 'captcha_required') {
                await this.loadCaptcha();
                this.error = e.message;
            } else {
                this.error = e.message;
            }
        } finally {
            this.loading = false;
        }
    },

    async loadCaptcha() {
        try {
            const challenge = await api('/captcha');
            this.captcha = { id: challenge.id, src: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(challenge.svg) };
        } catch {
            this.captcha = null;
        }
    },

    autoShowRoute() {
        const first = this.results.find((r) => r.found && r.route?.origin && r.route?.destination);
        if (first) {
            this.showOnGlobe(first);
        }
    },

    showOnGlobe(result) {
        if (!result.route?.origin || !result.route?.destination) {
            return;
        }
        const detail = { origin: result.route.origin, destination: result.route.destination, current: result.route.current };
        window.__pendingGlobeRoute = detail;
        window.dispatchEvent(new CustomEvent('globe:route', { detail }));
        document.getElementById('route-globe')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    },

    progressValue(result) {
        return Math.round((result.progress || 0) * 100);
    },

    progressPercent(result) {
        return Math.round((result.progress || 0) * 100) + '%';
    },

    when(iso) {
        return formatDateTime(iso);
    },

    shareUrl(result) {
        return this.$root.dataset.trackUrl + '/' + encodeURIComponent(result.number);
    },

    qrUrl(result) {
        return '/api/v1/track/' + encodeURIComponent(result.number) + '/qr?locale=' + document.documentElement.lang;
    },

    async copyLink(result) {
        try {
            await navigator.clipboard.writeText(this.shareUrl(result));
            this.copied = result.number;
            setTimeout(() => (this.copied = ''), 2000);
        } catch {
            this.copied = '';
        }
    },

    openSubscribe(result) {
        this.subscribeFor = result.number;
        this.subscribeMessage = '';
    },

    async subscribe() {
        try {
            const data = await api('/tracking-subscriptions', { method: 'POST', body: { number: this.subscribeFor, email: this.subscribeEmail } });
            this.subscribeMessage = data.message;
            this.subscribeEmail = '';
        } catch (e) {
            this.subscribeMessage = e.fields?.email?.[0] || e.message;
        }
    },

    statusTone(status) {
        return {
            delivered: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            delayed: 'bg-amber-50 text-amber-800 ring-amber-600/20',
            at_customs: 'bg-amber-50 text-amber-800 ring-amber-600/20',
            returned: 'bg-red-50 text-red-700 ring-red-600/20',
            cancelled: 'bg-red-50 text-red-700 ring-red-600/20',
        }[status] || 'bg-sky-50 text-sky-800 ring-sky-600/20';
    },
});
