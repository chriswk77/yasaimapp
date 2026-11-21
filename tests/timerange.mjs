/**
 * Test of the time filter (assets/js/timerange.js).
 *
 *   node tests/timerange.mjs
 *
 * WHY THIS IS TESTED. The time filter hides points OUT. If it cuts
 * wrongly, half of a trip is missing — and you do not notice, because
 * the result looks plausible after all. Exactly the kind of mistake you
 * only notice when you look for a find and do not find it.
 *
 * HERE ONCE STOOD tests/touren.mjs, testing the gap detection: six
 * hours of pause as the boundary, midnight crossed on purpose. That flew
 * out on 14.08.2026 — a day is a tour, and nothing is guessed any more.
 * What remains from there is the test of the timestamp: Safari did not
 * read "2026-08-10 14:23:11" without the T, and that is the mistake that
 * occurs on exactly one device.
 */

const { dayOf, timestamp, inTimeRange, readableDay, label, collectDays, collectTours,
        othersDayKey, othersDayOf, othersDayHolds, walksOf } =
    await import('../assets/js/timerange.js');
const { autoName, nextName, today, stillRunning, TOUR_GAP_MS } =
    await import('../assets/js/tour.js');

let failed = 0;
function check(name, condition, extra = '') {
    if (condition) return;
    failed++;
    console.log(`  FAILED  ${name}${extra ? ' — ' + extra : ''}`);
}

const p = (found, created = null) => ({ found_at: found, created_at: created });

console.log('\nThe day of a point');
check('from the find time', dayOf(p('2026-08-10 14:23:11')) === '2026-08-10');
check('falling back to the creation time',
    dayOf(p(null, '2026-08-09 07:00:00')) === '2026-08-09');
check('the find time wins',
    dayOf(p('2026-08-10 14:00:00', '2026-08-01 07:00:00')) === '2026-08-10');
check('without either no day', dayOf(p(null, null)) === null);
check('nonsense yields no day', dayOf({ found_at: 'morgen' }) === null);
check('and no point at all yields none either', dayOf(null) === null);

console.log('\nThe moment as a number (for sorting)');
check('read with a space — the Safari case',
    timestamp(p('2026-08-10 14:23:11')) === new Date('2026-08-10T14:23:11').getTime());
check('without a time null', timestamp(p(null)) === null);
check('younger is larger',
    timestamp(p('2026-08-10 09:00:00')) < timestamp(p('2026-08-10 10:00:00')));

console.log('\nIn the time range');
check('without a filter everything is in', inTimeRange('2026-08-10', '', ''));
check('without a day a point drops out as soon as filtering is on',
    !inTimeRange(null, '2026-08-01', ''));
check('without a day and without a filter it stays in', inTimeRange(null, '', ''));
check('exactly that day counts — the "one tour" case',
    inTimeRange('2026-08-10', '2026-08-10', '2026-08-10'));
check('the day before does not', !inTimeRange('2026-08-09', '2026-08-10', '2026-08-10'));
check('the day after does not', !inTimeRange('2026-08-11', '2026-08-10', '2026-08-10'));
check('from', inTimeRange('2026-08-10', '2026-08-01', ''));
check('from, not before it', !inTimeRange('2026-07-31', '2026-08-01', ''));
check('until', inTimeRange('2026-08-10', '', '2026-08-31'));
check('until, not after it', !inTimeRange('2026-09-01', '', '2026-08-31'));
// THE EDGE DAY BELONGS IN. "bis 10.08." means inclusive — otherwise of
// all days the one explicitly meant would vanish.
check('the until-day itself belongs in', inTimeRange('2026-08-31', '', '2026-08-31'));
check('the from-day itself too', inTimeRange('2026-08-01', '2026-08-01', ''));

console.log('\nReadable');
check('weekday and date', readableDay('2026-08-06') === 'DO 06.08.2026',
    readableDay('2026-08-06'));
check('single-digit days with a zero', readableDay('2026-08-03').includes('03.08.'));

