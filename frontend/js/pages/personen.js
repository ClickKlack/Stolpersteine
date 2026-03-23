document.addEventListener('alpine:init', () => {
    Alpine.data('personenPage', () => ({

        // ----- Liste -------------------------------------------------------
        personen: [],
        loading: false,
        error: null,

        // ----- Filter ------------------------------------------------------
        filter: { name: '', status: '' },

        // ----- Modal -------------------------------------------------------
        modalOpen: false,
        modalMode: 'create',   // 'create' | 'edit'
        saving: false,
        formError: null,
        editId: null,

        form: {
            vorname:         '',
            nachname:        '',
            geburtsname:     '',
            // Geburtsdatum – zusammengesetzt aus Hilfsfeldern beim Speichern
            geburtsdatum_genauigkeit: '',   // '' | 'tag' | 'monat' | 'jahr'
            geburtsdatum_voll:        '',   // bei genauigkeit 'tag'
            geburtsdatum_jahr:        '',   // bei 'monat' und 'jahr'
            geburtsdatum_monat:       '',   // bei 'monat'
            // Sterbedatum
            sterbedatum_genauigkeit:  '',
            sterbedatum_voll:         '',
            sterbedatum_jahr:         '',
            sterbedatum_monat:        '',
            // Weitere Felder
            biografie_kurz:           '',
            wikipedia_name:           '',
            wikidata_id_person:       '',
        },

        // ----- Löschen -----------------------------------------------------
        deleteId: null,
        deleteConfirmOpen: false,
        deletePersonName: '',
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
                if (this.filter.name)   params.set('name',   this.filter.name);
                if (this.filter.status) params.set('status', this.filter.status);
                const qs = params.toString() ? '?' + params.toString() : '';
                this.personen = await api.get('/personen' + qs);
            } catch (e) {
                this.error = e.message || 'Personen konnten nicht geladen werden.';
            } finally {
                this.loading = false;
            }
        },

        resetFilter() {
            this.filter = { name: '', status: '' };
            this.load();
        },

        // ----- Modal öffnen ------------------------------------------------
        openCreate() {
            this.formError = null;
            this.modalMode = 'create';
            this.editId    = null;
            this.form = {
                vorname: '', nachname: '', geburtsname: '',
                geburtsdatum_genauigkeit: 'tag', geburtsdatum_voll: '',
                geburtsdatum_jahr: '', geburtsdatum_monat: '',
                sterbedatum_genauigkeit: 'tag', sterbedatum_voll: '',
                sterbedatum_jahr: '', sterbedatum_monat: '',
                biografie_kurz: '', wikipedia_name: '', wikidata_id_person: '',
                biografie_dok_url: null, biografie_dok_titel: null,
                status: 'validierung',
            };
            this.modalOpen = true;
            this.$nextTick(() => document.getElementById('p-nachname')?.focus());
        },

        openEdit(person) {
            this.formError = null;
            this.modalMode = 'edit';
            this.editId    = person.id;

            const geb  = this._decompose(person.geburtsdatum, person.geburtsdatum_genauigkeit);
            const ster = this._decompose(person.sterbedatum,  person.sterbedatum_genauigkeit);

            this.form = {
                vorname:            person.vorname            ?? '',
                nachname:           person.nachname           ?? '',
                geburtsname:        person.geburtsname        ?? '',
                geburtsdatum_genauigkeit: person.geburtsdatum_genauigkeit ?? '',
                geburtsdatum_voll:   geb.voll,
                geburtsdatum_jahr:   geb.jahr,
                geburtsdatum_monat:  geb.monat,
                sterbedatum_genauigkeit:  person.sterbedatum_genauigkeit ?? '',
                sterbedatum_voll:    ster.voll,
                sterbedatum_jahr:    ster.jahr,
                sterbedatum_monat:   ster.monat,
                biografie_kurz:      person.biografie_kurz      ?? '',
                wikipedia_name:      person.wikipedia_name      ?? '',
                wikidata_id_person:  person.wikidata_id_person  ?? '',
                biografie_dok_url:   person.biografie_dok_url   ?? null,
                biografie_dok_titel: person.biografie_dok_titel ?? null,
                status:              person.status              ?? 'validierung',
            };
            this.modalOpen = true;
            this.$nextTick(() => document.getElementById('p-nachname')?.focus());
        },

        closeModal() {
            this.modalOpen = false;
        },

        // ----- Speichern ---------------------------------------------------
        async save() {
            if (!this.form.nachname.trim()) {
                this.formError = 'Nachname ist erforderlich.';
                return;
            }
            this.saving    = true;
            this.formError = null;
            try {
                const payload = {
                    vorname:            this.form.vorname            || null,
                    nachname:           this.form.nachname,
                    geburtsname:        this.form.geburtsname        || null,
                    geburtsdatum:       this._compose('geburtsdatum'),
                    geburtsdatum_genauigkeit: this.form.geburtsdatum_genauigkeit || null,
                    sterbedatum:        this._compose('sterbedatum'),
                    sterbedatum_genauigkeit:  this.form.sterbedatum_genauigkeit  || null,
                    biografie_kurz:     this.form.biografie_kurz     || null,
                    wikipedia_name:     this.form.wikipedia_name      || null,
                    wikidata_id_person: this.form.wikidata_id_person  || null,
                    status:             this.form.status || 'validierung',
                };

                if (this.modalMode === 'create') {
                    await api.post('/personen', payload);
                    Alpine.store('notify').success('Person angelegt.');
                } else {
                    await api.put('/personen/' + this.editId, payload);
                    Alpine.store('notify').success('Person gespeichert.');
                }
                this.modalOpen = false;
                await this.load();
            } catch (e) {
                this.formError = e.message || 'Speichern fehlgeschlagen.';
            } finally {
                this.saving = false;
            }
        },

        // ----- Löschen -----------------------------------------------------
        openDelete(person) {
            this.deleteId          = person.id;
            this.deletePersonName  = [person.vorname, person.nachname].filter(Boolean).join(' ');
            this.deleteConfirmOpen = true;
        },

        closeDelete() {
            this.deleteConfirmOpen = false;
        },

        async doDelete() {
            this.deleting = true;
            try {
                await api.delete('/personen/' + this.deleteId);
                Alpine.store('notify').success('Person gelöscht.');
                this.deleteConfirmOpen = false;
                await this.load();
            } catch (e) {
                Alpine.store('notify').error(e.message || 'Löschen fehlgeschlagen.');
            } finally {
                this.deleting = false;
            }
        },

        // ----- Hilfsfunktionen ---------------------------------------------

        statusLabel(s) {
            return { ok: 'Ok', validierung: 'Validierung' }[s] ?? s;
        },

        // ISO-Datum anzeigen je nach Genauigkeit
        formatDate(iso, genauigkeit) {
            if (!iso) return '–';
            const [y, m, d] = iso.split('-');
            if (genauigkeit === 'jahr')  return y;
            if (genauigkeit === 'monat') return `${m}/${y}`;
            return d ? `${d}.${m}.${y}` : iso;   // 'tag' oder null
        },

        // ISO-Datum aus Formular-Hilfsfeldern zusammensetzen
        _compose(prefix) {
            const g = this.form[prefix + '_genauigkeit'];
            if (!g || g === 'tag') {
                return this.form[prefix + '_voll'] || null;
            }
            const jahr  = (this.form[prefix + '_jahr'] || '').trim();
            if (!jahr) return null;
            if (g === 'jahr')  return `${jahr}-01-01`;
            const monat = (this.form[prefix + '_monat'] || '01').toString().padStart(2, '0');
            return `${jahr}-${monat}-01`;   // 'monat'
        },

        // ISO-Datum in Formular-Hilfsfelder zerlegen
        _decompose(iso, genauigkeit) {
            if (!iso) return { voll: '', jahr: '', monat: '' };
            const [y, m] = iso.split('-');
            if (!genauigkeit || genauigkeit === 'tag') return { voll: iso, jahr: '', monat: '' };
            if (genauigkeit === 'monat') return { voll: '', jahr: y, monat: m };
            return { voll: '', jahr: y, monat: '' };   // 'jahr'
        },
    }));
});
