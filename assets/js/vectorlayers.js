/**
 * The layers of the vector map — the same registry (map_layers.php) that
 * layers.js reads for Leaflet, turned into MapLibre sources and layers.
 *
 * THE SAME SHAPE AS buildLayers() RETURNS (baseMaps, overlays, tracksLayer,
 * startLayer …), so map.js and Leaflet's layer picker take them unchanged:
 * each entry here is an L.Layer whose onAddVector/onRemoveVector put its
 * sources and style layers into the map (vectormap.js calls them). Built
 * for the vector map of 29.09.2026; what is not here yet is said at its
 * place below.
 *
 * WHAT CHANGES FOR WHICH LAYER:
 * - A NEW BASE MAP, „OpenStreetMap": OpenStreetMap's data as vector tiles,
 *   delivered by OpenFreeMap online and by the Europe file in pCloud for
 *   offline areas. Its lettering is drawn by MapLibre and stays upright
 *   when the map turns. Called „Vektorkarte" until 01.10.2026 — once the
 *   vector map was everybody's, the word named the engine, not a map, and
 *   the maintainer asked what the layer really is.
 * - THE AERIAL IMAGE keeps the states' own services, each a raster source
 *   BOUNDED TO ITS STATE'S BOX (areas.json) and asked for in the format that
 *   is transparent outside the state, so neighbours overlap cleanly; the
 *   worldwide picture lies beneath, as 'fallbackAlways' wants. Its
 *   lettering — the street layer the maintainer asked for — comes from the
 *   vector map: roads, bridges, borders and every name, upright.
 * - Raster layers with writing baked in stay what they are; turned, their
 *   writing turns along. (The topographic map and OpenTopoMap were such
 *   layers until 01.10.2026; the base map took their look, see TOPO.)
 * - Overlays without writing (rock type, tree species) are
 *   rasters that may turn freely.
 * - THE HILLSHADE IS COMPUTED, not a picture — see terrainShade below.
 * - SO ARE THE CONTOUR LINES, from the same terrain — see terrainContours.
 * - THE PROTECTED AREAS come from the same service and the same code as on
 *   the Leaflet map (outlineKindData in layers.js) and are drawn by
 *   MapLibre — see VectorOutlineLayer below.
 * - EVERY RASTER LAYER DRAWS LEAFLET'S OWN TILES — the same layer objects,
 *   the same addresses, the same filters, the classes of the tree species
 *   and the rock type hidden and repainted as there: see the stack
 *   protocol below.
 * - NOT YET: the offline tiles.
 */

import { addProtocol } from '../vendor/maplibre/maplibre-gl.mjs';
import { imageMeta, outlineKindData, areaName, shortAreaName, factSheet,
         RegionLayer, wmsLayer, tileLayer, clipToRegion } from './layers.js';
import { PMTiles, decompress, COMPRESSION } from './pmtiles.js';
import mlcontour from '../vendor/maplibre-contour/maplibre-contour.mjs';
import { tileEntry } from './tileurls.js';
import { overlaysFor, keepOverlaysPerBase } from './layermemory.js';

const OFM_STYLE = 'https://tiles.openfreemap.org/styles/liberty';
const OFM_ATTRIBUTION = '<a href="https://openfreemap.org">OpenFreeMap</a> · © OpenStreetMap-Mitwirkende';
/* The base map's name — the same as the Leaflet map's OpenStreetMap layer
   (map_layers.php 'osm'), which it replaces here: a device that had that one
   chosen finds this one, and back. A proper name, the same in every language. */
const BASE = 'OpenStreetMap';

/** The OpenFreeMap style, fetched once: its tiles, lettering and icons. */
export async function vectorStyle() {
    const response = await fetch(OFM_STYLE);
    if (!response.ok) throw new Error(`Vektorkarte: HTTP ${response.status}`);
    const style = await response.json();
    await throughOmtProtocol(style);
    return style;
}

/* THE BASE MAP'S TILES, THE DEVICE FIRST (30.09.2026). OpenFreeMap names
   its tiles through a tile list (TileJSON) whose address carries the week
   of the planet run — an area stored under those addresses would be lost
   with the next run. So the style's vector source goes through a protocol
   of our own: an address that names only z/x/y, the device asked first
   (areas store under OMT.key), OpenFreeMap's current address after. If the
   tile list cannot be had, the style stays as it came: online it works as
   before, offline it never did. */
const OMT = { online: null, keyBase: null };
const omtKey = (z, x, y) => `${OMT.keyBase}offline/omt/${z}/${x}/${y}.pbf`;

async function throughOmtProtocol(style) {
    const source = style.sources?.openmaptiles;
    if (!source?.url) return;
    try {
        const list = await (await fetch(source.url)).json();
        if (!list?.tiles?.[0]) return;
        OMT.online = list.tiles[0];
        style.sources.openmaptiles = {
            type: 'vector',
            tiles: ['yasai-omt://{z}/{x}/{y}'],
            minzoom: list.minzoom ?? 0,
            maxzoom: list.maxzoom ?? 14,
            ...(list.bounds ? { bounds: list.bounds } : {}),
            attribution: list.attribution ?? source.attribution ?? '',
        };
        omtProtocol();
    } catch (e) {
        console.warn('Vector map: tile list not loadable, tiles straight from OpenFreeMap', e);
    }
}

let omtProtocolAdded = false;
function omtProtocol() {
    if (omtProtocolAdded) return;
    omtProtocolAdded = true;
    addProtocol('yasai-omt', async (params, abort) => {
        const [z, x, y] = params.url.slice('yasai-omt://'.length).split('/').map(Number);
        if (OMT.keyBase) {
            const stored = await caches.match(omtKey(z, x, y)).catch(() => null);
            if (stored) {
                const bytes = await stored.arrayBuffer();
                // Stored as the Europe file holds them — gzipped, see omtFromEurope.
                return { data: stored.headers.get('X-Tile-Compression') === 'gzip'
                    ? await decompress(bytes, COMPRESSION.gzip) : bytes };
            }
        }
        if (OMT.online && navigator.onLine !== false) {
            const response = await fetch(OMT.online.replace('{z}', z).replace('{x}', x).replace('{y}', y),
                { signal: abort.signal, credentials: 'omit' });
            if (response.ok) return { data: await response.arrayBuffer() };
            // The sea and the empty land have no tile: nothing to draw.
            if (response.status === 204 || response.status === 404) return { data: new ArrayBuffer(0) };
            throw new Error(`Vektorkarte: HTTP ${response.status}`);
        }
        throw new Error(`Vektorkarte ${z}/${x}/${y}: weder auf dem Gerät noch im Netz`);
    });
}

/* THE EUROPE FILE, read through the server (api/v1/offline_map.php — why
   not straight from pCloud: includes/offline_map.php). */
let europe = null;
function europeFile(baseUrl) {
    europe ??= new PMTiles(async (offset, length, signal) => {
        const response = await fetch(`${baseUrl}api/v1/offline_map.php?file=europe&offset=${offset}&length=${length}`,
            { signal, credentials: 'same-origin' });
        if (!response.ok) throw new Error(`Europa-Datei: HTTP ${response.status}`);
        return response.arrayBuffer();
    });
    return europe;
}

/** One base-map tile out of the Europe file, in the answer form loadArea knows. */
async function omtFromEurope(baseUrl, z, x, y) {
    try {
        const file = europeFile(baseUrl);
        const bytes = await file.tile(z, x, y);
        if (!bytes) return { missing: true };
        return { answer: new Response(bytes, { headers: {
            'Content-Type': 'application/x-protobuf',
            'Content-Length': String(bytes.byteLength),
            ...(file.header.tileCompression === COMPRESSION.gzip ? { 'X-Tile-Compression': 'gzip' } : {}),
        } }) };
    } catch {
        return { unreachable: true };
    }
}

/**
 * A layer of the picker whose content is MapLibre sources and layers.
 * `build()` answers { sources, layers: [{ layer, group }] }.
 */
class StyleLayer extends L.Layer {
    constructor(name, build, attribution) {
        super();
        this.mapName = name;
        this._build = build;
        this._attribution = attribution ?? '';
        this._ids = [];
        this._sourceIds = [];
        this._wanted = false;
    }
    getAttribution() { return this._attribution; }
    onAddVector(vmap) {
        this._wanted = true;
        vmap.ready().then(() => {
            if (!this._wanted || this._ids.length) return;
            const { sources, layers } = this._build();
            for (const [id, source] of Object.entries(sources)) {
                if (!vmap.gl.getSource(id)) { vmap.gl.addSource(id, source); this._sourceIds.push(id); }
            }
            for (const { layer, group } of layers) {
                vmap.addStyleLayer(layer, group);
                this._ids.push(layer.id);
            }
        });
    }
    onRemoveVector(vmap) {
        this._wanted = false;
        for (const id of this._ids) if (vmap.gl.getLayer(id)) vmap.gl.removeLayer(id);
        for (const id of this._sourceIds) if (vmap.gl.getSource(id)) vmap.gl.removeSource(id);
        this._ids = [];
        this._sourceIds = [];
    }
}

/* THE RASTER LAYERS DRAW LEAFLET'S OWN TILES (30.09.2026, stage 4 of the
   vector map offline).

   Until now the vector map asked the states' services by addresses of its
   own making — a WMS request in another order and version than Leaflet's.
   The aerial image a phone had stored for an area lay under Leaflet's
   addresses, so the vector map found none of it, and an area loaded on the
   vector map would have been one the Leaflet map could not use.

   Now every raster layer is built from the very layer objects the Leaflet
   map uses (layers.js: RegionLayer for the states' stacks, wmsLayer and
   tileLayer for the rest), never put on a Leaflet map, and asked for their
   addresses through tileurls.js — the load window's arithmetic. One
   protocol draws a tile:

       yasai-stack://<layer>/<version>/<z>/<x>/<y>

   It asks the layer which services draw on THIS tile (the stack; for the
   states' layers RegionLayer.stackFor with the tile's own bounds — the
   same answer every time, whatever the view), fetches each service's
   picture — the device first, under any address the Leaflet map may have
   stored it under, then the network —, applies what Leaflet's createTile
   applies (the white fill cut of Austria and Switzerland, Veneto's black,
   the Swiss map's clip to its border, the classes of the tree species),
   enlarges a service's highest level where the map goes deeper (Leaflet's
   maxNativeZoom), and lays the pictures over each other, the worldwide one
   beneath. MapLibre takes the result as an ImageBitmap — measured: no
   encoding in between. The version changes when a stack must be asked
   anew (the state borders arrived, a class was switched off). */
const stacks = new Map();           // layer key → { parts(z, x, y), version, source }
let stackMap = null;                // the bridge the layers' arithmetic reckons with
let stackProtocolAdded = false;

