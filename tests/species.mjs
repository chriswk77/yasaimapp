/**
 * Test of the offline species search — how species.js merges the stored
 * packages into one list.
 *
 *   node tests/species.mjs
 *
 * WHAT IS AT STAKE: a species that simply is not there. Nothing breaks and
 * nothing complains; in the woods the name just does not come, while at
 * home with a network the server offers it. Until 26.09.2026 the merge let
 * whichever row came first take a name, and the fungi package is sorted by
 * the DGfM binomial alone — so a misapplied synonym („Lactarius pyrogalus
 * ss. auct.") or a synonym of the very same name („Agrocybe molesta" → A.
 * dura) came before the species and swallowed it. 34 fungi were gone that
 * way, 426 animals and some 3,700 GBIF names.
 *
 * THE ROWS ARE THE PACKAGES' OWN, copied as the endpoint delivers them and
 * in their order, because the order is where the fault lived. A fixture
 * made up for the rule would test the rule, not the lists.
 *
 * tests/species.php holds the server side; this file only the merge.
 */

// Stand in for the browser before the module is loaded: the settings with
// the chosen groups, a cache storage holding the packages, and a server
// whose directory names exactly those — so the module searches what is
// stored and fetches nothing.
const storage = new Map([['map.settings', JSON.stringify({ speciesGroups: ['fungi', 'lichens', 'birds', 'insects'] })]]);
globalThis.localStorage = {
    getItem: (k) => storage.get(k) ?? null,
    setItem: (k, v) => storage.set(k, String(v)),
    removeItem: (k) => storage.delete(k),
    key: (i) => [...storage.keys()][i] ?? null,
    get length() { return storage.size; },
};

const packages = {
    fungi: ['3-40340-r2', [
        ['Agrocybe dura', '(Bolton) Singer', 'Rissiger Ackerling', null],
        ['Agrocybe molesta', null, null, null, 'Agrocybe dura'],
        ['Agrocybe molesta', 'Singer', null, null],
        ['Coenogonium pineti', '(Ach.) Lücking & Lumbsch', null, null],
        ['Collybiopsis ramealis', '(Bull.) Millsp.', 'Ast-Schwindling', null],
        ['Cortinarius pseudocolus', 'M.M. Moser', 'Falscher Rotfuß-Gürtelfuß', 'D'],
        ['Dimerella pineti', '(Ach.) Vězda', null, null],
        ['Hygrocybe mucronella agg.', null, null, '3'],
        ['Hygrocybe mucronella', '(Fr.) P. Karst.', 'Bitterer Saftling', null],
        ['Lactarius circellatus', 'Fr.', 'Gebänderter Hainbuchen-Milchling', null],
        ['Lactarius hortensis', null, null, null, 'Lactarius pyrogalus'],
        ['Lactarius pyrogalus ss. auct.', null, null, null, 'Lactarius circellatus'],
        ['Lactarius pyrogalus', '(Bull.) Fr.', 'Hasel-Milchling', null],
        ['Marasmiellus ramealis', null, null, null, 'Collybiopsis ramealis'],
        ['Marasmiellus ramealis var. macrosporus', '(Courtec.) Antonín & Noordel.', null, 'D'],
        ['Xerocomellus chrysenteron agg.', null, 'Rotfußröhrling-Aggregat', '♦'],
        ['Xerocomellus chrysenteron s. str.', '(Bull.) Šutara', 'Rotfußröhrling, Gewöhnlicher R., "Samtkappe"', null],
    ]],
    gbif: ['3-373139', [
        ['Coenogonium pineti', 'Lücking & Lumbsch', null, null],
        ['Dimerella pineti', null, null, null, 'Coenogonium pineti'],
        ['Lactarius hortensis', 'Velen.', null, null],
        ['Lactarius hortensis', 'Fr.', null, null],
        ['Xerocomellus chrysenteron', 'Šutara', null, null],
    ]],
    lichens: ['3-2767-r1', [
        ['Coenogonium pineti', '(Schrad.) Lücking & Lumbsch', null, '*'],
        ['Dimerella pineti', null, null, null, 'Coenogonium pineti'],
    ]],
    birds: ['3-12717', [
        ['Accipiter badius', '(Gmelin, 1788)', 'Schikra', null],
        ['Accipiter badius', '(Gmelin, 1788)', null, null],
        ['Cariama cristata', '(Linnaeus, 1766)', 'Rotfuß-Seriema', null],
        ['Crypturellus erythropus', '(Pelzeln, 1863)', 'Rotfußtinamu', null],
    ]],
    // After migrations 0135 and 0136, which took Fauna Europaea's „(null)"
    // and the subgenus before an author out.
    insects: ['3-216970', [
        // The Stuttgart row first (no author), Fauna Europaea's genus, one
        // of its subgenera under the genus's bare name — as delivered since
        // 26.09.2026.
        ['Acronicta', null, null, null],
        ['Acronicta', 'Ochsenheimer, 1816', null, null],
        ['Acronicta', null, null, null],
        ['Lasiocampa quercus', '(Linnaeus, 1758)', 'Eichenspinner', null],
        ['Lasiocampa quercus', '(Linnaeus, 1758)', null, null],
        ['Lasiocampa quercus quercus', '(Linnaeus, 1758)', null, null],
        ['Phalaena quercus', null, null, null, 'Lasiocampa quercus'],
        ['Phalaena quercus', null, null, null, 'Lasiocampa quercus quercus'],
    ]],
};

