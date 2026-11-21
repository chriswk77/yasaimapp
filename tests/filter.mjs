/**
 * Tests the shared point filter (assets/js/filter.js).
 *
 *   node tests/filter.mjs        (from the project directory)
 *
 * WHY THIS MODULE OF ALL THINGS NEEDS A TEST: it is the yardstick for
 * three more places. The sidebar and Organisieren filter with it, and
 * autolist_matches() on the server must give the same answer — otherwise
 * Organisieren shows a set that the auto list built from it afterwards carries
 * differently. What stands here is thus the specification the other side
 * is tested against (tests/autolists.php).
 *
 * THE EMPTY ROW is the case where it goes wrong first: at the end of the
 * list there is always one, so you can keep typing. If it counted, a
 * blank "enthält nicht" row would be the condition "nowhere contains
 * nothing" — and the map empty with one click.
 */

import {
    newRow, readTerms, matchTerms,
    writeTerms, termsFromText, TYPES,
    readAreas, areaMatches,
    regionsOf, municipalitiesOf, regionName, mtbOf,
    regionMatches, municipalityMatches, mtbMatches, mtbAlternatives,
    placeMatches,
    elevationMatches, elevationLabel, ELEVATION_MAX, ELEVATION_STEP,
    taxonomyMatches, taxonomyLabel, TAXONOMY_RANKS, TAXONOMY_DROPDOWN_MAX,
    taxonomyDerive,
    EDIBILITY_MARKS, readEdibility, edibilityMatches, responsibilityMatches,
    writeEdibility, edibilityFromText, dgfmWord,
} from '../assets/js/filter.js';

let failed = 0;
function check(b, what) {
    if (b) { console.log(`  ok    ${what}`); } else { failed++; console.log(`  FAILED: ${what}`); }
}

console.log('The starting position:');
check(newRow().type === 'contains',
    'a new row is set to „enthält" — the common case');
check(Object.keys(TYPES).length === 2,
    'there are exactly two kinds, otherwise the dropdown would have to offer more');

console.log('Matching:');
const haystack = 'boletus edulis fagus sylvatica wiesbaden';
check(matchTerms([{ type: 'contains', text: 'boletus' }], haystack),
    '„enthält" matches');
check(!matchTerms([{ type: 'contains', text: 'amanita' }], haystack),
    '„enthält" does not match when the word is missing');
check(matchTerms([{ type: 'contains_not', text: 'amanita' }], haystack),
    '„enthält nicht" matches when the word is missing');
check(!matchTerms([{ type: 'contains_not', text: 'boletus' }], haystack),
    '„enthält nicht" does not match when the word is there');
check(matchTerms([{ type: 'contains', text: 'BOLETUS' }], haystack),
    'upper and lower case are the same');
check(matchTerms([{ type: 'contains', text: 'wiesbaden' }], haystack),
    'the search covers the whole haystack, not just the title');

console.log('Multiple rows are AND-linked:');
check(matchTerms([
    { type: 'contains', text: 'boletus' },
    { type: 'contains_not', text: 'amanita' },
], haystack), 'both satisfied');
check(!matchTerms([
    { type: 'contains', text: 'boletus' },
    { type: 'contains', text: 'amanita' },
], haystack), 'one unsatisfied suffices for exclusion');

console.log('Comma means OR within a row:');
check(matchTerms([{ type: 'contains', text: 'boletus edulis, boletus aereus' }], haystack),
    'one of the alternatives present suffices');
check(!matchTerms([{ type: 'contains', text: 'amanita, russula' }], haystack),
    'none present means no match');
check(!matchTerms([{ type: 'contains_not', text: 'amanita, boletus' }], haystack),
    '„enthält nicht" with comma: ONE present already excludes');
check(matchTerms([{ type: 'contains_not', text: 'amanita, russula' }], haystack),
    '„enthält nicht" with comma matches when none is present');
check(matchTerms([
    { type: 'contains', text: 'boletus edulis, boletus aereus' },
    { type: 'contains', text: 'wiesbaden' },
], haystack), 'the OR stays inside its row — across rows it is still AND');
check(matchTerms([{ type: 'contains', text: ' , ,, ' }], haystack),
    'a row of bare commas has no alternatives and does not count');
check(matchTerms([{ type: 'contains_not', text: ' , ' }], haystack),
    'the same as „enthält nicht" — otherwise one comma would empty the map');

console.log('The empty row does not count:');
check(matchTerms([{ type: 'contains', text: '' }], haystack),
    'an empty „enthält" row filters nothing away');
