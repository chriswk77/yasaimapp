import { icon } from './icons.js';
import { t } from './i18n.js';

/**
 * The time filter — ONCE, for the sidebar and for Organisieren.
 *
 * It builds its interface itself: one row with the day list and two date
 * fields. Both pages only put down an empty box — otherwise the same
 * markup would stand twice in the project and drift apart at the first
 * rebuild.
 *
 * THE FIELDS THEMSELVES ARE THE DISPLAY. A filter that merely shortens
 * the list is a riddle — you see the result and not the cause. Since the
 * row stands open, the two dates say what applies; a clear button joins
 * them as soon as there is something to clear.
 *
 * A DAY IS A TOUR — for now (the call, 14.08.2026). The day list
 * names the two most recent "Letzte Tour" and "Vorletzte Tour", all
 * further ones with weekday and date. Whoever picks one filters exactly
 * that day. The earlier gap detection (touren.js) guessed from find
 * times where a tour begins; that will later be replaced by "Tour
 * starten / beenden" by hand, and then a tour list takes the place of
 * the day list. Until then the day is the more honest simplification: It
 * stands in the data instead of being estimated.
 *
 * THE LIST LOADS MORE WHILE SCROLLING. It reaches back to the first
 * point ever, and at ten years of mapping that is thousands of rows —
 * building all at once cost a noticeable pause on unfolding. It starts
 * with a few and grows while you scroll.
 */

const FIRST  = 15;   // this many rows stand there on unfolding
const REFILL = 20;   // this many are added while scrolling

/** The two letters of the weekday — "DO" for Thursday. */
const WEEKDAYS = ['SO', 'MO', 'DI', 'MI', 'DO', 'FR', 'SA'].map((w) => t(w));

/**
 * A point's day as "YYYY-MM-DD".
 *
 * found_at, falling back to created_at — the same order as in
 * timestamp() right below and as server-side in bucket.php. A point
 * without a find time is not timeless: It was created on
 * some day, and for "what did I bring back that day" exactly that one
 * counts. Whoever looks for the MISSING find time has the dedicated
 * checkbox for that.
 */
export function dayOf(point) {
    const raw = point?.found_at || point?.created_at || '';
    const day = String(raw).slice(0, 10);
    return /^\d{4}-\d{2}-\d{2}$/.test(day) ? day : null;
}

/**
 * The same point in time as a number — for sorting the point list.
 *
 * MySQL delivers "2026-08-10 14:23:11" without a time zone. Safari makes
 * nothing usable of that with new Date() as long as the space is in
 * there; with "T" all of them read it. Both are local time and should
 * stay it — the find was at half past two in the afternoon, not half
 * past twelve UTC.
 *
 * A WHOLE MODULE touren.js ONCE STOOD NEXT TO THIS, guessing from the
 * find times where a tour begins: six hours of pause counted as the
 * boundary, and a night excursion across midnight deliberately stayed
 * ONE tour. That flew out on 14.08.2026 — the maintainer never hunts
 * mushrooms across midnight, and it was guesswork anyway. In its place
 * comes the day (dayOf) and later "Tour starten / beenden" by hand.
 */
