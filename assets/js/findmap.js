/**
 * The Fundbrowser's own map — the tab „Karte" of the opened find
 * (12.10.2026: „die Karte mit Direktteilen, Umrisszeichnen und Position
 * setzen"). One find on it: its point, which may be dragged, and its outline,
 * which may be drawn anew. Step two of three; the third takes the find's
 * sidebar off the desktop map, and from then on this is where a find is
 * placed and outlined at the desk.
 *
 * THE SAME MAP AS THE BIG ONE, NOT A SECOND KIND. It is built from the same
 * pieces map.js builds its map from — the bridge that lets MapLibre answer
 * in Leaflet's words (vectormap.js) and the same layer registry turned into
 * MapLibre's layers (vectorlayers.js) —, so the base maps, the aerial image,
 * the shading and the protected areas look here as they look there, and the
 * device's choice of base map is the same choice. A small Leaflet map with
 * OpenStreetMap's pictures would have been a hundred lines less and the
 * second map whose look drifts.
 *
 * LOADED WHEN THE TAB IS FIRST OPENED, NOT WITH THE PAGE. Both modules
 * stand on Leaflet's global L (its markers, events and controls are the
 * bridge's vocabulary), and most visits of the Fundbrowser never open the
 * tab: Leaflet, its stylesheet and MapLibre come when they are needed.
 *
 * WHAT THE MAP DOES NOT DECIDE: where the find is and whether it may move.
 * It reads both from its host each time it draws (read()), and every change
 * it makes goes back through the host (moved, outlined) into the point
 * window's fields — nothing is saved before „Speichern", exactly as on the
 * big map.
 */

import { t } from './i18n.js';
import { settings } from './settings.js';
import * as polygon from './polygon.js';

/**
 * @param {HTMLElement} box  the tab's map area (pages/finds.php, #finds-map),
 *                           data-leaflet-js / data-leaflet-css name Leaflet's files
 * @param {object} host
 *   start                   the page's start data — baseUrl, mapLayers
 *   read()                  the open find now: { lat, lon, outline, color, mayMove } or null
 *   moved(lat, lon)         the point was dragged or set by a click
 *   outlined(corners)       an outline was drawn and finished
 */
