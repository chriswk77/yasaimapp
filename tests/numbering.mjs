/**
 * Tests the bulk numbering's BROWSER TWIN (assets/js/numbering.js).
 *
 *   node tests/numbering.mjs
 *
 * THE ORIGINAL LIVES ON THE SERVER (find_number_start, find_number_plan in
 * includes/find_number.php), and tests/find_number.php holds it to these
 * very cases under „Many numbers at once". A case changed here changes
 * there — the window's preview must show the numbers the server writes.
 *
 * The finds are the ones the maintainer tried the draft with on 26.09.2026:
 * old notebook finds of 2019 without numbers, one typed by hand
 * („wk-00003"), one from another app („HM-0543"), two of this year from
 * the series, one without a find time.
 */

import { readStart, planNumbers } from '../assets/js/numbering.js';
import { findNumberKey } from '../assets/js/findnumber.js';

let failed = 0;
function check(b, what) {
    if (b) { console.log(`  ok    ${what}`); } else { failed++; console.log(`  FAILED: ${what}`); }
}

const WK = { pattern: 'wk-NNNNN', counter: 555, restart: false, period: null };
const DAY = { pattern: 'JJMMTT-NN', counter: 3, restart: true, period: '2026-09-26' };
const FINDS = [
    { id: 3120, title: 'Boletus edulis', number: null, found_at: '2019-08-24 10:12:00' },
    { id: 3121, title: 'Cantharellus cibarius', number: null, found_at: '2019-08-24 11:40:00' },
    { id: 3122, title: 'Amanita muscaria', number: 'wk-00003', found_at: '2019-08-31 09:05:00' },
    { id: 3123, title: 'Russula emetica', number: null, found_at: '2019-09-07 15:30:00' },
    { id: 3124, title: 'Macrolepiota procera', number: 'HM-0543', found_at: '2019-09-07 16:02:00' },
    { id: 3125, title: 'Lactarius deliciosus', number: null, found_at: '2019-09-14 13:20:00' },
    { id: 3126, title: 'Craterellus cornucopioides', number: null, found_at: '2019-09-21 12:00:00' },
    { id: 4410, title: 'Boletus reticulatus', number: 'wk-00553', found_at: '2026-09-20 11:05:00' },
    { id: 4418, title: 'Amanita rubescens', number: 'wk-00554', found_at: '2026-09-24 15:40:00' },
    { id: 3127, title: 'Hydnum repandum', number: null, found_at: null },
];
const TAKEN = new Map([
    ...Array.from({ length: 9 }, (_, i) => ['wk-' + String(544 + i).padStart(5, '0'), 'Fund aus der App']),
    ['wk-00006', 'Russula ochroleuca'], ['190824-01', 'Xerocomus badius'],
    ['260926-01', 'Suillus luteus'], ['260926-02', 'Suillus grevillei'],
]);

/** The plan as one line per find: „id:state:number". */
const line = (plan) => plan.rows.map((r) => `${r.id}:${r.state}${r.number ? ':' + r.number : ''}${r.conflict ? '@' + r.conflict : ''}`).join(' ');
const plan = (o) => planNumbers({ series: WK, finds: FINDS, taken: TAKEN, restart: false, scope: 'missing', start: null, ...o });

console.log('\nWhat „Fortlaufend ab" reads (twin of find_number_start):');
let s = readStart('wk-NNNNN', 'neu-01');
check(s.kind === 'number' && s.first === 1 && s.width === 2 && s.head === 'neu-' && s.tail === '' && !s.own,
    'a first number of its own shape: its last digits count');
s = readStart('wk-NNNNN', 'wk-01');
check(s.kind === 'number' && s.own && s.head === 'wk-' && s.width === 5, 'the series\' own shape is spelled the series\' way');
s = readStart('wk-NNNNN', 'CW-JJJJ-NNNN');
check(s.kind === 'pattern' && s.pattern === 'CW-JJJJ-NNNN' && s.first === 1 && s.width === 4 && s.dated && s.unit === 'Jahr',
    'a pattern: the date from each find, the N-run from 1');
s = readStart('wk-NNNNN', 'wk-JJJJ-0100');
check(s.kind === 'pattern' && s.pattern === 'wk-JJJJ-NNNN' && s.first === 100, 'a pattern with a written number starts there');
check(readStart('wk-NNNNN', 'JJMMTT-NN').unit === 'Tag', 'a pattern with day, month and year begins anew by the day');
check(readStart('wk-NNNNN', 'NEU-01').kind === 'number', 'a single N is a letter, not a placeholder');
check(!!readStart('wk-NNNNN', 'Y-5').error, 'the „Y-" mark is refused');
check(!!readStart('wk-NNNNN', 'HM-').error && !!readStart('wk-NNNNN', 'CW-JJJJ-').error, 'nothing that could count is refused');
check(readStart('wk-NNNNN', '  ') === null, 'nothing typed is no answer yet');

