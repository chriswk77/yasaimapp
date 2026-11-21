/**
 * Translate a place name into a region.
 *
 * WHAT FOR, EXACTLY. Google shows Plus Codes shortened: "8G4C+9M Frankfurt".
 * The first four characters are missing; in return a place name stands next
 * to it — and without that the code cannot be resolved, it repeats every
 * hundred kilometers. The map view only helps if you are already standing
 * in the right region anyway.
 *
 * WHAT GOES OUTSIDE, and only that: the place name someone typed in
 * themselves. No coordinate, no species name, no point, no id.
 * "Frankfurt" — the service learns no more, and no more is in the request
 * either. That is the reason it is defensible here and at no other place
 * in this application.
 *
 * ONLY ON REQUEST, not while typing. Asking happens only once a short Plus
 * Code was recognized AND a place name stands beside it — so at most a few
 * times a day. Nominatim allows one request per second and asks that
 * results be kept; both are below.
 *
 * AND IT MAY FAIL. No network, service gone, nothing found: Then the map
 * view remains the reference, just as without this file. A search that no
 * longer answers at all without reception would be the worse trade — in
 * the forest exactly that is the normal case.
 */

const SERVICE = 'https://nominatim.openstreetmap.org/search';

/** What was asked once is not asked again. */
const remembered = new Map();

/** One request per second, as the terms of use demand. */
let lastRequest = 0;

/**
 * Search for places.
 *
 * SEVERAL HITS, NOT ONE. "Neustadt" exists a dozen times in Germany,
 * "Hausen" more often. Silently taking the first would mean sending the
 * user to a spot they did not mean — and not even showing them that there
 * was a choice.
 *
 * (When completing a Plus Code it is different: There the place serves
 * only as a rough reference, and the choice is made by the code anyway.
 * For that there is findPlace() further down.)
 *
 * @param {string} rawQuery
 * @param {number} total
 * @returns {Promise<Array<{lat:number, lon:number, name:string, short:string, label:string}>>}
 */
export async function findPlaces(rawQuery, total = 5) {
    const query = String(rawQuery ?? '').trim();
    if (query.length < 2 || query.length > 120) return [];

    const key = total + '|' + query.toLowerCase();
    if (remembered.has(key)) return remembered.get(key);

    // Without network, do not even try — the failure would otherwise come
    // only after the timeout, and until then the display would stall.
    if (!navigator.onLine) return [];

    const wait = 1000 - (Date.now() - lastRequest);
    if (wait > 0) await new Promise(r => setTimeout(r, wait));
    lastRequest = Date.now();

    try {
        const abort = new AbortController();
        const timer = setTimeout(() => abort.abort(), 6000);

        const answer = await fetch(
            `${SERVICE}?format=jsonv2&limit=${total}&accept-language=de&q=${encodeURIComponent(query)}`,
            { signal: abort.signal, headers: { Accept: 'application/json' } }
        );
        clearTimeout(timer);
        if (!answer.ok) return [];

        const raw = await answer.json();
        const hits = (Array.isArray(raw) ? raw : [])
            .map((t) => {
                const lat = parseFloat(t.lat), lon = parseFloat(t.lon);
                if (!Number.isFinite(lat) || !Number.isFinite(lon)) return null;
                const full = String(t.display_name ?? query);
                const parts = full.split(',').map((x) => x.trim());
                return {
                    lat, lon,
                    name: full,
                    // The name alone and the rest apart: "München" large,
                    // "Bayern, Deutschland" small below it. The whole
                    // display_name in one line is unreadable.
                    short: parts[0],
                    label: parts.slice(1).join(', '),
                };
            })
            .filter(Boolean);

        // Remember the not-finding too: A typo should not go out anew on
        // every keystroke.
        remembered.set(key, hits);
        return hits;
    } catch {
        // Abort, timeout, no network — all the same case.
        return [];
    }
}

/** A single hit, for completing short Plus Codes. */
export async function findPlace(place) {
    return (await findPlaces(place, 1))[0] ?? null;
}
