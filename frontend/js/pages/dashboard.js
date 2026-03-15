document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardPage', () => ({
        stats: null,
        loading: false,
        error: null,

        async init() {
            this.loading = true;
            this.error   = null;

            try {
                const [personen, steine, orte, dokumente, staedte, stadtteile, strassen, lokationen, plz] = await Promise.all([
                    api.get('/personen'),
                    api.get('/stolpersteine'),
                    api.get('/verlegeorte'),
                    api.get('/dokumente'),
                    api.get('/adressen/staedte'),
                    api.get('/adressen/alle-stadtteile'),
                    api.get('/adressen/alle-strassen'),
                    api.get('/adressen/alle-lokationen'),
                    api.get('/adressen/alle-plz'),
                ]);

                const count = arr => Array.isArray(arr) ? arr.length : '–';

                this.stats = {
                    personen:   count(personen),
                    steine:     count(steine),
                    orte:       count(orte),
                    dokumente:  count(dokumente),
                    staedte:    count(staedte),
                    stadtteile: count(stadtteile),
                    strassen:   count(strassen),
                    lokationen: count(lokationen),
                    plz:        count(plz),
                };
            } catch (e) {
                this.error = e.message || 'Daten konnten nicht geladen werden.';
            } finally {
                this.loading = false;
            }
        },
    }));
});
