/**
 * Build the map layers from the registry.
 *
 * The interesting part is the layer "Luftbild amtlich": Germany has no
 * nationwide aerial imagery service but sixteen state services. To the
 * outside it is one layer nonetheless — every service whose state lies in
 * the view is loaded, so that at a state border both sides are visible at
 * once. Outside, a worldwide fallback layer takes over.
 *
 * On zooming: every tile source has a highest level for which real tiles
 * exist. Above it, drawing continues magnified instead of switching off —
 * blurry is still more usable than black. Whoever leaves the native
 * resolution in doing so gets a one-time notice; map.js takes care of
 * that via mapInfo.
 */

import { settings } from './settings.js';
import { overlaysFor, keepOverlaysPerBase } from './layermemory.js';
import { t } from './i18n.js';
/* STATICALLY AND NOT ON DEMAND, and that is the whole point of the line.
   This module is needed at exactly one moment: when there is no network.
   Fetching it at that moment means fetching it when nothing can be
   fetched — unless it happens to lie in the app cache from an earlier
   visit. It would then work for whoever had been in the administration
   before, and not for whoever had not, which is the worst kind of "works
   on my machine". It weighs nothing; it is a handful of functions. */
import * as protectedStore from './protectedareas.js';

/* ---------------------------------------------------------------------------
 * FETCH TILES WITH CORS WHERE THE SERVICE ALLOWS IT.
 *
 * WHY THIS COUNTS AT ALL — this is not about bandwidth but about the
 * browser's storage quota, so in the end about whether an area can still be
 * downloaded.
 *
 * An <img> without crossOrigin gets an "opaque" response from a foreign
 * server: the page may draw it but not read it — not even its size. So that
 * nothing about foreign content can be inferred from the size, Chrome adds
 * a RANDOM amount to such entries in the storage account. Measured in the
 * browser:
 *
 *   readable (own server)   423 kB real -> 429 kB reported, 1.5 % surcharge
 *   opaque                  the same 6.9 kB tile stored 25 times, charged
 *                           per entry: 0.87 / 1.22 / 1.29 / …
 *                           / 11.8 / 12.7 / 13.5 MB. Mean 7.7 MB.
 *
 * THIS IS NOT AN ESTIMATE OF THE SIZE BUT NOISE — and that is the point of
 * the thing: if the surcharge depended on the real size, it would betray
 * exactly what it is meant to hide. That is why the same tile is charged
 * 0.87 MB one time and 13.5 MB another. Nor does the range apply to tiles,
 * but to every opaque response any page stores — videos, archives, large
 * images. Our seven kilobytes fall under the same rule.
 *
 * The maintainer therefore saw 825 MB occupied without ever having loaded an
 * area — that was around a hundred incidentally collected tiles with maybe
 * 1 MB of real content.
 *
 * WHETHER THE SURCHARGE ALSO COUNTS AGAINST THE QUOTA is NOT settled by
 * that, and the attempt to provoke it turned out differently than
 * expected: 900 opaque entries — nominally 6.4 GB, really 1.26 MB — could
 * be stored without objection, although the browser had beforehand named
 * 2.9 GB as the quota. No QuotaExceededError, nothing. The initially
 * obvious worry that the incidental storage could burst the quota and
 * prevent loading an area is therefore unsubstantiated; whoever wants to
 * write it back in here should measure it first.
 *
 * Still, one should not rely on the number: after deleting ALL test caches
 * the same browser continued to report 6.43 GB occupied, and the quota at
 * exactly the same value. So it does not reliably come back down either.
 *
 * THE REASON FOR THIS CHANGE IS THEREFORE THE PLAINER ONE: the display
 * should tell the truth. A line claiming 825 MB where one megabyte lies
 * makes you doubt your own perception instead of the browser's
 * bookkeeping.
 *
 * WITH crossOrigin the response is readable and is counted honestly. But
 * that cannot simply be switched on everywhere: where the service does not
 * send the clearance, an image marked that way does not load AT ALL
 * anymore. A padded tile would become a missing one — distinctly worse.
 *
 * HENCE THE CAREFUL WAY AROUND: a layer starts without crossOrigin, i.e.
 * exactly as before. As soon as its first tile has loaded, its address is
 * checked once per fetch with CORS. Only when that works does the layer
 * get crossOrigin — and all tiles drawn afterwards are readable. If it
 * fails, everything stays as it was. So nothing can break; it only gets
 * better where it can.
 *
 * The verdict is remembered per host, so that from the second visit on
 * fetching uses CORS from the first tile. There is NO redraw after a
 * "yes": the one screenful that came in opaque the very first time soon
 * falls out of the volatile cache anyway — that would be double loading
 * work and a flicker on the map for nothing.
 * ------------------------------------------------------------------------- */

// 'tile-cors' is the settings key page.php's migration map moved
// 'kachel-cors' to; the stored verdicts live under the new name.
const CORS_MEMORY        = 'tile-cors';
const CORS_DEADLINE_DAYS = 30;

/** At most one first probe per host per session — several layers share one. */
const corsChecked = new Set();
/** Is a probe running right now? Otherwise a screenful of errors fired it dozens of times. */
const corsRunning = new Set();

function corsHost(url) {
    try {
        // Placeholders like {s} stay in place and yield their own but
        // stable key — it then applies to the whole subdomain family.
        return new URL(url, location.href).host;
    } catch {
        return null;
    }
}

/** true / false, or null when unknown or too long ago. */
function corsRemembered(host) {
    const entry = (settings.get(CORS_MEMORY, {}) || {})[host];
    if (!entry) return null;

    // WITH A DEADLINE, because a "no" would otherwise hold forever: a
    // service that retrofits the clearance would never come to enjoy it
    // again — and a single unlucky probe would be a verdict for life.
    const days = (Date.now() - Date.parse(entry.when)) / 86400000;
    return Number.isNaN(days) || days > CORS_DEADLINE_DAYS ? null : entry.ok;
}

function rememberCors(host, yes) {
    const all = { ...(settings.get(CORS_MEMORY, {}) || {}) };
    // 'ja'/'wann' became 'ok'/'when' on 22.08.2026 — legacy.js rewrites
    // the persisted verdicts, so no device forgets what it already knows.
    all[host] = { ok: yes, when: new Date().toISOString() };
    settings.set(CORS_MEMORY, all);
}

/** Fetch one tile with CORS. null means "a probe is already running". */
async function corsProbe(host, url) {
    if (corsRunning.has(host)) return null;
    corsRunning.add(host);
    try {
        const response = await fetch(url, { mode: 'cors', credentials: 'omit' });
        return response.ok;
    } catch {
        // No clearance — or no network. Which of the two, the error does
        // not say; hence the check with navigator.onLine below.
        return false;
    } finally {
        corsRunning.delete(host);
    }
}

/** After the first loaded tile: does CORS work? Then use it from now on. */
async function adoptCors(layer, host, url) {
    if (!url || corsChecked.has(host)) return;
    corsChecked.add(host);

    if (await corsProbe(host, url)) {
        layer.options.crossOrigin = 'anonymous';
        rememberCors(host, true);
    } else if (navigator.onLine) {
        // A "NO" ONLY WITH A NETWORK. In a dead zone every probe fails,
        // and a day in the woods would otherwise set a wrong note for
        // every service that holds for a month.
        rememberCors(host, false);
    }
}

/**
 * The emergency exit: switch CORS back off when tiles no longer arrive with
 * it.
 *
 * WHAT THIS PROTECTS AGAINST: the remembered "yes" holds for a month. If a
 * service withdraws the clearance within that time — or a network sits in
 * between on the road that cuts off the headers — then an image marked
 * with crossOrigin does not load AT ALL anymore. Without this retreat the
 * map would stay white, right until the deadline expires. That would be a
 * bad trade for a storage problem.
 *
 * NOT EVERY ERROR COUNTS: a missing tile image is the normal case here,
 * not the exception — the official services answer with 404 outside their
 * area. So switching off does not happen on the error; there is a check
 * first: only if the probe is actually rejected now was it CORS.
 */
async function withdrawCors(layer, host, url) {
    if (!url || !navigator.onLine) return;

    const response = await corsProbe(host, url);
    if (response !== false) return;   // null = already running, true = was not CORS

    delete layer.options.crossOrigin;
    rememberCors(host, false);
    corsChecked.add(host);
    // Redraw HERE ALREADY, unlike when switching on: there the old image
    // was fine and merely counted unfavorably, here it is missing.
    layer.redraw();
}

/*
 * The hook is placed on L.TileLayer.onAdd, not at the six spots where
 * layers come into being here: every one of them is in the end a
 * TileLayer, the WMS layers and the one with a fallback source included,
 * and L.TileLayer.WMS calls this version from within its own onAdd. One
 * spot instead of six that could be forgotten again with the next new
 * layer type.
 *
 * The layers with an image filter (withImageFilter) remain untouched by this
 * — they set crossOrigin themselves anyway, because they send their tiles
 * through a canvas and must be able to read for that.
 */
