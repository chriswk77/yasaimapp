/**
 * Tests the detection of open determinations.
 *
 *   node tests/identification.mjs
 *
 * TWO SIDES, AND THE SECOND IS THE MORE IMPORTANT ONE. That "cf." gets
 * recognized you notice on the first try. That "Suillus" does NOT count as
 * undetermined although "sl" is hiding in it you never notice — you only
 * see a work list that is too long and take it for correct.
 *
 * The names below are real: they come from the inventory or are common
 * species where a substring comparison would misfire.
 */

import { notIdentified, openMarksIn, identificationMatches, familiarName }
    from '../assets/js/identification.js';

let failed = 0;
function check(b, what) {
    if (b) { console.log(`  ok    ${what}`); } else { failed++; console.log(`  FAILED: ${what}`); }
}

console.log('\nOpen determinations\n');

console.log('The abbreviations are recognized');
for (const name of [
    // confer, in all spellings that occur
    'Ramariopsis cf. luteo-ochracea',
    'Cortinarius cf anomalus',
    'Russula Cf. Vesca',
    'Inocybe CF. geophylla',
    'Amanita cfr. rubescens',
    'Russula c.f. vesca',
    // affinis, "closely related"
    'Russula aff. nigricans',
    'Lactarius aff nigricans',
    'Cortinarius Aff. Anomalus',
    'Entoloma affin. sericeum',
    // genus yes, species no
    'Scutellinia spec',
    'Amanita sp.',
    'Cortinarius sp',
    'Entoloma spp.',
    'Hygrocybe species',
    'Mycena SPEC.',
    // sensu lato
    'Russula s.l.',
    'Russula s. l.',
    'Russula sl',
    'Clitocybe s. lat.',
    'Armillaria sensu lato',
]) {
    check(notIdentified(name) === true, `„${name}"`);
}

console.log('\nAnd determined names stay untouched');
for (const name of [
    // Here the abbreviation hides IN THE MIDDLE of the word — the real
    // touchstone.
    'Inocybe splendens',        // sp in splendens
    'Suillus luteus',           // sl in Suillus
    'Sparassis crispa',         // sp at the start of the word
    'Boletus speciosus',        // spec at the start of the word
    // THE TOUCHSTONE FOR aff.: "affinis" is itself a valid epithet.
    // Searched as a substring, "aff" would turn a finished determination
    // into its opposite.
    'Russula affinis',
    'Lactarius affinis',
    'Inocybe affinis Peck',
    'Clavulinopsis helveola',   // sl right in the middle
    // The author Linné — "L." must not pass as half an "s. l.".
    'Amanita muscaria L.',
    'Boletus edulis Bull.',
    'Agaricus campestris L. s.str.',
    // From the real inventory
    'Hygrocybe conica',
    'Butyriboletus roseogriseus',
    'Amanita friabilis (Fundbericht von 2000)',
    // Not a name, but a place
    '49°36\'58.1"N 9°37\'38.6"E',
    '6F6H+QF',
    'Campingplatz Dschini?',
    '',
]) {
    check(notIdentified(name) === false, `„${name}"`);
}

console.log('\nNonsense does not crash');
for (const value of [null, undefined, 42, {}, []]) {
    check(notIdentified(value) === false, `${JSON.stringify(value) ?? String(value)} — yields false`);
}

console.log('\nThe families are told apart (the fine hooks, 23.08.2026)');
check(openMarksIn('Amanita sp.').join(',') === 'sp', '„Amanita sp." is the sp family');
check(openMarksIn('Mycena SPEC.').join(',') === 'sp', 'spec belongs to the same family — spellings are noise');
check(openMarksIn('Cortinarius cf anomalus').join(',') === 'cf', 'cf is its own family');
check(openMarksIn('Amanita cfr. rubescens').join(',') === 'cf', 'cfr too');
check(openMarksIn('Russula c.f. vesca').join(',') === 'cf', 'and the dotted c.f.');
check(openMarksIn('Russula aff. nigricans').join(',') === 'aff', 'aff is its own family');
check(openMarksIn('Clitocybe s. lat.').join(',') === 'sl', 's. lat. is the sl family');
check(openMarksIn('Armillaria sensu lato').join(',') === 'sl', 'sensu lato too');
check(openMarksIn('Russula cf. sp.').join(',') === 'sp,cf', 'two notes, two families');
check(openMarksIn('Galerina marginata agg.').join(',') === 'agg', 'agg is its own family (18.09.2026)');
check(openMarksIn('Clitocybe rivulosa aggr.').join(',') === 'agg', 'aggr too');
// SENSU STRICTO (18.09.2026): its own family in every spelling — and NOT open.
for (const title of ['Boletus edulis s. str.', 'Boletus edulis s.str.', 'Boletus edulis sensu stricto',
                     'Boletus edulis s. s.', 'Boletus edulis ss.']) {
    check(openMarksIn(title).join(',') === 'sstr', `„${title}" is the s. str. family`);
}
check(notIdentified('Boletus edulis s. str.') === false, 's. str. is the narrow sense — not an open identification');
check(notIdentified('Boletus edulis s. l.') === true, 'while s. l. still is');
check(identificationMatches({ open: true, marks: [], certainty: [] }, 'Boletus edulis s. str.', undefined) === false,
    'the collective hook leaves s. str. alone');
