document.addEventListener('alpine:init', () => {
    Alpine.data('detailPage', () => ({
        loading: true,
        error: null,
        stein: null,
        _watchId: null,

        async init() {
            // Initiales Laden
            await this._laden(Alpine.store('router').id);

            // Bei Hash-Wechsel neu laden (z.B. Popup-Link auf Karte)
            this._watchId = this.$watch(() => Alpine.store('router').id, id => {
                if (Alpine.store('router').current === 'stein') this._laden(id);
            });
        },

        async _laden(id) {
            if (!id) {
                this.error   = 'Keine ID angegeben.';
                this.loading = false;
                return;
            }
            this.loading = true;
            this.error   = null;
            this.stein   = null;
            try {
                this.stein = await api.get(`/public/stolpersteine/${id}`);
            } catch (e) {
                this.error = e.message || 'Person konnte nicht geladen werden.';
            } finally {
                this.loading = false;
            }
        },

        zurueck() {
            Alpine.store('router').go('liste');
        },

        get name() {
            if (!this.stein) return '';
            const teile = [this.stein.nachname, this.stein.vorname].filter(Boolean);
            if (this.stein.geburtsname) teile.push(`geb. ${this.stein.geburtsname}`);
            return teile.join(', ');
        },

        get daten() {
            if (!this.stein) return '';
            const teile = [];
            if (this.stein.geburtsdatum) teile.push(`* ${this.stein.geburtsdatum}`);
            if (this.stein.sterbedatum)  teile.push(`† ${this.stein.sterbedatum}`);
            return teile.join('  ');
        },

        get adresse() {
            if (!this.stein) return '';
            const str = [this.stein.strasse, this.stein.hausnummer].filter(Boolean).join('\u00a0');
            return [str, this.stein.stadtteil, 'Magdeburg'].filter(Boolean).join(', ');
        },

        get osmLink() {
            if (!this.stein?.lat || !this.stein?.lon) return null;
            return `https://www.openstreetmap.org/?mlat=${this.stein.lat}&mlon=${this.stein.lon}&zoom=17`;
        },

        get fotoSrc() {
            if (!this.stein) return null;
            if (this.stein.foto_pfad) return `/api/uploads/${this.stein.foto_pfad}`;
            if (this.stein.wikimedia_commons) {
                // Wikimedia Commons Thumbnail-URL aufbauen
                const name = decodeURIComponent(this.stein.wikimedia_commons);
                const enc  = encodeURIComponent(name.replace(/ /g, '_'));
                return `https://commons.wikimedia.org/wiki/Special:FilePath/${enc}?width=400`;
            }
            return null;
        },

        get dokGroesse() {
            if (!this.stein?.biografie_dok_groesse_bytes) return null;
            const kb = Math.round(this.stein.biografie_dok_groesse_bytes / 1024);
            return kb >= 1024
                ? `${(kb / 1024).toFixed(1)}\u00a0MB`
                : `${kb}\u00a0KB`;
        },
    }));
});
