/**
 * Protected-area boundaries on the device, by federal state.
 *
 * WHY BY STATE AND NOT BY AREA. The tiles of an offline area are pictures —
 * they only exist for the patch of forest one asked for. Protected-area
 * boundaries are lines with names, and one needs them exactly where one had
 * not planned to go: a spontaneous detour, a path that turns out to lead
 * somewhere else. Whoever collects in Hesse wants the boundaries of Hesse,
 * not those of three squares they happened to outline
 * (19.08.2026).
 *
 * IT FITS, and that was measured before it was built: the 768 nature
 * reserves of Hesse come to 3.1 MB. The whole of Germany is in the order of
 * sixty — a state is a phone-sized amount, an outline is not.
 *
 * THE POLYGONS ARE NEVER CUT. The service files every area under exactly
 * ONE state and hands out the whole geometry, across the border with it.
 * Measured: of 9012 nature reserves, 9006 carry one of the sixteen state
 * codes; the remaining six are 'AWZ' — the marine reserves of the North and
 * Baltic Sea, which belong to no state. So an area straddling the Hesse /
 * Rhineland-Palatinate border comes complete with Hesse, and whoever stands
 * on the Palatinate side of it and has only that state loaded does not see
 * it. Right at a border, both states.
 *
 * THE TYPES COME FROM THE LAYER REGISTRY and are not listed a second time
 * here. Whoever adds a fourth kind of protected area to map_layers.php gets
 * it offline without touching this file — and cannot end up with two lists
 * that disagree, which is the trap the icon library was built against.
 */

import { t } from './i18n.js';

const STORE = 'protected-areas';

/**
 * The sixteen, in the order one looks for them: alphabetical.
 *
 * The codes are the service's, not ours — 'BL' on every area carries
 * exactly these. AWZ is deliberately absent: nobody collects mushrooms in
 * the exclusive economic zone.
 */
export const STATES = [
    { code: 'BW', name: 'Baden-Württemberg' },
    { code: 'BY', name: 'Bayern' },
    { code: 'BE', name: 'Berlin' },
    { code: 'BB', name: 'Brandenburg' },
    { code: 'HB', name: 'Bremen' },
    { code: 'HH', name: 'Hamburg' },
    { code: 'HE', name: 'Hessen' },
    { code: 'MV', name: 'Mecklenburg-Vorpommern' },
    { code: 'NI', name: 'Niedersachsen' },
    { code: 'NW', name: 'Nordrhein-Westfalen' },
    { code: 'RP', name: 'Rheinland-Pfalz' },
    { code: 'SL', name: 'Saarland' },
    { code: 'SN', name: 'Sachsen' },
    { code: 'ST', name: 'Sachsen-Anhalt' },
    { code: 'SH', name: 'Schleswig-Holstein' },
    { code: 'TH', name: 'Thüringen' },
];

/** The registry entry — url and the kinds of area. */
export function registryEntry() {
    /* ONE KEY, 'layers', ON BOTH PAGES. The map delivers the full layer
       list under it, the administration only the outline subset — the
       find() below does not care. Its predecessor 'ebenen' existed only on
       the administration page, so this function came up empty on the map
       without anyone deciding that. */
    const all = window.START_DATA?.layers || [];
    return all.find(e => e.type === 'wfs-outline') ?? null;
}

/**
 * The address a state is stored under.
 *
 * IT IS NEVER REQUESTED. Cache Storage needs a Request as its key, and a key
 * wants to be readable when one looks into the storage with the developer
 * tools. So it points at our own origin and at nothing: the content comes
 * from the provider, not from here.
 *
 * NOT UNDER api/, and tests/endpoints.mjs was right to insist. It used to
 * read api/v1/offline.php — a file that does not exist and never should.
 * An address that looks like an endpoint and is none sends the next reader
 * looking for it, and a test that watches over exactly that had to be
 * argued away rather than followed.
 */
function stateUrl(code) {
    return (window.START_DATA?.baseUrl ?? '/') + 'offline-data?protected=' + encodeURIComponent(code);
}