check(matchTerms([{ type: 'contains_not', text: '' }], haystack),
    'and an empty „enthält nicht" row just as little — otherwise the map would be empty');
check(matchTerms([{ type: 'contains', text: '   ' }], haystack),
    'and one containing only spaces');
check(matchTerms([], haystack), 'without rows everything matches');

console.log('The place — drawn areas:');
// A square around the Brocken and one around nothing at all.
const square = [[51.7, 10.5], [51.9, 10.5], [51.9, 10.7], [51.7, 10.7]];
const far    = [[48.0, 8.0], [48.1, 8.0], [48.1, 8.1], [48.0, 8.1]];
check(areaMatches([], 51.8, 10.6), 'without areas everything matches');
check(areaMatches([square], 51.8, 10.6), 'inside the one area');
check(!areaMatches([square], 50.0, 10.6), 'outside stays out');
check(areaMatches([far, square], 51.8, 10.6),
    'several areas are alternatives — the second one suffices');
check(!areaMatches([far, square], 47.0, 9.0),
    'in none of them means out');
check(JSON.stringify(readAreas([square, 'kaputt', [[1, 2]], null])) === JSON.stringify([square]),
    'readAreas keeps only usable outlines — the readTerms of the place');
check(readAreas('kein Feld').length === 0, 'and nonsense yields an empty list');

console.log('The place, part two — region, municipality, map sheet:');
const stock = [
    { region: 'THU', municipality: 'Bornhagen' },
    { region: 'HES', municipality: 'Witzenhausen' },
    { region: 'THU', municipality: 'Uder' },
    { region: null, municipality: null },
];
check(JSON.stringify(regionsOf(stock)) === '["HES","THU"]',
    'the region list comes from the stock, distinct and sorted — null stays out');
check(JSON.stringify(municipalitiesOf(stock)) === '["Bornhagen","Uder","Witzenhausen"]',
    'the municipality suggestions likewise');
check(regionName('THU') === 'Thüringen' && regionName('TIR') === 'Tirol',
    'the codes are written out for display');
check(regionName('XYZ') === 'XYZ',
    'an unknown code shows itself — true, if unpolished');
check(regionMatches([], null), 'no checked region means no condition');
check(regionMatches(['THU', 'HES'], 'THU'), 'one of the checked ones suffices');
check(!regionMatches(['THU'], 'BAY'), 'a different region stays out');
check(!regionMatches(['THU'], null),
    'a point whose lookup has not run falls out of a set condition');
check(municipalityMatches(['Bornhagen'], 'Bornhagen') && !municipalityMatches(['Bornhagen'], 'Uder'),
    'municipalities compare exactly');
check(mtbMatches('', '4625', '24'), 'an empty sheet field means no condition');
check(mtbMatches('4625', '4625', '243'), 'the bare sheet takes every quarter');
check(mtbMatches('4625/2', '4625', '243'), 'the quarter takes its sixteenths');
check(mtbMatches('4625/24', '4625', '243'), 'the sixteenth takes its parts');
check(!mtbMatches('4625/1', '4625', '243'), 'the wrong quarter stays out');
check(!mtbMatches('4626', '4625', '243'), 'the wrong sheet stays out');
check(!mtbMatches('4625/2', '4625', null), 'a point without quadrant only fits the bare sheet');
check(mtbMatches('9999, 4625 / 2', '4625', '243'),
    'comma is OR here too, and whitespace inside an entry does not count');
check(JSON.stringify(mtbAlternatives(' 4625 / 2 , , 58 ')) === '["4625/2","58"]',
    'the alternatives come out clean');
check(JSON.stringify(mtbOf([{ mtb: '4625', mtb_quadrant: '243' }, { mtb: '4625', mtb_quadrant: '243' },
        { mtb: '5915', mtb_quadrant: null }, { mtb: null }]))
        === '["4625/243","5915"]',
    'the sheet suggestions come from the stock, distinct — without quadrant the bare sheet');

console.log('The offers narrow along (placeMatches with except):');
const hessen = { lat: 50.0, lon: 8.7, region: 'HES', municipality: 'Wiesbaden', mtb: '5915', mtb_quadrant: '124' };
const cond = { areas: [], regions: ['THU'], municipalities: [], mtb: '' };
check(!placeMatches(cond, hessen),
    'the full test applies every condition — the Hessian falls to the THU checkmark');
check(placeMatches(cond, hessen, 'regions'),
    'except leaves out exactly the own condition: the region OFFERS still see the Hessian');
