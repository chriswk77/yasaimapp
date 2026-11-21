/**
 * Recognizing open identifications by the name.
 *
 * WHAT FOR. "What have I not identified yet?" is the question for one's own
 * work list — the finds where something remains to be done. It sits in no
 * attribute and no column, but in the NAME itself: Whoever is unsure writes
 * it there, and has always done so in the same handful of abbreviations.
 *
 *   cf.      confer, "compare with" — the species is presumed, not proven
 *   aff.     affinis, "closely related" — near this species, but not it
 *   sp.      species, the genus stands, the species does not
 *   s. l.    sensu lato, in the wide sense — so precisely not the narrow species
 *
 * SINCE 23.08.2026 THE FILTER ASKS FINER (the wish): besides the
 * collective "nur nicht bestimmte" every abbreviation FAMILY can be
 * checked on its own — sp. separate from cf. separate from s. l. — and
 * the point's own field "Bestimmungssicherheit" joins in. The families
 * are the unit, not the spellings: whoever checks "sp." means sp, spp,
 * spec and species at once; nobody wants to tell those apart.
 *
 * A MODULE OF ITS OWN AND NOT IN species.js. The one there needs 'caches' and
 * with it a browser; here stands pure calculation, and only that way can it
 * be checked in tests/identification.mjs without a browser. The same
 * separation as with polygon.js and timerange.js.
 *
 * SEVERAL SPELLINGS, BECAUSE THEY EXIST. At the desk you type "cf.", in the
 * forest "cf", an old list says "Cfr." — that is the same note. Accepting
 * only one spelling would yield a work list that silently omits half, and
 * that is worse than none.
 *
 * WORD BY WORD, NOT AS A SUBSTRING. "sp" is also inside "splendens", "sl"
 * inside "Suillus" and "cf" inside many an author name. A plain includes()
 * would declare half the collection unidentified — and because the result
 * looks plausible, it would go unnoticed for a long time.
 */

/**
 * The families, in the order the filter shows them. 'value' is what the
 * filter state stores, 'label' what the checkbox says.
 */
export const OPEN_MARKS = [
    { value: 'sp',  label: 'sp.'   },
    { value: 'cf',  label: 'cf.'   },
    { value: 'aff', label: 'aff.'  },
    { value: 'sl',  label: 's. l.' },
    // The aggregate — a group of species told apart only under the
    // microscope, or not at all in the field: „Galerina marginata agg."
    // (18.09.2026: „das Kürzel agg. für Aggregat bitte noch
    // dazu"). Open like the others: the find has a name, not a species.
    { value: 'agg', label: 'agg.'  },
    /* SENSU STRICTO IS NOT OPEN (18.09.2026: „das brauchen wir
       natürlich auch noch zum Filtern"). The other five say the name is
       wider than a species or not certain; „s. str." says the opposite —
       the species in the narrow sense, as tightly as a name can be meant.
       It gets its checkbox like the others, so one can filter for it,
       but the collective hook „nur nicht bestimmte" must not take it:
       open: false keeps it out of notIdentified(). */
    { value: 'sstr', label: 's. str.', open: false },
];

/** Does this family say the identification is open? All but s. str. */
function familyOpen(value) {
    return OPEN_MARKS.find((k) => k.value === value)?.open !== false;
}

/** Abbreviations that stand on their own, per family. */
const SINGLE = {
    cf: ['cf', 'cfr'],                      // confer
    // affinis, "closely related". HERE IT SHOWS WHY THE COMPARISON IS WORD
    // BY WORD: "affinis" is itself a valid species epithet — Russula affinis,
    // Lactarius affinis. Searched as a substring, "aff" turned each of these
    // names into an open identification, that is, a finished identification
    // into its opposite. As a whole word it only matches the note "aff." and
    // never the name.
    aff: ['aff', 'affin'],
    sp: ['sp', 'spp', 'spec', 'species'],   // genus yes, species no
    sl: ['sl'],                             // sensu lato, written as one
    agg: ['agg', 'aggr'],                   // aggregatum, both short forms
    sstr: ['sstr', 'ss'],                   // s.str. and ss. written as one
};

