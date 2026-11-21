/**
 * A MapLibre map that map.js can speak to as if it were Leaflet's.
 *
 * THE CALL OF 29.09.2026, after the vector trial page: the map moves
 * from Leaflet to MapLibre — it turns with the view while its names stay
 * upright, and the OpenStreetMap vector data can later be offered offline
 * from his own server. Built BESIDE the Leaflet map, switchable per device
 * and for the administrator first, until it can do everything; then
 * Leaflet goes.
 *
 * WHY A BRIDGE AND NOT A REWRITE. map.js is 21,000 lines, and only about a
 * hundred of them speak to the map object — the sidebar, the point window,
 * the lists and the queue know nothing of Leaflet. So instead of rewriting
 * those hundred places in one go, this object answers them in Leaflet's
 * words: its events (moveend, zoomend, click with e.latlng …), its camera
 * (getZoom, setView, fitBounds, getBounds …), its projections, its control
 * corners, and addLayer/removeLayer for the layer kinds map.js creates —
 * markers, lines, areas, circles, groups. Leaflet stays loaded, but only as
 * the library of those objects; none of them is ever drawn by it here. Each
 * place moves to MapLibre's own words once it is touched for its own sake.
 *
 * TWO NUMBERS DIFFER BY ONE: MapLibre counts its zoom on 512-pixel tiles,
 * Leaflet and every raster service on 256. Level 16 in map.js is 15 here —
 * the bridge converts in both directions, and nothing outside sees it.
 *
 * THE FINDS ARE NOT TWO THOUSAND DOM ELEMENTS. MapLibre moves a DOM marker
 * one by one on every frame of a pan or a turn; two thousand of them stutter.
 * A marker whose icon is a find's symbol ('point-symbol') therefore becomes
 * a feature of ONE symbol layer, its icon drawn once per look into an image
 * (symbolFor keeps one icon per look since 243e089), and a tap on it fires
 * the marker's own 'click' — the handlers in map.js never know. Every other
 * marker (the location, the direction ring, the corner handles) is a real
 * DOM marker, and so is the chosen find while it may be dragged.
 */

import { Map as MapLibreMap, Marker as GlMarker, Popup as GlPopup } from '../vendor/maplibre/maplibre-gl.mjs';

const OFFSET = 1;                   // Leaflet zoom = MapLibre zoom + 1

/* MAPLIBRE'S STYLESHEET, ONLY WHERE ITS MAP RUNS. index.php cannot know
   which engine a device chose, and the Leaflet map never needs it. Without
   it a DOM marker stands in the page's flow BELOW the canvas — the dragged
   find was simply gone — and a popup has neither frame nor tip. The map
   waits for it (ready()), and goes on without it if it cannot be had. */
const sheetLoaded = new Promise((resolve) => {
    const sheet = document.createElement('link');
    sheet.rel = 'stylesheet';
    sheet.href = new URL('../vendor/maplibre/maplibre-gl.css', import.meta.url).href;
    sheet.onload = sheet.onerror = resolve;
    document.head.append(sheet);
});

/** Leaflet's [lat, lon] or L.LatLng → MapLibre's [lon, lat]. */
function lngLat(latlng) {
    const l = L.latLng(latlng);
    return [l.lng, l.lat];
}

/**
 * The symbol of a find as an image MapLibre can place: the SVG of the
 * divIcon rendered once at the screen's density.
 */
function iconImage(icon) {
    const [w, h] = icon.options.iconSize ?? [24, 24];
    const ratio = Math.max(1, Math.min(3, window.devicePixelRatio || 1));
    const html = String(icon.options.html ?? '');
    // The library's drawing, only given the namespace an image of it needs.
    const svg = html.includes('xmlns') ? html : html.replace(/^(\s*<svg)/, '$1 xmlns="http://www.w3.org/2000/svg"');
    return new Promise((resolve) => {
        const img = new Image(w * ratio, h * ratio);
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = w * ratio;
            canvas.height = h * ratio;
            canvas.getContext('2d').drawImage(img, 0, 0, w * ratio, h * ratio);
            resolve({ data: canvas.getContext('2d').getImageData(0, 0, w * ratio, h * ratio), ratio });
        };
        img.onerror = () => resolve(null);
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    });
}

/* The layer order, bottom to top. Each group is an invisible anchor layer;
   a style layer of a group is inserted below the anchor of the next. */
const GROUPS = ['base', 'overlay', 'labels', 'paths', 'points'];

export class VectorMap extends L.Evented {
    /**
     * @param {string} id       the container's id
     * @param {object} options  Leaflet's map options that matter here
     *                          (center, zoom, maxZoom), plus glyphs and
     *                          sprite for the vector styles
     */
    constructor(id, options) {
        super();
        this._container = document.getElementById(id);
        this._container.classList.add('is-vector');
        this.options = { crs: L.CRS.EPSG3857, maxZoom: options.maxZoom ?? 20, minZoom: 0, ...options };
        this._layers = {};
        this._symbols = new Map();      // stamp(marker) → marker, for the finds' layer
        this._images = new Map();       // image id → { id, done, ready }
        this._offsets = new Map();      // "dx,dy" → [dx, dy], see _syncFinds
        this._findsSync = null;         // the frame that writes the finds' layer
        this._interactive = new Map();  // MapLibre layer id → the Leaflet path it draws
        this._looks = new Map();        // what the stylesheet says of a path, see _look

        const center = L.latLng(options.center ?? [51, 10]);
        this.gl = new MapLibreMap({
            container: this._container,
            style: {
                version: 8,
                glyphs: options.glyphs,
                sprite: options.sprite,
                sources: {
                    finds: { type: 'geojson', data: { type: 'FeatureCollection', features: [] } },
                },
                layers: [
                    ...GROUPS.map((g) => ({ id: `anchor-${g}`, type: 'background',
                                            paint: { 'background-opacity': 0 } })),
                    {
                        id: 'finds', type: 'symbol', source: 'finds',
                        layout: {
                            'icon-image': ['get', 'icon'],
                            'icon-allow-overlap': true,
                            'icon-ignore-placement': true,
                            // Upright on the screen while the map turns.
                            'icon-rotation-alignment': 'viewport',
                            'symbol-sort-key': ['get', 'order'],
                        },
                        paint: { 'icon-opacity': ['get', 'opacity'] },
                    },
                ],
            },
            center: [center.lng, center.lat],
            zoom: (options.zoom ?? 6) - OFFSET,
            maxZoom: this.options.maxZoom - OFFSET,
            attributionControl: false,
            // A phone turns the map with two fingers; the mouse with the
            // right button, as in every vector map.
            dragRotate: true,
            pitchWithRotate: false,
            touchPitch: false,
        });
        // Registered before every other 'load' listener (_wireEvents passes
        // 'load' on to the page, which may add paths then): from here on
        // a path is drawn at once (_addPath).
        this.gl.once('load', () => { this._glStyleLoaded = true; });
        this._initControlCorners();
        this._initSamples();
        this._wireEvents();
        this._findsReady = Promise.all([new Promise((resolve) => this.gl.once('load', resolve)), sheetLoaded]);
    }

