document.addEventListener('alpine:init', () => {
    Alpine.data('importPage', () => ({

        // Wizard-Schritt: 1 | 2 | 3 | 4
        schritt: 1,

        // Schritt 1: Datei
        datei: null,
        dateiName: '',
        analyzing: false,
        analyzeError: null,

        // Schritt 2: Mapping
        analyse: null,      // { zeilenanzahl, spaltenanzahl, vorschau, felder }
        mapping: {},        // { feldname: 'A', ... }
        startzeile: 2,
        previewError: null,
        previewing: false,

        // Schritt 3: Vorschau
        vorschau: null,
        vorschauLimit: 50,

        // Schritt 4: Ergebnis
        executing: false,
        executeError: null,
        ergebnis: null,

        // ----- Schritt 1: Datei -------------------------------------------

        onFileChange(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.datei      = file;
            this.dateiName  = file.name;
            this.analyzeError = null;
        },

        async doAnalyze() {
            if (!this.datei) return;
            this.analyzing    = true;
            this.analyzeError = null;
            try {
                const form = new FormData();
                form.append('datei', this.datei);
                this.analyse = await api.upload('/import/analyze', form);
                this._autoMap();
                this.schritt = 2;
            } catch (e) {
                this.analyzeError = e.message || 'Analyse fehlgeschlagen.';
            } finally {
                this.analyzing = false;
            }
        },

        // Versucht Spalten anhand der Kopfzeile automatisch zuzuordnen
        _autoMap() {
            this.mapping = {};
            if (!this.analyse?.vorschau?.length) return;
            const headerRow = this.analyse.vorschau[0].spalten;
            const allFelder = [
                ...this.analyse.felder.person,
                ...this.analyse.felder.verlegeort,
                ...this.analyse.felder.stein,
            ];
            for (const [col, val] of Object.entries(headerRow)) {
                if (!val) continue;
                const norm = val.trim().toLowerCase()
                    .replace(/\s+/g, '_')
                    .replace(/ä/g, 'ae').replace(/ö/g, 'oe')
                    .replace(/ü/g, 'ue').replace(/ß/g, 'ss');
                const match = allFelder.find(f => f === norm || f === val.trim().toLowerCase());
                if (match && !this.mapping[match]) {
                    this.mapping[match] = col;
                }
            }
        },

        // ----- Schritt 2: Mapping -----------------------------------------

        // Spalten als Array für Dropdowns: [{ col: 'A', label: 'A – Nachname' }, ...]
        get spaltenOptionen() {
            if (!this.analyse?.vorschau?.length) return [];
            return Object.entries(this.analyse.vorschau[0].spalten).map(([col, val]) => ({
                col,
                label: val ? `${col} – ${val}` : col,
            }));
        },

        // Vorschau-Spaltenbuchstaben als Array für x-for
        get vorschauSpalten() {
            if (!this.analyse?.vorschau?.length) return [];
            return Object.keys(this.analyse.vorschau[0].spalten);
        },

        // Feldgruppen für das Mapping-Formular
        get feldGruppen() {
            if (!this.analyse) return [];
            return [
                { gruppe: 'Person',       felder: this.analyse.felder.person },
                { gruppe: 'Verlegeort',   felder: this.analyse.felder.verlegeort },
                { gruppe: 'Stolperstein', felder: this.analyse.felder.stein },
            ];
        },

        async doPreview() {
            const mapped = Object.values(this.mapping).filter(Boolean);
            if (!mapped.length) {
                this.previewError = 'Bitte mindestens ein Feld zuordnen.';
                return;
            }
            this.previewing   = true;
            this.previewError = null;
            try {
                const form = new FormData();
                form.append('datei',     this.datei);
                form.append('mapping',   JSON.stringify(this.mapping));
                form.append('startzeile', String(this.startzeile));
                this.vorschau = await api.upload('/import/preview', form);
                this.schritt  = 3;
            } catch (e) {
                this.previewError = e.message || 'Vorschau fehlgeschlagen.';
            } finally {
                this.previewing = false;
            }
        },

        // ----- Schritt 3 → 4: Import ausführen ---------------------------

        async doExecute() {
            this.executing    = true;
            this.executeError = null;
            try {
                const form = new FormData();
                form.append('datei',     this.datei);
                form.append('mapping',   JSON.stringify(this.mapping));
                form.append('startzeile', String(this.startzeile));
                this.ergebnis = await api.upload('/import/execute', form);
                this.schritt  = 4;
                Alpine.store('notify').success(
                    `Import abgeschlossen: ${this.ergebnis.neue_steine} Stolperstein(e) importiert.`
                );
            } catch (e) {
                this.executeError = e.message || 'Import fehlgeschlagen.';
            } finally {
                this.executing = false;
            }
        },

        // ----- Hilfsmethoden ----------------------------------------------

        reset() {
            this.schritt      = 1;
            this.datei        = null;
            this.dateiName    = '';
            this.analyse      = null;
            this.mapping      = {};
            this.startzeile   = 2;
            this.vorschau     = null;
            this.vorschauLimit = 50;
            this.ergebnis     = null;
            this.analyzeError = null;
            this.previewError = null;
            this.executeError = null;
        },

        feldLabel(feld) {
            const labels = {
                nachname:             'Nachname *',
                vorname:              'Vorname',
                geburtsname:          'Geburtsname',
                geburtsdatum:         'Geburtsdatum',
                sterbedatum:          'Sterbedatum',
                biografie_kurz:       'Biografie (kurz)',
                wikidata_id_person:   'Wikidata-ID (Person)',
                strasse_aktuell:      'Straße (aktuell) *',
                hausnummer_aktuell:   'Hausnummer',
                stadtteil:            'Stadtteil',
                plz_aktuell:          'PLZ',
                wikidata_id_strasse:   'Wikidata-ID (Straße)',
                wikidata_id_stadtteil: 'Wikidata-ID (Stadtteil)',
                lat:                  'Breitengrad (lat)',
                lon:                  'Längengrad (lon)',
                beschreibung:         'Beschreibung',
                bemerkung_historisch: 'Historische Bemerkung',
                grid_n:               'Grid N',
                grid_m:               'Grid M',
                verlegedatum:         'Verlegedatum',
                inschrift:            'Inschrift',
                wikidata_id_stein:    'Wikidata-ID (Stein)',
                osm_id:               'OSM-ID',
                pos_x:                'Position X',
                pos_y:                'Position Y',
                lat_override:         'Breitengrad (lat)',
                lon_override:         'Längengrad (lon)',
                wikimedia_commons:    'Wikimedia Commons (Dateiname)',
                foto_lizenz_autor:    'Foto-Urheber',
                foto_lizenz_name:     'Foto-Lizenz',
                foto_lizenz_url:      'Foto-Lizenz-URL',
                status:               'Status',
                zustand:              'Zustand',
            };
            return labels[feld] ?? feld;
        },

        personStatusLabel(s) {
            return { neu: 'neu', vorhanden: 'vorhanden', neu_in_import: 'neu (im Import)' }[s] ?? s;
        },

        ortStatusLabel(s) {
            return { neu: 'neu', vorhanden: 'vorhanden', neu_in_import: 'neu (im Import)' }[s] ?? s;
        },

        get importMoeglich() {
            return this.vorschau && (this.vorschau.gesamt - this.vorschau.fehler) > 0;
        },
    }));
});
