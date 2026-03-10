document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardPage', () => ({
        stats: null,
        loading: false,
        error: null,

        async init() {
            this.loading = true;
            this.error   = null;

            try {
                const [personen, steine, orte] = await Promise.all([
                    api.get('/personen'),
                    api.get('/stolpersteine'),
                    api.get('/verlegeorte'),
                ]);

                this.stats = {
                    personen: Array.isArray(personen) ? personen.length : '–',
                    steine:   Array.isArray(steine)   ? steine.length   : '–',
                    orte:     Array.isArray(orte)      ? orte.length     : '–',
                };
            } catch (e) {
                this.error = e.message || 'Daten konnten nicht geladen werden.';
            } finally {
                this.loading = false;
            }
        },
    }));
});