const tileOnAdd = L.TileLayer.prototype.onAdd;
L.TileLayer.include({
    onAdd(map) {
        const host = corsHost(this._url);

        // ONLY ONCE PER LAYER: onAdd runs again on every switching of the
        // layer on and off, and without this mark another tileerror
        // watchman accumulated with every layer change — by the tenth
        // change ten probes on the same error.
        if (host && !this._corsGuarded) {
            this._corsGuarded = true;

            const remembered = corsRemembered(host);
            if (remembered === true) {
                this.options.crossOrigin = 'anonymous';
            } else if (remembered === null) {
                // Only after a loaded tile: its address is provenly valid,
                // and that it arrived also means there is a network.
                this.once('tileload', (e) =>
                    adoptCors(this, host, e.tile?.currentSrc || e.tile?.src));
            }

            // As long as fetching uses CORS, the emergency exit stays guarded.
            this.on('tileerror', (e) => {
                if (this.options.crossOrigin) withdrawCors(this, host, e.tile?.src);
            });
        } else if (host && corsRemembered(host) === true) {
            // Switched on again after the probe went well the first time:
            // then with CORS from the first tile on.
            this.options.crossOrigin = 'anonymous';
        }
        return tileOnAdd.call(this, map);
    },
});

/**
 * Dedicated panes for the overlays.
 *
 * Leaflet puts all tile layers into the same pane (tilePane), where base
 * map and overlay have to come to terms via the z-indices. The composite
 * layers assign their own, so the state services lie among themselves in a
 * fixed order — and the contour lines thus ended up under the aerial
 * imagery. With a dedicated pane only the order within the overlays counts
 * anymore; above the base map they lie in any case.
 *
 * **The blend mode belongs on the pane, not on the individual layer.**
 * A pane with a z-index opens its own stacking context; a layer inside it
 * then blends only against its own empty pane instead of against the map
 * beneath. The hillshade looked as if laid over with mere opacity — a
 * white veil over everything, and the relief flips in the eye. Attached to
 * the pane it blends against everything drawn beneath it.
 *
 * Incidentally this is also factually more correct: if two hillshades lie
 * on top of each other at a border, they first settle with each other and
 * then multiply jointly — instead of darkening twice in a row.
 */
function overlayPane(map, blendMode) {
    const name = blendMode ? `mapOverlays-${blendMode}` : 'mapOverlays';
    if (!map.getPane(name)) {
        const pane = map.createPane(name);
        // Blending panes beneath the normal ones: a hillshade belongs
        // under the contour lines, not above. Both between tilePane (200)
        // and overlayPane (400).
        pane.style.zIndex = blendMode ? 250 : 260;
        pane.style.pointerEvents = 'none';
        if (blendMode) pane.style.mixBlendMode = blendMode;
    }
    return name;
}

/**
 * WMS layer that cuts out a service's black border.
 *
 * Veneto delivers black instead of transparent tiles outside its image
 * data — mounted above the neighboring country it would cover up its
 * picture. The older vintage rv:ortofoto_2021 behaves correctly but is
 * three years older.
 *
 * Every tile therefore runs through a canvas. Taking away pure black alone
 * does not suffice: when scaling down, the service blends between image
 * and nothing at the edge; a dark rim would otherwise remain there. But a
 * mere brightness threshold would be wrong, for real forest shadows are
 * just as dark — in the forest near Cortina at level 15 a good 0.25 % of
 * the pixels lie below 16, and it is precisely the forest areas that are
 * to stay intact.
 *
 * Hence two stages: first look for the pure black, then take along only
 * those dark points that border on it. A shadow in the middle of the stand
 * has no connection to the edge and stays.
 *
 * The prerequisite is a CORS clearance from the service, otherwise the
 * browser blocks reading out the canvas. Veneto allows it; should it be
 * missing after all, the image stays unchanged instead of vanishing.
 */
// Black with Veneto, white with basemap.at — the same mechanics, different
// fill color. 'core' recognizes the fill area beyond doubt, 'fringe' the soft
// transition that arises when scaling down; the fringe is only taken along
// when it touches a core, and at most 'rounds' pixels far.
const BLACK = {
    core: (p, i) => { const j = i * 4; return p[j] === 0 && p[j + 1] === 0 && p[j + 2] === 0; },
    fringe: (p, i) => { const j = i * 4; return p[j] <= 40 && p[j + 1] <= 40 && p[j + 2] <= 40; },
    rounds: 3,
};

const WHITE = {
    /**
     * Bright alone does not suffice here: at the Großglockner 0.27 % and
     * on the Zugspitzplatt almost 1 % of real snow lies above 250, and the
     * fill color is not pure white everywhere — at Lake Constance it is
     * 253. What makes the fill area unmistakable is its smoothness: it has
     * no structure whatsoever, while snow varies from pixel to pixel even
     * in the brightest firn.
     *
     * Bright and with four identical neighbors separates the two by a
     * factor of a thousand: fill areas 32–60 % of the tile, snow tiles
     * 0.00–0.02 %.
     */
    core: (p, i, width, height) => {
        const j = i * 4;
        if (p[j] < 250 || p[j + 1] < 250 || p[j + 2] < 250) return false;

        const x = i % width;
        const y = (i - x) / width;
        if (x === 0 || y === 0 || x === width - 1 || y === height - 1) return false;

        for (const offset of [-1, 1, -width, width]) {
            const k = (i + offset) * 4;
            if (p[k] !== p[j] || p[k + 1] !== p[j + 1] || p[k + 2] !== p[j + 2]) return false;
        }
        return true;
    },
    /**
     * Distinctly more generous than with the black, and on purpose:
     * measured across the edge, the value drops within *one* pixel from
     * 251 to 107 through 180 before the real image begins. With a tight
     * threshold exactly this one point would remain — as a fine white
     * stroke along the whole national border.
     *
     * The wide threshold is not dangerous, because only what borders on
     * the fill area is taken along — and because beneath this layer there
     * always lies another. What gets cut away too much here, the Bavarian
     * or worldwide image beneath replaces; checked at two border spots,
     * not a single one of 16384 points was without an image.
     *
     * Twelve rounds instead of three, because the fill edge has JPEG
     * artifacts: values like (255,254,255) or (245,245,245) stand there
     * that neither pass as core nor are reached in three rounds. What was
     * left otherwise was a fine white stroke along the whole national
     * border.
     */
    fringe: (p, i) => { const j = i * 4; return p[j] >= 120 && p[j + 1] >= 120 && p[j + 2] >= 120; },
    rounds: 12,
};

function withImageFilter(BaseClass, filter) {
    return BaseClass.extend({
        // For the vector map, which draws the same tiles its own way and
        // applies the same filter (vectorlayers.js, the stack protocol).
        imageFilter: filter,
        createTile(coords, done) {
            const canvas = L.DomUtil.create('canvas', 'leaflet-tile');
            const size   = this.getTileSize();
            canvas.width  = size.x;
            canvas.height = size.y;

            const image = new Image();
            image.crossOrigin = 'anonymous';

            image.onload = () => {
                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(image, 0, 0, size.x, size.y);
                try {
                    const data = ctx.getImageData(0, 0, size.x, size.y);
                    // The layer is passed along so a filter can also depend
                    // on changeable state — for instance on which classes
                    // are currently hidden.
                    filter(data.data, size.x, size.y, this);
                    ctx.putImageData(data, 0, 0);
                } catch (e) {
                    // Canvas locked (no CORS clearance): then just the
                    // image as delivered — unlovely, but better than
                    // nothing.
                }
                done(null, canvas);
            };
            image.onerror = (e) => done(e, canvas);

            image.src = this.getTileUrl(coords);
            return canvas;
        },
    });
}

const BlackTransparent = withImageFilter(L.TileLayer.WMS,
    (p, b, h) => cutOut(p, b, h, BLACK));

/**
 * A tile layer cut to its region's outline — nothing survives outside it.
 *
 * 18.09.2026, with the topographic map at a distant zoom: „Es
 * ist alles weiß, auch die Schweiz — ist das die Schweizer Karte?" It
 * was. swisstopo's national map draws the whole neighbourhood at small
 * scales, pale relief from Dijon to Innsbruck, and a tile that touches
 * Switzerland at zoom 7 is six hundred kilometres wide. That pale
 * neighbourhood is not white, so the fill-tile cut (WithoutFillTiles)
 * lets it through, and it lay over half of southern Germany like a veil.
 *
 * The outline is the answer, not a threshold: the region layer means „in
 * Switzerland the Swiss map", and the canvas can say exactly that —
 * destination-in with the country's polygon keeps the pixels inside and
 * clears the rest, whatever colour they are. Holes (evenodd) stay holes.
 * The same outlines servicesInView() already decides by, projected into
 * the tile's own pixels at the tile's own zoom.
 *
 * REPLACES the fill-tile cut, it does not add to it: the fill lies
 * outside the outline by definition. Opt in per region (clipToRegion in
 * map_layers.php); the aerial regions keep their cut, because swisstopo
 * and basemap.at draw real imagery a few kilometres past the border and
 * the cut lets that stand.
 */