console.log('\nThe plan (twin of find_number_plan):');
let p = plan({ mode: 'next' });
check(line(p) === '3120:new:wk-00555 3121:new:wk-00556 3122:kept 3123:new:wk-00557 3124:kept 3125:new:wk-00558 '
    + '3126:new:wk-00559 4410:kept 4418:kept 3127:new:wk-00560', 'the next free numbers, in the given order');
check(p.counter === 561, 'and the counter goes on behind them');
p = plan({ mode: 'from', start: readStart('wk-NNNNN', 'neu-01') });
check(line(p) === '3120:new:neu-01 3121:new:neu-02 3122:kept 3123:new:neu-03 3124:kept 3125:new:neu-04 '
    + '3126:new:neu-05 4410:kept 4418:kept 3127:new:neu-06', 'a new series walks past the kept numbers — no neu-04 after wk-00003');
p = plan({ mode: 'from', start: readStart('wk-NNNNN', 'wk-01') });
check(line(p) === '3120:new:wk-00001 3121:new:wk-00002 3122:kept 3123:new:wk-00004 3124:kept 3125:new:wk-00005 '
    + '3126:taken:wk-00006@Russula ochroleuca 4410:kept 4418:kept 3127:new:wk-00007',
    'the notebook: wk-00003 holds its place, a taken number is left out, this year\'s numbers hold none');
p = plan({ mode: 'from', scope: 'all', restart: true, start: readStart('wk-NNNNN', 'CW-JJJJ-NNNN') });
check(line(p) === '3120:new:CW-2019-0001 3121:new:CW-2019-0002 3122:new:CW-2019-0003 3123:new:CW-2019-0004 '
    + '3124:new:CW-2019-0005 3125:new:CW-2019-0006 3126:new:CW-2019-0007 4410:new:CW-2026-0001 4418:new:CW-2026-0002 3127:undated',
    'a new pattern, every year anew — a find without a find time stays');
p = plan({ mode: 'from', scope: 'all', restart: false, start: readStart('wk-NNNNN', 'CW-JJJJ-NNNN') });
check(p.rows.find((r) => r.id === 4418).number === 'CW-2026-0009', 'the same pattern running through the years');
p = plan({ mode: 'next', series: DAY, scope: 'all' });
check(line(p) === '3120:new:190824-02 3121:new:190824-03 3122:new:190831-01 3123:new:190907-01 3124:new:190907-02 '
    + '3125:new:190914-01 3126:new:190921-01 4410:new:260920-01 4418:new:260924-01 3127:undated',
    'the next free per day — a day that holds 01 goes on at 02');
check(p.counter === 3 && p.period === '2026-09-26', 'older days leave the counter of today alone');
p = plan({ mode: 'remove' });
check(line(p) === '3120:kept 3121:kept 3122:new 3123:kept 3124:new 3125:kept 3126:kept 4410:new 4418:new 3127:kept',
    'removing touches only the finds with an own number');

console.log('\nOne number, any spelling:');
// The caller keys what is taken by findNumberKey — an unpadded „wk-6" an
// old import left is the wk-00006 the count reaches.
const takenSpelled = new Map([...TAKEN].filter(([k]) => k !== 'wk-00006'));
takenSpelled.set(findNumberKey('wk-NNNNN', 'wk-6'), 'Russula ochroleuca');
p = plan({ mode: 'from', start: readStart('wk-NNNNN', 'wk-01'), taken: takenSpelled });
check(p.rows.find((r) => r.id === 3126).state === 'taken', '„wk-6" stored the old way takes wk-00006 all the same');

console.log('\nNo number twice, whatever the order:');
const pair = [
    { id: 1, title: 'A', number: 'wk-00007', found_at: '2019-01-01 10:00:00' },
    { id: 2, title: 'B', number: 'wk-00001', found_at: '2019-01-02 10:00:00' },
];
p = planNumbers({ series: WK, finds: pair, taken: new Map([['wk-00002', 'X']]), mode: 'from', scope: 'all',
                  restart: false, start: readStart('wk-NNNNN', 'wk-01') });
check(line(p) === '1:taken:wk-00001@B 2:taken:wk-00002@X',
    'B cannot move, so it keeps wk-00001 — and A may not take it');
p = planNumbers({ series: WK, finds: [pair[1]], taken: new Map(), mode: 'from', scope: 'all', restart: false,
                  start: readStart('wk-NNNNN', 'wk-01') });
check(line(p) === '2:kept', 'a find that already carries its number stays as it is');
p = planNumbers({ series: WK, finds: [{ id: 9, title: 'C', number: null, found_at: null }], taken: new Map(),
                  mode: 'from', scope: 'all', restart: false, start: readStart('wk-NNNNN', 'wk-NNN') });
check(line(p) === '9:new:wk-00001', 'a number of the series\' shape is spelled the series\' way, as saving will');

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
