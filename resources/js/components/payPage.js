import { api, formatDateTime, newIdempotencyKey, readJson, t } from '../lib/api';

/**
 * Pay page (section 5.3). Lists methods by name only; account details are fetched from the
 * server only after the customer selects a method (FR-54). Proof upload with amount, payer,
 * date and transaction id (FR-60 to FR-66).
 */
export default () => ({
    methods: [],
    selected: null,
    details: null,
    selecting: false,
    error: '',
    copied: '',
    history: [],
    form: { amount_paid: '', payer_name: '', paid_on: '', transaction_id: '', note: '' },
    giftCard: { brand: '', code: '', pin: '', amount: '' },
    files: [],
    fileError: '',
    uploading: false,
    uploaded: false,
    uploadErrors: {},
    idempotencyKey: null,
    dragging: false,

    init() {
        this.methods = readJson('payment-methods', []) || [];
        this.selected = this.$root.dataset.selected || null;
        this.form.paid_on = new Date().toISOString().slice(0, 10);
        if (this.$root.dataset.payable === 'true' && this.selected) {
            this.loadCurrent();
        }
        this.loadHistory();
    },

    async loadCurrent() {
        try {
            const data = await api('/orders/' + this.$root.dataset.order + '/payment-method');
            this.details = data.data;
            if (this.details) {
                this.form.amount_paid = (this.details.amount_due.amount / (['XAF', 'XOF'].includes(this.details.amount_due.currency) ? 1 : 100)).toString();
            }
        } catch {
            this.details = null;
        }
    },

    async loadHistory() {
        try {
            const data = await api('/orders/' + this.$root.dataset.order + '/proofs');
            this.history = data.data;
        } catch {
            this.history = [];
        }
    },

    async select(id) {
        if (this.selecting || this.$root.dataset.payable !== 'true') {
            return;
        }
        this.selecting = true;
        this.error = '';
        try {
            this.details = await api('/orders/' + this.$root.dataset.order + '/payment-method', { method: 'POST', body: { method_id: id } });
            this.selected = id;
            this.form.amount_paid = (this.details.amount_due.amount / (['XAF', 'XOF'].includes(this.details.amount_due.currency) ? 1 : 100)).toString();
            this.$nextTick(() => this.$refs.details?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        } catch (e) {
            this.error = e.message;
        } finally {
            this.selecting = false;
        }
    },

    /** Only https links are clickable; anything else (javascript:, data:) becomes inert. */
    safeLink(value) {
        return typeof value === 'string' && /^https:\/\//i.test(value) ? value : '#';
    },

    /** QR images are rendered by the server as SVG data URIs; nothing else is loaded. */
    isQrImage(value) {
        return typeof value === 'string' && value.startsWith('data:image/svg+xml;base64,');
    },

    isGiftCard() {
        return this.details?.method?.kind === 'gift_card';
    },

    async copy(value) {
        try {
            await navigator.clipboard.writeText(value);
            this.copied = value;
            setTimeout(() => (this.copied = ''), 1800);
        } catch {
            this.copied = '';
        }
    },

    pickFiles(event) {
        this.addFiles(Array.from(event.target.files || []));
        event.target.value = '';
    },

    drop(event) {
        this.dragging = false;
        this.addFiles(Array.from(event.dataTransfer?.files || []));
    },

    addFiles(list) {
        this.fileError = '';
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'application/pdf'];
        for (const file of list) {
            if (this.files.length >= 3) {
                this.fileError = t('max_files');
                break;
            }
            if (file.size > 8 * 1024 * 1024) {
                this.fileError = t('file_too_large');
                continue;
            }
            if (file.type && !allowed.includes(file.type)) {
                this.fileError = t('file_type');
                continue;
            }
            this.files.push({ file, name: file.name, size: (file.size / 1024 / 1024).toFixed(2) + ' MB' });
        }
    },

    removeFile(index) {
        this.files.splice(index, 1);
    },

    async submitProof() {
        if (this.files.length === 0) {
            this.fileError = t('add_proof');
            return;
        }
        this.uploading = true;
        this.uploadErrors = {};
        this.error = '';
        this.idempotencyKey ??= newIdempotencyKey();

        const body = new FormData();
        this.files.forEach((f) => body.append('files[]', f.file));
        Object.entries(this.form).forEach(([k, v]) => v !== '' && body.append(k, v));
        if (this.isGiftCard()) {
            Object.entries(this.giftCard).forEach(([k, v]) => v !== '' && body.append('gift_card[' + k + ']', v));
        }

        try {
            await api('/orders/' + this.$root.dataset.order + '/proofs', { method: 'POST', body, idempotencyKey: this.idempotencyKey });
            this.uploaded = true;
            setTimeout(() => window.location.reload(), 1800);
        } catch (e) {
            this.uploadErrors = e.fields || {};
            this.error = e.message;
            this.idempotencyKey = null;
        } finally {
            this.uploading = false;
        }
    },

    uploadError(key) {
        const direct = this.uploadErrors[key]?.[0];
        if (direct) {
            return direct;
        }
        const nested = Object.entries(this.uploadErrors).find(([k]) => k.startsWith(key + '.'));
        return nested ? nested[1][0] : '';
    },

    when(iso) {
        return formatDateTime(iso);
    },
});