check(identificationMatches({ open: false, marks: ['sstr'], certainty: [] }, 'Boletus edulis s. str.', undefined) === true,
    'its own checkbox finds it');
check(openMarksIn('Russula affinis').length === 0, 'the affinis touchstone holds per family too');

console.log('\nThe filter question (identificationMatches)');
const off = { open: false, marks: [], certainty: [] };
check(identificationMatches(off, 'Amanita muscaria', undefined) === true,
    'nothing checked lets everything pass');

const open = { open: true, marks: [], certainty: [] };
check(identificationMatches(open, 'Amanita sp.', undefined) === true,
    'the collective hook takes any title note');
check(identificationMatches(open, 'Russula virescens', 'unbestimmt') === true,
    'and the field value unbestimmt — clean title or not');
check(identificationMatches(open, 'Russula virescens', 'unsicher') === false,
    'but NOT unsicher: that states an identification, a shaky one — it has its own hook');
check(identificationMatches(open, 'Russula virescens', undefined) === false,
    'a clean title without the field stays out');

const onlySp = { open: false, marks: ['sp'], certainty: [] };
check(identificationMatches(onlySp, 'Amanita sp.', undefined) === true,
    'the sp hook takes sp titles');
check(identificationMatches(onlySp, 'Cortinarius cf anomalus', undefined) === false,
    'and lets cf titles pass by');

const mixed = { open: false, marks: ['cf'], certainty: ['unsicher'] };
check(identificationMatches(mixed, 'Cortinarius cf anomalus', undefined) === true
   && identificationMatches(mixed, 'Russula virescens', 'unsicher') === true,
    'checks are OR-linked, like the threat categories');
check(identificationMatches(mixed, 'Russula virescens', 'makroskopisch sicher') === false,
    'what matches no check stays out');

const level = { open: false, marks: [], certainty: ['makroskopisch sicher'] };
check(identificationMatches(level, 'Russula virescens', 'makroskopisch sicher') === true
   && identificationMatches(level, 'Russula virescens', undefined) === false,
    'a certainty hook means exactly its step — a point without the field never matches one');
check(identificationMatches(level, 'Russula virescens', 'mak. sicher') === true,
    'the short form from before 0069 still counts — device stores resync on their own time');

console.log('\nThe name a title offers as a suggestion (familiarName)\n');

// „agg." and „s. str." look ahead of the reference lists and are offered as
// written, in one spelling (26.09.2026): cut to the binomial,
// „Candolleomyces candolleana agg." offered the species in the narrow sense.
for (const [title, name] of [
    ['Candolleomyces candolleana agg.', 'Candolleomyces candolleana agg.'],
    ['Galerina marginata agg', 'Galerina marginata agg.'],
    ['Xerocomellus chrysenteron s. str.', 'Xerocomellus chrysenteron s. str.'],
    ['Xerocomellus chrysenteron s.str.', 'Xerocomellus chrysenteron s. str.'],
]) {
    check(familiarName(title) === name, `"${title}" offers "${name}"`);
}

// Everything else as before: the binomial, or nothing.
for (const [title, name] of [
    ['Boletus edulis', 'Boletus edulis'],
    ['Boletus edulis var. arenarius', 'Boletus edulis'],
    ['Russula ochroleuca s. l.', 'Russula ochroleuca'],
    ['Russula splendens', 'Russula splendens'],
]) {
    check(familiarName(title) === name, `"${title}" offers "${name}"`);
}
for (const title of ['Russula cf. vesca', 'Cortinarius cf anomalus', 'Scutellinia spec', 'Amanita sp.', 'Parkplatz', '']) {
    check(familiarName(title) === null, `"${title}" offers nothing`);
}

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
