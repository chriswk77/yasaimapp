/**
 * Tests the find-number BROWSER TWIN (assets/js/findnumber.js).
 *
 *   node tests/findnumber.mjs
 *
 * THE ORIGINAL LIVES ON THE SERVER, and tests/find_number.php holds it to
 * these very cases: the pattern arithmetic here mirrors
 * find_number_render(), the normalization mirrors the digit-run rule of
 * find_number_settings_save(). If a case changes on one side, it changes
 * on the other and in BOTH tests — a preview that promises something the
 * minting will not do is worse than none.
 */

import { renderFindNumber, normalizePattern, periodUnit, periodKey, findNumberFallback, findNumberReserved,
         readFindNumber, normalizeFindNumber, numberDate, nextFreeNumber, findNumberKey } from '../assets/js/findnumber.js';

let failed = 0;
function check(b, what) {
    if (b) { console.log(`  ok    ${what}`); } else { failed++; console.log(`  FAILED: ${what}`); }
}

// The same fixed find time as over there: 2026-08-14 09:20.
const when = new Date(2026, 7, 14, 9, 20, 0);

console.log('\nThe pattern arithmetic (twin of find_number_render):');
check(renderFindNumber('CW-JJJJ-NNNN', when, 7) === 'CW-2026-0007',
    'prefix, find year and padded counter');
check(renderFindNumber('JJ/MM/TT-NN', new Date(2026, 7, 4), 3) === '26/08/04-03',
    'short year, month and day come from the find time');
check(renderFindNumber('JJJJNNNN', when, 12) === '20260012',
    'year glued to counter — neither eats the other');
check(renderFindNumber('NN', when, 100) === '100',
    'the counter grows past its digits instead of being cut');
check(renderFindNumber('N-NNN-N', when, 5) === 'N-005-N',
    'only the longest N-run is the counter, the others stay literal');
check(renderFindNumber('WK JJJJ NNNN', new Date(2026, 7, 23), 5) === 'WK 2026 0005',
    'spaces are ordinary pattern text');

console.log('\nThe normalization (twin of the digit-run rule):');
let n = normalizePattern('wk-JJJJ-MM-TT-00000543');
check(n !== null && n.pattern === 'wk-JJJJ-MM-TT-NNNNNNNN' && n.next === 544,
    'a typed number becomes N-places, and the NEXT find counts on past it');
n = normalizePattern('Revier7-NNNN');
check(n !== null && n.pattern === 'Revier7-NNNN' && n.next === null,
    'with N present, digits stay literal text — the 7 belongs to the name');
check(normalizePattern('gar kein Zaehler hier') === null,
    'without N and without digits there is no counter');
n = normalizePattern('  CW-NNNN  ');
check(n !== null && n.pattern === 'CW-NNNN',
    'the edges are trimmed, like on the server');
n = normalizePattern('');
check(n !== null && n.pattern === '' && n.next === null,
    'empty is valid — the database id speaks then');

console.log('\nThe date section (twin of find_number_period):');
check(periodUnit('A-JJJJ-MM-TT-NNN') === 'Tag', 'year, month and day: the day');
check(periodUnit('A-JJJJ-MM-NNN') === 'Monat', 'month without day: the month');
check(periodUnit('A-JJ-NNN') === 'Jahr', 'the two-digit year counts as a year');
check(periodUnit('A-NNN') === null, 'without a date part there is no section');
check(periodUnit('A-TT-NNN') === null, 'a day alone identifies no day');
check(periodUnit('A-MM-TT-NNN') === null, 'month and day without a year collide across years');
check(periodUnit('A-JJJJ-TT-NNN') === 'Jahr', 'a day without a month falls back to the year');
// The key half, the same cases and the same day as over there (23.08.2026).
const day = new Date(2026, 7, 23, 10, 0, 0);
check(periodKey('A-JJJJ-MM-TT-NNN', day) === '2026-08-23' && periodKey('A-JJJJ-MM-NNN', day) === '2026-08'
    && periodKey('A-JJ-NNN', day) === '2026' && periodKey('A-JJJJ-TT-NNN', day) === '2026',
    'the section key of a day, a month, a year — the key find_number_period stores');