const store = {
    async keys() {
        return Object.keys(packages).map((id) => ({ url: 'http://test/api/v1/species_list.php?package=' + id }));
    },
    async match(request) {
        const url = new URL(typeof request === 'string' ? request : request.url, 'http://test');
        const id = url.searchParams.get('package');
        if (!packages[id]) return undefined;
        const [version, species] = packages[id];
        return {
            headers: { get: (h) => (h === 'ETag' ? `"species-${id}-${version}"` : null) },
            json: async () => ({ package: id, version, species }),
        };
    },
    async delete() { return true; },
    async put() {},
};
// caches.keys/has and indexedDB answer „nothing old here" to legacy.js, whose
// one-time takeovers run on import.
globalThis.caches = { open: async () => store, keys: async () => [], has: async () => false };
globalThis.indexedDB = { databases: async () => [], deleteDatabase() {} };
globalThis.window = { START_DATA: { baseUrl: '/', csrf: 'x' }, addEventListener() {}, caches: globalThis.caches };
globalThis.fetch = async (url) => {
    if (!String(url).includes('species_packages')) throw new Error('offline');
    const directory = Object.entries(packages)
        .map(([identifier, [version]]) => ({ identifier, version, automatic: true }));
    return { ok: true, status: 200, json: async () => ({ packages: directory }) };
};

const species = await import('../assets/js/species.js');
const list = await species.loadSpeciesList();

let failed = 0;
function check(name, condition, seen = '') {
    if (condition) {
        console.log('  ok    ' + name);
    } else {
        console.log('  FAILED ' + name + (seen ? '  ->  ' + seen : ''));
        failed++;
    }
}

const hits = (input) => species.searchSpeciesLocally(input)?.hits ?? [];
const shown = (found) => found.map((h) => h.name + (h.via ? ' (über ' + h.via + ')' : '')).join(' | ') || '—';

console.log('\nThe stored packages');
check('the five packages were merged into one list',
    Array.isArray(list) && list.packages?.length === 5, String(list?.packages));

console.log('\nWhat went missing offline');

// The trigger: the misapplied name came first, its bare form counted as
// seen, and the Hasel-Milchling was gone.
let r = hits('lactarius pyrogalus');
check('"lactarius pyrogalus" gives the Hasel-Milchling first',
    r[0]?.name === 'Lactarius pyrogalus' && r[0]?.german === 'Hasel-Milchling' && !r[0]?.via, shown(r));
check('and the misapplied name still leads to L. circellatus',
    r.some((h) => h.name === 'Lactarius circellatus' && h.via === 'Lactarius pyrogalus ss. auct.'), shown(r));

// With the species gone its synonym pointed at nothing.
r = hits('lactarius hortensis');
check('"lactarius hortensis" leads to the Hasel-Milchling again',
    r.some((h) => h.name === 'Lactarius pyrogalus' && h.via === 'Lactarius hortensis'), shown(r));

// The DGfM carries the name twice — a species of its own, and a synonym of
// A. dura. The synonym came first and took the name.
r = hits('agrocybe molesta');
check('"agrocybe molesta" gives the species itself, with its author',
    r.some((h) => h.name === 'Agrocybe molesta' && !h.via && h.author === 'Singer'), shown(r));
check('and A. dura as what the name also stands for',
    r.some((h) => h.name === 'Agrocybe dura' && h.via === 'Agrocybe molesta'), shown(r));

// Across packages alike: the lichen list's synonym swallowed the fungus.
r = hits('dimerella pineti');
check('"dimerella pineti" keeps the DGfM\'s species beside the lichen list\'s synonym',
    r.some((h) => h.name === 'Dimerella pineti' && h.source === 'fungi' && !h.via)
    && r.some((h) => h.name === 'Coenogonium pineti' && h.via === 'Dimerella pineti'), shown(r));

// The DGfM list itself carries the aggregate and the plain name as two taxa
// — the bare-name rule is for another package, not for its own.
r = hits('hygrocybe mucronella');
check('"hygrocybe mucronella" gives the Bitterer Saftling beside the aggregate',
    r.some((h) => h.name === 'Hygrocybe mucronella' && h.german === 'Bitterer Saftling')
    && r.some((h) => h.name === 'Hygrocybe mucronella agg.'), shown(r));

// One name, two synonyms in one package: Fauna Europaea hangs the old
// „Phalaena quercus" on the species and on its nominate subspecies. The
// second row of the name used to go, and which one that was — species or
// subspecies — was the sort's chance.
r = hits('phalaena quercus');
check('"phalaena quercus" leads to the Eichenspinner and to its nominate subspecies',
    r.some((h) => h.name === 'Lasiocampa quercus' && h.via === 'Phalaena quercus' && h.german === 'Eichenspinner')
    && r.some((h) => h.name === 'Lasiocampa quercus quercus' && h.via === 'Phalaena quercus'), shown(r));

