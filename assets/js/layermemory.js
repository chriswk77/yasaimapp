/**
 * Which overlays are on — remembered PER BASE MAP, on this device.
 *
 * WHY (06.10.2026): „können wir für die beiden Karten, also
 * Satellitenbild und OpenStreetMap, jeweils eigen die anderen Layer
 * speichern … die Geländeschummerung brauche ich nicht wirklich bei den
 * Satellitenbildern, bei OpenStreetMap habe ich sie gerne an … pro Gerät,
 * also in Local Storage." Until then one list ('overlays') held for every
 * base map, and switching to the aerial picture took the shading along.
 *
 * ONE MEMORY FOR BOTH MAPS — the Leaflet fallback (layers.js) and the
 * vector map (vectorlayers.js) speak the same layer events, and two copies
 * of this would drift apart at the first change.
 *
 * A BASE MAP SEEN FOR THE FIRST TIME takes what is on as it comes in, and
 * from then on keeps its own list. The old single list is where every base
 * map starts, so nobody's overlays vanish on the first switch after the
 * update.
 *
 * THE SWITCH APPLIES ONE TICK LATER: Leaflet's layer control is still in its
 * own click while it fires baselayerchange, and it redraws its boxes only
 * after a change that comes outside that click — applied inside it, the map
 * and the boxes would disagree.
 */

const KEY = 'overlaysByBase';

/* THE SETTINGS COME IN, they are not imported: vectorlayers.js receives its
   bundle as a parameter, and this follows the same hand. */

/** The overlay names to switch on with this base map. */
export function overlaysFor(settings, baseName) {
    const all = settings.get(KEY, null) || {};
    if (Array.isArray(all[baseName])) return all[baseName];
    const before = settings.get('overlays', []);   // the one list before 06.10.2026
    return Array.isArray(before) ? before : [];
}

/**
 * Keep each base map's overlays from now on.
 *
 * @param {object} settings   the settings bundle (settings.js)
 * @param {object} map        the Leaflet map or the vector bridge
 * @param {object} overlays   name → layer, as the layer control knows them
 * @param {string} startName  the base map the page opened with
 * @param {(name: string) => string} [rename]  an old overlay name → today's
 */
export function keepOverlaysPerBase(settings, map, overlays, startName, rename = (n) => n) {
    let current = startName;
    let applying = false;
    const on = () => Object.keys(overlays).filter((n) => map.hasLayer(overlays[n]));

    const save = () => {
        if (applying || current === null) return;
        const all = { ...(settings.get(KEY, null) || {}) };
        all[current] = on();
        settings.set(KEY, all);
    };
    map.on('overlayadd overlayremove', save);

    map.on('baselayerchange', (e) => {
        const before = on();
        current = e.name;
        const all = settings.get(KEY, null) || {};
        const wanted = Array.isArray(all[current]) ? all[current].map(rename) : before;
        setTimeout(() => {
            applying = true;
            try {
                for (const name of Object.keys(overlays)) {
                    const want = wanted.includes(name);
                    const has = map.hasLayer(overlays[name]);
                    if (want && !has) overlays[name].addTo(map);
                    else if (!want && has) map.removeLayer(overlays[name]);
                }
            } finally {
                applying = false;
            }
            save();
        }, 0);
    });
}
