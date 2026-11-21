/**
 * One-time takeover of device data from the German storage names.
 *
 * On 16.08.2026 every identifier and every contract moved to English —
 * but the devices still carry queues, photos and offline areas under the
 * old names, and two of those are the ONLY copy of something: the queue
 * holds finds that never reached the server, the photo store holds their
 * voucher photos. Nothing here may be lost, so everything copies first
 * and deletes only after success.
 *
 * The settings bundle is NOT handled here: index.php and includes/page.php
 * read it raw in the <head> before any module loads (theme flash), so its
 * takeover runs inline there.
 *
 * Everything is guarded — a device without old data, a private window, a
 * failing storage: the takeover then simply does nothing, and the promise
 * below resolves anyway. Modules that read the moved stores await it:
 * photostore.js, areas.js and species.js all touch data whose copy may
 * still be running when the page boots.
 */

function queueEntry(entry) {
    const types = { anlegen: 'create', aendern: 'update', loeschen: 'delete' };
    // The section IS the api file name — those moved to English with the
    // files, so waiting entries must move with them or they would replay
    // against endpoints that no longer exist.
    const sections = { punkte: 'points', listen: 'lists', gebiete: 'areas',
                       eimer: 'bucket', fotos: 'photos', arten: 'species',
                       artenliste: 'species_list', artenpakete: 'species_packages',
                       hoehe: 'elevation', konflikte: 'conflicts' };
    const out = { ...entry };
    if ('bereich' in out)    { out.section     = sections[out.bereich] ?? out.bereich; delete out.bereich; }
    else if ('section' in out) { out.section   = sections[out.section] ?? out.section; }
    if ('art' in out)        { out.type        = types[out.art] ?? out.art; delete out.art; }
    if ('daten' in out)      { out.data        = out.daten;      delete out.daten; }
    if ('vorlaeufig' in out) { out.provisional = out.vorlaeufig; delete out.vorlaeufig; }
    if ('eingereiht' in out) { out.enqueued    = out.eingereiht; delete out.eingereiht; }
    return out;
}

function localStorageTakeover() {
    try {
        // The queue — entries are rewritten to the new shape, the counter
        // must survive (new provisional ids would collide with waiting
        // entries without it), rejected entries keep their reason.
        if (localStorage.getItem('map.queue') === null) {
            const raw = localStorage.getItem('karte.warteschlange');
            if (raw !== null) {
                const old = JSON.parse(raw);
                if (Array.isArray(old)) {
                    localStorage.setItem('map.queue', JSON.stringify(old.map(queueEntry)));
                }
            }
            const nr = localStorage.getItem('karte.warteschlange.nr');
            if (nr !== null && localStorage.getItem('map.queue.counter') === null) {
                localStorage.setItem('map.queue.counter', nr);
            }
            const rejected = localStorage.getItem('karte.warteschlange.verworfen');
            if (rejected !== null && localStorage.getItem('map.queue.rejected') === null) {
                const old = JSON.parse(rejected);
                if (Array.isArray(old)) {
                    localStorage.setItem('map.queue.rejected', JSON.stringify(old.map((v) => ({
                        entry:  queueEntry(v.eintrag ?? {}),
                        reason: v.grund ?? '',
                    }))));
                }
            }
            for (const n of ['karte.warteschlange', 'karte.warteschlange.nr',
                             'karte.warteschlange.verworfen']) localStorage.removeItem(n);
        }

        // The offline-area directory snapshot — found live in a browser
        // during the takeover test, missing from the contracts table.
        const snapshot = localStorage.getItem('karte.gebiete.abbild');
        if (snapshot !== null && localStorage.getItem('map.areas.snapshot') === null) {
            localStorage.setItem('map.areas.snapshot', snapshot);
        }
        localStorage.removeItem('karte.gebiete.abbild');

        // Which containers stand unfolded in the administration — the last
        // storage key that still spoke German (found 22.08.2026).
        const open = localStorage.getItem('karte.offeneContainer');
        if (open !== null && localStorage.getItem('map.openContainers') === null) {
            localStorage.setItem('map.openContainers', open);
        }
        localStorage.removeItem('karte.offeneContainer');

        // The per-package "keep current" flags — a prefix family.
        for (const name of Object.keys(localStorage)) {
            if (name.startsWith('artenpaket-auto-')) {
                const newName = 'species-package-auto-' + name.slice('artenpaket-auto-'.length);
                if (localStorage.getItem(newName) === null) {
                    localStorage.setItem(newName, localStorage.getItem(name));
                }
                localStorage.removeItem(name);
            }
        }
    } catch { /* private window or full — fresh start then */ }

    try {
        const old = sessionStorage.getItem('karte.containerFilter');
        if (old !== null && sessionStorage.getItem('map.containerFilter') === null) {
            sessionStorage.setItem('map.containerFilter', old);
        }
        sessionStorage.removeItem('karte.containerFilter');
    } catch { /* same */ }
}