    // --- Leaflet's control corners, so its controls sit where they sat ---

    _initControlCorners() {
        const container = L.DomUtil.create('div', 'leaflet-control-container', this._container);
        this._controlCorners = {};
        for (const [v, h] of [['top', 'left'], ['top', 'right'], ['bottom', 'left'], ['bottom', 'right']]) {
            this._controlCorners[v + h] = L.DomUtil.create('div', `leaflet-${v} leaflet-${h}`, container);
        }
        this._controlContainer = container;
    }
    addControl(control) { control.addTo(this); return this; }
    removeControl(control) { control.remove(); return this; }

    // --- events, in Leaflet's words ---

    _event(e) {
        const ll = e.lngLat ? L.latLng(e.lngLat.lat, e.lngLat.lng) : undefined;
        return {
            latlng: ll,
            containerPoint: e.point ? L.point(e.point.x, e.point.y) : undefined,
            layerPoint: e.point ? L.point(e.point.x, e.point.y) : undefined,
            originalEvent: e.originalEvent,
        };
    }

    _wireEvents() {
        const gl = this.gl;
        let zoomAtStart = null;
        gl.on('movestart', (e) => {
            zoomAtStart = gl.getZoom();
            // Only a hand's move carries the event that started it.
            if (e.originalEvent) this._userMoving = true;
            this.fire('movestart');
        });
        gl.on('move', () => this.fire('move'));
        gl.on('moveend', () => {
            this._userMoving = false;
            this.fire('moveend');
            if (zoomAtStart !== null && Math.abs(gl.getZoom() - zoomAtStart) > 1e-6) this.fire('zoomend');
            zoomAtStart = null;
        });
        gl.on('zoomstart', () => this.fire('zoomstart'));
        gl.on('zoom', () => this.fire('zoom'));
        gl.on('rotate', () => this.fire('rotate'));
        gl.on('dragstart', () => this.fire('dragstart'));
        gl.on('resize', () => this.fire('resize'));
        /* A TAP GOES TO WHAT LIES ON TOP, as in Leaflet: a find, an area,
           the strip along an outline — and only when nothing takes it, or
           what took it lets it bubble, to the map. MapLibre asks its layers
           one by one and would tell the map first; map.js would then set a
           point or close the window before the area had heard of it. */
        for (const type of ['click', 'dblclick', 'contextmenu']) {
            gl.on(type, (e) => {
                const target = this._target(e.point, type);
                if (target) {
                    // Leaflet keeps the browser's menu away from a layer that answers it.
                    if (type === 'contextmenu') e.originalEvent?.preventDefault?.();
                    target.fire(type, { ...this._event(e), target });
                    // Markers never bubble; a path does unless it says no or was stopped.
                    const bubbles = target instanceof L.Path && target.options.bubblingMouseEvents !== false;
                    if (!bubbles || e.originalEvent?._stopped) return;
                }
                this.fire(type, this._event(e));
            });
        }
        /* The pointer tells what a tap would hit — with the cursor the
           stylesheet gives that thing (a hand, or the copy sign along an
           outline). Once a frame at most, and not while the map moves. */
        let hover = null;
        gl.on('mousemove', (e) => {
            this.fire('mousemove', this._event(e));
            if (hover !== null) { hover = e.point; return; }
            hover = e.point;
            requestAnimationFrame(() => {
                const at = hover;
                hover = null;
                if (gl.isMoving()) return;
                const target = this._target(at, 'click');
                gl.getCanvas().style.cursor = target ? this._cursor(target) : '';
            });
        });
        gl.on('mouseout', () => { gl.getCanvas().style.cursor = ''; });
        gl.once('load', () => this.fire('load'));
    }

    /* THE STYLESHEET DECIDES, AS IT DOES FOR LEAFLET. Leaflet draws a path
       as SVG with the layer's className, so map.css gives the drawing
       tool its dashed outline and the corners their white dots, and it
       switches whole kinds off for taps — everything while tracing, the
       areas and corner handles while a point is being set. The vector map
       draws no SVG, so it asks the stylesheet what it WOULD say: a hidden
       sample with the same classes in the same place, read through
       getComputedStyle. One set of rules for both engines; a second copy
       of them in here would drift apart from the first. */
    _initSamples() {
        const root = L.DomUtil.create('div', '', this._container);
        root.setAttribute('aria-hidden', 'true');
        root.style.cssText = 'position:absolute;left:0;top:0;width:0;height:0;overflow:hidden;visibility:hidden';
        const overlay = L.DomUtil.create('div', 'leaflet-pane leaflet-overlay-pane', root);
        const svg = overlay.appendChild(document.createElementNS('http://www.w3.org/2000/svg', 'svg'));
        this._samplePath = svg.appendChild(document.createElementNS('http://www.w3.org/2000/svg', 'path'));
        this._samplePath.setAttribute('d', 'M0 0');
        const markers = L.DomUtil.create('div', 'leaflet-pane leaflet-marker-pane', root);
        this._sampleMarker = L.DomUtil.create('div', '', markers);
    }