// The first valid row of a name stays; where it names no author, the row
// of its own package that yields lends it — the Stuttgart list gives most
// genera none, Fauna Europaea does, and the server takes it just so.
r = hits('acronicta');
check('a genus the Stuttgart list names without author takes Fauna Europaea\'s',
    r.length === 1 && r[0].author === 'Ochsenheimer, 1816', shown(r) + ' / ' + r.map((h) => h.author).join(' | '));

console.log('\nGBIF after the Germany lists, as on the server');

// GBIF's own „Lactarius hortensis" begins with the whole input and is a
// direct hit, the DGfM's answer comes through a synonym — sorted by those
// keys alone, GBIF stood first offline and last online (26.09.2026).
r = hits('lactarius hortensis');
check('"lactarius hortensis" gives the DGfM\'s Hasel-Milchling first, GBIF\'s name after it',
    r[0]?.name === 'Lactarius pyrogalus' && r[0]?.via === 'Lactarius hortensis'
    && r.findIndex((h) => h.name === 'Lactarius hortensis' && h.source === 'gbif') > 0, shown(r));

console.log('\nAn old name typed out leads to its species, as on the server');

// The keys asked only about the name shown, and the species found under its
// old name does not start with the input — its variety filed under the old
// genus does, and stood on top (26.09.2026).
r = hits('Marasmiellus ramealis');
check('"Marasmiellus ramealis" gives Collybiopsis ramealis first, not the variety of the old name',
    r[0]?.name === 'Collybiopsis ramealis' && r[0]?.via === 'Marasmiellus ramealis', shown(r));
r = hits('mar ram');
check('and so does "mar ram", the name abbreviated word by word',
    r[0]?.name === 'Collybiopsis ramealis', shown(r));
r = hits('Marasmiellus ramealis var. macrosporus');
check('the variety typed out is still the variety',
    r[0]?.name === 'Marasmiellus ramealis var. macrosporus', shown(r));
// One word is a genus being typed, not a name spelled out.
r = hits('marasmiellus');
check('"marasmiellus" alone keeps the name shown first',
    r[0]?.name === 'Marasmiellus ramealis var. macrosporus', shown(r));

console.log('\nUmlauts, ß and brackets as the server reads them');

// The database ignores accents, and its full-text index spells ß out —
// offline „rohrling" found nothing until 26.09.2026, online 37 Röhrlinge.
r = hits('rotfussrohrling');
check('"rotfussrohrling" finds the Rotfußröhrling, typed without ß and ö',
    r.some((h) => h.name === 'Xerocomellus chrysenteron agg.')
    && r.some((h) => h.name === 'Xerocomellus chrysenteron s. str.'), shown(r));
r = hits('gebanderter');
check('"gebanderter" finds the Gebänderter Hainbuchen-Milchling',
    r.some((h) => h.name === 'Lactarius circellatus'), shown(r));

// A word behind a quote or a bracket is a word, as in the full-text index.
r = hits('samtkappe');
check('"samtkappe" finds the „Samtkappe" in its quotes',
    r[0]?.name === 'Xerocomellus chrysenteron s. str.', shown(r));

// LIKE, which sorts, compares letter by letter and does not spell ß out:
// „rotfuß" starts „Rotfußtinamu", „rotfuss" does not — the server puts the
// Tinamu before the Gürtelfuß on the one and after it on the other.
const before = (input, a, b) => {
    const n = hits(input).map((h) => h.name);
    return n.includes(a) && n.includes(b) && n.indexOf(a) < n.indexOf(b);
};
check('"rotfuß" and "rotfuss" sort as the server does, ß kept for the order',
    before('rotfuß', 'Crypturellus erythropus', 'Cortinarius pseudocolus')
    && before('rotfuss', 'Cortinarius pseudocolus', 'Crypturellus erythropus'),
    shown(hits('rotfuß')) + '  //  ' + shown(hits('rotfuss')));

console.log('\nWhat stays as it was');

// 13.09.2026: GBIF does not add the bare name a third time.
r = hits('xerocomellus chrys');
check('GBIF\'s bare „Xerocomellus chrysenteron" stays out beside agg. and s. str.',
    r.length === 2 && !r.some((h) => h.name === 'Xerocomellus chrysenteron'), shown(r));

// A name valid in two packages comes once, from the one whose word counts.
r = hits('coenogonium pineti');
check('a name valid in the lichens and the fungi comes once, from the lichen list',
    r.filter((h) => h.name === 'Coenogonium pineti').length === 1
    && r.find((h) => h.name === 'Coenogonium pineti')?.source === 'lichens', shown(r));

// Within one animal package the first valid row wins — the Stuttgart
// list's, which carries the German name.
r = hits('accipiter badius');
check('a doubled valid name in one package comes once, with the German name',
    r.length === 1 && r[0].german === 'Schikra', shown(r));

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
