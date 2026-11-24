/**
 * „FUNDE" TO A SPECIES — the finds of it with a picture that one may see,
 * as the Fundbrowser's tiles, and beneath them the older pictures of the
 * picture archives (api/v1/species_finds.php, includes/species_finds.php).
 *
 * (28.10.2026: „bearbeitete Fundpunkte und auch die Fundpunkte von Freunden,
 * die diese Art mit Bild haben … Kacheln mit den Bildern … beim
 * Draufklicken in den Fundbrowser"; 01.11.2026 the older pictures.) The
 * server takes the finds from the Fundbrowser's own stock, so a friend's
 * find comes only as far as his share lets it; the tile is the
 * Fundbrowser's tile, and a click opens the find there.
 *
 * ONE PIECE FOR ANY PAGE since 24.11.2026 — the find's „Bestimmung"
 * (identify.js) shows it, and a module that shows a species may show the
 * same instead of drawing it a second time. The page gives the place; this
 * gives the answer, the drawing and the clicks:
 *
 *   const answer = await loadSpeciesFinds(name);     // asked once per name, kept for the visit
 *   panel.innerHTML = speciesFindsHtml(answer, { name, embedded });
 *   wireSpeciesFinds(panel, figures);                 // figures: mountFigureWindow(…)
 *
 * NO api.js, for the reason weblinks.js gives: a GET needs neither the form
 * token nor the queue, and a page without START_DATA must not die of an
 * import. The addresses come from where this file itself lies.
 */

import { t } from './i18n.js';
import { iconHtml } from './icons.js';
import { personName } from './names.js';

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// assets/js/ → the app's root, whatever folder the page itself lies in.
const BASE = new URL('../../', import.meta.url);

const asked = new Map();   // name → Promise<{finds, older}>

/**
 * The finds and the older pictures of a species. Asked once per name and
 * kept for the visit; a failed request is forgotten, so the next look asks
 * again.
 *
 * @param {string} name
 * @returns {Promise<{finds: object[], older: object[]}>}
 */
