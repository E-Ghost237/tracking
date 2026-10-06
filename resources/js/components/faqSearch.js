export default () => ({
    query: '',

    matches(text) {
        const q = this.query.trim().toLowerCase();
        return q === '' || (text || '').toLowerCase().includes(q);
    },
});
