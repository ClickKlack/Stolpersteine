document.addEventListener('alpine:init', () => {
    Alpine.data('importPage', () => ({

        // Wizard-Schritt: 1 | 2 | 3 | 4 | 5
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

        // Schritt 2: Biografisches Dokument – globale Einstellung
        // 'spalte' = aus Spalte lesen | 'ja' = alle Ja | 'nein' = alle Nein
        dokIstBiografieGlobal: 'spalte',

        // Schritt 3: Vorschau
        vorschau: null,
        vorschauLimit: 50,

        // Schritt 4: Ergebnis
        executing: false,
        executeError: null,
        ergebnis: null,

        // Schritt 5: URL-Prüfung
        urlValidierung: null,   // Array von Dokument-Objekten (erweitert mit url_status)
        urlPruefend: false,

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
                ...(this.analyse.felder.dokument ?? []),
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

        // Spalten als Array für Dropdowns: [{ col: 'A', label: 'A – Spaltenname (Beispielwert)' }, ...]
        get spaltenOptionen() {
            if (!this.analyse?.vorschau?.length) return [];
            const headerRow = this.analyse.vorschau[0].spalten;
            const dataRow   = this.analyse.vorschau[1]?.spalten ?? {};
            return Object.entries(headerRow).map(([col, header]) => {
                const sample = dataRow[col];
                let label = header ? `${col} – ${header}` : col;
                if (sample && sample !== header) {
                    const preview = sample.length > 30 ? sample.slice(0, 30) + '…' : sample;
                    label += ` (z.B. ${preview})`;
                }
                return { col, label };
            });
        },

        // Vorschau-Spaltenbuchstaben als Array für x-for
        get vorschauSpalten() {
            if (!this.analyse?.vorschau?.length) return [];
            return Object.keys(this.analyse.vorschau[0].spalten);
        },

        // Feldgruppen für das Mapping-Formular
        // Wenn biografisch global gesetzt → dokument_ist_biografie aus Feldern entfernen
        get feldGruppen() {
            if (!this.analyse) return [];
            const gruppen = [
                { gruppe: 'Person',       felder: this.analyse.felder.person },
                { gruppe: 'Verlegeort',   felder: this.analyse.felder.verlegeort },
                { gruppe: 'Stolperstein', felder: this.analyse.felder.stein },
            ];
            let dokFelder = this.analyse.felder.dokument ?? [];
            if (dokFelder.length) {
                if (this.dokIstBiografieGlobal !== 'spalte') {
                    dokFelder = dokFelder.filter(f => f !== 'dokument_ist_biografie');
                }
                gruppen.push({ gruppe: 'Dokument', felder: dokFelder });
            }
            return gruppen;
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
                form.append('datei',                    this.datei);
                form.append('mapping',                  JSON.stringify(this.mapping));
                form.append('startzeile',               String(this.startzeile));
                form.append('dok_ist_biografie_global', this.dokIstBiografieGlobal);
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
                form.append('datei',                    this.datei);
                form.append('mapping',                  JSON.stringify(this.mapping));
                form.append('startzeile',               String(this.startzeile));
                form.append('dok_ist_biografie_global', this.dokIstBiografieGlobal);
                this.ergebnis = await api.upload('/import/execute', form);
                this.schritt  = 4;
                // URL-Validierungs-State mit importierten Dokumenten vorbefüllen
                this.urlValidierung = (this.ergebnis.dokumente ?? []).map(d => ({ ...d }));
                Alpine.store('notify').success(
                    `Import abgeschlossen: ${this.ergebnis.neue_steine} Stolperstein(e) importiert.`
                );
            } catch (e) {
                this.executeError = e.message || 'Import fehlgeschlagen.';
            } finally {
                this.executing = false;
            }
        },

        // ----- Schritt 5: URL-Prüfung ------------------------------------

        async doUrlValidierung() {
            const ids = (this.urlValidierung ?? []).map(d => d.id).filter(Boolean);
            if (!ids.length) return;
            this.urlPruefend = true;
            try {
                const results = await api.post('/dokumente/url-check', { ids });
                // Ergebnisse in urlValidierung einpflegen
                this.urlValidierung = this.urlValidierung.map(dok => {
                    const r = results.find(r => r.id === dok.id);
                    return r ? { ...dok, ...r } : dok;
                });
                Alpine.store('notify').success('URL-Prüfung abgeschlossen.');
            } catch (e) {
                Alpine.store('notify').error(e.message || 'URL-Prüfung fehlgeschlagen.');
            } finally {
                this.urlPruefend = false;
            }
        },

        urlStatusLabel(status) {
            if (status === null || status === undefined) return '–';
            if (status >= 200 && status < 300) return status + ' OK';
            if (status >= 300 && status < 400) return status + ' Weiterleitung';
            if (status >= 400) return status + ' Fehler';
            return String(status);
        },

        urlStatusFarbe(status) {
            if (status === null || status === undefined) return 'var(--pico-muted-color)';
            if (status >= 200 && status < 300) return 'var(--pico-ins-color, #166534)';
            if (status >= 300 && status < 400) return '#92400e';
            return 'var(--pico-del-color, #991b1b)';
        },

        formatGroesse(bytes) {
            if (!bytes) return '–';
            return (bytes / 1024).toFixed(1).replace('.', ',') + ' kB';
        },

        // ----- Hilfsmethoden ----------------------------------------------

        reset() {
            this.schritt                = 1;
            this.datei                  = null;
            this.dateiName              = '';
            this.analyse                = null;
            this.mapping                = {};
            this.startzeile             = 2;
            this.dokIstBiografieGlobal  = 'spalte';
            this.vorschau               = null;
            this.vorschauLimit          = 50;
            this.ergebnis               = null;
            this.urlValidierung         = null;
            this.urlPruefend            = false;
            this.analyzeError           = null;
            this.previewError           = null;
            this.executeError           = null;
        },

        feldLabel(feld) {
            const labels = {
                nachname:             'Nachname *',
                vorname:              'Vorname',
                geburtsname:          'Geburtsname',
                geburtsdatum:         'Geburtsdatum',
                sterbedatum:          'Sterbedatum',
                biografie_kurz:       'Biografie (kurz)',
                wikipedia_name:       'Wikipedia-Name (Person)',
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
                dokument_url:             'Dokument-URL (extern)',
                dokument_ist_biografie:   'Biografisches Dokument (ja/nein)',
            };
            return labels[feld] ?? feld;
        },

        // Alle nicht-leeren Feldwerte einer Vorschau-Zeile als [{feld, wert}]-Array
        alleFelder(zeile) {
            const result = [];
            const combined = {
                ...(zeile.person     ?? {}),
                ...(zeile.verlegeort ?? {}),
                ...(zeile.stein      ?? {}),
            };
            for (const [key, val] of Object.entries(combined)) {
                if (val !== null && val !== '' && val !== undefined) {
                    result.push({ feld: this.feldLabel(key), wert: String(val) });
                }
            }
            if (zeile.dokument_url) {
                result.push({ feld: 'Dokument-URL', wert: zeile.dokument_url });
                result.push({ feld: 'Als Biografie', wert: zeile.dokument_ist_biografie ? 'Ja' : 'Nein' });
            }
            return result;
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

        get hatDokumentUrls() {
            return (this.ergebnis?.dokumente?.length ?? 0) > 0;
        },
    }));
});