check(!placeMatches({ ...cond, mtb: '4625' }, hessen, 'regions'),
    'but the other conditions keep sieving the offer stock');

console.log('Reading tidies up:');
check(readTerms([{ type: 'contains', text: '  Buche  ' }])[0].text === 'Buche',
    'surrounding whitespace is trimmed');
check(readTerms([{ type: 'quatsch', text: 'x' }])[0].type === 'contains',
    'an unknown kind becomes „enthält" instead of an error');
check(readTerms([{ type: 'contains', text: '' }]).length === 0,
    'empty rows drop out — that is how you get rid of one');
check(readTerms('kein Feld').length === 0,
    'and nonsense yields an empty list, not a crash');

console.log('Round trip (the view travels through a form field):');
const rows = [
    { type: 'contains', text: 'Buche' },
    { type: 'contains_not', text: 'Fomes' },
    { type: 'contains', text: '' },
];
const text = writeTerms(rows);
check(text === 'a~Buche|n~Fomes',
    'written short and readable, the empty row drops out');
check(JSON.stringify(termsFromText(text)) === JSON.stringify(readTerms(rows)),
    'and read back the same is there');
check(termsFromText('').length === 0, 'nothing yields nothing');
check(termsFromText('kaputt|a~gut').length === 1,
    'unreadable parts drop out silently — it is a view, not a contract');

console.log('The vertical bar separates and must therefore not appear in the text:');
check(!writeTerms([{ type: 'contains', text: 'a|b' }]).includes('|b'),
    'a bar in the search text is replaced, otherwise the row would fall apart into two');


/* THE HEIGHT BAND (26.08.2026). Its twin sits in data.php and
   is tested in tests/autolists.php — what stands here is the
   specification that one is held against. Whoever changes a rule here
   changes it there.

   THE UPPER END MEANS „AND ABOVE". Without that, a find in the Alps would
   fall out because the scale stops at 2500 — and it would fall out
   SILENTLY, which is the sort of loss one only notices when looking for a
   particular find. */
console.log('\nThe height band:');
const band = (from, to) => ({ from, to });

check(elevationMatches(band(0, ELEVATION_MAX), 300),
    'the full band lets everything through');
check(elevationMatches(band(0, ELEVATION_MAX), null),
    'and a point without an elevation too — no condition means no condition');

check(elevationMatches(band(300, 900), 300), 'the lower edge belongs in');
check(elevationMatches(band(300, 900), 900), 'the upper edge belongs in');
check(!elevationMatches(band(300, 900), 299), 'one metre below does not');
check(!elevationMatches(band(300, 900), 901), 'one metre above does not');

/* A POINT WITHOUT AN ELEVATION DROPS OUT as soon as the band applies —
   the same rule the time filter follows for a point without a find time.
   „Unknown" is in no band. */
check(!elevationMatches(band(300, 900), null),
    'without an elevation a point drops out of an active band');
check(!elevationMatches(band(300, 900), ''),
    'an empty elevation counts as none');
check(!elevationMatches(band(300, 900), 'hoch'),
    'and nonsense counts as none too');

console.log('\nThe open top:');
check(elevationMatches(band(1000, ELEVATION_MAX), 2800),
    'above the scale still counts — the upper end means „and above"');
check(elevationMatches(band(ELEVATION_MAX, ELEVATION_MAX), 3000),
    'both handles at the ceiling: everything above it');
check(!elevationMatches(band(ELEVATION_MAX, ELEVATION_MAX), 2400),
    'and nothing below it');

console.log('\nWhat the band reads as:');
check(elevationLabel(band(0, ELEVATION_MAX)) === 'Alle Höhen · 0 – ∞ m', 'the full band, open at the top');
check(elevationLabel(band(300, 900)) === '300–900 m', 'a real band');
check(elevationLabel(band(0, 900)) === 'bis 900 m', 'only an upper end');
check(elevationLabel(band(300, ELEVATION_MAX)) === 'ab 300 m', 'only a lower end');
check(ELEVATION_STEP === 100, 'the step is a hundred metres');


/* THE SYSTEMATIC RANKS (26.08.2026). The twin is
   taxonomy_matches() in includes/taxonomy.php, tested against the same
   cases in tests/autolists.php — the two cannot import from each other,
   so only a pair of tests holds them together. */
console.log('\nThe systematic ranks:');
const boletus = { kingdom: 'Fungi', phylum: 'Basidiomycota', class: 'Agaricomycetes',
                  order: 'Boletales', family: 'Boletaceae', genus: 'Boletus' };