    /** The sample, dressed as this layer would be in Leaflet. */
    _dress(layer) {
        if (layer instanceof L.Marker) {
            const el = this._sampleMarker;
            el.className = `leaflet-marker-icon leaflet-interactive ${layer.options.icon?.options?.className ?? ''}`;
            return el;
        }
        // What Leaflet's SVG renderer writes as attributes (_updateStyle);
        // a rule in the stylesheet beats each of them.
        const o = layer.options;
        const el = this._samplePath;
        el.setAttribute('class', `${o.interactive !== false ? 'leaflet-interactive ' : ''}${o.className ?? ''}`);
        const set = (name, value) => (value === null || value === undefined
            ? el.removeAttribute(name) : el.setAttribute(name, value));
        set('stroke', o.stroke !== false ? o.color : 'none');
        set('stroke-opacity', o.opacity);
        set('stroke-width', o.weight);
        set('stroke-dasharray', o.dashArray || null);
        set('fill', o.fill ? (o.fillColor || o.color) : 'none');
        set('fill-opacity', o.fillOpacity);
        return el;
    }

    /** What the stylesheet makes of a path — remembered per look. */
    _look(layer) {
        const o = layer.options;
        const key = [o.className, o.stroke, o.color, o.weight, o.opacity, o.dashArray,
            o.fill, o.fillColor, o.fillOpacity].join('|');
        let look = this._looks.get(key);
        if (look) return look;
        const cs = getComputedStyle(this._dress(layer));
        const paint = (v) => (v && v !== 'none' ? v : null);
        const width = parseFloat(cs.strokeWidth) || 0;
        const dash = cs.strokeDasharray && cs.strokeDasharray !== 'none'
            ? cs.strokeDasharray.split(/[ ,]+/).map(parseFloat).filter((n) => n > 0) : [];
        look = {
            stroke: paint(cs.stroke),
            width,
            strokeOpacity: Number(cs.strokeOpacity),
            // Leaflet counts dashes in pixels, MapLibre in line widths.
            dash: dash.length && width ? dash.map((n) => n / width) : null,
            fill: paint(cs.fill),
            fillOpacity: Number(cs.fillOpacity),
            // The dark seam map.css draws with a drop shadow (see .draw-polygon).
            seam: !!cs.filter && cs.filter !== 'none',
        };
        this._looks.set(key, look);
        return look;
    }

    /** Whether the stylesheet lets this layer take a tap right now. */
    _takesTaps(layer) {
        return getComputedStyle(this._dress(layer)).pointerEvents !== 'none';
    }

    _cursor(layer) {
        const cursor = getComputedStyle(this._dress(layer)).cursor;
        return cursor && cursor !== 'auto' ? cursor : 'pointer';
    }

    /**
     * What a tap at this point lands on: the topmost find or path the
     * stylesheet lets take taps — or nothing, and then it is the map's.
     * As in Leaflet, what lies on top but does not listen hands the tap
     * to the map, not to what lies below it.
     */
    _target(point, type) {
        const ids = ['finds', ...this._interactive.keys()].filter((id) => this.gl.getLayer(id));
        // A few pixels of give: a two-pixel line is no target for a finger.
        const box = [[point.x - 3, point.y - 3], [point.x + 3, point.y + 3]];
        for (const feature of this.gl.queryRenderedFeatures(box, { layers: ids })) {
            const found = feature.layer.id === 'finds'
                ? this._symbols.get(feature.properties.id)
                : this._interactive.get(feature.layer.id);
            // A style layer of many features names the one that was hit.
            const layer = found?.vectorTarget ? found.vectorTarget(feature) : found;
            if (!layer || !this._takesTaps(layer)) continue;
            return layer.listens(type) ? layer : null;
        }
        return null;
    }

    whenReady(fn, ctx) {
        if (this.gl.loaded()) fn.call(ctx || this, { target: this });
        else this.gl.once('load', () => fn.call(ctx || this, { target: this }));
        return this;
    }

    // --- the camera ---

    getZoom() { return this.gl.getZoom() + OFFSET; }
    get _zoom() { return this.getZoom(); }
    getMinZoom() { return this.gl.getMinZoom() + OFFSET; }
    getMaxZoom() { return this.gl.getMaxZoom() + OFFSET; }
    setMaxZoom(z) { this.gl.setMaxZoom(z - OFFSET); return this; }
    getCenter() { const c = this.gl.getCenter(); return L.latLng(c.lat, c.lng); }
    getBearing() { return this.gl.getBearing(); }

    /* WHETHER A HAND IS MOVING THE MAP — pinch, pan, turn, wheel, and the
       glide after. MapLibre ends a gesture the moment code moves the
       camera (every easeTo and jumpTo stops the handlers), so whatever
       moves the map by itself — following the location, turning with the
       view — asks this first and waits. isMoving() heals a missed end;
       the handlers' own word (not public, hence the ?.) covers a gesture
       begun while one of our glides was still running. */
    userMoving() {
        return (!!this._userMoving && this.gl.isMoving()) || !!this.gl._handlers?.isActive?.();
    }
    setBearing(b) { this.gl.setBearing(b); return this; }
    /* FOLLOWING AND TURNING IN ONE MOVE (03.10.2026, walking with
       „Mitdrehen" on: the map stood still and the dot walked out of it).
       Every camera call in MapLibre begins with stop() — setBearing is a
       jumpTo — so the heading frame, turning the map nearly sixty times a
       second after the compass, killed each follow glide one frame after it
       began, and the centre crept a few pixels per location report. One
       easeTo carries both, centre and bearing; map.js keeps the compass
       from turning the map while it runs (followLocation, drawHeading). */
    followTo(latlng, bearing, options = {}) {
        const duration = (options.duration ?? 0.5) * 1000;
        this.gl.easeTo({ center: lngLat(latlng), bearing, duration });
        return this;
    }