console.log('\nThe button label');
const days = ['2026-08-10', '2026-08-06', '2026-07-30'];
check('without a filter "Zeitraum"', label('', '', days) === 'Zeitraum');
check('the youngest day is called "Letzte Tour"',
    label('2026-08-10', '2026-08-10', days) === 'Letzte Tour');
check('the second-youngest "Vorletzte Tour"',
    label('2026-08-06', '2026-08-06', days) === 'Vorletzte Tour');
check('every other day with its weekday',
    label('2026-07-30', '2026-07-30', days).startsWith('DO '),
    label('2026-07-30', '2026-07-30', days));
check('a real range as a span',
    label('2026-08-01', '2026-08-10', days) === '01.08.–10.08.');
check('only from', label('2026-08-01', '', days) === 'ab 01.08.');
check('only until', label('', '2026-08-10', days) === 'bis 10.08.');
// THE YEAR ONLY WHEN IT SAYS SOMETHING: whoever filters this season
// knows which one — four characters per date turned the span into a line
// that wraps.
check('a foreign year is stated',
    label('2019-08-01', '2019-08-10', days) === '01.08.19–10.08.19',
    label('2019-08-01', '2019-08-10', days));

console.log('\nThe day list');
const place = (found, municipality) => ({ found_at: found, municipality });
const points = [
    p('2026-08-10 09:00:00'), p('2026-08-10 15:00:00'),   // the same day
    p('2026-08-06 11:00:00'),
    p(null, '2026-07-30 20:00:00'),                       // only creation time
    p(null, null),                                        // no time at all
];
const list = collectDays(points);
check('every day once', list.length === 3, JSON.stringify(list));
check('youngest first', list[0].tag === '2026-08-10');
check('oldest last', list.at(-1).tag === '2026-07-30');
check('points without any time do not count',
    !list.some((z) => z.tag === null));
check('without points an empty list', collectDays([]).length === 0);
// Organisieren gets the day ready-made from the server and hands it in via its
// own route — that this works is part of this module's promise.
check('the day may also come from outside',
    collectDays([{ tag: '2026-01-01' }], (x) => x.tag)[0].tag === '2026-01-01');

console.log('\nThe municipality stands with its day');
check('one place',
    collectDays([place('2026-08-10 09:00:00', 'Hofheim')])[0].orte === 'Hofheim');
check('two places of the same day, deduplicated',
    collectDays([place('2026-08-10 09:00:00', 'Hofheim'),
                 place('2026-08-10 11:00:00', 'Wiesbaden'),
                 place('2026-08-10 14:00:00', 'Hofheim')])[0].orte === 'Hofheim, Wiesbaden');
// Two at most — in a narrow list you recognize the tour by the first
// name, not by all four.
check('from the third an ellipsis',
    collectDays([place('2026-08-10 09:00:00', 'A'), place('2026-08-10 10:00:00', 'B'),
                 place('2026-08-10 11:00:00', 'C')])[0].orte === 'A, B …');
check('without a municipality it stays empty',
    collectDays([p('2026-08-10 09:00:00')])[0].orte === '');

console.log('\nTours and days in one list');
const t = (found, tourName, municipality = '') =>
    ({ found_at: found, created_at: null, tour: tourName, municipality });
{
    const rows = collectTours([
        t('2026-08-10 09:00:00', 'Wetterau', 'Butzbach'),
        t('2026-08-10 14:00:00', 'Wetterau', 'Nidda'),
        t('2026-08-10 17:00:00', '23.08.2026 (2)'),
        p('2026-08-09 09:00:00'),
    ]);
    check('two named outings on one day stay two rows',
        rows.filter(r => r.tour).length === 2);
    check('a point without a tour keeps its day row',
        rows.some(r => !r.tour && r.tag === '2026-08-09'));
    check('the name is the row', rows.some(r => r.tour === 'Wetterau'));
    check('a tour collects its places like a day does',
        rows.find(r => r.tour === 'Wetterau')?.orte === 'Butzbach, Nidda');
    check('most recent first, tours before the bare day',
        rows[0].tag === '2026-08-10' && !!rows[0].tour);
}

