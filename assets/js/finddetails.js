/**
 * A find's details as words — what a plate prints and the point window shows
 * (plates.js asks its host for them; this is the one answer, since
 * 11.10.2026 for the map AND the Fundbrowser).
 *
 * OUT OF map.js, where it stood in the plates' host: the Fundbrowser mounts
 * the plates too, and two copies of „which words does a plate print"
 * would drift apart the first time a field is added. Pure functions of a
 * point — nothing here knows a page.
 */

import { readableDate } from './dates.js';
import { findNumberFallback } from './findnumber.js';
import { CERTAINTY_FIELD } from './identification.js';
import { keyingBlocks } from './findkeying.js';
import { sporePrintRead } from './findtraits.js';

/* THE PLACE AS HE WRITES IT, from the filing path „DE/THU/Arenshausen":
   land and state joined by a bare comma, the municipality after a comma
   and a space — „DE,THU, Arenshausen". One function, because the point
   window's place line and the plate's details say it alike; a path
   without a state keeps what it has. '' stays ''.
   THE ORTSLAGE FOLLOWS after one more comma and a space (0206,
   27.11.2026: „die Ortslage auch an den Fundort hängen … zum Beispiel de
   Hess Wiesbaden, und dann die nähere Ortslage noch dran") — the way his
   own file names always carried it („DE,HE, Rambach, Goldsteintal"). It
   is said, never filed: the folder path stays country/state/municipality. */
export function placeWritten(path, placeName = '') {
    const [country, land, ...place] = path.split('/');
    const written = land === undefined ? country
        : place.length ? `${country},${land}, ${place.join(', ')}` : `${country},${land}`;
    const extra = String(placeName ?? '').trim();
    return extra && written ? `${written}, ${extra}` : written;
}

/**
 * WHEN AND WHERE AS A FILE NAME CARRIES THEM — the twin of cloud_name_head()
 * in includes/cloud_names.php, which names the pictures in the cloud: the
 * day as 2026-08-29, the place as „DE,BAY, Krün" (country and region joined
 * by a bare comma, the municipality after a comma and a space; a part the
 * find does not know left out with its comma). 08.11.2026, for
 * the export's file name: „das Datum … Jahr minus Monat minus Tag. Den Ort
 * … so wie YasaiMap den Ort schon standardmäßig abspeichert". Whoever
 * changes one side changes the other.
 */
export function nameDay(point) {
    return String((point?.found_at || point?.created_at) ?? '').slice(0, 10);
}
export function namePlace(point) {
    const codes = [point?.country, point?.region].map((c) => String(c ?? '').trim().toUpperCase()).filter(Boolean).join(',');
    const town = String(point?.municipality ?? '').trim();
    return codes && town ? `${codes}, ${town}` : codes + town;
}

/**
 * The words of a find: the same ones the point window shows — the plate
 * prints them, it does not interpret them.
 *
 * @param {object} point   a find as the server prepares it (point_prepare)
 * @param {boolean} isFind whether it lies in a species list — only then the
 *                         number it would carry anyway (Y-<id>) is printed
 */
export function findDetails(point, isFind) {
    if (!point) return {};
    const extra = point.extra ?? {};
    const q = point.mtb_quadrant || '';
    return {
        species:    point.title ?? '',
        // An Aufsammlung's number is its box number, never a „Y-" mark.
        number:     point.is_collection ? (point.collection_number ?? '')
            : point.find_number ?? (isFind && point.id > 0 ? findNumberFallback(point.id) : ''),
        date:       point.found_at ? readableDate(point.found_at) : '',
        place:      point.municipality ?? '',
        place_full: placeWritten(point.folder || point.ablage || ''),
        /* THE ORTSLAGE A DETAIL OF ITS OWN, not part of place_full (0206):
           plates go public (the front page shows them), and a name within
           a kilometre tells the spot about as finely as the 64th quadrant.
           Whoever wants it on a plate ticks it; nobody gets it unasked. */
        place_name: point.place_name ?? '',
        mtb:        point.mtb ? point.mtb + (q ? '/' + q : '') : '',
        gps:        point.lat != null ? `${Number(point.lat).toFixed(5)}, ${Number(point.lon).toFixed(5)}` : '',
        elevation:  point.elevation_m != null ? `${point.elevation_m} m` : '',
        finder:     extra['Finder'] ?? '',
        determiner: extra['Bestimmer'] ?? '',
        certainty:  extra[CERTAINTY_FIELD] ?? '',
        amount:     extra['Anzahl / Menge'] ?? '',
        substrate:  extra['Substrat / Wirt'] ?? '',
        vegetation: extra['Begleitvegetation'] ?? '',
        voucher:    extra['Beleg-Nr.'] ?? '',
        /* THE KEYING WAYS for the plate's group „Schlüssel" (findkeying.js,
           keyingBlocks — the taxonomy session delivers them with the find,
           09.11.2026): one's own confirmed ways, one per book. Not a detail
           — detailsRows reads only what d.fields names — but carried here,
           because these words are what the plate and its print draw from. */
        keyings:    keyingBlocks(point),
        // Odour and taste as written; the spore print by its words, its colour beside (findtraits.js).
        odour:      extra['Geruch'] ?? '',
        taste:      extra['Geschmack'] ?? '',
        spore_print: sporePrintRead(extra['Sporenpulverfarbe'])?.words ?? (extra['Sporenpulverfarbe'] ?? ''),
        spore_print_color: sporePrintRead(extra['Sporenpulverfarbe'])?.color ?? '',
        // The find description (find_fields.php — „Bemerkung" until 26.11.2026), in one run like the note.
        remark:     String(extra['Fundbeschreibung'] ?? '').replace(/\s*\n\s*/g, ' ').trim(),
        /* THE NOTE IN ONE RUN: its line breaks become spaces, because a
           detail is one value after its label — a field wraps it at its
           edge like any long value. */
        note:       String(point.note ?? '').replace(/\s*\n\s*/g, ' ').trim(),
    };
}