/* THE VINTAGE OF THE DATA, straight from the provider.
 *
 * The service says it itself, in the abstract of its capabilities:
 * "Stand der Daten: FFH-Gebiete (2019), Vogelschutzgebiete (2019),
 * Nationalparke (2025), Naturschutzgebiete (2023), …" — one year per kind
 * of area, and they differ by years. That is what makes „Erneuern"
 * judgeable at all: whoever loaded Bavaria last week gains nothing from
 * pressing it again, but whoever loaded it before the 2025 national parks
 * arrived gains a lot.
 *
 * IT IS PROSE, AND IT IS TREATED AS SUCH. A sentence in a foreign
 * document can be reworded at any time — so anything not clearly readable
 * yields null, and the display then simply keeps quiet. Never a guess:
 * a wrong year here would be worse than none, because it decides whether
 * somebody fetches twenty megabytes over mobile data.
 *
 * Once per page: the answer is one document for the whole service and
 * changes at most yearly.
 */
let currencyPromise = null;

export function providerCurrency() {
    if (currencyPromise) return currencyPromise;

    const entry = registryEntry();
    if (!entry?.url) return Promise.resolve(null);

    const p = new URLSearchParams({
        SERVICE: 'WFS', VERSION: '2.0.0', REQUEST: 'GetCapabilities',
    });

    /* ONLY OUR THREE KINDS. The service names nine — bird sanctuaries,
       biosphere reserves, nature parks and more —, and a line listing all
       of them says nothing about what lies on this device. Filtered here
       and not at the display, so every reader gets the same answer to
       "what is the vintage of what we fetch". */
    const ours = (entry.kinds || []).map(k => k.currencyLabel).filter(Boolean);

    currencyPromise = fetch(`${entry.url}?${p}`)
        .then((response) => (response.ok ? response.text() : null))
        .then((text) => {
            const all = text === null ? null : readCurrency(text);
            if (!all) return null;
            const mine = Object.fromEntries(
                ours.filter(label => all[label]).map(label => [label, all[label]]));
            return Object.keys(mine).length > 0 ? mine : null;
        })
        .catch(() => null);

    return currencyPromise;
}

