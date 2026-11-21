/**
 * One recipient's filter on a shared list — the sidebar's points filter,
 * the same groups and the same building blocks from filter.js
 * (08.10.2026: „den gleichen Filter wie unser Filter bei Punkten … viel
 * komplexer, viel besser aufgebaut").
 *
 * WHAT IS STORED is the auto lists' filter shape, which the server already
 * reads (autolist_filter_read in data.php) plus the identification group
 * (list_share_filter_read in list_shares.php). The server evaluates it —
 * not this page, not the recipient's device.
 *
 * TWO DIFFERENCES TO THE SIDEBAR, both because a share is not a view:
 *   - the time is two dates and „ohne Fundzeit", without tours and days —
 *     a tour is the owner's own outing, and „letzte Tour" would move;
 *   - areas are not drawn here (the window lies over the map): they are
 *     taken over from the sidebar's filter, where drawing lives.
 *
 * WHAT CAN BE CHOSEN comes from the list's own points (regions,
 * municipalities, sheets, ranks): offered is only what the list holds.
 */

import { t } from './i18n.js';
import * as filter from './filter.js';

const win = document.getElementById('recipient-filter');
const $ = (id) => document.getElementById(id);

let state = null;
let points = [];
let findFields = [];
let sidebarAreas = () => [];
let done = null;

/** What map.js hands in once: the sidebar's areas and the find fields. */
export function configure(options) {
    findFields = options.findFields ?? [];
    sidebarAreas = options.sidebarAreas ?? (() => []);
}

/** A stored filter (or null) as the drawers' state. */
function toState(f) {
    f = f ?? {};
    const taxonomy = {};
    for (const [key] of filter.TAXONOMY_RANKS) taxonomy[key] = String(f.taxonomy?.[key] ?? '');
    return {
        terms: filter.readTerms(f.terms ?? []),
        from: f.from ?? '',
        until: f.until ?? '',
        undated: !!f.undated,
        areas: filter.readAreas(f.areas ?? []),
        regions: [...(f.regions ?? [])],
        municipalities: [...(f.municipalities ?? [])],
        mtbText: Array.isArray(f.mtb) ? f.mtb.join(', ') : String(f.mtb ?? ''),
        elevation: { from: Number(f.elevation_from ?? 0), to: Number(f.elevation_to ?? filter.ELEVATION_MAX) },
        identification: {
            open: !!f.identification?.open,
            marks: [...(f.identification?.marks ?? [])],
            certainty: [...(f.identification?.certainty ?? [])],
            worked: f.identification?.worked ?? '',
        },
        taxonomy,
        rl: [...(f.rl ?? [])],
        edibility: [...(f.edibility ?? [])],
        responsibility: !!f.responsibility,
    };
}

/** The drawers' state as the stored shape — null when nothing is set. */
function toFilter(s) {
    const f = {};
    const terms = filter.readTerms(s.terms);
    if (terms.length) f.terms = terms;
    if (s.undated) f.undated = true;
    else {
        if (s.from) f.from = s.from;
        if (s.until) f.until = s.until;
    }
    if (s.areas.length) f.areas = s.areas;
    if (s.regions.length) f.regions = s.regions;
    if (s.municipalities.length) f.municipalities = s.municipalities;
    if (s.mtbText.trim()) f.mtb = s.mtbText;
    if (s.elevation.from > 0 || s.elevation.to < filter.ELEVATION_MAX) {
        f.elevation_from = s.elevation.from;
        f.elevation_to = s.elevation.to;
    }
    // Only the ranks — drawTaxonomy keeps helpers of its own in the object.
    const taxonomy = Object.fromEntries(filter.TAXONOMY_RANKS
        .map(([key]) => [key, s.taxonomy[key]]).filter(([, v]) => typeof v === 'string' && v !== ''));
    if (Object.keys(taxonomy).length) f.taxonomy = taxonomy;
    if (s.rl.length) f.rl = s.rl;
    if (s.edibility.length) f.edibility = s.edibility;
    if (s.responsibility) f.responsibility = true;
    const id = s.identification;
    if (id.open || id.marks.length || id.certainty.length || id.worked) f.identification = { ...id };
    return Object.keys(f).length ? f : null;
}

/**
 * The filter in one line — the twin of list_share_filter_readable() in
 * list_shares.php, for a filter not yet saved. Null = no filter.
 */
