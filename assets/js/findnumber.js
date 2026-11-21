/**
 * The find-number pattern arithmetic — the browser twin.
 *
 * THE ORIGINAL LIVES ON THE SERVER (find_number_render and the digit-run
 * normalization in includes/find_number.php): the server mints, this here
 * only PREVIEWS while somebody types in the settings. TWO PLACES THAT
 * MUST COMPUTE THE SAME THING — whoever changes the rules there changes
 * them here in the same breath, and tests/findnumber.mjs holds this twin
 * against the very cases tests/find_number.php holds the original to.
 * A preview that shows something other than what minting will do would
 * be worse than none.
 *
 * Pure calculation, no DOM — loadable in Node for exactly that test.
 */

/**
 * The number a find has without a pattern — „Y-100", its database id with
 * YASAIMAPP's mark. The twin of find_number_fallback() in
 * includes/find_number.php, which says why the mark exists; a pattern may
 * not begin with it.
 */
export const FIND_NUMBER_FALLBACK_PREFIX = 'Y-';

/** Every spelling the mark ever had — „Y:" until 04.10.2026 (find_number.php says why it changed). */
const FIND_NUMBER_RESERVED_PREFIXES = ['Y-', 'Y:'];

export function findNumberFallback(id) {
    return FIND_NUMBER_FALLBACK_PREFIX + id;
}

/** Does a pattern begin with the mark? Case and blanks do not hide it. */
export function findNumberReserved(value) {
    const start = String(value).trimStart().toUpperCase();
    return FIND_NUMBER_RESERVED_PREFIXES.some((prefix) => start.startsWith(prefix));
}

/**
 * Render one number from the pattern.
 *
 * @param {string} pattern  with JJJJ/JJ/MM/TT and an N-run
 * @param {Date} when       the find time the date parts come from
 * @param {number} n        the running number
 */
export function renderFindNumber(pattern, when, n) {
    // The N-run first, like over there: the date replacements insert
    // digits, and a pattern like "JJJJNNNN" must not have its year eaten
    // by a later N-hungry pass. Only the first longest run is the counter.
    let longest = null;
    for (const run of pattern.matchAll(/N+/g)) {
        if (longest === null || run[0].length > longest[0].length) longest = run;
    }
    if (longest !== null) {
        const digits = String(n).padStart(longest[0].length, '0');
        pattern = pattern.slice(0, longest.index) + digits
                + pattern.slice(longest.index + longest[0].length);
    }

    const two = (x) => String(x).padStart(2, '0');
    return pattern
        .replaceAll('JJJJ', String(when.getFullYear()))
        .replaceAll('JJ', two(when.getFullYear() % 100))
        .replaceAll('MM', two(when.getMonth() + 1))
        .replaceAll('TT', two(when.getDate()));
}

/**
 * The date section the pattern's numbers live in — the twin of
 * find_number_period() on the server, reduced to what the settings label
 * needs: the unit word. THE CHAIN MUST REACH DOWN FROM THE YEAR: a TT
 * alone does not identify a day ("05-001" of September and of October
 * would collide), so Tag needs JJ+MM+TT, Monat needs JJ+MM, Jahr needs
 * JJ — and without a year there is no section at all.
 *
 * @returns {'Tag'|'Monat'|'Jahr'|null}
 */
export function periodUnit(pattern) {
    if (!pattern.includes('JJ')) return null;
    if (pattern.includes('MM') && pattern.includes('TT')) return 'Tag';
    if (pattern.includes('MM')) return 'Monat';
    return 'Jahr';
}

/**
 * The section a find of `when` belongs to — the key half of
 * find_number_period(): '2026-08-23' for a day, '2026-08' for a month,
 * '2026' for a year, null without a section. The settings' preview
 * compares it with the section the counter counts in, as the mint does.
 *
 * @returns {?string}
 */
export function periodKey(pattern, when) {
    const unit = periodUnit(pattern);
    if (unit === null) return null;
    const two = (x) => String(x).padStart(2, '0');
    const year = String(when.getFullYear());
    if (unit === 'Tag') return `${year}-${two(when.getMonth() + 1)}-${two(when.getDate())}`;
    if (unit === 'Monat') return `${year}-${two(when.getMonth() + 1)}`;
    return year;
}

/**
 * The pattern split into what it is made of — the twin of
 * find_number_tokens(): the counter is the FIRST longest N-run, the date
 * parts are found left to right, the longest first, the rest is literal.
 *
 * @returns {Array<['lit', string]|['date', string]|['counter', number]>}
 */
export function findNumberTokens(pattern) {
    let longest = null;
    for (const run of pattern.matchAll(/N+/g)) {
        if (longest === null || run[0].length > longest[0].length) longest = run;
    }
    const tokens = [];
    const scan = (part) => {
        let literal = '';
        for (let i = 0; i < part.length;) {
            const key = ['JJJJ', 'JJ', 'MM', 'TT'].find((k) => part.startsWith(k, i));
            if (key) {
                if (literal !== '') { tokens.push(['lit', literal]); literal = ''; }
                tokens.push(['date', key]);
                i += key.length;
            } else {
                literal += part[i];
                i++;
            }
        }
        if (literal !== '') tokens.push(['lit', literal]);
    };
    if (longest === null) { scan(pattern); return tokens; }
    scan(pattern.slice(0, longest.index));
    tokens.push(['counter', longest[0].length]);
    scan(pattern.slice(longest.index + longest[0].length));
    return tokens;
}

// The syntax characters only: with the u flag an escaped „-" or „/" is an
// error, and outside a class neither needs one.
const escapeRegex = (text) => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const two = (x) => String(x).padStart(2, '0');

