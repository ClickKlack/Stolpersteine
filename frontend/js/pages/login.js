document.addEventListener('alpine:init', () => {
    Alpine.data('loginPage', () => ({
        benutzername: '',
        passwort: '',
        loading: false,
        error: null,

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
    }));
});
