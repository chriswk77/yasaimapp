/**
 * The searchable text of a point — ONE builder for both views.
 *
 * IT USED TO STAND TWICE: here (as pointHaystack in map.js, for the
 * sidebar) and in PHP (point_haystack in data.php), and Organisieren got
 * the finished text handed down from the server with every point. That
 * third path is gone since 25.08.2026, and the reason was a measurement:
 * the text cost 130 KB of a 786 KB payload — 17 % — while EVERY ONE of
 * its ingredients already travelled in that same payload, because the
 * columns of the table show them. It was not a text, it was a copy.
 *
 * The PHP twin stays: the auto lists match server-side, and there no
 * browser is present to build anything. tests/haystack.php holds the two
 * against each other — whoever adds a field on one side and not the other
 * gets two different search results in two views, and notices it on the
 * day somebody searches in Organisieren for what they found in the
 * sidebar.
 */

import { findNumberFallback } from './findnumber.js';

/**
 * What the text search recognizes a point by.
 *
 * NOT ONLY TITLE AND NOTE, as before. The map sheet belongs in, because
 * "5816" is the most natural way to search for an area — for mapping it is
 * the actual place reference. And the custom fields, because they hold what
 * the find is: substrate, accompanying vegetation, the species name itself.
 * Today they are empty, but they are the reason these fields exist.
 *
 * THE LIST NAME STAYS OUT. It would stand on every point of the list, and
 * "funde" would then find everything from "Meine Funde" — a filter that
 * sorts nothing out is none. Whoever searches by list checks it next door.
 *
 * NOT CACHED, although this runs over every point on every keystroke. The
 * first attempt cached the text on the point and told by updated_at
 * whether it still held — and was wrong with that: on saving, the point is
 * replaced in the state before the server has answered, so updated_at
 * stands still for a moment. The search would have searched the old text
 * during that time. Stringing a few strings together is the wrong place to
 * buy yourself that kind of bug.
 */
export function pointHaystack(point) {
    const parts = [point.title || '', point.note || ''];
    if (point.mtb) parts.push(point.mtb, point.mtb + '/' + (point.mtb_quadrant ?? ''));
    // Municipality and region: the way a human searches for an area.
    // "mittenwald" finds the find, "bay" everything from Bavaria — and both
    // are closer to what someone actually types than a sheet number.
    if (point.municipality) parts.push(point.municipality, point.region ?? '', point.country ?? '');
    // The protected area likewise: "altkönig" finds all finds from the
    // area, and that is exactly the question one asks of a find stock —
    // what did I find there.
    if (point.protected_area) parts.push(point.protected_area);
    // The find number, like over there: a collection number one cannot
    // search for is a label without a hook — without a pattern the „Y-"
    // mark the find shows (findnumber.js).
    // An Aufsammlung is found by its box number („CW-A-2026-03").
    if (point.is_collection) { if (point.collection_number) parts.push(point.collection_number); }
    else if (point.find_number) parts.push(point.find_number);
    else if (point.id > 0) parts.push(findNumberFallback(point.id));
    for (const value of Object.values(point.extra ?? {})) parts.push(String(value));
    // Trimmed like its twin point_haystack() in data.php: title and note
    // always go along, a point without both would otherwise yield a single
    // space instead of the empty text.
    return parts.join(' ').toLowerCase().trim();
}