function stackProtocol(vmap) {
    stackMap = vmap;
    if (stackProtocolAdded) return;
    stackProtocolAdded = true;
    addProtocol('yasai-stack', async (params, abort) => {
        const m = /^yasai-stack:\/\/([^/]+)\/\d+\/(\d+)\/(\d+)\/(\d+)$/.exec(params.url);
        const stack = m && stacks.get(m[1]);
        if (!stack) throw new Error(`Vektorkarte: unbekannte Kachel ${params.url}`);
        const [z, x, y] = [Number(m[2]), Number(m[3]), Number(m[4])];
        const pictures = await Promise.all(stack.parts(z, x, y)
            .map((part) => layerPicture(part, z, x, y, abort.signal)));
        const canvas = new OffscreenCanvas(256, 256);
        const ctx = canvas.getContext('2d');
        let drawn = 0;
        for (const picture of pictures) {
            if (picture?.width) { ctx.drawImage(picture, 0, 0, 256, 256); drawn++; }
        }
        /* NOTHING AND NO LINE is no empty tile: MapLibre would keep it and
           never ask again once reception is back. An empty answer only
           where the services said there is nothing (an overlay outside its
           states). */
        if (!drawn && pictures.some((p) => p?.unreachable)) {
            throw new Error(`Vektorkarte ${z}/${x}/${y}: weder auf dem Gerät noch im Netz`);
        }
        return { data: canvas.transferToImageBitmap() };
    });
}

/** Register a stack and answer its MapLibre source. */
function stackSource(key, parts, { attribution = '' } = {}) {
    const stack = { parts, version: 0 };
    stacks.set(key, stack);
    stack.source = () => ({
        type: 'raster',
        tiles: [`yasai-stack://${key}/${stack.version}/{z}/{x}/{y}`],
        tileSize: 256,
        maxzoom: 20,
        attribution,
    });
    return stack;
}

/** Ask a stack anew: MapLibre drops its tiles for the new address. */
function refreshStack(vmap, key, sourceId) {
    const stack = stacks.get(key);
    if (!stack) return;
    stack.version++;
    vmap.gl?.getSource(sourceId)?.setTiles(stack.source().tiles);
}

/** A layer's tile size in fields of the 256 grid: 1, or 2 for a 512 tile. */
const fieldsAcross = (layer) => (layer.getTileSize?.().x ?? 256) / 256;

/** One layer's picture for a tile, as Leaflet's createTile would draw it. */
async function layerPicture({ layer, alternates = [] }, z, x, y, signal) {
    const o = layer.options ?? {};
    const lowest = o.minNativeZoom ?? o.minZoom ?? 0;
    if (z < lowest) return null;
    // Above its highest level a layer's last level is enlarged, as Leaflet does.
    const level = Math.min(z, o.maxNativeZoom ?? o.maxZoom ?? z);
    const up = z - level;
    const k = { x: x >> up, y: y >> up, z: level };
    // Every address with the size of the picture it names: a 512 tile
    // holds four of this map's 256 tiles (wmsTileSize in map_layers.php).
    const named = (l) => [].concat(tileEntry(l, k, stackMap) ?? [])
        .filter((e) => typeof e === 'string').map((url) => ({ url, across: fieldsAcross(l) }));
    const chain = named(layer);
    const others = alternates.flatMap(named);
    const got = await tilePicture(chain, others, signal);
    if (!got.image) return got.unreachable ? { unreachable: true } : null;

    // The tile of its own grid the picture is — what the clip reckons in.
    const own = L.point(Math.floor(k.x / got.across), Math.floor(k.y / got.across));
    own.z = k.z;
    let picture = got.image;
    if (layer.imageFilter || layer.clipRegion) {
        const canvas = new OffscreenCanvas(picture.width, picture.height);
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(picture, 0, 0);
        if (layer.imageFilter) {
            const data = ctx.getImageData(0, 0, canvas.width, canvas.height);
            layer.imageFilter(data.data, canvas.width, canvas.height, layer);
            ctx.putImageData(data, 0, 0);
        }
        if (layer.clipRegion) {
            clipToRegion(ctx, stackMap, own, L.point(canvas.width, canvas.height), layer.clipRegion);
        }
        picture = canvas;
    }
    /* THE PART THIS TILE IS: a 512 picture covers two fields across, and
       each of them 2^up tiles of this level once the layer is enlarged. */
    const span = got.across * 2 ** up;
    if (span > 1) {
        const part = picture.width / span;
        const canvas = new OffscreenCanvas(256, 256);
        canvas.getContext('2d').drawImage(picture,
            (x - own.x * span) * part, (y - own.y * span) * part, part, part, 0, 0, 256, 256);
        picture = canvas;
    }
    return picture;
}

/* THE LAST PICTURES FETCHED, by address. A 512 picture serves four tiles
   of this map, and MapLibre asks for them side by side: without this, each
   of the four would fetch it again. Kept as the blob (tens of kilobytes),
   not the decoded bitmap (a megabyte), and the promise from the first ask
   on, so the other three wait for the same request. */
const PICTURES_KEPT = 48;
const pictureMemory = new Map();
function fetchPicture(url) {
    let blob = pictureMemory.get(url);
    if (blob) {
        pictureMemory.delete(url);          // to the young end
    } else {
        // 'same-origin': the relay answers only a visitor it knows (areas.js, fetchTile).
        blob = fetch(url, { mode: 'cors', credentials: 'same-origin' })
            // A refusal is not kept: the next ask may find the service back.
            .then((response) => (response.ok ? response.blob() : (pictureMemory.delete(url), null)));
        blob.catch(() => pictureMemory.delete(url));
        if (pictureMemory.size >= PICTURES_KEPT) pictureMemory.delete(pictureMemory.keys().next().value);
    }
    pictureMemory.set(url, blob);
    return blob;
}

/**
 * A tile's picture: from the device under any of its addresses, else from
 * the network along the chain (the worldwide image's second source).
 * An opaque stored answer (a service without CORS) cannot be drawn by
 * MapLibre at all — it is passed over.
 */
async function tilePicture(chain, others, signal) {
    for (const { url, across } of [...chain, ...others]) {
        const stored = await caches.match(url).catch(() => null);
        if (stored && stored.type !== 'opaque') {
            try { return { image: await createImageBitmap(await stored.blob()), across }; } catch { /* no picture */ }
        }
    }
    if (navigator.onLine === false) return { unreachable: true };
    let unreachable = false;
    for (const { url, across } of chain) {
        let blob;
        try {
            blob = await fetchPicture(url);
        } catch {
            unreachable = true;
            continue;
        }
        if (signal?.aborted) throw new DOMException('Aborted', 'AbortError');
        if (!blob) continue;
        try {
            return { image: await createImageBitmap(blob), across };
        } catch {
            // A service's error page instead of a picture — the next source.
        }
    }
    return { unreachable };
}

/** A tile's bounds, for asking a stack which services touch it. */
function tileBounds(z, x, y) {
    const crs = L.CRS.EPSG3857;
    return L.latLngBounds(crs.pointToLatLng(L.point(x * 256, y * 256), z),
                          crs.pointToLatLng(L.point((x + 1) * 256, (y + 1) * 256), z));
}

/**
 * The parts of a states' stack for a tile: the worldwide picture where
 * RegionLayer wants it, then each state's service. The layer objects are
 * built once per service, format and layer name — and each WMS service
 * also in its other format (opaque JPEG or transparent PNG, which
 * RegionLayer chooses by what else lies in the view), because that is the
 * address the Leaflet map may have stored the tile under.
 */
function regionParts(region) {
    const built = new Map();
    const build = (service, opaque, z) => {
        const values = service.tileUrl ? null : region.wmsValues(service, opaque, z);
        const key = `${service.region}|${values ? `${values.format}|${values.layers}|${service.tileSize || region.wmsTileSize}` : 'tiles'}`;
        let layer = built.get(key);
        if (!layer) {
            layer = region.buildLayer(service, opaque, z);
            built.set(key, layer);
        }
        return layer;
    };
    /* AND THE 256 TWINS, ON THE DEVICE ONLY. Until 01.10.2026 every WMS
       was asked in 256 tiles, and the areas loaded until then lie under
       those addresses. A service now asked in 512 (wmsTileSize) looks
       there too before it goes to the network — an area loaded last week
       still has its aerial image in the woods. tilePicture asks the
       alternates on the device and never fetches them. */
    const twin = (service) => ({ ...service, tileSize: 256 });
    const parts = (z, x, y) => {
        const { services, withFallback, opaque } = region.stackFor(tileBounds(z, x, y), z);
        const list = withFallback && region.fallback ? [{ layer: region.fallback }] : [];
        for (const service of services) {
            const large = !service.tileUrl && (service.tileSize || region.wmsTileSize) > 256;
            list.push({
                layer: build(service, opaque, z),
                alternates: service.tileUrl ? [] : [
                    build(service, !opaque, z),
                    ...(large ? [build(twin(service), opaque, z), build(twin(service), !opaque, z)] : []),
                ],
            });
        }
        return list;
    };
    // New borders, new clip outlines: the layers are built anew.
    parts.reset = () => built.clear();
    return parts;
}

/* THE HILLSHADE FROM ELEVATION DATA (30.09.2026, after a draft
   in the pane: „Ja, ersetze sie, Stärke 0,8 passt").

   The Leaflet map multiplies a finished picture (basemap.de, basemap.at,
   swisstopo), and a finished picture has its light burnt in from the
   north-west. Turned south with „Mitdrehen", the light falls from the
   bottom of the screen and the eye flips the relief: the ring walls on
   the Altkönig read as trenches. MapLibre computes the shading itself from
   terrain tiles, and with the illumination anchored to the VIEWPORT the
   light comes from the top left of the screen however the map stands.
   Shadows only, no highlights: like the multiplied picture it darkens the
   slopes and lays no pale veil over the map.

   THE DATA IS MAPTERHORN'S — open terrain tiles merging the states' own
   elevation models: every German state at 1 m (Baden-Württemberg at
   0.25 m), Austria and Slovenia likewise, Switzerland 0.5–2 m. Measured
   on 30.09.2026 at fourteen spots from the Harz to the Bernese Oberland:
   level 16 everywhere (0.7–0.8 m per pixel), 17 in places — fine enough
   for paths, ditches and charcoal pits the pictures smear. Terrarium
   encoding, 512-pixel tiles. Levels above 16 are drawn from 16.

   FOR THE DEVICE (30.09.2026) the tiles come out of Mapterhorn's archives
   on download.mapterhorn.com, read by range — the way they offer „Area
   Extracts", and the tile server stays for looking at maps. An area
   download stores each tile under its tile-server address, and the map
   asks the device first (the terrain protocol below). */
const TERRAIN = {
    tiles: ['yasai-terrain://{z}/{x}/{y}'],
    attribution: '<a href="https://mapterhorn.com/attribution">© Mapterhorn</a>',
};
const terrainUrl = (z, x, y) => `https://tiles.mapterhorn.com/${z}/${x}/${y}.webp`;

