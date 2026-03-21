// Token sofort beim Skript-Laden aus dem Hash auslesen –
// bevor der Alpine-Router location.hash überschreiben kann.
const _pendingResetToken = (() => {
    const match = window.location.hash.match(/^#passwort-reset\?token=([a-f0-9]+)$/i);
    return match ? match[1] : null;
})();

document.addEventListener('alpine:init', () => {
    Alpine.data('loginPage', () => ({
        // view: 'login' | 'forgotPassword' | 'forgotSent' | 'resetPassword' | 'resetDone'
        view: 'login',

        // Login
        benutzername: '',
        passwort:     '',
        loading:      false,
        error:        null,

        // Passwort vergessen
        forgotInput:   '',
        forgotLoading: false,

        // Passwort zurücksetzen
        resetToken:      '',
        neuesPasswort:   '',
        neuesPasswort2:  '',
        resetLoading:    false,
        resetError:      null,

        init() {
            // Token wurde beim Skript-Laden gespeichert (vor Router-Initialisierung)
            if (_pendingResetToken) {
                this.resetToken = _pendingResetToken;
                this.view = 'resetPassword';
                history.replaceState(null, '', window.location.pathname);
            }
        },

        // ----- Login -------------------------------------------------------
        async submit() {
            if (!this.benutzername || !this.passwort) return;

            this.loading = true;
            this.error   = null;

            try {
                await api.post('/auth/login', {
                    benutzername: this.benutzername,
                    passwort:     this.passwort,
                });
                await Alpine.store('auth').check();
                Alpine.store('router').go('dashboard');
            } catch (e) {
                this.error = e.message || 'Anmeldung fehlgeschlagen.';
            } finally {
                this.loading = false;
            }
        },

        // ----- Passwort vergessen ------------------------------------------
        showForgot() {
            this.forgotInput = '';
            this.view = 'forgotPassword';
        },

        async submitForgot() {
            if (!this.forgotInput.trim()) return;

            this.forgotLoading = true;
            try {
                await api.post('/auth/passwort-vergessen', {
                    benutzername_oder_email: this.forgotInput.trim(),
                });
            } catch (_) {
                // Fehler ignorieren – immer gleiche Ansicht zeigen (Enumeration-Schutz)
            } finally {
                this.forgotLoading = false;
                this.view = 'forgotSent';
            }
        },

        // ----- Passwort zurücksetzen ---------------------------------------
        get resetPasswordsMatch() {
            return this.neuesPasswort === this.neuesPasswort2;
        },

        async submitReset() {
            this.resetError = null;

            if (this.neuesPasswort.length < 8) {
                this.resetError = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
                return;
            }
            if (!this.resetPasswordsMatch) {
                this.resetError = 'Die Passwörter stimmen nicht überein.';
                return;
            }

            this.resetLoading = true;
            try {
                await api.post('/auth/passwort-reset', {
                    token:         this.resetToken,
                    neues_passwort: this.neuesPasswort,
                });
                this.view = 'resetDone';
            } catch (e) {
                this.resetError = e.message || 'Der Link ist ungültig oder abgelaufen.';
            } finally {
                this.resetLoading = false;
            }
        },

        backToLogin() {
            this.error       = null;
            this.benutzername = '';
            this.passwort    = '';
            this.view = 'login';
        },
    }));
});