/** Copy the pending voucher photos: karte-fotos → map-photos. */
async function photosTakeover() {
    const existing = (await indexedDB.databases?.() ?? []).map((d) => d.name);
    if (!existing.includes('karte-fotos')) return;

    const openDb = (name, create) => new Promise((resolve, reject) => {
        const r = indexedDB.open(name, 1);
        if (create) r.onupgradeneeded = () => {
            const db = r.result;
            if (!db.objectStoreNames.contains('pending')) {
                const store = db.createObjectStore('pending', { keyPath: 'id' });
                store.createIndex('point', 'pointId', { unique: false });
            }
        };
        r.onsuccess = () => resolve(r.result);
        r.onerror   = () => reject(r.error);
        r.onblocked = () => reject(new Error('blocked'));
    });

    const oldDb = await openDb('karte-fotos', false);
    if (!oldDb.objectStoreNames.contains('ausstehend')) { oldDb.close(); return; }
    const records = await new Promise((resolve, reject) => {
        const t = oldDb.transaction('ausstehend', 'readonly').objectStore('ausstehend').getAll();
        t.onsuccess = () => resolve(t.result); t.onerror = () => reject(t.error);
    });
    oldDb.close();

    if (records.length > 0) {
        const newDb = await openDb('map-photos', true);
        await new Promise((resolve, reject) => {
            const t = newDb.transaction('pending', 'readwrite');
            const store = t.objectStore('pending');
            for (const s of records) {
                const out = { ...s };
                if ('punktId' in out) { out.pointId = out.punktId; delete out.punktId; }
                store.put(out);
            }
            t.oncomplete = () => resolve(); t.onerror = () => reject(t.error);
        });
        newDb.close();
    }
    // Only now — the copy is through, the old database may go.
    indexedDB.deleteDatabase('karte-fotos');
}

/** Copy the deliberately downloaded offline caches: arten → species,
 *  gebiet-<id> → area-<id>. Tiles are paid for in user download time —
 *  without this copy the service worker's next activate() would have
 *  swept them (its keep-list knows only the new names for good). */
async function cachesTakeover() {
    if (!('caches' in window)) return;
    const names = await caches.keys();
    for (const name of names) {
        let newName = null;
        if (name === 'arten') newName = 'species';
        else if (name.startsWith('gebiet-')) newName = 'area-' + name.slice('gebiet-'.length);
        if (newName === null) { continue; }

        /* MERGED AND NOT SKIPPED WHEN THE NEW NAME ALREADY EXISTS. "Then it
           has already been taken over" was the reasoning, and it held right
           up until a bug wrote NEW tiles under the OLD name again
           (loadArea, until 19.08.2026). Whoever had already been through
           the takeover then kept a second cache that nothing reads and
           nothing clears — paid for in download time and lying there for
           good.

           Overwriting costs nothing: a tile address always yields the same
           tile, and a name list carries its edition in the ETag. */
        const source = await caches.open(name);
        const target = await caches.open(newName);
        for (const request of await source.keys()) {
            const response = await source.match(request);
            if (response) await target.put(request, response);
        }
        await caches.delete(name);
    }
    // The bucket store is a server copy and refills on the next sync — the
    // old database just goes.
    indexedDB.deleteDatabase('karte-eimer');
}


/* ---------------------------------------------------------------------
   Stage 2 (21.08.2026): the schema moved to English, and with it every
   payload key, every filter key, and the species group names. The stores
   keep their (already English) names — what changes is what lies INSIDE:
   queue payloads, the snapshot rows, the settings values, the package
   URLs in the species cache. Runs once, marked, and only renames what it
   positively knows: user content (field names in 'zusatz', titles) is
   never walked blindly.
   --------------------------------------------------------------------- */

const STAGE2_MARK = 'map.takeover.english-schema';