function withRegionClip(region) {
    return L.TileLayer.extend({
        clipRegion: region,      // for the vector map, as imageFilter above
        createTile(coords, done) {
            const canvas = L.DomUtil.create('canvas', 'leaflet-tile');
            const size   = this.getTileSize();
            canvas.width  = size.x;
            canvas.height = size.y;

            const image = new Image();
            image.crossOrigin = 'anonymous';
            image.onload = () => {
                const ctx = canvas.getContext('2d');
                ctx.drawImage(image, 0, 0, size.x, size.y);
                clipToRegion(ctx, this._map, coords, size, region);
                done(null, canvas);
            };
            image.onerror = (e) => done(e, canvas);
            image.src = this.getTileUrl(coords);
            return canvas;
        },
    });
}

/** Keep only what lies inside the region's rings — in the tile's pixels. */
export function clipToRegion(ctx, map, coords, size, region) {
    const origin = coords.scaleBy(size);
    const path = new Path2D();
    for (const polygon of region.polygons) {
        for (const ring of polygon) {
            ring.forEach(([lon, lat], i) => {
                const p = map.project(L.latLng(lat, lon), coords.z).subtract(origin);
                if (i === 0) path.moveTo(p.x, p.y); else path.lineTo(p.x, p.y);
            });
            path.closePath();
        }
    }
    ctx.globalCompositeOperation = 'destination-in';
    ctx.fill(path, 'evenodd');
    ctx.globalCompositeOperation = 'source-over';
}

/**
 * Cut out white fill areas.
 *
 * Two services deliver beyond their area not 404s but pure white — and
 * since both lie in the stack above the German states, this white covered
 * up their aerial imagery:
 *
 *   basemap.at   up to around ten kilometers past the border, even just
 *                for the German half of a border tile. Visible as white
 *                patches at Lake Constance and near Bad Reichenhall.
 *   swisstopo    generously keeps drawing all around Switzerland and only
 *                turns white further out — near Rosenheim a 668-byte
 *                white tile. Hence it only showed at small scale, but
 *                there over a wide area.
 *
 * Cutting out is safe with both, because even snow does not come out
 * uniformly white in these images: measured, fill tiles run at 32 to 98 %
 * bright and smooth pixels, while Pitztal, Großglockner, Aletsch,
 * Matterhorn and Jungfrau all lie between 0.00 and 0.02 %.
 */
/**
 * A tile layer with a second source.
 *
 * WHAT FOR. Esri Clarity shows Central Europe distinctly more neutrally
 * than the older World Imagery — measured on the same forest tile 50/53/45
 * instead of 35/46/13, i.e. without the olive cast. But it has holes: in
 * the Spessart it ends at level 18, while World Imagery delivers up to 20.
 * A plain swap would thus tear a hole exactly where you look in deepest.
 *
 * ONE LAYER, NOT TWO. Laying both on top of each other would be the
 * obvious way and the wrong one: it doubled the tiles — when displaying
 * as when preloading for the woods. Instead every tile asks the good
 * source first and only falls back when nothing lies there. Exactly one
 * is always loaded.
 *
 * OFFLINE THE SAME APPLIES: preloading stores per tile what actually
 * answered (see areas.js), and here the fallback address finds the same
 * way into the cache. No double space on the device.
 */
const WithFallbackSource = L.TileLayer.extend({
    createTile(coords, done) {
        const image = document.createElement('img');
        image.alt = '';
        image.setAttribute('role', 'presentation');

        L.DomEvent.on(image, 'load', L.Util.bind(this._tileOnLoad, this, done, image));
        L.DomEvent.on(image, 'error', L.Util.bind(function () {
            // ONLY NOW the second source, and only once. Without the mark
            // an error in both sources would run on endlessly as an
            // alternation — per tile, at that.
            if (!image.dataset.alternated && this.options.alternateUrl) {
                image.dataset.alternated = '1';
                image.src = L.Util.template(this.options.alternateUrl,
                    { ...coords, s: this._getSubdomain(coords), r: L.Browser.retina ? '@2x' : '' });
                return;
            }
            this._tileOnError(done, image, new Error(t('Kachel fehlt in beiden Quellen')));
        }, this));

        if (this.options.crossOrigin || this.options.crossOrigin === '') {
            image.crossOrigin = this.options.crossOrigin === true ? '' : this.options.crossOrigin;
        }
        image.src = this.getTileUrl(coords);
        return image;
    },
});

const WithoutFillTiles = withImageFilter(L.TileLayer,
    (p, b, h) => cutOut(p, b, h, WHITE));

function cutOut(p, width, height, color) {
    const count = width * height;
    const gone = new Uint8Array(count);

    // 1. The unambiguous nothing: the fill area itself.
    for (let i = 0; i < count; i++) {
        if (color.core(p, i, width, height)) gone[i] = 1;
    }

    // 2. Take along the soft fringe all around — but only what touches the
    //    fill color, so similar tones in the image interior stay untouched.
    for (let round = 0; round < color.rounds; round++) {
        const before = gone.slice();
        let changed = false;
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                const i = y * width + x;
                if (before[i]) continue;
                if (!color.fringe(p, i)) continue;
                if ((x > 0           && before[i - 1]) ||
                    (x < width - 1   && before[i + 1]) ||
                    (y > 0           && before[i - width]) ||
                    (y < height - 1  && before[i + width])) {
                    gone[i] = 1;
                    changed = true;
                }
            }
        }
        if (!changed) break;
    }

    for (let i = 0; i < count; i++) if (gone[i]) p[i * 4 + 3] = 0;
}

/* --- The protected areas' data, for both engines ---------------------------
 *
 * The Leaflet map draws them with OutlineLayer below, the vector map with
 * its own layer in vectorlayers.js (30.09.2026). What they must not differ
 * in lives here once: where the areas come from, what an area is called,
 * and what a tap on it shows. */

/**
 * One kind's areas in a box, as a GeoJSON collection — from the provider,
 * or from the device when there is no line. null when the device has
 * nothing for it; throws when the provider fails and the device cannot
 * stand in.
 *
 * @param {{url: string}} source  the registry entry (or what carries its url)
 * @param {object} kind           one of the entry's kinds
 * @param {L.LatLngBounds} area
 * @param {AbortSignal} signal
 */
export async function outlineKindData(source, kind, area, signal) {
    const s = area.getSouth(), w = area.getWest();
    const n = area.getNorth(), o = area.getEast();

    /* WITHOUT A NETWORK, FROM THE DEVICE — if a state was fetched for
       exactly this. The boundaries are the one overlay one needs where
       one had not planned to be, so they must not be the first thing to
       go when reception does. Nothing is asked of the provider here:
       without a line the request would only run into its timeout, and
       the map would wait for it. */
    if (!navigator.onLine) {
        return await kindDataFromDevice(kind, area);
    }

    /* THE BOX IN THE SERVICE'S OWN GRID, the output in degrees — the trap
       of this service, and it has moved once already.

       Until September 2026 the box went in degrees, latitude first (WFS
       2.0 for EPSG:4326). Then the provider's capabilities came to name
       urn:ogc:def:crs:EPSG::25832 as every kind's DefaultCRS, and a box
       in degrees — either order, with or without ',EPSG:4326' behind it,
       with the old or the new type names — found nothing any more,
       anywhere: HTTP 200, an empty collection, no error. Measured on
       30.09.2026 over the Hoher Vogelsberg: in degrees 0 areas, the same
       box in UTM 32N five, starting with „In der Breungeshainer Heide".
       Both maps drew no protected area online from then on, and nothing
       said so. So the box goes in UTM 32N, with its CRS named, which
       holds whichever default the provider picks next.

       The OUTPUT stays longitude before latitude, as GeoJSON prescribes
       and Leaflet reads it — only without SRSNAME: with 'EPSG::4326' the
       output comes latitude-first, and the areas land in Somalia instead
       of the Taunus, again without an error surfacing anywhere. */
    const box = utm32Box(s, w, n, o);
    const p = new URLSearchParams({
        SERVICE: 'WFS', VERSION: '2.0.0', REQUEST: 'GetFeature',
        TYPENAMES: kind.type, OUTPUTFORMAT: 'GEOJSON', COUNT: '300',
        BBOX: `${box.join(',')},urn:ogc:def:crs:EPSG::25832`,
    });

    try {
        const response = await fetch(`${source.url}?${p}`, { signal });
        if (!response.ok) throw new Error(t('HTTP %s für %s', response.status, kind.text));
        return await response.json();
    } catch (e) {
        // The line was there a moment ago and is not any more — a dead
        // spot, a captive portal, the provider throttling. What lies on
        // the device is then better than nothing, and it is the same
        // data.
        if (e.name === 'AbortError') throw e;
        const fallback = await kindDataFromDevice(kind, area);
        if (fallback) return fallback;
        throw e;
    }
}

/**
 * WGS84 → ETRS89 / UTM zone 32N (EPSG:25832), the protected-area service's
 * own grid. ETRS89 and WGS84 lie well under a metre apart here — nothing
 * for a box that is padded by a quarter anyway, and zone 32's grid runs on
 * across the east of the country as the service uses it there too.
 * Snyder's transverse Mercator series, as every UTM converter has it.
 */
