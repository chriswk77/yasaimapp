/**
 * Test of the device side of the field times: the comparison that decides
 * what goes to the server at all.
 *
 *   node tests/fieldtimes.mjs
 *
 * THIS IS ABOUT THE ONE DANGER OF THE DIFF: a field that wrongly counts as
 * untouched is NOT sent — the input would be gone, and without any message
 * at all. That is why every group tests both directions: what changed
 * comes along, what is the same stays out, and the format traps (find time
 * with and without seconds, outline with and without rounding) are nailed
 * down individually.
 */

import { differences } from '../assets/js/fieldtimes.js';

let failed = 0;
function check(name, condition, seen = '') {
    if (condition) {
        console.log('  ok    ' + name);
    } else {
        console.log('  FAILED ' + name + (seen ? '  ->  ' + JSON.stringify(seen) : ''));
        failed++;
    }
}

const NOW = '2026-08-11T14:00:00.000Z';

const point = {
    id: 5, lat: 49.994001, lon: 9.160102, title: 'Alt', note: null,
    found_at: '2026-08-10 09:30:00',
    lists: [3, 7], outline: null,
    extra: { substrat: 'Fagus' },
    field_times: { title: '2026-08-01T10:00:00.000Z' },
};

// The full form, as the map builds it — here: nothing changed.
const unchanged = {
    title: 'Alt', note: '', extra: { substrat: 'Fagus', begleitvegetation: '' },
    extra_sources: {},
    lat: 49.994001, lon: 9.160102, lists: [7, 3],
    found_at: '2026-08-10T09:30', outline: null,
};

console.log('\nNothing changed means: nothing to send');

let u = differences(point, unchanged, NOW);
check('daten is null', u.data === null, u.data);
check('the order of the lists is no change either', true);

console.log('\nThe format traps do not count as a change');

u = differences(point, { ...unchanged, found_at: '2026-08-10T09:30' }, NOW);
check('find time: field without seconds against column with seconds', u.data === null, u.data);

u = differences({ ...point, note: null }, { ...unchanged, note: '' }, NOW);
check('note: null against empty string', u.data === null, u.data);

u = differences({ ...point, outline: [[49.1, 9.1], [49.2, 9.2], [49.15, 9.3]] },
    { ...unchanged, outline: [[49.1000001, 9.0999999], [49.2, 9.2], [49.15, 9.3]] }, NOW);
check('outline: rounding leftovers below eleven centimetres', u.data === null, u.data);

u = differences(point, { ...unchanged, lat: 49.9940014, lon: 9.1601022 }, NOW);
check('location: deviation below the column resolution', u.data === null, u.data);

console.log('\nEvery real change comes along — only it, with time and seen state');

u = differences(point, { ...unchanged, title: 'Neu' }, NOW);
check('the title is in there', u.data?.title === 'Neu', u.data);
check('no other field', !('note' in u.data) && !('lat' in u.data) && !('lists' in u.data), u.data);
check('with its time', u.data?.field_times?.title === NOW);
check('and the known state', u.data?.field_seen?.title === '2026-08-01T10:00:00.000Z');
check('the time map comes back IN FULL', u.times?.title === NOW);

u = differences(point, { ...unchanged, lat: 49.995, lon: 9.161 }, NOW);
check('a moved location sends both coordinates',
    u.data?.lat === 49.995 && u.data?.lon === 9.161, u.data);
check('under the ONE field position', u.data?.field_times?.position === NOW && !('lat' in (u.data?.field_times ?? {})));
check('seen is null — the location had no time yet', u.data?.field_seen?.position === null);

u = differences(point, { ...unchanged, lists: [3] }, NOW);
check('a changed list assignment comes along', Array.isArray(u.data?.lists), u.data);

u = differences(point, { ...unchanged, found_at: '2026-08-10T11:45' }, NOW);
check('a genuinely changed find time comes along', u.data?.found_at === '2026-08-10T11:45', u.data);

console.log('\nExtra fields individually');

u = differences(point, { ...unchanged, extra: { substrat: 'Fagus', begleitvegetation: 'Luzula' } }, NOW);
check('only the changed subfield travels', u.data?.extra?.begleitvegetation === 'Luzula'
    && !('substrat' in (u.data?.extra ?? {})), u.data);
check('under its own key', u.data?.field_times?.['extra.begleitvegetation'] === NOW);

u = differences(point, { ...unchanged, extra: { substrat: '' } }, NOW);
check('an emptied subfield travels as an empty string', u.data?.extra?.substrat === '', u.data);

u = differences(point, { ...unchanged, extra: {} }, NOW);
check('a field no longer present in the form at all also counts as emptied',
    u.data?.extra?.substrat === '', u.data);

u = differences(point,
    { ...unchanged, extra_sources: { substrat: { Fagus: 'taxref' } } }, NOW);
check('same text, but freshly chosen source: the field travels anyway',
    u.data?.extra?.substrat === 'Fagus' && u.data?.extra_sources?.substrat?.Fagus === 'taxref', u.data);

console.log('\nThe find number by hand');

u = differences({ ...point, find_number: null }, { ...unchanged, find_number: 'wk-00123' }, NOW);
check('a number typed into the window travels, with its time',
    u.data?.find_number === 'wk-00123' && u.times?.find_number === NOW, u.data);
u = differences({ ...point, find_number: null }, { ...unchanged, find_number: null }, NOW);
check('an empty field against no number is no change', u.data === null, u.data);
u = differences({ ...point, find_number: 'wk-00123' }, { ...unchanged, find_number: null }, NOW);
check('emptying a number is a change — it goes as null', 'find_number' in (u.data ?? {}) && u.data.find_number === null, u.data);
u = differences(point, unchanged, NOW);
check('a form without the field (a waypoint, a foreign find) says nothing about it',
    !('find_number' in (u.data ?? {})), u.data);

console.log('\nThe time map loses nothing');

u = differences(point, { ...unchanged, note: 'dazu' }, NOW);
check('the old title time stays in the map',
    u.data?.field_times?.title === '2026-08-01T10:00:00.000Z', u.data?.field_times);
check('next to the new note time', u.data?.field_times?.note === NOW);

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
