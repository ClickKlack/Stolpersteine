document.addEventListener('alpine:init', () => {
    Alpine.data('profilPage', () => ({

        loading:  false,
        error:    null,

        // Aktuelle Daten
        benutzername: '',
        rolle:        '',

        // E-Mail-Formular
        emailForm:     { email: '' },
        emailSaving:   false,
        emailError:    null,
        emailSuccess:  false,

        // Passwort-Formular
        pwForm: {
            aktuelles_passwort: '',
            neues_passwort:     '',
            neues_passwort2:    '',
        },
        pwSaving:  false,
        pwError:   null,
        pwSuccess: false,

        async init() {
            this.loading = true;
            this.error   = null;
            try {
                const data       = await api.get('/auth/profil');
                this.benutzername = data.benutzername;
                this.rolle        = data.rolle;
                this.emailForm.email = data.email || '';
            } catch (e) {
                this.error = e.message || 'Profil konnte nicht geladen werden.';
            } finally {
                this.loading = false;
            }
        },

        // ----- E-Mail speichern -------------------------------------------
        async saveEmail() {
            this.emailSaving = true;
            this.emailError  = null;
            this.emailSuccess = false;
            try {
                await api.put('/auth/profil', { email: this.emailForm.email });
                this.emailSuccess = true;
                Alpine.store('notify').success('E-Mail-Adresse gespeichert.');
            } catch (e) {
                this.emailError = e.message || 'Speichern fehlgeschlagen.';
            } finally {
                this.emailSaving = false;
            }
        },

        // ----- Passwort speichern -----------------------------------------
        get pwMatch() {
            return this.pwForm.neues_passwort === this.pwForm.neues_passwort2;
        },

        async savePassword() {
            this.pwError   = null;
            this.pwSuccess = false;

            if (!this.pwMatch) {
                this.pwError = 'Die neuen Passwörter stimmen nicht überein.';
                return;
            }
            if (this.pwForm.neues_passwort.length < 8) {
                this.pwError = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
                return;
            }

            this.pwSaving = true;
            try {
                await api.put('/auth/profil', {
                    aktuelles_passwort: this.pwForm.aktuelles_passwort,
                    neues_passwort:     this.pwForm.neues_passwort,
                });
                this.pwSuccess = true;
                this.pwForm = { aktuelles_passwort: '', neues_passwort: '', neues_passwort2: '' };
                Alpine.store('notify').success('Passwort geändert.');
            } catch (e) {
                this.pwError = e.message || 'Passwort konnte nicht geändert werden.';
            } finally {
                this.pwSaving = false;
            }
        },
    }));
});
