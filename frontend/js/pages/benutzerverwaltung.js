document.addEventListener('alpine:init', () => {
    Alpine.data('benutzerverwaltungPage', () => ({

        // ----- Liste -------------------------------------------------------
        benutzer: [],
        loading: false,
        error: null,

        // ----- Filter ------------------------------------------------------
        filter: { benutzername: '', rolle: '', aktiv: '' },

        // ----- Modal -------------------------------------------------------
        modalOpen: false,
        modalMode: 'create',   // 'create' | 'edit'
        activeTab: 'stammdaten', // 'stammdaten' | 'audit'
        saving: false,
        formError: null,
        editId: null,
        editBenutzername: '',

        form: {
            benutzername: '',
            email:        '',
            rolle:        'editor',
            aktiv:        true,
        },

        // ----- Passwort-Reset (im Edit-Modus) ------------------------------
        resetSending: false,
        resetError:   null,
        resetSuccess: false,

        // ----- Audit-Log ---------------------------------------------------
        auditLog: [],
        auditLoading: false,

        // ----- Löschen -----------------------------------------------------
        deleteId: null,
        deleteName: '',
        deleteConfirmOpen: false,
        deleting: false,
        deleteError: null,

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
                if (this.filter.benutzername) params.set('benutzername', this.filter.benutzername);
                if (this.filter.rolle)        params.set('rolle',        this.filter.rolle);
                if (this.filter.aktiv !== '') params.set('aktiv',        this.filter.aktiv);
                const qs = params.toString() ? '?' + params.toString() : '';
                this.benutzer = await api.get('/benutzer' + qs);
            } catch (e) {
                this.error = e.message || 'Benutzer konnten nicht geladen werden.';
            } finally {
                this.loading = false;
            }
        },

        resetFilter() {
            this.filter = { benutzername: '', rolle: '', aktiv: '' };
            this.load();
        },

        // ----- Modal: Erstellen --------------------------------------------
        openCreate() {
            this.formError     = null;
            this.modalMode     = 'create';
            this.editId        = null;
            this.editBenutzername = '';
            this.activeTab     = 'stammdaten';
            this.auditLog      = [];
            this.form = { benutzername: '', email: '', rolle: 'editor', aktiv: true };
            this.modalOpen = true;
        },

        // ----- Modal: Bearbeiten -------------------------------------------
        async openEdit(b) {
            this.formError        = null;
            this.modalMode        = 'edit';
            this.editId           = b.id;
            this.editBenutzername = b.benutzername;
            this.activeTab        = 'stammdaten';
            this.auditLog         = [];
            this.resetSending = false;
            this.resetError   = null;
            this.resetSuccess = false;
            this.form = {
                benutzername: b.benutzername,
                email:        b.email || '',
                rolle:        b.rolle,
                aktiv:        b.aktiv === 1 || b.aktiv === true,
            };
            this.modalOpen = true;
            await this.loadAudit();
        },

        // ----- Audit-Log laden ---------------------------------------------
        async loadAudit() {
            if (!this.editId) return;
            this.auditLoading = true;
            try {
                this.auditLog = await api.get('/benutzer/' + this.editId + '/audit');
            } catch (e) {
                this.auditLog = [];
            } finally {
                this.auditLoading = false;
            }
        },

        // ----- Speichern ---------------------------------------------------
        async save() {
            this.saving    = true;
            this.formError = null;
            try {
                if (this.modalMode === 'create') {
                    await api.post('/benutzer', this.form);
                    Alpine.store('notify').success('Benutzer erstellt.');
                } else {
                    await api.put('/benutzer/' + this.editId, {
                        email: this.form.email,
                        rolle: this.form.rolle,
                        aktiv: this.form.aktiv,
                    });
                    Alpine.store('notify').success('Benutzer aktualisiert.');
                }
                this.modalOpen = false;
                await this.load();
            } catch (e) {
                this.formError = e.message || 'Speichern fehlgeschlagen.';
            } finally {
                this.saving = false;
            }
        },

        // ----- Passwort-Reset-Mail senden (Edit-Modus) ---------------------
        async sendReset() {
            this.resetSending = true;
            this.resetError   = null;
            this.resetSuccess = false;
            try {
                await api.post('/benutzer/' + this.editId + '/passwort-reset', {});
                this.resetSuccess = true;
                Alpine.store('notify').success('Passwort-Reset-Mail wurde gesendet.');
            } catch (e) {
                this.resetError = e.message || 'Versand fehlgeschlagen.';
            } finally {
                this.resetSending = false;
            }
        },

        // ----- Löschen: öffnen ---------------------------------------------
        openDelete(b) {
            this.deleteId           = b.id;
            this.deleteName         = b.benutzername;
            this.deleteError        = null;
            this.deleteConfirmOpen  = true;
        },

        // ----- Löschen: bestätigen -----------------------------------------
        async confirmDelete() {
            this.deleting    = true;
            this.deleteError = null;
            try {
                await api.delete('/benutzer/' + this.deleteId);
                Alpine.store('notify').success('Benutzer gelöscht.');
                this.deleteConfirmOpen = false;
                if (this.modalOpen && this.editId === this.deleteId) {
                    this.modalOpen = false;
                }
                await this.load();
            } catch (e) {
                this.deleteError = e.message || 'Löschen fehlgeschlagen.';
            } finally {
                this.deleting = false;
            }
        },

        // ----- Hilfsfunktionen ---------------------------------------------
        isOwnAccount(b) {
            const auth = Alpine.store('auth');
            return auth.user && auth.user.benutzername === b.benutzername;
        },

        formatDate(dt) {
            if (!dt) return '–';
            try {
                return new Date(dt).toLocaleString('de-DE', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit',
                });
            } catch {
                return dt;
            }
        },
    }));
});