const quercus = { kingdom: 'Plantae', phylum: 'Tracheophyta', class: 'Magnoliopsida',
                  order: 'Fagales', family: 'Fagaceae', genus: 'Quercus' };

check(taxonomyMatches({}, boletus), 'nothing asked lets everything through');
check(taxonomyMatches({}, null), 'and a point without ranks too');

check(taxonomyMatches({ family: 'Boletaceae' }, boletus), 'the family hits');
check(!taxonomyMatches({ family: 'Amanitaceae' }, boletus), 'a different family does not');
check(taxonomyMatches({ family: 'boletaceae' }, boletus),
    'case does not matter — the offers come from the stock, the typing from a human');

/* ALL SET RANKS MUST HOLD. „Reich und Familie" is two demands, not a
   choice between them — the same reading the place filter uses for
   region and municipality. */
check(taxonomyMatches({ kingdom: 'Fungi', family: 'Boletaceae' }, boletus), 'two that hold');
check(!taxonomyMatches({ kingdom: 'Plantae', family: 'Boletaceae' }, boletus),
    'one that does not is enough to hold the point back');

check(taxonomyMatches({ kingdom: 'Plantae' }, quercus), 'a plant is found by its kingdom');
check(!taxonomyMatches({ kingdom: 'Fungi' }, quercus), 'and not by a foreign one');

/* A GENUS IN NO BACKBONE DROPS OUT as soon as a rank is demanded — the
   same rule the height band follows for a point without an elevation.
   „Unknown" is in no family. */
check(!taxonomyMatches({ family: 'Boletaceae' }, null),
    'without ranks a point drops out of an active condition');
check(!taxonomyMatches({ kingdom: 'Fungi' }, { kingdom: null, family: 'Boletaceae' }),
    'and a rank that is null counts as no answer');

/* THE GENUS REACHES FURTHER THAN THE RANKS ABOVE IT: it stands on the
   point, everything above is looked up and can fail. Measured on the
   local stock: 225 genera, 212 resolvable — thirteen finds are filterable
   by genus and by nothing else. */
check(taxonomyMatches({ genus: 'Boletus' }, { genus: 'Boletus' }),
    'a genus without any ranks above it is still found by its genus');
check(!taxonomyMatches({ family: 'Boletaceae' }, { genus: 'Boletus' }),
    'but not by a family nobody could look up');

console.log('\nWhat the ranks read as:');
check(taxonomyLabel({}) === '', 'nothing set reads as nothing');
check(taxonomyLabel({ family: 'Boletaceae' }) === 'Familie: Boletaceae', 'one rank');
check(taxonomyLabel({ kingdom: 'Fungi', family: 'Boletaceae' })
    === 'Reich: Fungi · Familie: Boletaceae', 'two, in the order one reads them');

check(TAXONOMY_RANKS.map(([k]) => k).join(',') === 'kingdom,phylum,class,order,family,genus',
    'the six ranks, top down — the same words as TAXONOMY_RANKS in includes/taxonomy.php');
/* THE GENUS IS THE DEEPEST, and that is what makes it useful: choosing it
   fills in everything above. It was added on 26.08.2026 after the maintainer
   objected to its absence — the text rows do find a genus, but they
   cannot OFFER one. */
check(TAXONOMY_RANKS.at(-1)[0] === 'genus', 'and the genus is the deepest of them');
check(TAXONOMY_DROPDOWN_MAX === 20, 'up to twenty offers a rank gets a drop-down');


/* WHAT LIES ABOVE FOLLOWS BY ITSELF (26.08.2026: „ich kann ja
   nicht Ascomycota wählen und das Reich Plantae").
   
   Tested WITHOUT a browser, and that is why taxonomyDerive is its own
   function: the rule is arithmetic over the stock, not a piece of
   interface. It was written inside the drawing at first, and the test
   said so on the first run — „document is not defined". */
