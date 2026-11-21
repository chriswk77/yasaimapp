/**
 * THE FUNDBROWSER'S „BILDARCHIV" (02.11.2026: „Mach Variante 1,
 * nenn es Bildarchiv"): the older pictures of one's own and one's friends'
 * picture archives (includes/picture_archives.php) — until now only under
 * Bestimmung → Funde, which whoever has no keys never reaches. The
 * Fundbrowser became their place.
 *
 * A GALLERY, NOT FINDS. An older find is a dated folder of pictures under a
 * species; it is no point and never becomes one, so nothing here offers what
 * a find has — no pencil, no map, no lists, no plate. A tile shows its first
 * picture, the species, day and place and whose archive it is; a click opens
 * the picture window (figurewindow.js, the one Bestimmung uses) with all the
 * find's pictures in its strip.
 *
 * Its own module beside finds.js, which only switches to it: the sidebar's
 * rows here are the archive's (whose, which year), the search word filters
 * by species, place and day, and the tiles are built the first time one
 * turns to the archive — 3,000 of them nobody asked for would be 3,000
 * elements. Every picture comes through pages/old_picture.php, which asks
 * the rights again; no share link reaches the browser.
 */

import { settings } from './settings.js';
import { t } from './i18n.js';
import { iconHtml } from './icons.js';
import { readableDate } from './dates.js';
import { request } from './api.js';
import { toast } from './toast.js';
import { mountFigureWindow } from './figurewindow.js';
import { archiveExporter } from './archiveexport.js';
import { personName } from './names.js';

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/**
 * @param {{box: HTMLElement, base: string}} host the archive's tile area, and the app's base address
 */