const POINT_KEYS = {
    titel: 'title', notiz: 'note', listen: 'lists', umriss: 'outline',
    gefunden_am: 'found_at', zusatz: 'extra', zusatz_arten: 'extra_species',
    feldzeiten: 'field_times', stand: 'touched_at',
    erstellt_am: 'created_at', geaendert_am: 'updated_at', geloescht_am: 'deleted_at',
    hoehe_m: 'elevation_m', hoehe_ellipsoid_m: 'elevation_ellipsoid_m',
    hoehe_genauigkeit_m: 'elevation_accuracy_m', hoehe_quelle: 'elevation_source',
    genauigkeit_m: 'accuracy_m', gemeinde: 'municipality',
    gemeinde_quelle: 'municipality_source', landesteil: 'region', staat: 'country',
    schutzgebiet: 'protected_area', besitzer: 'owner',
    // lists / areas / photos payloads
    /* felder was missed by the 21.08. move on the CLIENT side — the queue
       kept enqueuing it while the server already read 'fields'. Devices
       that ran stage 2 before this entry existed keep such a payload
       untranslated; the server drops the key silently, which is exactly
       what it did the whole time the client was broken. Nothing new is
       lost, and newly enqueued saves carry 'fields'. */
    felder: 'fields',
    farbe: 'color', eltern_id: 'parent_id', sortierung: 'sort_order',
    ist_arten: 'is_species', ecken: 'corners', messwerte: 'measurements',
    gemessen_am: 'measured_on', aufgenommen_am: 'taken_at', breite: 'width',
    hoehe: 'height', kamera: 'camera', dateiname: 'filename',
    ausrichtung: 'orientation', gesehen: 'seen', punkt_id: 'point_id',
};

const TIME_KEYS = { titel: 'title', notiz: 'note', ort: 'position',
                    listen: 'lists', umriss: 'outline', gefunden_am: 'found_at' };

const GROUPS = { pilze: 'fungi', flechten: 'lichens', pflanzen: 'plants',
                 insekten: 'insects', spinnen: 'arachnids', weichtiere: 'molluscs',
                 voegel: 'birds', saeugetiere: 'mammals', kriechtiere: 'herptiles',
                 fische: 'fish', tiere_rest: 'other_animals' };

const TERM_TYPES = { enthaelt: 'contains', enthaelt_nicht: 'contains_not' };

const FIELD_TYPES = { mehrzeilig: 'multiline', auswahl: 'choice' };

/* One row of a list's field definitions — the inner shape of the 'fields'
   payload. Its keys are NOT in POINT_KEYS because that map only walks the
   top level; a queued list save from the German era carries whole rows. */
function renameFieldRow(row) {
    if (!row || typeof row !== 'object') return row;
    const out = { ...row };
    if ('typ' in out) { out.type = FIELD_TYPES[out.typ] ?? out.typ; delete out.typ; }
    else if ('type' in out) { out.type = FIELD_TYPES[out.type] ?? out.type; }
    if ('ist_arten' in out) { out.is_species = out.ist_arten; delete out.ist_arten; }
    if ('optionen' in out) { out.options = out.optionen; delete out.optionen; }
    return out;
}

function renameTimeKey(k) {
    if (typeof k === 'string' && k.startsWith('zusatz.')) return 'extra.' + k.slice(7);
    return TIME_KEYS[k] ?? k;
}

function renameTermRow(row) {
    if (!row || typeof row !== 'object') return row;
    const out = { ...row };
    if ('art' in out) { out.type = TERM_TYPES[out.art] ?? out.art; delete out.art; }
    else if ('type' in out) { out.type = TERM_TYPES[out.type] ?? out.type; }
    return out;
}

function renameFilter(f) {
    if (!f || typeof f !== 'object') return f;
    const out = {};
    const top = { zeit: 'time', gebiet: 'area', begriffe: 'terms',
                  quellen: 'sources', ohnezeit: 'undated' };
    for (const [k, v] of Object.entries(f)) out[top[k] ?? k] = v;
    if (out.time && typeof out.time === 'object') {
        const t = {};
        const keys = { von: 'from', bis: 'until', ohnezeit: 'undated' };
        for (const [k, v] of Object.entries(out.time)) t[keys[k] ?? k] = v;
        out.time = t;
    }
    if (out.area && typeof out.area === 'object' && 'ecken' in out.area) {
        out.area = { ...out.area, corners: out.area.ecken };
        delete out.area.ecken;
    }
    if (Array.isArray(out.terms)) out.terms = out.terms.map(renameTermRow);
    if (Array.isArray(out.sources)) {
        out.sources = out.sources.map((q) => (q === 'ohne' ? 'none' : q));
    }
    return out;
}

function renamePayload(data) {
    if (!data || typeof data !== 'object' || Array.isArray(data)) return data;
    const out = {};
    for (const [k, v] of Object.entries(data)) out[POINT_KEYS[k] ?? k] = v;
    if (Array.isArray(out.fields)) out.fields = out.fields.map(renameFieldRow);
    if (out.field_times && typeof out.field_times === 'object') {
        const t = {};
        for (const [k, v] of Object.entries(out.field_times)) t[renameTimeKey(k)] = v;
        out.field_times = t;
    }
    if (out.field_seen && typeof out.field_seen === 'object') {
        const t = {};
        for (const [k, v] of Object.entries(out.field_seen)) t[renameTimeKey(k)] = v;
        delete out.field_seen;
        out.field_seen = t;
    }
    if (out.filter) out.filter = renameFilter(out.filter);
    return out;
}

