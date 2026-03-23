document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardPage', () => ({
        stats: null,
        loading: false,
        error: null,

        async init() {
            this.loading = true;
            this.error   = null;
            try {
                this.stats = await api.get('/dashboard/statistiken');
            } catch (e) {
                this.error = e.message || 'Statistiken konnten nicht geladen werden.';
            } finally {
                this.loading = false;
            }
        },

        // Prozentwert 0–100, NaN-sicher
        pct(n, total) {
            return total > 0 ? Math.round((n / total) * 100) : 0;
        },

        // Balken-Segmente aus einem { key: count }-Objekt erzeugen
        segmente(obj, total) {
            if (!obj) return [];
            return Object.entries(obj)
                .filter(([, n]) => n > 0)
                .map(([key, n]) => ({ key, n, pct: this.pct(n, total) }));
        },

        // Anzahl im Balken nur anzeigen wenn Segment breit genug (≥ 8%)
        segLabel(seg) {
            return seg.pct >= 8 ? String(seg.n) : '';
        },

        // Hintergrundfarbe je Status/Zustand/URL-Status
        statusColor(key) {
            const map = {
                ok:            '#dcfce7',
                freigegeben:   '#dcfce7',
                verfuegbar:    '#dcfce7',
                validierung:   '#fef3c7',
                neu:           '#dbeafe',
                archiviert:    '#f3f4f6',
                kein_stein:    '#fce7f3',
                fehlerhaft:    '#fee2e2',
                stein_fehlend: '#fee2e2',
                beschaedigt:   '#ffedd5',
                unleserlich:   '#fef9c3',
                ohne_url:      '#f3f4f6',
                ungeprueft:    '#fef3c7',
                umleitung:     '#dbeafe',
                fehler:        '#fee2e2',
            };
            return map[key] ?? '#e5e7eb';
        },

        // Lesbares Label je Schlüssel
        statusLabel(key) {
            const map = {
                ok:            'Ok',
                freigegeben:   'Freigegeben',
                verfuegbar:    'Verfügbar',
                validierung:   'Validierung',
                neu:           'Neu',
                archiviert:    'Archiviert',
                fehlerhaft:    'Fehlerhaft',
                stein_fehlend: 'Fehlend',
                kein_stein:    'Kein Stein',
                beschaedigt:   'Beschädigt',
                unleserlich:   'Unleserlich',
                ohne_url:      'Kein URL',
                ungeprueft:    'Ungeprüft',
                umleitung:     'Umleitung',
                fehler:        'Fehler',
            };
            return map[key] ?? key;
        },
    }));
});