export function loadSpeciesFinds(name) {
    let answer = asked.get(name);
    if (!answer) {
        answer = fetch(new URL(`api/v1/species_finds.php?${new URLSearchParams({ name })}`, BASE),
            { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(async (response) => {
                const body = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(body.error ?? String(response.status));
                return { finds: body.finds ?? [], older: body.older ?? [] };
            });
        asked.set(name, answer);
        answer.catch(() => asked.delete(name));
    }
    return answer;
}

/** How many there are to look at — the older pictures count by their finds, as the finds do. */
export const speciesFindsCount = ({ finds, older }) => finds.length + older.length;

/**
 * One find as the Fundbrowser draws its tile: the first picture whole, the
 * name, number and day, whose it is.
 *
 * embedded: the page stands in another page's frame — the find opens in the
 * whole tab then, not in the frame. current: the find one came from; its
 * tile is marked, so one sees it is among them.
 */
export function findTile(f, { embedded = false, current = 0 } = {}) {
    const meta = [f.number, f.found_at && new Date(f.found_at.replace(' ', 'T')).toLocaleDateString('de-DE')].filter(Boolean).join(' · ');
    const href = `${new URL('pages/finds.php', BASE).pathname}#fund=${f.id}`;
    return `<a class="finds-tile${f.own ? '' : ' is-foreign'}${f.id === current ? ' is-active' : ''}" href="${esc(href)}"${embedded ? ' target="_top"' : ''}>
        <span class="finds-tile-image"><img src="${esc(f.thumb)}" alt="" loading="lazy" decoding="async"><span class="finds-tile-missing">${iconHtml('photo')}</span>${f.pictures > 1
            ? `<span class="finds-tile-n">${iconHtml('photo')}${f.pictures}</span>` : ''}</span>
        <span class="finds-tile-name">${esc(f.title)}</span>
        <span class="finds-tile-meta">${esc(meta)}</span>
        ${f.own ? '' : `<span class="finds-tile-owner">${esc(t('von %s', personName(f.owner) || t('Unbekannt')))}</span>`}
    </a>`;
}

// A label that is only the running number says nothing on the picture.
const pictureLabel = (f) => f.label && !/\d/.test(f.label) ? f.label : '';

/**
 * „ÄLTERE BILDER": pictures from before the app, out of one's own and one's
 * friends' picture archives through the server (pages/old_picture.php, which
 * asks the rights again). A find there is a dated folder; it stands as a
 * line with its day and place — and whose archive it is, where it is a
 * friend's — and its pictures as small ones beneath, so a click opens the
 * picture window with the find's other pictures in its strip. The word
 * after the running number in the file name („Spore", „cheilo") sits on the
 * picture. A gallery, not finds: nothing here leads into the Fundbrowser.
 */
function olderPicturesHtml(older, species) {
    const day = (d) => d ? new Date(`${d}T12:00`).toLocaleDateString('de-DE') : t('ohne Datum');
    return `<h4 class="list-label">${esc(t('Ältere Bilder'))}</h4>${older.map((find) => {
        const where = [day(find.date), find.place].filter(Boolean).join(' · ');
        const at = (p, s) => new URL(`pages/old_picture.php?a=${find.archive}&f=${encodeURIComponent(p.id)}&s=${s}`, BASE).href;
        const pictures = find.pictures.map((p) => ({
            files: [at(p, 'large')], thumb: at(p, 'small'), original: at(p, 'full'), label: p.label, text: p.file,
            owner: find.owner, date: find.date, place: find.place, lat: find.lat, lon: find.lon, species,
            photo: true,
        }));
        const count = find.pictures.length === 1 ? t('1 Bild') : t('%d Bilder', find.pictures.length);
        return `<div class="species-older-find">
            <p class="species-older-head"><strong>${esc(where)}</strong> <span class="hint-text">· ${esc(find.owner ? `${count} · ${t('von %s', personName(find.owner))}` : count)}</span></p>
            <div class="species-older-pics">${pictures.map((f) => `
                <button type="button" class="species-older-pic" data-picture="${esc(JSON.stringify(f))}" title="${esc(f.text)}">
                    <img src="${esc(f.thumb)}" alt="" loading="lazy">
                    ${pictureLabel(f) ? `<span>${esc(pictureLabel(f))}</span>` : ''}
                </button>`).join('')}</div>
        </div>`;
    }).join('')}`;
}

/**
 * The whole block. Two blocks get a head each; one alone needs none —
 * except the older pictures, whose head says what they are. Without finds
 * but with older pictures, „noch keinen Fund mit Bild" would be read as
 * „nothing here".
 *
 * @param {{finds: object[], older: object[]}} answer
 * @param {{name?: string, embedded?: boolean, current?: number}} [options]
 */
export function speciesFindsHtml({ finds, older }, { name = '', embedded = false, current = 0 } = {}) {
    const tiles = finds.length ? `<div class="species-finds">${finds.map((f) => findTile(f, { embedded, current })).join('')}</div>` : '';
    return older.length
        ? (tiles && `<h4 class="list-label">${esc(t('Funde mit Bild'))}</h4>${tiles}`) + olderPicturesHtml(older, name)
        : tiles || `<p class="hint-text">${esc(t('Zu dieser Art gibt es noch keinen Fund mit Bild. Weder von dir noch von deinen Freunden.'))}</p>`;
}

/** The line under an older picture in the picture window: its word, its file, whose archive. */
export function olderPictureCaption(f) {
    return [pictureLabel(f) && `<strong>${esc(pictureLabel(f))}</strong>`, esc(f.text), f.owner && esc(t('von %s', personName(f.owner)))]
        .filter(Boolean).join(' · ');
}

/**
 * After the drawing: a tile whose picture the cloud does not hand out now
 * stands with a quiet sign, as in the Fundbrowser; and an older picture
 * opens the picture window with its find's pictures in the strip.
 *
 * @param {HTMLElement} panel what speciesFindsHtml() was put into
 * @param {{open: (pictures: object[], at?: number) => void}} [figures] the page's picture window (figurewindow.js)
 */
export function wireSpeciesFinds(panel, figures = null) {
    for (const img of panel.querySelectorAll('.finds-tile-image img')) {
        img.addEventListener('error', () => img.parentElement.classList.add('is-broken'), { once: true });
    }
    if (!figures || panel.dataset.olderWired) return;
    panel.dataset.olderWired = '1';
    panel.addEventListener('click', (e) => {
        const pic = e.target.closest('.species-older-pic');
        if (!pic) return;
        const all = [...pic.parentElement.querySelectorAll('.species-older-pic')];
        figures.open(all.map((b) => JSON.parse(b.dataset.picture)), Math.max(0, all.indexOf(pic)));
    });
}
