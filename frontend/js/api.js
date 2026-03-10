/**
 * Zentraler HTTP-Client.
 * Alle Anfragen laufen über diese Methoden – Cookie-Auth via credentials: 'include'.
 * Fehler werden als { status, message } geworfen.
 */
const api = (() => {
    async function request(method, path, data = null) {
        const opts = {
            method,
            credentials: 'include',
            headers: {},
        };

        if (data instanceof FormData) {
            // Kein Content-Type setzen – Browser setzt multipart/form-data inkl. Boundary
            opts.body = data;
        } else if (data !== null) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(data);
        }

        const res = await fetch(CONFIG.apiBase + path, opts);

        if (res.status === 204) return null;

        const json = await res.json().catch(() => ({ error: res.statusText }));

        if (!res.ok) {
            throw { status: res.status, message: json.error || res.statusText };
        }

        // Backend gibt immer { success: true, data: ... } zurück
        return json.data ?? json;
    }

    return {
        get:    (path)       => request('GET',    path),
        post:   (path, data) => request('POST',   path, data),
        put:    (path, data) => request('PUT',    path, data),
        delete: (path)       => request('DELETE', path),
        upload: (path, form) => request('POST',   path, form),
    };
})();