export function readable(f) {
    if (!f) return null;
    const s = toState(f);
    const parts = [];
    for (const z of s.terms) {
        parts.push(z.type === 'contains' ? t('Suchwort „%s“', z.text) : t('ohne Suchwort „%s“', z.text));
    }
    const day = (d) => d.split('-').reverse().join('.');
    if (s.undated) parts.push(t('ohne Fundzeit'));
    else if (s.from && s.until) parts.push(t('%s bis %s', day(s.from), day(s.until)));
    else if (s.from) parts.push(t('ab %s', day(s.from)));
    else if (s.until) parts.push(t('bis %s', day(s.until)));
    if (s.areas.length) parts.push(s.areas.length === 1 ? t('ein Gebiet') : t('%d Gebiete', s.areas.length));
    parts.push(...s.municipalities, ...s.regions);
    if (s.mtbText.trim()) parts.push(t('MTB %s', s.mtbText.trim()));
    if (s.elevation.from > 0 || s.elevation.to < filter.ELEVATION_MAX) {
        parts.push(t('%d bis %d m', s.elevation.from, s.elevation.to));
    }
    parts.push(...filter.TAXONOMY_RANKS.map(([key]) => s.taxonomy[key]).filter((v) => typeof v === 'string' && v !== ''));
    if (s.rl.length) parts.push(t('Rote Liste %s', s.rl.join(', ')));
    if (s.edibility.length || s.responsibility) parts.push(t('Speisewert'));
    const id = s.identification;
    if (id.open || id.marks.length || id.certainty.length || id.worked) parts.push(t('Bestimmung'));
    return parts.join(' · ');
}

/**
 * Open the window. Resolves with the new filter (null = none), or with
 * undefined when it was cancelled.
 */
export function edit({ title, value, listPoints }) {
    state = toState(value);
    points = listPoints ?? [];
    $('recipient-filter-heading').textContent = title;
    drawAll();
    win.showModal();
    return new Promise((resolve) => { done = resolve; });
}

function finish(result) {
    const resolve = done;
    done = null;
    win.close();
    resolve?.(result);
}

function drawAll() {
    filter.drawTermRows($('sf-terms'), state.terms, () => {}, t('Suchwort …'));
    $('sf-from').value = state.from;
    $('sf-until').value = state.until;
    $('sf-undated').checked = state.undated;
    drawAreas();
    drawRegions();
    filter.drawMunicipalities($('sf-municipalities'), {
        available: () => filter.municipalitiesOf(points),
        chosen: state.municipalities,
        onChange: () => drawAll(),
    });
    filter.drawMtb($('sf-mtb'), {
        get: () => state.mtbText,
        available: () => filter.mtbOf(points),
        onChange: (value) => { state.mtbText = value; },
    });
    filter.drawElevation($('sf-elevation'), state.elevation, () => {});
    filter.drawIdentification($('sf-identification'), state.identification, () => {}, findFields);
    drawTaxonomy();
    filter.drawRl($('sf-rl'), state.rl, () => {});
    filter.drawResponsibility($('sf-responsibility'), state.responsibility, (on) => { state.responsibility = on; });
    filter.drawEdibility($('sf-edibility'), state.edibility, () => {});
}

function drawAreas() {
    filter.drawAreas($('sf-areas'), state.areas, () => drawAreas(), null);
    $('sf-areas-take').hidden = sidebarAreas().length === 0;
}

let regionsOpen = false;
function drawRegions() {
    filter.drawRegions($('sf-regions'), {
        available: () => filter.regionsOf(points),
        chosen: state.regions,
        open: regionsOpen || state.regions.length > 0,
        onFold: (open) => { regionsOpen = open; drawRegions(); },
        onChange: () => drawRegions(),
    });
}

function drawTaxonomy() {
    const offers = (rank, others) => {
        const values = new Set();
        for (const p of points) {
            const ranks = p.taxonomy;
            if (!ranks || !ranks[rank] || !filter.taxonomyMatches(others, ranks)) continue;
            values.add(ranks[rank]);
        }
        return [...values].sort((a, b) => a.localeCompare(b, 'de'));
    };
    filter.drawTaxonomy($('sf-taxonomy'), state.taxonomy, offers, () => drawTaxonomy());
}

$('sf-from').addEventListener('change', (e) => { state.from = e.target.value; });
$('sf-until').addEventListener('change', (e) => { state.until = e.target.value; });
$('sf-undated').addEventListener('change', (e) => { state.undated = e.target.checked; });
$('sf-areas-take').addEventListener('click', () => {
    for (const corners of sidebarAreas()) state.areas.push(corners);
    drawAreas();
});
$('sf-clear').addEventListener('click', () => {
    state = toState(null);
    drawAll();
});
$('sf-cancel').addEventListener('click', () => finish(undefined));
win.addEventListener('cancel', (e) => { e.preventDefault(); finish(undefined); });
win.querySelector('form').addEventListener('submit', (e) => {
    e.preventDefault();
    finish(toFilter(state));
});
