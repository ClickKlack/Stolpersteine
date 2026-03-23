// Leaflet-Instanz für den Koordinaten-Override-Editor (außerhalb von Alpine)
let _steinMap    = null;
let _steinMarker = null;

document.addEventListener('alpine:init', () => {
    Alpine.data('stolpersteinePage', () => ({

        // ----- Liste -------------------------------------------------------
        steine: [],
        loading: false,
        error: null,

        // ----- Filter ------------------------------------------------------
        filter: { name: '', strasse: '', stadtteil: '', status: '', zustand: '', foto_status: '', person_id: null, verlegeort_id: null, navHinweis: '' },

        // Filter-Lookups
        strasseLookup:   { query: '', ergebnisse: [], offen: false, loading: false },
        stadtteilLookup: { query: '', ergebnisse: [], offen: false, loading: false },

        // ----- Modal -------------------------------------------------------
        modalOpen: false,
        modalMode: 'create',   // 'create' | 'edit'
        saving: false,
        formError: null,
        editId: null,

        form: {
            person_id:              null,
            verlegeort_id:          null,
            verlegedatum:           '',
            inschrift:              '',
            status:                 'neu',
            zustand:                'verfuegbar',
            wikidata_id_stein:      '',
            osm_id:                 '',
            foto_pfad:              '',
            wikimedia_commons:      '',
            wikimedia_commons_eingabe: '',
            foto_lizenz_autor:      '',
            foto_lizenz_name:       '',
            foto_lizenz_url:        '',
            foto_eigenes:           false,
            pos_x:                  null,
            pos_y:                  null,
        },

        // Anzeige-Objekt für die gewählte Person
        personDisplay: null,      // { id, vorname, nachname }
        personLookup: { query: '', loading: false, ergebnisse: [], offen: false },

        // Anzeige-Objekt für den gewählten Verlegeort
        verlegeortDisplay: null,  // { id, strasse, hausnummer, stadtteil, plz }
        verlegeortLookup: { query: '', loading: false, ergebnisse: [], offen: false },

        // Raster des gewählten Verlegeorts
        verlegeortGrid: { n: null, m: null },   // Spalten × Zeilen
        rasterPositionen: [],                    // [{pos_x, pos_y, vorname, nachname, id}] der anderen Steine

        // ----- Foto --------------------------------------------------------
        fotoLaden: false,
        fotoFehler: null,
        fotoDateiAusstehend: null,        // File-Objekt im Create-Modus
        fotoPreviewUrl: null,             // lokale Objekt-URL für Preview
        commonsImportAusstehend: false,   // Commons-Import im Create-Modus angefordert
        fotoVergleich: null,              // null | { identisch, hash_lokal, hash_commons }
        fotoVergleichLaden: false,

        // ----- Löschen -----------------------------------------------------
        deleteId: null,
        deleteConfirmOpen: false,
        deleteSteinName: '',
        deleting: false,

        // ----- Initialisierung ---------------------------------------------
        async init() {
            const nf = Alpine.store('navFilter');
            if (nf.page === 'stolpersteine') {
                const { filter, openEditId } = nf.consume();
                if (filter.person_id)    this.filter.person_id    = filter.person_id;
                if (filter.verlegeort_id) this.filter.verlegeort_id = filter.verlegeort_id;
                if (filter.navHinweis)   this.filter.navHinweis   = filter.navHinweis;
                await this.load();
                if (openEditId) {
                    const stein = this.steine.find(s => s.id === openEditId);
                    if (stein) this.openEdit(stein);
                }
            } else {
                await this.load();
            }
        },

        // ----- Liste laden -------------------------------------------------
        async load() {
            this.loading = true;
            this.error   = null;
            try {
                const params = new URLSearchParams();
                if (this.filter.name)         params.set('name',          this.filter.name);
                if (this.filter.strasse)      params.set('strasse',       this.filter.strasse);
                if (this.filter.stadtteil)    params.set('stadtteil',     this.filter.stadtteil);
                if (this.filter.status)       params.set('status',        this.filter.status);
                if (this.filter.zustand)      params.set('zustand',       this.filter.zustand);
                if (this.filter.foto_status)  params.set('foto_status',   this.filter.foto_status);
                if (this.filter.person_id)    params.set('person_id',     this.filter.person_id);
                if (this.filter.verlegeort_id) params.set('verlegeort_id', this.filter.verlegeort_id);
                const qs = params.toString() ? '?' + params.toString() : '';
                this.steine = await api.get('/stolpersteine' + qs);
            } catch (e) {
                this.error = e.message || 'Stolpersteine konnten nicht geladen werden.';
            } finally {
                this.loading = false;
            }
        },

        resetFilter() {
            this.filter          = { name: '', strasse: '', stadtteil: '', status: '', zustand: '', foto_status: '', person_id: null, verlegeort_id: null, navHinweis: '' };
            this.strasseLookup   = { query: '', ergebnisse: [], offen: false, loading: false };
            this.stadtteilLookup = { query: '', ergebnisse: [], offen: false, loading: false };
            this.load();
        },

        // ----- Filter-Lookups ----------------------------------------------
        async strasseFilterSuchen() {
            const q = this.strasseLookup.query.trim();
            this.filter.strasse = q;
            if (q.length < 2) {
                this.strasseLookup.ergebnisse = [];
                this.strasseLookup.offen      = false;
                this.load();
                return;
            }
            this.strasseLookup.loading = true;
            try {
                const result = await api.get('/adressen/strassen?q=' + encodeURIComponent(q));
                this.strasseLookup.ergebnisse = result.map(s => s.name);
                this.strasseLookup.offen = this.strasseLookup.ergebnisse.length > 0;
            } catch {
                this.strasseLookup.ergebnisse = [];
            } finally {
                this.strasseLookup.loading = false;
            }
        },

        strasseFilterWaehlen(name) {
            this.strasseLookup.query  = name;
            this.filter.strasse       = name;
            this.strasseLookup.offen  = false;
            this.load();
        },

        async stadtteilFilterSuchen() {
            const q = this.stadtteilLookup.query.trim();
            this.filter.stadtteil = q;
            if (q.length < 2) {
                this.stadtteilLookup.ergebnisse = [];
                this.stadtteilLookup.offen      = false;
                this.load();
                return;
            }
            this.stadtteilLookup.loading = true;
            try {
                this.stadtteilLookup.ergebnisse = await api.get('/adressen/stadtteile?q=' + encodeURIComponent(q));
                this.stadtteilLookup.offen = this.stadtteilLookup.ergebnisse.length > 0;
            } catch {
                this.stadtteilLookup.ergebnisse = [];
            } finally {
                this.stadtteilLookup.loading = false;
            }
        },

        stadtteilFilterWaehlen(name) {
            this.stadtteilLookup.query  = name;
            this.filter.stadtteil       = name;
            this.stadtteilLookup.offen  = false;
            this.load();
        },

        // ----- Modal öffnen ------------------------------------------------
        openCreate() {
            this.formError                = null;
            this.fotoFehler               = null;
            this.fotoDateiAusstehend      = null;
            this.commonsImportAusstehend  = false;
            if (this.fotoPreviewUrl) { URL.revokeObjectURL(this.fotoPreviewUrl); }
            this.fotoPreviewUrl           = null;
            this.modalMode                = 'create';
            this.editId                   = null;
            this.form             = {
                person_id: null, verlegeort_id: null, verlegedatum: '',
                inschrift: '', status: 'neu', zustand: 'verfuegbar',
                wikidata_id_stein: '', osm_id: '', foto_pfad: '',
                wikimedia_commons: '', wikimedia_commons_eingabe: '',
                foto_lizenz_autor: '', foto_lizenz_name: '', foto_lizenz_url: '',
                foto_eigenes: false, pos_x: null, pos_y: null,
                lat_override: '', lon_override: '',
            };
            this.personDisplay     = null;
            this.verlegeortDisplay = null;
            this.verlegeortGrid    = { n: null, m: null };
            this.rasterPositionen  = [];
            if (_steinMap) { _steinMap.remove(); _steinMap = null; _steinMarker = null; }
            this._resetPersonLookup();
            this._resetVerlegeortLookup();
            this.modalOpen = true;
        },

        openEdit(stein) {
            this.formError       = null;
            this.fotoFehler      = null;
            this.fotoVergleich   = null;
            this.modalMode       = 'edit';
            this.editId          = stein.id;
            this.form = {
                person_id:                 stein.person_id,
                verlegeort_id:             stein.verlegeort_id,
                verlegedatum:              stein.verlegedatum          ?? '',
                inschrift:                 stein.inschrift             ?? '',
                status:                    stein.status                ?? 'neu',
                zustand:                   stein.zustand               ?? 'verfuegbar',
                wikidata_id_stein:         stein.wikidata_id_stein     ?? '',
                osm_id:                    stein.osm_id                ?? '',
                foto_pfad:                 stein.foto_pfad             ?? '',
                wikimedia_commons:         stein.wikimedia_commons     ?? '',
                wikimedia_commons_eingabe: stein.wikimedia_commons     ?? '',
                foto_lizenz_autor:         stein.foto_lizenz_autor     ?? '',
                foto_lizenz_name:          stein.foto_lizenz_name      ?? '',
                foto_lizenz_url:           stein.foto_lizenz_url       ?? '',
                foto_eigenes:              stein.foto_eigenes           == 1,
                pos_x:                     stein.pos_x                  ?? null,
                pos_y:                     stein.pos_y                  ?? null,
                lat_override:              stein.lat_override            ?? '',
                lon_override:              stein.lon_override            ?? '',
            };
            this._steinVerlegeortLat = stein.verlegeort_lat ?? null;
            this._steinVerlegeortLon = stein.verlegeort_lon ?? null;
            this.personDisplay = {
                id:       stein.person_id,
                vorname:  stein.vorname  || '',
                nachname: stein.nachname || '',
                status:   stein.person_status || '',
            };
            this.verlegeortDisplay = {
                id:         stein.verlegeort_id,
                strasse:    stein.strasse_aktuell    || '',
                hausnummer: stein.hausnummer_aktuell || '',
                stadtteil:  stein.stadtteil          || '',
                status:     stein.verlegeort_status  || '',
            };
            this.verlegeortGrid = {
                n: stein.grid_n ? parseInt(stein.grid_n) : null,
                m: stein.grid_m ? parseInt(stein.grid_m) : null,
            };
            if (this.verlegeortGrid.n && this.verlegeortGrid.m) {
                this._rasterPositionenLaden(stein.verlegeort_id, stein.id);
            }
            this._resetPersonLookup();
            this._resetVerlegeortLookup();
            this.modalOpen = true;
            this.$nextTick(() => {
                this.initSteinMap();
                if (stein.wikimedia_commons) this.fotoVergleichen();
            });
        },

        closeModal() {
            this.modalOpen = false;
            if (_steinMap) { _steinMap.remove(); _steinMap = null; _steinMarker = null; }
        },

        // ----- Person-Lookup -----------------------------------------------
        _resetPersonLookup() {
            this.personLookup = { query: '', loading: false, ergebnisse: [], offen: false };
        },

        async personSuchen() {
            const q = this.personLookup.query.trim();
            if (q.length < 2) {
                this.personLookup.ergebnisse = [];
                this.personLookup.offen      = false;
                return;
            }
            this.personLookup.loading = true;
            try {
                this.personLookup.ergebnisse = await api.get('/personen?name=' + encodeURIComponent(q));
                this.personLookup.offen = true;
            } catch {
                this.personLookup.ergebnisse = [];
            } finally {
                this.personLookup.loading = false;
            }
        },

        personWaehlen(person) {
            this.form.person_id = person.id;
            this.personDisplay  = { id: person.id, vorname: person.vorname || '', nachname: person.nachname || '' };
            this._resetPersonLookup();
        },

        personZuruecksetzen() {
            this.form.person_id = null;
            this.personDisplay  = null;
            this._resetPersonLookup();
            this.$nextTick(() => document.getElementById('st-person-lookup')?.focus());
        },

        // ----- Verlegeort-Lookup -------------------------------------------
        _resetVerlegeortLookup() {
            this.verlegeortLookup = { query: '', loading: false, ergebnisse: [], offen: false };
        },

        async verlegeortSuchen() {
            const q = this.verlegeortLookup.query.trim();
            if (q.length < 2) {
                this.verlegeortLookup.ergebnisse = [];
                this.verlegeortLookup.offen      = false;
                return;
            }
            this.verlegeortLookup.loading = true;
            try {
                this.verlegeortLookup.ergebnisse = await api.get('/verlegeorte?strasse=' + encodeURIComponent(q));
                this.verlegeortLookup.offen = true;
            } catch {
                this.verlegeortLookup.ergebnisse = [];
            } finally {
                this.verlegeortLookup.loading = false;
            }
        },

        verlegeortWaehlen(ort) {
            this.form.verlegeort_id  = ort.id;
            this.form.pos_x          = null;
            this.form.pos_y          = null;
            this.verlegeortDisplay   = {
                id:         ort.id,
                strasse:    ort.strasse_aktuell    || '',
                hausnummer: ort.hausnummer_aktuell || '',
                stadtteil:  ort.stadtteil          || '',
                plz:        ort.plz_aktuell        || '',
            };
            this.verlegeortGrid = {
                n: ort.grid_n ? parseInt(ort.grid_n) : null,
                m: ort.grid_m ? parseInt(ort.grid_m) : null,
            };
            if (this.verlegeortGrid.n && this.verlegeortGrid.m) {
                this._rasterPositionenLaden(ort.id, this.editId);
            } else {
                this.rasterPositionen = [];
            }
            this._resetVerlegeortLookup();
        },

        verlegeortZuruecksetzen() {
            this.form.verlegeort_id = null;
            this.form.pos_x         = null;
            this.form.pos_y         = null;
            this.verlegeortDisplay  = null;
            this.verlegeortGrid     = { n: null, m: null };
            this.rasterPositionen   = [];
            this._resetVerlegeortLookup();
            this.$nextTick(() => document.getElementById('st-ort-lookup')?.focus());
        },

        // ----- Raster-Positionen laden -------------------------------------
        async _rasterPositionenLaden(verlegeortId, ausgeschlossenId) {
            try {
                const steine = await api.get('/stolpersteine?verlegeort_id=' + verlegeortId);
                this.rasterPositionen = steine
                    .filter(s => s.id !== ausgeschlossenId && s.pos_x != null && s.pos_y != null)
                    .map(s => ({
                        id:       s.id,
                        pos_x:    parseInt(s.pos_x),
                        pos_y:    parseInt(s.pos_y),
                        name:     [s.vorname, s.nachname].filter(Boolean).join(' '),
                    }));
            } catch {
                this.rasterPositionen = [];
            }
        },

        rasterZelleWaehlen(x, y) {
            // Zweites Klicken auf dieselbe Zelle hebt Auswahl auf
            if (this.form.pos_x === x && this.form.pos_y === y) {
                this.form.pos_x = null;
                this.form.pos_y = null;
            } else {
                this.form.pos_x = x;
                this.form.pos_y = y;
            }
        },

        // ----- Speichern ---------------------------------------------------
        async save() {
            if (!this.form.person_id) {
                this.formError = 'Bitte eine Person auswählen.';
                return;
            }
            if (!this.form.verlegeort_id) {
                this.formError = 'Bitte einen Verlegeort auswählen.';
                return;
            }
            this.saving    = true;
            this.formError = null;
            try {
                const payload = {
                    person_id:         this.form.person_id,
                    verlegeort_id:     this.form.verlegeort_id,
                    verlegedatum:      this.form.verlegedatum      || null,
                    inschrift:         this.form.inschrift          || null,
                    status:            this.form.status,
                    zustand:           this.form.zustand,
                    wikidata_id_stein: this.form.wikidata_id_stein  || null,
                    osm_id:            this.form.osm_id             ? parseInt(this.form.osm_id) : null,
                    foto_pfad:         this.form.foto_pfad          || null,
                    wikimedia_commons: this.form.wikimedia_commons  || null,
                    foto_eigenes:      this.form.foto_eigenes        ? 1 : 0,
                    pos_x:             this.form.pos_x != null       ? parseInt(this.form.pos_x) : null,
                    pos_y:             this.form.pos_y != null       ? parseInt(this.form.pos_y) : null,
                    lat_override:      this.form.lat_override !== ''  ? parseFloat(this.form.lat_override) : null,
                    lon_override:      this.form.lon_override !== ''  ? parseFloat(this.form.lon_override) : null,
                };
                if (this.modalMode === 'create') {
                    const neu = await api.post('/stolpersteine', payload);
                    this.editId = neu.id;
                    if (this.fotoDateiAusstehend) {
                        await this._uploadDatei(this.fotoDateiAusstehend);
                        this.fotoDateiAusstehend = null;
                        if (this.fotoPreviewUrl) { URL.revokeObjectURL(this.fotoPreviewUrl); this.fotoPreviewUrl = null; }
                    } else if (this.commonsImportAusstehend) {
                        this.commonsImportAusstehend = false;
                        await this.commonsImportieren();
                    }
                    Alpine.store('notify').success('Stolperstein angelegt.');
                } else {
                    await api.put('/stolpersteine/' + this.editId, payload);
                    Alpine.store('notify').success('Stolperstein gespeichert.');
                }
                this.modalOpen = false;
                await this.load();
            } catch (e) {
                this.formError = e.message || 'Speichern fehlgeschlagen.';
            } finally {
                this.saving = false;
            }
        },

        // ----- Foto-Upload -------------------------------------------------
        async fotoHochladen(event) {
            const file = event.target.files[0];
            if (!file) return;

            this.fotoFehler = null;

            // Create-Modus: Datei puffern, lokale Preview zeigen
            if (this.editId === null) {
                if (this.fotoPreviewUrl) URL.revokeObjectURL(this.fotoPreviewUrl);
                this.fotoDateiAusstehend = file;
                this.fotoPreviewUrl      = URL.createObjectURL(file);
                return;
            }

            // Edit-Modus: sofort hochladen
            await this._uploadDatei(file);
            event.target.value = '';
        },

        async _uploadDatei(file) {
            this.fotoLaden  = true;
            this.fotoFehler = null;
            try {
                const formData = new FormData();
                formData.append('foto', file);
                formData.append('foto_eigenes', this.form.foto_eigenes ? '1' : '0');

                const result = await api.upload('/stolpersteine/' + this.editId + '/foto/upload', formData);
                this.form.foto_pfad         = result.foto_pfad         ?? '';
                this.form.foto_lizenz_autor = result.foto_lizenz_autor ?? '';
                this.form.foto_lizenz_name  = result.foto_lizenz_name  ?? '';
                this.form.foto_lizenz_url   = result.foto_lizenz_url   ?? '';
                Alpine.store('notify').success('Foto hochgeladen.');
                // Vergleich neu durchführen falls Commons-Link gesetzt
                if (this.form.wikimedia_commons) this.fotoVergleichen();
            } catch (e) {
                this.fotoFehler = e.message || 'Upload fehlgeschlagen.';
            } finally {
                this.fotoLaden = false;
            }
        },

        async fotoLoeschen() {
            if (this.editId === null) {
                // Neu-Modus: gepufferte Datei verwerfen
                this.fotoDateiAusstehend = null;
                if (this.fotoPreviewUrl) { URL.revokeObjectURL(this.fotoPreviewUrl); this.fotoPreviewUrl = null; }
                return;
            }
            this.fotoLaden  = true;
            this.fotoFehler = null;
            try {
                const result = await api.delete('/stolpersteine/' + this.editId + '/foto');
                this.form.foto_pfad         = result.foto_pfad         ?? '';
                this.form.foto_lizenz_autor = result.foto_lizenz_autor ?? '';
                this.form.foto_lizenz_name  = result.foto_lizenz_name  ?? '';
                this.form.foto_lizenz_url   = result.foto_lizenz_url   ?? '';
                Alpine.store('notify').success('Foto entfernt.');
            } catch (e) {
                this.fotoFehler = e.message || 'Löschen fehlgeschlagen.';
            } finally {
                this.fotoLaden = false;
            }
        },

        // ----- Commons-Import ----------------------------------------------
        commonsEingabeNormalisieren() {
            let v = this.form.wikimedia_commons_eingabe.trim();
            if (!v) {
                this.form.wikimedia_commons = '';
                return;
            }
            // URL: letztes Segment extrahieren
            if (v.includes('/')) {
                try { v = decodeURIComponent(new URL(v).pathname.split('/').pop()); } catch { v = v.split('/').pop(); }
            }
            // "File:" / "Datei:" entfernen
            v = v.replace(/^(File|Datei):/i, '').trim();
            // Percent-Encoding auflösen, damit nie doppelt kodiert wird
            try { v = decodeURIComponent(v); } catch { /* bei ungültigem Encoding unverändernt lassen */ }
            this.form.wikimedia_commons = v;
        },

        async commonsImportieren() {
            if (!this.form.wikimedia_commons) return;
            // Create-Modus: Wunsch merken, wird nach dem Speichern ausgeführt
            if (this.editId === null) {
                this.commonsImportAusstehend = true;
                return;
            }
            this.fotoLaden  = true;
            this.fotoFehler = null;
            try {
                const result = await api.post(
                    '/stolpersteine/' + this.editId + '/foto/commons-import',
                    { commons_datei: this.form.wikimedia_commons }
                );
                this.form.foto_pfad         = result.foto_pfad         ?? '';
                this.form.wikimedia_commons = result.wikimedia_commons  ?? this.form.wikimedia_commons;
                this.form.wikimedia_commons_eingabe = this.form.wikimedia_commons;
                this.form.foto_lizenz_autor = result.foto_lizenz_autor  ?? '';
                this.form.foto_lizenz_name  = result.foto_lizenz_name   ?? '';
                this.form.foto_lizenz_url   = result.foto_lizenz_url    ?? '';
                this.form.foto_eigenes      = false;
                Alpine.store('notify').success('Bild von Wikimedia Commons importiert.');
                // Nach Import: Vergleich ist bekannt identisch
                this.fotoVergleich = { identisch: true };
            } catch (e) {
                this.fotoFehler = e.message || 'Import fehlgeschlagen.';
            } finally {
                this.fotoLaden = false;
            }
        },

        // ----- Foto-Vergleich (SHA1) ----------------------------------------
        async fotoVergleichen() {
            if (!this.form.wikimedia_commons || !this.editId) return;
            this.fotoVergleichLaden = true;
            this.fotoVergleich      = null;
            try {
                this.fotoVergleich = await api.get('/stolpersteine/' + this.editId + '/foto/vergleich');
            } catch {
                // Vergleich optional – Fehler still ignorieren
            } finally {
                this.fotoVergleichLaden = false;
            }
        },

        // ----- Datum formatieren -------------------------------------------
        formatDate(iso, genauigkeit) {
            if (!iso) return '';
            const [y, m, d] = iso.split('-');
            if (genauigkeit === 'jahr')  return y;
            if (genauigkeit === 'monat') return `${m}/${y}`;
            return d ? `${d}.${m}.${y}` : iso;
        },

        // ----- Inschrift einfügen (Anführungszeichen entfernen) ------------
        inschriftEinfuegen(e) {
            e.preventDefault();
            let t = e.clipboardData.getData('text');
            // Führende/abschließende Anführungs- und Hochkommazeichen entfernen
            t = t.replace(/^[\u0022\u0027\u00AB\u00BB\u2018\u2019\u201A\u201B\u201C\u201D\u201E\u201F\u2039\u203A]+|[\u0022\u0027\u00AB\u00BB\u2018\u2019\u201C\u201D\u2039\u203A]+$/g, '');
            e.target.setRangeText(t, e.target.selectionStart, e.target.selectionEnd, 'end');
            this.form.inschrift = e.target.value;
        },

        // ----- Löschen -----------------------------------------------------
        openDelete(stein) {
            this.deleteId        = stein.id;
            this.deleteSteinName = [stein.vorname, stein.nachname].filter(Boolean).join(' ')
                                 + ' – '
                                 + ([stein.strasse_aktuell, stein.hausnummer_aktuell].filter(Boolean).join(' ') || '?');
            this.deleteConfirmOpen = true;
        },

        closeDelete() {
            this.deleteConfirmOpen = false;
        },

        async doDelete() {
            this.deleting = true;
            try {
                await api.delete('/stolpersteine/' + this.deleteId);
                Alpine.store('notify').success('Stolperstein gelöscht.');
                this.deleteConfirmOpen = false;
                await this.load();
            } catch (e) {
                Alpine.store('notify').error(e.message || 'Löschen fehlgeschlagen.');
            } finally {
                this.deleting = false;
            }
        },

        // ----- Hilfsfunktionen ---------------------------------------------
        personAnzeige(stein) {
            return [stein.vorname, stein.nachname].filter(Boolean).join(' ') || '–';
        },

        adresseAnzeige(stein) {
            return [stein.strasse_aktuell, stein.hausnummer_aktuell].filter(Boolean).join(' ') || '–';
        },

        fotoUrl(pfad) {
            if (!pfad) return null;
            const base = CONFIG.apiBase.replace(/\/api$/, '');
            return base + '/uploads/' + pfad;
        },

        statusLabel(s) {
            return {
                neu:         'Neu',
                validierung: 'Validierung',
                freigegeben: 'Freigegeben',
                archiviert:  'Archiviert',
                fehlerhaft:  'Fehlerhaft',
            }[s] || s;
        },

        zustandLabel(z) {
            return {
                verfuegbar:    'Verfügbar',
                stein_fehlend: 'Stein fehlend',
                kein_stein:    'Kein Stein',
                beschaedigt:   'Beschädigt',
                unleserlich:   'Unleserlich',
            }[z] || z;
        },

        // ----- Koordinaten-Override Karte ----------------------------------
        initSteinMap() {
            if (_steinMap) { _steinMap.remove(); _steinMap = null; _steinMarker = null; }
            const el = document.getElementById('map-stein-edit');
            if (!el) return;

            const hasOverride = this.form.lat_override !== '' && this.form.lon_override !== '';
            let lat, lon, zoom;
            if (hasOverride) {
                lat  = parseFloat(this.form.lat_override);
                lon  = parseFloat(this.form.lon_override);
                zoom = 18;
            } else if (this._steinVerlegeortLat && this._steinVerlegeortLon) {
                lat  = parseFloat(this._steinVerlegeortLat);
                lon  = parseFloat(this._steinVerlegeortLon);
                zoom = 18;
            } else {
                const cfg = Alpine.store('config');
                lat  = cfg.map_lat  ?? 51.5;
                lon  = cfg.map_lon  ?? 10.0;
                zoom = 13;
            }

            _steinMap = L.map('map-stein-edit').setView([lat, lon], zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(_steinMap);

            if (hasOverride) {
                _steinMarker = L.marker([lat, lon], { draggable: true }).addTo(_steinMap);
                _steinMarker.on('dragend', (e) => {
                    const pos = e.target.getLatLng();
                    this.form.lat_override = pos.lat.toFixed(8);
                    this.form.lon_override = pos.lng.toFixed(8);
                });
            }

            _steinMap.on('click', (e) => {
                this.form.lat_override = e.latlng.lat.toFixed(8);
                this.form.lon_override = e.latlng.lng.toFixed(8);
                if (_steinMarker) {
                    _steinMarker.setLatLng(e.latlng);
                } else {
                    _steinMarker = L.marker(e.latlng, { draggable: true }).addTo(_steinMap);
                    _steinMarker.on('dragend', (ev) => {
                        const pos = ev.target.getLatLng();
                        this.form.lat_override = pos.lat.toFixed(8);
                        this.form.lon_override = pos.lng.toFixed(8);
                    });
                }
            });
        },

        updateSteinMapMarker() {
            if (!_steinMap) return;
            const lat = parseFloat(this.form.lat_override);
            const lon = parseFloat(this.form.lon_override);
            if (isNaN(lat) || isNaN(lon)) return;
            if (_steinMarker) {
                _steinMarker.setLatLng([lat, lon]);
            } else {
                _steinMarker = L.marker([lat, lon], { draggable: true }).addTo(_steinMap);
                _steinMarker.on('dragend', (e) => {
                    const pos = e.target.getLatLng();
                    this.form.lat_override = pos.lat.toFixed(8);
                    this.form.lon_override = pos.lng.toFixed(8);
                });
            }
            _steinMap.setView([lat, lon], 18);
        },

        steinOverrideLoeschen() {
            this.form.lat_override = '';
            this.form.lon_override = '';
            if (_steinMarker) { _steinMarker.remove(); _steinMarker = null; }
        },
    }));
});