/**
 * The pattern turned round — the twin of find_number_matcher(): the
 * counter a group, the date parts digits, those in `fixed` taken from
 * `when` so only that section matches. Null without a counter.
 *
 * @returns {?RegExp}
 */
export function findNumberMatcher(pattern, fixed, when) {
    const tokens = findNumberTokens(pattern);
    if (!tokens.some(([kind]) => kind === 'counter')) return null;
    const values = { JJJJ: String(when.getFullYear()), JJ: two(when.getFullYear() % 100),
                     MM: two(when.getMonth() + 1), TT: two(when.getDate()) };
    const source = tokens.map(([kind, value]) => {
        if (kind === 'lit') return escapeRegex(value);
        if (kind === 'date') return fixed.includes(value) ? values[value] : `\\d{${value === 'JJJJ' ? 4 : 2}}`;
        return '(\\d+)';
    }).join('');
    return new RegExp(`^${source}$`, 'iu');
}

/**
 * A typed number read against the pattern — which number of the series it
 * is, its spelling in the series (the twin of find_number_normalize():
 * the pattern's own text, the date digits as typed, the running number
 * padded to the N-run and never cut) and the date parts it names. Null
 * when it lacks the pattern's shape: then it is a series of its own.
 *
 * @returns {?{n: number, normalized: string, parts: Object<string, string>}}
 */
export function readFindNumber(pattern, value) {
    const tokens = findNumberTokens(pattern);
    if (!tokens.some(([kind]) => kind === 'counter')) return null;
    const source = tokens.map(([kind, part]) => kind === 'lit' ? escapeRegex(part)
        : kind === 'date' ? `(\\d{${part === 'JJJJ' ? 4 : 2}})` : '(\\d+)').join('');
    const m = new RegExp(`^${source}$`, 'iu').exec(String(value).trim());
    if (!m) return null;
    let group = 1;
    let n = 0;
    const parts = {};
    const normalized = tokens.map(([kind, part]) => {
        if (kind === 'lit') return part;
        if (kind === 'date') { parts[part] = m[group]; return m[group++]; }
        const digits = m[group++].replace(/^0+/, '');
        n = Number(digits || 0);
        return (digits || '0').padStart(part, '0');
    }).join('');
    return { n, normalized, parts };
}

/** The spelling a typed number is stored in — the twin of find_number_normalize(). */
export function normalizeFindNumber(pattern, value) {
    return pattern ? (readFindNumber(pattern, value)?.normalized ?? value) : value;
}

/**
 * The key a number is compared by — the twin of find_number_key(): its
 * spelling in the series where it has the series' shape, lower-cased, so
 * „WK-1", „wk-01" and „wk-00001" are one number, as a person reads them.
 */
export function findNumberKey(pattern, number) {
    const text = String(number ?? '').trim();
    return (pattern ? (readFindNumber(pattern, text)?.normalized ?? text) : text).toLowerCase();
}

/**
 * The date a number names, down to its section's unit — null where the
 * pattern names no year. A two-digit year is read as 20xx.
 *
 * @returns {?Date}
 */
export function numberDate(parts) {
    const year = parts.JJJJ !== undefined ? Number(parts.JJJJ)
        : parts.JJ !== undefined ? 2000 + Number(parts.JJ) : null;
    if (year === null) return null;
    return new Date(year, parts.MM !== undefined ? Number(parts.MM) - 1 : 0, parts.TT !== undefined ? Number(parts.TT) : 1);
}

/**
 * The next free number for a find of `when` — the twin of the mint's
 * choice (find_number_mint): the highest number of the series already
 * given, plus one, at least the counter — and with a restart only within
 * the section of `when`, where the counter counts only if that section is
 * its own.
 *
 * @param {{pattern: string, counter: number, restart: boolean, period: ?string}} series
 * @param {Iterable<string>} numbers  the numbers already given
 * @returns {{n: number, text: string, unit: ?string}}
 */
export function nextFreeNumber(series, numbers, when) {
    const unit = series.restart ? periodUnit(series.pattern) : null;
    const floor = unit === null || periodKey(series.pattern, when) === series.period ? series.counter : 1;
    const fixed = { Tag: ['JJJJ', 'JJ', 'MM', 'TT'], Monat: ['JJJJ', 'JJ', 'MM'], Jahr: ['JJJJ', 'JJ'] }[unit] ?? [];
    const regex = findNumberMatcher(series.pattern, fixed, when);
    let highest = 0;
    for (const number of numbers) {
        const m = regex?.exec(String(number).trim());
        if (!m) continue;
        const digits = m[1].replace(/^0+/, '');
        if (digits.length <= 8) highest = Math.max(highest, Number(digits || 0));
    }
    const n = Math.max(floor, highest + 1);
    return { n, text: renderFindNumber(series.pattern, when, n), unit };
}

/**
 * What saving would make of the typed pattern — the twin of the
 * digit-run normalization in find_number_settings_save().
 *
 * @returns {{pattern: string, next: ?number}|null}
 *          next carries the recognized continuation when a written-out
 *          number became the counter — the typed one is the LAST GIVEN
 *          number, the next find counts on past it (543 → 544). null when
 *          the N's were already there; the whole result is null when
 *          there is no counter at all.
 */
export function normalizePattern(raw) {
    const pattern = raw.trim();
    if (pattern === '' || /N/.test(pattern)) {
        return { pattern, next: null };
    }
    let longest = null;
    for (const run of pattern.matchAll(/[0-9]+/g)) {
        if (longest === null || run[0].length > longest[0].length) longest = run;
    }
    if (longest === null) {
        return null;
    }
    return {
        pattern: pattern.slice(0, longest.index) + 'N'.repeat(longest[0].length)
               + pattern.slice(longest.index + longest[0].length),
        next: parseInt(longest[0], 10) + 1,
    };
}