    setView(latlng, zoom, options = {}) {
        const target = { center: lngLat(latlng), zoom: (zoom ?? this.getZoom()) - OFFSET };
        if (options.animate) this.gl.easeTo({ ...target, duration: 400 });
        else this.gl.jumpTo(target);
        return this;
    }
    setZoom(zoom) { this.gl.easeTo({ zoom: zoom - OFFSET, duration: 250 }); return this; }
    zoomIn(delta = 1) { this.gl.zoomIn(); return this; }
    zoomOut(delta = 1) { this.gl.zoomOut(); return this; }
    panTo(latlng, options = {}) {
        const duration = options.animate === false ? 0 : (options.duration ?? 0.25) * 1000;
        this.gl.easeTo({ center: lngLat(latlng), duration });
        return this;
    }
    flyTo(latlng, zoom, options = {}) {
        this.gl.flyTo({ center: lngLat(latlng), zoom: (zoom ?? this.getZoom()) - OFFSET,
                        duration: (options.duration ?? 1) * 1000 });
        return this;
    }
    fitBounds(bounds, options = {}) {
        const b = L.latLngBounds(bounds);
        if (!b.isValid()) return this;
        // Leaflet's padding and its two corner paddings, as MapLibre's four
        // sides — the area window keeps the covered right edge free so.
        const all = L.point(options.padding ?? [0, 0]);
        const topLeft = L.point(options.paddingTopLeft ?? all);
        const bottomRight = L.point(options.paddingBottomRight ?? all);
        const view = {
            padding: { top: topLeft.y, left: topLeft.x, bottom: bottomRight.y, right: bottomRight.x },
            duration: options.animate === false ? 0 : 400,
        };
        /* ONLY WHEN GIVEN. MapLibre lays the options over its defaults, and
           a maxZoom: undefined overwrote its own — Math.min with undefined
           is NaN, and „Zeigen" on an area threw „Invalid LngLat (NaN, NaN)". */
        if (options.maxZoom !== undefined) view.maxZoom = options.maxZoom - OFFSET;
        this.gl.fitBounds([[b.getWest(), b.getSouth()], [b.getEast(), b.getNorth()]], view);
        return this;
    }
    flyToBounds(bounds, options = {}) { return this.fitBounds(bounds, options); }
    getBounds() {
        const b = this.gl.getBounds();
        return L.latLngBounds([b.getSouth(), b.getWest()], [b.getNorth(), b.getEast()]);
    }
    getSize() { return L.point(this._container.clientWidth, this._container.clientHeight); }
    invalidateSize() { this.gl.resize(); return this; }
    getContainer() { return this._container; }
    stop() { this.gl.stop(); return this; }

    // --- projections: the container point is also the layer point here ---

    latLngToContainerPoint(latlng) { const p = this.gl.project(lngLat(latlng)); return L.point(p.x, p.y); }
    containerPointToLatLng(point) { const p = L.point(point); const l = this.gl.unproject([p.x, p.y]); return L.latLng(l.lat, l.lng); }
    latLngToLayerPoint(latlng) { return this.latLngToContainerPoint(latlng); }
    layerPointToLatLng(point) { return this.containerPointToLatLng(point); }
    mouseEventToLatLng(e) {
        const r = this._container.getBoundingClientRect();
        return this.containerPointToLatLng([e.clientX - r.left, e.clientY - r.top]);
    }
    project(latlng, zoom) { return L.CRS.EPSG3857.latLngToPoint(L.latLng(latlng), zoom ?? this.getZoom()); }
    unproject(point, zoom) { return L.CRS.EPSG3857.pointToLatLng(L.point(point), zoom ?? this.getZoom()); }
    getPixelWorldBounds(zoom) { return L.CRS.EPSG3857.getProjectedBounds(zoom ?? this.getZoom()); }
    distance(a, b) { return L.latLng(a).distanceTo(L.latLng(b)); }

    // Panes: layers of this map draw in MapLibre, not in panes. An empty
    // element keeps code that asks for one from failing.
    getPane() { return this._container; }
    createPane() { return this._container; }

    // --- popups ---

