document.addEventListener('alpine:init', () => {
    Alpine.data('kartePage', () => ({
        loading: true,
        error: null,
        _map: null,
        _markers: null,

        async init() {
            this.loading = true;
            this.error   = null;
            try {
                const steine = await api.get('/public/stolpersteine');
                this._initMap(steine);
            } catch (e) {
                this.error = e.message || 'Karte konnte nicht geladen werden.';
            } finally {
                this.loading = false;
                // Größe neu berechnen, da Container während des Ladens versteckt war
                this.$nextTick(() => this._map?.invalidateSize());
            }
        },

        _initMap(steine) {
            if (this._map) return; // bereits initialisiert

            // Karte initialisieren (Magdeburg als Default-Center)
            this._map = L.map('karte-container').setView([52.1205, 11.6276], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende',
                maxZoom: 19,
            }).addTo(this._map);

            this._markers = L.markerClusterGroup ? L.markerClusterGroup() : L.featureGroup();

            steine.forEach(s => {
                if (!s.lat || !s.lon) return;

                const popup = L.popup({ maxWidth: 280 }).setContent(
                    `<div class="map-popup">
                        <strong>${this._formatName(s)}</strong><br>
                        <span class="popup-adresse">${this._formatAdresse(s)}</span><br>
                        <a href="#stein/${s.id}" class="popup-link">Zur Detailseite →</a>
                    </div>`
                );

                const marker = L.marker([s.lat, s.lon]).bindPopup(popup);
                this._markers.addLayer(marker);
            });

            this._map.addLayer(this._markers);

            // Karte auf alle Marker zoomen wenn vorhanden
            if (steine.filter(s => s.lat && s.lon).length > 0) {
                try { this._map.fitBounds(this._markers.getBounds(), { padding: [30, 30] }); } catch (_) {}
            }
        },

        _formatName(s) {
            const teile = [s.nachname, s.vorname].filter(Boolean);
            if (s.geburtsname) teile.push(`geb. ${s.geburtsname}`);
            return teile.join(', ');
        },

        _formatAdresse(s) {
            const teile = [s.strasse, s.hausnummer].filter(Boolean).join('\u00a0');
            return [teile, s.stadtteil].filter(Boolean).join(' · ');
        },
    }));
});