/* HOW FINE AN AREA'S TERRAIN IS LOADED: level 15, about 1.6 m a pixel —
   paths and ditches still show, and a Vogelsberg-sized square of 40 km
   weighs 275 MB instead of the 983 MB of level 16 (measured 30.09.2026).
   Zoomed in further without a line, level 15 is enlarged (coarserTerrain). */
const TERRAIN_OFFLINE_MAX = 15;

/* Mapterhorn's archives: the planet file holds levels 0–12, one archive per
   level-6 tile the levels from 13 on (download_urls.json). */
const archives = new Map();
function terrainArchive(z, x, y) {
    const name = z <= 12 ? 'planet' : `6-${x >> (z - 6)}-${y >> (z - 6)}`;
    let archive = archives.get(name);
    if (!archive) {
        archive = PMTiles.fromUrl(`https://download.mapterhorn.com/${name}.pmtiles`, { credentials: 'omit' });
        archives.set(name, archive);
    }
    return archive;
}

/** One terrain tile out of the archive, in the answer form loadArea knows. */
async function terrainFromArchive(z, x, y) {
    try {
        const bytes = await terrainArchive(z, x, y).tile(z, x, y);
        if (!bytes) return { missing: true };
        return { answer: new Response(bytes, { headers: {
            'Content-Type': 'image/webp', 'Content-Length': String(bytes.byteLength) } }) };
    } catch (e) {
        // A 404 of a whole archive is a spot Mapterhorn has no fine data
        // for; everything else is a line that dropped.
        return /HTTP 404/.test(String(e?.message)) ? { missing: true } : { unreachable: true };
    }
}

/**
 * THE TERRAIN PROTOCOL: the device first, then the network, then — without
 * a line — a coarser tile from the device, enlarged.
 *
 * THE DEVICE FIRST, online too: what lies there is the same data, arrives
 * at once and costs no traffic.
 *
 * ENLARGED WITHOUT SMOOTHING. A terrain tile is no picture but elevations
 * written into colours (terrarium: red × 256 + green + blue / 256), and
 * blending two neighbours' colours gives an elevation neither has — a
 * seam of false cliffs. Each pixel doubled stays true, only coarser.
 */
let terrainProtocolAdded = false;
function terrainProtocol() {
    if (terrainProtocolAdded) return;
    terrainProtocolAdded = true;
    addProtocol('yasai-terrain', async (params, abort) => ({ data: await terrainTile(params.url, abort.signal) }));
}

/** One terrain tile's bytes by its yasai-terrain address — the protocol's
    way, shared with the contour lines (terrainContours). */
async function terrainTile(address, signal) {
    const [z, x, y] = address.slice('yasai-terrain://'.length).split('/').map(Number);
    const stored = await caches.match(terrainUrl(z, x, y)).catch(() => null);
    if (stored) return stored.arrayBuffer();
    if (navigator.onLine !== false) {
        try {
            const response = await fetch(terrainUrl(z, x, y), { signal, credentials: 'omit' });
            if (response.ok) return response.arrayBuffer();
        } catch (e) {
            if (e.name === 'AbortError') throw e;
        }
    }
    const coarser = await coarserTerrain(z, x, y);
    if (coarser) return coarser;
    throw new Error(`Gelände ${z}/${x}/${y}: weder auf dem Gerät noch im Netz`);
}

async function coarserTerrain(z, x, y) {
    for (let up = 1; up <= 4 && z - up >= 0; up++) {
        const stored = await caches.match(terrainUrl(z - up, x >> up, y >> up)).catch(() => null);
        if (!stored) continue;
        const image = await createImageBitmap(await stored.blob());
        const part = image.width / 2 ** up;
        const canvas = document.createElement('canvas');
        canvas.width = image.width;
        canvas.height = image.height;
        const ctx = canvas.getContext('2d');
        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(image, (x - ((x >> up) << up)) * part, (y - ((y >> up) << up)) * part, part, part,
                      0, 0, canvas.width, canvas.height);
        // PNG, not webp: a lossy encoding would bend the elevations again.
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        return blob ? blob.arrayBuffer() : null;
    }
    return null;
}

/* HOW STRONG, BY WHAT LIES BENEATH (30.09.2026). 0.8 was his
   pick on the vector map; over the aerial image he tried 1 and found the
   depths read far better there — the photograph's own light and dark
   swallow a shading that is right on a pale map. So the shading follows
   the base map: the full strength over the aerial image, 0.8 elsewhere. */
const SHADE_STRENGTH = { aerial: 1, other: 0.8 };

function terrainShade(entry) {
    let strength = SHADE_STRENGTH.other;
    const layer = new StyleLayer(entry.name, () => ({
        sources: {
            terrain: { type: 'raster-dem', tiles: TERRAIN.tiles, tileSize: 512,
                       encoding: 'terrarium', maxzoom: 16, attribution: TERRAIN.attribution },
        },
        layers: [{ layer: { id: 'terrain-shade', type: 'hillshade', source: 'terrain', paint: {
            'hillshade-illumination-anchor': 'viewport',
            'hillshade-illumination-direction': 315,
            // 1 is MapLibre's strongest; see SHADE_STRENGTH.
            'hillshade-exaggeration': strength,
            'hillshade-shadow-color': '#000000',
            'hillshade-highlight-color': 'rgba(255, 255, 255, 0)',
            'hillshade-accent-color': 'rgba(0, 0, 0, 0)',
        } }, group: 'overlay' }],
    }), TERRAIN.attribution);
    layer.setStrength = (vmap, value) => {
        strength = value;
        if (vmap.gl.getLayer('terrain-shade')) vmap.gl.setPaintProperty('terrain-shade', 'hillshade-exaggeration', value);
    };
    terrainProtocol();
    return loadsTerrain(layer, 0, TERRAIN_OFFLINE_MAX);
}

/* FOR THE AREA DOWNLOAD, A TERRAIN LAYER ANSWERS AS A LEAFLET TILE LAYER
   WOULD — tile size, levels, addresses (tileUrls, layersAt, drawsAt in
   map.js), so the load window counts, weighs and loads it like every
   other layer. A 512 tile at MapLibre's level z is a 512 tile at Leaflet's
   z + 1, which is what the window counts in. offlineEntry adds where the
   bytes come from: the archive, not the tile server. `from` and `upTo`
   are the terrain levels the layer reads, in MapLibre's count. */
function loadsTerrain(layer, from, upTo) {
    L.setOptions(layer, { minNativeZoom: from + 1, maxNativeZoom: upTo + 1 });
    layer.getTileSize = () => L.point(512, 512);
    layer.getTileUrl = (c) => terrainUrl(c.z - 1, c.x, c.y);
    layer.offlineEntry = (c) => ({ url: terrainUrl(c.z - 1, c.x, c.y), get: () => terrainFromArchive(c.z - 1, c.x, c.y) });
    // What map.js may ask of a tile layer and that means nothing here.
    layer._pxBoundsToTileRange = () => null;
    layer.redraw = () => layer;
    return layer;
}

/* CONTOUR LINES, COMPUTED (01.10.2026: „Ja, bau die
   Höhenlinien"). The official ones — the WMS of eight states and regions,
   Hessen and the Vogelsberg not among them — went the same day („lösch
   mir komplett die Ebene der amtlichen Höhenlinien"), and these took
   their place and their plain name. They are worked out on the device
   from the same Mapterhorn terrain the shading draws, so they exist
   wherever the terrain does, their numbers are MapLibre's lettering and
   stay upright when the map turns, and offline they need nothing but the
   terrain an area download stores anyway. THE LEAFLET FALLBACK HAS NONE:
   the library draws into MapLibre only.

   THE LIBRARY IS maplibre-contour (BSD, onthegomap; vendored 0.1.1 in
   assets/vendor/maplibre-contour): marching squares over a terrain tile
   and its neighbours, written out as a vector tile MapLibre draws like
   any other. ITS OWN WAY TO THE TILES IS REPLACED. Left alone it fetches
   the tile-server address in a worker of its own — no device, no
   enlarged coarser tile, nothing offline. Its LocalDemManager takes a
   getTile of ours (terrainTile, the shading's way), and the protocol is
   ours too (yasai-contour), because the step depends on the land — see
   THE STEEPER THE LAND below; the library's own protocol knows only a
   step per zoom. The price is the main thread: decoding and tracing run
   beside the page, a few milliseconds a tile, where the worker would have
   taken them.

   HOW DENSE (MapLibre's levels; Leaflet's is one more): every 10 m from
   level 14 on, a thick line each 50 m — the German TK25's spacing —, and
   coarser as one zooms out, nothing below 10. THE TERRAIN IS READ ONE
   LEVEL COARSER THAN THE LINES (overzoom 1) and at most at level 14, about
   3 m a pixel: the 1-m models trace every rut and root plate, and a line
   that frays at each one stops being a contour. */
const CONTOURS = 'Höhenlinien';
/* What a device may still remember: the official layer, and this one's
   name of its first hours. Whoever had either on finds these lines on. */
const CONTOURS_BEFORE = ['Höhenlinien (amtlich)', 'Höhenlinien (berechnet)'];
const CONTOUR_FROM = 10;
const CONTOUR_TERRAIN_MAX = 14;
const CONTOUR_STEPS = { 10: [200, 1000], 11: [100, 500], 12: [50, 250], 13: [20, 100], 14: [10, 50] };

/* THE STEEPER THE LAND, THE WIDER THE STEP (01.10.2026, at the
   scale of 100 m on a mountain of the Alps: „Das sind halt auch einfach
   zu viel Linien. Wobei ich natürlich nicht weiß, wie das in einem
   Mittelgebirge aussehen würde"; of four maps side by side — Nordkette and
   Großer Feldberg, 10 and 20 m — and three ways he took the one where the
   map decides by the land). Printed maps do the same by country: the
   German TK25 every 10 m, the Swiss national map every 20.

   DECIDED PER BLOCK OF ABOUT 50 KM, the terrain tile of level 9: the mean
   slope over it, measured on the 100-m pixels of that tile. Measured that
   day: Nordkette 24.4°, Allgäu 24.5°, Berchtesgaden 14.2° (with its
   foreland in the block); the Black Forest at the Feldberg 10.0°, its
   north 5.5°, Harz 4.0, Eifel 5.7, Pfälzerwald 5.5, Vogelsberg 5.1, Taunus
   2.0, the Munich plain 1.2. The line at 12° leaves every Mittelgebirge on
   the fine step and gives the Alps the wide one. A block and not a tile:
   where two tiles of different steps meet, every other line ends at their
   edge, and blocks of 50 km meet rarely — along the edge of the Alps.
   The terrain of level 9 is what an area download stores anyway
   (loadsTerrain from CONTOUR_FROM - 1), so the choice holds offline. */
