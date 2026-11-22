/**
 * The symbol of a picture name — for the rails that list a find's pictures
 * by name and, narrowed down, show only the symbols (13.11.2026:
 * „für die ganzen Labels eindeutige Symbole … dass ich links nur noch eine
 * Symbolleiste habe und dass die linke Sidebar gar nicht mehr so breit
 * werden muss").
 *
 * KEYED BY THE WORD, lower-cased, because the names are the account's
 * vocabulary (photos.php, PHOTO_TITLES_MACRO / _MICRO) and stored as
 * typed: a stock word renamed in a list saved before 14.11.2026 simply
 * has no symbol any more and falls back to its letters, like any word the
 * account added itself. The drawings live in includes/icons.php.
 */
import { icon } from './icons.js';

const SUBJECT_ICONS = {
    'belegfoto':                'photo',
    'hauptmotiv':               'subject-main',
    'kollektion':               'subject-collection',
    'größenvergleich':          'subject-size',
    'habitat':                  'subject-habitat',
    'standort':                 'subject-site',
    'hutoberseite':             'subject-cap-top',
    'unterseite':               'subject-cap-under',
    'schnittbild':              'subject-section',
    'stielbasis':               'subject-stem-base',
    'velum':                    'subject-velum',
    'detail':                   'magnifier',
    'makrochemie':              'subject-chemistry',
    'sporenpulver':             'subject-spore-print',
    'makrochemie spp':          'subject-spore-print-chemistry',
    // The old word until migration 0197 has renamed its pictures (photos.php, PHOTO_TITLES_RENAMED).
    'makrochemie sporenpulver': 'subject-spore-print-chemistry',
    'exsikkat':                 'subject-exsiccate',
    'übersichtsschnitt':        'subject-overview',
    'sporen':                   'spore',
    'konidien':                 'subject-conidia',
    'hymenium':                 'subject-hymenium',
    'basidien':                 'subject-basidia',
    'cheilozystiden':           'subject-cheilocystidia',
    'pleurozystiden':           'subject-pleurocystidia',
    'pileozystiden':            'subject-pileocystidia',
    'kaulozystiden':            'subject-caulocystidia',
    'hds (hutdeckschicht)':     'subject-cuticle',
    'hds':                      'subject-cuticle',
    'asci':                     'subject-asci',
    'paraphysen':               'subject-paraphyses',
    'randhaare':                'subject-marginal-hairs',
    'excipulum':                'subject-excipulum',
    'schnallen':                'subject-clamps',
    // By their place (photos.php, PHOTO_TITLES_MICRO) — one drawing: a
    // monogram would read „SH" for Hymenium and for Hut alike.
    'schnallen hymenium':       'subject-clamps',
    'schnallen hut':            'subject-clamps',
    'schnallen stiel':          'subject-clamps',
    'generative hyphen':        'subject-generative-hyphae',
    'skeletthyphen':            'subject-skeletal-hyphae',
    'bindehyphen':              'subject-binding-hyphae',
    'capillitium':              'subject-capillitium',
    'sklerotium':               'subject-sclerotium',
};

/**
 * The symbol for a picture name, or for one of the rails' own groups.
 * @param {string} name  the name as the rail shows it
 * @param {?string} own  an icon name that wins — the groups that carry no
 *        picture name (Text, Auswertung, Ohne Motivnamen) say theirs, since
 *        their words are translated and no key to rely on
 * @returns {SVGElement}
 */
export function subjectIcon(name, own = null) {
    const known = own ?? SUBJECT_ICONS[String(name ?? '').trim().toLowerCase()];
    if (known) return icon(known);
    return monogram(name);
}

/* A NAME WITHOUT A DRAWING WEARS ITS LETTERS in a rounded square — the
   first two of a single word („Keimporen" → Ke), the initials of the first
   two words otherwise („Sporen KOH" → SK). Drawn here rather than in the
   library: it is no drawing but the name itself. */
function monogram(name) {
    const words = String(name ?? '').trim().split(/\s+/).filter(Boolean);
    const letters = words.length > 1
        ? (words[0][0] + words[1][0])
        : (words[0] ?? '?').slice(0, 2);
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('class', 'icon subject-monogram');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.8');
    const box = document.createElementNS(ns, 'rect');
    for (const [k, v] of Object.entries({ x: 3, y: 3, width: 18, height: 18, rx: 3 })) box.setAttribute(k, String(v));
    const text = document.createElementNS(ns, 'text');
    for (const [k, v] of Object.entries({ x: 12, y: 15.6, 'text-anchor': 'middle', 'font-size': 9, 'font-weight': 600, fill: 'currentColor', stroke: 'none' })) text.setAttribute(k, String(v));
    text.textContent = letters.charAt(0).toUpperCase() + letters.slice(1).toLowerCase();
    svg.append(box, text);
    return svg;
}
