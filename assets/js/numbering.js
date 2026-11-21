/**
 * Many find numbers at once — the arithmetic of Organisieren → Fundnummern
 * (26.09.2026: „das mehrfach umbenennen der Fundnummern").
 *
 * THE ORIGINAL LIVES ON THE SERVER: find_number_start(), find_number_plan()
 * and find_number_draw_many() in includes/find_number.php decide what is
 * written; this twin only PREVIEWS, so the window can show every number
 * before anything is saved. tests/numbering.mjs holds it to the cases
 * tests/find_number.php holds the original to — whoever changes a rule
 * on one side changes it on the other and in both tests.
 *
 * Pure calculation, no DOM — loadable in Node for exactly that test. The
 * window itself is assets/js/numberdialog.js.
 */

import { readFindNumber, nextFreeNumber, findNumberReserved, findNumberTokens, renderFindNumber,
         normalizeFindNumber, findNumberKey, periodUnit, periodKey } from './findnumber.js';
import { t } from './i18n.js';

/** 'Y-m-d H:i:s' as the server stores it → a local Date, or null. */
export const findDate = (foundAt) => foundAt ? new Date(String(foundAt).replace(' ', 'T')) : null;

/** Does the pattern name a date — JJJJ, JJ, MM or TT anywhere? */
export function findNumberDated(pattern) {
    return findNumberTokens(pattern).some(([kind]) => kind === 'date');
}

/**
 * „Die nächsten freien" for many finds, one after the other — the twin of
 * find_number_draw_many(): each draws as the mint would at that moment,
 * and the counter moves on as it would.
 *
 * @returns {{numbers: string[], counter: number, period: ?string}}
 */
export function drawMany(series, numbers, foundAts) {
    let counter = Math.max(1, Number(series.counter) || 1);
    let period = series.period ?? null;
    const sectioned = !!series.restart && periodUnit(series.pattern) !== null;
    const given = [...numbers];
    const out = [];
    for (const foundAt of foundAts) {
        const when = findDate(foundAt) ?? new Date();
        const next = nextFreeNumber({ pattern: series.pattern, counter, restart: !!series.restart, period }, given, when);
        const key = sectioned ? periodKey(series.pattern, when) : null;
        if (key === null) counter = next.n + 1;
        else if (period === null || key >= period) { counter = next.n + 1; period = key; }
        const text = next.text.slice(0, 80);
        given.push(text);
        out.push(text);
    }
    return { numbers: out, counter, period };
}

/**
 * What was typed after „Fortlaufend ab" — the twin of find_number_start():
 * a FIRST NUMBER whose last run of digits counts up („neu-01" → neu-02 …;
 * one of the series' own shape in the series' spelling), or a PATTERN as
 * the settings read one, where JJJJ, JJ, MM and TT become each find's own
 * date and the N-run the running number from 1. A pattern without an
 * N-run of two starts its count at the last number written in it.
 *
 * @returns {null|{error: string}|{kind: 'number', first: number, width: number,
 *           head: string, tail: string, own: boolean}|{kind: 'pattern',
 *           pattern: string, first: number, width: number, dated: boolean, unit: ?string}}
 */
export function readStart(seriesPattern, typed) {
    const text = String(typed ?? '').trim();
    if (text === '') return null;
    if (findNumberReserved(text)) {
        return { error: t('Eine Fundnummer darf nicht mit „Y-" anfangen. So beginnen die Nummern, die YASAIMAPP selbst vergibt.') };
    }
    const own = seriesPattern ? (readFindNumber(seriesPattern, text)?.normalized ?? null) : null;
    if (own === null && /JJ|MM|TT|NN/.test(text)) {
        const longestN = Math.max(0, ...[...text.matchAll(/N+/g)].map((m) => m[0].length));
        const runs = [...text.matchAll(/\d+/g)];
        let pattern = text;
        let first = 1;
        if (longestN < 2 && runs.length > 0) {
            const run = runs.at(-1);
            if (run[0].length > 15) return { error: t('Die Zahl in der ersten Nummer ist zu lang.') };
            pattern = text.slice(0, run.index) + 'N'.repeat(Math.max(run[0].length, longestN + 1))
                + text.slice(run.index + run[0].length);
            first = Number(run[0]);
        } else if (longestN === 0) {
            return { error: t('Im Muster fehlt die laufende Nummer: NNN oder eine Zahl.') };
        }
        const width = findNumberTokens(pattern).find(([kind]) => kind === 'counter')?.[1] ?? 1;
        return { kind: 'pattern', pattern, first, width, dated: findNumberDated(pattern), unit: periodUnit(pattern) };
    }
    const spelled = own ?? text;
    const runs = [...spelled.matchAll(/\d+/g)];
    if (runs.length === 0) return { error: t('In der ersten Nummer fehlt eine Zahl, die hochzählen kann.') };
    const run = runs.at(-1);
    if (run[0].length > 15) return { error: t('Die Zahl in der ersten Nummer ist zu lang.') };
    return { kind: 'number', first: Number(run[0]), width: run[0].length,
             head: spelled.slice(0, run.index), tail: spelled.slice(run.index + run[0].length), own: own !== null };
}