const STEEP_SLOPE = 12;
const RELIEF_LEVEL = 9;
const CONTOUR_STEPS_STEEP = { 10: [200, 1000], 11: [100, 500], 12: [100, 500], 13: [50, 250], 14: [20, 100] };

/** The step of a level, on gentle or steep land: [minor, major], or null below CONTOUR_FROM. */
function contourStep(level, steep) {
    const steps = steep ? CONTOUR_STEPS_STEEP : CONTOUR_STEPS;
    const key = Object.keys(steps).map(Number).filter((k) => k <= level).pop();
    return key === undefined ? null : steps[key];
}

const steepness = new Map();       // block → Promise<boolean>, while measuring and after
const steepKnown = new Map();      // block → boolean, once measured
function steepBlock(bx, by) {
    const key = `${bx}/${by}`;
    let measured = steepness.get(key);
    if (!measured) {
        measured = measureSlope(bx, by).then((slope) => {
            steepKnown.set(key, slope >= STEEP_SLOPE);
            return slope >= STEEP_SLOPE;
        });
        /* Not on the device and no line: the next tile asks again, and
           until then the block counts as gentle — the step its tiles were
           just drawn with, and the key must not wait on it in a loop. */
        measured.catch(() => { steepness.delete(key); if (!steepKnown.has(key)) steepKnown.set(key, false); });
        steepness.set(key, measured);
    }
    return measured;
}

/** The mean slope in degrees over a terrain tile of RELIEF_LEVEL. */
async function measureSlope(bx, by) {
    const bytes = await terrainTile(`yasai-terrain://${RELIEF_LEVEL}/${bx}/${by}`);
    const image = await createImageBitmap(new Blob([bytes]));
    const w = image.width;
    const ctx = new OffscreenCanvas(w, w).getContext('2d', { willReadFrequently: true });
    ctx.drawImage(image, 0, 0);
    const d = ctx.getImageData(0, 0, w, w).data;
    const height = (i, j) => { const o = (j * w + i) * 4; return d[o] * 256 + d[o + 1] + d[o + 2] / 256 - 32768; };
    const n = 2 ** RELIEF_LEVEL;
    const lat = Math.atan(Math.sinh(Math.PI * (1 - 2 * (by + 0.5) / n)));
    const pixel = 40075016.686 * Math.cos(lat) / (w * n);
    let sum = 0;
    let count = 0;
    // Every second pixel: the mean does not move, the work quarters.
    for (let j = 2; j < w - 2; j += 2) {
        for (let i = 2; i < w - 2; i += 2) {
            const dx = (height(i + 1, j) - height(i - 1, j)) / (2 * pixel);
            const dy = (height(i, j + 1) - height(i, j - 1)) / (2 * pixel);
            sum += Math.atan(Math.hypot(dx, dy));
            count++;
        }
    }
    return sum / count * 180 / Math.PI;
}

/* THE COLOUR, BY WHAT LIES BENEATH, as the shading's strength: the brown
   of printed maps on a pale ground, a pale gold on the aerial image, whose
   dark woods swallow the brown — Bavaria offers its own lines in yellow
   for that reason (map_layers.php). A depiction, not a surface. */
const CONTOUR_LOOK = {
    other: { line: '#9b6a3c', halo: 'rgba(255, 255, 255, 0.85)' },
    aerial: { line: '#f3dc9a', halo: 'rgba(0, 0, 0, 0.6)' },
};

let contourManager = null;
function contourSource() {
    if (!contourManager) {
        contourManager = new mlcontour.LocalDemManager({
            demUrlPattern: 'yasai-terrain://{z}/{x}/{y}', encoding: 'terrarium', maxzoom: CONTOUR_TERRAIN_MAX,
            cacheSize: 64, timeoutMs: 20000,
            getTile: async (url, abort) => ({ data: new Blob([await terrainTile(url, abort.signal)]) }),
        });
        addProtocol('yasai-contour', async (params, abort) => {
            const [z, x, y] = params.url.slice('yasai-contour://'.length).split('/').map(Number);
            if (z < CONTOUR_FROM) return { data: new ArrayBuffer(0) };
            const shift = z - RELIEF_LEVEL;
            // Unmeasurable (no terrain, no line): the fine step, as everywhere before.
            const steep = await steepBlock(x >> shift, y >> shift).catch(() => false);
            const tile = await contourManager.fetchContourTile(z, x, y, {
                levels: contourStep(z, steep), overzoom: 1,
                contourLayer: 'contours', elevationKey: 'ele', levelKey: 'level',
            }, abort);
            return { data: tile.arrayBuffer };
        });
    }
    return 'yasai-contour://{z}/{x}/{y}';
}

function terrainContours() {
    let look = CONTOUR_LOOK.other;
    const layer = new StyleLayer(CONTOURS, () => ({
        sources: {
            contours: { type: 'vector', tiles: [contourSource()], minzoom: CONTOUR_FROM, maxzoom: 15,
                        attribution: TERRAIN.attribution },
        },
        layers: [
            { layer: { id: 'contour-lines', type: 'line', source: 'contours', 'source-layer': 'contours',
                       minzoom: CONTOUR_FROM, layout: { 'line-join': 'round' }, paint: {
                'line-color': look.line,
                /* INTO THE BACKGROUND AS ONE ZOOMS OUT
                   (01.10.2026: „Wenn man allerdings in den Alpen weiter
                   wegzoomt, sieht man quasi überhaupt kein Satellitenbild
                   mehr, nur noch Linien"). A first ramp put them full from
                   Leaflet 16; on the phone he corrected it: „Du musst viel
                   später deutlich werden. Und viel früher undeutlich" —
                   he cannot see the level, only the map. So now full only
                   from Leaflet 17 (the scale shows 50 m), still under two
                   thirds at 16 (100 m), and from 15 (300 m) out a faint
                   texture of a quarter to a third. */
                'line-opacity': ['interpolate', ['linear'], ['zoom'], 10, 0.2, 13, 0.25, 14, 0.35, 15, 0.6, 16, 0.85],
                // level 1: the thick line of each step (CONTOUR_STEPS)
                'line-width': ['interpolate', ['linear'], ['zoom'],
                    10, ['match', ['get', 'level'], 1, 0.6, 0.3],
                    14, ['match', ['get', 'level'], 1, 0.8, 0.4],
                    15, ['match', ['get', 'level'], 1, 1, 0.5],
                    16, ['match', ['get', 'level'], 1, 1.2, 0.6]],
            } }, group: 'overlay' },
            { layer: { id: 'contour-numbers', type: 'symbol', source: 'contours', 'source-layer': 'contours',
                       minzoom: CONTOUR_FROM, filter: ['>', ['get', 'level'], 0], layout: {
                'symbol-placement': 'line',
                'symbol-spacing': 350,
                // Without separator, as printed maps write it: 1000, not 1.000.
                'text-field': ['to-string', ['get', 'ele']],
                'text-font': ['Noto Sans Regular'],
                'text-size': 11,
            }, paint: {
                'text-color': look.line,
                'text-halo-color': look.halo,
                'text-halo-width': 1.2,
                'text-opacity': ['interpolate', ['linear'], ['zoom'], 11, 0.45, 14, 0.6, 16, 1],
            } }, group: 'labels' },
        ],
    }), TERRAIN.attribution);
    /* WHAT ONE LINE MEANS AT THIS ZOOM, for the key above the scale
       (map.js, contourKey): the step a tile of this level was traced
       with. MapLibre takes a vector tile of the whole level below the
       zoom, and the library takes the steps of the nearest level at or
       below it — so the same rule twice. */
    layer.stepAt = (zoom, center) => {
        const level = Math.min(Math.floor(zoom), 15);
        if (level < CONTOUR_FROM) return null;
        // The block under the middle of the view says gentle or steep (THE STEEPER THE LAND).
        const n = 2 ** RELIEF_LEVEL;
        const bx = Math.floor((center.lng + 180) / 360 * n);
        const by = Math.floor((1 - Math.asinh(Math.tan(center.lat * Math.PI / 180)) / Math.PI) / 2 * n);
        const steep = steepKnown.get(`${bx}/${by}`);
        const [minor, major] = contourStep(level, steep === true);
        // Not measured yet: the key says the fine step and is asked again once it is.
        const pending = steep === undefined ? steepBlock(bx, by).catch(() => false) : null;
        return { minor, major, colour: look.line, pending };
    };
    layer.setLook = (vmap, onAerial) => {
        look = onAerial ? CONTOUR_LOOK.aerial : CONTOUR_LOOK.other;
        if (!vmap.gl.getLayer('contour-lines')) return;
        vmap.gl.setPaintProperty('contour-lines', 'line-color', look.line);
        vmap.gl.setPaintProperty('contour-numbers', 'text-color', look.line);
        vmap.gl.setPaintProperty('contour-numbers', 'text-halo-color', look.halo);
    };
    terrainProtocol();
    // The lines at level z read the terrain of z - 1 (overzoom 1).
    return loadsTerrain(layer, CONTOUR_FROM - 1, CONTOUR_TERRAIN_MAX);
}

/* THE LOOK OF THE TOPOGRAPHIC MAP (01.10.2026, the same
   evening as the three steps of lightness that stood here first: „Kannst
   du es mehr den Farben der Topokarte angleichen … die Topokarte gefällt
   mir echt gut", then „Ortschaften sind rot"; of five drafts beside the
   BKG's TopPlusOpen he took „G", with smaller symbols). The aim was that
   the two raster maps — the topographic one and OpenTopoMap — could go,
   and the same evening they went („Nimm die topografische Karte und
   OpenTopoMap raus").

   THE COLOURS ARE MEASURED, not guessed: at some 1,000 spots where the
   OpenStreetMap data says forest, meadow, scrub or field, TopPlusOpen's
   own pixel at level 15, the most frequent one per class — forest
   #dcecb2 (85 %), scrub #e9f3ca, meadow #f5f9e1 (87 %), field #fefdf5,
   the ground white; in the villages the area #fae1d6 and the houses
   #e7bbaf. Pale on purpose: that map tells forest from meadow by its
   SYMBOLS, not by lightness, and so does this one now — trees in the
   forest, dots on the meadows, rings in scrub and heath (landSymbols).

   AND WHAT THE STYLE LEFT OUT, the reason the raster maps showed more:
   - houses: OpenFreeMap draws them flat only up to level 14 and as 3-D
     blocks above, which this map drops (it never tilts) — from Leaflet
     level 15 on there were none at all. Now flat at every level;
   - villages: the residential area was tinted only up to level 12;
   - lanes and service roads: white with a pale grey edge on a cream
     ground, light on light — now a darker edge;
   - field and forest tracks: drawn like a driveway, below level 16 a
     one-pixel pale line; now their own brown dashes, from level 13;
   - paths: white dashes; now dark grey;
   - main roads yellow with a grey edge, as on the topographic map.
   A depiction, not a surface: these are the map's own colours, never the
   app's. If the style one day has no landcover_wood, the land stays as
   it comes. */