/**
 * Abbreviations in two parts.
 *
 * Needed because the periods become spaces beforehand: "s.l." turns into
 * "s l", and only as a PAIR is that again what was meant.
 *
 * ON THEIR OWN, "s" AND "l" MUST NOT COUNT. "L." is the abbreviation for
 * Linné and stands behind every second old name — "Amanita muscaria L."
 * would otherwise be unidentified, although it says the opposite: a name
 * with an author.
 */
const PAIRS = {
    sl: [['s', 'l'], ['s', 'lat'], ['sensu', 'lato']],
    /* "c.f." falls apart into the letters c and f, like "s.l." into s
       and l — only as a pair is it the note again (the maintainer named the
       spelling on 23.08.2026). The trade-off is the same as with s/l
       and Linné: hand-typed author INITIALS "C. F." would match too.
       That is accepted — the suggestions never write authors into the
       title, so initials only get there by hand, and rarer than a
       sloppy c.f. */
    cf: [['c', 'f']],
    // „s. str.", „s. s.", „sensu stricto" — the same spellings s. l. has,
    // with the same Linné trade-off for the bare pair of letters.
    sstr: [['s', 'str'], ['s', 'stricto'], ['sensu', 'stricto'], ['s', 's']],
};

/**
 * Which families of open marks this name carries.
 *
 * ONLY THE NAME, never the find fields. Species lists carry species-typed
 * FIELDS besides the title — "Substrat / Wirt", "Begleitvegetation" — and
 * an open note there talks about the NEIGHBOUR, not the find: a finished
 * "Russula virescens" with "Quercus sp." as accompanying vegetation means
 * "some oak stood nearby", not "this find is unidentified". Searching the
 * fields would drag exactly such finished finds into the work list.
 * (the maintainer read the shorter version of this sentence as nonsense on
 * 23.08.2026 — rightly, it sounded as if "sp." did not mean open.)
 *
 * A NAMELESS POINT DOES NOT COUNT AS UNIDENTIFIED. A point that carries
 * only its coordinate in the title is usually not a find at all but a
 * place — a parking lot, a waypoint, a reminder. Throwing it into the work
 * list would mean filling it with what was never meant to be identified.
 *
 * @returns {string[]} family values ('sp', 'cf', …), empty when none
 */
