document.addEventListener('alpine:init', () => {
    Alpine.data('exportPage', () => ({

        // ----- Aktive Kategorie ----------------------------------------------
        kategorie: 'wikipedia',

        // ----- Wikipedia Sub-Tab ---------------------------------------------
        wpTab:       'export',
        wpTemplates: [],
        tplLaden:    false,
        tplFehler:   null,

        // ----- Stadtteile mit Wikipedia-Stolpersteinliste --------------------
        stadtteile:    [],
        loading:       false,
        error:         null,

        // ----- Auswahl -------------------------------------------------------
        gewaehlterStadtteil: null,

        // ----- Export-Ergebnis -----------------------------------------------
        exportLaden:   false,
        exportFehler:  null,
        wikitext:      null,
        anzahl:        0,
        wikiseiteName: null,

        // ----- Kopierstatus --------------------------------------------------
        kopiert: false,

        // ----- Wikipedia live (Vergleich) ------------------------------------
        diffLaden:    false,
        diffFehler:   null,
        wikitextLive: null,

        // ----- Diff-Ergebnis -------------------------------------------------
        diffPaare:          null,
        diffNurAenderungen: true,

        // ----- OSM -----------------------------------------------------------
        osmTab:              'diff',       // 'diff' | 'templates'
        osmScopes:           [],           // [{id: null, name: 'Gesamte Stadt'}, ...Stadtteile]
        osmGewaehlterScope:  null,         // null = Gesamte Stadt, sonst {id, name}
        osmLaden:            false,
        osmFehler:           null,
        osmDiffDaten:        null,
        osmFilter:           'unterschiede', // 'alle' | 'unterschiede' | 'nur_lokal' | 'nur_osm' | 'nicht_freigegeben'
        osmAufgeklappt:      {},
        osmTemplates:        [],
        osmTplLaden:         false,
        osmTplFehler:        null,

        // ----- Verfügbare Platzhalter je Template-Name ----------------------
        tplPlatzhalter: {
            seite: [
                { gruppe: 'Seite', items: [
                    { key: '[[SEITE.STADTTEIL]]',                info: 'Name des Stadtteils' },
                    { key: '[[SEITE.STADTTEIL_WIKIDATA]]',       info: 'Wikidata-ID des Stadtteils' },
                    { key: '[[SEITE.STADTTEIL_WIKIPEDIA]]',      info: 'Wikipedia-Artikel des Stadtteils (Seitentitel)' },
                    { key: '[[SEITE.STADTTEIL_WIKIPEDIA_LINK]]', info: 'Wikipedia-Link des Stadtteils: [[Titel|Name]] oder [[Titel]] wenn identisch' },
                    { key: '[[SEITE.STOLPERSTEINE_WIKIPEDIA]]',  info: 'Wikipedia-Seite der Stolpersteinliste' },
                    { key: '[[SEITE.ZEILEN]]',              info: 'Alle generierten Tabellenzeilen' },
                    { key: '[[SEITE.ANZAHL_ZEILEN]]',       info: 'Anzahl der Stolpersteine' },
                ]},
            ],
            zeile: [
                { gruppe: 'Person', items: [
                    { key: '[[PERSON.NAME_VOLL]]',      info: 'Nachname, Vorname (geb. Geburtsname)' },
                    { key: '[[PERSON.VORNAME]]',        info: 'Vorname' },
                    { key: '[[PERSON.NACHNAME]]',       info: 'Nachname' },
                    { key: '[[PERSON.GEBURTSNAME]]',    info: 'Geburtsname' },
                    { key: '[[PERSON.GEBURTSDATUM]]',   info: 'Geburtsdatum (deutsch formatiert)' },
                    { key: '[[PERSON.STERBEDATUM]]',    info: 'Sterbedatum (deutsch formatiert)' },
                    { key: '[[PERSON.BIOGRAFIE_KURZ]]', info: 'Kurzbiografie' },
                    { key: '[[PERSON.BIOGRAFIE_LANG]]', info: 'Biografie: Kurztext + Link zum Biografie-Dokument (kombiniert)' },
                    { key: '[[PERSON.WIKIPEDIA_NAME]]', info: 'Wikipedia-Artikel der Person' },
                    { key: '[[PERSON.WIKIDATA_ID]]',    info: 'Wikidata-ID der Person' },
                ]},
                { gruppe: 'Ort', items: [
                    { key: '[[ORT.ADRESSE]]',              info: 'Straße + Hausnummer + Beschreibung (kombiniert)' },
                    { key: '[[ORT.STRASSE]]',              info: 'Straßenname' },
                    { key: '[[ORT.HAUSNUMMER]]',           info: 'Hausnummer (optional)' },
                    { key: '[[ORT.STRASSE_WIKIPEDIA]]',    info: 'Wikipedia-Artikel der Straße' },
                    { key: '[[ORT.STADTTEIL]]',            info: 'Stadtteil' },
                    { key: '[[ORT.PLZ]]',                  info: 'Postleitzahl' },
                    { key: '[[ORT.BESCHREIBUNG]]',         info: 'Beschreibung des Verlegeorts' },
                    { key: '[[ORT.BEMERKUNG_HISTORISCH]]', info: 'Historische Bemerkung' },
                ]},
                { gruppe: 'Stein', items: [
                    { key: '[[STEIN.INSCHRIFT_BR]]',      info: 'Inschrift in Großbuchstaben, Zeilenumbrüche als <br />' },
                    { key: '[[STEIN.INSCHRIFT]]',         info: 'Inschrift in Großbuchstaben' },
                    { key: '[[STEIN.VERLEGEDATUM]]',      info: 'Verlegedatum (DD.MM.YYYY)' },
                    { key: '[[STEIN.LAT]]',               info: 'Breitengrad' },
                    { key: '[[STEIN.LON]]',               info: 'Längengrad' },
                    { key: '[[STEIN.WIKIMEDIA_COMMONS]]', info: 'Wikimedia-Commons-Dateiname' },
                    { key: '[[STEIN.FOTO_AUTOR]]',        info: 'Foto-Autor' },
                    { key: '[[STEIN.FOTO_LIZENZ]]',       info: 'Foto-Lizenzname' },
                    { key: '[[STEIN.FOTO_LIZENZ_URL]]',   info: 'Foto-Lizenz-URL' },
                    { key: '[[STEIN.WIKIDATA_ID]]',       info: 'Wikidata-ID des Steins' },
                    { key: '[[STEIN.OSM_ID]]',            info: 'OpenStreetMap-ID' },
                    { key: '[[STEIN.STATUS]]',            info: 'Status' },
                    { key: '[[STEIN.ZUSTAND]]',           info: 'Zustand' },
                ]},
                { gruppe: 'Dokument (Biografie)', items: [
                    { key: '[[DOK.URL]]',         info: 'URL des Biografie-Dokuments' },
                    { key: '[[DOK.DATEINAME]]',   info: 'Dateiname des Dokuments' },
                    { key: '[[DOK.LIZENZ]]',      info: 'Quellenangabe (Domain)' },
                    { key: '[[DOK.TYP_GROESSE]]', info: 'Typ und Größe, z.B. (PDF; 173,1 kB)' },
                    { key: '[[DOK.GROESSE_KB]]',  info: 'Dateigröße, z.B. 173,1 kB' },
                ]},
            ],
        },

        async init() {
            this.loading = true;
            this.error   = null;
            try {
                const [alle, mitSteinen] = await Promise.all([
                    api.get('/adressen/alle-stadtteile'),
                    api.get('/adressen/alle-stadtteile?mit_freigegebenen_steinen=1'),
                ]);
                this.stadtteile = alle.filter(st => st.wikipedia_stolpersteine);
                // OSM-Scopes: Gesamte Stadt + nur Stadtteile mit freigegebenen Steinen
                this.osmScopes = [
                    { id: null, name: 'Gesamte Stadt' },
                    ...mitSteinen.map(st => ({ id: st.id, name: st.name })),
                ];
                this.osmGewaehlterScope = this.osmScopes[0];
            } catch (e) {
                this.error = e.message || 'Stadtteile konnten nicht geladen werden.';
            } finally {
                this.loading = false;
            }
        },

        kategorieWaehlen(kat) {
            this.kategorie           = kat;
            this.wikitext            = null;
            this.exportFehler        = null;
            this.kopiert             = false;
            this.gewaehlterStadtteil = null;
            if (kat === 'openstreetmap' && this.osmScopes.length === 0) {
                this.osmGewaehlterScope = { id: null, name: 'Gesamte Stadt' };
            }
        },

        async wpTabWaehlen(tab) {
            this.wpTab = tab;
            if (tab === 'templates' && this.wpTemplates.length === 0) {
                await this.ladeWpTemplates();
            }
        },

        async ladeWpTemplates() {
            this.tplLaden  = true;
            this.tplFehler = null;
            try {
                const liste = await api.get('/templates?zielsystem=wikipedia');
                this.wpTemplates = liste.map(t => ({ ...t, _saving: false, _fehler: null, _ok: false }));
            } catch (e) {
                this.tplFehler = e.message || 'Templates konnten nicht geladen werden.';
            } finally {
                this.tplLaden = false;
            }
        },

        async templateSpeichern(tpl) {
            tpl._saving = true;
            tpl._fehler = null;
            tpl._ok     = false;
            try {
                const updated = await api.put('/templates/' + tpl.id, { inhalt: tpl.inhalt });
                const neueVersion = updated.id !== tpl.id;
                tpl.id           = updated.id;
                tpl.version      = updated.version;
                tpl.geaendert_am = updated.geaendert_am;
                tpl._ok      = neueVersion ? '✓ Gespeichert als Version ' + updated.version : '✓ Keine Änderung – Version unverändert';
                setTimeout(() => { tpl._ok = false; }, 3000);
            } catch (e) {
                tpl._fehler = e.message || 'Speichern fehlgeschlagen.';
            } finally {
                tpl._saving = false;
            }
        },

        async vergleichen() {
            if (!this.gewaehlterStadtteil) return;
            this.diffLaden    = true;
            this.diffFehler   = null;
            this.wikitextLive = null;
            this.diffPaare    = null;
            try {
                const result = await api.get(
                    '/export/wikipedia/diff?stadtteil_id=' + this.gewaehlterStadtteil.id
                );
                if (!result.live) {
                    this.diffFehler = 'Wikipedia-Seite "' + result.seitenname + '" konnte nicht abgerufen werden.';
                    return;
                }
                this.wikitextLive = result.live;
                if (this.wikitext) this.berechneDiffPaare();
            } catch (e) {
                this.diffFehler = e.message || 'Vergleich fehlgeschlagen.';
            } finally {
                this.diffLaden = false;
            }
        },

        berechneDiffPaare() {
            const esc     = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            const toLines = s => {
                const lines = (s || '')
                    .replace(/\r\n/g, '\n').replace(/\r/g, '\n')
                    .normalize('NFC')
                    .split('\n');
                if (lines.length && lines[lines.length - 1] === '') lines.pop();
                return lines;
            };
            const charHtml = (liveStr, lokalStr) => {
                let lh = '', kh = '';
                for (const c of Diff.diffChars(liveStr, lokalStr)) {
                    const v = esc(c.value);
                    if      (c.added)   kh += `<mark style="background:rgba(0,160,100,0.3);border-radius:2px;padding:0 1px;">${v}</mark>`;
                    else if (c.removed) lh += `<mark style="background:rgba(210,100,0,0.3);border-radius:2px;padding:0 1px;">${v}</mark>`;
                    else { kh += v; lh += v; }
                }
                return { lokalHtml: kh, liveHtml: lh };
            };

            const liveLines  = toLines(this.wikitextLive);
            const lokalLines = toLines(this.wikitext);
            if (!liveLines.length || !lokalLines.length) { this.diffPaare = null; return; }

            const changes = Diff.diffArrays(liveLines, lokalLines);
            const paare = [];
            let i = 0;
            while (i < changes.length) {
                const block = changes[i];
                if (!block.added && !block.removed) {
                    for (const line of block.value) {
                        paare.push({ lokal: line, live: line, lokalHtml: esc(line), liveHtml: esc(line), typ: 'gleich' });
                    }
                    i++;
                } else {
                    const removedLines = [];
                    const addedLines   = [];
                    while (i < changes.length && (changes[i].added || changes[i].removed)) {
                        if (changes[i].removed) removedLines.push(...changes[i].value);
                        else                    addedLines.push(...changes[i].value);
                        i++;
                    }
                    const max = Math.max(removedLines.length, addedLines.length);
                    for (let j = 0; j < max; j++) {
                        const lokal = j < addedLines.length   ? addedLines[j]   : null;
                        const live  = j < removedLines.length ? removedLines[j] : null;
                        const html  = (lokal !== null && live !== null)
                            ? charHtml(live, lokal)
                            : { lokalHtml: lokal !== null ? esc(lokal) : null,
                                liveHtml:  live  !== null ? esc(live)  : null };
                        paare.push({ lokal, live, ...html, typ: 'geaendert' });
                    }
                }
            }
            this.diffPaare = paare;
        },

        // =====================================================================
        // OSM-Methoden
        // =====================================================================

        async osmTabWaehlen(tab) {
            this.osmTab = tab;
            if (tab === 'templates' && this.osmTemplates.length === 0) {
                await this.ladeOsmTemplates();
            }
        },

        async ladeOsmTemplates() {
            this.osmTplLaden  = true;
            this.osmTplFehler = null;
            try {
                const liste = await api.get('/templates?zielsystem=osm');
                this.osmTemplates = liste.map(t => ({ ...t, _saving: false, _fehler: null, _ok: false }));
            } catch (e) {
                this.osmTplFehler = e.message || 'OSM-Templates konnten nicht geladen werden.';
            } finally {
                this.osmTplLaden = false;
            }
        },

        async osmTemplateSpeichern(tpl) {
            tpl._fehler = null;
            tpl._ok     = false;

            // JSON-Validierung für Tags-Template
            if (tpl.name === 'tags') {
                try {
                    const parsed = JSON.parse(tpl.inhalt);
                    if (typeof parsed !== 'object' || Array.isArray(parsed)) {
                        tpl._fehler = 'Ungültiges JSON: Muss ein Objekt sein.';
                        return;
                    }
                } catch (e) {
                    tpl._fehler = 'Ungültiges JSON: ' + e.message;
                    return;
                }
            }

            tpl._saving = true;
            try {
                const updated = await api.put('/templates/' + tpl.id, { inhalt: tpl.inhalt });
                const neueVersion = updated.id !== tpl.id;
                tpl.id           = updated.id;
                tpl.version      = updated.version;
                tpl.geaendert_am = updated.geaendert_am;
                tpl._ok = neueVersion ? '✓ Gespeichert als Version ' + updated.version : '✓ Keine Änderung – Version unverändert';
                setTimeout(() => { tpl._ok = false; }, 3000);
            } catch (e) {
                tpl._fehler = e.message || 'Speichern fehlgeschlagen.';
            } finally {
                tpl._saving = false;
            }
        },

        osmScopeWaehlen(scope) {
            this.osmGewaehlterScope = scope;
            this.osmDiffDaten = null;
            this.osmFehler    = null;
        },

        async osmAbgleichen() {
            this.osmLaden    = true;
            this.osmFehler   = null;
            this.osmDiffDaten = null;
            this.osmAufgeklappt = {};
            try {
                const scopeId = this.osmGewaehlterScope?.id;
                const url = scopeId !== null && scopeId !== undefined
                    ? '/export/osm/diff?stadtteil_id=' + scopeId
                    : '/export/osm/diff';
                this.osmDiffDaten = await api.get(url);
            } catch (e) {
                this.osmFehler = e.message || 'OSM-Abgleich fehlgeschlagen.';
            } finally {
                this.osmLaden = false;
            }
        },

        osmHerunterladen() {
            const scopeId = this.osmGewaehlterScope?.id;
            const url = CONFIG.apiBase + '/export/osm/datei'
                + (scopeId !== null && scopeId !== undefined ? '?stadtteil_id=' + scopeId : '');
            const a = document.createElement('a');
            a.href     = url;
            a.download = scopeId ? 'stolpersteine-stadtteil-' + scopeId + '.osm' : 'stolpersteine-gesamt.osm';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        },

        osmToggleZeile(lokalId) {
            this.osmAufgeklappt[lokalId] = !this.osmAufgeklappt[lokalId];
        },

        osmGefilterteGematchte() {
            if (!this.osmDiffDaten) return [];
            const alle = this.osmDiffDaten.gematchte;
            if (this.osmFilter === 'alle' || this.osmFilter === 'unterschiede') {
                return this.osmFilter === 'unterschiede'
                    ? alle.filter(g => g.hat_unterschiede)
                    : alle;
            }
            return alle;
        },

        async osmIdUebernehmen(lokalId, osmId) {
            try {
                await api.put('/stolpersteine/' + lokalId, { osm_id: osmId });
                // Diff aktualisieren: match_typ ändern, osm_id in lokal setzen
                if (this.osmDiffDaten) {
                    const g = this.osmDiffDaten.gematchte.find(x => x.lokal_id === lokalId);
                    if (g) {
                        g.match_typ = 'osm_id';
                        g.osm_id    = osmId;
                    }
                }
            } catch (e) {
                alert('Fehler beim Übernehmen der OSM-ID: ' + (e.message || 'Unbekannter Fehler'));
            }
        },

        osmTagFarbe(diff) {
            if (diff.gleich) return 'color:var(--pico-color-green-500,#2d9c55)';
            if (diff.in_lokal && diff.in_osm) return 'color:var(--pico-color-orange-500,#d97706)';
            if (diff.in_lokal && !diff.in_osm) return 'color:var(--pico-color-red-500,#dc2626)';
            return 'color:var(--pico-color-blue-500,#2563eb)';
        },

        osmKoordFarbe(abstandM) {
            if (abstandM === null) return '';
            if (abstandM > 15) return 'color:var(--pico-color-red-500,#dc2626)';
            if (abstandM > 5)  return 'color:var(--pico-color-orange-500,#d97706)';
            return '';
        },

        osmPlatzhalter: {
            abfrage: [
                { gruppe: 'Stadt', items: [
                    { key: '[[STADT.NAME]]', info: 'Stadtname aus der Konfiguration' },
                ]},
            ],
            tags: [
                { gruppe: 'Person', items: [
                    { key: '[[PERSON.NAME_OSM]]',    info: 'Vorname Nachname (geb. Geburtsname) – OSM-Format' },
                    { key: '[[PERSON.NAME_VOLL]]',   info: 'Nachname, Vorname geb. Geburtsname – Wikipedia-Format' },
                    { key: '[[PERSON.VORNAME]]',     info: 'Vorname' },
                    { key: '[[PERSON.NACHNAME]]',    info: 'Nachname' },
                    { key: '[[PERSON.WIKIDATA_ID]]', info: 'Wikidata-ID der Person (für subject:wikidata)' },
                ]},
                { gruppe: 'Ort', items: [
                    { key: '[[ORT.STRASSE]]',    info: 'Straßenname (für addr:street)' },
                    { key: '[[ORT.HAUSNUMMER]]', info: 'Hausnummer (für addr:housenumber)' },
                    { key: '[[ORT.PLZ]]',        info: 'Postleitzahl (für addr:postcode)' },
                    { key: '[[ORT.STADTTEIL]]',  info: 'Stadtteil' },
                    { key: '[[ORT.STADT]]',      info: 'Stadtname (für addr:city)' },
                ]},
                { gruppe: 'Stein', items: [
                    { key: '[[STEIN.INSCHRIFT_ORIGINAL]]', info: 'Inschrift wie in der DB, Zeilenumbrüche als " | " (für inscription)' },
                    { key: '[[STEIN.INSCHRIFT]]',          info: 'Inschrift in Großbuchstaben' },
                    { key: '[[STEIN.VERLEGEDATUM_ISO]]',   info: 'Verlegedatum ISO YYYY-MM-DD (für start_date)' },
                    { key: '[[STEIN.WIKIDATA_ID]]',        info: 'Wikidata-ID des Steins (für wikidata)' },
                    { key: '[[STEIN.OSM_ID]]',             info: 'OSM-ID' },
                    { key: '[[STEIN.WIKIMEDIA_COMMONS]]',  info: 'Wikimedia-Commons-Dateiname (für wikimedia_commons)' },
                ]},
                { gruppe: 'Dokument', items: [
                    { key: '[[DOK.URL]]', info: 'URL des Biografie-Dokuments der Person (für website); leer = Tag wird weggelassen' },
                ]},
            ],
        },

        osmPlatzhalterEinfuegen(tpl, placeholder) {
            const ta = document.getElementById('osm-tpl-textarea-' + tpl.id);
            if (ta) {
                ta.focus();
                document.execCommand('insertText', false, placeholder);
            } else {
                tpl.inhalt += placeholder;
            }
        },

        // =====================================================================
        // Ende OSM-Methoden
        // =====================================================================

        platzhalterEinfuegen(tpl, placeholder) {
            const ta = document.getElementById('tpl-textarea-' + tpl.id);
            if (ta) {
                ta.focus();
                document.execCommand('insertText', false, placeholder);
                // tpl.inhalt wird via 'input'-Event durch x-model automatisch aktualisiert
            } else {
                tpl.inhalt += placeholder;
            }
        },

        stadttteilWaehlen(st) {
            this.gewaehlterStadtteil = st;
            this.wikitext            = null;
            this.exportFehler        = null;
            this.kopiert             = false;
            this.wikitextLive        = null;
            this.diffFehler          = null;
            this.diffPaare           = null;
        },

        async exportieren() {
            if (!this.gewaehlterStadtteil) return;

            this.exportLaden  = true;
            this.exportFehler = null;
            this.wikitext     = null;
            this.kopiert      = false;
            this.diffPaare    = null;

            try {
                const result = await api.get(
                    '/export/wikipedia?stadtteil_id=' + this.gewaehlterStadtteil.id
                );
                this.wikitext      = result.wikitext;
                this.anzahl        = result.anzahl;
                this.wikiseiteName = result.wikipedia_name;
                if (this.wikitextLive) this.berechneDiffPaare();
            } catch (e) {
                this.exportFehler = e.message || 'Export fehlgeschlagen.';
            } finally {
                this.exportLaden = false;
            }
        },

        async kopieren() {
            if (!this.wikitext) return;
            try {
                await navigator.clipboard.writeText(this.wikitext);
                this.kopiert = true;
                setTimeout(() => { this.kopiert = false; }, 2500);
            } catch {
                // Fallback: Textarea-Select für Browser ohne Clipboard-API
                const ta = document.getElementById('wikitext-output');
                if (ta) { ta.select(); document.execCommand('copy'); }
            }
        },
    }));
});