const TOPO = {
    ground: '#fefefd',
    land: { forest: '#dcecb2', scrub: '#e9f3ca', meadow: '#f5f9e1', field: '#fefdf5' },
    village: '#fae1d6',
    house: '#e7bbaf',
    houseEdge: '#cf9d8f',
    edge: '#b4b5b1',             // lanes and service roads
    track: '#8a6a45',
    path: '#6f6c66',
    mainRoad: '#fdf766',
    mainEdge: '#a9aaa7',
    tree: '#a9cf80',             // the symbols
    grass: '#9cc878',
    orchardTree: '#8cc06a',
    vine: '#93a96f',
    vineyard: '#f1f4dc',
    wetland: '#eef5f1',          // with blue marsh signs
    marsh: '#6f9fc6',
    cemetery: '#e3eed4',         // with small crosses
    cross: '#97a98a',
    peak: '#5a554f',
    cliff: '#857e77',
    houseNumber: '#9a8a82',
    quarry: '#f1f0ee',           // with grey bricks, as OpenTopoMap draws it
    quarryLine: '#aaa7a2',
    rock: '#e6e3de',             // bare rock and scree
};

/* THE SYMBOLS GET SMALLER AS ONE ZOOMS OUT („mach die Symbole
   kleiner. Wenn man rauszoomt, sonst werden sie einfach zu dominant. Dann
   können es ruhig viele kleine sein, die man nicht mehr deutlich sieht").
   A pattern keeps its pixel size at every zoom, so there are three of
   each, chosen by level — Leaflet 14–15 a fine grain, 16 small, 17 and
   on still a little smaller than the first draft. Below 14 none. Drawn at
   twice the size for sharp screens (pixelRatio 2). */
const SYMBOL_STEPS = [
    { level: 13, scale: 0.5, tile: 30 },     // MapLibre levels: Leaflet's minus one
    { level: 15, scale: 0.68, tile: 40 },
    { level: 16, scale: 0.85, tile: 50 },
];

function landSymbols() {
    const images = {};
    const draw = (id, tile, paint) => {
        const canvas = new OffscreenCanvas(tile * 2, tile * 2);
        const ctx = canvas.getContext('2d');
        ctx.scale(2, 2);
        paint(ctx);
        images[id] = { width: tile * 2, height: tile * 2, data: ctx.getImageData(0, 0, tile * 2, tile * 2).data };
    };
    const conifer = (ctx, x, y, s) => {
        ctx.beginPath();
        ctx.moveTo(x, y - 6 * s); ctx.lineTo(x - 3.2 * s, y + 2.5 * s); ctx.lineTo(x + 3.2 * s, y + 2.5 * s);
        ctx.closePath();
        ctx.moveTo(x, y + 2.5 * s); ctx.lineTo(x, y + 5 * s);
        ctx.stroke();
    };
    const broadleaf = (ctx, x, y, s) => {
        ctx.beginPath();
        ctx.arc(x, y - 1.5 * s, 3.6 * s, 0, Math.PI * 2);
        ctx.moveTo(x, y + 2.1 * s); ctx.lineTo(x, y + 5 * s);
        ctx.stroke();
    };
    SYMBOL_STEPS.forEach(({ scale: s, tile: t }, i) => {
        draw(`land-sym-forest-${i}`, t, (ctx) => {
            ctx.strokeStyle = TOPO.tree;
            ctx.lineWidth = Math.max(0.7, 1.1 * s);
            ctx.lineJoin = 'round';
            conifer(ctx, 0.2 * t, 0.23 * t, s); broadleaf(ctx, 0.7 * t, 0.22 * t, s);
            broadleaf(ctx, 0.45 * t, 0.73 * t, s); conifer(ctx, 0.87 * t, 0.75 * t, s);
        });
        draw(`land-sym-meadow-${i}`, Math.round(t * 0.8), (ctx) => {
            const u = t * 0.8;
            ctx.fillStyle = TOPO.grass;
            for (const [x, y] of [[0.17, 0.25], [0.67, 0.25], [0.42, 0.75], [0.88, 0.75]]) {
                for (const dx of [0, 3.5 * s]) {
                    ctx.beginPath(); ctx.arc(x * u + dx, y * u, Math.max(0.55, 0.9 * s), 0, Math.PI * 2); ctx.fill();
                }
            }
        });
        /* ORCHARDS AND VINEYARDS (01.10.2026: „Obstplantagen, die
           in Open Topo Map gezeichnet werden können"): the tiles carry them
           as farmland with subclass orchard and vineyard. Orchards as
           OpenTopoMap draws them — small rings in a regular grid —, vine
           rows as columns of dots. */
        draw(`land-sym-orchard-${i}`, Math.round(t * 0.34), (ctx) => {
            const u = Math.round(t * 0.34);
            ctx.strokeStyle = TOPO.orchardTree;
            ctx.lineWidth = Math.max(0.6, 0.9 * s);
            ctx.beginPath(); ctx.arc(u / 2, u / 2, 1.1 + 1.2 * s, 0, Math.PI * 2); ctx.stroke();
        });
        draw(`land-sym-vineyard-${i}`, Math.round(t * 0.24), (ctx) => {
            const u = Math.round(t * 0.24);
            ctx.fillStyle = TOPO.vine;
            for (const y of [0.25, 0.75]) {
                ctx.beginPath(); ctx.arc(u / 2, y * u, Math.max(0.5, 0.8 * s), 0, Math.PI * 2); ctx.fill();
            }
        });
        /* MARSH (01.10.2026: „Sümpfe oder Feuchtgebiete wären
           natürlich auch cool. Diese von normalen Wiesen zu unterscheiden").
           The topographic maps' sign: a short blue line with tufts above.
           The style drew wetlands with a small picture of its own sprite. */
        draw(`land-sym-wetland-${i}`, Math.round(t * 0.7), (ctx) => {
            const u = Math.round(t * 0.7);
            ctx.strokeStyle = TOPO.marsh;
            ctx.lineWidth = Math.max(0.6, 0.9 * s);
            ctx.lineCap = 'round';
            for (const [x, y] of [[0.27, 0.32], [0.77, 0.8]]) {
                const cx = x * u, cy = y * u, half = 3.4 * s + 0.6;
                ctx.beginPath();
                ctx.moveTo(cx - half, cy); ctx.lineTo(cx + half, cy);
                for (const dx of [-0.55, 0, 0.55]) { ctx.moveTo(cx + dx * half, cy - 0.6); ctx.lineTo(cx + dx * half, cy - 2.2 * s - 0.6); }
                ctx.stroke();
            }
        });
        draw(`land-sym-scrub-${i}`, Math.round(t * 0.6), (ctx) => {
            const u = t * 0.6;
            ctx.strokeStyle = TOPO.tree;
            ctx.lineWidth = Math.max(0.6, 1 * s);
            for (const [x, y] of [[0.25, 0.28], [0.75, 0.72]]) {
                ctx.beginPath(); ctx.arc(x * u, y * u, 2.4 * s + 0.4, 0, Math.PI * 2); ctx.stroke();
            }
        });
    });
    /* QUARRIES AS A BRICK WALL (01.10.2026: „In der Open Topo
       Map sieht das aus wie eine Ziegelmauer. Bei uns ist es einfach nur
       eine weiße Fläche"). OpenStreetMap marks them landuse=quarry, the
       tiles carry it as landuse class 'quarry' — the style drew nothing.
       Two sizes, the smaller below Leaflet 16, as with the trees. */
    [[12, 8], [16, 10]].forEach(([w, h], i) => {
        const canvas = new OffscreenCanvas(w * 2, h * 2);
        const ctx = canvas.getContext('2d');
        ctx.scale(2, 2);
        ctx.strokeStyle = TOPO.quarryLine;
        ctx.lineWidth = 0.8;
        ctx.beginPath();
        ctx.moveTo(0, 0.4); ctx.lineTo(w, 0.4);
        ctx.moveTo(0, h / 2 + 0.4); ctx.lineTo(w, h / 2 + 0.4);
        ctx.moveTo(0.4, 0); ctx.lineTo(0.4, h / 2);
        ctx.moveTo(w / 2 + 0.4, h / 2); ctx.lineTo(w / 2 + 0.4, h);
        ctx.stroke();
        images[`quarry-bricks-${i}`] = { width: w * 2, height: h * 2, data: ctx.getImageData(0, 0, w * 2, h * 2).data };
    });
    // Small crosses on cemeteries, as the topographic map has them.
    draw('cemetery-crosses', 14, (ctx) => {
        ctx.strokeStyle = TOPO.cross;
        ctx.lineWidth = 0.8;
        ctx.beginPath();
        for (const [x, y] of [[3.5, 4], [10.5, 11]]) {
            ctx.moveTo(x, y - 2.2); ctx.lineTo(x, y + 2.2);
            ctx.moveTo(x - 1.4, y - 0.8); ctx.lineTo(x + 1.4, y - 0.8);
        }
        ctx.stroke();
    });
    // The peak's sign: a small dark triangle.
    draw('peak-sign', 9, (ctx) => {
        ctx.fillStyle = TOPO.peak;
        ctx.beginPath(); ctx.moveTo(4.5, 1); ctx.lineTo(8.3, 8); ctx.lineTo(0.7, 8); ctx.closePath(); ctx.fill();
    });
    return images;
}

/** Put the symbols into the map once — before any layer asks for them. */
function addLandSymbols(gl) {
    if (gl.hasImage('land-sym-forest-0')) return;
    for (const [id, image] of Object.entries(landSymbols())) gl.addImage(id, image, { pixelRatio: 2 });
}