    /* A LEAFLET POPUP, SHOWN BY MAPLIBRE. It stays on the map's books
       while it is open (hasLayer, isOpen), so Leaflet's own close() finds
       its way here — the corner menu closes itself so on every drag. */
    openPopup(popup, latlng) {
        if (!(popup instanceof L.Popup)) popup = L.popup().setContent(popup);
        if (latlng) popup.setLatLng(latlng);
        // One at a time, as Leaflet's autoClose does.
        if (this._shown && this._shown !== popup) this._shown._glPopup?.remove();
        if (this._shown === popup) return this;

        const o = popup.options ?? {};
        let content = popup.getContent?.();
        if (typeof content === 'function') content = content(popup._source ?? popup);
        /* Leaflet's offset puts the tip's point at [0, 7] by default and
           adds the icon's popupAnchor; MapLibre's [0, 0] already sits on
           the spot. The distance is kept for whichever side MapLibre picks
           to keep the popup in view — its answer to Leaflet's autoPan. */
        const shift = L.point(o.offset ?? [0, 7]).add(L.point(popup._source?.options?.icon?.options?.popupAnchor ?? [0, 0]));
        const ox = shift.x;
        const d = Math.max(0, 7 - shift.y);
        const gl = new GlPopup({
            closeButton: o.closeButton !== false,
            className: o.className || '',
            maxWidth: `${o.maxWidth ?? 300}px`,
            offset: { center: [ox, 0], top: [ox, d], 'top-left': [ox, d], 'top-right': [ox, d],
                      bottom: [ox, -d], 'bottom-left': [ox, -d], 'bottom-right': [ox, -d],
                      left: [d, 0], right: [-d, 0] },
        }).setLngLat(lngLat(popup.getLatLng()));
        if (content instanceof HTMLElement) gl.setDOMContent(content);
        else gl.setHTML(String(content ?? ''));

        const id = L.stamp(popup);
        this._layers[id] = popup;
        popup._map = this;
        popup._glPopup = gl;
        this._popup = this._shown = popup;
        gl.on('close', () => {
            if (this._shown === popup) this._shown = null;
            if (this._popup === popup) this._popup = null;
            delete this._layers[id];
            popup._map = null;
            popup._glPopup = null;
            this.fire('popupclose', { popup });
            popup.fire?.('remove');
        });
        gl.addTo(this.gl);
        this.fire('popupopen', { popup });
        popup.fire?.('add');
        return this;
    }
    closePopup(popup) { (popup ?? this._shown)?._glPopup?.remove(); return this; }

    // --- style layers, placed by group ---

    addStyleLayer(layer, group) {
        const next = GROUPS[GROUPS.indexOf(group) + 1];
        this.gl.addLayer(layer, next ? `anchor-${next}` : undefined);
    }

    /* A STYLE LAYER THAT TAKES TAPS, beside the finds and paths: a tap on
       it goes to `target` as to a Leaflet layer (see _target), and
       target.vectorTarget(feature), if it has one, names the answering
       object per feature — the protected areas answer with the area. */
    addTapLayer(id, target) { this._interactive.set(id, target); }
    removeTapLayer(id) { this._interactive.delete(id); }
    ready() { return this._findsReady; }

    // --- layers: Leaflet's objects, drawn by MapLibre ---

    hasLayer(layer) { return !!layer && !!this._layers[L.stamp(layer)]; }
    eachLayer(fn, ctx) { for (const layer of Object.values(this._layers)) fn.call(ctx, layer); return this; }

    addLayer(layer) {
        const id = L.stamp(layer);
        if (this._layers[id]) return this;
        this._layers[id] = layer;
        layer._map = this;
        layer._mapToAdd = this;
        if (layer instanceof L.Popup) {
            delete this._layers[id];
            return this.openPopup(layer);
        }
        if (typeof layer.onAddVector === 'function') {
            layer.onAddVector(this);
        } else if (layer instanceof L.Marker) {
            this._addMarker(layer);
        } else if (layer instanceof L.Path) {
            this._addPath(layer);
        } else if (layer instanceof L.LayerGroup) {
            layer.eachLayer((child) => this.addLayer(child));
        } else {
            console.warn('Vector map: layer kind not drawn yet', layer);
        }
        layer.fire?.('add');
        this.fire('layeradd', { layer });
        return this;
    }

    removeLayer(layer) {
        const id = L.stamp(layer);
        if (layer instanceof L.Popup) { layer._glPopup?.remove(); return this; }
        if (!this._layers[id]) return this;
        if (typeof layer.onRemoveVector === 'function') {
            layer.onRemoveVector(this);
        } else if (layer instanceof L.Marker) {
            this._removeMarker(layer);
        } else if (layer instanceof L.Path) {
            this._removePath(layer);
        } else if (layer instanceof L.LayerGroup) {
            layer.eachLayer((child) => this.removeLayer(child));
        }
        delete this._layers[id];
        layer._map = null;
        layer._mapToAdd = null;
        layer.fire?.('remove');
        this.fire('layerremove', { layer });
        return this;
    }

    // --- markers ---

    /* TWO WAYS TO DRAW A MARKER. A find is a feature of the symbol layer
       (see the head); every other marker is a real DOM marker — and so is
       the ONE find whose dragging is on. A picture inside the map's picture
       cannot be grabbed, while MapLibre's own markers know finger and mouse;
       so a find that may be dragged steps out of the layer for as long as it
       may, and back in after. drawMarker() switches dragging on only for the
       chosen find, so there is never more than one of them. */

    _isFind(marker) {
        const icon = marker.options.icon;
        return icon instanceof L.DivIcon && String(icon.options.className ?? '').includes('point-symbol');
    }

    _addMarker(marker) {
        /* NOT LEAFLET'S setIcon, getElement, setOpacity: they work on
           marker._icon, the element Leaflet would have hung into its pane,
           and with a map set setIcon builds that element and hangs it into
           the container — a stray one for every redraw. These stand in for
           them while the marker is here; removal deletes them again, which
           lays the prototype's bare. */
        marker.setIcon = (next) => { marker.options.icon = next; this._redrawMarker(marker); return marker; };
        marker.getElement = () => marker._vectorEl ?? undefined;
        marker.setOpacity = (value) => { marker.options.opacity = value; this._redrawMarker(marker); return marker; };
        marker._vectorMove = () => {
            // While MapLibre drags the marker, it places it itself.
            if (marker._glMarker && !marker._vectorDragging) marker._glMarker.setLngLat(lngLat(marker.getLatLng()));
            if (this._symbols.has(L.stamp(marker))) this._syncFinds();
        };
        marker.on('move', marker._vectorMove);
        if (this._isFind(marker)) this._toLayer(marker);
        else this._mountDom(marker);
        this._initDragging(marker);
    }

