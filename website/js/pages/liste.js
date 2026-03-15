document.addEventListener('alpine:init', () => {
    Alpine.data('listePage', () => ({
        loading: true,
        error: null,
        _alle: [],
        gefiltert: [],
        suchbegriff: '',
        _suchTimer: null,
        seite: 1,
        proSeite: 30,

        async init() {
            this.loading = true;
            this.error   = null;
            try {
                this._alle = await api.get('/public/stolpersteine');
                this._filter();
            } catch (e) {
                this.error = e.message || 'Liste konnte nicht geladen werden.';
            } finally {
                this.loading = false;
            }
        },

        onSuche() {
            clearTimeout(this._suchTimer);
            this._suchTimer = setTimeout(() => {
                this.seite = 1;
                this._filter();
            }, 250);
        },

        _filter() {
            const q = this.suchbegriff.trim().toLowerCase();
            if (!q) {
                this.gefiltert = this._alle;
                return;
            }
            this.gefiltert = this._alle.filter(s => {
                const name    = [s.nachname, s.vorname, s.geburtsname].filter(Boolean).join(' ').toLowerCase();
                const adresse = [s.strasse, s.hausnummer, s.stadtteil].filter(Boolean).join(' ').toLowerCase();
                return name.includes(q) || adresse.includes(q);
            });
        },

        get seitenAnzahl() {
            return Math.max(1, Math.ceil(this.gefiltert.length / this.proSeite));
        },

        get aktuelleSeite() {
            const start = (this.seite - 1) * this.proSeite;
            return this.gefiltert.slice(start, start + this.proSeite);
        },

        vorige() {
            if (this.seite > 1) this.seite--;
        },

        naechste() {
            if (this.seite < this.seitenAnzahl) this.seite++;
        },

        oeffne(id) {
            Alpine.store('router').go('stein', id);
        },

        _formatName(s) {
            const teile = [s.nachname, s.vorname].filter(Boolean);
            if (s.geburtsname) teile.push(`geb. ${s.geburtsname}`);
            return teile.join(', ');
        },

        _formatAdresse(s) {
            const str = [s.strasse, s.hausnummer].filter(Boolean).join('\u00a0');
            return [str, s.stadtteil].filter(Boolean).join(' · ');
        },

        _formatDaten(s) {
            const teile = [];
            if (s.geburtsdatum) teile.push(`* ${s.geburtsdatum}`);
            if (s.sterbedatum)  teile.push(`† ${s.sterbedatum}`);
            return teile.join('  ');
        },
    }));
});