/** The base map's layers in the topographic look — new objects, the shared style stays as it came. */
function topoLayers(layers) {
    const at = layers.findIndex((l) => l.id === 'landcover_wood');
    if (at < 0) return layers;
    const fill = (id, filter, paint, extra = {}) => ({ id, type: 'fill', source: 'openmaptiles',
        'source-layer': 'landcover', filter, paint, ...extra });
    const shrubs = ['in', ['get', 'subclass'], ['literal', ['scrub', 'heath']]];
    const kinds = {
        wetland: ['==', ['get', 'class'], 'wetland'],
        field: ['==', ['get', 'class'], 'farmland'],
        orchard: ['==', ['get', 'subclass'], 'orchard'],
        vineyard: ['==', ['get', 'subclass'], 'vineyard'],
        meadow: ['all', ['==', ['get', 'class'], 'grass'], ['!', shrubs]],
        scrub: ['all', ['==', ['get', 'class'], 'grass'], shrubs],
        forest: ['==', ['get', 'class'], 'wood'],
    };
    const symbol = (kind) => ['step', ['zoom'], `land-sym-${kind}-0`,
        SYMBOL_STEPS[1].level, `land-sym-${kind}-1`, SYMBOL_STEPS[2].level, `land-sym-${kind}-2`];
    // Fields lowest, the forest on top, where polygons overlap; the symbols above all four.
    const ground = { ...TOPO.land, orchard: TOPO.land.meadow, vineyard: TOPO.vineyard, wetland: TOPO.wetland };
    const land = [
        // Orchards and vineyards are farmland too: drawn over the field, under the rest.
        // The wetland under the forest, its signs over it: a swamp forest shows both.
        ...['field', 'orchard', 'vineyard', 'meadow', 'scrub', 'wetland', 'forest'].map((kind) =>
            fill(`land-${kind}`, kinds[kind], { 'fill-color': ground[kind], 'fill-antialias': false })),
        ...['orchard', 'vineyard', 'meadow', 'scrub', 'forest', 'wetland'].map((kind) =>
            fill(`land-sym-${kind}`, kinds[kind], { 'fill-pattern': symbol(kind) }, { minzoom: SYMBOL_STEPS[0].level })),
        // Bare rock and scree: the tiles have them (landcover class 'rock'), the style drew nothing.
        fill('land-rock', ['==', ['get', 'class'], 'rock'], { 'fill-color': TOPO.rock, 'fill-antialias': false }),
        // Quarries above the land, which often runs on beneath them in the data.
        { id: 'landuse_quarry', type: 'fill', source: 'openmaptiles', 'source-layer': 'landuse',
          filter: ['==', ['get', 'class'], 'quarry'], paint: { 'fill-color': TOPO.quarry } },
        { id: 'landuse_quarry_bricks', type: 'fill', source: 'openmaptiles', 'source-layer': 'landuse', minzoom: 13,
          filter: ['==', ['get', 'class'], 'quarry'],
          paint: { 'fill-pattern': ['step', ['zoom'], 'quarry-bricks-0', SYMBOL_STEPS[1].level, 'quarry-bricks-1'] } },
        { id: 'landuse_quarry_edge', type: 'line', source: 'openmaptiles', 'source-layer': 'landuse', minzoom: 13,
          filter: ['==', ['get', 'class'], 'quarry'], paint: { 'line-color': TOPO.quarryLine, 'line-width': 0.6 } },
    ];

    const out = [];
    const edges = ['road_minor_casing', 'road_service_track_casing', 'tunnel_street_casing', 'bridge_street_casing',
                   'tunnel_service_track_casing', 'bridge_service_track_casing'];
    const mains = ['road_secondary_tertiary', 'road_trunk_primary', 'bridge_secondary_tertiary', 'bridge_trunk_primary',
                   'tunnel_secondary_tertiary', 'tunnel_trunk_primary'];
    const mainEdges = mains.map((id) => `${id}_casing`);
    const noTrack = ['!=', ['get', 'class'], 'track'];
    /* NOT DRAWN ANY MORE: the style's own wetland (replaced above) and its
       green veil over every protected area of the tiles — wildlife
       refuges, FFH areas, nature reserves — which tinted the white ground
       that means „nothing mapped". The protected areas have a layer of
       their own, from the BfN. */
    const dropped = ['landcover_grass', 'landcover_wetland', 'park', 'park_outline'];
    for (const original of layers) {
        const id = original.id;
        if (dropped.includes(id)) continue;
        if (id === 'landcover_wood') { out.push(...land); continue; }
        const l = { ...original, paint: { ...original.paint } };
        if (l.type === 'background') l.paint['background-color'] = TOPO.ground;
        if (id === 'building') {
            delete l.maxzoom;
            l.paint = { 'fill-color': TOPO.house, 'fill-outline-color': TOPO.houseEdge };
        }
        if (id === 'landuse_residential') {
            delete l.maxzoom;
            l.paint = { 'fill-color': TOPO.village };
        }
        if (id === 'landuse_cemetery') l.paint = { 'fill-color': TOPO.cemetery };
        if (edges.includes(id)) l.paint['line-color'] = TOPO.edge;
        if (mains.includes(id)) l.paint['line-color'] = TOPO.mainRoad;
        if (mainEdges.includes(id)) l.paint['line-color'] = TOPO.mainEdge;
        if (id === 'road_service_track' || id === 'road_service_track_casing') l.filter = ['all', l.filter, noTrack];
        if (id === 'road_path_pedestrian') {
            l.minzoom = 13;
            l.paint['line-color'] = TOPO.path;
            l.paint['line-dasharray'] = [2, 1.6];
            l.paint['line-width'] = ['interpolate', ['exponential', 1.2], ['zoom'], 13, 0.6, 15, 1.1, 20, 4];
        }
        out.push(l);
        if (id === 'landuse_residential') {
            // Trade, industry, shops, garages and railway land: the village's tone — the style left them white.
            out.push({ id: 'landuse_built', type: 'fill', source: 'openmaptiles', 'source-layer': 'landuse',
                filter: ['in', ['get', 'class'], ['literal', ['commercial', 'industrial', 'retail', 'garages', 'railway']]],
                paint: { 'fill-color': TOPO.village } });
        }
        if (id === 'landuse_cemetery') {
            out.push({ id: 'landuse_cemetery_crosses', type: 'fill', source: 'openmaptiles', 'source-layer': 'landuse',
                minzoom: 14, filter: ['==', ['get', 'class'], 'cemetery'], paint: { 'fill-pattern': 'cemetery-crosses' } });
        }
        if (id === 'road_service_track') {
            // Tracks leave the white service roads: their own brown dashes.
            out.push({
                id: 'road_track', type: 'line', source: 'openmaptiles', 'source-layer': 'transportation', minzoom: 12,
                filter: ['all', ['==', ['get', 'class'], 'track'], ['match', ['get', 'brunnel'], ['tunnel'], false, true]],
                layout: { 'line-join': 'round' },
                paint: { 'line-color': TOPO.track, 'line-dasharray': [3, 1.5],
                         'line-width': ['interpolate', ['exponential', 1.2], ['zoom'], 12, 0.8, 14, 1.4, 16, 2.4, 20, 6] },
            });
        }
    }
    /* CLIFFS as the topographic maps draw them: a grey line with teeth on
       the low side. OpenStreetMap draws a cliff with the low side to the
       RIGHT of its direction, and a positive line-offset is to the right
       of the direction too — so a short-dashed broad line, offset that
       way, puts the teeth where the ground falls away. The tiles carry the
       cliffs as lines of mountain_peak class 'cliff'. */
    const cliffs = ['==', ['get', 'class'], 'cliff'];
    out.push({
        id: 'cliff', type: 'line', source: 'openmaptiles', 'source-layer': 'mountain_peak', minzoom: 13,
        filter: cliffs, paint: { 'line-color': TOPO.cliff, 'line-width': 1.1 },
    }, {
        id: 'cliff_teeth', type: 'line', source: 'openmaptiles', 'source-layer': 'mountain_peak', minzoom: 13,
        filter: cliffs,
        paint: {
            'line-color': TOPO.cliff,
            'line-width': ['interpolate', ['linear'], ['zoom'], 13, 2, 17, 4],
            'line-offset': ['interpolate', ['linear'], ['zoom'], 13, 1.4, 17, 2.6],
            'line-dasharray': [0.25, 0.9],
        },
    });

    /* PEAKS with name and height, and HOUSE NUMBERS close in: the tiles
       carry both (mountain_peak, housenumber), the style drew neither. */
    out.push({
        id: 'peak', type: 'symbol', source: 'openmaptiles', 'source-layer': 'mountain_peak', minzoom: 11,
        filter: ['in', ['get', 'class'], ['literal', ['peak', 'volcano']]],
        layout: {
            'icon-image': 'peak-sign',
            'icon-allow-overlap': true,
            'text-field': ['case', ['has', 'ele'],
                ['concat', ['coalesce', ['get', 'name:latin'], ['get', 'name'], ''], '\n', ['to-string', ['get', 'ele']], ' m'],
                ['coalesce', ['get', 'name:latin'], ['get', 'name'], '']],
            'text-font': ['Noto Sans Regular'],
            'text-size': 11,
            'text-anchor': 'top',
            'text-offset': [0, 0.5],
            'text-max-width': 8,
            'text-optional': true,
        },
        paint: { 'text-color': TOPO.peak, 'text-halo-color': 'rgba(255, 255, 255, 0.85)', 'text-halo-width': 1.2 },
    }, {
        id: 'housenumber', type: 'symbol', source: 'openmaptiles', 'source-layer': 'housenumber', minzoom: 17,
        layout: { 'text-field': ['get', 'housenumber'], 'text-font': ['Noto Sans Regular'], 'text-size': 10 },
        paint: { 'text-color': TOPO.houseNumber, 'text-halo-color': 'rgba(255, 255, 255, 0.7)', 'text-halo-width': 1 },
    });
    return out;
}

/* THE STREET LAYER over the aerial image (29.09.2026): the
   vector map's roads, bridges and borders, and all of its names but the
   points of interest — what Esri's two lettering grids gave, drawn upright. */
function streetLayers(style, prefix) {
    const keep = (l) => (l.type === 'line' && /^(road|bridge)_/.test(l.id) && !/hatching/.test(l.id))
        || (l.type === 'line' && /^boundary/.test(l.id))
        || (l.type === 'symbol' && !/^poi/.test(l.id) && !/one_way/.test(l.id));
    return style.layers.filter(keep).map((l) => {
        const layer = { ...l, id: `${prefix}${l.id}`, source: `${prefix}${l.source}` };
        if (l.type === 'line') layer.paint = { ...l.paint, 'line-opacity': 0.75 };
        return { layer, group: l.type === 'symbol' ? 'labels' : 'labels' };
    });
}

/** Web mercator in MapLibre's world of 512 pixels at zoom 0. */
function worldPoint(lon, lat) {
    const y = Math.asinh(Math.tan(lat * Math.PI / 180));
    return [(lon + 180) / 360 * 512, (1 - y / Math.PI) / 2 * 512];
}

/**
 * One name point per area, for the lettering. Its own source rather than
 * the polygons': MapLibre cuts polygons into tiles and would letter every
 * piece. The point is the centre of the largest part; `from` is the zoom
 * at which the area is 80 × 26 pixels on screen — Leaflet's rule for when
 * a name fits (adjustLabels in layers.js), worked out once per area.
 */