function utm32(lat, lon) {
    const a = 6378137;
    const f = 1 / 298.257222101;            // GRS80
    const k0 = 0.9996;
    const e2 = f * (2 - f);
    const ep2 = e2 / (1 - e2);
    const phi = lat * Math.PI / 180;
    const sin = Math.sin(phi), cos = Math.cos(phi), tan = Math.tan(phi);
    const N = a / Math.sqrt(1 - e2 * sin * sin);
    const T = tan * tan;
    const C = ep2 * cos * cos;
    const A = cos * (lon - 9) * Math.PI / 180;
    const e4 = e2 * e2, e6 = e4 * e2;
    const M = a * ((1 - e2 / 4 - 3 * e4 / 64 - 5 * e6 / 256) * phi
        - (3 * e2 / 8 + 3 * e4 / 32 + 45 * e6 / 1024) * Math.sin(2 * phi)
        + (15 * e4 / 256 + 45 * e6 / 1024) * Math.sin(4 * phi)
        - (35 * e6 / 3072) * Math.sin(6 * phi));
    const east = 500000 + k0 * N * (A + (1 - T + C) * A ** 3 / 6
        + (5 - 18 * T + T * T + 72 * C - 58 * ep2) * A ** 5 / 120);
    const north = k0 * (M + N * tan * (A * A / 2 + (5 - T + 9 * C + 4 * C * C) * A ** 4 / 24
        + (61 - 58 * T + T * T + 600 * C - 330 * ep2) * A ** 6 / 720));
    return [east, north];
}

/**
 * A box in degrees as a box in UTM 32N — [minEast, minNorth, maxEast,
 * maxNorth]. The grid stands at a slant to the parallels, so the edges'
 * midpoints are taken along with the corners: a bulging edge would
 * otherwise reach past the box.
 */
function utm32Box(s, w, n, o) {
    const points = [[s, w], [s, o], [n, w], [n, o],
        [s, (w + o) / 2], [n, (w + o) / 2], [(s + n) / 2, w], [(s + n) / 2, o]].map(([la, lo]) => utm32(la, lo));
    const east = points.map((q) => q[0]);
    const north = points.map((q) => q[1]);
    return [Math.min(...east), Math.min(...north), Math.max(...east), Math.max(...north)].map(Math.round);
}

/**
 * The same kind of area, but out of the device store.
 *
 * Returns null when nothing is stored for it — the caller then keeps
 * its own error, which is the truthful one: without a line and without
 * a download there simply are no boundaries.
 */
async function kindDataFromDevice(kind, area) {
    let stock;
    try {
        stock = await protectedStore.fromDevice(area);
    } catch (e) {
        console.warn('Protected areas unreadable from the device:', e);
        return null;
    }

    const data = stock.get(kind.type);
    if (!data || data.features.length === 0) {
        // Said out loud, because from outside this is indistinguishable
        // from a broken feature: no line, no boundaries, no reason.
        console.warn(`Ohne Netz: für ${kind.text} liegt in diesem Ausschnitt `
            + 'nichts auf dem Gerät. Bundesland unter Verwaltung → '
            + 'Offline-Daten herunterladen.');
        return null;
    }
    return data;
}

/** An area's name — the field is called differently per kind. */
export function areaName(kind, properties) {
    const e = properties || {};
    return e[kind.nameField] || e.NAME || e.Gebietsname || t('ohne Namen');
}

/**
 * The name as it stands on the map. Very long names get shortened —
 * "Reifenberger Wiesen, Schmittgrund b. Oberreifenberg mit angr. Fl."
 * otherwise lays itself over half the area. It stands complete in the
 * profile.
 */
export function shortAreaName(name) {
    return name.length > 42 ? name.slice(0, 40).trimEnd() + '…' : name;
}

/**
 * Protected areas as our own drawing instead of a finished picture.
 *
 * The WMS of the Federal Agency for Nature Conservation knows only one
 * style: hatched areas, hardly readable on aerial imagery and without any
 * statement of which area you have in front of you. But the same service
 * also hands out the areas as GeoJSON. With that we draw strong outlines,
 * write the name inside, and a tap shows ordinance and surface.
 *
 * Only the visible view is ever loaded, and only from a zoom level on at
 * which the data volume is justifiable. Two precautions keep this calm: a
 * somewhat larger view than necessary, so small panning triggers no new
 * request, and an abort of the still-running request as soon as the map
 * wanders on.
 */
class OutlineLayer {
    constructor(entry) {
        this.name        = entry.name;
        this.url         = entry.url;
        this.kinds       = entry.kinds;
        this.minZoom     = entry.minZoom || 0;
        this.attribution = entry.attribution;

        this.leafletLayer = L.layerGroup();
        this.loadedArea = null;   // view the data is valid for
        this.aborter = null;           // abort running requests
        this.tooFarOut = false;   // for the notice in the status line
        this.parts = new Map();        // color of the kind -> drawn areas
        this.hidden = new Set(); // kinds deselected via the legend

        // Deselectable via the legend: unlike with the raster, nothing has
        // to be redrawn for that; the kind is simply unhooked.
        this.leafletLayer.setMapClasses = (colors) => {
            this.hidden = new Set(colors);
            this.buildDisplay();
        };

        // Zoomed further out nothing is loaded — then its legend should
        // vanish too, instead of promising areas that right now are none.
        //
        // The map is passed instead of buffering the level: Leaflet fires
        // 'zoomend' before 'moveend', so a remembered value would, when
        // drawing the legend, still be the one from before the zoom.
        this.leafletLayer.mapIsDrawing = (map) => map.getZoom() >= this.minZoom;
    }

    /** Hang the not-deselected kinds into the layer. */
    buildDisplay() {
        this.leafletLayer.clearLayers();
        for (const [color, layer] of this.parts) {
            if (!this.hidden.has(color)) this.leafletLayer.addLayer(layer);
        }
    }

    /** For the notice: is nothing on right now because zoomed out too far? */
    state(map) {
        if (!map.hasLayer(this.leafletLayer)) return null;
        return map.getZoom() < this.minZoom
            ? { kind: 'zoom', fromZoom: this.minZoom }
            : { kind: 'ok' };
    }

    async refresh(map) {
        if (!map.hasLayer(this.leafletLayer)) {
            this.aborter?.abort();
            this.loadedArea = null;
            return;
        }

        if (map.getZoom() < this.minZoom) {
            this.aborter?.abort();
            this.parts.clear();
            this.leafletLayer.clearLayers();
            this.loadedArea = null;
            this.tooFarOut = true;
            return;
        }
        this.tooFarOut = false;

        // Already loaded? Then only reassess the labeling.
        if (this.loadedArea?.contains(map.getBounds())) {
            this.adjustLabels(map);
            return;
        }

        // Load with a margin, so not every pan asks anew.
        const area = map.getBounds().pad(0.25);

        this.aborter?.abort();
        this.aborter = new AbortController();
        const signal = this.aborter.signal;

        try {
            const parts = await Promise.all(
                this.kinds.map(kind => this.loadKind(kind, area, signal))
            );
            if (signal.aborted) return;

            this.parts = new Map();
            this.kinds.forEach((kind, i) => {
                if (parts[i]) this.parts.set(kind.color, parts[i]);
            });
            this.buildDisplay();
            this.loadedArea = area;
            this.adjustLabels(map);
        } catch (e) {
            if (e.name !== 'AbortError') {
                console.warn('Schutzgebiete nicht ladbar:', e);
            }
        }
    }

    async loadKind(kind, area, signal) {
        const data = await outlineKindData(this, kind, area, signal);
        return data ? this.fromGeoJson(kind, data) : null;
    }

    /**
     * Build the Leaflet layer from a GeoJSON collection.
     *
     * SEPARATE BECAUSE THERE ARE TWO WAYS IN. The collection comes either
     * from the provider or out of the device store, and it must look and
     * behave identically whichever it was — same colours, same names, same
     * profile on tapping. Two copies of this would have drifted apart at
     * the first change to the styling, and only one of them would ever be
     * looked at while developing: the online one.
     */
    fromGeoJson(kind, data) {
        return L.geoJSON(data, {
            // Strong outline, hardly any fill. The fill must not fall away
            // entirely — without it the area could not be tapped.
            style: () => ({
                color: kind.color,
                weight: 3.5,
                opacity: 1,
                fillColor: kind.color,
                fillOpacity: 0.08,
            }),
            attribution: this.attribution,
            onEachFeature: (feature, layer) => {
                const e = feature.properties || {};
                const name = areaName(kind, e);

                layer.mapRegionName = name;

                // Name in the color of its area kind: on a bright topo map
                // white lettering is hardly readable, and the color says
                // in passing what kind of area this is, without having to
                // hunt for the outline beneath. Long names shortened, see
                // shortAreaName.
                const label = L.DomUtil.create('span');
                label.textContent = shortAreaName(name);
                label.style.color = kind.color;

                layer.bindTooltip(label, {
                    permanent: true, direction: 'center', className: 'area-name',
                });
                layer.bindPopup(() => factSheet(kind, name, e));
                layer.on('mouseover', () => layer.setStyle({ weight: 5, fillOpacity: 0.2 }));
                layer.on('mouseout',  () => layer.setStyle({ weight: 3.5, fillOpacity: 0.08 }));
            },
        });
    }