export function timestamp(point) {
    const raw = point?.found_at || point?.created_at;
    if (!raw) return null;
    const d = new Date(String(raw).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? null : d.getTime();
}

/** Does the day lie in the range? Comparison happens on the string. */
export function inTimeRange(day, from, to) {
    if (from === '' && to === '') return true;
    if (day === null) return false;
    if (from !== '' && day < from) return false;
    if (to !== '' && day > to) return false;
    return true;
}

/** "DO 07.08.2026" */
export function readableDay(day) {
    const d = new Date(day + 'T12:00:00');
    if (Number.isNaN(d.getTime())) return day;
    const pad = (n) => String(n).padStart(2, '0');
    return `${WEEKDAYS[d.getDay()]} ${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()}`;
}

/** Short, for the button: "07.08." or "07.08.25" if another year applies. */
function short(day, withYear) {
    const [y, m, d] = day.split('-');
    return `${d}.${m}.` + (withYear ? y.slice(-2) : '');
}

/**
 * The range in words: "Letzte Tour", "ab 01.08.", "07.08.–10.08.".
 *
 * SINCE 17.08.2026 NOTHING CALLS THIS — it wrote the chip that carried the
 * time filter, and that chip is gone since the fields stand open. It stays
 * anyway, and deliberately: it is a pure function with its own checks, and
 * "Tour starten / beenden" needs exactly this sentence again as soon as a
 * tour list takes the place of the day list. Whoever finds it without a
 * caller has not found a leftover.
 */
export function label(from, to, days = []) {
    if (from === '' && to === '') return t('Zeitraum');

    const year = String(new Date().getFullYear());
    const withYear = [from, to].some((w) => w !== '' && w.slice(0, 4) !== year);

    if (from !== '' && from === to) {
        // A SINGLE DAY usually means "one tour" — then that is what it
        // says, or there would be two names for the same choice.
        const index = days.indexOf(from);
        if (index === 0) return t('Letzte Tour');
        if (index === 1) return t('Vorletzte Tour');
        const d = new Date(from + 'T12:00:00');
        return WEEKDAYS[d.getDay()] + ' ' + short(from, withYear);
    }
    if (from !== '' && to !== '') return `${short(from, withYear)}–${short(to, withYear)}`;
    if (from !== '') return t('ab %s', short(from, withYear));
    return t('bis %s', short(to, withYear));
}

/**
 * The days on which points exist — most recent first, each once.
 *
 * @param {Iterable} points
 * @param {(p: any) => (string|null)} dayOfPoint  how the day comes out of
 *        the point. The sidebar has the full points and computes it
 *        itself; Organisieren gets it ready-made from the server, because its
 *        point data is lean.
 * @param {(p: any) => ?string} [ownerOf]  whose a point is — the same
 *        question collectTours asks, with the same answers
 */
export function collectDays(points, dayOfPoint = dayOf, ownerOf = () => null) {
    const places = new Map();   // day -> set of municipalities
    const persons = new Map();  // day -> {names: set of foreign names, own: bool}
    for (const p of points) {
        const d = dayOfPoint(p);
        if (d === null) continue;
        if (!places.has(d)) {
            places.set(d, new Set());
            persons.set(d, { names: new Set(), own: false });
        }
        // THE MUNICIPALITY IS ALREADY ON THE POINT (the call,
        // 14.08.2026) — the server fills it in on creation. Showing it in
        // the day list thus costs nothing and answers the question you
        // really have when leafing back: not "when was that" but "where".
        const place = (p?.municipality ?? '').trim();
        if (place !== '') places.get(d).add(place);
        /* A DAY OF SOMEBODY ELSE'S FINDS SAYS WHOSE (26.09.2026:
           Finn set a find he had identified later, without a tour, and it
           stood in the list as „Vorletzte Tour" — „es sieht so aus, als
           wäre das meine eigene Tour"). The friends' outings have named
           their person since 14.09.2026, but only the NAMED ones: a point
           without a tour falls into a bare day, and the day never asked.
           It asks now, by the rule of the tours — one own point makes the
           day mine, and one's own name never stands on a row. The same
           gap held every friend's day from before the tours existed. */
        const by = ownerOf(p);
        if (by) persons.get(d).names.add(String(by)); else persons.get(d).own = true;
    }

    return [...places.keys()].sort().reverse().map((d) => {
        const names = [...places.get(d)];
        const who = persons.get(d);
        const foreign = [...who.names];
        return {
            tag: d,
            // At most two, then an ellipsis: Whoever drove through four
            // municipalities in one day does not need all four in a narrow
            // list — they recognize the tour by the first name.
            orte: names.slice(0, 2).join(', ') + (names.length > 2 ? ' …' : ''),
            // The persons of a day that is not mine, cut like the places:
            // an excursion day holds a dozen guests, and the row is narrow.
            owner: who.own || foreign.length === 0 ? null
                : foreign.slice(0, 2).join(', ') + (foreign.length > 2 ? ' …' : ''),
        };
    });
}

/**
 * Tours and days in one list — most recent first.
 *
 * SINCE 23.08.2026 A TOUR IS NO LONGER A DAY: points created during a
 * named outing carry its name (point.tour), and the row then shows the
 * NAME — two outings on the same day stay two rows. Everything without a
 * name keeps its day row, so the old stock reads exactly as before. This
 * is the "tour list takes the place of the day list" the module head
 * promised.
 *
 * @param {(p: object) => ?string} [ownerOf] whose a point is — the
 *        (short) name for somebody else's point, null for one's own
 * @returns rows of two kinds: {tour, tag, orte, owner} for a named outing,
 *          {tag, orte, owner} for a bare day — tag is the day the outing began
 *          (a bare day's own day), and the sort key; owner names the person of a foreign
 *          outing or day and is null for one's own.
 */
export function collectTours(points, dayOfPoint = dayOf, ownerOf = () => null) {
    const tours = new Map();   // key -> {name, tag, places:Set}
    const bare  = [];          // points without a tour

    /* GROUPED BY IDENTITY, NOT BY NAME (migration 0079) — the same rule
       organize.js has followed since it was minted, and this side had not.
       It went unnoticed for as long as the proposed name counted up across
       all days: every outing had its own name anyway, so grouping by name
       gave the right answer by accident. Since the name is plainly
       „Tagestour" again (27.08.2026), it stops giving it: every
       day's outing would fall into ONE row, and picking it would show
       three years of finds at once.

       The key is „t:<id>" where a point carries one; everything older
       falls back to the bare name and behaves exactly as before — that is
       also why a stored filter value from yesterday still matches. The
       prefix is the same trick the excursions use („e:<id>"): a key must
       not be confusable with a name somebody typed. */
    for (const p of points) {
        const name = (p?.tour ?? '').trim();
        if (name === '') { bare.push(p); continue; }
        const id  = String(p?.tour_id ?? '').trim();
        const key = id !== '' ? 't:' + id : name;
        const d = dayOfPoint(p);
        if (!tours.has(key)) tours.set(key, { name, tag: d ?? '', last: undefined, places: new Set(), owners: new Set(), own: false });
        const t = tours.get(key);
        /* WHOSE OUTING (14.09.2026: „bei den letzten Touren sind
           auch die Touren der anderen meiner Freunde mit dabei — das ist
           okay, nur müsste man sie kennzeichnen"). ownerOf says whose a
           point is — a name for somebody else's, null for one's own; the
           caller decides that, because only it knows who is looking and
           how the company shortens its names (map.js hands in
           shortNameOf). A tour whose every point is somebody else's is
           that person's outing, and the row says so. One own point makes
           it mine — a co-owned find in my filing is still the holder's
           tour, and stays marked as his. One's own name NEVER stands on a
           row („eigene nicht mit dem eigenen Namen kennzeichnen"). */
        const by = ownerOf(p);
        if (by) t.owners.add(String(by)); else t.own = true;
        /* THE ROW CARRIES THE DAY THE OUTING BEGAN (23.09.2026,
           after an evening of entries that ran past midnight: „die Tour
           soll so heißen wie das Startdatum, nicht das Enddatum — man
           macht ja nicht durch, oftmals nur bis ein, zwei Uhr"). Until
           then the LATEST day stood on the row, and a tour begun on the
           21st read as one of the 22nd. The earliest day is the sort key
           too, so the row keeps its place among the days it started in. */
        if (d !== null && (t.tag === '' || d < t.tag)) t.tag = d;
        // A RENAME REACHES EVERY POINT, but a queue that has not drained
        // may hold both spellings for a moment. The later day wins, so the
        // row is called what the outing was called last.
        if (d !== null && (t.last === undefined || d >= t.last)) { t.name = name; t.last = d; }
        const place = (p?.municipality ?? '').trim();
        if (place !== '') t.places.add(place);
    }

    const rows = [...tours.entries()].map(([key, t]) => {
        const names = [...t.places];
        return {
            tour: key,
            // What the row SHOWS. For an id-less tour the two are the same
            // string; showDayList reads label ?? tour either way.
            label: t.name,
            tag: t.tag,
            orte: names.slice(0, 2).join(', ') + (names.length > 2 ? ' …' : ''),
            // The recorder's name for a foreign outing, null for one's own.
            owner: !t.own && t.owners.size > 0 ? [...t.owners].join(', ') : null,
        };
    });
    /* SOMEBODY ELSE'S DAY GETS A KEY OF ITS OWN (26.09.2026:
       „nur Finns Punkte zeigen"). Picked by its date, the row brought the
       whole day — one's own outing of that day included — while it named
       Finn. With „d:<day>" it picks what it gathered and nothing else:
       the tourless finds of others on that day (othersDayHolds). One's
       own day keeps its date — it has always meant the whole day. Only
       here and not in collectDays: a key needs a page with a tour filter,
       and Organisieren has none; there a row still picks its date. */
    rows.push(...collectDays(bare, dayOfPoint, ownerOf).map((r) => r.owner === null ? r
        : { ...r, tour: othersDayKey(r.tag), label: readableDay(r.tag) }));

    // Days sort within themselves as before; a tour and a day row on the
    // same day put the tour first — the name is the more precise answer.
    // (A keyed day of others sorts among the tours of its date, after
    // them: it was pushed last, and the sort is stable.)
    return rows.sort((a, b) => b.tag.localeCompare(a.tag)
        || (a.tour ? -1 : 0) - (b.tour ? -1 : 0));
}

/** The key of somebody else's day — „d:2026-09-26" (see collectTours). */
export function othersDayKey(day) {
    return 'd:' + day;
}

/** The day inside such a key, or null for any other key. */
export function othersDayOf(key) {
    const s = String(key ?? '');
    return /^d:\d{4}-\d{2}-\d{2}$/.test(s) ? s.slice(2) : null;
}

/**
 * Does the point belong to the day of others that `key` names?
 *
 * THE SAME THREE QUESTIONS collectTours asked when it built the row — no
 * tour, somebody else's, that day — so the key picks exactly what the row
 * gathered. One's own points never: a day with a single own tourless
 * point is one's own day and gets no key at all.
 *
 * @param {(p: object) => ?string} ownerOf  the caller's „whose" — the
 *        very function it handed collectTours
 * @param {?string} day  the point's day; a walk passes its own (its start)
 */
export function othersDayHolds(point, key, ownerOf, day = dayOf(point)) {
    const wanted = othersDayOf(key);
    return wanted !== null
        && (point?.tour ?? '').trim() === ''
        && !!ownerOf(point)
        && day === wanted;
}

/** A moment as one comparable string, „YYYY-MM-DD HH:MM[:SS]" — or null. */
function momentKey(raw) {
    const key = String(raw ?? '').replace('T', ' ').slice(0, 19);
    return key.length >= 16 ? key : null;
}

/**
 * The walks of one day a find lies on.
 *
 * HERE AND NOT IN map.js since 26.09.2026: the map decides by it which
 * walks a filter keeps (visibleTracks), and the rule follows the tours
 * collected above. Here tests/timerange.mjs can hold it.
 *
 * BY TOUR FIRST (23.09.2026: „Textfilter ‚bol', und mir wird
 * trotzdem die Wegstrecke vom 20.09. angezeigt — auf ihr gibt es keinen
 * Treffer"). The one „bol" of that day, a Butyriboletus at 8:55, belonged
 * to the first outing of the morning, which had no recording; the walk on
 * the map was the second outing's, from 9:24. With no walk holding 8:55
 * the nearest in time was taken — and a find of another outing lit it.
 * A find that carries a tour now lies only on walks of that same tour,
 * or on none: a tour without a recording has no walk to light.
 *
 * THEN BY PERSON (26.09.2026: „mach alle drei", after it was
 * named to him). A tour is somebody's own, so the rule above never
 * crossed people — but a find WITHOUT a tour was matched by the clock
 * alone: Finn's tourless find at two o'clock lit the maintainer's walk from
 * one to three, and under „nur Finns Punkte" the maintainer's walk stood on
 * the map beside them. It lies now only on its own person's walks — own
 * finds and own walks carry no owner_id, `me` stands in for both. AN
 * EXCURSION IS ONE WALK FOR ALL: a find made on it lies on the
 * excursion's walks too, whoever recorded them — the guests record
 * nothing, and they walked the leader's way.
 *
 * Without a tour (the stock from before tours): the walks whose span holds
 * its moment, failing that the nearest — but only within half an hour, a
 * find two hours off a walk was not found on it. A find with a day but no
 * time of day lies on all of them — there is nothing to tell them apart by.
 *
 * @param {object[]} walks  the walks of the find's day
 * @param {?number} me  the viewer's account id
 */
const WALK_NEAREST_MS = 30 * 60 * 1000;
export function walksOf(walks, point, me = null) {
    const tourId = String(point.tour_id ?? '').trim();
    if (tourId !== '') {
        return walks.filter(w => String(w.tour_id ?? '').trim() === tourId);
    }
    const person = point.owner_id ?? me;
    const excursion = point.excursion_id ?? null;
    walks = walks.filter(w => (w.owner_id ?? me) === person
        || (excursion !== null && w.excursion_id === excursion));
    const m = momentKey(point.found_at || point.created_at);
    if (m === null) return walks;
    const held = walks.filter(w => {
        const from = momentKey(w.started_at), to = momentKey(w.ended_at) ?? from;
        return from !== null && m >= from && m <= to;
    });
    if (held.length > 0) return held;
    const ms = Date.parse(m.replace(' ', 'T'));
    let nearest = null, gap = Infinity;
    for (const w of walks) {
        const from = momentKey(w.started_at), to = momentKey(w.ended_at) ?? from;
        if (from === null) continue;
        const d = Math.min(Math.abs(ms - Date.parse(from.replace(' ', 'T'))),
                           Math.abs(ms - Date.parse(to.replace(' ', 'T'))));
        if (d < gap) { gap = d; nearest = w; }
    }
    return nearest && gap <= WALK_NEAREST_MS ? [nearest] : [];
}

/**
 * Build the time filter into a box.
 *
 * IT STANDS OPEN, ALWAYS (the call, 17.08.2026). Until then a chip
 * carried the current range and unfolded the fields on click — a sensible
 * economy back when the whole filter had to fit above the fold. Since the
 * filter section scrolls on its own, that economy bought nothing and cost
 * a press before every date: the two calendars now simply stand there.
 *
 * WHAT THE CHIP ALSO DID was clear the range — that was its second click.
 * Without it there had to be a way back, so a clear button sits at the end
 * of the row, and only when something is set: a button that clears nothing
 * is furniture.
 *
 * @param {object} o
 * @param {HTMLElement} o.root  the empty box
 * @param {() => {from: string, to: string}} o.get
 * @param {(from: string, to: string) => void} o.set  called on every change
 * @param {() => Array<{tag: string, tour?: string, label?: string}>} o.days
 *        the tour and day list, fresh on every opening (collectTours — or
 *        collectDays, then no row carries a name). 'tour' is the KEY the
 *        filter works with, 'label' what a person reads; for an id-less
 *        tour the two are the same string.
 * @param {() => string} [o.getTour]  the active tour filter — pages
 *        without a tour filter (Organisieren) simply leave both away, and a
 *        tour row then falls back to its day
 * @param {(key: string) => void} [o.setTour]  gets the KEY, not the label
 * @param {(key: string) => ?string} [o.ownerFallback]  the person of a
 *        foreign tour key that was never picked this visit (restored
 *        filter) — null for one's own or unknown
 * @returns {{refresh: () => void, labelOf: (key: string) => string,
 *            personOf: (key: string) => ?string}}
 *          refresh for changes from outside, labelOf to name a key,
 *          personOf to say whose it is
 */
export function buildTimeFilter({ root, get, set, days, years, getTour, setTour, labelFallback, ownerFallback }) {
    root.textContent = '';

    /* TWO ROWS (14.09.2026: „der Tourknopf und die zwei
       Datumsfelder werden auf dem Handy zu breit, jetzt wo der Tourname
       mit Name steht — mach da zwei Zeilen draus"). The tour row first:
       it is one press, the date range is two calendars, and whoever
       checks what they brought back today should not have to get past
       two date fields first. The clear button stands beside the tour
       chip — it clears the whole time filter, tour and range alike, and
       on the tour row it leaves the two calendars their full width. */
    const fields = document.createElement('div');
    fields.className = 'time-filter-fields';
    const range = document.createElement('div');
    range.className = 'time-filter-fields time-filter-range';

    const dayButton = document.createElement('button');
    dayButton.type = 'button';
    dayButton.className = 'filter-chip time-filter-tour';
    // The word in a span of its own, so a long name („Tagestour
    // (the maintainer)") can be cut with an ellipsis instead of pushing the
    // row apart — a flex button cannot cut its bare text.
    const dayLabel = document.createElement('span');
    dayLabel.className = 'time-filter-tour-name';
    dayLabel.textContent = t('Letzte Tour …');
    dayButton.append(dayLabel);
    fields.append(dayButton);

    const field = (caption) => {
        const labelEl = document.createElement('label');
        labelEl.className = 'time-filter-field';
        const span = document.createElement('span');
        span.textContent = caption;
        const input = document.createElement('input');
        input.type = 'date';
        labelEl.append(span, input);
        range.append(labelEl);
        return input;
    };
    // THE CAPTIONS ARE USER TEXT, not identifiers — German, like every
    // word the user reads. The English-schema move once swept them up
    // („from"/„to" stood over the fields for a day, looking like code).
    const fromField = field(t('von'));
    /* THE ARROW BETWEEN THE TWO (18.09.2026: „zwischen von und
       bis eine Schaltfläche, die mir das Datum bei von nach bis kopiert —
       so dass ich dort weitermachen kann"). One day is the usual range:
       whoever looks at a single tour types the day, presses the arrow and
       is done; whoever wants a week types the day, presses the arrow and
       moves the end. The focus goes to „bis" for exactly that second
       case. Nothing to press while „von" is empty. */
    const copyButton = document.createElement('button');
    copyButton.type = 'button';
    copyButton.className = 'filter-chip time-filter-copy';
    copyButton.title = t('Datum von „von" nach „bis" übernehmen');
    copyButton.setAttribute('aria-label', t('Datum von „von" nach „bis" übernehmen'));
    copyButton.append(icon('arrow-right'));
    range.append(copyButton);
    const toField   = field(t('bis'));

    const clearButton = document.createElement('button');
    clearButton.type = 'button';
    clearButton.className = 'filter-chip time-filter-clear';
    clearButton.title = t('Zeitfilter aufheben');
    clearButton.setAttribute('aria-label', t('Zeitfilter aufheben'));
    clearButton.append(icon('close'));
    fields.append(clearButton);

    /* THE YEAR BAND (19.09.2026: „bei Zeit hätte ich gerne wie
       bei Höhe einen Regler mit zwei Anfassern, der in Jahren springt —
       das älteste Jahr links, das aktuellste rechts, so dass ich mir die
       Funde aus einem Jahr oder aus zweien recht schnell anzeigen lassen
       kann"). The same two handles as the elevation band, drawn with the
       same classes — on year BOUNDARIES: a handle at position i stands on
       the first of January of the i-th year of the stock, so one year is
       one step wide and the two handles never meet; the elevation rule
       „always one step apart" holds here without any exception. Both at
       the ends means every year, which is the empty filter.

       THE FIELDS STAY THE TRUTH. A step of the band writes the two dates
       into „von" and „bis"; a typed date lands the handles on the years
       around it, and the caption then says the years only when the
       fields sit exactly on their boundaries — otherwise the fields say
       it better than a rounded caption could. `years` names the span of
       the stock, [oldest, newest], or null while there is none. */
    const band = document.createElement('div');
    band.className = 'band-slider time-filter-years';
    const bandTrack = document.createElement('div');
    bandTrack.className = 'band-track';
    const bandSpan = document.createElement('div');
    bandSpan.className = 'band-span';
    bandTrack.append(bandSpan);
    const yearHandle = (label) => {
        const el = document.createElement('input');
        el.type = 'range';
        el.className = 'band-handle';
        el.min = '0';
        el.step = '1';
        el.setAttribute('aria-label', label);
        return el;
    };
    const fromYear = yearHandle(t('Erstes Jahr'));
    const toYear = yearHandle(t('Letztes Jahr'));
    /* THE CAPTION STANDS ABOVE THE TRACK, like „von" and „bis" over their
       fields (19.09.2026: „die Überschrift bei dem Regler oben
       drüber — unten kommt dann noch ‚ohne Fundzeit', und da drüber steht
       dann 2025, das ist irgendwie blöd"): the word „Jahre" left, the
       chosen years right, in the captions' own small type — so the row
       reads as one more field of the filter and the band ends before the
       checkbox with air, not with a stray number. */
    const yearCaption = document.createElement('div');
    yearCaption.className = 'time-filter-years-caption';
    const yearWord = document.createElement('span');
    yearWord.textContent = t('Jahre');
    const yearValue = document.createElement('span');
    yearValue.className = 'time-filter-years-value';
    yearCaption.append(yearWord, yearValue);
    band.append(bandTrack, fromYear, toYear);
    band.hidden = true;
    yearCaption.hidden = true;

    const yearOf = (day) => Number(String(day).slice(0, 4));

    function paintYears() {
        const span = years?.() ?? null;
        if (!span) { band.hidden = true; yearCaption.hidden = true; return; }
        const [min, max] = span;
        const n = max - min + 1;
        fromYear.max = String(n);
        toYear.max = String(n);
        const { from, to } = get();
        let i = from !== '' ? Math.min(Math.max(yearOf(from) - min, 0), n - 1) : 0;
        let j = to !== '' ? Math.min(Math.max(yearOf(to) - min + 1, 1), n) : n;
        if (j <= i) j = i + 1;
        fromYear.value = String(i);
        toYear.value = String(j);
        bandSpan.style.left = (i / n * 100) + '%';
        bandSpan.style.right = (100 - j / n * 100) + '%';
        // At the right end the lower grip needs to be on top, otherwise the
        // upper one covers it and the band cannot be widened again.
        const atEnd = i >= n - 1;
        fromYear.style.zIndex = atEnd ? '4' : '2';
        toYear.style.zIndex = atEnd ? '2' : '4';
        const all = from === '' && to === '';
        const exact = from === `${min + i}-01-01` && to === `${min + j - 1}-12-31`;
        // The span stands beside „Alle Jahre", so the scale of the track can
        // be read off without dragging.
        yearValue.textContent = all ? t('Alle Jahre') + ' · ' + (n === 1 ? String(min) : `${min} – ${max}`)
            : exact ? (j - i === 1 ? String(min + i) : `${min + i} – ${min + j - 1}`)
            : '';
        fromYear.title = yearValue.textContent;
        toYear.title = yearValue.textContent;
        band.hidden = false;
        yearCaption.hidden = false;
    }

    const yearMoved = (moved) => {
        const span = years?.() ?? null;
        if (!span) return;
        const [min, max] = span;
        const n = max - min + 1;
        let i = Number(fromYear.value);
        let j = Number(toYear.value);
        // One step apart, the dragged one keeps its value — see drawElevation.
        if (j - i < 1) {
            if (moved === fromYear) {
                j = Math.min(n, i + 1);
                i = Math.min(i, j - 1);
            } else {
                i = Math.max(0, j - 1);
                j = Math.max(j, i + 1);
            }
        }
        // A dragged band replaces a chosen tour, like a typed range.
        setTour?.('');
        if (i === 0 && j === n) set('', '');
        else set(`${min + i}-01-01`, `${min + j - 1}-12-31`);
        refresh();
    };
    fromYear.addEventListener('input', () => yearMoved(fromYear));
    toYear.addEventListener('input', () => yearMoved(toYear));

    root.append(fields, range, yearCaption, band);

    function isSet() {
        const { from, to } = get();
        return from !== '' || to !== '' || (getTour?.() ?? '') !== '';
    }

    /* WHAT A KEY IS CALLED. The filter holds „t:9f3c…" or „e:12", and
       neither is a word anybody wants on a button. The list knows both —
       every row carries key and label — so the answer is written down as
       the list is drawn, and the button reads it off.

       REMEMBERED RATHER THAN LOOKED UP FRESH: days() walks the whole stock,
       and refresh() runs on every change of the filter. A key can only
       become current by being picked out of that very list — EXCEPT a
       key restored from the remembered filter (30.08.2026): that one was
       picked in an earlier visit, so labelFallback lets the caller
       derive its name from the stock instead of showing „t:9f3c…". */
    const labels = new Map();
    // Key -> recorder's name, for the rows that are somebody else's.
    const owners = new Map();

    function labelOf(key) {
        return labels.get(key) ?? labelFallback?.(key) ?? key;
    }

    // Whose a key's outing or day is — null for one's own.
    function personOf(key) {
        return owners.get(key) ?? ownerFallback?.(key) ?? null;
    }

    function refresh() {
        const { from, to } = get();
        fromField.value = from;
        toField.value   = to;
        // The button carries the chosen tour's name — with a name filtered,
        // both date fields stand empty, and without this word the row would
        // not say what it is doing.
        const tour = getTour?.() ?? '';
        // A chosen foreign tour keeps its person on the chip: two outings
        // called „Tagestour" would otherwise read the same once picked.
        // A RESTORED key was never picked this visit, so the map above is
        // empty for it — the caller derives the person from the stock,
        // the same way labelFallback derives the name.
        const by = personOf(tour);
        dayLabel.textContent = tour !== '' ? labelOf(tour) + (by ? ' (' + by + ')' : '') : t('Letzte Tour …');
        dayButton.classList.toggle('is-active', tour !== '');
        clearButton.hidden = !isSet();
        copyButton.disabled = from === '';
        paintYears();
    }

    copyButton.addEventListener('click', () => {
        const from = fromField.value;
        if (from === '') return;
        // A typed range replaces a chosen tour — see the change handler.
        setTour?.('');
        set(from, from);
        refresh();
        toField.focus();
    });

    clearButton.addEventListener('click', () => {
        setTour?.('');
        set('', '');
        refresh();
    });

    dayButton.addEventListener('click', () => {
        const rows = days();
        for (const row of rows) {
            if (row.tour) labels.set(row.tour, row.label ?? row.tour);
            if (row.tour && row.owner) owners.set(row.tour, row.owner);
        }
        showDayList(dayButton, rows, (row) => {
            if (row.tour && setTour) {
                // Choosing a name replaces the range, and the other way
                // round — the two would otherwise intersect to nothing.
                set('', '');
                setTour(row.tour);
            } else {
                setTour?.('');
                set(row.tag, row.tag);
            }
            refresh();
        });
    });

    for (const f of [fromField, toField]) {
        f.addEventListener('change', () => {
            let from = fromField.value;
            let to   = toField.value;
            // Do not reject a twisted input, turn it around: Whoever types
            // the end first does not mean the empty set.
            if (from !== '' && to !== '' && from > to) [from, to] = [to, from];
            // A typed range replaces a chosen tour — see the day list.
            setTour?.('');
            set(from, to);
            refresh();
        });
    }

    refresh();
    // labelOf goes out too: the sentence under the filter names the chosen
    // outing as well, and it must not spell out a key either — and
    // personOf with it, for the day of others, whose sentence names Finn.
    return { refresh, labelOf, personOf };
}

/**
 * The day list as a flyout on the button.
 *
 * Its own shell instead of popupMenu(): The list scrolls and grows while
 * doing so, and a menu that closes on scrolling (that is how popupMenu
 * holds it, so it does not stay standing next to its anchor) would be
 * exactly wrong here.
 */
function showDayList(anchor, days, onSelect) {
    if (days.length === 0) return;
    /* A second tap on the button closes the list (25.09.2026) — the rule
       of popupMenu and infoWindow in dialogs.js: the button's own
       pointerdown is not „elsewhere", so its click lands here instead of
       closing the list and opening it again at once. */
    if (openDayList) {
        const same = openDayList.anchor === anchor;
        openDayList.close();
        if (same) return;
    }

    const windowEl = document.createElement('div');
    windowEl.className = 'popup-menu day-list';
    windowEl.setAttribute('role', 'menu');
    windowEl.tabIndex = -1;

    let shown = 0;
    /* THE CAPTION COUNTS ONLY ONE'S OWN ROWS (26.09.2026). „Letzte Tour"
       answers what I just brought home — so a friend's row never wears it,
       and a friend's row above mine must not push my last outing down to
       „Vorletzte". Counted across the refills, hence out here. */
    let ownShown = 0;
    const addMore = (howMany) => {
        const end = Math.min(days.length, shown + howMany);
        for (; shown < end; shown++) {
            const day = days[shown];
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'popup-menu-entry';
            button.setAttribute('role', 'menuitem');

            // A NAMED TOUR SHOWS ITS NAME, with day and places underneath —
            // the name says what is meant, the date whether it is right.
            // The two most recent BARE days keep their "Letzte/Vorletzte
            // Tour" caption for the same reason as always: whoever was out
            // yesterday sees from "MI 06.08.2026" that the "letzte Tour" is
            // not from today. (Bare rows with named ones above them still
            // count from the top — the caption means recency, not kind —
            // but only one's own rows count, see ownShown.)
            // A keyed day of others („d:…") is drawn as a day, not as a
            // named tour — its label IS the date, and would stand twice.
            if (day.tour && othersDayOf(day.tour) === null) {
                const name = document.createElement('span');
                /* THE LABEL MAY DIFFER FROM THE KEY (26.08.2026). A tour
                   IS its name, so for one the two are the same thing. An
                   excursion is not: its key is „e:<id>", because two
                   excursions may share a name and neither of them is the
                   name of a tour. What the row shows is the label. */
                name.textContent = day.label ?? day.tour;
                const data = document.createElement('span');
                data.className = 'item-data';
                // A FOREIGN OUTING NAMES ITS PERSON FIRST — „von Ben" is
                // the qualifier that tells two „Tagestour" rows apart; the
                // day and the places come after. The row wears the quiet
                // tone of the shared lists (app.css, .day-list .is-foreign).
                data.textContent = [
                    day.owner ? t('von %s', day.owner) : '',
                    day.tag ? readableDay(day.tag) : '',
                    day.orte ?? '',
                ].filter(Boolean).join(' · ');
                if (day.owner) button.classList.add('is-foreign');
                button.append(name, data);
            } else if (day.owner) {
                // SOMEBODY ELSE'S DAY: the date is its name — there is no
                // other, and „Vorletzte Tour" would claim it as mine —, the
                // person first beneath it, as on a foreign tour. On the map
                // it carries its key and picks only those finds; in
                // Organisieren, without a tour filter, it picks its date.
                const dateEl = document.createElement('span');
                dateEl.textContent = readableDay(day.tag);
                const data = document.createElement('span');
                data.className = 'item-data';
                data.textContent = [t('von %s', day.owner), day.orte ?? '']
                    .filter(Boolean).join(' · ');
                button.classList.add('is-foreign');
                button.append(dateEl, data);
            } else if (ownShown <= 1) {
                const name = document.createElement('span');
                name.textContent = ownShown === 0 ? t('Letzte Tour') : t('Vorletzte Tour');
                const data = document.createElement('span');
                data.className = 'item-data';
                data.textContent = readableDay(day.tag)
                    + (day.orte ? ' · ' + day.orte : '');
                button.append(name, data);
            } else {
                const dateEl = document.createElement('span');
                dateEl.textContent = readableDay(day.tag);
                button.append(dateEl);
                if (day.orte) {
                    const placeEl = document.createElement('span');
                    placeEl.className = 'item-data';
                    placeEl.textContent = day.orte;
                    button.append(placeEl);
                }
            }
            if (!day.owner) ownShown++;

            button.addEventListener('click', () => { close(); onSelect(day); });
            windowEl.append(button);
        }
    };
    addMore(FIRST);

    // Load more while scrolling — once the rest is under two window heights.
    windowEl.addEventListener('scroll', () => {
        if (shown >= days.length) return;
        if (windowEl.scrollTop + windowEl.clientHeight * 2 >= windowEl.scrollHeight) {
            addMore(REFILL);
        }
    });

    document.body.append(windowEl);
    const a = anchor.getBoundingClientRect();
    const m = windowEl.getBoundingClientRect();
    windowEl.style.left = Math.max(8, Math.min(a.left, window.innerWidth - m.width - 8)) + 'px';
    let top = a.bottom + 4;
    if (top + m.height > window.innerHeight - 8) {
        top = Math.max(8, a.top - m.height - 4);
    }
    windowEl.style.top = top + 'px';

    function close() {
        document.removeEventListener('pointerdown', elsewhere, true);
        document.removeEventListener('keydown', key, true);
        window.removeEventListener('resize', close);
        windowEl.remove();
        if (openDayList?.windowEl === windowEl) openDayList = null;
    }
    const elsewhere = (e) => {
        if (windowEl.contains(e.target) || anchor.contains(e.target)) return;
        close();
    };
    const key       = (e) => { if (e.key === 'Escape') close(); };

    document.addEventListener('pointerdown', elsewhere, true);
    document.addEventListener('keydown', key, true);
    window.addEventListener('resize', close);
    // NO closing on scroll, unlike with popupMenu: Here you scroll
    // INSIDE the window, and that is the operation itself.
    openDayList = { anchor, windowEl, close };

    windowEl.focus();
}

/** The day list standing right now — for the second tap on its button. */
let openDayList = null;
