/**
 * Wiederverwendbare SVG-Icons als HTML-Strings.
 * Verwendung in Alpine.js: <span x-html="ICONS.wikidata"></span>
 */
const ICONS = {
    // Wikidata-Barcode-Logo (vereinfacht, Originalfarben #990000 / #339966)
    wikidata: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
        <rect x="1"  y="1" width="4" height="22" fill="#990000"/>
        <rect x="7"  y="1" width="2" height="22" fill="#990000"/>
        <rect x="11" y="1" width="5" height="22" fill="#339966"/>
        <rect x="18" y="1" width="2" height="22" fill="#990000"/>
        <rect x="22" y="1" width="2" height="22" fill="#339966"/>
    </svg>`,

    // OpenStreetMap-Pin in OSM-Grün (#7EBC6F)
    osm: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
        <path d="M12 2C8.686 2 6 4.686 6 8c0 4.418 6 12 6 12s6-7.582 6-12c0-3.314-2.686-6-6-6z" fill="#7EBC6F"/>
        <circle cx="12" cy="8" r="2.2" fill="white"/>
    </svg>`,

    // Kamera-Symbol für lokales Foto
    foto: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="14" height="14" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
    </svg>`,

    // Wikimedia Commons-Logo (vereinfacht, Commons-Blau #006699)
    commons: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
        <circle cx="12" cy="12" r="11" fill="#006699"/>
        <text x="12" y="17" text-anchor="middle" font-family="serif" font-size="14" font-weight="bold" fill="white">W</text>
    </svg>`,
};