    /**
     * Show names only where the area is large enough for them. Otherwise,
     * with small areas, the labels lie on top of each other and you read
     * nothing at all anymore.
     */
    adjustLabels(map) {
        const MIN_WIDTH = 80;   // pixels
        const MIN_HEIGHT  = 26;

        this.leafletLayer.eachLayer(collection => collection.eachLayer?.(layer => {
            if (!layer.getBounds || !layer.getTooltip) return;
            const g = layer.getBounds();
            const a = map.latLngToContainerPoint(g.getNorthWest());
            const b = map.latLngToContainerPoint(g.getSouthEast());
            // If the center lies outside the picture, only a cut-off word
            // hangs at the map edge — with no recognizable area to it.
            const center = layer.getCenter?.() ?? g.getCenter();
            const visible = map.getBounds().contains(center);

            const fits = visible
                       && Math.abs(b.x - a.x) >= MIN_WIDTH
                       && Math.abs(b.y - a.y) >= MIN_HEIGHT;

            const tooltip = layer.getTooltip();
            if (!tooltip) return;
            if (fits && !layer.isTooltipOpen()) layer.openTooltip();
            if (!fits && layer.isTooltipOpen()) layer.closeTooltip();
        }));
    }
}

/** Content of the window when tapping an area. */
export function factSheet(kind, name, e) {
    const box = L.DomUtil.create('div', 'area-profile');

    const titleEl = L.DomUtil.create('strong', '', box);
    titleEl.textContent = name;

    const sub = L.DomUtil.create('div', 'area-type', box);
    sub.textContent = kind.text;
    sub.style.color = kind.color;

    /* Hectares in FLAECHE; the FFH areas carry none since the provider's
       rebuild of September 2026, only the shape's own area in square
       metres of its UTM grid (st_area_shape_ — the Altkönig's FFH area
       750,470 m², beside its nature reserve's 204 ha). */
    const area = e.FLAECHE != null ? Number(e.FLAECHE) : Number(e.st_area_shape_) / 10000;
    if (Number.isFinite(area) && area > 0) {
        const z = L.DomUtil.create('div', '', box);
        // FLAECHE is in hectares.
        z.textContent = area >= 100
            ? `${(area / 100).toLocaleString('de-DE', { maximumFractionDigits: 1 })} km²`
            : `${area.toLocaleString('de-DE', { maximumFractionDigits: 1 })} ha`;
    }

    const link = (text, target) => {
        const a = L.DomUtil.create('a', '', box);
        a.href = target;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.textContent = text;
    };

    const ordinance = e.LEG_LINK || e.URL_der_gueltigen_Verordnung;
    if (ordinance && /^https?:/.test(ordinance)) link(t('Verordnung'), ordinance);

    /**
     * For FFH areas the standard data form lists the habitat types and
     * species for which the area was reported in the first place — that
     * is, the reason for the designation, ordered by EU code at that. That
     * stands in no nature-reserve ordinance, and it is the far more useful
     * answer.
     *
     * The code is already in the data, behind the state prefix:
     * "HE_DE5615304" -> "DE5615304".
     *
     * On the address form: the earlier address SDF.aspx?site=DE… is
     * redirected by the EEA itself to exactly this one, with the code
     * unchanged. The content of the page could not be cross-checked when
     * building this in — it only loads its data later in the browser.
     */
    // SITECODE since the rebuild; before it the number with its state
    // in front, which downloads on a device still carry.
    const areaNumber = String(e.SITECODE || e.Gebietsnummer_mit_Landeskuerzel || '');
    const euKey = areaNumber.split('_').pop();
    if (/^[A-Z]{2}\d{7}$/.test(euKey)) {
        link(t('Lebensraumtypen und Arten'),
                `https://natura2000.eea.europa.eu/Natura2000/sdf/#/sdf?site=${euKey}`);
    }
    return box;
}

/**
 * A picture layer's description (map_layers.php type 'image' — today the
 * rain): its address tagged with the day it was made, so a browser that
 * keeps the picture a day still shows the new one the morning after, and
 * its hint with the window it shows („Regen vom 10.09. bis 23.09. …").
 * The registry's address and hint while the description is not there.
 */
export async function imageMeta(entry, baseUrl) {
    const base = { url: baseUrl + entry.url, hint: entry.hint, note: '' };
    if (!entry.meta) return base;
    try {
        const meta = await (await fetch(baseUrl + entry.meta, { cache: 'no-cache' })).json();
        if (!meta?.from || !meta?.to) return base;
        const day = (iso) => { const [, m, d] = iso.split('-'); return `${d}.${m}.`; };
        return {
            url: `${baseUrl}${entry.url}&v=${encodeURIComponent(meta.made ?? meta.to)}`,
            // No full stop of its own: the date already ends the sentence („bis 23.09.").
            hint: t('Regen vom %s bis %s', day(meta.from), day(meta.to)) + ' ' + (entry.hint ?? ''),
            // Under the key only the fortnight — no explanation (map.js, the legend).
            note: `${day(meta.from)}–${day(meta.to)}`,
        };
    } catch {
        return base;
    }
}

export function buildLayers(registry, map, baseUrl) {
    const baseMaps = {};
    const overlays    = {};
    const areaLayers = [];      // all layers composed by federal state
    const outlineLayers  = [];      // layers that load geometries on demand
    let aerialOfficial = null;
    let protectedLayer   = null;
    let tracksLayer = null;     // the recorded tracks — filled by map.js

    const mapMaxZoom = map.getMaxZoom();

    for (const entry of registry) {
        // Overlays get the pane that belongs to their blend mode.
        const pane = entry.group === 'overlay'
            ? overlayPane(map, entry.blendMode)
            : null;

        /* THE RECORDED TRACKS: an empty group, nothing more. Its content
           comes from the device's store and is drawn by map.js
           (drawTracks) — this file only gives the switch a place in the
           picker, with the same memory as every other overlay. Leaflet's
           own overlayPane (400): above every tile overlay (250/260),
           below the markers (600), where a line under a find belongs. */
        if (entry.type === 'tracks') {
            const group = L.layerGroup();
            group.mapHint = entry.hint;
            group.mapName = entry.name;
            overlays[entry.name] = group;
            tracksLayer = group;
            continue;
        }

        if (entry.type === 'image') {
            // One picture over a box (the rain): Leaflet lays it on as it is.
            const [w, s, e, n] = entry.box;
            const picture = L.imageOverlay(baseUrl + entry.url, [[s, w], [n, e]], {
                opacity: entry.opacity ?? 1, attribution: entry.attribution, interactive: false,
                pane: overlayPane(map, null),
            });
            picture.mapHint = entry.hint;
            picture.mapLegendNote = '';
            picture.mapLegend = entry.legend;
            picture.mapNoPrefetch = entry.noPrefetch;
            picture.mapName = entry.name;
            overlays[entry.name] = picture;
            imageMeta(entry, baseUrl).then(({ url, hint, note }) => {
                picture.setUrl(url);
                picture.mapHint = hint;
                picture.mapLegendNote = note;
                map.fire('layerdescribed');
            });
            continue;
        }

        if (entry.type === 'wfs-outline') {
            const outline = new OutlineLayer(entry);
            outlineLayers.push(outline);
            outline.leafletLayer.mapHint = entry.hint;
            outline.leafletLayer.mapNoPrefetch = entry.noPrefetch;
            outline.leafletLayer.mapLegend = entry.legend;
            outline.leafletLayer.mapName    = entry.name;
            outline.leafletLayer.mapOutline  = outline;
            overlays[entry.name] = outline.leafletLayer;
            if (entry.id === 'protected_areas') protectedLayer = outline;
            continue;
        }

        if (entry.type === 'wms-by-area') {
            // The aerial imagery's lettering draws in the overlay pane, so
            // it lies above the hillshade and the other overlays.
            const areaLayer = new RegionLayer(entry, baseUrl, pane, {
                pane: overlayPane(map, null),
                mapMaxZoom,
            });
            areaLayers.push(areaLayer);

            if (entry.group === 'overlay') {
                areaLayer.leafletLayer.mapHint = entry.hint;
                areaLayer.leafletLayer.mapNoPrefetch = entry.noPrefetch;
                areaLayer.leafletLayer.mapLegend = entry.legend;
                areaLayer.leafletLayer.mapName    = entry.name;
                overlays[entry.name] = areaLayer.leafletLayer;
            } else {
                // The highest real level depends on which area is
                // currently drawing — hence queried instead of fixed.
                areaLayer.leafletLayer.mapInfo = {
                    name: entry.name,
                    get nativeMax() { return areaLayer.nativeMaxCurrent ?? (entry.nativeMax || 19); },
                };
                // Only the aerial imagery knows the message "only exists
                // zoomed further in" — the topographic map has no such
                // gap, its fallback draws everywhere.
                if (entry.id === 'aerial_official') {
                    aerialOfficial = areaLayer;
                }
                baseMaps[entry.name] = areaLayer.leafletLayer;
            }
            continue;
        }

        const layer = entry.type === 'wms'
            ? wmsLayer(entry, pane)
            : tileLayer(entry, mapMaxZoom);

        // Some services must not be downloaded wholesale — preloading
        // reads that off here. See the registry.
        layer.mapNoPrefetch = entry.noPrefetch;

        if (entry.group === 'overlay') {
            overlays[entry.name] = layer;
        } else {
            layer.mapInfo = { name: entry.name, nativeMax: entry.maxZoom || 19 };
            baseMaps[entry.name] = layer;
        }
    }

    // Starting layer: the last chosen one, otherwise the official aerial
    // imagery — that is the reason this application exists.
    const remembered = settings.get('baseMap');
    const start = (remembered && baseMaps[remembered]) || Object.values(baseMaps)[0];
    start.addTo(map);

    // Restore the overlays last switched on WITH THIS BASE MAP (layermemory.js,
    // 06.10.2026). Names that no longer exist are silently passed over.
    const startName = Object.keys(baseMaps).find((name) => baseMaps[name] === start);
    for (const name of overlaysFor(settings, startName)) {
        overlays[name]?.addTo(map);
    }

    map.on('baselayerchange', (e) => {
        settings.set('baseMap', e.name);
        // A just-switched-on regional base map has no stack yet, and the
        // map can meanwhile stand somewhere else.
        for (const areaLayer of areaLayers) areaLayer.refresh(map);
    });

    // Each base map keeps its own overlays (layermemory.js).
    keepOverlaysPerBase(settings, map, overlays, startName);
    map.on('overlayadd overlayremove', (e) => {
        // A just-switched-on overlay has no content yet.
        e.layer?.mapRegion?.refresh(map);
        e.layer?.mapOutline?.refresh(map);
    });

    for (const areaLayer of areaLayers) areaLayer.loadBorders(map);

    return {
        baseMaps,
        overlays,
        tracksLayer,
        startLayer: start,
        updateAfterArea: () => {
            for (const areaLayer of areaLayers) areaLayer.refresh(map);
            for (const outline of outlineLayers)  outline.refresh(map);
        },
        // Which services are drawing right now — for troubleshooting in
        // the console. Switched-off layers report null instead of their
        // last state: it would otherwise stand there, because switched off
        // they do not update themselves.
        currentServices: () => Object.fromEntries(
            areaLayers.map(g => [
                g.name,
                map.hasLayer(g.leafletLayer) ? g.stackSignature : null,
            ])
        ),
        // Why the protected areas are drawing nothing right now — for the notice.
        protectedAreasState: () => protectedLayer?.state(map) ?? null,
    };
}