/* GROUPED BY IDENTITY (migration 0079). This is the check the plain name
   „Tagestour" stands or falls on: every day proposes it, so grouping by
   name would throw a whole season into ONE row and picking it would show
   three years of finds. organize.js has grouped this way since the id was
   minted; this side had not, and nobody saw it because the old counter
   gave every outing a different name anyway. */
{
    const withId = (found, tourName, id) =>
        ({ found_at: found, created_at: null, tour: tourName, tour_id: id, municipality: '' });
    const rows = collectTours([
        withId('2026-08-10 09:00:00', 'Tagestour', 'aaaaaaaaaaaa'),
        withId('2026-08-10 11:00:00', 'Tagestour', 'aaaaaaaaaaaa'),
        withId('2026-08-11 09:00:00', 'Tagestour', 'bbbbbbbbbbbb'),
    ]);
    check('two days of the same name are two rows',
        rows.length === 2, JSON.stringify(rows));
    check('the key is the identity, not the word',
        rows.every(r => r.tour.startsWith('t:')));
    check('and both still read „Tagestour"',
        rows.every(r => r.label === 'Tagestour'));
    check('one outing stays one row across its own hours',
        rows.find(r => r.tour === 't:aaaaaaaaaaaa') !== undefined && rows.length === 2);
}

/* WHOSE OUTING (14.09.2026). The friends' tours stand in the list beside
   one's own — their finds come through the shares, and so do their days.
   A row is foreign when EVERY point in it is somebody else's; one own
   point makes the outing mine. */
{
    const of = (found, id, owner = null) =>
        ({ found_at: found, created_at: null, tour: 'Tagestour', tour_id: id, municipality: '',
           ...(owner ? { owner, owner_id: 99 } : {}) });
    // The caller says whose a point is — here: the owner's first name,
    // the way the map hands in the sidebar's short form.
    const ownerOf = (p) => p.owner ? p.owner.split(' ')[0] : null;
    const rows = collectTours([
        of('2026-09-12 09:00:00', 'cccccccccccc', 'Sebastian Schröder'),
        of('2026-09-12 11:00:00', 'cccccccccccc', 'Sebastian Schröder'),
        of('2026-09-11 09:00:00', 'dddddddddddd'),
        of('2026-09-10 09:00:00', 'eeeeeeeeeeee', 'Sebastian Schröder'),
        of('2026-09-10 10:00:00', 'eeeeeeeeeeee'),
    ], undefined, ownerOf);
    check('a tour of foreign points alone names its person, in the form the caller gives',
        rows.find(r => r.tour === 't:cccccccccccc')?.owner === 'Sebastian');
    check('without a caller\'s answer no row names anybody',
        collectTours([of('2026-09-12 09:00:00', 'cccccccccccc', 'Sebastian Schröder')])
            .every(r => r.owner === null));
    check('an own tour names nobody',
        rows.find(r => r.tour === 't:dddddddddddd')?.owner === null);
    check('one own point makes the outing mine',
        rows.find(r => r.tour === 't:eeeeeeeeeeee')?.owner === null);
}