function namePoints(kind, collection) {
    const features = [];
    for (const feature of collection?.features ?? []) {
        const g = feature.geometry;
        const parts = g?.type === 'Polygon' ? [g.coordinates] : g?.type === 'MultiPolygon' ? g.coordinates : [];
        let centre = null, largest = 0;
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        for (const part of parts) {
            const ring = part[0];
            if (!ring?.length) continue;
            let a = 0, cx = 0, cy = 0;
            for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
                const [x0, y0] = ring[j];
                const [x1, y1] = ring[i];
                const cross = x0 * y1 - x1 * y0;
                a += cross;
                cx += (x0 + x1) * cross;
                cy += (y0 + y1) * cross;
                const [wx, wy] = worldPoint(x1, y1);
                minX = Math.min(minX, wx); maxX = Math.max(maxX, wx);
                minY = Math.min(minY, wy); maxY = Math.max(maxY, wy);
            }
            if (a !== 0 && Math.abs(a) > largest) {
                largest = Math.abs(a);
                centre = [cx / (3 * a), cy / (3 * a)];
            }
        }
        if (!centre) continue;
        const from = Math.max(Math.log2(80 / Math.max(maxX - minX, 1e-9)),
                              Math.log2(26 / Math.max(maxY - minY, 1e-9)));
        features.push({
            type: 'Feature',
            geometry: { type: 'Point', coordinates: centre },
            properties: { name: shortAreaName(areaName(kind, feature.properties)), from },
        });
    }
    return { type: 'FeatureCollection', features };
}

const EMPTY = { type: 'FeatureCollection', features: [] };

/**
 * THE PROTECTED AREAS ON THE VECTOR MAP (30.09.2026). The same areas from
 * the same service as on the Leaflet map, with the same fallback to the
 * device when there is no line — outlineKindData in layers.js — drawn by
 * MapLibre: a strong outline and a faint fill per kind, the name in the
 * kind's colour inside, a tap shows the profile. Leaflet's tooltip per area
 * and its size rule become MapLibre lettering: a name only where it fits
 * and collides with nothing, and upright when the map turns.
 *
 * Only the view is loaded, with a margin, and only from the entry's zoom
 * on; a request still running is dropped when the map wanders on — the
 * same two precautions as OutlineLayer.
 */
class VectorOutlineLayer extends L.Layer {
    constructor(entry) {
        super();
        this.entry = entry;
        this.minZoom = entry.minZoom || 0;
        this.mapName = entry.name;
        this.mapHint = entry.hint;
        this.mapLegend = entry.legend;
        this.mapNoPrefetch = entry.noPrefetch;
        // Marks it as loaded on demand: it stays out of the offline picker.
        this.mapOutline = this;
        this.hidden = new Set();       // kinds switched off in the legend, by colour
        this.loaded = null;            // the box the drawn data is good for
        this.aborter = null;
        this.vmap = null;
        this._hovered = new Map();     // kind index → feature id under the pointer
        this._listeners = [];
        /* One stand-in per kind takes the taps, as a Leaflet path would —
           so the stylesheet decides when it may (not while tracing), and
           the tap goes on to the map as Leaflet's does. */
        this.targets = entry.kinds.map((kind) => {
            const stand = new L.Polygon([]);
            stand.on('click', (e) => this._profile(kind, stand._feature, e.latlng));
            return stand;
        });
        // What map.js asks of an overlay with a legend (drawLegend).
        this.setMapClasses = (colors) => { this.hidden = new Set(colors); this._showKinds(); };
        this.mapIsDrawing = (map) => map.getZoom() >= this.minZoom;
        this._onMove = () => this.refresh();
    }

    getAttribution() { return this.entry.attribution; }

    /** For the notice: is nothing on right now because zoomed out too far? */
    state(map) {
        if (!map.hasLayer(this)) return null;
        return map.getZoom() < this.minZoom ? { kind: 'zoom', fromZoom: this.minZoom } : { kind: 'ok' };
    }

    _ids(i) {
        return { areas: `po-${i}`, names: `po-${i}-names`, fill: `po-${i}-f`, line: `po-${i}-l`, name: `po-${i}-n` };
    }

    onAddVector(vmap) {
        this.vmap = vmap;
        this._wanted = true;
        vmap.on('moveend', this._onMove);
        vmap.ready().then(() => {
            if (!this._wanted || vmap.gl.getSource(this._ids(0).areas)) return;
            const gl = vmap.gl;
            this.entry.kinds.forEach((kind, i) => {
                const id = this._ids(i);
                gl.addSource(id.areas, { type: 'geojson', data: EMPTY, generateId: true });
                gl.addSource(id.names, { type: 'geojson', data: EMPTY });
                // Under the pointer the area stands out, as in Leaflet.
                const hover = (on, off) => ['case', ['boolean', ['feature-state', 'hover'], false], on, off];
                // A faint fill, never none: without it the area could not be tapped.
                vmap.addStyleLayer({ id: id.fill, type: 'fill', source: id.areas,
                    paint: { 'fill-color': kind.color, 'fill-opacity': hover(0.2, 0.08) } }, 'paths');
                vmap.addStyleLayer({ id: id.line, type: 'line', source: id.areas,
                    layout: { 'line-join': 'round' },
                    paint: { 'line-color': kind.color, 'line-width': hover(5, 3.5) } }, 'paths');
                /* The name in the colour of its kind, with a dark seam — on a
                   light topographic map white letters vanish, on the forest
                   dark ones do; the seam carries both (.area-name in map.css). */
                vmap.addStyleLayer({ id: id.name, type: 'symbol', source: id.names,
                    filter: ['>=', ['zoom'], ['get', 'from']],
                    layout: {
                        'text-field': ['get', 'name'],
                        'text-font': ['Noto Sans Bold'],
                        'text-size': 12,
                        'text-max-width': 12,
                        'text-line-height': 1.2,
                    },
                    paint: {
                        'text-color': kind.color,
                        'text-halo-color': 'rgba(0, 0, 0, .9)',
                        'text-halo-width': 1.6,
                        'text-halo-blur': 0.6,
                    } }, 'paths');
                vmap.addTapLayer(id.fill, {
                    vectorTarget: (feature) => { this.targets[i]._feature = feature; return this.targets[i]; },
                });
                const move = (e) => this._hover(i, e.features?.[0]?.id);
                const leave = () => this._hover(i, null);
                gl.on('mousemove', id.fill, move);
                gl.on('mouseleave', id.fill, leave);
                this._listeners.push([id.fill, move, leave]);
            });
            this._showKinds();
            this.loaded = null;
            this.refresh();
        });
    }

    onRemoveVector(vmap) {
        this._wanted = false;
        this.aborter?.abort();
        this.loaded = null;
        vmap.off('moveend', this._onMove);
        const gl = vmap.gl;
        for (const [id, move, leave] of this._listeners) {
            gl.off('mousemove', id, move);
            gl.off('mouseleave', id, leave);
        }
        this._listeners = [];
        this._hovered.clear();
        this.entry.kinds.forEach((kind, i) => {
            const id = this._ids(i);
            vmap.removeTapLayer(id.fill);
            for (const layer of [id.name, id.line, id.fill]) if (gl.getLayer(layer)) gl.removeLayer(layer);
            for (const source of [id.names, id.areas]) if (gl.getSource(source)) gl.removeSource(source);
        });
    }

    async refresh() {
        const vmap = this.vmap;
        if (!this._wanted || !vmap) return;

        if (vmap.getZoom() < this.minZoom) {
            this.aborter?.abort();
            this.loaded = null;
            this._setData(this.entry.kinds.map(() => null));
            return;
        }
        // Already loaded for this view — MapLibre letters by itself.
        if (this.loaded?.contains(vmap.getBounds())) return;

        // With a margin, so not every pan asks anew.
        const area = vmap.getBounds().pad(0.25);
        this.aborter?.abort();
        this.aborter = new AbortController();
        const signal = this.aborter.signal;
        try {
            const data = await Promise.all(this.entry.kinds.map(
                (kind) => outlineKindData(this.entry, kind, area, signal)));
            if (signal.aborted || !this._wanted) return;
            await vmap.ready();
            this._setData(data);
            this.loaded = area;
        } catch (e) {
            if (e.name !== 'AbortError') console.warn('Schutzgebiete nicht ladbar:', e);
        }
    }

    _setData(data) {
        const gl = this.vmap?.gl;
        if (!gl) return;
        this.entry.kinds.forEach((kind, i) => {
            const id = this._ids(i);
            const areas = gl.getSource(id.areas);
            if (!areas) return;
            // New data, new feature ids: a hover mark would land on another area.
            gl.removeFeatureState({ source: id.areas });
            this._hovered.delete(i);
            areas.setData(data[i] ?? EMPTY);
            gl.getSource(id.names)?.setData(namePoints(kind, data[i]));
        });
    }

    _showKinds() {
        const gl = this.vmap?.gl;
        if (!gl) return;
        this.entry.kinds.forEach((kind, i) => {
            const id = this._ids(i);
            const visibility = this.hidden.has(kind.color) ? 'none' : 'visible';
            for (const layer of [id.fill, id.line, id.name]) {
                if (gl.getLayer(layer)) gl.setLayoutProperty(layer, 'visibility', visibility);
            }
        });
    }

    _hover(i, featureId) {
        const gl = this.vmap?.gl;
        const source = this._ids(i).areas;
        const was = this._hovered.get(i);
        if (!gl || was === featureId) return;
        if (was !== undefined && was !== null) gl.setFeatureState({ source, id: was }, { hover: false });
        if (featureId !== undefined && featureId !== null) gl.setFeatureState({ source, id: featureId }, { hover: true });
        this._hovered.set(i, featureId);
    }

    /** The profile of the tapped area — the same window as on the Leaflet map. */
    _profile(kind, feature, latlng) {
        if (!feature || !this.vmap) return;
        const properties = feature.properties ?? {};
        L.popup({ maxWidth: 300 })
            .setLatLng(latlng)
            .setContent(factSheet(kind, areaName(kind, properties), properties))
            .openOn(this.vmap);
    }
}

/**
 * @param {object[]} registry  map_layers_for_js()
 * @param {VectorMap} vmap
 * @param {string} baseUrl
 * @param {object} style       the OpenFreeMap style (vectorStyle())
 * @param {object} settings    the settings bundle
 */
