/**
 * „Richtung zeigen" — which way a find lies from here, and how far.
 *
 * THE IDEA OF 29.09.2026, now that the location mark shows where
 * one looks: a „navigation in quotation marks". No route, no turn-by-turn —
 * only the direction. While the find lies off the screen, an arrow sits
 * inside the edge where the line from here to the find leaves the screen,
 * and wanders along the edge as one walks; once the find is on the screen,
 * the arrow goes and the find wears a ring. Beside the arrow, and under the
 * ring, a small badge carries the distance in a straight line and the ✕
 * that ends it.
 *
 * NO NAME, AND NOTHING AT THE TOP (his corrections to the first two
 * drafts): the top of a phone's map already holds the friendship requests,
 * the messages and the offline badge, and a name plus a bar there was too
 * much. The distance stays short for the same reason — see readable().
 *
 * THE MAP STAYS NORTH-UP. Turning it with the view was asked and set
 * aside: the tiles carry their labels baked in, and facing south every
 * name would stand on its head. The cone says where one looks, the arrow
 * where the find lies, and the two together are the whole answer.
 *
 * ITS OWN MODULE, as map.js is split up piece by piece: the map hands in
 * where one stands (origin), the rest lives here. Remembered in the
 * settings bundle, so a page Android cleared away in the woods comes back
 * pointing at the same find.
 */

import { t } from './i18n.js';
import { iconHtml } from './icons.js';
import { settings } from './settings.js';

const EDGE_INSET = 14;    // px the arrow's tip keeps from the screen's edge
const BAR_GAP    = 8;     // px it keeps from a row of buttons above or below
const ARROW_HALF = 15;    // half the arrow's box (map.css: 30 px)
const BADGE_GAP  = 6;     // px between arrow and badge
const BADGE_KEEP = 4;     // px a badge keeps from the screen's edge

/**
 * A distance the way a badge can carry it: under a kilometre in metres —
 * to the metre below 100, to ten above —, then in kilometres with one
 * decimal, from ten kilometres on whole ones. So it never grows wider than
 * „990 m".
 */
export function readable(metres) {
    const m = metres < 100 ? Math.round(metres) : Math.round(metres / 10) * 10;
    if (m < 1000) return `${m} m`;
    if (metres < 9950) {
        return (metres / 1000).toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' km';
    }
    return Math.round(metres / 1000).toLocaleString('de-DE') + ' km';
}

/** The badge: the distance, and the ✕ that ends it all. */
function badge(onClose) {
    const el = document.createElement('div');
    el.className = 'direction-badge';
    const far = document.createElement('span');
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'direction-close';
    close.setAttribute('aria-label', t('Richtung beenden'));
    close.innerHTML = iconHtml('close');
    close.addEventListener('click', (e) => { e.stopPropagation(); onClose(); });
    el.append(far, close);
    // A tap here must never reach the map: in „Punkt setzen" it would set one.
    L.DomEvent.disableClickPropagation(el);
    return { el, far };
}

/**
 * @param {L.Map} map
 * @param {{ origin: () => ?L.LatLng, bars: () => {top: number, bottom: number} }} hooks
 *        where one stands, if known; how far the rows of buttons at the top
 *        and the bottom of the map reach into it, in px
 */