function stage2LocalStorage() {
    // The queue payloads. The entry shape is already stage-1 English.
    const queue = localStorage.getItem('map.queue');
    if (queue !== null) {
        const entries = JSON.parse(queue);
        if (Array.isArray(entries)) {
            localStorage.setItem('map.queue', JSON.stringify(entries.map((e) => (
                e && typeof e === 'object' && 'data' in e ? { ...e, data: renamePayload(e.data) } : e
            ))));
        }
    }
    const rejected = localStorage.getItem('map.queue.rejected');
    if (rejected !== null) {
        const entries = JSON.parse(rejected);
        if (Array.isArray(entries)) {
            localStorage.setItem('map.queue.rejected', JSON.stringify(entries.map((v) => (
                v && typeof v === 'object' && v.entry && typeof v.entry === 'object' && 'data' in v.entry
                    ? { ...v, entry: { ...v.entry, data: renamePayload(v.entry.data) } } : v
            ))));
        }
    }

    // The offline-area directory snapshot rows.
    const snapshot = localStorage.getItem('map.areas.snapshot');
    if (snapshot !== null) {
        const rows = JSON.parse(snapshot);
        if (Array.isArray(rows)) {
            localStorage.setItem('map.areas.snapshot', JSON.stringify(rows.map(renamePayload)));
        }
    }

    // The device identity of the offline areas.
    const device = localStorage.getItem('karte.gebiete.geraet');
    if (device !== null && localStorage.getItem('map.areas.device') === null) {
        localStorage.setItem('map.areas.device', device);
    }
    localStorage.removeItem('karte.gebiete.geraet');

    /* Inside those per-device records, zoom_bis is the one key the schema
       move missed — valid_until right beside it already travelled. Renamed
       22.08.2026; only the old key is touched, so running again costs
       nothing. */
    const deviceRecords = localStorage.getItem('map.areas.device');
    if (deviceRecords !== null) {
        const all = JSON.parse(deviceRecords);
        if (all && typeof all === 'object' && !Array.isArray(all)) {
            let changed = false;
            for (const record of Object.values(all)) {
                if (record && typeof record === 'object' && 'zoom_bis' in record) {
                    record.zoom_to = record.zoom_bis;
                    delete record.zoom_bis;
                    changed = true;
                }
            }
            if (changed) localStorage.setItem('map.areas.device', JSON.stringify(all));
        }
    }

    /* The form-state rescue moved from 'karte.formularstand' with German
       record keys to 'map.formstate' on 22.08.2026. It lives only half an
       hour — but that half hour is exactly the camera flow it exists for,
       and it can hold the ONLY copy of a typed label. So it is copied
       like everything else instead of being declared expired. */
    const formRescue = localStorage.getItem('karte.formularstand');
    if (formRescue !== null) {
        if (localStorage.getItem('map.formstate') === null) {
            const alt = JSON.parse(formRescue);
            if (alt && typeof alt === 'object') {
                const values = { ...(alt.werte || {}) };
                if ('koord' in values)    { values.coord = values.koord;    delete values.koord; }
                if ('gefunden' in values) { values.found = values.gefunden; delete values.gefunden; }
                localStorage.setItem('map.formstate', JSON.stringify(
                    { pointId: alt.punktId, values, when: alt.wann }));
            }
        }
        localStorage.removeItem('karte.formularstand');
    }

    // Settings values: the sort order, the term rows, the species groups.
    const raw = localStorage.getItem('map.settings');
    if (raw !== null) {
        const settings = JSON.parse(raw) || {};
        if (settings.sorting === 'zeit') settings.sorting = 'time';
        if (Array.isArray(settings.searchTerms)) {
            settings.searchTerms = settings.searchTerms.map(renameTermRow);
        }
        if (Array.isArray(settings.sidebarSectionsCollapsed)) {
            const sections = { meine: 'mine', geteilt: 'shared' };
            settings.sidebarSectionsCollapsed =
                settings.sidebarSectionsCollapsed.map((s) => sections[s] ?? s);
        }
        if ('artengruppen' in settings) {
            const old = settings.artengruppen;
            settings.speciesGroups = Array.isArray(old)
                ? old.map((g) => GROUPS[g] ?? g) : old;
            delete settings.artengruppen;
        }
        /* The tile-CORS verdicts kept German inner keys when the outer
           'kachel-cors' → 'tile-cors' move ran in the page preamble —
           renamed to ok/when on 22.08.2026. Without this rewrite every
           device would drop its remembered verdicts and probe all tile
           hosts again; only old keys are touched, running twice is free. */
        const cors = settings['tile-cors'];
        if (cors && typeof cors === 'object' && !Array.isArray(cors)) {
            for (const entry of Object.values(cors)) {
                if (!entry || typeof entry !== 'object') continue;
                if ('ja' in entry)   { entry.ok   = entry.ja;   delete entry.ja; }
                if ('wann' in entry) { entry.when = entry.wann; delete entry.wann; }
            }
        }
        localStorage.setItem('map.settings', JSON.stringify(settings));
    }

    // The per-package "keep current" flags carry the group in their name.
    for (const name of Object.keys(localStorage)) {
        if (name.startsWith('species-package-auto-')) {
            const group = name.slice('species-package-auto-'.length);
            if (GROUPS[group]) {
                const newName = 'species-package-auto-' + GROUPS[group];
                if (localStorage.getItem(newName) === null) {
                    localStorage.setItem(newName, localStorage.getItem(name));
                }
                localStorage.removeItem(name);
            }
        }
    }
}