/** The years out of the capabilities document, by the provider's label. */
export function readCurrency(xml) {
    const abstract = /<ows:Abstract>([\s\S]*?)<\/ows:Abstract>/.exec(xml)?.[1];
    if (!abstract) return null;

    /* Up to the full stop that ends the enumeration — the sentence after
       it talks about the metadata catalogue and carries numbers of its
       own. */
    const list = /Stand der Daten:([^.]*)/.exec(abstract)?.[1];
    if (!list) return null;

    const years = {};
    for (const [, label, year] of list.matchAll(/([^,(]+)\((\d{4})\)/g)) {
        years[label.trim()] = Number(year);
    }
    return Object.keys(years).length > 0 ? years : null;
}

/**
 * Ask the service for one kind of area in one state.
 *
 * THE FILTER IS THE OGC ONE AND NOT CQL. The service silently ignores
 * CQL_FILTER — measured: it then answered with all 9012 areas instead of
 * Hesse's 768, and nothing about the answer said so. A filter that is
 * ignored rather than refused is the worst kind.
 *
 * AND THE FIELD COMES FROM THE REGISTRY, because it is not the same for all
 * three: 'BL' for national parks and nature reserves, 'Bundesland' for the
 * FFH areas. Filtering on a field a layer does not have does not yield a
 * readable error — the request fails at the network level, in 200 ms, with
 * "Failed to fetch" and no status. Two of three states then arrive and the
 * third does not, which looks like a bad line and is not.
 */
async function fetchKind(url, kind, code, signal) {
    const field = kind.regionField || 'BL';
    const filter = '<fes:Filter xmlns:fes="http://www.opengis.net/fes/2.0">'
        + `<fes:PropertyIsEqualTo><fes:ValueReference>${field}</fes:ValueReference>`
        + `<fes:Literal>${code}</fes:Literal></fes:PropertyIsEqualTo></fes:Filter>`;

    const p = new URLSearchParams({
        SERVICE: 'WFS', VERSION: '2.0.0', REQUEST: 'GetFeature',
        TYPENAMES: kind.type, OUTPUTFORMAT: 'GEOJSON', COUNT: '10000',
        FILTER: filter,
    });

    const response = await fetch(`${url}?${p}`, { signal });
    if (!response.ok) throw new Error(t('HTTP %s für %s', response.status, kind.text));

    return await response.json();
}

/**
 * The enclosing rectangle of a feature, in the order Leaflet reads.
 *
 * COMPUTED WHEN STORING, not when reading. Offline the question is asked on
 * every pan of the map — walking thousands of coordinate rings each time
 * would make the boundaries the slowest thing on the screen. Once, on
 * arrival, costs nothing anybody notices.
 */
function bounds(geometry) {
    let s = 90, w = 180, n = -90, o = -180;
    const walk = (c) => {
        if (typeof c[0] === 'number') {
            if (c[1] < s) s = c[1];
            if (c[1] > n) n = c[1];
            if (c[0] < w) w = c[0];
            if (c[0] > o) o = c[0];
            return;
        }
        for (const part of c) walk(part);
    };
    if (geometry?.coordinates) walk(geometry.coordinates);
    return [s, w, n, o];
}

/**
 * Fetch a state and put it on the device.
 *
 * ONE KIND AFTER THE OTHER, not all at once. The service throttles: during
 * the measurements it began refusing after a handful of large requests, and
 * with "Failed to fetch" — no status, nothing to read. Three requests in a
 * row it takes without complaint.
 *
 * @param {(text: string) => void} [report]
 * @returns {Promise<{count: number, bytes: number}>}
 */
export async function download(code, report) {
    const entry = registryEntry();
    if (!entry) throw new Error(t('Die Schutzgebiete stehen in dieser Anlage nicht zur Verfügung.'));
    if (!('caches' in window)) throw new Error(t('Dieser Browser kann nichts offline behalten.'));

    const state = STATES.find(l => l.code === code);
    const parts = [];
    let count = 0;

    for (const [i, kind] of (entry.kinds || []).entries()) {
        /* THE SHARE COMES ALONG, not only the sentence
           (22.08.2026: on the phone a bar is better). It follows the
           COUNT IN THE TEXT — "(2/3)" and a bar at two thirds say the
           same thing, and two readouts of one progress that disagree are
           worse than one alone. Reported before the kind is fetched, so
           the bar moves at the start of a step and not only at its end:
           for the first third it would otherwise sit at zero, which
           looks like nothing is happening at all. */
        report?.(t('%s: %s (%s/%s) …', state?.name ?? code, kind.text, i + 1, entry.kinds.length),
                 i + 1, entry.kinds.length);
        const data = await fetchKind(entry.url, kind, code);
        const features = (data.features || []).map(f => ({
            b: bounds(f.geometry),
            f,
        }));
        count += features.length;
        parts.push({ type: kind.type, features });
    }

    // Full at the end: the storing that follows is a matter of
    // milliseconds, and a bar stopping at two thirds would be a lie.
    report?.(t('%s: wird abgelegt …', state?.name ?? code), entry.kinds.length, entry.kinds.length);

    /* THE VINTAGE TRAVELS WITH THE PACK. Without it the row could only
       say WHEN it was fetched, and that answers the wrong question:
       whoever downloaded yesterday has 2019 FFH areas just like whoever
       downloaded a year ago, because the provider publishes once a year.
       Stored is what our kinds carry, not the whole list — the other
       seven belong to layers we do not draw. Missing is allowed: it then
       just is not shown. */
    const currency = await providerCurrency();
    const vintage = currency
        ? Object.fromEntries((entry.kinds || [])
            .filter(k => k.currencyLabel && currency[k.currencyLabel])
            .map(k => [k.currencyLabel, currency[k.currencyLabel]]))
        : null;

    const body = JSON.stringify({ code, fetched: Date.now(), kinds: parts, vintage });
    const store = await caches.open(STORE);
    const fetched = Date.now();
    await store.put(new Request(stateUrl(code)), new Response(body, {
        headers: { 'Content-Type': 'application/json' },
    }));

    /* THE SUMMARY IS WRITTEN HERE, WHERE IT IS AT HAND ANYWAY. It stood
       nowhere, so stored() recomputed it on every opening of the
       administration: read every stored state in as text and push it
       through JSON.parse just to count areas. Bavaria alone is several
       megabytes, and the unpacking runs on the main thread — the
       administration froze, no tab responded any more, and it looked like
       a hanging load. It was arithmetic.

       A second, tiny entry under the same address with &summary=1: that
       way it carries the same protected parameter remove() deletes by and
       stored() sorts by — both catch it without one extra line. */
    await store.put(new Request(stateUrl(code) + '&summary=1'),
        new Response(JSON.stringify({ count, bytes: body.length, fetched, vintage }), {
            headers: { 'Content-Type': 'application/json' },
        }));

    return { count, bytes: body.length };
}

/**
 * What lies on the device: code → {count, bytes, fetched}.
 *
 * NOTHING IS UNPACKED HERE. This function used to read every stored state
 * in as text and parse it to count the areas — at several megabytes per
 * state that freezes the whole page, for JSON.parse runs on the main
 * thread and cannot be interrupted. The numbers now sit alongside as
 * their own small entry, written at download time (see download()).
 *
 * WHAT WAS DOWNLOADED BEFORE THIS CHANGE has no such entry. It is not
 * parsed after all as a substitute — that would be the very freeze this
 * is meant to remove. Instead: it is there, the size comes from the blob
 * (which costs nothing, for it never touches the content), and the count
 * stays open until the state is refreshed once. A missing number is a
 * small blemish; a page that no longer responds is not. */
export async function stored() {
    const here = new Map();
    if (!('caches' in window)) return here;

    const store = await caches.open(STORE);
    const packs = new Map();   // code → Request des großen Pakets

    for (const request of await store.keys()) {
        const address = new URL(request.url);
        const code = address.searchParams.get('protected');
        if (!code) {
            await store.delete(request);
            continue;
        }
        if (address.searchParams.get('summary') === '1') {
            const answer = await store.match(request);
            if (!answer) continue;
            try {
                const d = await answer.json();
                here.set(code, { count: d.count ?? null, bytes: d.bytes ?? 0,
                               fetched: d.fetched ?? null, vintage: d.vintage ?? null });
            } catch { /* then the pack itself counts in a moment */ }
            continue;
        }
        packs.set(code, request);
    }

    for (const [code, request] of packs) {
        if (here.has(code)) continue;              // die Zusammenfassung war here
        const answer = await store.match(request);
        if (!answer) continue;
        let bytes = 0;
        try { bytes = (await answer.blob()).size; } catch { /* then zero it is */ }
        here.set(code, { count: null, bytes, fetched: null, vintage: null });
    }
    return here;
}

/**
 * Take a state off the device.
 *
 * BY THE PARAMETER AND NOT BY THE ADDRESS, because the address has changed
 * once: what was fetched before 19.08.2026 sits under the old api/ key.
 * stored() reads those perfectly well — it asks for the parameter — and
 * deleting has to reach just as far, or a state would go on being listed
 * that nothing can remove.
 */
export async function remove(code) {
    if (!('caches' in window)) return;
    const store = await caches.open(STORE);
    for (const request of await store.keys()) {
        if (new URL(request.url).searchParams.get('protected') === code) {
            await store.delete(request);
        }
    }
}

/**
 * Everything stored, as one GeoJSON collection per kind — clipped to the
 * view by the rectangles computed on arrival.
 *
 * ALL STATES AT ONCE. Which one the map is looking at would have to be
 * decided from state polygons we do not carry, and the answer is not even
 * needed: whoever has three states on the device has a few thousand
 * rectangles to compare, and that is nothing.
 *
 * @returns {Promise<Map<string, object>>} kind → FeatureCollection
 */
export async function fromDevice(area) {
    const out = new Map();
    if (!('caches' in window)) return out;

    const [s, w, n, o] = [area.getSouth(), area.getWest(), area.getNorth(), area.getEast()];
    const store = await caches.open(STORE);

    for (const request of await store.keys()) {
        // The summary entry carries no areas — and opening it would mean
        // parsing it.
        if (new URL(request.url).searchParams.get('summary') === '1') continue;
        const answer = await store.match(request);
        if (!answer) continue;
        let d;
        try { d = await answer.json(); } catch { continue; }

        for (const kind of d.kinds || []) {
            const hits = kind.features
                // Two rectangles miss each other exactly when one lies
                // entirely beside or entirely above the other.
                .filter(({ b }) => !(b[2] < s || b[0] > n || b[3] < w || b[1] > o))
                .map(({ f }) => f);
            if (hits.length === 0) continue;

            const sofar = out.get(kind.type);
            if (sofar) sofar.features.push(...hits);
            else out.set(kind.type, { type: 'FeatureCollection', features: hits });
        }
    }
    return out;
}
