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
};
