document.addEventListener('alpine:init', () => {
    Alpine.data('adressenPage', () => ({

        // Aktives Untermenü: 'lokationen' | 'staedte' | 'stadtteile' | 'strassen' | 'plz'
        sub: 'lokationen',

        // Gemeinsamer Ladezustand
        loading: false,
        error: null,

        // Listen
        lokationen:  [],
        staedte:     [],
        stadtteile:  [],
        strassen:    [],
        plzListe:    [],

        // Filter Lokationen (IDs für API-Call)
        filterStadtteilId: '',
        filterStrasseId:   '',
        filterPlzId:       '',

        // Suchfelder für Stadtteile / Straßen / PLZ Tabs
        searchStadtteil: '',
        searchStrasse:   '',
        searchPlz:       '',

        get filteredStadtteile() {
            const q = this.searchStadtteil.toLowerCase();
            return q ? this.stadtteile.filter(st => st.name.toLowerCase().includes(q) || (st.stadt_name || '').toLowerCase().includes(q)) : this.stadtteile;
        },
        get filteredStrassen() {
            const q = this.searchStrasse.toLowerCase();
            return q ? this.strassen.filter(s => s.name.toLowerCase().includes(q) || (s.stadt_name || '').toLowerCase().includes(q)) : this.strassen;
        },
        get filteredPlzListe() {
            const q = this.searchPlz.toLowerCase();
            return q ? this.plzListe.filter(p => p.plz.toLowerCase().includes(q) || (p.stadt_name || '').toLowerCase().includes(q)) : this.plzListe;
        },

        // Tipp-Suchfelder Lokationen-Filterleiste
        filterStadtId:    '',
        filterStadtteilQ: '',
        filterStrasseQ:   '',
        filterPlzQ:       '',
        filterLokOpen:    { stadtteil: false, strasse: false, plz: false },

        get filterStadtteileOpts() {
            let list = this.stadtteile;
            if (this.filterStadtId) list = list.filter(st => String(st.stadt_id) === String(this.filterStadtId));
            const q = this.filterStadtteilQ.toLowerCase();
            return (q ? list.filter(st => st.name.toLowerCase().includes(q)) : list).slice(0, 30);
        },
        get filterStrassenOpts() {
            let list = this.strassen;
            if (this.filterStadtId) list = list.filter(s => String(s.stadt_id) === String(this.filterStadtId));
            const q = this.filterStrasseQ.toLowerCase();
            return (q ? list.filter(s => s.name.toLowerCase().includes(q)) : list).slice(0, 30);
        },
        get filterPlzOpts() {
            let list = this.plzListe;
            if (this.filterStadtId) list = list.filter(p => String(p.stadt_id) === String(this.filterStadtId));
            const q = this.filterPlzQ.toLowerCase();
            return (q ? list.filter(p => p.plz.includes(q)) : list).slice(0, 30);
        },

        // Tipp-Suchfelder Edit-Modal (Lokationen)
        editStrasseQ:   '',
        editStadtteilQ: '',
        editPlzQ:       '',
        editLokOpen:    { strasse: false, stadtteil: false, plz: false },

        get editStrassenOpts() {
            const q = this.editStrasseQ.toLowerCase();
            const list = q ? this.strassen.filter(s => s.name.toLowerCase().includes(q) || (s.stadt_name || '').toLowerCase().includes(q)) : this.strassen;
            return list.slice(0, 20);
        },
        get editStadtteileOpts() {
            const q = this.editStadtteilQ.toLowerCase();
            const list = q ? this.stadtteile.filter(st => st.name.toLowerCase().includes(q) || (st.stadt_name || '').toLowerCase().includes(q)) : this.stadtteile;
            return list.slice(0, 20);
        },
        get editPlzOpts() {
            const q = this.editPlzQ.toLowerCase();
            const list = q ? this.plzListe.filter(p => p.plz.includes(q) || (p.stadt_name || '').toLowerCase().includes(q)) : this.plzListe;
            return list.slice(0, 20);
        },

        // Modal: 'edit' | 'delete' | null
        modal: null,
        modalFehler: null,
        deleting: false,
        saving: false,
        isNew: false,

        // Aktuell bearbeitetes / zu löschendes Objekt
        editObj: {},
        deleteObj: {},

        // ---------------------------------------------------------------

        async init() {
            await this.loadStaedte();
            // Stadtteile, Straßen, PLZ für Lokationen-Filter vorladen
            const [st, sr, pl] = await Promise.all([
                api.get('/adressen/alle-stadtteile'),
                api.get('/adressen/alle-strassen'),
                api.get('/adressen/alle-plz'),
            ]);
            this.stadtteile = st;
            this.strassen   = sr;
            this.plzListe   = pl;
            await this.loadSub('lokationen');
        },

        async loadSub(name) {
            this.sub   = name;
            this.error = null;
            this.modal = null;
            await this._load();
        },

        async _load() {
            this.loading = true;
            this.error   = null;
            try {
                switch (this.sub) {
                    case 'lokationen':
                        await this._loadLokationen();
                        break;
                    case 'staedte':
                        this.staedte = await api.get('/adressen/staedte');
                        break;
                    case 'stadtteile':
                        this.stadtteile = await api.get('/adressen/alle-stadtteile');
                        break;
                    case 'strassen':
                        this.strassen = await api.get('/adressen/alle-strassen');
                        break;
                    case 'plz':
                        this.plzListe = await api.get('/adressen/alle-plz');
                        break;
                }
            } catch (e) {
                this.error = e.message || 'Fehler beim Laden.';
            } finally {
                this.loading = false;
            }
        },

        async _loadLokationen() {
            const params = new URLSearchParams();
            if (this.filterStadtteilId) params.set('stadtteil_id', this.filterStadtteilId);
            if (this.filterStrasseId)   params.set('strasse_id',   this.filterStrasseId);
            if (this.filterPlzId)       params.set('plz_id',       this.filterPlzId);
            const qs = params.toString();
            this.lokationen = await api.get('/adressen/alle-lokationen' + (qs ? '?' + qs : ''));
        },

        async loadStaedte() {
            try {
                this.staedte = await api.get('/adressen/staedte');
            } catch {}
        },

        // ---------------------------------------------------------------
        // Filter-Lookups Lokationen-Filterleiste
        // ---------------------------------------------------------------

        selectFilterStadtteil(st) {
            this.filterStadtteilId       = st.id;
            this.filterStadtteilQ        = st.name;
            this.filterLokOpen.stadtteil = false;
            this._loadLokationen();
        },
        clearFilterStadtteil() {
            this.filterStadtteilId       = '';
            this.filterStadtteilQ        = '';
            this.filterLokOpen.stadtteil = false;
            this._loadLokationen();
        },

        selectFilterStrasse(s) {
            this.filterStrasseId       = s.id;
            this.filterStrasseQ        = s.name;
            this.filterLokOpen.strasse = false;
            this._loadLokationen();
        },
        clearFilterStrasse() {
            this.filterStrasseId       = '';
            this.filterStrasseQ        = '';
            this.filterLokOpen.strasse = false;
            this._loadLokationen();
        },

        selectFilterPlz(p) {
            this.filterPlzId       = p.id;
            this.filterPlzQ        = p.plz;
            this.filterLokOpen.plz = false;
            this._loadLokationen();
        },
        clearFilterPlz() {
            this.filterPlzId       = '';
            this.filterPlzQ        = '';
            this.filterLokOpen.plz = false;
            this._loadLokationen();
        },

        onFilterStadtChange() {
            // Stadt gewechselt → abhängige Filter zurücksetzen
            this.clearFilterStadtteil();
            this.clearFilterStrasse();
            this.clearFilterPlz();
        },

        // ---------------------------------------------------------------
        // Edit-Lookups (Lokationen-Modal)
        // ---------------------------------------------------------------

        selectEditStrasse(s) {
            this.editObj.strasse_id   = s.id;
            this.editStrasseQ         = s.name + (s.stadt_name ? ' (' + s.stadt_name + ')' : '');
            this.editLokOpen.strasse  = false;
        },
        selectEditStadtteil(st) {
            this.editObj.stadtteil_id   = st.id;
            this.editStadtteilQ         = st.name + (st.stadt_name ? ' (' + st.stadt_name + ')' : '');
            this.editLokOpen.stadtteil  = false;
        },
        clearEditStadtteil() {
            this.editObj.stadtteil_id = '';
            this.editStadtteilQ       = '';
        },
        selectEditPlz(p) {
            this.editObj.plz_id   = p.id;
            this.editPlzQ         = p.plz + (p.stadt_name ? ' (' + p.stadt_name + ')' : '');
            this.editLokOpen.plz  = false;
        },
        clearEditPlz() {
            this.editObj.plz_id = '';
            this.editPlzQ       = '';
        },

        _initEditLokLookups(obj) {
            const str = this.strassen.find(s => s.id == obj.strasse_id);
            this.editStrasseQ = str ? str.name + (str.stadt_name ? ' (' + str.stadt_name + ')' : '') : '';
            const st = this.stadtteile.find(s => s.id == obj.stadtteil_id);
            this.editStadtteilQ = st ? st.name + (st.stadt_name ? ' (' + st.stadt_name + ')' : '') : '';
            const p = this.plzListe.find(p => p.id == obj.plz_id);
            this.editPlzQ = p ? p.plz + (p.stadt_name ? ' (' + p.stadt_name + ')' : '') : '';
            this.editLokOpen = { strasse: false, stadtteil: false, plz: false };
        },

        // ---------------------------------------------------------------
        // Create
        // ---------------------------------------------------------------

        openCreate() {
            this.isNew       = true;
            this.modalFehler = null;
            const defaults = {
                staedte:    { name: '', wikidata_id: '' },
                stadtteile: { name: '', stadt_id: this.staedte[0]?.id ?? '', wikidata_id: '', wikipedia_stadtteil: '', wikipedia_stolpersteine: '' },
                strassen:   { name: '', stadt_id: this.staedte[0]?.id ?? '', wikidata_id: '', wikipedia_name: '' },
                plz:        { plz: '',  stadt_id: this.staedte[0]?.id ?? '' },
                lokationen: { strasse_id: '', stadtteil_id: '', plz_id: '' },
            };
            this.editObj = { ...(defaults[this.sub] ?? {}) };
            if (this.sub === 'lokationen') {
                this.editStrasseQ   = '';
                this.editStadtteilQ = '';
                this.editPlzQ       = '';
                this.editLokOpen    = { strasse: false, stadtteil: false, plz: false };
            }
            this.modal   = 'edit';
        },

        // ---------------------------------------------------------------
        // Edit
        // ---------------------------------------------------------------

        openEdit(obj) {
            this.isNew       = false;
            this.editObj     = { ...obj };
            this.modalFehler = null;
            this.modal       = 'edit';
            if (this.sub === 'lokationen') {
                this._initEditLokLookups(obj);
            }
        },

        async saveEdit() {
            this.saving      = true;
            this.modalFehler = null;
            try {
                const bodies = {
                    staedte:    { name: this.editObj.name,  wikidata_id: this.editObj.wikidata_id || null },
                    stadtteile: { name: this.editObj.name,  stadt_id: this.editObj.stadt_id, wikidata_id: this.editObj.wikidata_id || null, wikipedia_stadtteil: this.editObj.wikipedia_stadtteil || null, wikipedia_stolpersteine: this.editObj.wikipedia_stolpersteine || null },
                    strassen:   { name: this.editObj.name,  stadt_id: this.editObj.stadt_id, wikidata_id: this.editObj.wikidata_id || null, wikipedia_name: this.editObj.wikipedia_name || null },
                    plz:        { plz:  this.editObj.plz,   stadt_id: this.editObj.stadt_id },
                    lokationen: { strasse_id: this.editObj.strasse_id, stadtteil_id: this.editObj.stadtteil_id || null, plz_id: this.editObj.plz_id || null },
                };

                if (this.isNew) {
                    const createUrls = {
                        staedte:    '/adressen/staedte',
                        stadtteile: '/adressen/alle-stadtteile',
                        strassen:   '/adressen/alle-strassen',
                        plz:        '/adressen/alle-plz',
                        lokationen: '/adressen/alle-lokationen',
                    };
                    await api.post(createUrls[this.sub], bodies[this.sub]);
                } else {
                    const id = this.editObj.id ?? this.editObj.lokation_id;
                    const updateUrls = {
                        staedte:    `/adressen/staedte/${id}`,
                        stadtteile: `/adressen/alle-stadtteile/${id}`,
                        strassen:   `/adressen/alle-strassen/${id}`,
                        plz:        `/adressen/alle-plz/${id}`,
                        lokationen: `/adressen/alle-lokationen/${id}`,
                    };
                    await api.put(updateUrls[this.sub], bodies[this.sub]);
                }

                this.modal = null;
                Alpine.store('notify').success(this.isNew ? 'Angelegt.' : 'Gespeichert.');
                await this._load();
                // Lookup-Listen für Filter aktuell halten (ohne Sub-Navigation zu wechseln)
                const [st, sr, pl] = await Promise.all([
                    api.get('/adressen/alle-stadtteile'),
                    api.get('/adressen/alle-strassen'),
                    api.get('/adressen/alle-plz'),
                ]);
                this.stadtteile = st;
                this.strassen   = sr;
                this.plzListe   = pl;
                await this.loadStaedte();
            } catch (e) {
                this.modalFehler = e.message || 'Fehler beim Speichern.';
            } finally {
                this.saving = false;
            }
        },

        // ---------------------------------------------------------------
        // Delete
        // ---------------------------------------------------------------

        openDelete(obj) {
            this.deleteObj   = obj;
            this.modalFehler = null;
            this.modal       = 'delete';
        },

        async confirmDelete() {
            this.deleting    = true;
            this.modalFehler = null;
            const id         = this.deleteObj.id ?? this.deleteObj.lokation_id;
            const endpoints  = {
                staedte:    `/adressen/staedte/${id}`,
                stadtteile: `/adressen/alle-stadtteile/${id}`,
                strassen:   `/adressen/alle-strassen/${id}`,
                plz:        `/adressen/alle-plz/${id}`,
                lokationen: `/adressen/alle-lokationen/${id}`,
            };
            try {
                await api.delete(endpoints[this.sub]);
                this.modal = null;
                Alpine.store('notify').success('Gelöscht.');
                await this._load();
            } catch (e) {
                this.modalFehler = e.message || 'Löschen fehlgeschlagen.';
            } finally {
                this.deleting = false;
            }
        },

        // ---------------------------------------------------------------
        // Wikidata
        // ---------------------------------------------------------------

        openWikidata(wikidataId) {
            if (wikidataId) {
                window.open(`https://www.wikidata.org/wiki/${wikidataId}`, '_blank');
            }
        },

        // ---------------------------------------------------------------
        // Hilfsmethoden
        // ---------------------------------------------------------------

        deleteLabel(obj) {
            if (!obj || !obj.id) return '';
            switch (this.sub) {
                case 'staedte':    return obj.name;
                case 'stadtteile': return `${obj.name} (${obj.stadt_name})`;
                case 'strassen':   return `${obj.name} (${obj.stadt_name})`;
                case 'plz':        return `${obj.plz} (${obj.stadt_name})`;
                case 'lokationen': return `${obj.strasse_name}${obj.stadtteil_name ? ', ' + obj.stadtteil_name : ''}${obj.plz ? ' ' + obj.plz : ''}`;
            }
            return '';
        },

        modalTitle() {
            const labels = { staedte: 'Stadt', stadtteile: 'Stadtteil', strassen: 'Straße', plz: 'PLZ', lokationen: 'Lokation' };
            return (this.isNew ? 'Neue/r ' : '') + (labels[this.sub] ?? '') + (this.isNew ? '' : ' bearbeiten');
        },
    }));
});