export function mountArchive({ box, base }) {
    const api = 'archive_finds.php';
    const picture = (archive, id, size) => `${base}pages/old_picture.php?a=${archive}&f=${encodeURIComponent(id)}&s=${size}`;
    const day = (d) => (d ? readableDate(d).split(' ')[0] : '');
    /* The label on a picture where the file name says what it shows
       („Spore", „cheilo") — not a running number (as the identification module labels its figures). */
    const labelOf = (p) => (p.label && !/\d/.test(p.label) ? p.label : '');

    let finds = null;            // the overview, once fetched
    let loading = null;
    const tiles = [];
    const filter = { who: 'all', year: 'all', ...(structuredClone(settings.get('findsArchiveFilter', null)) ?? {}) };

    const figures = mountFigureWindow(document.getElementById('keys-figure-window'), {
        /* The file's own name says species, day and place already (the
           archives are named so) — the line adds only what it does not:
           what the picture shows, whose archive. The original needs no link
           any more: the window loads it behind the preview by itself, and
           „Exportieren" makes a file of it (13.11.2026). */
        caption: (f) => [f.label && `<strong>${esc(f.label)}</strong>`, esc(f.text), f.owner && esc(t('von %s', personName(f.owner)))]
            .filter(Boolean).join(' · '),
        exportPicture: archiveExporter(),
    });

    const whoOf = (f) => (f.owner === null ? 'own' : 'o:' + f.owner);
    const yearOf = (f) => (f.date ? f.date.slice(0, 4) : 'none');
    const passesWho = (f, who = filter.who) => who === 'all' || whoOf(f) === who;
    const passesYear = (f, year = filter.year) => year === 'all' || yearOf(f) === year;
    const passesWord = (f, word) => word === '' || word.split(/\s+/).every((w) => f.hay.includes(w));

    /** The overview, fetched once — the tiles built with it. */
    function load() {
        loading ??= request(api).then((answer) => {
            finds = answer.finds ?? [];
            for (const f of finds) {
                f.hay = [f.name, f.place, f.owner, day(f.date), f.date].join(' ').toLowerCase();
            }
            // A choice remembered from a visit whose owner or year is gone now.
            if (filter.who !== 'all' && !finds.some((f) => passesWho(f))) filter.who = 'all';
            if (filter.year !== 'all' && !finds.some((f) => passesYear(f))) filter.year = 'all';
            build();
        });
        return loading;
    }

    function build() {
        const fragment = document.createDocumentFragment();
        finds.forEach((f, i) => {
            const tile = document.createElement('div');
            tile.className = 'finds-tile is-archive' + (f.owner === null ? '' : ' is-foreign');
            tile.setAttribute('role', 'button');
            tile.tabIndex = 0;
            tile.dataset.archiveAt = String(i);
            const count = f.pictures > 1 ? `<span class="finds-tile-n">${iconHtml('photo')}${f.pictures}</span>` : '';
            tile.innerHTML = `<span class="finds-tile-image">
                    <img src="${esc(picture(f.archive, f.cover, 'small'))}" alt="" loading="lazy" decoding="async">
                    <span class="finds-tile-missing">${iconHtml('photo')}</span>
                    ${count}
                </span>
                <span class="finds-tile-name"><i>${esc(f.name)}</i></span>
                <span class="finds-tile-meta">${esc([day(f.date) || t('ohne Datum'), f.place].filter(Boolean).join(' · '))}</span>
                ${f.owner === null ? '' : `<span class="finds-tile-owner">${esc(t('von %s', personName(f.owner)))}</span>`}`;
            tiles.push(tile);
            fragment.append(tile);
        });
        box.append(fragment);
    }

    // A picture that does not come keeps its tile, with the quiet sign (as the finds' tiles do).
    box.addEventListener('error', (e) => {
        if (e.target.tagName === 'IMG') e.target.closest('.finds-tile-image')?.classList.add('is-broken');
    }, true);

    /** A find's pictures into the picture window — fetched now, they are not in the overview. */
    async function open(f) {
        let answer;
        try {
            answer = await request(`${api}?archive=${f.archive}&key=${encodeURIComponent(f.key)}&name=${encodeURIComponent(f.name)}`);
        } catch (e) {
            toast(e.message || t('Die Bilder ließen sich nicht laden.'), 'error');
            return;
        }
        figures.open((answer.pictures ?? []).map((p) => ({
            files: [picture(f.archive, p.id, 'large')], thumb: picture(f.archive, p.id, 'small'),
            original: picture(f.archive, p.id, 'full'), label: labelOf(p), text: p.file, owner: f.owner,
            species: f.name, date: f.date, place: f.place, lat: f.lat, lon: f.lon,
            photo: true,
        })));
    }

    box.addEventListener('click', (e) => {
        const tile = e.target.closest('[data-archive-at]');
        if (tile) open(finds[Number(tile.dataset.archiveAt)]);
    });
    box.addEventListener('keydown', (e) => {
        const tile = e.target.closest('[data-archive-at]');
        if (tile && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            open(finds[Number(tile.dataset.archiveAt)]);
        }
    });

    return {
        /** The archive is there to show (fetched and built). */
        get ready() { return finds !== null; },
        load,

        /** Hide what the choice and the word leave out; the count for the bar. */
        show(word) {
            let n = 0;
            let pictures = 0;
            finds.forEach((f, i) => {
                const keep = passesWho(f) && passesYear(f) && passesWord(f, word);
                tiles[i].hidden = !keep;
                if (keep) { n++; pictures += f.pictures; }
            });
            return { n, pictures };
        },

        /** The sidebar's groups for the archive: whose, then which year — counted as the finds' are. */
        sideHtml(word) {
            const byWho = finds.filter((f) => passesYear(f) && passesWord(f, word));
            const byYear = finds.filter((f) => passesWho(f) && passesWord(f, word));
            const owners = new Map();
            for (const f of finds) if (f.owner !== null && !owners.has(whoOf(f))) owners.set(whoOf(f), f.owner);
            const years = new Set(finds.map(yearOf));
            const row = (group, value, label, n, depth = 0) => {
                const active = filter[group] === value;
                return `<button type="button" class="finds-row${active ? ' is-active' : ''}${n === 0 ? ' is-empty' : ''}"
                         data-group="archive-${group}" data-value="${esc(value)}" style="--depth:${depth}" aria-pressed="${active}">
                    <span class="finds-row-name">${esc(label)}</span><span class="finds-row-n">${n}</span>
                </button>`;
            };
            let html = `<div class="list-label">${esc(t('Bildarchiv'))}</div><div class="finds-rows">`;
            html += row('who', 'all', t('Alle'), byWho.length);
            // Whose archive only where there is more than one's own.
            if (owners.size) {
                html += row('who', 'own', t('Eigene'), byWho.filter((f) => f.owner === null).length);
                for (const [key, name] of [...owners].sort((a, b) => a[1].localeCompare(b[1], 'de'))) {
                    html += row('who', key, name, byWho.filter((f) => whoOf(f) === key).length, 1);
                }
            }
            html += `</div><div class="list-label">${esc(t('Jahre'))}</div><div class="finds-rows">`;
            html += row('year', 'all', t('Alle Jahre'), byYear.length);
            for (const y of [...years].filter((y) => y !== 'none').sort((a, b) => b.localeCompare(a))) {
                html += row('year', y, y, byYear.filter((f) => yearOf(f) === y).length);
            }
            if (years.has('none')) html += row('year', 'none', t('Ohne Datum'), byYear.filter((f) => yearOf(f) === 'none').length);
            return html + '</div>';
        },

        /** A click on one of those rows. */
        choose(group, value) {
            filter[group.replace('archive-', '')] = value;
            settings.set('findsArchiveFilter', { ...filter });
        },
    };
}