    _removeMarker(marker) {
        // As Leaflet does: taken off while draggable, it comes back draggable.
        if (marker.dragging?.enabled()) marker.options.draggable = true;
        delete marker.dragging;
        if (this._symbols.delete(L.stamp(marker))) this._syncFinds();
        this._unmountDom(marker);
        marker.off('move', marker._vectorMove);
        delete marker._vectorMove;
        delete marker.setIcon;
        delete marker.getElement;
        delete marker.setOpacity;
    }

    _redrawMarker(marker) {
        if (this._symbols.has(L.stamp(marker))) this._syncFinds();
        if (marker._glMarker) this._fillDom(marker);
    }

    _toLayer(marker) {
        this._symbols.set(L.stamp(marker), marker);
        return this._syncFinds();
    }

    /* A REAL DOM MARKER. MapLibre moves — and drags — a wrapper of its own;
       Leaflet's icon element sits inside it with the margins Leaflet gives
       it as the anchor. A new icon therefore only swaps the wrapper's
       child, and the element MapLibre holds stays the same. */
    _mountDom(marker) {
        const wrapper = document.createElement('div');
        const gl = new GlMarker({ element: wrapper, anchor: 'top-left' })
            .setLngLat(lngLat(marker.getLatLng()))
            .addTo(this.gl);
        marker._glMarker = gl;
        this._fillDom(marker);

        for (const type of ['click', 'dblclick', 'contextmenu']) {
            wrapper.addEventListener(type, (e) => {
                /* THE TAIL OF A DRAG IS NO CLICK. The icon follows the
                   pointer, so the button comes up over it and the browser
                   calls that a click on it — Leaflet swallows it, and so
                   does this. */
                if (type === 'click' && Date.now() - (marker._vectorDragEnd ?? 0) < 400) {
                    e.stopPropagation();
                    return;
                }
                // A marker that does not listen is no target, as in Leaflet:
                // the event goes on to the map.
                if (!marker.listens(type)) return;
                e.stopPropagation();
                if (type === 'contextmenu') e.preventDefault();
                marker.fire(type, { latlng: marker.getLatLng(), originalEvent: e, target: marker });
            });
        }

        // MapLibre's drag, told in Leaflet's words — the same events in the
        // same order as its MarkerDrag fires them.
        gl.on('dragstart', () => {
            marker._vectorDragging = true;
            // Leaflet's mark on what is being dragged — the corner handle
            // grows by it, so the finger knows it has the handle.
            marker._vectorEl?.classList.add('leaflet-drag-target');
            marker.fire('movestart').fire('dragstart');
        });
        gl.on('drag', () => {
            const at = gl.getLngLat();
            const oldLatLng = marker._latlng;
            marker._latlng = L.latLng(at.lat, at.lng);
            const e = { latlng: marker._latlng, oldLatLng };
            marker.fire('move', e).fire('drag', e);
        });
        gl.on('dragend', () => {
            marker._vectorDragging = false;
            marker._vectorEl?.classList.remove('leaflet-drag-target');
            marker._vectorDragEnd = Date.now();
            marker.fire('moveend').fire('dragend');
        });
    }

    _fillDom(marker) {
        const icon = marker.options.icon ?? new L.Icon.Default();
        const el = icon.createIcon();
        /* Leaflet's stylesheet lets a marker icon through to the map unless
           it carries this class — its own markers get it on adding. */
        if (marker.options.interactive !== false) el.classList.add('leaflet-interactive');
        if (marker.options.title) el.title = marker.options.title;
        el.style.opacity = String(marker.options.opacity ?? 1);
        /* NOT marker._icon: Leaflet's update() positions whatever stands
           there by a transform of its own, and MapLibre positions the same
           element by another — the two would fight every frame. getElement()
           answers from here instead. */
        marker._vectorEl = el;
        const wrapper = marker._glMarker.getElement();
        wrapper.replaceChildren(el);
        wrapper.style.zIndex = marker.options.zIndexOffset ? String(1000 + marker.options.zIndexOffset) : '';
    }

    _unmountDom(marker) {
        marker._glMarker?.remove();
        marker._glMarker = null;
        marker._vectorEl = null;
    }

    /* LEAFLET'S marker.dragging, as drawMarker() switches it: on for the
       chosen find (unless it is an area), off for all others. */
    _initDragging(marker) {
        const map = this;
        const id = L.stamp(marker);
        let on = false;
        marker.dragging = {
            enabled: () => on,
            enable() {
                if (on) return;
                on = true;
                if (map._symbols.delete(id)) {
                    map._syncFinds();
                    // Still standing if it was on its way back into the layer.
                    if (!marker._glMarker) map._mountDom(marker);
                }
                marker._glMarker.setDraggable(true);
            },
            disable() {
                if (!on) return;
                on = false;
                marker._glMarker?.setDraggable(false);
                if (!map._isFind(marker)) return;
                /* BACK INTO THE LAYER — and the DOM marker goes only once the
                   layer draws the find: GeoJSON goes through the worker
                   first, and without the wait the find would blink out for a
                   few frames on every deselection. */
                const gl = marker._glMarker;
                map._toLayer(marker)
                    .then(() => map._drawnInLayer(marker))
                    .then(() => {
                        if (!on && marker._glMarker === gl && map._symbols.has(id)) map._unmountDom(marker);
                    });
            },
        };
        if (marker.options.draggable) marker.dragging.enable();
    }

    /** Resolves once the finds' layer draws this marker — or after a second. */
    _drawnInLayer(marker) {
        const id = L.stamp(marker);
        const until = Date.now() + 1000;
        return new Promise((resolve) => {
            const look = () => {
                const p = this.gl.project(lngLat(marker.getLatLng()));
                const hit = this.gl.queryRenderedFeatures([[p.x - 4, p.y - 4], [p.x + 4, p.y + 4]], { layers: ['finds'] })
                    .some((f) => f.properties.id === id);
                if (hit || Date.now() > until) resolve();
                else requestAnimationFrame(look);
            };
            requestAnimationFrame(look);
        });
    }