export function createDirection(map, { origin, bars }) {
    let target = null;          // { id, lat, lon }
    let ring = null;            // the marker at the find: ring and badge
    let ringBadge = null;
    let frame = null;
    /* THE FRAME THE ARROW WALKS IN keeps clear of the button rows (measured
       29.09.2026 on a phone: at the bottom edge the arrow lay under the
       tools and the attribution, the badge on the location button). Asked
       of the map when the direction starts and when the map changes size —
       the rows do not move in between, and a reading per frame would cost
       a layout each time. */
    let clear = { top: EDGE_INSET, bottom: EDGE_INSET };
    const measure = () => {
        const b = bars();
        clear = { top: Math.max(EDGE_INSET, b.top + BAR_GAP), bottom: Math.max(EDGE_INSET, b.bottom + BAR_GAP) };
    };

    const overlay = document.createElement('div');
    overlay.className = 'direction-overlay';
    overlay.hidden = true;
    const arrow = document.createElement('span');
    arrow.className = 'direction-arrow';
    arrow.setAttribute('aria-hidden', 'true');
    const edgeBadge = badge(() => stop());
    overlay.append(arrow, edgeBadge.el);
    map.getContainer().append(overlay);

    function drawSoon() {
        if (frame === null) frame = requestAnimationFrame(draw);
    }

    function draw() {
        frame = null;
        if (target === null) return;

        const here = origin();
        const where = L.latLng(target.lat, target.lon);
        const text = here ? readable(here.distanceTo(where)) : '';
        for (const b of [edgeBadge, ringBadge]) {
            if (!b) continue;
            b.far.textContent = text;
            b.far.hidden = text === '';
        }

        const size = map.getSize();
        const p = map.latLngToContainerPoint(where);
        const inside = p.x >= EDGE_INSET && p.x <= size.x - EDGE_INSET
                    && p.y >= clear.top && p.y <= size.y - clear.bottom;
        overlay.hidden = inside;
        /* The badge under the ring, unless that would put it into the
           bottom button row — then above the ring (measured: under it, it
           slid behind the location button). */
        if (ringBadge) {
            const tall = ringBadge.el.offsetHeight || 31;
            ringBadge.el.classList.toggle('is-over', p.y + 21 + tall > size.y - clear.bottom);
        }
        if (inside) return;

        /* FROM WHERE ONE STANDS, if that is on the screen; otherwise from
           the middle of it — whoever pushed the map elsewhere looks from
           there. The ray leaves the inset frame at the nearer of its two
           crossings. */
        let o = here ? map.latLngToContainerPoint(here) : null;
        if (!o || o.x < 0 || o.y < 0 || o.x > size.x || o.y > size.y) o = L.point(size.x / 2, size.y / 2);
        const dx = p.x - o.x, dy = p.y - o.y;
        const length = Math.hypot(dx, dy) || 1;
        const ux = dx / length, uy = dy / length;
        const tx = dx > 0 ? (size.x - EDGE_INSET - o.x) / dx : dx < 0 ? (EDGE_INSET - o.x) / dx : Infinity;
        const ty = dy > 0 ? (size.y - clear.bottom - o.y) / dy : dy < 0 ? (clear.top - o.y) / dy : Infinity;
        const reach = Math.max(0, Math.min(tx, ty));
        // The tip on the frame, the arrow's centre half a box further in.
        const cx = o.x + dx * reach - ux * ARROW_HALF;
        const cy = o.y + dy * reach - uy * ARROW_HALF;
        const angle = Math.atan2(dy, dx) * 180 / Math.PI;
        arrow.style.transform = `translate(${cx - ARROW_HALF}px, ${cy - ARROW_HALF}px) rotate(${angle.toFixed(1)}deg)`;

        /* THE BADGE INWARD OF THE ARROW, so it never hangs over the edge:
           as far along the line as its own extent in that direction asks,
           then held inside the screen. */
        const bw = edgeBadge.el.offsetWidth, bh = edgeBadge.el.offsetHeight;
        const back = ARROW_HALF + BADGE_GAP + Math.abs(ux) * bw / 2 + Math.abs(uy) * bh / 2;
        const bx = Math.min(Math.max(cx - ux * back - bw / 2, BADGE_KEEP), size.x - bw - BADGE_KEEP);
        const by = Math.min(Math.max(cy - uy * back - bh / 2, clear.top), size.y - bh - clear.bottom);
        edgeBadge.el.style.transform = `translate(${bx}px, ${by}px)`;
    }

    function start(find) {
        stop(false);
        target = { id: find.id, lat: Number(find.lat), lon: Number(find.lon) };
        settings.set('direction', target);

        ringBadge = badge(() => stop());
        ringBadge.el.classList.add('is-under');
        const html = document.createElement('div');
        html.className = 'direction-mark';
        const circle = document.createElement('span');
        circle.className = 'direction-ring';
        html.append(circle, ringBadge.el);
        ring = L.marker([target.lat, target.lon], {
            icon: L.divIcon({ className: '', html, iconSize: [34, 34], iconAnchor: [17, 17] }),
            interactive: false,
            keyboard: false,
            zIndexOffset: 1000,
        }).addTo(map);
        measure();
        drawSoon();
    }

    function stop(forget = true) {
        target = null;
        overlay.hidden = true;
        if (ring) { map.removeLayer(ring); ring = null; ringBadge = null; }
        if (forget) settings.set('direction', null);
    }

    map.on('move zoom viewreset', drawSoon);
    map.on('resize', () => { measure(); drawSoon(); });

    return {
        start,
        stop,
        /** Where one stands has changed — the arrow and the distance follow. */
        redraw: drawSoon,
        /** The remembered find, or null. */
        remembered: () => settings.get('direction', null),
    };
}