/**
 * The plan: what every chosen find is to carry — the twin of
 * find_number_plan(), which says why each rule is what it is: 'next' draws
 * as the mint would, 'from' counts in the given order (one count per
 * section where a dated pattern begins anew), 'remove' takes the own
 * number away. A kept find holds its place in a count only when it already
 * carries that place's number, and no number is ever given twice.
 *
 * @param {object} o
 * @param {?{pattern: string, counter: number, restart: boolean, period: ?string}} o.series  the account's
 * @param {Array<{id: number, title: string, number: ?string, found_at: ?string}>} o.finds  in order
 * @param {Map<string, string>} o.taken  findNumberKey() → title, of own finds outside the choice
 * @param {'next'|'from'|'remove'} o.mode
 * @param {?object} o.start   readStart()'s answer, for 'from'
 * @param {boolean} o.restart  a dated pattern begins anew per section
 * @param {'missing'|'all'} o.scope
 * @returns {{rows: Array<{id: number, state: 'new'|'kept'|'taken'|'undated', number: ?string, conflict: ?string}>,
 *            counter: ?number, period: ?string}}
 */
export function planNumbers({ series, finds, taken, mode, start, restart, scope }) {
    // Compared by the number in the series, not by its letters
    // (findNumberKey) — `taken` must be keyed the same way.
    const lower = (s) => findNumberKey(series?.pattern ?? null, s);
    const has = (f) => String(f.number ?? '').trim() !== '';
    // Written through point_update, which spells a number of the account's
    // own shape the series' way — the preview spells it the same.
    const spell = (text) => series?.pattern ? normalizeFindNumber(series.pattern, text) : text;

    const pattern = mode === 'next' ? (series?.pattern ?? null) : (start?.kind === 'pattern' ? start.pattern : null);
    const dated = mode !== 'remove' && pattern !== null && findNumberDated(pattern);

    const rows = [];
    const moving = [];
    const carriers = new Map(taken);
    finds.forEach((f, i) => {
        moving[i] = mode === 'remove' ? has(f) : (scope === 'all' || !has(f));
        const undated = moving[i] && dated && !f.found_at;
        rows[i] = { id: f.id, state: undated ? 'undated' : 'kept', number: null, conflict: null };
        if ((!moving[i] || undated) && has(f)) carriers.set(lower(f.number), f.title ?? '');
    });
    let counter = null;
    let period = null;

    if (mode === 'remove') {
        finds.forEach((f, i) => { if (moving[i]) rows[i].state = 'new'; });
        return { rows, counter: null, period: null };
    }

    if (mode === 'next' && series) {
        const batch = rows.map((r, i) => i).filter((i) => rows[i].state === 'kept' && moving[i]);
        const drawn = drawMany(series, [...carriers.keys()], batch.map((i) => finds[i].found_at ?? null));
        batch.forEach((i, k) => { rows[i].state = 'new'; rows[i].number = spell(drawn.numbers[k]); });
        counter = drawn.counter;
        period = drawn.period;
    } else if (mode === 'from' && start && !start.error) {
        const counts = new Map();
        finds.forEach((f, i) => {
            const when = findDate(f.found_at);
            if (dated && !when) return;
            const section = pattern !== null && restart && start.unit ? (periodKey(pattern, when) ?? '') : '';
            const n = counts.get(section) ?? start.first;
            const text = spell((pattern !== null
                ? renderFindNumber(pattern, when ?? new Date(), n)
                : start.head + String(n).padStart(start.width, '0') + start.tail).slice(0, 80));
            if (!moving[i]) {
                if (lower(f.number) === lower(text)) counts.set(section, n + 1);
                return;
            }
            counts.set(section, n + 1);
            rows[i].state = 'new';
            rows[i].number = text;
        });
    }

    let moved;
    do {
        moved = false;
        const stays = new Map(carriers);
        rows.forEach((r, i) => {
            if ((r.state === 'taken' || r.state === 'kept') && moving[i] && has(finds[i])) {
                stays.set(lower(finds[i].number), finds[i].title ?? '');
            }
        });
        const given = new Map();
        rows.forEach((r, i) => {
            if (r.state !== 'new') return;
            const key = lower(r.number);
            if (key === lower(finds[i].number)) {
                r.state = 'kept';
                r.number = null;
                moved = true;
            } else if (stays.has(key) || given.has(key)) {
                r.state = 'taken';
                r.conflict = stays.get(key) ?? given.get(key);
                moved = true;
            } else {
                given.set(key, finds[i].title ?? '');
            }
        });
    } while (moved);

    return { rows, counter, period };
}
