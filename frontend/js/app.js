/**
 * Alpine.js Stores & Haupt-App-Komponente
 *
 * Reihenfolge:
 *   1. Stores werden per Alpine.store() registriert
 *   2. Haupt-Komponente 'app' bindet sich an <body x-data="app">
 */

// ---------------------------------------------------------------------------
// Auth-Store – eingeloggter Benutzer
// ---------------------------------------------------------------------------
document.addEventListener('alpine:init', () => {
    Alpine.store('auth', {
        user: null,       // { benutzername, rolle } | null
        ready: false,     // true sobald /auth/me geprüft wurde

        async check() {
            try {
                this.user = await api.get('/auth/me');
            } catch {
                this.user = null;
            } finally {
                this.ready = true;
            }
        },

        get isAdmin() {
            return this.user?.rolle === 'admin';
        },

        async logout() {
            try {
                await api.post('/auth/logout');
            } finally {
                this.user = null;
                Alpine.store('router').go('login');
            }
        },
    });

    // -----------------------------------------------------------------------
    // Notification-Store – kurze Toast-Meldungen
    // -----------------------------------------------------------------------
    Alpine.store('notify', {
        message: null,
        type: 'info',     // 'info' | 'success' | 'error'
        _timer: null,

        show(message, type = 'info') {
            clearTimeout(this._timer);
            this.message = message;
            this.type    = type;
            this._timer  = setTimeout(() => { this.message = null; }, 4500);
        },

        success(message) { this.show(message, 'success'); },
        error(message)   { this.show(message, 'error'); },
        dismiss()        { clearTimeout(this._timer); this.message = null; },
    });

    // -----------------------------------------------------------------------
    // Router-Store – Hash-basiertes Routing
    // -----------------------------------------------------------------------
    Alpine.store('router', {
        current: 'dashboard',

        // Alle bekannten Seiten
        pages: ['dashboard', 'personen', 'stolpersteine', 'verlegeorte', 'dokumente', 'suche', 'import', 'export', 'adressen', 'benutzerverwaltung', 'profil'],

        init() {
            this._sync();
            window.addEventListener('hashchange', () => this._sync());
        },

        _sync() {
            const hash = location.hash.replace(/^#\/?/, '') || 'dashboard';
            this.current = this.pages.includes(hash) ? hash : 'dashboard';
        },

        go(page) {
            location.hash = page;
        },

        is(page) {
            return this.current === page;
        },
    });

    // -----------------------------------------------------------------------
    // Config-Store – Systemkonfiguration (stadt_name etc.)
    // -----------------------------------------------------------------------
    Alpine.store('config', {
        stadt_name: null,
        wikidata_city_id: null,

        async load() {
            try {
                const data = await api.get('/konfiguration');
                this.stadt_name      = data.stadt_name      ?? null;
                this.wikidata_city_id = data.wikidata_city_id ?? null;
            } catch {
                // Konfiguration optional
            }
        },
    });

    // -----------------------------------------------------------------------
    // Haupt-App-Komponente
    // -----------------------------------------------------------------------
    Alpine.data('app', () => ({
        get auth()   { return Alpine.store('auth'); },
        get router() { return Alpine.store('router'); },
        get notify() { return Alpine.store('notify'); },
        get config() { return Alpine.store('config'); },

        async init() {
            Alpine.store('router').init();
            await Alpine.store('auth').check();

            // Nicht-eingeloggte Benutzer zur Login-Seite
            if (!Alpine.store('auth').user) {
                Alpine.store('router').go('login');
            } else {
                await Alpine.store('config').load();
            }
        },
    }));
});