export function openMarksIn(name) {
    if (typeof name !== 'string') return [];

    // Punctuation to spaces: That way "cf." and "cf" are the same, and
    // "s.l." falls apart into the two parts the pair set catches again.
    const words = name.toLowerCase()
        .replace(/[.,;:!?()[\]{}"'«»]/g, ' ')
        .split(/\s+/)
        .filter(Boolean);

    const found = [];
    for (const { value } of OPEN_MARKS) {
        const singles = SINGLE[value] ?? [];
        let hit = words.some((word) => singles.includes(word));

        for (const [a, b] of PAIRS[value] ?? []) {
            if (hit) break;
            for (let i = 0; i < words.length - 1 && !hit; i++) {
                hit = words[i] === a && words[i + 1] === b;
            }
        }
        if (hit) found.push(value);
    }
    return found;
}

/** Does this name carry ANY note that the identification is open? */
export function notIdentified(name) {
    return openMarksIn(name).some(familyOpen);
}

/**
 * The name a find's title vouches for as a suggestion — for „Schon
 * gefundene Arten zuerst vorschlagen" (familiarSpecies in map.js).
 *
 * Genus and epithet — and where the title calls it an aggregate or the
 * species in the narrow sense, that word as well, in one spelling
 * (26.09.2026). He maps „Candolleomyces candolleana agg." ahead
 * of the reference lists: TaxRef knows neither the aggregate nor the
 * species s. str. yet, GBIF neither, so only what was typed can offer it
 * again. Cut to the binomial, the suggestion named the species in the
 * narrow sense — the very thing the title declined to claim. „agg." and
 * „s. str." look ahead to how the lists will split a name; the other marks
 * do not: a title with cf., aff. or sp. where the epithet belongs offers
 * nothing, and one with a mark behind the binomial (s. l., cf.) offers the
 * binomial, as before.
 *
 * @returns {string|null}
 */
export function familiarName(title) {
    const m = /^([A-Z][a-zäöüß-]+) ([a-z][a-zäöüß-]+)/.exec(title ?? '');
    if (!m || ['sp', 'spec', 'cf', 'aff', 'agg', 's', 'sensu', 'sect'].includes(m[2])) return null;
    const marks = openMarksIn(title);
    const qualifier = marks.includes('agg') ? ' agg.' : marks.includes('sstr') ? ' s. str.' : '';
    return m[1] + ' ' + m[2] + qualifier;
}

/**
 * The fixed find field the finer filter reads — its name as it stands in
 * includes/find_fields.php (field names are user-facing German and the
 * keys of point.extra at the same time). The options come along in
 * find_fields from the start data, so the checkboxes cannot drift apart
 * from what the form offers.
 */
export const CERTAINTY_FIELD = 'Bestimmungssicherheit';

/** The field value that means "not identified" — for the collective hook. */
const CERTAINTY_OPEN = 'unbestimmt';

/* The short forms the field carried before migration 0069 wrote them out.
   A device may hold points stored under the old words until its buckets
   refresh — and between deploying and applying the migration the whole
   stock still speaks them. Read-side the two mean the same. Deletable
   once 0069 has run and every device has synced. */
const CERTAINTY_LEGACY = {
    'mak. sicher': 'makroskopisch sicher',
    'mik. sicher': 'mikroskopisch sicher',
};

/**
 * Does a point pass the identification filter?
 *
 * OR ACROSS EVERYTHING CHECKED, like the threat categories: whoever ticks
 * "sp." and "unsicher" wants both work lists at once, not their overlap.
 * Nothing checked means the group is off — everything passes.
 *
 * The collective hook 'open' is the old "nur nicht bestimmte" plus the
 * field: any open mark in the title OR the field saying 'unbestimmt'.
 * Deliberately NOT 'unsicher' — that value states an identification, a
 * shaky one; it has its own checkbox for whoever wants it in the list.
 *
 * @param {{open: boolean, marks: string[], certainty: string[]}} sel
 * @param {string} title      the point's title
 * @param {string|undefined} certainty  the point's field value, if any
 */
export function identificationMatches(sel, title, certainty) {
    const open = !!sel.open;
    const marks = sel.marks ?? [];
    const chosen = sel.certainty ?? [];
    if (!open && marks.length === 0 && chosen.length === 0) return true;

    const inTitle = openMarksIn(title);
    let value = typeof certainty === 'string' ? certainty.trim() : '';
    value = CERTAINTY_LEGACY[value] ?? value;

    if (open && (inTitle.some(familyOpen) || value === CERTAINTY_OPEN)) return true;
    if (marks.some((m) => inTitle.includes(m))) return true;
    return chosen.includes(value) && value !== '';
}

/**
 * The „bearbeitet" condition (30.08.2026) — deliberately NOT a
 * fourth layer of identificationMatches: those three are OR-linked
 * degrees of identification, while this is a MATERIAL status and combines
 * as an AND („nicht bestimmte, die schon bearbeitet sind" is a sentence
 * one means). It lives in the same filter group because that is where
 * the maintainer put it, and in this file because map and Organisieren must
 * read the word the same way.
 *
 * '' means no condition; 'yes' wants cloud recordings on the find,
 * 'no' wants none. A point that carries no count (a shared one — the
 * recordings are the owner's) reads as not worked up.
 */
export function workedMatches(sel, recordings) {
    const wanted = sel.worked ?? '';
    if (wanted === '') return true;
    return wanted === 'yes' ? (recordings ?? 0) > 0 : !(recordings > 0);
}
