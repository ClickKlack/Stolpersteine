document.addEventListener('alpine:init', () => {

    // -----------------------------------------------------------------------
    // Router-Store – Hash-basiertes Routing mit optionaler ID
    // Unterstützt: #karte  #liste  #stein/42
    // -----------------------------------------------------------------------
    Alpine.store('router', {
        current: 'karte',
        id: null,
        pages: ['karte', 'liste', 'stein'],

        init() {
            this._sync();
            window.addEventListener('hashchange', () => this._sync());
        },

        _sync() {
            const raw  = location.hash.replace(/^#\/?/, '') || 'karte';
            const [page, id] = raw.split('/');
            this.current = this.pages.includes(page) ? page : 'karte';
            this.id      = id || null;
        },

        go(page, id = null) {
            location.hash = id ? `${page}/${id}` : page;
        },

        is(page) {
            return this.current === page;
        },
    });

    // -----------------------------------------------------------------------
    // Statistiken-Store – beim Start laden
    // -----------------------------------------------------------------------
    Alpine.store('stats', {
        steine: null,
        personen: null,
        strassen: null,
        stadtteile: null,

        async load() {
            try {
                const d = await api.get('/public/statistiken');
                this.steine     = d.steine;
                this.personen   = d.personen;
                this.strassen   = d.strassen;
                this.stadtteile = d.stadtteile;
            } catch (_) { /* still show the rest of the page */ }
        },
    });

});

// Beim Laden initialisieren
document.addEventListener('DOMContentLoaded', () => {
    Alpine.store('stats').load();
});