check(periodKey('A-NNN', day) === null && periodKey('A-TT-NNN', day) === null,
    'and none without a year');

console.log('\nA number typed by hand (twin of find_number_normalize, 26.09.2026):');
check(normalizeFindNumber('wk-NNNNN', 'wk-01') === 'wk-00001' && normalizeFindNumber('wk-NNNNN', 'WK-00001') === 'wk-00001',
    'the series\' own spelling: the pattern\'s text, the counter padded');
check(normalizeFindNumber('wk-NNNNN', 'wk-1234567') === 'wk-1234567', 'padded, never cut');
check(normalizeFindNumber('wk-NNNNN', 'HM-0543') === 'HM-0543' && readFindNumber('wk-NNNNN', 'HM-0543') === null,
    'another shape is a series of its own and stays as typed');
check(normalizeFindNumber('CW-JJJJ-NNN', 'cw-2024-12') === 'CW-2024-012' && normalizeFindNumber('JJMMTT-NN', '260925-3') === '260925-03',
    'the date digits as typed, the counter padded');
check(normalizeFindNumber('Fund (NNN).JJ', 'fund (7).26') === 'Fund (007).26', 'brackets and dots are text, not rules');
const read = readFindNumber('JJMMTT-NN', '260924-05');
check(read?.n === 5 && numberDate(read.parts).toDateString() === new Date(2026, 8, 24).toDateString(),
    'which number of the series, and the day it names');

console.log('\nThe next free number (twin of the mint\'s choice):');
const daily = { pattern: 'JJMMTT-NN', counter: 3, restart: true, period: '2026-09-25' };
const givenDaily = ['260925-01', '260925-02', '260924-01', '260924-02', '260924-05'];
check(nextFreeNumber(daily, givenDaily, new Date(2026, 8, 25)).text === '260925-03',
    'today: the counter of the running section');
check(nextFreeNumber(daily, givenDaily, new Date(2026, 8, 24)).text === '260924-06',
    'a day past: above the highest number that day holds');
check(nextFreeNumber(daily, givenDaily, new Date(2026, 8, 23)).text === '260923-01', 'an empty day begins at 1');
check(nextFreeNumber({ pattern: 'wk-NNNNN', counter: 555, restart: false, period: null }, ['wk-00554', 'wk-00600'], new Date()).text === 'wk-00601',
    'without a restart: above the highest of the whole series');

console.log('\nOne number, any spelling (twin of find_number_key):');
check(findNumberKey('wk-NNNNN', 'WK-1') === 'wk-00001' && findNumberKey('wk-NNNNN', 'wk-01') === 'wk-00001'
    && findNumberKey('wk-NNNNN', ' wk-00001 ') === 'wk-00001', '„WK-1", „wk-01" and „wk-00001" are one number');
check(findNumberKey('wk-NNNNN', 'HM-0543') === 'hm-0543' && findNumberKey(null, ' WK-1 ') === 'wk-1',
    'another shape, or no pattern: its letters, lower-cased');
check(findNumberKey('JJMMTT-NN', '190824-1') === '190824-01', 'a dated series pads its counter too');

console.log('\nThe mark without a pattern (twin of find_number_fallback):');
check(findNumberFallback(100) === 'Y-100', 'a find without a number reads „Y-100"');
check(findNumberReserved('Y-NNN') && findNumberReserved('  y-NNN') && !findNumberReserved('CW-Y-NNN')
    && !findNumberReserved('YNNN'), 'the mark counts at the start, whatever the case — and only there');
check(findNumberReserved('Y:100') && findNumberFallback(5) === 'Y-5',
    'the old colon („Y:100", until 04.10.2026) is still the mark — but a find now reads „Y-5"');

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
