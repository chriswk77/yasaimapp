/**
 * Short names — as short as they may be, as long as they must be.
 *
 * A name stands in the sidebar next to a list, in a row of a container, on
 * a marker's tooltip. There is room for a word there, not for a person's
 * full registration. "Finn Horn" says nothing more than "Finn" as long as
 * there is only one Finn — and when a second one turns up, exactly as many
 * letters of the surname are added as it takes to tell them apart, and not
 * one more (20.08.2026).
 *
 *   Finn Horn,  Tobias Lenz              ->  Finn          Tobias
 *   Finn Horn,  Finn Bauer               ->  Finn H.       Finn B.
 *   Finn Horn,  Finn Hansen              ->  Finn Ho.      Finn Ha.
 *   Finn Horn,  Finn Hoffmann            ->  Finn Hor.     Finn Hof.
 *   Finn Horn,  Finn Horn                ->  Finn Horn     Finn Horn
 *
 * THE SET DECIDES, NOT THE NAME. How short "Finn" may be depends on who
 * else is there — so every call needs the whole company. Whoever shortens
 * a single name against nothing gets it back whole, and that is right: one
 * name alone is never ambiguous.
 *
 * THE DOT MARKS THE CUT, and only there. "Finn H." says: there is more of
 * this surname, you are seeing as much of it as it takes. Where the whole
 * surname is shown there is nothing to mark — "Finn Ho" for somebody who
 * is really called Ho would be a promise of letters that do not exist.
 *
 * WHY NOT "F. Horn": the first name is what one recognises somebody by,
 * the surname is only the tiebreaker. Shortening the wrong one of the two
 * leaves the reader with the part that says least.
 */

/** First name and surname — everything after the first word is surname. */
function split(name) {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);
    return { first: parts[0] ?? '', last: parts.slice(1).join(' ') };
}

/**
 * The short form of one name within a company.
 *
 * @param {string} name    the full name
 * @param {Iterable<string>} company  every name it has to be told apart
 *   from — its own may be in there, it does not disturb.
 * @returns {string}
 */
export function shortName(name, company = []) {
    const me = split(name);
    if (me.first === '') return String(name ?? '').trim();

    // Everybody else with the same first name. Only they can make it
    // ambiguous — the rest is of no concern, however long their names.
    const rivals = [];
    for (const other of company) {
        const o = split(other);
        const full = (o.first + ' ' + o.last).trim();
        if (full === (me.first + ' ' + me.last).trim()) continue;   // myself
        if (o.first === me.first) rivals.push(o.last);
    }
    if (rivals.length === 0) return me.first;

    // As many letters of the surname as it takes. Without a surname there
    // is nothing to add — then the first name stands there twice, and that
    // is honest: the two really are not distinguishable here.
    for (let n = 1; n <= me.last.length; n++) {
        const mine = me.last.slice(0, n);
        if (!rivals.some(r => r.slice(0, n) === mine)) {
            // The dot only where something was really cut off — see above.
            return me.first + ' ' + mine + (n < me.last.length ? '.' : '');
        }
    }
    return (me.first + ' ' + me.last).trim();
}

/**
 * The short forms of a whole company at once.
 *
 * Cheaper and, above all, CONSISTENT: whoever shortens name by name may
 * pass a different company each time and then show the same person as
 * "Finn" in one row and as "Finn Ho" in the next.
 *
 * @param {Iterable<string>} names
 * @returns {Map<string,string>} full name -> short form
 */
export function shortNames(names) {
    const all = [...names];
    return new Map(all.map(n => [n, shortName(n, all)]));
}

/* --- One company for the whole page ----------------------------------------
   EVERY NAME THE PAGE SHOWS IS TOLD APART AGAINST THE SAME PEOPLE
   (15.11.2026: „im Freundeskreis zwei Finns — der eine Finn H., der andere
   Finn B. … das machen wir überall dort, wo wir Namen anzeigen"). Until then
   each place shortened against whatever it happened to have — the edit bar
   against nothing at all (the first word), the sidebar against the sharers —
   and one Finn read „Finn" in one place and „Finn H." in the next.

   The company is the viewer's circle as the page's start data knows it: his
   own name, his friends, the owners of the lists shared with him and the
   people in those shares. Built once per page, the first time a name is
   asked for — the start data does not change while the page stands. A name
   from outside the circle (an excursion's leader, a photographer one has
   never befriended) is told apart against the circle as well, so it never
   reads like one of the friends. A page without that knowledge
   (keys.php before it carries friends) shows names whole: shortened against
   nobody, every Finn would be „Finn". */
let circle = null;
function circleOf() {
    if (circle !== null) return circle;
    const start = globalThis.window?.START_DATA ?? {};
    const names = new Set();
    const add = (n) => { if (typeof n === 'string' && n.trim() !== '') names.add(n.trim()); };
    add(start.user?.name);
    for (const f of start.friends ?? []) add(f.name);
    for (const c of start.shared ?? []) {
        add(c.owner);
        for (const s of c.shares ?? []) add(s.display_name);
    }
    circle = names;
    return circle;
}

/**
 * A person's name as every place on the page shows it: the first name, and
 * as many letters of the surname as the viewer's circle needs to tell two
 * people apart („Finn H." beside „Finn B.").
 */
export function personName(name) {
    const full = String(name ?? '').trim();
    if (full === '') return full;
    const company = circleOf();
    if (company.size < 2 && !company.has(full)) return full;
    return shortName(full, [...company, full]);
}
