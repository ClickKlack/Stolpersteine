/**
 * Leichtgewichtiger API-Client für öffentliche Endpunkte.
 * Keine Authentifizierung erforderlich.
 */
const api = {
    async get(path) {
        const res = await fetch(CONFIG.apiBase + path);
        const json = await res.json();
        if (!res.ok || !json.success) {
            throw new Error(json.error || `HTTP ${res.status}`);
        }
        return json.data;
    },
};