export async function buildVectorLayers(registry, vmap, baseUrl, style, settings, { offlineFiles = [] } = {}) {
    OMT.keyBase = new URL(baseUrl, location.href).href;
    const baseMaps = {};
    const overlays = {};
    let tracksLayer = null;

    /* THE COMMONEST LETTERS ON THE DEVICE, once. MapLibre fetches a font's
       characters in ranges of 256, only the ranges it meets; the service
       worker keeps what was fetched (VECTOR_ASSETS). A dead spot on a road
       never driven would then have no letters for its names. So the three
       fonts' Latin ranges — umlauts and ß, the Latin extensions of Polish
       and Czech names, the dashes and quotes — are asked for once. */
    if (navigator.onLine !== false && !settings.get('vectorFontsKept')) {
        const fonts = ['Noto Sans Regular', 'Noto Sans Bold', 'Noto Sans Italic'];
        const ranges = ['0-255', '256-511', '8192-8447'];
        Promise.all(fonts.flatMap((font) => ranges.map((range) =>
            fetch(style.glyphs.replace('{fontstack}', font).replace('{range}', range)).then((r) => r.ok))))
            .then((all) => { if (all.every(Boolean)) settings.set('vectorFontsKept', 1); })
            .catch(() => { /* the next start asks again */ });
    }
    let protectedLayer = null;
    let shade = null;
    let computed = null;
    stackProtocol(vmap);
    const ofmSources = (prefix) => Object.fromEntries(Object.entries(style.sources)
        .map(([id, source]) => [`${prefix}${id}`, source]));

    /* THE NEW BASE MAP FIRST. Its own layers, prefixed so no two sources or
       layers of different base maps meet by name; the 3-D buildings go,
       since the map never tilts. Writing in the labels group, above the
       overlays, as it lies over the hillshade in Leaflet too. */
    baseMaps[BASE] = new StyleLayer(BASE, () => (addLandSymbols(vmap.gl), {
        sources: ofmSources('v-'),
        layers: topoLayers(style.layers)
            .filter((l) => l.type !== 'fill-extrusion')
            .map((l) => ({
                layer: { ...l, id: `v-${l.id}`, ...(l.source ? { source: `v-${l.source}` } : {}) },
                group: l.type === 'symbol' ? 'labels' : 'base',
            })),
    }), OFM_ATTRIBUTION);

    /* FOR THE AREA DOWNLOAD — once the Europe file lies in pCloud. The base
       map answers the load window as the terrain does (see terrainShade):
       512 tiles, MapLibre's z is Leaflet's z + 1, up to level 14, the last
       the tiles have (MapLibre draws the finer levels from it). The street
       layer over the aerial image reads the same tiles, so it comes along. */
    if (offlineFiles.includes('europe')) {
        const vector = baseMaps[BASE];
        L.setOptions(vector, { minNativeZoom: 1, maxNativeZoom: 15 });
        vector.getTileSize = () => L.point(512, 512);
        vector.getTileUrl = (c) => omtKey(c.z - 1, c.x, c.y);
        vector.offlineEntry = (c) => ({ url: omtKey(c.z - 1, c.x, c.y), get: () => omtFromEurope(baseUrl, c.z - 1, c.x, c.y) });
        vector._pxBoundsToTileRange = () => null;
        vector.redraw = () => vector;
    }

    for (const entry of registry) {
        if (entry.type === 'tracks') {
            // The recorded tracks: drawn by map.js as Leaflet lines, which
            // the bridge turns into MapLibre lines.
            const group = L.layerGroup();
            group.mapHint = entry.hint;
            group.mapName = entry.name;
            overlays[entry.name] = group;
            tracksLayer = group;
            continue;
        }
        if (entry.type === 'wfs-outline') {
            const outline = new VectorOutlineLayer(entry);
            overlays[entry.name] = outline;
            if (entry.id === 'protected_areas') protectedLayer = outline;
            continue;
        }
        if (entry.type === 'image') {
            /* ONE PICTURE OVER A BOX (the rain, includes/rain.php): an image
               source with the box's four corners. The picture is drawn in
               Web Mercator, so MapLibre's straight stretch between the
               corners puts every pixel where it belongs. */
            const id = `r-${entry.id}`;
            const [w, s, e, n] = entry.box;
            const coordinates = [[w, n], [e, n], [e, s], [w, s]];
            let url = baseUrl + entry.url;
            const picture = new StyleLayer(entry.name, () => ({
                sources: { [id]: { type: 'image', url, coordinates } },
                layers: [{ layer: { id, type: 'raster', source: id,
                    paint: { 'raster-opacity': entry.opacity ?? 1, 'raster-fade-duration': 0 } }, group: 'overlay' }],
            }), entry.attribution);
            picture.mapHint = entry.hint;
            picture.mapLegendNote = '';
            picture.mapLegend = entry.legend;
            picture.mapNoPrefetch = entry.noPrefetch;
            overlays[entry.name] = picture;
            imageMeta(entry, baseUrl).then((fresh) => {
                url = fresh.url;
                picture.mapHint = fresh.hint;
                picture.mapLegendNote = fresh.note;
                vmap.fire('layerdescribed');
                vmap.gl?.getSource(id)?.updateImage({ url, coordinates });
            });
            continue;
        }
        if (entry.id === 'osm') continue;                    // BASE is OpenStreetMap
        if (entry.id === 'hillshade') {
            // Same name, so a device that had it on keeps it on.
            shade = terrainShade(entry);
            shade.mapHint = entry.hint;
            overlays[entry.name] = shade;
            // The contour lines come from the same terrain, so they stand beside it.
            computed = terrainContours();
            computed.mapHint = 'Aus den Geländedaten berechnet. Es gibt sie überall. Ohne Netz in geladenen Gebieten.';
            overlays[CONTOURS] = computed;
            continue;
        }

        const overlay = entry.group === 'overlay';
        // MapLibre knows no blend modes; a multiplied picture would lie on
        // as a veil, so it is laid on fainter (the hillshade, the one such
        // layer, is computed above instead).
        const opacity = entry.blendMode === 'multiply' ? Math.min(entry.opacity ?? 1, 0.4) : (entry.opacity ?? 1);
        const id = `r-${entry.id}`;

        const group = overlay ? 'overlay' : 'base';
        const paint = { 'raster-opacity': opacity, 'raster-fade-duration': 0 };

        if (entry.type === 'wms-by-area') {
            /* THE STATES' STACK, Leaflet's own (see the stack protocol). Its
               borders come from areas.json; until they are there it draws
               the worldwide picture alone, as the Leaflet map does, and is
               asked anew once they arrived. */
            const region = new RegionLayer(entry, baseUrl, null, {
                // The states without CORS come through our server (includes/tile_relay.php).
                relayBase: new URL(`${baseUrl}api/v1/tile_relay.php`, location.href).href,
            });
            const parts = regionParts(region);
            const stack = stackSource(id, parts, { attribution: entry.attribution ?? '' });
            region.loadBorders(vmap).then(() => { parts.reset(); refreshStack(vmap, id, id); });
            const layer = new StyleLayer(entry.name, () => {
                const sources = { [id]: stack.source() };
                const layers = [{ layer: { id, type: 'raster', source: id, paint }, group }];
                if (entry.id === 'aerial_official') {
                    Object.assign(sources, ofmSources('a-'));
                    layers.push(...streetLayers(style, 'a-'));
                }
                return { sources, layers };
            }, [entry.attribution, entry.fallback?.attribution,
                entry.id === 'aerial_official' ? OFM_ATTRIBUTION : null].filter(Boolean).join(' · '));
            /* THE LOAD WINDOW asks the stack the Leaflet map would ask
               (layersAt) — BUT NOT ESRI'S LETTERING. The Leaflet map lays
               two grids of names and roads over the aerial image; this map
               draws neither (its lettering is the street layer, see
               regionParts), and the download fetched both all the same:
               three requests a field where one draws — measured 01.10.2026
               1.4 × 1.3 km of the Vogelsberg at level 19, Hessen's picture and
               Esri's World_Transportation and World_Boundaries_and_Places,
               840 fields, 2,520 requests. What the street layer reads
               instead are the base map's tiles, so the aerial image takes
               them along once the Europe file is there: a few dozen 512
               tiles for an area, and offline the names are back. */
            const lettering = new Set(region.label);
            const streets = entry.id === 'aerial_official' && typeof baseMaps[BASE].offlineEntry === 'function';
            layer.mapRegion = {
                tileLayersFor: (bounds, z) => [
                    ...region.tileLayersFor(bounds, z).filter((l) => !lettering.has(l)),
                    ...(streets ? [baseMaps[BASE]] : []),
                ],
            };
            layer.mapHint = entry.hint;
            (overlay ? overlays : baseMaps)[entry.name] = layer;
            continue;
        }

        /* A SINGLE SERVICE — the tree species and the rock type with their
           classes, OpenTopoMap: Leaflet's layer object, drawn through the
           same protocol. */
        const leaf = entry.type === 'wms' ? wmsLayer(entry, null) : tileLayer(entry, 20);
        const stack = stackSource(id, () => [{ layer: leaf }], { attribution: entry.attribution ?? '' });
        const layer = new StyleLayer(entry.name, () => ({
            sources: { [id]: stack.source() },
            layers: [{ layer: { id, type: 'raster', source: id, paint }, group }],
        }), entry.attribution);
        // The load window finds the tile layer inside (tileLayers in map.js).
        layer.eachLayer = (fn) => { fn(leaf); return layer; };
        if (typeof leaf.setMapClasses === 'function') {
            // What map.js asks of a layer with a legend (drawLegend): the
            // classes are set on Leaflet's layer, its filter reads them.
            layer.mapLegend = entry.legend;
            layer.setMapClasses = (colors) => {
                leaf.setMapClasses(colors);
                refreshStack(vmap, id, id);
            };
        }
        layer.mapHint = entry.hint;
        layer.mapNoPrefetch = entry.noPrefetch;
        (overlay ? overlays : baseMaps)[entry.name] = layer;
    }

    // Starting layer and overlays: the same memory as the Leaflet map's.
    // A device that chose the base map under its old name keeps it.
    const remembered = settings.get('baseMap') === 'Vektorkarte' ? BASE : settings.get('baseMap');
    const start = (remembered && baseMaps[remembered]) || baseMaps[BASE];
    start.addTo(vmap);
    // The overlays last switched on WITH THIS BASE MAP (layermemory.js,
    // 06.10.2026) — the shading on the street map, none on the aerial one.
    const startName = Object.keys(baseMaps).find((name) => baseMaps[name] === start);
    const today = (name) => (CONTOURS_BEFORE.includes(name) ? CONTOURS : name);
    for (const name of overlaysFor(settings, startName)) {
        overlays[today(name)]?.addTo(vmap);
    }

    vmap.on('baselayerchange', (e) => settings.set('baseMap', e.name));

    // The shading's strength and the contours' colour follow the base map —
    // see SHADE_STRENGTH and CONTOUR_LOOK.
    const aerial = baseMaps[registry.find((e) => e.id === 'aerial_official')?.name];
    const shadeFor = (base) => {
        shade?.setStrength(vmap, base === aerial ? SHADE_STRENGTH.aerial : SHADE_STRENGTH.other);
        computed?.setLook(vmap, base === aerial);
    };
    shadeFor(start);
    vmap.on('baselayerchange', (e) => shadeFor(e.layer));
    keepOverlaysPerBase(settings, vmap, overlays, startName, today);

    return {
        baseMaps,
        overlays,
        tracksLayer,
        startLayer: start,
        updateAfterArea: () => {},
        currentServices: () => ({}),
        protectedAreasState: () => protectedLayer?.state(vmap) ?? null,
        // The contour lines' step here and now, or null when they are off or too far out.
        contourKey: () => (computed && vmap.hasLayer(computed)
            ? computed.stepAt(vmap.gl.getZoom(), vmap.gl.getCenter()) : null),
    };
}
