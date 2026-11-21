/**
 * The tile addresses of a Leaflet tile layer — for the load window AND for
 * the vector map (30.09.2026: stage 4 of the vector map offline).
 *
 * Moved here out of map.js, where the load window asked it. The vector map
 * asks the same question for every raster tile it draws: its aerial image,
 * topographic map and overlays are built from the very Leaflet layer
 * objects the Leaflet map uses (layers.js), and ask them for their
 * addresses through this function. So a tile the Leaflet map stored for an
 * area is found by the vector map under the same address, and an area
 * loaded on the vector map is one the Leaflet map can use — one arithmetic,
 * not two that drift apart.
 *
 * `map` is the map the layer's arithmetic reckons with: Leaflet's own, or
 * the vector map's bridge (vectormap.js), which answers the same questions
 * (options.crs, unproject, getPixelWorldBounds).
 */

/**
 * All addresses of a tile across layers — see tileEntry for one.
 *
 * I DO NOT REBUILD THE URLS. The aerial image is a stack of several
 * official WMS services; every tile URL is a bounding-box request with a
 * conversion. Recreating them here would mean doubling Leaflet's
 * arithmetic — and with the next Leaflet it would drift apart without
 * anyone noticing: the tiles would lie in the cache, just under URLs that
 * are never requested.
 *
 * So Leaflet itself is asked. getTileUrl() delivers exactly what the map
 * will later request — by definition, not by imitation.
 */
export function tileUrls(k, layers, map) {
    const urls = [];
    for (const layer of layers) {
        if (!drawsAt(layer, k.z)) continue;
        const entry = tileEntry(layer, k, map);
        if (entry) urls.push(entry);
    }
    return urls;
}

/**
 * One layer's address for a tile of the 256 grid (x, y, z) — a string, a
 * chain [first, second source], an entry with its own way to the bytes
 * ({ url, get }), or null.
 */
export function tileEntry(layer, k, map) {
    /* A VECTOR-MAP LAYER NAMES ITS TILE ITSELF, with the way to its
       bytes (the terrain out of Mapterhorn's archive, vectorlayers.js)
       — none of the Leaflet arithmetic below applies to it. */
    if (typeof layer.offlineEntry === 'function') {
        const gr = layer.getTileSize().x / 256;
        const coord = L.point(Math.floor(k.x / gr), Math.floor(k.y / gr));
        coord.z = k.z;
        return layer.offlineEntry(coord) ?? null;
    }

    // Leaflet reckons with L.Point, not with a plain object: the WMS
    // version of getTileUrl calls coords.scaleBy() to turn the tile
    // into a bounding box. A {x,y,z} blows it up with a TypeError —
    // which was swallowed here and thereby silently skipped EVERY WMS
    // layer. What remained were the plain tile grids; the hillshade
    // therefore reported "hier keine Daten", and of the aerial image
    // only the worldwide picture came instead of the state services.
    //
    // 512 tiles are moreover a grid of their own: one of them covers
    // four fields of the 256 arithmetic.
    const gr = layer.getTileSize().x / 256;
    const coord = L.point(Math.floor(k.x / gr), Math.floor(k.y / gr));
    coord.z = k.z;

    // Leaflet's getTileUrl takes the zoom level NOT from the tile
    // passed in but from the layer's _tileZoom — that is the level the
    // map currently shows. Without this move every generated URL
    // carries the same level, and a layer whose _tileZoom is not even
    // set yet delivers 'z=undefined' and with it an error.
    const oldZoom = layer._tileZoom;
    layer._tileZoom = k.z;

    // Map and reference system are only set by Leaflet on adding. A
    // layer that does not currently lie on the map could thus not
    // compute its WMS boxes at all — and exactly that one is meant to
    // be loaded along here.
    const withoutMap = !layer._map;
    if (withoutMap) layer._map = map;
    if (!layer._crs) {
        layer._crs = layer.options.crs || map.options.crs;

        // And the reference system belongs in the request itself too.
        // Without 'crs=EPSG:3857' a WMS does not know what the numbers
        // in the box refer to: Bavaria's contour-line service then
        // answers with 400 and "Leider ist ein Fehler aufgetreten"
        // instead of an image.
        if (layer.wmsParams) {
            layer._wmsVersion = parseFloat(layer.wmsParams.version);
            layer.wmsParams[layer._wmsVersion >= 1.3 ? 'crs' : 'srs'] = layer._crs.code;
        }
    }

    // And with the map comes the world's tile grid: getTileUrl computes
    // {-y} from it as soon as a map is there. Without adding it has
    // never been computed — then undefined stands there, and the layer
    // drops out, ALTHOUGH only this move brought it in. It hangs on
    // the zoom level, so it belongs redetermined here.
    const oldRange = layer._globalTileRange;
    const world = map.getPixelWorldBounds(k.z);
    if (world) layer._globalTileRange = layer._pxBoundsToTileRange(world);

    try {
        const url = layer.getTileUrl(coord);
        if (url && !/undefined|NaN/.test(url)) {
            // A CHAIN WHEN THE LAYER HAS A SECOND SOURCE.
            //
            // Not two entries: then prefetching loads both and stores
            // both — duplicate tiles for an image that is only seen
            // once. As a chain, the second is only asked when the
            // first has nothing. See WithFallbackSource in layers.js.
            const fallback = layer.options?.alternateUrl;
            return fallback
                ? [url, L.Util.template(fallback, { ...coord, s: '', r: '' })]
                : url;
        }
    } catch (error) {
        console.warn('Keine Kacheladresse fuer', layer.options?.layers || layer._url, error);
    } finally {
        layer._tileZoom = oldZoom;
        layer._globalTileRange = oldRange;
        if (withoutMap) layer._map = null;
    }
    return null;
}

/**
 * Does this layer request tiles of its own at this level at all?
 *
 * Above maxNativeZoom Leaflet keeps drawing the tiles of the highest real
 * level enlarged (_clampZoom) and requests no new ones. Whoever skips that
 * loads, for every level above, that many URLs again that do not exist —
 * and waits for nothing but 404s.
 */
export function drawsAt(layer, z) {
    const o = layer.options || {};
    if (o.maxNativeZoom !== undefined && z > o.maxNativeZoom) return false;
    if (o.minNativeZoom !== undefined && z < o.minNativeZoom) return false;
    if (o.maxZoom !== undefined && z > o.maxZoom) return false;
    if (o.minZoom !== undefined && z < o.minZoom) return false;
    return true;
}

