document.addEventListener('alpine:init', () => {
    Alpine.data('dokumentePage', () => ({

        // ----- Liste -------------------------------------------------------
        dokumente: [],
        loading: false,
        error: null,

        // ----- Filter -------------------------------------------------------
        filter: { person_id: '', typ: '', url_fehler: false },

        // ----- Personensuche -----------------------------------------------
        personSuchtext: '',
        personVorschlaege: [],
        personSucheAktiv: false,
        selectedPerson: null,
        _personSuchTimer: null,

        // ----- Anlegen / Bearbeiten -----------------------------------------
        modalOpen: false,
        editId: null,          // null = Neuanlage, sonst ID des bearbeiteten Dokuments
        saving: false,
        formError: null,
        urlInfoLaden: false,
        fetchedUrlStatus: null,   // aus ↺-Abruf
        savedUrlStatus: null,     // aus DB (beim Öffnen Bearbeiten)
        savedUrlGeprueftAm: null,
        form: {
            titel: '',
            beschreibung_kurz: '',
            quelle_url: '',
            typ: 'pdf',
            personen: [],          // [{id, vorname, nachname, geburtsname}]
            stolperstein_id: '',
            quelle: '',
            groesse_bytes: '',
            dateiname: '',
        },

        // Personensuche im Formular
        formPersonSuchtext: '',
        formPersonVorschlaege: [],
        formPersonSucheAktiv: false,
        _formPersonSuchTimer: null,

        // ----- URL-Check / Spiegel -----------------------------------------
        checkingIds: new Set(),
        mirroringIds: new Set(),
        checkingAll: false,
        checkAllProgress: { done: 0, total: 0 },
        downloadingAll: false,
        downloadAllProgress: { done: 0, total: 0, errors: 0 },
        refreshingDateinamen: false,
        refreshDateinamenProgress: { done: 0, total: 0 },

        // ----- Löschen -------------------------------------------------------
        deleteId: null,
        deleteConfirmOpen: false,
        deleting: false,

        // ----- Personensuche (Filter) ---------------------------------------
        onPersonInput() {
            clearTimeout(this._personSuchTimer);
            const text = this.personSuchtext.trim();
            if (text.length < 2) {
                this.personVorschlaege = [];
                this.personSucheAktiv  = false;
                if (!this.selectedPerson) {
                    this.filter.person_id = '';
                }
                return;
            }
            this._personSuchTimer = setTimeout(() => this.searchPersonen(text), 300);
        },

        async searchPersonen(text) {
            try {
                const results = await api.get('/personen?name=' + encodeURIComponent(text));
                this.personVorschlaege = results.slice(0, 8);
                this.personSucheAktiv  = this.personVorschlaege.length > 0;
            } catch (e) {
                this.personVorschlaege = [];
            }
        },

        selectPerson(person) {
            this.selectedPerson   = person;
            this.filter.person_id = person.id;
            this.personSuchtext   = person.nachname + (person.vorname ? ', ' + person.vorname : '');
            this.personSucheAktiv = false;
            this.personVorschlaege = [];
            this.load();
        },

        clearPersonFilter() {
            this.selectedPerson    = null;
            this.filter.person_id  = '';
            this.personSuchtext    = '';
            this.personVorschlaege = [];
            this.personSucheAktiv  = false;
            this.load();
        },

        // ----- Initialisierung ----------------------------------------------
        async init() {
            await this.load();
        },

        // ----- Liste laden --------------------------------------------------
        async load() {
            this.loading = true;
            this.error   = null;
            try {
                const params = new URLSearchParams();
                if (this.filter.person_id) params.set('person_id', this.filter.person_id);
                if (this.filter.typ)       params.set('typ', this.filter.typ);
                if (this.filter.url_fehler) params.set('url_fehler', '1');
                const qs = params.toString() ? '?' + params.toString() : '';
                this.dokumente = await api.get('/dokumente' + qs);
            } catch (e) {
                this.error = e.message || 'Dokumente konnten nicht geladen werden.';
            } finally {
                this.loading = false;
            }
        },

        // ----- Anlegen ------------------------------------------------------
        openCreate() {
            this.editId              = null;
            this.formError           = null;
            this.fetchedUrlStatus    = null;
            this.savedUrlStatus      = null;
            this.savedUrlGeprueftAm  = null;
            this.formPersonSuchtext    = '';
            this.formPersonVorschlaege = [];
            this.formPersonSucheAktiv  = false;
            this.form = {
                titel: '', beschreibung_kurz: '', quelle_url: '',
                typ: 'pdf', personen: [], stolperstein_id: '',
                quelle: '', groesse_bytes: '', dateiname: '',
            };
            this.modalOpen = true;
        },

        // ----- Bearbeiten ---------------------------------------------------
        async openEdit(dok) {
            this.editId           = dok.id;
            this.formError        = null;
            this.fetchedUrlStatus    = null;
            this.savedUrlStatus      = null;
            this.savedUrlGeprueftAm  = null;
            this.formPersonSuchtext    = '';
            this.formPersonVorschlaege = [];
            this.formPersonSucheAktiv  = false;
            try {
                const full = await api.get('/dokumente/' + dok.id);
                this.form = {
                    titel:             full.titel             || '',
                    beschreibung_kurz: full.beschreibung_kurz || '',
                    quelle_url:        full.quelle_url        || '',
                    typ:               full.typ               || 'pdf',
                    personen:          full.personen          || [],
                    stolperstein_id:   full.stolperstein_id   || '',
                    quelle:     full.quelle      || '',
                    groesse_bytes:     full.groesse_bytes      || '',
                    dateiname:         full.dateiname          || '',
                };
                this.savedUrlStatus     = full.url_status      ?? null;
                this.savedUrlGeprueftAm = full.url_geprueft_am ?? null;
                this.modalOpen = true;
            } catch (e) {
                Alpine.store('notify').error(e.message || 'Dokument konnte nicht geladen werden.');
            }
        },

        // URL-Metadaten vom Backend holen und Felder füllen
        async fetchUrlInfo() {
            const url = this.form.quelle_url.trim();
            if (!url) return;
            this.urlInfoLaden = true;
            try {
                const info = await api.post('/dokumente/url-info', { url });
                if (info.dateiname && !this.form.titel)              this.form.titel         = info.dateiname;
                if (info.dateiname)                                   this.form.dateiname     = info.dateiname;
                if (info.typ)                                         this.form.typ           = info.typ;
                if (info.groesse_bytes)                               this.form.groesse_bytes = info.groesse_bytes;
                if (info.quelle && !this.form.quelle)   this.form.quelle = info.quelle;
                if (info.url_status !== undefined)                    this.fetchedUrlStatus   = info.url_status;
            } catch (e) {
                // Silently ignore – User kann manuell befüllen
            } finally {
                this.urlInfoLaden = false;
            }
        },

        // Personensuche im Formular
        onFormPersonInput() {
            clearTimeout(this._formPersonSuchTimer);
            const text = this.formPersonSuchtext.trim();
            if (text.length < 2) {
                this.formPersonVorschlaege = [];
                this.formPersonSucheAktiv  = false;
                return;
            }
            this._formPersonSuchTimer = setTimeout(async () => {
                try {
                    const results = await api.get('/personen?name=' + encodeURIComponent(text));
                    const bereitsIds = new Set(this.form.personen.map(p => p.id));
                    this.formPersonVorschlaege = results.filter(p => !bereitsIds.has(p.id)).slice(0, 8);
                    this.formPersonSucheAktiv  = this.formPersonVorschlaege.length > 0;
                } catch (e) {
                    this.formPersonVorschlaege = [];
                }
            }, 300);
        },

        addFormPerson(person) {
            if (!this.form.personen.find(p => p.id === person.id)) {
                this.form.personen.push(person);
            }
            // Hinzugefügte Person aus Vorschlagsliste entfernen, Liste bleibt offen
            this.formPersonVorschlaege = this.formPersonVorschlaege.filter(p => p.id !== person.id);
            if (this.formPersonVorschlaege.length === 0) {
                this.formPersonSucheAktiv = false;
            }
        },

        removeFormPerson(idx) {
            this.form.personen.splice(idx, 1);
        },

        async toggleBiografie(person, idx) {
            if (this.editId) {
                // Bearbeiten: sofort per API
                try {
                    const result = await api.post('/dokumente/' + this.editId + '/biografie', { person_id: person.id });
                    this.form.personen[idx] = { ...person, ist_biografie: result.ist_biografie };
                    Alpine.store('notify').success(result.ist_biografie ? 'Als Biografie gesetzt.' : 'Biografie-Zuordnung entfernt.');
                } catch (e) {
                    Alpine.store('notify').error(e.message || 'Aktion fehlgeschlagen.');
                }
            } else {
                // Neuanlegen: lokal merken, wird nach dem Speichern angewendet
                this.form.personen[idx] = { ...person, ist_biografie: !person.ist_biografie };
            }
        },

        async save() {
            if (!this.form.titel.trim()) {
                this.formError = 'Titel ist erforderlich.';
                return;
            }

            // URL-Info sicherstellen: laufenden Fetch abwarten oder neu starten
            if (this.form.quelle_url.trim()) {
                if (this.urlInfoLaden) {
                    await new Promise(r => {
                        const t = setInterval(() => { if (!this.urlInfoLaden) { clearInterval(t); r(); } }, 50);
                    });
                } else if (this.fetchedUrlStatus === null) {
                    await this.fetchUrlInfo();
                }
            }

            this.saving    = true;
            this.formError = null;

            const payload = {
                titel:             this.form.titel.trim(),
                beschreibung_kurz: this.form.beschreibung_kurz.trim() || null,
                quelle_url:        this.form.quelle_url.trim() || null,
                typ:               this.form.typ,
                person_ids:        this.form.personen.map(p => p.id),
                stolperstein_id:   this.form.stolperstein_id ? parseInt(this.form.stolperstein_id) : null,
                quelle:     this.form.quelle.trim() || null,
                groesse_bytes:     this.form.groesse_bytes ? parseInt(this.form.groesse_bytes) : null,
                dateiname:         this.form.dateiname.trim() || null,
                url_status:        this.fetchedUrlStatus,
            };

            try {
                if (this.editId) {
                    await api.put('/dokumente/' + this.editId, payload);
                    Alpine.store('notify').success('Dokument gespeichert.');
                } else {
                    if (!payload.quelle_url) {
                        this.formError = 'URL ist erforderlich.';
                        this.saving = false;
                        return;
                    }
                    const neu = await api.post('/dokumente', payload);
                    // Biografie-Zuordnungen nachträglich anwenden
                    const neuId = neu?.id ?? neu?.data?.id;
                    if (neuId) {
                        for (const p of this.form.personen.filter(p => p.ist_biografie)) {
                            await api.post('/dokumente/' + neuId + '/biografie', { person_id: p.id });
                        }
                    }
                    Alpine.store('notify').success('Dokument angelegt.');
                }
                this.modalOpen = false;
                await this.load();
            } catch (e) {
                if (e.status === 409) {
                    this.formError = `URL bereits vorhanden (Dokument-ID: ${e.data?.vorhandenes_dokument_id}).`;
                } else {
                    this.formError = e.message || 'Speichern fehlgeschlagen.';
                }
            } finally {
                this.saving = false;
            }
        },

        // ----- URL-Check für ein Dokument -----------------------------------
        async checkUrl(dok) {
            this.checkingIds = new Set([...this.checkingIds, dok.id]);
            try {
                const results = await api.post('/dokumente/url-check', { ids: [dok.id] });
                const result  = results[0];
                const idx = this.dokumente.findIndex(d => d.id === dok.id);
                if (idx !== -1) {
                    this.dokumente[idx] = { ...this.dokumente[idx], ...result };
                }
                const label = result.url_status === 200 ? 'OK' : `HTTP ${result.url_status}`;
                Alpine.store('notify').success('URL-Status: ' + label);
            } catch (e) {
                Alpine.store('notify').error(e.message || 'URL-Prüfung fehlgeschlagen.');
            } finally {
                this.checkingIds = new Set([...this.checkingIds].filter(id => id !== dok.id));
            }
        },

        // ----- Dateinamen (und ggf. Titel) aller sichtbaren Dokumente neu ableiten
        async refreshAllDateinamen() {
            const candidates = this.dokumente.filter(d => d.quelle_url);
            if (candidates.length === 0) return;
            this.refreshingDateinamen = true;
            this.refreshDateinamenProgress = { done: 0, total: candidates.length };
            try {
                for (const dok of candidates) {
                    try {
                        const results = await api.post('/dokumente/refresh-dateinamen', { ids: [dok.id] });
                        const result = results[0];
                        if (result) {
                            const idx = this.dokumente.findIndex(d => d.id === dok.id);
                            if (idx !== -1) {
                                this.dokumente[idx] = { ...this.dokumente[idx], ...result };
                            }
                        }
                    } catch (e) {
                        // Einzelfehler ignorieren
                    } finally {
                        this.refreshDateinamenProgress.done++;
                    }
                }
                Alpine.store('notify').success(`${candidates.length} Dateiname(n) aktualisiert.`);
            } finally {
                this.refreshingDateinamen = false;
            }
        },

        // ----- Alle sichtbaren URLs prüfen ----------------------------------
        async checkAllVisible() {
            const candidates = this.dokumente.filter(d => d.quelle_url);
            if (candidates.length === 0) return;
            this.checkingAll = true;
            this.checkAllProgress = { done: 0, total: candidates.length };
            try {
                for (const dok of candidates) {
                    this.checkingIds = new Set([...this.checkingIds, dok.id]);
                    try {
                        const results = await api.post('/dokumente/url-check', { ids: [dok.id] });
                        const result = results[0];
                        const idx = this.dokumente.findIndex(d => d.id === dok.id);
                        if (idx !== -1) {
                            this.dokumente[idx] = { ...this.dokumente[idx], ...result };
                        }
                    } catch (e) {
                        // Einzelfehler ignorieren, weiter mit nächstem
                    } finally {
                        this.checkingIds = new Set([...this.checkingIds].filter(id => id !== dok.id));
                        this.checkAllProgress.done++;
                    }
                }
                Alpine.store('notify').success(`${candidates.length} URL(s) geprüft.`);
            } finally {
                this.checkingAll = false;
            }
        },

        // ----- Lokalen Spiegel anlegen --------------------------------------
        async mirrorPdf(dok) {
            this.mirroringIds = new Set([...this.mirroringIds, dok.id]);
            try {
                const updated = await api.post('/dokumente/' + dok.id + '/spiegel', {});
                const idx = this.dokumente.findIndex(d => d.id === dok.id);
                if (idx !== -1) {
                    this.dokumente[idx] = { ...this.dokumente[idx], ...updated };
                }
                Alpine.store('notify').success('PDF lokal gespiegelt.');
            } catch (e) {
                Alpine.store('notify').error(e.message || 'Spiegeln fehlgeschlagen.');
            } finally {
                this.mirroringIds = new Set([...this.mirroringIds].filter(id => id !== dok.id));
            }
        },

        // ----- Alle sichtbaren Dokumente herunterladen (spiegeln + URL-Check) --
        async downloadAll() {
            const candidates = this.dokumente.filter(d => d.quelle_url);
            if (candidates.length === 0) return;
            this.downloadingAll = true;
            this.downloadAllProgress = { done: 0, total: candidates.length, errors: 0 };
            for (const dok of candidates) {
                this.mirroringIds = new Set([...this.mirroringIds, dok.id]);
                try {
                    const updated = await api.post('/dokumente/' + dok.id + '/spiegel', {});
                    const idx = this.dokumente.findIndex(d => d.id === dok.id);
                    if (idx !== -1) {
                        this.dokumente[idx] = { ...this.dokumente[idx], ...updated };
                    }
                } catch (e) {
                    this.downloadAllProgress.errors++;
                } finally {
                    this.mirroringIds = new Set([...this.mirroringIds].filter(id => id !== dok.id));
                    this.downloadAllProgress.done++;
                }
            }
            this.downloadingAll = false;
            const { done, errors } = this.downloadAllProgress;
            if (errors === 0) {
                Alpine.store('notify').success(`${done} Dokument(e) heruntergeladen.`);
            } else {
                Alpine.store('notify').error(`${done - errors} von ${done} heruntergeladen, ${errors} Fehler.`);
            }
        },

        // ----- Löschen -------------------------------------------------------
        openDelete(dok) {
            this.deleteId          = dok.id;
            this.deleteConfirmOpen = true;
        },

        async doDelete() {
            this.deleting = true;
            try {
                await api.delete('/dokumente/' + this.deleteId);
                Alpine.store('notify').success('Dokument gelöscht.');
                this.deleteConfirmOpen = false;
                await this.load();
            } catch (e) {
                Alpine.store('notify').error(e.message || 'Löschen fehlgeschlagen.');
            } finally {
                this.deleting = false;
            }
        },

        // ----- Hilfsmethoden ------------------------------------------------

        isChecking(id) {
            return this.checkingIds.has(id);
        },

        isMirroring(id) {
            return this.mirroringIds.has(id);
        },

        urlStatusLabel(status) {
            if (!status) return '–';
            if (status === 200) return 'OK (200)';
            return 'HTTP ' + status;
        },

        urlStatusClass(status) {
            if (!status) return '';
            return status === 200 ? 'color-ok' : 'color-error';
        },

        formatGroesse(bytes) {
            if (!bytes) return '–';
            return (bytes / 1024).toFixed(1).replace('.', ',') + ' kB';
        },

        formatDate(iso) {
            if (!iso) return '–';
            return new Date(iso).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' });
        },

        typLabel(typ) {
            return { pdf: 'PDF', foto: 'Foto', scan: 'Scan', url: 'URL' }[typ] ?? typ ?? '–';
        },

        get isAdmin() {
            return Alpine.store('auth')?.isAdmin === true;
        },

        closeTopModal() {
            if (this.deleteConfirmOpen) { this.deleteConfirmOpen = false; return; }
            if (this.modalOpen)         { this.modalOpen         = false; return; }
        },
    }));
});