export function mountFindMap(box, host) {
    const canvas = box.querySelector('.finds-map-canvas');
    const bar = box.querySelector('.finds-map-bar');
    const drawBar = box.querySelector('.finds-map-draw');
    const message = box.querySelector('.finds-map-message');
    const placeButton = box.querySelector('[data-map-action="place"]');
    const fitButton = box.querySelector('[data-map-action="fit"]');
    const closeButton = box.querySelector('[data-map-action="close"]');

    let map = null;            // the bridge, once it stands
    let loading = null;        // the promise of it
    let marker = null;
    let area = null;
    let fittedFor = null;      // the find the view was last fitted to
    let placing = false;       // „Position setzen": the next click sets the point
    let sketch = null;         // { corners, done, shape, band, dots } while an outline is drawn

    /* ON THE PHONE THE MAP WAITS TO BE ASKED FOR (27.11.2026: „Wir können
       im Prinzip auch die Karte erstmal ausblenden, brauchen sie gar nicht
       laden und einen Button Position setzen machen … nur, wenn wir bewusst
       die Position setzen wollen"). There it stood over everything in the
       tab, and direction and outline — used more often — below it; and a
       map costs data in the woods. Closed it is the bar alone,
       „Position setzen", not loaded; that button or „Umriss ziehen" opens
       it, and another find closes it again. At the desk it stands beside
       the panel as before: there it costs no room.
       OPENED, IT TAKES THE WHOLE SCREEN (the same day: „diese Karten dann
       auch als Fullscreen … als Fullscreen-Overlay und nicht noch versuchen,
       sie in einen Rahmen innerhalb der Seite zu pressen"), and closes
       itself when its job is done — the point set, the outline finished or
       given up —, or by its cross. */
    const phone = matchMedia('(max-width: 860px)');
    let opened = false;
    const closed = () => phone.matches && !opened;
    function closedSync() {
        box.classList.toggle('is-closed', closed());
        box.classList.toggle('is-full', phone.matches && opened);
        if (closeButton) closeButton.hidden = !(phone.matches && opened);
        if (!closed()) {
            box.hidden = false;
            return;
        }
        placeButton.hidden = !host.read()?.mayMove;
        fitButton.hidden = true;
        // Nothing to set and nothing to show: the closed map is not there at all.
        box.hidden = placeButton.hidden;
    }
    /** Open the closed map and wait for it. */
    async function open() {
        opened = true;
        box.hidden = false;
        closedSync();
        await show();
        return map !== null;
    }
    /** The full-screen map's job is done, or it is dismissed: closed again (phone only). */
    function shut() {
        if (!phone.matches || !opened) return;
        placeMode(false);
        endSketch();
        opened = false;
        closedSync();
    }
    closeButton?.addEventListener('click', () => shut());

    function say(text) {
        message.textContent = text;
        message.hidden = text === '';
    }

    /** Leaflet's script and stylesheet, once — the bridge speaks its words. */
    function leaflet() {
        if (window.L) return Promise.resolve();
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = box.dataset.leafletCss;
        document.head.append(css);
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = box.dataset.leafletJs;
            script.onload = resolve;
            script.onerror = () => reject(new Error(t('Leaflet ließ sich nicht laden.')));
            document.head.append(script);
        });
    }

    async function build() {
        await leaflet();
        const [bridge, kit] = await Promise.all([import('./vectormap.js'), import('./vectorlayers.js')]);
        const style = await kit.vectorStyle();
        const here = host.read();
        const vmap = bridge.createVectorMap(canvas.id, {
            center: here ? [here.lat, here.lon] : [51, 10],
            zoom: here ? 16 : 6,
            maxZoom: 20,
            glyphs: style.glyphs,
            sprite: style.sprite,
        });
        L.control.attribution({ prefix: '<a href="https://maplibre.org">MapLibre</a>' }).addTo(vmap);
        L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(vmap);
        const { baseMaps, overlays } = await kit.buildVectorLayers(host.start.mapLayers ?? [], vmap,
            host.start.baseUrl, style, settings);
        /* THE WALKS ARE THE BIG MAP'S: map.js draws them into this group,
           and here nobody would — a switch that turns on nothing. */
        for (const [name, layer] of Object.entries(overlays)) {
            if (layer instanceof L.LayerGroup && !layer.onAddVector) delete overlays[name];
        }
        L.control.layers(baseMaps, overlays, { position: 'topright', collapsed: true }).addTo(vmap);
        vmap.on('click', onClick);
        vmap.on('mousemove', onMove);
        // Reachable from the console, as map.js makes its map (window.map).
        window.findMap = vmap;
        map = vmap;
        return vmap;
    }

    /**
     * The tab came into sight: the map is built the first time, sized to its
     * box every time (a map built or resized in a hidden tab measures 0 × 0).
     */
    async function show() {
        if (closed()) {
            closedSync();
            return;
        }
        if (!map) {
            if (!window.WebGLRenderingContext) {
                say(t('Die Karte lässt sich in diesem Browser nicht zeichnen.'));
                return;
            }
            say(t('Karte wird geladen …'));
            loading ??= build().catch((e) => {
                console.error('Find map:', e);
                loading = null;
                say(t('Die Karte ließ sich nicht laden: %s', e.message));
                return null;
            });
            if (!(await loading)) return;
            say('');
        }
        map.invalidateSize?.();
        map.gl?.resize();
        redraw();
    }

    /** The find as it stands in the window — point, outline, whether it may move. */
    function redraw() {
        if (!map) return;
        const here = host.read();
        if (marker) { map.removeLayer(marker); marker = null; }
        if (area) { map.removeLayer(area); area = null; }
        placeButton.hidden = !here?.mayMove;
        fitButton.hidden = !here;
        // Closed on the phone (a coordinate typed meanwhile): the bar alone says it.
        if (closed()) {
            closedSync();
            return;
        }
        if (!here) return;

        if (polygon.isUsable(here.outline)) {
            area = L.polygon(here.outline, {
                color: here.color, weight: 2, fillColor: here.color, fillOpacity: .15, interactive: false,
            }).addTo(map);
        }
        /* THE POINT IS A DOM MARKER, not one of the finds' symbols the bridge
           paints into its layer: only a DOM marker can be dragged
           (vectormap.js, _addMarker). Where the window lets the coordinate
           be changed — one's own find, or a share's right to move —, and
           ONLY AFTER „POSITION SETZEN" (13.10.2026): the window
           saves itself at the desk (cockpit.js), and a point that went along
           with a pan of the map would be saved a second later. A deliberate
           step first, then a click on the spot or a drag of the point. */
        marker = L.marker([here.lat, here.lon], {
            icon: L.divIcon({
                className: 'finds-map-point',
                html: `<span style="--point:${here.color}"></span>`,
                iconSize: [22, 22],
                iconAnchor: [11, 11],
            }),
            draggable: here.mayMove && placing && !sketch,
            keyboard: false,
            title: here.mayMove && placing ? t('Ziehen, um den Fund zu verschieben') : '',
        });
        marker.on('dragend', () => {
            const at = marker.getLatLng();
            placeMode(false);
            host.moved(at.lat, at.lng);
            shut();
        });
        marker.addTo(map);

        if (fittedFor !== here.id) {
            fittedFor = here.id;
            fit(here);
        }
    }

    /** The whole find into view: its outline, or its point at a close level. */
    function fit(here = host.read()) {
        if (!map || !here) return;
        if (polygon.isUsable(here.outline)) map.fitBounds(polygon.bounds(here.outline), { padding: [40, 40] });
        else map.setView([here.lat, here.lon], Math.max(map.getZoom(), 17));
    }

    // --- Setting the position by a click ------------------------------------

    function placeMode(on) {
        if (placing === on) return;
        placing = on;
        placeButton.classList.toggle('is-active', on);
        box.classList.toggle('is-aiming', on || sketch !== null);
        if (on) say(t('Klicke auf die Stelle, an der der Fund liegt, oder ziehe den Punkt dorthin.'));
        else if (!sketch) say('');
        // The point takes a drag only while this mode is on (redraw says why).
        redraw();
    }
    placeButton.addEventListener('click', async () => {
        if (closed()) {
            if (await open()) placeMode(true);
            return;
        }
        placeMode(!placing);
    });
    fitButton.addEventListener('click', () => fit());

    function onClick(e) {
        if (sketch) {
            sketch.corners.push([e.latlng.lat, e.latlng.lng]);
            drawSketch();
            return;
        }
        if (!placing) return;
        placeMode(false);
        host.moved(e.latlng.lat, e.latlng.lng);
        shut();
    }

    // --- Drawing an outline ----------------------------------------------------

    /**
     * Draw an outline corner by corner — the big map's tool (map.js,
     * beginOutline) in the words of this small one: a click sets a corner, a
     * line follows the pointer and closes back to the first, „Ecke zurück"
     * takes the last one away, „Fertig" hands the corners to done().
     */
    async function beginOutline(done) {
        // On the phone the map may still be closed: „Umriss ziehen" opens it.
        if (closed() && !(await open())) return;
        if (!map) return;
        placeMode(false);
        endSketch();
        sketch = { corners: [], done, shape: null, band: null, dots: [] };
        drawBar.hidden = false;
        bar.hidden = true;
        box.classList.add('is-aiming');
        if (marker?.dragging) marker.dragging.disable();
        say(t('Klicke Ecke für Ecke auf die Karte, dann „Fertig".'));
        drawSketch();
    }

    function drawSketch(pointer = null) {
        if (!sketch) return;
        if (sketch.shape) map.removeLayer(sketch.shape);
        if (sketch.band) map.removeLayer(sketch.band);
        for (const dot of sketch.dots) map.removeLayer(dot);
        sketch.shape = sketch.band = null;
        sketch.dots = [];
        const corners = sketch.corners;
        const look = { color: '#ffd54a', weight: 2, interactive: false };
        if (corners.length >= 3) sketch.shape = L.polygon(corners, { ...look, fillOpacity: .15 }).addTo(map);
        else if (corners.length === 2) sketch.shape = L.polyline(corners, look).addTo(map);
        sketch.dots = corners.map((c) => L.circleMarker(c, { ...look, radius: 4, fillColor: '#ffd54a', fillOpacity: 1 }).addTo(map));
        // The rubber band: from the last corner to the pointer, and on to the first.
        if (pointer && corners.length > 0) {
            const run = corners.length > 1 ? [corners[corners.length - 1], pointer, corners[0]] : [corners[0], pointer];
            sketch.band = L.polyline(run, { ...look, dashArray: '4 6' }).addTo(map);
        }
        drawBar.querySelector('[data-draw="back"]').disabled = corners.length === 0;
        drawBar.querySelector('[data-draw="done"]').disabled = corners.length < 3;
    }

    function onMove(e) {
        if (sketch) drawSketch([e.latlng.lat, e.latlng.lng]);
    }

    function endSketch() {
        if (!sketch) return;
        if (sketch.shape) map.removeLayer(sketch.shape);
        if (sketch.band) map.removeLayer(sketch.band);
        for (const dot of sketch.dots) map.removeLayer(dot);
        sketch = null;
        drawBar.hidden = true;
        bar.hidden = false;
        box.classList.remove('is-aiming');
        say('');
    }

    drawBar.addEventListener('click', (e) => {
        const action = e.target.closest('[data-draw]')?.dataset.draw;
        if (!action || !sketch) return;
        if (action === 'back') {
            sketch.corners.pop();
            drawSketch();
        } else if (action === 'cancel') {
            endSketch();
            redraw();
            shut();
        } else if (action === 'done' && sketch.corners.length >= 3) {
            const { corners, done } = sketch;
            endSketch();
            done(corners);
            shut();
        }
    });

    /* ESCAPE TAKES BACK THE LAST CORNER, and ends the drawing when there is
       none — the big map's rule (photowindow.js has the same for its tools). */
    window.addEventListener('keydown', (e) => {
        const full = phone.matches && opened;
        if (e.key !== 'Escape' || (!sketch && !placing && !full) || !box.checkVisibility?.()) return;
        e.preventDefault();
        e.stopPropagation();
        if (placing) placeMode(false);
        else if (sketch && sketch.corners.length > 0) { sketch.corners.pop(); drawSketch(); }
        else if (sketch) { endSketch(); redraw(); shut(); }
        else shut();
    }, true);

    /** The find goes (another one opens, the page goes back): no tool stays armed. */
    function reset() {
        placeMode(false);
        endSketch();
        fittedFor = null;
        // The next find starts with the map closed again (phone, see `opened`).
        opened = false;
        closedSync();
    }

    return { show, redraw, beginOutline, reset };
}
