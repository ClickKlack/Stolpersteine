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
    // -----------------------------------------------------------------------
    // navFilter-Store – seitenübergreifende Navigation mit Filter/Edit-Zielen
    // -----------------------------------------------------------------------
    Alpine.store('navFilter', {
        page:       null,
        filter:     {},
        openEditId: null,

        consume() {
            const r = { filter: this.filter, openEditId: this.openEditId };
            this.page       = null;
            this.filter     = {};
            this.openEditId = null;
            return r;
        },
    });

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
        sub: null,

        // Alle bekannten Seiten
        pages: ['dashboard', 'personen', 'stolpersteine', 'verlegeorte', 'dokumente', 'import', 'export', 'adressen', 'benutzerverwaltung', 'profil'],

        init() {
            this._sync();
            window.addEventListener('hashchange', () => this._sync());
        },

        _sync() {
            const raw  = location.hash.replace(/^#\/?/, '') || 'dashboard';
            const [page, sub] = raw.split('/');
            this.current = this.pages.includes(page) ? page : 'dashboard';
            this.sub     = sub || null;
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
    // Globale Hilfsfunktion – von überall in Templates aufrufbar (kein $root nötig)
    window.navigateTo = function(page, opts = {}) {
        const nf    = Alpine.store('navFilter');
        nf.page       = page;
        nf.filter     = opts.filter     || {};
        nf.openEditId = opts.openEditId || null;
        Alpine.store('router').go(page);
    };

    Alpine.data('app', () => ({
        get auth()   { return Alpine.store('auth'); },
        get router() { return Alpine.store('router'); },
        get notify() { return Alpine.store('notify'); },
        get config() { return Alpine.store('config'); },

        async init() {
            // Passwort-Reset-Links vor Router-Initialisierung erkennen,
            // damit der Hash nicht überschrieben wird bevor loginPage ihn liest
            const isResetLink = /^#passwort-reset\?token=/i.test(location.hash);

            // Zentraler Handler für abgelaufene Sessions:
            // Jeder API-Aufruf mit 401 (außer Login/Passwort) löst diesen aus.
            window.addEventListener('auth:session-expired', () => {
                Alpine.store('auth').user = null;
                Alpine.store('router').go('login');
            });

            Alpine.store('router').init();
            await Alpine.store('auth').check();

            if (isResetLink && Alpine.store('auth').user) {
                // Aktive Session beenden, damit die Login-Sektion sichtbar wird
                // und loginPage.init() den Reset-Token verarbeiten kann
                try { await api.post('/auth/logout'); } catch (_) {}
                Alpine.store('auth').user = null;
            }

            if (!Alpine.store('auth').user) {
                Alpine.store('router').go('login');
            } else {
                await Alpine.store('config').load();
            }
        },
    }));
});