console.log('\nThe ranks above follow by themselves:');
{
    const stock = [
        { kingdom: 'Fungi', phylum: 'Ascomycota', class: 'Pezizomycetes',
          order: 'Pezizales', family: 'Pyronemataceae', genus: 'Scutellinia' },
        { kingdom: 'Fungi', phylum: 'Basidiomycota', class: 'Agaricomycetes',
          order: 'Agaricales', family: 'Amanitaceae', genus: 'Amanita' },
        { kingdom: 'Plantae', phylum: 'Tracheophyta', class: 'Magnoliopsida',
          order: 'Fagales', family: 'Fagaceae', genus: 'Quercus' },
    ];
    const offers = (rank, others) => [...new Set(stock
        .filter((r) => taxonomyMatches(others, r))
        .map((r) => r[rank]).filter(Boolean))].sort();

    const conditions = { kingdom: '', phylum: '', class: '', order: '', family: '', genus: '' };
    taxonomyDerive(conditions, offers);
    check(conditions.kingdom === '', 'nothing chosen, nothing derived');

    conditions.phylum = 'Ascomycota';
    taxonomyDerive(conditions, offers);
    check(conditions.kingdom === 'Fungi', 'Ascomycota brings the kingdom Fungi with it');
    check((conditions._derived ?? []).includes('kingdom'), 'and it is marked as derived');
    check(conditions.class === '', 'downwards nothing is derived — a phylum is no family');

    conditions.family = 'Fagaceae';
    conditions.phylum = '';
    taxonomyDerive(conditions, offers);
    check(conditions.kingdom === 'Plantae', 'the deepest choice decides — Fagaceae is Plantae');
    check(conditions.order === 'Fagales' && conditions.class === 'Magnoliopsida',
        'and everything between it and the kingdom comes along');

    /* RELEASED AGAIN, otherwise clearing the family would leave „Plantae"
       standing as a condition nobody ever chose. */
    conditions.family = '';
    taxonomyDerive(conditions, offers);
    check(conditions.kingdom === '' && conditions.order === '',
        'clearing the choice releases what followed from it');

    /* THE GENUS IS THE DEEPEST RANK, so choosing it fills EVERYTHING
       above — that is the whole gain of taking it in. */
    conditions.kingdom = ''; conditions._derived = [];
    conditions.genus = 'Quercus';
    taxonomyDerive(conditions, offers);
    check(conditions.family === 'Fagaceae' && conditions.kingdom === 'Plantae',
        'the genus Quercus fills family and kingdom alike');
    conditions.genus = '';
    taxonomyDerive(conditions, offers);

    // An EXPLICIT value is not swept away by that.
    conditions.kingdom = 'Fungi';
    taxonomyDerive(conditions, offers);
    taxonomyDerive(conditions, offers);
    check(conditions.kingdom === 'Fungi', 'a rank one chose oneself survives every redraw');
}

{
    console.log('\nThe DGfM marks (17.09.2026)');
    /* THE SAME READING AS ON THE SERVER — dgfm_marks_match() in
       includes/dgfm_marks.php, held by tests/dgfm_marks.php: nothing
       chosen means all, chosen marks are OR-linked, the responsibility
       hook is an AND beside them. A point without marks drops out as
       soon as a mark is chosen — „Speisepilz" must not mean „or unknown". */
    const steinpilz = [{ list: 'edible' }, { list: 'inconsistent' }, { list: 'responsibility', value: '!!' }];
    const haeubling = [{ list: 'poisonous', syndrome: 'Amatoxin-Syndrom' }];
    check(edibilityMatches([], steinpilz) && edibilityMatches([], []), 'nothing chosen lets everything through');
    check(edibilityMatches(['edible'], steinpilz), 'edible matches the Steinpilz');
    check(!edibilityMatches(['poisonous'], steinpilz), 'poisonous does not');
    check(edibilityMatches(['poisonous', 'inconsistent'], steinpilz), 'two chosen means one suffices');
    check(!edibilityMatches(['edible'], []), 'a point without marks drops out once a mark is chosen');
    check(!edibilityMatches(['edible'], undefined), 'and a point from before the field likewise');
    check(responsibilityMatches(false, haeubling) && responsibilityMatches(true, steinpilz)
        && !responsibilityMatches(true, haeubling), 'the hook passes the Steinpilz and stops the Gifthäubling');
    check(readEdibility(['poisonous', 'quatsch', 'edible', 'edible']).join(',') === 'edible,poisonous',
        'reading keeps known marks only, once each, in the group\'s order');
    check(writeEdibility(['poisonous', 'edible']) === 'edible,poisonous'
        && edibilityFromText('poisonous,edible,x').join(',') === 'edible,poisonous',
        'the form text round-trips');
    check(EDIBILITY_MARKS.length === 3, 'three marks — the DGfM\'s three lists');
    check(dgfmWord({ list: 'responsibility', value: '!!' }) === 'Verantwortungsart !!'
        && dgfmWord({ list: 'poisonous' }) === 'Giftpilz', 'the word carries the value, where there is one');
}

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