    /* The finds' layer is written once per frame, however many markers
       changed in it — a redraw touches hundreds at a time. The pictures of
       new looks are drawn FIRST and the features built only AFTER, from the
       markers as they stand then: features built before an await would
       overwrite whatever a later frame wrote in between. Resolves once the
       layer has been given what the markers say. */
    _syncFinds() {
        if (this._findsSync) return this._findsSync;
        this._findsSync = new Promise((resolve) => requestAnimationFrame(async () => {
            await this._findsReady;
            for (;;) {
                const waiting = [];
                for (const marker of this._symbols.values()) {
                    const image = this._image(marker.options.icon);
                    if (!image.done) waiting.push(image.ready);
                }
                if (!waiting.length) break;
                await Promise.all(waiting);
            }
            // From here on a change asks for the next frame.
            this._findsSync = null;
            const features = [];
            const offsets = this._offsets.size;
            for (const [id, marker] of this._symbols) {
                const icon = marker.options.icon;
                const [w, h] = icon.options.iconSize ?? [24, 24];
                const [ax, ay] = icon.options.iconAnchor ?? [w / 2, h / 2];
                const offset = `${w / 2 - ax},${h / 2 - ay}`;
                if (!this._offsets.has(offset)) this._offsets.set(offset, [w / 2 - ax, h / 2 - ay]);
                const at = marker.getLatLng();
                features.push({
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: [at.lng, at.lat] },
                    properties: {
                        id, icon: this._image(icon).id,
                        offset,
                        // Paler while it waits for the server, as in Leaflet.
                        opacity: marker.options.opacity ?? 1,
                        // The chosen one on top, as Leaflet's zIndexOffset did.
                        order: String(icon.options.className).includes('is-selected') ? 1 : 0,
                    },
                });
            }
            /* THE ANCHOR BY NAME, NOT BY VALUE. A GeoJSON feature's
               properties reach the style as numbers and strings only — an
               array arrives as its JSON text, and 'icon-offset' fell back to
               nothing: every find stood centred on its spot instead of on
               its foot, a few pixels off (29.09.–30.09.2026). The few
               offsets there are — one per shape and state — are named, and
               the layer looks them up. */
            if (this._offsets.size !== offsets) {
                const cases = [...this._offsets].flatMap(([key, value]) => [key, ['literal', value]]);
                this.gl.setLayoutProperty('finds', 'icon-offset', ['match', ['get', 'offset'], ...cases, ['literal', [0, 0]]]);
            }
            this.gl.getSource('finds')?.setData({ type: 'FeatureCollection', features });
            resolve();
        }));
        return this._findsSync;
    }

    /** The picture of a find's look — drawn once, then shared by every find wearing it. */
    _image(icon) {
        const id = `find-${L.stamp(icon)}`;
        let image = this._images.get(id);
        if (!image) {
            image = { id, done: false };
            image.ready = iconImage(icon).then((drawn) => {
                if (drawn && !this.gl.hasImage(id)) this.gl.addImage(id, drawn.data, { pixelRatio: drawn.ratio });
                image.done = true;
            });
            this._images.set(id, image);
        }
        return image;
    }

    // --- lines, areas and circles ---

    _pathSource(layer) { return `path-${L.stamp(layer)}`; }

    _pathData(layer) {
        if (layer instanceof L.Circle) {
            // A circle in metres: a ring of 64 corners around the centre.
            const c = layer.getLatLng();
            const r = layer.getRadius();
            const ring = [];
            for (let i = 0; i <= 64; i++) {
                const a = (i / 64) * 2 * Math.PI;
                const dLat = (r * Math.cos(a)) / 111320;
                const dLon = (r * Math.sin(a)) / (111320 * Math.cos(c.lat * Math.PI / 180));
                ring.push([c.lng + dLon, c.lat + dLat]);
            }
            return { type: 'Feature', geometry: { type: 'Polygon', coordinates: [ring] }, properties: {} };
        }
        if (layer instanceof L.CircleMarker) {
            const c = layer.getLatLng();
            return { type: 'Feature', geometry: { type: 'Point', coordinates: [c.lng, c.lat] }, properties: {} };
        }
        const toRing = (list) => list.map((ll) => [ll.lng, ll.lat]);
        let rings = layer.getLatLngs();
        if (layer instanceof L.Polygon) {
            // Leaflet nests one level deeper for multi-polygons.
            if (rings.length && Array.isArray(rings[0]) && rings[0].length && Array.isArray(rings[0][0])) {
                return { type: 'Feature', properties: {}, geometry: { type: 'MultiPolygon',
                    coordinates: rings.map((poly) => poly.map((ring) => { const r = toRing(ring); if (r.length) r.push(r[0]); return r; })) } };
            }
            if (rings.length && !Array.isArray(rings[0])) rings = [rings];
            return { type: 'Feature', properties: {}, geometry: { type: 'Polygon',
                coordinates: rings.map((ring) => { const r = toRing(ring); if (r.length) r.push(r[0]); return r; }) } };
        }
        if (rings.length && Array.isArray(rings[0])) {
            return { type: 'Feature', properties: {}, geometry: { type: 'MultiLineString', coordinates: rings.map(toRing) } };
        }
        return { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: toRing(rings) } };
    }

    _addPath(layer) {
        // Leaflet's setStyle recomputes pixel bounds from a projected point
        // it never had here; a zero point keeps that arithmetic harmless.
        if (layer instanceof L.CircleMarker) layer._point = L.point(0, 0);
        // Leaflet's path methods end in its renderer; this one writes the
        // source instead (setLatLngs, setStyle, redraw, setRadius, bringToFront …).
        // A path still waiting for the style (below) has nothing to write
        // into yet — its drawing on 'load' takes whatever it is by then.
        const map = this;
        layer._renderer = {
            options: {},
            _updatePath: (l) => { if (!l._glWaiting) map._syncPath(l); },
            _updateStyle: (l) => { if (!l._glWaiting) map._drawPath(l); },
            _updateCircle: (l) => { if (!l._glWaiting) map._syncPath(l); },
            _bringToFront: (l) => { for (const id of l._glLayers ?? []) map.gl.moveLayer(id, 'anchor-points'); },
            _bringToBack: () => {},
            _removePath: () => {},
            _addPath: () => {},
        };
        /* NOT BEFORE THE STYLE HAS LOADED (05.11.2026). MapLibre refuses
           addSource until then — „Style is not done loading" — and the
           throw went up through drawFilterAreaShapes() at the start of
           map.js, so a drawn filter area in the sidebar stopped the whole
           page from starting: no sidebar, no points, a map that „hangs".
           Leaflet draws a path the moment it is added; here it waits for
           'load' and is drawn then, unless it was taken off meanwhile. */
        /* Our own flag, not gl.loaded(): that one also turns false for a
           moment whenever tiles are pending, long after 'load' has fired
           its only time. A token per waiting, so a path taken off and put
           on again before 'load' is drawn once, not twice. */
        if (!this._glStyleLoaded) {
            const token = {};
            layer._glWaiting = token;
            this.gl.once('load', () => {
                if (layer._glWaiting !== token) return;
                layer._glWaiting = null;
                if (this._layers[L.stamp(layer)] === layer) this._drawPathFirst(layer);
            });
            return;
        }
        this._drawPathFirst(layer);
    }

    _drawPathFirst(layer) {
        this.gl.addSource(this._pathSource(layer), { type: 'geojson', data: this._pathData(layer) });
        layer._glLayers = [];
        this._drawPath(layer);
    }

    /** The MapLibre layers a path needs to look as the stylesheet says. */
    _pathLayers(layer) {
        const src = this._pathSource(layer);
        const look = this._look(layer);
        if (layer instanceof L.CircleMarker && !(layer instanceof L.Circle)) {
            return [{ id: `${src}-c`, type: 'circle', source: src, paint: {
                'circle-radius': layer.getRadius(),
                'circle-color': look.fill ?? '#000',
                'circle-opacity': look.fill ? look.fillOpacity : 0,
                'circle-stroke-color': look.stroke ?? '#000',
                'circle-stroke-width': look.stroke ? look.width : 0,
                'circle-stroke-opacity': look.strokeOpacity,
            } }];
        }
        const specs = [];
        const layout = { 'line-join': 'round', 'line-cap': 'round' };
        const area = layer instanceof L.Polygon || layer instanceof L.Circle;
        if (area && look.fill) {
            specs.push({ id: `${src}-f`, type: 'fill', source: src,
                paint: { 'fill-color': look.fill, 'fill-opacity': look.fillOpacity } });
        }
        if (look.stroke) {
            /* THE SEAM beneath the line: MapLibre knows no drop shadow, so
               a blurred dark line one step wider stands in for it. */
            if (look.seam) {
                specs.push({ id: `${src}-s`, type: 'line', source: src, layout, paint: {
                    'line-color': 'rgba(0, 0, 0, .6)',
                    'line-width': look.width + 2,
                    'line-blur': 2,
                    'line-opacity': look.strokeOpacity,
                } });
            }
            specs.push({ id: `${src}-l`, type: 'line', source: src, layout, paint: {
                'line-color': look.stroke,
                'line-width': look.width,
                'line-opacity': look.strokeOpacity,
                'line-dasharray': look.dash ?? undefined,
            } });
        }
        return specs;
    }

    /* Draw a path's layers — or, when only its paint changed (a redraw
       asks this of every area), set the paint. A look that needs other
       layers (a fill that came or went) is built anew at the same place
       among the paths. */
    _drawPath(layer) {
        const specs = this._pathLayers(layer);
        const old = layer._glLayers ?? [];
        if (old.length && old.length === specs.length && specs.every((spec, i) => spec.id === old[i])) {
            for (const spec of specs) {
                for (const [name, value] of Object.entries(spec.paint)) {
                    this.gl.setPaintProperty(spec.id, name, value ?? null);
                }
            }
        } else {
            const order = this.gl.getLayersOrder();
            const before = old.length ? order[order.indexOf(old.at(-1)) + 1] : 'anchor-points';
            for (const id of old) { this.gl.removeLayer(id); this._interactive.delete(id); }
            for (const spec of specs) {
                for (const name of Object.keys(spec.paint)) if (spec.paint[name] === undefined) delete spec.paint[name];
                this.gl.addLayer(spec, before);
            }
            layer._glLayers = specs.map((spec) => spec.id);
            // The seam is no target: it would widen the line for the finger.
            if (layer.options.interactive !== false) {
                for (const id of layer._glLayers) if (!id.endsWith('-s')) this._interactive.set(id, layer);
            }
        }
        this._syncPath(layer);
    }

    _syncPath(layer) {
        this.gl.getSource(this._pathSource(layer))?.setData(this._pathData(layer));
        const circle = `${this._pathSource(layer)}-c`;
        if (this.gl.getLayer(circle)) this.gl.setPaintProperty(circle, 'circle-radius', layer.getRadius());
    }

    _removePath(layer) {
        for (const id of layer._glLayers ?? []) {
            if (this.gl.getLayer(id)) this.gl.removeLayer(id);
            this._interactive.delete(id);
        }
        const src = this._pathSource(layer);
        if (this.gl.getSource(src)) this.gl.removeSource(src);
        layer._glLayers = null;
        layer._glWaiting = null;
        layer._renderer = null;
    }
}

/** Create the map — once MapLibre's own style has told us its lettering. */
export function createVectorMap(id, options) {
    return new VectorMap(id, options);
}