/** The stored protected-area packs carry their kinds under the old key. */
async function stage2ProtectedAreas() {
    if (!('caches' in window)) return;
    if (!(await caches.has('protected-areas'))) return;
    const cache = await caches.open('protected-areas');
    for (const request of await cache.keys()) {
        const answer = await cache.match(request);
        if (!answer) continue;
        let body;
        try { body = await answer.clone().json(); } catch { continue; }
        if (!body || typeof body !== 'object') continue;
        let changed = false;
        if ('arten' in body)  { body.kinds   = body.arten;  delete body.arten;  changed = true; }
        if ('geholt' in body) { body.fetched = body.geholt; delete body.geholt; changed = true; }
        if (changed) {
            await cache.put(request, new Response(JSON.stringify(body),
                { headers: { 'Content-Type': 'application/json' } }));
        }
    }
}

/** The downloaded name lists sit under URLs carrying the old group names. */
async function stage2SpeciesCache() {
    if (!('caches' in window)) return;
    if (!(await caches.has('species'))) return;
    const cache = await caches.open('species');
    for (const request of await cache.keys()) {
        const m = request.url.match(/([?&]package=)([a-z_]+)/);
        if (!m) continue;
        const answer = await cache.match(request);
        if (!answer) continue;
        // The URL moves only for renamed groups; the body moves for all.
        const newUrl = GROUPS[m[2]]
            ? request.url.replace(m[1] + m[2], m[1] + GROUPS[m[2]])
            : request.url;
        /* The body moves too: package format 2 renamed its three keys.
           Read as text, not json() — the lists are megabytes, and the
           keys sit in the first line. */
        let body = await answer.text();
        body = body.replace('{"paket":', '{"package":')
                   .replace(',"fassung":', ',"version":')
                   .replace(',"arten":[', ',"species":[');
        await cache.put(new Request(newUrl), new Response(body,
            { headers: { 'Content-Type': 'application/json; charset=utf-8' } }));
        await cache.delete(request);
    }
}

function stage2Caches() {
    if (localStorage.getItem(STAGE2_MARK) !== null) return Promise.resolve();
    return Promise.all([stage2SpeciesCache(), stage2ProtectedAreas()]).then(
        () => { try { localStorage.setItem(STAGE2_MARK, '1'); } catch { /* then again next time */ } },
        (e) => console.warn('Takeover (name lists) waits for the next start:', e),
    );
}

localStorageTakeover();
/* SYNCHRONOUSLY, before any module reads the settings or the queue: the
   rewrite only touches old keys and old values, so running it again costs
   nothing — but running it late would let map.js read a term row whose
   'art' nobody understands any more. The cache rewrites stay async below;
   their readers await deviceTakeover. */
try { stage2LocalStorage(); } catch (e) { console.warn('Takeover (schema) waits for the next start:', e); }

/** Modules touching photos or offline caches await this before reading. */
export const deviceTakeover = (async () => {
    try { await photosTakeover(); } catch (e) { console.warn('Photo takeover waits for the next start:', e); }
    try { await cachesTakeover(); } catch (e) { console.warn('Cache takeover waits for the next start:', e); }
    // After the cache copy: stage 2 rewrites inside the copied stores.
    try { await stage2Caches(); } catch (e) { console.warn('Takeover (schema) waits for the next start:', e); }
})();
