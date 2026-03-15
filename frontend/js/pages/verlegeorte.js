// Leaflet-Instanzen außerhalb von Alpine (nicht reaktiv)
let _listMap   = null;
let _editMap   = null;
let _editMarker = null;

document.addEventListener('alpine:init', () => {
    Alpine.data('verlegeortePage', () => ({

        // ----- Liste -------------------------------------------------------
        orte: [],
        loading: false,
        error: null,

        // ----- Filter ------------------------------------------------------
        filter: { adresse: '', stadtteil: '', status: '' },

        // ----- Modal -------------------------------------------------------
        modalOpen: false,
        modalMode: 'create',   // 'create' | 'edit'
        saving: false,
        formError: null,
        editId: null,

        form: {
            adress_lokation_id:   null,
            hausnummer_aktuell:   '',
            beschreibung:         '',
            lat:                  '',
            lon:                  '',
            bemerkung_historisch: '',
            grid_n:               '',
            grid_m:               '',
            raster_beschreibung:  '',
        },

        // Anzeige-Objekt für die gewählte Adresse
        adresse: null,   // { lokation_id, strasse_name, wikidata_id_strasse, stadtteil_name,
                         //   wikidata_id_stadtteil, plz, stadt_name, wikidata_id_ort }

        // ----- Adress-Lookup -----------------------------------------------
        lookup: {
            query:     '',
            loading:   false,
            ergebnisse: [],
            offen:     false,
        },

        // ----- Neue Adresse (Inline-Formular) ------------------------------
        neueAdresse: {
            aktiv:               false,
            strasse_name:        '',
            wikidata_id_strasse: '',
            stadtteil_name:      '',
            wikidata_id_stadtteil: '',
            plz:                 '',
            stadt_name:          '',
            wikidata_id_ort:     '',
            saving:              false,
            error:               null,
        },

        // ----- Kartenvorschau (Liste) --------------------------------------
        mapPreview: { open: false, lat: null, lon: null, adresse: '' },

        // ----- Löschen -----------------------------------------------------
        deleteId: null,
        deleteConfirmOpen: false,
        deleteOrtName: '',
        deleting: false,

        // ----- Initialisierung ---------------------------------------------
        async init() {
            await this.load();
        },

        // ----- Liste laden -------------------------------------------------
        async load() {
            this.loading = true;
            this.error   = null;
            try {
                const params = new URLSearchParams();
                if (this.filter.adresse)   params.set('strasse',   this.filter.adresse);
                if (this.filter.stadtteil) params.set('stadtteil', this.filter.stadtteil);
                if (this.filter.status)    params.set('status',    this.filter.status);
                const qs = params.toString() ? '?' + params.toString() : '';
                this.orte = await api.get('/verlegeorte' + qs);
            } catch (e) {
                this.error = e.message || 'Verlegeorte konnten nicht geladen werden.';
            } finally {
                this.loading = false;
            }
        },

        resetFilter() {
            this.filter = { adresse: '', stadtteil: '', status: '' };
            this.load();
        },

        // ----- Modal öffnen ------------------------------------------------
        openCreate() {
            this.formError = null;
            this.modalMode = 'create';
            this.editId    = null;
            const cfg = Alpine.store('config');
            this.form = {
                adress_lokation_id:   null,
                hausnummer_aktuell:   '',
                beschreibung:         '',
                lat:                  '',
                lon:                  '',
                bemerkung_historisch: '',
                grid_n:               '',
                grid_m:               '',
                raster_beschreibung:  '',
                status:               'validierung',
            };
            this.adresse = null;
            this._resetLookup();
            this.neueAdresse = {
                aktiv:                false,
                strasse_name:         '',
                wikidata_id_strasse:  '',
                stadtteil_name:       '',
                wikidata_id_stadtteil: '',
                plz:                  '',
                stadt_name:           cfg.stadt_name        || '',
                wikidata_id_ort:      cfg.wikidata_city_id  || '',
                saving:               false,
                error:                null,
            };
            this.modalOpen = true;
            this.$nextTick(() => {
                document.getElementById('v-lookup')?.focus();
                this.initEditMap();
            });
        },

        openEdit(ort) {
            this.formError = null;
            this.modalMode = 'edit';
            this.editId    = ort.id;
            this.form = {
                adress_lokation_id:   ort.adress_lokation_id  ?? null,
                hausnummer_aktuell:   ort.hausnummer_aktuell   ?? '',
                beschreibung:         ort.beschreibung         ?? '',
                lat:                  ort.lat                  ?? '',
                lon:                  ort.lon                  ?? '',
                bemerkung_historisch: ort.bemerkung_historisch ?? '',
                grid_n:               ort.grid_n               ?? '',
                grid_m:               ort.grid_m               ?? '',
                raster_beschreibung:  ort.raster_beschreibung  ?? '',
                status:               ort.status               ?? 'validierung',
            };
            // Adress-Anzeige aus den JOIN-Feldern des Listeneintrags befüllen
            if (ort.adress_lokation_id) {
                this.adresse = {
                    lokation_id:          ort.adress_lokation_id,
                    strasse_name:         ort.strasse_aktuell         || '',
                    wikidata_id_strasse:  ort.wikidata_id_strasse     || '',
                    stadtteil_name:       ort.stadtteil               || '',
                    wikidata_id_stadtteil: ort.wikidata_id_stadtteil  || '',
                    plz:                  ort.plz_aktuell             || '',
                    stadt_name:           ort.stadt                   || '',
                    wikidata_id_ort:      ort.wikidata_id_ort         || '',
                };
            } else {
                this.adresse = null;
            }
            this._resetLookup();
            this.neueAdresse = {
                aktiv: false, strasse_name: '', wikidata_id_strasse: '',
                stadtteil_name: '', wikidata_id_stadtteil: '', plz: '',
                stadt_name: '', wikidata_id_ort: '', saving: false, error: null,
            };
            this.modalOpen = true;
            this.$nextTick(() => this.initEditMap());
        },

        closeModal() {
            if (this.neueAdresse.aktiv) {
                this.formError = 'Bitte zuerst die neue Adresse speichern oder abbrechen.';
                return;
            }
            this.modalOpen = false;
            if (_editMap) { _editMap.remove(); _editMap = null; _editMarker = null; }
        },

        // ----- Adress-Lookup -----------------------------------------------
        _resetLookup() {
            this.lookup = { query: '', loading: false, ergebnisse: [], offen: false };
        },

        async strasseSuchen() {
            const q = this.lookup.query.trim();
            if (q.length < 2) {
                this.lookup.ergebnisse = [];
                this.lookup.offen      = false;
                return;
            }
            this.lookup.loading = true;
            try {
                this.lookup.ergebnisse = await api.get('/adressen/strassen?q=' + encodeURIComponent(q));
                this.lookup.offen = true;
            } catch {
                this.lookup.ergebnisse = [];
            } finally {
                this.lookup.loading = false;
            }
        },

        lokationWaehlen(strasse, lok) {
            this.form.adress_lokation_id = lok.id;
            this.adresse = {
                lokation_id:           lok.id,
                strasse_name:          strasse.name,
                wikidata_id_strasse:   strasse.wikidata_id      || '',
                stadtteil_name:        lok.stadtteil_name       || '',
                wikidata_id_stadtteil: lok.wikidata_id_stadtteil || '',
                plz:                   lok.plz                  || '',
                stadt_name:            strasse.stadt_name       || '',
                wikidata_id_ort:       strasse.wikidata_id_ort  || '',
            };
            this._resetLookup();
            this.neueAdresse.aktiv = false;
        },

        adresseZuruecksetzen() {
            this.adresse = null;
            this.form.adress_lokation_id = null;
            this._resetLookup();
            this.neueAdresse.aktiv = false;
            this.$nextTick(() => document.getElementById('v-lookup')?.focus());
        },

        neueAdresseStarten() {
            const cfg = Alpine.store('config');
            this.neueAdresse = {
                aktiv:                 true,
                strasse_name:          this.lookup.query.trim(),
                wikidata_id_strasse:   '',
                stadtteil_name:        '',
                wikidata_id_stadtteil: '',
                plz:                   '',
                stadt_name:            cfg.stadt_name       || '',
                wikidata_id_ort:       cfg.wikidata_city_id || '',
                saving:                false,
                error:                 null,
            };
            this.lookup.offen = false;
        },

        async neueAdresseSpeichern() {
            if (!this.neueAdresse.strasse_name.trim()) {
                this.neueAdresse.error = 'Straße ist erforderlich.';
                return;
            }
            if (!this.neueAdresse.stadt_name.trim()) {
                this.neueAdresse.error = 'Stadt ist erforderlich.';
                return;
            }
            this.neueAdresse.saving = true;
            this.neueAdresse.error  = null;
            try {
                const lok = await api.post('/adressen/lokationen', {
                    strasse_name:          this.neueAdresse.strasse_name.trim(),
                    wikidata_id_strasse:   this.neueAdresse.wikidata_id_strasse  || null,
                    stadtteil_name:        this.neueAdresse.stadtteil_name.trim() || null,
                    wikidata_id_stadtteil: this.neueAdresse.wikidata_id_stadtteil || null,
                    plz:                   this.neueAdresse.plz.trim()           || null,
                    stadt_name:            this.neueAdresse.stadt_name.trim(),
                    wikidata_id_ort:       this.neueAdresse.wikidata_id_ort      || null,
                });
                this.form.adress_lokation_id = lok.lokation_id;
                this.adresse = {
                    lokation_id:           lok.lokation_id,
                    strasse_name:          lok.strasse_name          || '',
                    wikidata_id_strasse:   lok.wikidata_id_strasse   || '',
                    stadtteil_name:        lok.stadtteil_name        || '',
                    wikidata_id_stadtteil: lok.wikidata_id_stadtteil || '',
                    plz:                   lok.plz                   || '',
                    stadt_name:            lok.stadt_name            || '',
                    wikidata_id_ort:       lok.wikidata_id_ort       || '',
                };
                this.neueAdresse.aktiv = false;
                this._resetLookup();
            } catch (e) {
                this.neueAdresse.error = e.message || 'Adresse konnte nicht gespeichert werden.';
            } finally {
                this.neueAdresse.saving = false;
            }
        },

        // ----- Speichern ---------------------------------------------------
        async save() {
            this.saving    = true;
            this.formError = null;
            try {
                const payload = {
                    adress_lokation_id:   this.form.adress_lokation_id  || null,
                    hausnummer_aktuell:   this.form.hausnummer_aktuell   || null,
                    beschreibung:         this.form.beschreibung         || null,
                    lat:                  this.form.lat !== '' ? parseFloat(this.form.lat) : null,
                    lon:                  this.form.lon !== '' ? parseFloat(this.form.lon) : null,
                    bemerkung_historisch: this.form.bemerkung_historisch || null,
                    grid_n:               this.form.grid_n !== '' ? parseInt(this.form.grid_n) : null,
                    grid_m:               this.form.grid_m !== '' ? parseInt(this.form.grid_m) : null,
                    raster_beschreibung:  this.form.raster_beschreibung  || null,
                    status:               this.form.status || 'validierung',
                };

                if (this.modalMode === 'create') {
                    await api.post('/verlegeorte', payload);
                    Alpine.store('notify').success('Verlegeort angelegt.');
                } else {
                    await api.put('/verlegeorte/' + this.editId, payload);
                    Alpine.store('notify').success('Verlegeort gespeichert.');
                }
                this.modalOpen = false;
                if (_editMap) { _editMap.remove(); _editMap = null; _editMarker = null; }
                await this.load();
            } catch (e) {
                this.formError = e.message || 'Speichern fehlgeschlagen.';
            } finally {
                this.saving = false;
            }
        },

        // ----- Löschen -----------------------------------------------------
        openDelete(ort) {
            this.deleteId      = ort.id;
            this.deleteOrtName = [ort.strasse_aktuell, ort.hausnummer_aktuell].filter(Boolean).join(' ');
            this.deleteConfirmOpen = true;
        },

        closeDelete() {
            this.deleteConfirmOpen = false;
        },

        async doDelete() {
            this.deleting = true;
            try {
                await api.delete('/verlegeorte/' + this.deleteId);
                Alpine.store('notify').success('Verlegeort gelöscht.');
                this.deleteConfirmOpen = false;
                await this.load();
            } catch (e) {
                Alpine.store('notify').error(e.message || 'Löschen fehlgeschlagen.');
            } finally {
                this.deleting = false;
            }
        },

        statusLabel(s) {
            return { ok: 'Ok', validierung: 'Validierung' }[s] ?? s;
        },

        // ----- Hilfsfunktionen ---------------------------------------------
        adresseAnzeige(ort) {
            const adresse = [ort.strasse_aktuell, ort.hausnummer_aktuell].filter(Boolean).join(' ');
            return ort.beschreibung ? adresse + ' (' + ort.beschreibung + ')' : adresse;
        },

        koordinatenAnzeige(ort) {
            if (!ort.lat || !ort.lon) return '–';
            return parseFloat(ort.lat).toFixed(5) + ', ' + parseFloat(ort.lon).toFixed(5);
        },

        // ----- Karten-Methoden ---------------------------------------------

        _mapCenter() {
            // Mittelpunkt aus vorhandenen Orten berechnen, sonst Fallback
            const mitKoords = this.orte.filter(o => o.lat && o.lon);
            if (mitKoords.length > 0) {
                const lat = mitKoords.reduce((s, o) => s + parseFloat(o.lat), 0) / mitKoords.length;
                const lon = mitKoords.reduce((s, o) => s + parseFloat(o.lon), 0) / mitKoords.length;
                return [lat, lon];
            }
            return [52.13, 11.62]; // Fallback
        },

        openMapPreview(ort) {
            if (!ort.lat || !ort.lon) return;
            this.mapPreview = {
                open:    true,
                lat:     ort.lat,
                lon:     ort.lon,
                adresse: this.adresseAnzeige(ort) || 'Standort',
            };
            this.$nextTick(() => {
                if (_listMap) { _listMap.remove(); _listMap = null; }
                _listMap = L.map('map-preview').setView([ort.lat, ort.lon], 17);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                }).addTo(_listMap);
                L.marker([ort.lat, ort.lon]).addTo(_listMap);
            });
        },

        closeMapPreview() {
            this.mapPreview.open = false;
            if (_listMap) { _listMap.remove(); _listMap = null; }
        },

        initEditMap() {
            if (_editMap) { _editMap.remove(); _editMap = null; _editMarker = null; }
            const hasCoords = this.form.lat !== '' && this.form.lon !== '';
            const lat  = hasCoords ? parseFloat(this.form.lat) : this._mapCenter()[0];
            const lon  = hasCoords ? parseFloat(this.form.lon) : this._mapCenter()[1];
            const zoom = hasCoords ? 17 : 13;

            _editMap = L.map('map-edit').setView([lat, lon], zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(_editMap);

            if (hasCoords) {
                _editMarker = L.marker([lat, lon], { draggable: true }).addTo(_editMap);
                _editMarker.on('dragend', (e) => {
                    const pos = e.target.getLatLng();
                    this.form.lat = pos.lat.toFixed(8);
                    this.form.lon = pos.lng.toFixed(8);
                });
            }

            _editMap.on('click', (e) => {
                this.form.lat = e.latlng.lat.toFixed(8);
                this.form.lon = e.latlng.lng.toFixed(8);
                if (_editMarker) {
                    _editMarker.setLatLng(e.latlng);
                } else {
                    _editMarker = L.marker(e.latlng, { draggable: true }).addTo(_editMap);
                    _editMarker.on('dragend', (ev) => {
                        const pos = ev.target.getLatLng();
                        this.form.lat = pos.lat.toFixed(8);
                        this.form.lon = pos.lng.toFixed(8);
                    });
                }
            });
        },

        updateEditMapMarker() {
            if (!_editMap) return;
            const lat = parseFloat(this.form.lat);
            const lon = parseFloat(this.form.lon);
            if (isNaN(lat) || isNaN(lon)) return;
            if (_editMarker) {
                _editMarker.setLatLng([lat, lon]);
            } else {
                _editMarker = L.marker([lat, lon], { draggable: true }).addTo(_editMap);
                _editMarker.on('dragend', (e) => {
                    const pos = e.target.getLatLng();
                    this.form.lat = pos.lat.toFixed(8);
                    this.form.lon = pos.lng.toFixed(8);
                });
            }
            _editMap.setView([lat, lon], 17);
        },
    }));
});