/* AND A DAY WITHOUT A TOUR SAYS WHOSE TOO (26.09.2026). The case:
   his own „Tagestour" of the day, and Finn's find set later without a
   tour — it fell into a bare day row, which named nobody and read as
   „Vorletzte Tour", his own. The same rule as for the tours: foreign
   only when every point of the day is somebody else's. */
{
    const bare = (found, owner = null) =>
        ({ found_at: found, created_at: null, municipality: '',
           ...(owner ? { owner, owner_id: 99 } : {}) });
    const ownerOf = (p) => p.owner ? p.owner.split(' ')[0] : null;
    const rows = collectTours([
        { found_at: '2026-09-26 10:00:00', created_at: null, tour: 'Tagestour',
          tour_id: 'ffffffffffff', municipality: '' },
        bare('2026-09-26 18:00:00', 'Finn Horn'),
    ], undefined, ownerOf);
    // The day row carries a key of its own since the next block — so it
    // is told from the tour by the tour's „t:", not by having no key.
    check('a bare day of foreign points alone names its person',
        rows.find(r => !r.tour?.startsWith('t:'))?.owner === 'Finn', JSON.stringify(rows));
    check('and one\'s own tour of the same day still names nobody',
        rows.find(r => r.tour?.startsWith('t:'))?.owner === null);
    check('one own point makes the bare day mine',
        collectDays([bare('2026-09-20 09:00:00', 'Finn Horn'), bare('2026-09-20 11:00:00')],
            undefined, ownerOf)[0].owner === null);
    check('without a caller\'s answer no day names anybody',
        collectDays([bare('2026-09-20 09:00:00', 'Finn Horn')])[0].owner === null);
    check('two persons stand side by side',
        collectDays([bare('2026-09-20 09:00:00', 'Finn Horn'), bare('2026-09-20 10:00:00', 'Ben Mustermann')],
            undefined, ownerOf)[0].owner === 'Finn, Ben');
    // An excursion day holds a dozen guests — cut like the places.
    check('from the third an ellipsis, as with the places',
        collectDays([bare('2026-09-20 09:00:00', 'Finn Horn'), bare('2026-09-20 10:00:00', 'Ben Mustermann'),
                     bare('2026-09-20 11:00:00', 'Carla Beispiel')], undefined, ownerOf)[0].owner === 'Finn, Ben …');
}

/* AND PICKED, IT SHOWS ONLY THOSE FINDS (26.09.2026: „nur Finns
   Punkte zeigen"). Picked by its date, the row brought the whole day —
   one's own outing included. It carries „d:<day>" now, and the key must
   pick exactly what the row gathered: the tourless finds of others on
   that day. Not one's own, not Finn's named outing of the same day, not
   Finn's tourless finds of another day. */
{
    const ownerOf = (p) => p.owner ? p.owner.split(' ')[0] : null;
    const pt = (id, found, { tour = null, tourId = null, owner = null } = {}) =>
        ({ id, found_at: found, created_at: null, municipality: '', tour, tour_id: tourId,
           ...(owner ? { owner, owner_id: 99 } : {}) });
    const stock = [
        pt(1, '2026-09-26 10:00:00', { tour: 'Tagestour', tourId: 'aaaaaaaaaaaa' }),       // mine, named
        pt(2, '2026-09-26 12:00:00', { tour: 'Tagestour', tourId: 'bbbbbbbbbbbb', owner: 'Finn Horn' }),
        pt(3, '2026-09-26 18:00:00', { owner: 'Finn Horn' }),                              // the row
        pt(4, '2026-09-26 19:00:00', { owner: 'Finn Horn', tour: '  ' }),                  // blank = none
        pt(5, '2026-09-25 18:00:00', { owner: 'Finn Horn' }),                              // another day
        pt(6, '2026-09-24 09:00:00'),                                                      // mine, bare
    ];
    const rows = collectTours(stock, undefined, ownerOf);
    const day = rows.find(r => r.tag === '2026-09-26' && r.owner === 'Finn' && !r.tour?.startsWith('t:'));
    check('somebody else\'s day carries a key of its own',
        day?.tour === 'd:2026-09-26', JSON.stringify(rows));
    check('and reads as its date',
        day?.label === readableDay('2026-09-26'));
    check('one\'s own day keeps picking its date — no key',
        rows.find(r => r.tag === '2026-09-24')?.tour === undefined);
    check('Organisieren\'s days carry no key — it has no tour filter',
        collectDays(stock, undefined, ownerOf).every(r => r.tour === undefined));
    const picked = stock.filter(p => othersDayHolds(p, day?.tour, ownerOf)).map(p => p.id);
    check('the key picks exactly what the row gathered — Finn\'s tourless finds of that day',
        JSON.stringify(picked) === '[3,4]', JSON.stringify(picked));
    check('one\'s own tourless find of that day never belongs to it',
        !othersDayHolds(pt(7, '2026-09-26 20:00:00'), 'd:2026-09-26', ownerOf));
    // A walk has no find time — the caller hands in its start day.
    check('a walk is asked by the day it hands in',
        othersDayHolds({ owner: 'Finn Horn', owner_id: 99, created_at: '2026-09-27 08:00:00' },
            'd:2026-09-26', ownerOf, '2026-09-26'));
    check('the key and its day go both ways',
        othersDayOf(othersDayKey('2026-09-26')) === '2026-09-26');
    check('a tour key or an excursion key is no day of others',
        othersDayOf('t:aaaaaaaaaaaa') === null && othersDayOf('e:12') === null);
    check('neither is a name that merely starts with d:',
        othersDayOf('d:Wetterau') === null);
}
{
    /* WITHOUT AN ID THE NAME IS THE IDENTITY — everything from before
       migration 0079 behaves exactly as it did, and a filter value stored
       yesterday still finds its points. */
    const rows = collectTours([
        { found_at: '2026-08-10 09:00:00', created_at: null, tour: 'Wetterau', municipality: '' },
        { found_at: '2026-08-11 09:00:00', created_at: null, tour: 'Wetterau', municipality: '' },
    ]);
    check('old points without an id still group by their name', rows.length === 1);
    check('and their key IS the name', rows[0].tour === 'Wetterau');
    check('label and key are then the same string', rows[0].label === 'Wetterau');
}
{
    // An id-carrying outing and an old id-less one of the same name are
    // two — which is right: they are two outings, and the day underneath
    // the name tells them apart.
    const rows = collectTours([
        { found_at: '2026-08-10 09:00:00', created_at: null, tour: 'Wetterau', municipality: '' },
        { found_at: '2026-08-11 09:00:00', created_at: null, tour: 'Wetterau',
          tour_id: 'cccccccccccc', municipality: '' },
    ]);
    check('named alike, one with an id and one without: two rows', rows.length === 2);
}
// A blank tour is no tour — trailing spaces from typing must not create a
// second, invisible outing.
check('blank names count as none',
    collectTours([t('2026-08-10 09:00:00', '  ')]).every(r => !r.tour));