/**
 * maxZoom and maxNativeZoom are two different things: maxNativeZoom is the
 * highest level for which real tiles exist, maxZoom the highest at which
 * the layer is drawn at all. If both stand at the same value, the map
 * vanishes on zooming further — exactly what is not supposed to happen.
 */
export function tileLayer(entry, mapMaxZoom) {
    return L.tileLayer(entry.url, {
        attribution: entry.attribution,
        maxZoom: mapMaxZoom,
        maxNativeZoom: entry.maxZoom || 19,
        subdomains: entry.subdomains || 'abc',
    });
}

/**
 * Lettering grids for the aerial imagery.
 *
 * Place names, waters, borders and roads come from two tile grids and
 * belong firmly to the aerial imagery: the other base maps bring their
 * lettering themselves; there they would be doubled. Hence no checkbox of
 * their own, but part of the aerial imagery layer — they appear and vanish
 * with it.
 *
 * Drawing nevertheless happens in the overlay pane, not the base map pane:
 * this way the lettering lies above the hillshade and the overlays and stays
 * readable. The order among themselves counts too — the names above the
 * roads, otherwise the lines draw over the lettering.
 */
function labelLayers(list, pane, mapMaxZoom, attribution) {
    return (list || []).map((part, number) => L.tileLayer(part.url, {
        attribution: number === 0 ? attribution : undefined,
        maxZoom: mapMaxZoom,
        maxNativeZoom: part.maxZoom || 19,
        pane: pane,
        zIndex: 70 + number,
    }));
}

/**
 * Raster layer in which individual classes can be hidden.
 *
 * The tree-species map comes from the service as a finished picture;
 * deselecting individual species is not provided for by the protocol. But
 * measured, the service delivers a palette PNG with exactly eleven colors,
 * and precisely those from its legend at that — no blurring, no
 * compression artifacts. With that, every class can be recognized by its
 * color and made transparent to the pixel, without having to ask the
 * service for something it cannot do.
 */
const ClassRaster = withImageFilter(L.TileLayer.WMS, (p, width, height, layer) =>
    filterClasses(p, width, height, layer.mapHidden, layer.mapRepaint));

/**
 * Hide and repaint classes in a tile's pixels. ClassRaster's walk, and the
 * vector map's too (its tile protocol in vectorlayers.js) — one walk, so the
 * two maps cannot come to disagree about which pixel is which species.
 *
 * @param {Uint8ClampedArray} p      RGBA
 * @param {Set<number>} hidden       provider colours to make transparent
 * @param {Map<number, number[]>} repaint  provider colour → the bytes to draw
 */
export function filterClasses(p, width, height, hidden, repaint) {
    if (!hidden?.size && !repaint?.size) return;

    for (let i = 0; i < width * height; i++) {
        const j = i * 4;
        if (p[j + 3] === 0) continue;
        const colour = (p[j] << 16) | (p[j + 1] << 8) | p[j + 2];
        if (hidden?.has(colour)) { p[j + 3] = 0; continue; }
        /* AND A CLASS MAY BE REPAINTED (11.09.2026). Two species arrived
           in tones the eye cannot keep apart on the finished picture —
           two yellows and two greens; what the service delivers is not up
           for discussion, but what we draw is. Recognised is still the
           colour the PROVIDER sent, which is why the palette keeps it as
           'color' and the new one rides along as 'show'. The same walk
           through the pixels that hides a class can just as well give it
           another colour, so this costs nothing on top. */
        const fresh = repaint?.get(colour);
        if (fresh !== undefined) {
            p[j]     = fresh[0];
            p[j + 1] = fresh[1];
            p[j + 2] = fresh[2];
        }
    }
}

/** What the provider's colours are to be redrawn as — from the key's 'show'. */
export function repaintOf(legend) {
    return new Map((legend ?? [])
        .filter(e => e.show)
        .map(e => [colorNumber(e.color), colorBytes(e.show)]));
}

/** '#rrggbb' as a number, as it arises in the pixel comparison. */
export function colorNumber(hex) {
    return parseInt(String(hex).replace('#', ''), 16);
}

