/**
 * A map that is a picture: tiles and finds painted once into a canvas.
 *
 * BORN IN THE FIND REPORT (24.08.2026 — „Warum kann man denn
 * nicht einfach ein Canvas machen"), where a live map could not be pinned
 * down for printing: Leaflet frames itself for the box of one moment, and
 * the print box is never that box. A canvas carries a fixed bitmap, so
 * whatever box it lands in, it shows the same section. The report's page
 * carries the whole story of the two repairs that were not enough.
 *
 * MOVED OUT OF THAT PAGE ON 13.09.2026, when the excursion's closing page
 * wanted the same picture above its species list („eine Karte,
 * die nicht zoombar ist, nicht verschiebbar, nur der Ausschnitt mit den
 * Punkten"). Two copies of web Mercator and a tile loop would be the pair
 * that drifts; one module draws both.
 *
 * AND NO LIBRARY FOR IT. Web Mercator is four lines, the tiles are
 * ordinary images, the finds are circles. Sixty lines instead of a
 * megabyte, and every pixel of it is ours.
 *
 * SELF-BINDING, like tables.js: every <canvas data-points="[[lat,lon],…]">
 * on the page is painted at load. data-max-zoom caps how close the fit
 * may go, data-pad is the air around the outermost find in canvas pixels,
 * data-radius the dot, data-wait the id of a line to hide once the picture
 * stands. The canvas brings its own width and height in the markup, so
 * the space is reserved before the first tile arrives — otherwise the
 * page would jump once under the reading eye.
 *
 * crossOrigin KEEPS THE CANVAS CLEAN, and that is worth its one risk.
 * Without it the canvas would be tainted — for painting nothing changes,
 * only reading pixels back is then forbidden. But a clean canvas can be
 * measured: getImageData says whether the tiles and the finds are really
 * in there. tile.openstreetmap.org sends Access-Control-Allow-Origin: *
 * (measured 24.08.2026); a tile that arrives without it stays away, and a
 * missing tile is a pale patch the loader survives.
 */

const TILE = 256;

/** Web Mercator at zoom 0, where the whole world is one 256er tile. */
function project(lat, lon) {
    let s = Math.sin(lat * Math.PI / 180);
    s = Math.max(-0.9999, Math.min(0.9999, s));
    return [
        (lon + 180) / 360 * TILE,
        (0.5 - Math.log((1 + s) / (1 - s)) / (4 * Math.PI)) * TILE,
    ];
}

/**
 * Paint the section holding every point into the canvas.
 *
 * @param {HTMLCanvasElement} canvas   with its width and height set
 * @param {Array<[number, number]>} points  [lat, lon] each
 * @param {{pad?: number, maxZoom?: number, radius?: number, font?: number}} options
 * @returns {Promise<void>}  resolved once the picture stands
 */
export function drawStaticMap(canvas, points, options = {}) {
    const pad = options.pad ?? 40;
    const maxZoom = options.maxZoom ?? 16;
    const radius = options.radius ?? 8;
    const fontSize = options.font ?? 20;
    if (!canvas.getContext || points.length === 0) return Promise.resolve();

    const ctx = canvas.getContext('2d');
    const W = canvas.width, H = canvas.height;

    const flat = points.map(p => project(p[0], p[1]));
    const xs = flat.map(q => q[0]), ys = flat.map(q => q[1]);
    const minX = Math.min(...xs), maxX = Math.max(...xs);
    const minY = Math.min(...ys), maxY = Math.max(...ys);

    /* Infinity where a span is zero — one single find, or several on one
       spot, has no extent to fit and simply gets the closest zoom. Every
       zoom is the zoom-0 world times 2^z, which is why the fit can be a
       logarithm instead of a search. */
    const fitX = maxX > minX ? Math.log((W - 2 * pad) / (maxX - minX)) / Math.LN2 : Infinity;
    const fitY = maxY > minY ? Math.log((H - 2 * pad) / (maxY - minY)) / Math.LN2 : Infinity;
    const zoom = Math.max(0, Math.min(maxZoom, Math.floor(Math.min(fitX, fitY))));

    const scale = 2 ** zoom;
    const originX = (minX + maxX) / 2 * scale - W / 2;   // top left corner of the picture
    const originY = (minY + maxY) / 2 * scale - H / 2;

    const tile = (tx, ty, dx, dy) => new Promise((done) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';               // see above: keeps the canvas readable
        img.onload = () => done([img, dx, dy]);
        img.onerror = () => done(null);              // a missing tile stays paper
        img.src = `https://tile.openstreetmap.org/${zoom}/${tx}/${ty}.png`;
    });

    const jobs = [];
    const count = 2 ** zoom;
    for (let ty = Math.floor(originY / TILE); ty * TILE < originY + H; ty++) {
        // Above the north pole and below the south there are no tiles;
        // east and west the world wraps round.
        if (ty < 0 || ty >= count) continue;
        for (let tx = Math.floor(originX / TILE); tx * TILE < originX + W; tx++) {
            jobs.push(tile(((tx % count) + count) % count, ty, tx * TILE - originX, ty * TILE - originY));
        }
    }

    /* ALL TILES FIRST, THEN THE FINDS. Drawn as they arrive, a late tile
       would land on top of the circles and swallow them. */
    return Promise.all(jobs).then((tiles) => {
        for (const t of tiles) if (t) ctx.drawImage(t[0], t[1], t[2], TILE, TILE);

        ctx.lineWidth = Math.max(2, radius * 0.375);
        ctx.strokeStyle = '#b00';
        ctx.fillStyle = 'rgba(255, 51, 51, .85)';
        for (const q of flat) {
            ctx.beginPath();
            ctx.arc(q[0] * scale - originX, q[1] * scale - originY, radius, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
        }

        /* The attribution belongs IN the picture, not underneath it: the
           map may leave the page as an image, and the credit has to go
           with it. That is not politeness, it is the licence. */
        const credit = '© OpenStreetMap-Mitwirkende';
        ctx.font = `500 ${fontSize}px 'Helvetica Neue', Arial, sans-serif`;
        ctx.textBaseline = 'middle';
        const w = ctx.measureText(credit).width;
        const boxH = Math.round(fontSize * 1.7), padX = Math.round(fontSize * 0.55);
        ctx.fillStyle = 'rgba(255, 255, 255, .78)';
        ctx.fillRect(W - w - 2 * padX, H - boxH, w + 2 * padX, boxH);
        ctx.fillStyle = '#5d675f';
        ctx.fillText(credit, W - w - padX, H - boxH / 2);
    });
}

for (const canvas of document.querySelectorAll('canvas[data-points]')) {
    let points = [];
    try { points = JSON.parse(canvas.dataset.points); } catch { points = []; }
    drawStaticMap(canvas, points, {
        pad:     canvas.dataset.pad     ? Number(canvas.dataset.pad)     : undefined,
        maxZoom: canvas.dataset.maxZoom ? Number(canvas.dataset.maxZoom) : undefined,
        radius:  canvas.dataset.radius  ? Number(canvas.dataset.radius)  : undefined,
        font:    canvas.dataset.font    ? Number(canvas.dataset.font)    : undefined,
    }).then(() => {
        const wait = canvas.dataset.wait ? document.getElementById(canvas.dataset.wait) : null;
        if (wait) wait.hidden = true;
    });
}