console.log('\nWhich walk a find lies on');
/* WHOSE WALK (26.09.2026). A find without a tour was matched to walks by
   the clock alone, so Finn's find at two o'clock lit the maintainer's walk
   from one to three. It lies now on its own person's walks only — and on
   its excursion's walks, since an excursion is one walk for everybody. */
{
    const me = 7;
    const walk = (id, from, to, extra = {}) =>
        ({ client_id: id, started_at: '2026-09-26 ' + from + ':00', ended_at: '2026-09-26 ' + to + ':00', ...extra });
    const mine  = walk('mine', '13:00', '15:00');
    const eriks = walk('erik', '13:30', '16:00', { owner_id: 99 });
    const excursionWalk = walk('exc', '09:00', '12:00', { owner_id: 42, excursion_id: 5 });
    const tourWalk = walk('tour', '06:00', '08:00', { tour_id: 'aaaaaaaaaaaa' });
    const all = [mine, eriks, excursionWalk, tourWalk];
    const ids = (walks) => walks.map(w => w.client_id).join(',');
    const find = (at, extra = {}) => ({ found_at: '2026-09-26 ' + at + ':00', ...extra });

    check('a friend\'s find without a tour lies on his walk, not on mine',
        ids(walksOf(all, find('14:00', { owner_id: 99 }), me)) === 'erik',
        ids(walksOf(all, find('14:00', { owner_id: 99 }), me)));
    check('my own find in the same hour lies on my walk only',
        ids(walksOf(all, find('14:00'), me)) === 'mine');
    check('without a walk of his own it lies on none — not on mine',
        walksOf([mine], find('14:00', { owner_id: 99 }), me).length === 0);
    check('the nearest within half an hour is looked for among his walks',
        ids(walksOf(all, find('16:20', { owner_id: 99 }), me)) === 'erik');
    check('a guest\'s find lies on its excursion\'s walk, whoever recorded it',
        ids(walksOf(all, find('10:00', { owner_id: 3, excursion_id: 5 }), me)) === 'exc');
    check('the same find without the excursion lies on nobody else\'s walk',
        walksOf(all, find('10:00', { owner_id: 3 }), me).length === 0);
    check('a find without a time of day lies on all of its person\'s walks',
        ids(walksOf(all, { found_at: '2026-09-26', owner_id: 99 }, me)) === 'erik');
    check('a find with a tour still lies only on walks of that tour',
        ids(walksOf(all, find('14:00', { tour_id: 'aaaaaaaaaaaa' }), me)) === 'tour');
}