/** '#rrggbb' as the three bytes the pixel buffer wants. */
function colorBytes(hex) {
    const n = colorNumber(hex);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

export function wmsLayer(entry, pane) {
    // Only layers with a color key need the canvas; all others stay on
    // the straight path without a detour through a drawing buffer.
    const Kind = entry.legend?.length ? ClassRaster : L.TileLayer.WMS;

    const layer = new Kind(entry.url, {
        layers: entry.layers,
        format: entry.format || 'image/png',
        transparent: entry.format !== 'image/jpeg',
        version: '1.3.0',
        attribution: entry.attribution,
        maxZoom: 20,
        opacity: entry.opacity ?? 1,
        // Set only if it really is an overlay: an explicit
        // 'pane: undefined' overrides Leaflet's default 'tilePane', and
        // the layer then lands in no pane at all.
        ...(pane ? { pane: pane } : {}),
        // Larger tiles for the hillshade: the service computes the slope
        // per request and has no neighboring pixels at the tile edge,
        // which leaves a brighter line standing there. It becomes visible
        // only when multiplying, as a fine grid over the map. Four times
        // larger tiles mean four times fewer such edges.
        tileSize: entry.tileSize || 256,
    });

    // Hillshades are opaque, predominantly white graphics. With
    // multiplication white stays without effect and only the slope
    // shadows darken — that is how a relief map is meant. With mere
    // opacity a bright veil would arise over the whole map instead, and
    // the weakened relief easily flips in the eye.
    //
    // The blend mode belongs on the layer's container, not in the Leaflet
    // options; the container only exists once the layer lies on the map.
    if (entry.blendMode) {
        layer.on('add', () => {
            const container = layer.getContainer();
            if (container) container.style.mixBlendMode = entry.blendMode;
        });
    }

    layer.mapHint = entry.hint;
    layer.mapLegend = entry.legend;
    layer.mapName    = entry.name;

    if (Kind === ClassRaster) {
        layer.mapHidden = new Set();
        /* What the provider's colour is to be redrawn as — empty for
           every layer whose key says nothing about it. Built once here
           and not per tile: it is the same map for all of them. */
        layer.mapRepaint = repaintOf(entry.legend);
        layer.setMapClasses = (colors) => {
            layer.mapHidden = new Set([...colors].map(colorNumber));
            // The tiles lie there fully drawn; only a rebuild sends them
            // through the filter once more.
            layer.redraw();
        };
    }
    return layer;
}

/**
 * A layer composed of state services.
 *
 * Germany has no nationwide aerial imagery, but one service of its own
 * per state, and so have its neighbours. To the outside this is
 * nevertheless a single layer here: one entry in the picker, one checkbox.
 *
 * Every such layer runs through the same class; they differ only in
 * whether a worldwide substitute exists and whether the layer may deliver
 * opaquely.
 *
 * What is decisive is that **all** states lying in the view are loaded,
 * not only the one under the map center. Otherwise there would be a hard
 * cut at every state border: on the Rhine between Wiesbaden and Mainz you
 * would see, depending on the map center, either only the Hessian or only
 * the Rhineland-Palatinate side. Laid on top of each other the services
 * complement one another without gaps, because each delivers transparent
 * outside its area.
 *
 * The order in the stack is fixed (that of the registry) and deliberately
 * does not depend on the map center — otherwise the picture would
 * reshuffle when crossing the border, i.e. exactly the flicker we want to
 * get rid of.
 *
 * The worldwide fallback layer (Esri) goes beneath when
 *   - no state service is responsible (abroad, Saarland, Berlin, Hamburg),
 *   - zoomed out too far,
 *   - or the view juts out beyond the covered states and a black stripe
 *     would otherwise arise.
 */
export class RegionLayer {
    constructor(entry, baseUrl, pane, labelOptions = {}) {
        this.name          = entry.name;
        this.format        = entry.format || 'image/jpeg';
        this.attribution   = entry.attribution;
        this.maxZoom       = entry.maxZoom || 20;
        this.minZoomOverall = entry.minZoomOverall || 0;
        // The size a WMS service is asked in — see 'wmsTileSize' in the registry.
        this.wmsTileSize = entry.wmsTileSize || 256;
        /* Where a service marked 'relay' is asked instead of its own
           address — the vector map passes it (includes/tile_relay.php says
           why); the Leaflet map does not, its <img> may show what it cannot
           read, and the state's own address costs our server nothing. */
        this.relayBase = labelOptions.relayBase ?? null;
        this.opacity     = entry.opacity ?? 1;
        // The blend mode sits on the pane, not on the individual layers —
        // see overlayPane(). Here only its name is still needed. (It is not
        // called 'state': a method of that name stood here until
        // 01.10.2026, and a property of the same name shadowed it.)
        this.paneName      = pane || null;
        this.polygons      = null;      // borders, loaded on demand
        this.regions       = entry.regions;
        this.regionLayers     = new Map(); // area name -> tile or WMS layer
        this.stackSignature = null;      // how it is recognized that nothing changed
        this.nativeMaxCurrent = null;    // highest real level of the currently active services

        // An overlay lies above the base map and must always deliver
        // transparent; a base map may be opaque when it stands alone.
        this.isOverlay = entry.group === 'overlay';

        // Not every regional layer has a substitute for the rest of the
        // world (the official contour lines had none, until they went on
        // 01.10.2026) — without one the overlay stays empty outside its
        // areas, and that is as it should be.
        /* Place names, borders and roads — belong firmly to this layer, not
           into the picker. See labelLayers().

           BOTH KEYS ARE READ THE WAY THE REGISTRY WRITES THEM, and that had
           come apart: the registry said 'beschriftung', this line asked for
           'label'. undefined is a perfectly good argument — labelLayers()
           then built nothing, said nothing, and the aerial imagery lost its
           street and place names. The attribution beside it still said
           'beschriftungAttribution' and worked, which is what a half-done
           rename looks like from the inside. */
        this.label = labelLayers(
            entry.label, labelOptions.pane, labelOptions.mapMaxZoom || this.maxZoom,
            entry.labelAttribution
        );

        this.fallback = entry.fallback ? this.fallbackLayer(entry.fallback) : null;
        this.fallbackMaxZoom = entry.fallback?.maxZoom || 19;
        // Does the fallback layer always lie beneath, or only when nothing
        // else draws? See refresh().
        this.fallbackAlways = !!entry.fallbackAlways;

        // To the outside the whole behaves like one layer: a group into
        // which the currently fitting sources are hung.
        this.leafletLayer = L.layerGroup();
        this.leafletLayer.mapRegion = this;
        this.bordersUrl = baseUrl + (entry.bounds || '');
    }

    /**
     * The layer for everything outside the named areas.
     *
     * For the aerial imagery this is a worldwide tile grid (Esri), for the
     * hillshade by contrast a WMS: basemap.de covers Germany and delivers
     * nothing outside of its own accord. This way the sixteen federal
     * states need no sixteen identical entries — Germany is simply the
     * normal case here, and only the exceptions are enumerated.
     */
    fallbackLayer(r) {
        const shared = {
            attribution: r.attribution,
            maxZoom: this.maxZoom,
            opacity: this.opacity,
            ...(this.paneName ? { pane: this.paneName } : {}),
        };
        return r.layers
            ? L.tileLayer.wms(r.url, {
                ...shared,
                layers: r.layers,
                format: r.format || 'image/png',
                // JPEG knows no transparency, and for the hillshade none is
                // needed either: the service delivers pure white outside
                // its area, and white is without effect when multiplying.
                // See the registry.
                transparent: r.format !== 'image/jpeg',
                version: '1.3.0',
                tileSize: r.tileSize || 256,
            })
            : (r.alternateUrl
                ? new WithFallbackSource(r.url, {
                    ...shared,
                    maxNativeZoom: r.maxZoom || 19,
                    alternateUrl: r.alternateUrl,
                })
                : L.tileLayer(r.url, { ...shared, maxNativeZoom: r.maxZoom || 19 }));
    }

    /**
     * Which layer of a service at this zoom level? A service may grade
     * its layers by scale, each empty outside its range (layersByZoom) —
     * Saxony's and Vorarlberg's contour lines did, until the official
     * contour layer went on 01.10.2026; no entry uses it now. Every other
     * service has a single layer over the whole range.
     */
    layerFor(service, zoom) {
        if (!service.layersByZoom) return service.layers;

        let chosen = service.layersByZoom[0][1];
        for (const [fromLevel, layer] of service.layersByZoom) {
            if (zoom >= fromLevel) chosen = layer;
        }
        return chosen;
    }

    /**
     * Load the state borders on demand. Until they are there, the map
     * shows the fallback layer — distinctly better than waiting for the
     * data and displaying nothing at all that long.
     */
    async loadBorders(map) {
        try {
            const response = await fetch(this.bordersUrl);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            this.polygons = data.regions || [];
            this.stackSignature = null;   // force a rebuild
            this.refresh(map);
        } catch (e) {
            console.warn('State borders not loadable, official aerials stay on the fallback layer:', e);
        }
    }

    /**
     * Which services draw in this view at this level?
     *
     * Deliberately without the map: the same question arises when drawing
     * (then for the view) and when preloading for offline (then for an
     * outlined patch of forest that may lie somewhere entirely different
     * from where the map currently stands). The answer must not depend on
     * which of the two is asking.
     */
    stackFor(bounds, zoom) {
        const services = zoom >= this.minZoomOverall
            ? this.servicesInView(bounds, zoom)
            : [];

        // Fallback layer when no service is responsible — or when the view
        // juts out beyond the covered states, so no black stripe remains
        // there (Alsace, Vosges, Lake Constance).
        //
        // For the aerial imagery, by contrast, it always lies beneath
        // ('fallbackAlways'): there it is not substitute but foundation. A
        // state service can have gaps even in the middle of its own area —
        // without an underlay a black hole would remain there, with an
        // underlay the worldwide picture shines through. That costs tiles
        // that would otherwise be saved; the certainty is worth it.
        const withFallback = this.fallback !== null
            && (this.fallbackAlways
                || services.length === 0
                || this.reachesBeyond(bounds, services));

        // A single service without a fallback layer may deliver opaquely —
        // that is by far the most economical variant and the normal case
        // in a state's interior. An overlay never; it is supposed to let
        // through.
        const opaque = !this.isOverlay && services.length === 1 && !withFallback;

        return { services, withFallback, opaque };
    }

    /**
     * The tile layers that would draw in this view at this level — without
     * touching the map and without the layer having to be on it.
     *
     * There is only one reason for this, and that is preloading: whoever
     * takes a patch of forest offline wants to be able to switch between
     * aerial imagery and topographic map there later. So both must be
     * loadable, even if only one of the two currently lies on the map.
     *
     * The service layers are built ANEW here instead of taken from
     * this.regionLayers: the ones remembered there hang on the map, and
     * leafletLayerFor() would foist other layers on them for the requested
     * level (Saxony's graded contour lines). That would change the map
     * picture while someone only wanted to know a size. Fallback and
     * lettering are not affected by this — those are fixed per layer.
     */
    tileLayersFor(bounds, zoom) {
        const { services, withFallback, opaque } = this.stackFor(bounds, zoom);

        const out = [];
        if (withFallback) out.push(this.fallback);
        for (const service of services) out.push(this.buildLayer(service, opaque, zoom));
        out.push(...this.label);
        return out;
    }

    /** Redetermine the stack for the current view. */
    refresh(map) {
        if (!map.hasLayer(this.leafletLayer)) return;

        // Rounded to the whole level Leaflet fetches the tiles from —
        // otherwise a service would drop out at 8.7 although the tiles
        // come from level 9 and it would long have something to show.
        const zoom = Math.round(map.getZoom());
        const { services, withFallback, opaque } = this.stackFor(map.getBounds(), zoom);

        // The chosen layer belongs in the signature, otherwise Saxony's
        // grading would go unnoticed on zooming: same state, different
        // layer.
        const signature = (withFallback ? 'E|' : '')
            + services.map(d => `${d.region}:${d.tileUrl ? 'tiles' : this.layerFor(d, zoom)}`).join('|');

        // Up to which level do real tiles exist here? Moves with the area:
        // Switzerland delivers up to 20, TopPlusOpen only up to 18. The
        // zoom notice queries this so it does not claim the wrong thing.
        this.nativeMaxCurrent = services.length
            ? Math.max(...services.map(d => d.maxZoom || 19))
            : this.fallbackMaxZoom;

        if (signature === this.stackSignature) return;
        this.stackSignature = signature;

        this.leafletLayer.clearLayers();

        // Within the overlay pane, start above the simple overlays:
        // L.control.layers automatically assigns those single-digit
        // z-indices, and contour lines want to lie above the hillshade,
        // not beneath — otherwise the multiplication darkens the lines
        // along with everything else.
        let z = this.isOverlay ? 20 : 1;
        if (withFallback) {
            this.fallback.setZIndex(z++);
            this.leafletLayer.addLayer(this.fallback);
        }

        for (const service of services) {
            const layer = this.leafletLayerFor(service, opaque, zoom);
            layer.setZIndex(z++);
            this.leafletLayer.addLayer(layer);
        }

        // Last the lettering. It hangs on this layer but draws in the
        // overlay pane — the z-index above plays no role for it.
        for (const labelLayer of this.label) {
            this.leafletLayer.addLayer(labelLayer);
        }
    }

    /**
     * Get or create an area's layer.
     *
     * Two constructions: the German states deliver WMS, where format and
     * layers can be set per request. Austria and Switzerland deliver
     * ready-made tile grids (WMTS) — there is nothing to configure there;
     * in return they are faster and empty of their own accord outside
     * their area.
     */
    /* leafletLayerFor AND NOT layerFor: the class already carries a
       layerFor(service, zoom), which computes the WMS 'layers' PARAMETER
       for a zoom level. The German original told the two apart as
       schichtFuer/layerFor — the move to English folded both onto one
       name, the later definition won, and wmsValues() called ITSELF
       through it until the stack ran out. Two methods whose names differ
       only in language must not collapse into one word. */
    leafletLayerFor(service, opaque, zoom) {
        let layer = this.regionLayers.get(service.region);

        if (!layer) {
            layer = this.buildLayer(service, opaque, zoom);
            this.regionLayers.set(service.region, layer);
            return layer;
        }
        if (service.tileUrl) return layer;

        const { format, layers, transparent } = this.wmsValues(service, opaque, zoom);
        if (layer.wmsParams.format !== format || layer.wmsParams.layers !== layers) {
            layer.setParams({ format, layers, transparent });
        }
        return layer;
    }

    /**
     * Format, layers and transparency for a WMS service.
     *
     * Where black first has to be computed transparent, there is no way
     * around PNG: JPEG smears the black areas at the edges, and a gray
     * fringe would then remain. Such a service may never deliver opaquely
     * anyway, otherwise there would be nothing to cut out.
     */
    wmsValues(service, opaque, zoom) {
        const cutOut = !!service.blackTransparent;
        return {
            cutOut,
            format: cutOut ? 'image/png'
                  : (opaque ? this.format : (service.formatTransparent || 'image/png')),
            transparent: cutOut || !opaque,
            layers: this.layerFor(service, zoom),
        };
    }

    /** A fresh layer for a service — without a cache. */
    buildLayer(service, opaque, zoom) {
        // The German states share one attribution so Leaflet shows it once
        // instead of thirteen times. Foreign services stand under their
        // own licenses and need their own — Leaflet anyway shows only
        // those of the currently visible layers.
        const shared = {
            attribution: service.attribution || this.attribution,
            maxZoom: this.maxZoom,
            opacity: this.opacity,
            ...(this.paneName ? { pane: this.paneName } : {}),
        };

        if (service.tileUrl) {
            // Cut to the outline where the service asks for it, else the
            // white-fill cut, else the plain tile — see withRegionClip.
            const region = service.clipToRegion ? this.polygons?.find(l => l.name === service.region) : null;
            const Kind = region ? withRegionClip(region)
                : service.discardFillTiles ? WithoutFillTiles : L.TileLayer;
            return new Kind(service.tileUrl, {
                ...shared,
                maxNativeZoom: service.maxZoom || 19,
            });
        }

        const { cutOut, format, transparent, layers } = this.wmsValues(service, opaque, zoom);
        const Kind = cutOut ? BlackTransparent : L.TileLayer.WMS;
        const address = service.relay && this.relayBase
            ? `${this.relayBase}?relay=${encodeURIComponent(service.region)}`
            : service.url;
        return new Kind(address, {
            ...shared,
            layers,
            format,
            transparent,
            version: '1.3.0',
            tileSize: service.tileSize || this.wmsTileSize,
        });
    }

    /** All services whose state touches the view — in registry order. */
    servicesInView(bounds, zoom) {
        if (!this.polygons) return [];

        const hits = [];
        for (const service of this.regions) {
            if (zoom < (service.minZoom || 0)) continue;
            const region = this.polygons.find(l => l.name === service.region);
            if (region && countryTouches(region, bounds)) {
                hits.push(service);
            }
        }
        // Upper limit as a safeguard: at a tripoint three suffice, more
        // would be only load without gain.
        return hits.slice(0, 4);
    }

    /**
     * Does the view jut out beyond the covered states? The four corners
     * and the center are checked — exact enough to avoid the black stripe
     * at the national border without getting expensive.
     *
     * Every probe gets a leeway of around 300 meters. The borders are
     * rounded to about 110 meters, and neighboring states share no common
     * border course — in the middle of the Rhine there therefore lies a
     * narrow strip that arithmetically belongs to no state. Without the
     * leeway the fallback layer would constantly be loaded along there.
     */
    reachesBeyond(bounds, services) {
        if (!this.polygons) return true;

        const covered = new Set(services.map(d => d.region));
        const nw = bounds.getNorthWest(), so = bounds.getSouthEast();
        const probes = [
            [nw.lat, nw.lng], [nw.lat, so.lng], [so.lat, nw.lng], [so.lat, so.lng],
            [bounds.getCenter().lat, bounds.getCenter().lng],
        ];

        const leeway = 0.003; // degrees, a good 300 m in latitude

        const matches = (lat, lon) => this.regionsAt(lat, lon).some(n => covered.has(n));

        const isCovered = ([lat, lon]) => {
            if (matches(lat, lon)) return true;
            return [[leeway, 0], [-leeway, 0], [0, leeway], [0, -leeway]]
                .some(([dLat, dLon]) => matches(lat + dLat, lon + dLon));
        };

        return probes.some(probe => !isCovered(probe));
    }

    /**
     * In which areas does the point lie? Empty list if in none.
     *
     * Deliberately *all* hits, not only the first: since Tyrol and
     * Vorarlberg stand next to Austria, areas overlap. The aerial imagery
     * layer knows only "Österreich", the official contour lines knew only
     * "Tirol" — both had to strike a find in the Stubaital.
     */
    regionsAt(lat, lon) {
        if (!this.polygons) return [];

        const hits = [];
        for (const area of this.polygons) {
            const [south, west, north, east] = area.bbox;
            // Fast preliminary test — most areas drop out here.
            if (lat < south || lat > north || lon < west || lon > east) continue;

            for (const polygon of area.polygons) {
                // The first ring is the outer border, further ones are
                // holes: Bremen lies in Lower Saxony, Berlin in
                // Brandenburg.
                if (inRing(lon, lat, polygon[0]) &&
                    !polygon.slice(1).some(hole => inRing(lon, lat, hole))) {
                    hits.push(area.name);
                    break;
                }
            }
        }
        return hits;
    }
}

/**
 * Does the state touch the map view?
 *
 * First the cheap rectangle comparison, then two more precise probes: does
 * a corner of the view lie in the state, or a vertex of the state in the
 * view. That lets through at most cases where a state border cuts the view
 * without a vertex — then one layer more is loaded, which outside its area
 * costs around a hundred bytes per tile.
 */
function countryTouches(region, bounds) {
    const [south, west, north, east] = region.bbox;
    const s = bounds.getSouth(), w = bounds.getWest();
    const n = bounds.getNorth(), o = bounds.getEast();

    if (north < s || south > n || east < w || west > o) return false;

    const corners = [[s, w], [s, o], [n, w], [n, o], [(s + n) / 2, (w + o) / 2]];
    for (const polygon of region.polygons) {
        for (const [lat, lon] of corners) {
            if (inRing(lon, lat, polygon[0]) &&
                !polygon.slice(1).some(hole => inRing(lon, lat, hole))) {
                return true;
            }
        }
        for (const [lon, lat] of polygon[0]) {
            if (lat >= s && lat <= n && lon >= w && lon <= o) return true;
        }
    }
    return false;
}

/**
 * Point-in-polygon by ray casting: lay a ray to the right and count the
 * edge crossings. An odd number means inside.
 */
function inRing(x, y, ring) {
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const [xi, yi] = ring[i];
        const [xj, yj] = ring[j];
        if ((yi > y) !== (yj > y) && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) {
            inside = !inside;
        }
    }
    return inside;
}