console.log('\nThe automatic tour name');
/* „Tagestour" SINCE 24.08.2026, and no longer the date: every find
   carries its find time, the tour row shows the day beneath the name and
   the filter sorts by it — a name repeating all that stored the same
   datum a second time. The word is a placeholder one overwrites; the
   COUNTER is what now keeps two outings of one day apart, and that makes
   these checks more important than they were. */
check('is the plain word, not the date', autoName('2026-08-23') === 'Tagestour');
check('and it does not depend on the day',
    autoName('2026-08-23') === autoName('2025-01-02'));
check('today is local time, ten characters', /^\d{4}-\d{2}-\d{2}$/.test(today()));
check('midnight stays local — 23:30 is still the same day',
    today(new Date('2026-08-23T23:30:00')) === '2026-08-23');
check('the first of the day gets the bare word',
    nextName('2026-08-23', []) === 'Tagestour');
/* THE NUMBER ONLY FOR THE SECOND OUTING OF ONE DAY
   (27.08.2026): „man hat eine laufende Tour und bricht mittendrin ab mit
   neuer Tour, dann kann das Tagestour #2 sein". */
check('the second of the same day counts up',
    nextName('2026-08-23', ['Tagestour']) === 'Tagestour #2');
check('gaps are not reused blindly — the next free number wins',
    nextName('2026-08-23', ['Tagestour', 'Tagestour #2']) === 'Tagestour #3');
check('a custom name does not block the proposal',
    nextName('2026-08-23', ['Wetterau']) === 'Tagestour');
/* THE COUNTER NO LONGER REACHES ACROSS DAYS, and this check is the whole
   change: yesterday's „Tagestour" must NOT push today's to #2. The caller
   hands in only the day's names (takenToday in map.js) — hand in the whole
   stock and the old behaviour is silently back, which is why the argument
   is called takenToday and why this line stands here. */
check('yesterday`s outing does not number today`s',
    nextName('2026-08-24', []) === 'Tagestour');

console.log('\nDoes the tour still run? (the six-hour rule at midnight)');
// A moth night: begun on the 23rd, the clock now shows half past one.
const night = new Date('2026-08-24T01:30:00');
const at = (iso) => new Date(iso).getTime();
check('on its own day always — even after a long pause',
    stillRunning({ name: 'Pilze', day: '2026-08-23', last: at('2026-08-23T08:00:00') },
        new Date('2026-08-23T22:00:00')));
check('across midnight while the last point is fresh',
    stillRunning({ name: 'Leuchtabend', day: '2026-08-23', last: at('2026-08-23T23:50:00') }, night));
check('exactly six hours still counts',
    stillRunning({ name: 'Leuchtabend', day: '2026-08-23', last: night.getTime() - TOUR_GAP_MS }, night));
check('longer ago than six hours does not',
    !stillRunning({ name: 'Leuchtabend', day: '2026-08-23', last: at('2026-08-23T19:00:00') }, night));
check('a record from before the rule simply ends at midnight',
    !stillRunning({ name: 'Alt', day: '2026-08-23' }, night));
check('a blank name never runs',
    !stillRunning({ name: ' ', day: '2026-08-24', last: night.getTime() }, night));

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
