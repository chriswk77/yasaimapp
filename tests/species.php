<?php
/**
 * Tests the species search across the three reference lists for Germany.
 *
 *   php tests/species.php        (from the project directory)
 *
 * WHAT IS REALLY AT STAKE HERE
 * The search unites three full-text indexes and groups the result. Three
 * things can silently break in that, and none of them shows up while typing:
 * a name appears twice because it is in both lists. The count diverges from
 * the page, so that scrolling on skips hits or repeats them. Or the lichens
 * vanish again behind the fungi that grow on them — the state that brought
 * the second list in in the first place.
 *
 * Tested against the database, not against invented data: a search that is
 * only correct with made-up rows says nothing.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/species.php';
require_once __DIR__ . '/../includes/species_packages.php';
require_once __DIR__ . '/../includes/migrations.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

/** @return list<string> */
function names(array $result): array
{
    return array_column($result['hits'], 'name');
}

echo "\nBreaking up the input\n";

[$a, $start] = species_expression('bol edu');
check($a === '+bol* +edu*', '"bol edu" becomes "+bol* +edu*"');
check($start === 'bol', 'the first fragment drives the sorting');

[$a] = species_expression('a-b');
check($a === '', 'pieces too short fall away, the minus before them too');

[$a] = species_expression('cladonia -rangi');
check($a === '+cladonia* +rangi*',
    'a minus sign is not read as an exclusion');

[$a, , $whole] = species_expression('Boletus ed');
check($a === '+boletus*', 'for the full-text index "ed" falls away — too short');
check($whole === 'boletus ed', 'for the sorting it still counts, no index needed there');

echo "\nOrder: scientific name first\n";

// THE TRIGGER: Whoever searched "Begleitvegetation" for the oak and typed
// "quer" was served three fungi that happen to carry a German name with the
// same four letters — "Queradriger Rötling". Quercus sat in fourth place.
// The German name is an accessory and must not overtake the scientific
// one.
//
// AND THE OAK LOST A SECOND TIME, from 19.08.2026 until 24.09.2026, to a
// bird: the Stuttgart animal list carries the genus Querula, and between
// two scientific hits the full-text score decided — a score counted per
// table, 10.7 in the animal table against 7.3 in the plant table. The
// score sorts no more; between equals the alphabet decides.
$r = species_search($pdo, 'quer');
check(str_starts_with(names($r)[0], 'Quercus '),
    'on "quer" an oak sits on top, not a fungus with the German name "Queradriger …"');

// The score's other half: it counted a repeated word twice, so the
// nominate subspecies overtook its own species the moment the epithet was
// typed out — "Quercus ro" gave the species, "quercus robur" the
// subspecies.
$r = species_search($pdo, 'quercus robur');
check(names($r)[0] === 'Quercus robur',
    'on "quercus robur" the species sits on top, not "Quercus robur subsp. robur"');

// And the case the German preference was once built for: every willow is
// called Salix, so scientifically it starts with "sal". Only the German
// name distinguishes here — as a SUBORDINATE key it still does exactly
// that.
$r = species_search($pdo, 'sal weide');
check(names($r)[0] === 'Salix caprea',
    'on "sal weide" the Sal-Weide sits on top, not the alphabetically first Salix');

// The whole input as prefix, not just its first word.
$r = species_search($pdo, 'Boletus ed');
check(names($r)[0] === 'Boletus edulis',
    'on "Boletus ed" Boletus edulis sits on top, not the alphabetically first Boletus');

$r = species_search($pdo, 'Quercus ro');
check(names($r)[0] === 'Quercus robur',
    'on "Quercus ro" Quercus robur sits on top');

// Where there is no scientific hit, the German one keeps counting.
$r = species_search($pdo, 'steinpilz');
check(names($r)[0] === 'Boletus edulis',
    'on "steinpilz" the Steinpilz comes before the Schwarzer and the Birken-Steinpilz');

// GBIF tops up behind the Germany lists and by the browser's keys, the
// alphabet last. The full-text score it went by until 26.09.2026 counted
// "mucronella" twice in var. mucronella and put it above var. mitis —
// offline stood the other way round.
$r = species_search($pdo, 'hygrocybe mucronella');
$sources = array_column($r['hits'], 'source');
$firstGbif = array_search('gbif', $sources, true);
$mitis = array_search('Hygrocybe mucronella var. mitis', names($r), true);
$mucronella = array_search('Hygrocybe mucronella var. mucronella', names($r), true);
check($firstGbif !== false && !in_array('dgfm', array_slice($sources, $firstGbif), true)
      && $mitis !== false && $mucronella !== false && $mitis < $mucronella,
    'GBIF\'s names follow the Germany lists, and among themselves the alphabet decides');

echo "\nLichens can be found\n";

$r = species_search($pdo, 'clad rangi');
check(in_array('Cladonia rangiferina', names($r), true),
    'Cladonia rangiferina appears in the suggestions');

// GBIF then tops this up with "Xanthoria parietina parietina" — the species
// itself is still owed first place.
$r = species_search($pdo, 'xanthoria pari');
check(names($r)[0] === 'Xanthoria parietina' && $r['hits'][0]['source'] === 'lichens',
    'Xanthoria parietina sits on top, from the lichen list');
check($r['hits'][0]['red_list'] === '*',
    'with its endangerment classification (not endangered)');

// The actual trigger: before, "cladonia" delivered seven fungi that grow on
// Cladonias, and not a single Cladonia.
$r = species_search($pdo, 'cladonia');
$real = array_filter(names($r), fn($n) => str_starts_with($n, 'Cladonia '));
check(count($real) > 20,
    'on "cladonia" there are Cladonias, not only their inhabitants (' . count($real) . ')');
check(str_starts_with(names($r)[0], 'Cladonia '),
    'and in first place at that');

echo "\nPlants, and via the German name\n";

// With plants almost every species carries a German name, and that is what
// gets searched for. Trees as well as herbs.
foreach ([
    'stiel eiche'   => 'Quercus robur',
    'borstgras'     => 'Nardus stricta',
    'weiss tanne'   => 'Abies alba',
    'sal weide'     => 'Salix caprea',
] as $input => $expected) {
    $r = species_search($pdo, $input);
    check((names($r)[0] ?? '') === $expected, "\"$input\" leads to $expected");
}

$r = species_search($pdo, 'quercus robur');
check($r['hits'][0]['german'] !== null && $r['hits'][0]['source'] === 'plants',
    'the oak comes from the plant list, with a German name');

// The actual reason for sorting by the German name: before, "Steinpilz"
// stood behind the Schwarzer and the Birken-Steinpilz, because only the
// scientific name was checked against the prefix.
$r = species_search($pdo, 'steinpilz');
check(names($r)[0] === 'Boletus edulis',
    'whoever types "steinpilz" gets the Steinpilz first');

/* The aggregate is named, not authored (migration 0137). „Arum maculatum"
   stood twice, once by the author „agg.", and the two rows became one
   suggestion with the species' author and the aggregate's German name. */
$r = species_search($pdo, 'arum maculatum')['hits'];
$arum = array_column($r, null, 'name');
check(($arum['Arum maculatum']['author'] ?? '') === 'L.'
      && ($arum['Arum maculatum']['german'] ?? '') === 'Gefleckter Aronstab'
      && isset($arum['Arum maculatum agg.']) && $arum['Arum maculatum agg.']['author'] === null
      && $arum['Arum maculatum agg.']['german'] === 'Gefleckter Aronstab (Artengruppe)',
    'an aggregate and its species are two suggestions, each with its own fields');
check(species_reference($pdo, 'Arum maculatum', 'plants') === 'plants:5eac5fa1-8baa-4885-9f68-56efa5091c0f'
      && species_reference($pdo, 'Arum maculatum agg.', 'plants') === 'plants:42256498-d947-4375-8a7e-d333985e60a7',
    'and each title refers to its own row');
check((int) $pdo->query("SELECT COUNT(*) FROM (SELECT binomial FROM map_tax_plants WHERE taxnr = sipnr
                                               GROUP BY binomial HAVING COUNT(*) > 1) x")->fetchColumn() === 0,
    'no valid plant name stands twice — no qualifier, subgenus or hybrid name left in the author');

echo "\nThe fungi stay as they were\n";

$r = species_search($pdo, 'bol edu');
check(names($r)[0] === 'Boletus edulis', 'Boletus edulis sits on top');
check($r['hits'][0]['german'] === 'Steinpilz', 'with its German name');

$r = species_search($pdo, 'boletus esculentus');
check(names($r)[0] === 'Boletus edulis' && $r['hits'][0]['via'] === 'Boletus esculentus',
    'a fungus synonym leads to the valid name, with a note');

$r = species_search($pdo, 'polyblastia deminuta');
check(names($r)[0] === 'Halospora deminuta' && $r['hits'][0]['via'] === 'Polyblastia deminuta',
    'and a lichen synonym likewise');

echo "\nAn old name typed out leads to its species (26.09.2026)\n";

/* The keys asked only whether the name SHOWN starts with the input, and a
   species found under its old name does not — its varieties do, where the
   list files them under the old genus. „Marasmiellus ramealis" put
   Marasmiellus ramealis var. macrosporus on top, „bisporella citrina"
   Calycella terrestris („über Bisporella citrina var. terrestris"), and
   the maintainer took the first line. A name the input spells out, word for
   word and with nothing left over, now comes first. */
$r = species_search($pdo, 'Marasmiellus ramealis');
check(names($r)[0] === 'Collybiopsis ramealis' && $r['hits'][0]['via'] === 'Marasmiellus ramealis',
    '"Marasmiellus ramealis" gives Collybiopsis ramealis first, not the variety of the old name');
$r = species_search($pdo, 'mar ram');
check(names($r)[0] === 'Collybiopsis ramealis',
    'and so does "mar ram", the name abbreviated word by word');
$r = species_search($pdo, 'bisporella citrina');
check(names($r)[0] === 'Calycina citrina',
    '"bisporella citrina" gives Calycina citrina, not the species of an old variety');
$r = species_search($pdo, 'leccinum aurantiacum');
check(str_starts_with((string) $r['hits'][0]['via'], 'Leccinum aurantiacum ss.')
      && str_starts_with((string) $r['hits'][1]['via'], 'Leccinum aurantiacum ss.'),
    '"leccinum aurantiacum" gives the two species the name has meant before Leccinum decipiens');
$r = species_search($pdo, 'Marasmiellus ramealis var. macrosporus');
check(names($r)[0] === 'Marasmiellus ramealis var. macrosporus',
    'the variety typed out is still the variety');

echo "\nThe sensu qualifier of the DGfM list (13.09.2026)\n";

/* 143 binomials stand twice in the list, as the aggregate and as the
   species s. str. — the maintainer met „Xerocomellus chrysenteron" with the
   aggregate's German name on it. Delivered WITH the qualifier the two are
   two suggestions; typed with it they are found; and the bare name goes
   to the species, not the aggregate. */
$r = species_search($pdo, 'xerocomellus chrys');
$dgfm = array_values(array_filter($r['hits'], static fn(array $h): bool => $h['source'] === 'dgfm'));
$dgfmNames = array_map(static fn(array $h): string => $h['name'], $dgfm);
check(in_array('Xerocomellus chrysenteron agg.', $dgfmNames, true)
      && in_array('Xerocomellus chrysenteron s. str.', $dgfmNames, true),
    'the aggregate and the species in the strict sense are two suggestions');
check(count(array_unique(array_column($dgfm, 'identifier'))) === count($dgfm),
    'each with its own reference');
$agg = array_values(array_filter($dgfm, static fn(array $h): bool => $h['name'] === 'Xerocomellus chrysenteron agg.'));
check(($agg[0]['german'] ?? '') === 'Rotfußröhrling-Aggregat',
    'and the aggregate keeps its own German name');
check(!in_array('Xerocomellus chrysenteron', names($r), true),
    'and GBIF does not add the bare name a third time');
check(names(species_search($pdo, 'xerocomellus chrysenteron s. str.'))[0] === 'Xerocomellus chrysenteron s. str.',
    'typed with the qualifier, the qualifier is found');
check(names(species_search($pdo, 'armillaria mellea agg'))[0] === 'Armillaria mellea agg.',
    'and „agg" as well');

require_once __DIR__ . '/../includes/data.php';
check(species_reference($pdo, 'Xerocomellus chrysenteron', 'fungi') === 'fungi:850006',
    'the bare name refers to the species, not to the aggregate');
check(species_reference($pdo, 'Xerocomellus chrysenteron agg.', 'fungi') === 'fungi:838980',
    'the name with „agg." refers to the aggregate');
check(species_reference($pdo, 'Xerocomellus chrysenteron s.str.', 'fungi') === 'fungi:850006',
    'and a hand\'s „s.str." is read as the list\'s „s. str."');
check(species_reference($pdo, 'Boletus edulis var. arenarius', 'fungi') === 'fungi:849544',
    'the infraspecific markers still resolve as before');

echo "\nGBIF's names, the DGfM's rows (13.09.2026, migration 0106)\n";

/* „Macht doch die GBIF-Namen. Sie sind aktueller und weltweit
   hört man darauf." The DGfM's Boletus torosus is offered as GBIF's
   Imperator torosus — with the DGfM's German name, category and
   reference, under GBIF's author; the DGfM's own name is the „über"
   note and finds it as a synonym does. Where GBIF lumps two DGfM taxa
   into one name, both keep their DGfM names. */
if (column_exists($pdo, 'map_tax_links', 'gbif_name')) {
    $r = species_search($pdo, 'boletus tor');
    $top = $r['hits'][0] ?? [];
    check(($top['name'] ?? '') === 'Imperator torosus',
        'Boletus torosus is offered as Imperator torosus');
    check(($top['german'] ?? '') === 'Ochsen-Röhrling' && ($top['identifier'] ?? '') === 'fungi:7830',
        'with the DGfM\'s German name and reference');
    check(($top['via'] ?? '') === 'Boletus torosus',
        'and the DGfM\'s own name as the note');
    check(str_starts_with((string) ($top['author'] ?? ''), 'Assyov'),
        'under GBIF\'s author, not the DGfM\'s');
    check(count(array_filter(names(species_search($pdo, 'imperator tor')),
            static fn(string $n): bool => $n === 'Imperator torosus')) === 1,
        'typed as Imperator, it comes once — the DGfM row, not GBIF\'s bare row beside it');
    check(names(species_search($pdo, 'cordyceps mil'))[0] === 'Cordyceps militaris',
        'Cordyceps militaris stays — GBIF agrees, whatever COL calls it');
    check(names(species_search($pdo, 'agaricus fissur'))[0] === 'Agaricus fissuratus',
        'a taxon GBIF lumps keeps its DGfM name — nothing is merged away');
    check(species_reference($pdo, 'Imperator torosus', 'fungi') !== null
          && species_german_names($pdo, [species_reference($pdo, 'Imperator torosus', 'fungi')]) !== [],
        'a title under GBIF\'s name resolves to a DGfM row that knows the German name');
    check(species_reference($pdo, 'Hydropodia subalpina', 'fungi') !== null,
        'and so does a name the DGfM carries only as GBIF\'s');

    /* A GBIF name the DGfM keeps as a synonym row is a direct hit when
       typed: it IS the name shown (species_branch). Counted as a synonym
       hit it sank behind its whole genus — online only, the browser had
       it among them (24.09.2026). */
    $n = names(species_search($pdo, 'trichobelonium'));
    $kneiffii = array_search('Trichobelonium kneiffii', $n, true);
    $transiens = array_search('Trichobelonium transiens', $n, true);
    check($kneiffii !== false && $transiens !== false && $kneiffii < $transiens,
        'Trichobelonium kneiffii, GBIF\'s name for a DGfM Mollisia, sorts among its genus');

    /* Typed as shown, a species under GBIF's name is the DGfM's row
       (migration 0134). Until 26.09.2026 the full-text search knew only the
       DGfM's own name, and „Rhodofomes cajanderi" — the DGfM's Fomitopsis
       cajanderi — brought GBIF's own bare row: no German name, no category,
       a gbif: reference. Offline it was the DGfM's all along. */
    if (species_gbif_names_searchable($pdo)) {
        $top = species_search($pdo, 'rhodofomes cajanderi')['hits'][0] ?? [];
        check(($top['name'] ?? '') === 'Rhodofomes cajanderi' && ($top['identifier'] ?? '') === 'fungi:88'
              && ($top['via'] ?? null) === null,
            'typed under GBIF\'s name, a renamed species is the DGfM\'s row, without a note');

        // GBIF's name for the DGfM's Phaeogalera zetlandica is one the DGfM
        // keeps as a synonym of P. stagnina: both statements are offered,
        // both as the DGfM's species — as offline (tests/species.mjs).
        $r = species_search($pdo, 'galerina stagnina')['hits'];
        check((bool) array_filter($r, static fn(array $h): bool =>
                  $h['name'] === 'Galerina stagnina' && $h['identifier'] === 'fungi:829890')
              && (bool) array_filter($r, static fn(array $h): bool =>
                  $h['name'] === 'Phaeogalera stagnina' && $h['via'] === 'Galerina stagnina'),
            'a GBIF name the DGfM also keeps as a synonym gives both species, both from the DGfM');
    }

    /* A DGfM name GBIF does not know borrows the link of a homotypic name,
       or of the species all its names agree on — never of whichever had
       the lowest key (migration 0140). The Rosablättriger
       Egerlingsschirmling came as Leucoagaricus subcretaceus, a species of
       its own at GBIF; the Eichenwirrling as GBIF's separate Trametes
       quercina; the Ziegelrote Filzgewebe as Tomentella subvinosa. */
    if (isset(migrations_applied($pdo)['0140_gbif_links_through_synonyms.sql'])) {
        $top = species_search($pdo, 'leucocoprinus leucothites')['hits'][0] ?? [];
        check(($top['name'] ?? '') === 'Leucoagaricus leucothites' && ($top['identifier'] ?? '') === 'fungi:826860',
            'Leucocoprinus leucothites is offered under its homotypic GBIF name, not as L. subcretaceus');
        check((species_search($pdo, 'fomitopsis quercina')['hits'][0]['name'] ?? '') === 'Daedalea quercina',
            'Fomitopsis quercina as Daedalea quercina, the link its own synonym row carries');
        check((species_search($pdo, 'thelephora eulateritia')['hits'][0]['name'] ?? '') === 'Thelephora eulateritia',
            'where the synonyms lead to two species, the DGfM\'s own name stands');
    }

    /* Not the name another DGfM species keeps (migration 0141): Helvella
       fallax came as „Helvella atra", the name the Schwarze Lorchel keeps
       as its own — two species behind one suggestion. */
    if (isset(migrations_applied($pdo)['0141_gbif_name_not_another_species.sql'])) {
        $atra = array_filter(species_search($pdo, 'helvella atra')['hits'],
            static fn(array $h): bool => $h['name'] === 'Helvella atra');
        check(count($atra) === 1 && (reset($atra)['identifier'] ?? '') === 'fungi:807080'
              && (species_search($pdo, 'helvella fallax')['hits'][0]['identifier'] ?? '') === 'fungi:111121',
            '„Helvella atra" is the Schwarze Lorchel alone, Helvella fallax keeps its DGfM name');
    }
}

echo "\nOld names only GBIF knows (26.09.2026)\n";

/* The GBIF package brings GBIF's synonyms along, and offline an old name
   no Germany list uses leads to its accepted name — the Germany species
   of that name where there is one. The server searched GBIF's valid names
   only: „pla veg" found nothing of the Flacher Lackporling. */
$hit = static function (string $input, string $name) use ($pdo): array {
    foreach (species_search($pdo, $input)['hits'] as $h) {
        if ($h['name'] === $name) return $h;
    }
    return [];
};
$h = $hit('pla veg', 'Ganoderma applanatum');
check(($h['via'] ?? '') === 'Placodes vegetus' && str_starts_with((string) ($h['identifier'] ?? ''), 'fungi:')
      && ($h['german'] ?? '') !== '',
    'an old GBIF name leads to the DGfM\'s species, German name and all');
$h = $hit('cri ran', 'Cladonia rangiferina');
check(($h['via'] ?? '') === 'Cladonia rangiferina subsp. crispata' && ($h['source'] ?? '') === 'lichens',
    'and to the lichen list\'s, where that carries the accepted name');
$h = $hit('cop mon', 'Dactylella copepodii');
check(($h['via'] ?? '') === 'Monacrosporium copepodii' && ($h['source'] ?? '') === 'gbif',
    'and to GBIF\'s own accepted name where no Germany list has it');

// A name hit under its own spelling carries no note, however many old
// names hit it as well — GBIF knows a dozen forms of Trametes versicolor,
// and the DGfM's own f. albus once put „über …" on Boletus edulis.
$plain = true;
foreach (['trametes versicolor' => 'Trametes versicolor', 'boletus edulis' => 'Boletus edulis'] as $input => $name) {
    $top = species_search($pdo, $input)['hits'][0] ?? [];
    $plain = $plain && ($top['name'] ?? '') === $name && ($top['via'] ?? null) === null;
}
check($plain, 'a species typed as it is shown carries no „über …"');

echo "\nFauna Europaea's names (26.09.2026, migration 0135)\n";

/* The archive writes a missing specific epithet as „(null)" as well as \N,
   and 11,678 synonyms of subspecies carried the text in their name — every
   suggestion through one said „über Phalaena (null) quercus". Cleaned, the
   old name stands for the species and for its nominate subspecies. */
check((int) $pdo->query("SELECT COUNT(*) FROM map_tax_faeu WHERE binomial LIKE '%(null)%'")->fetchColumn() === 0,
    'no Fauna Europaea name carries the archive\'s „(null)"');
$r = species_search($pdo, 'phalaena quercus')['hits'];
check((bool) array_filter($r, static fn(array $h): bool =>
          $h['name'] === 'Lasiocampa quercus' && $h['via'] === 'Phalaena quercus')
      && (bool) array_filter($r, static fn(array $h): bool =>
          $h['name'] === 'Lasiocampa quercus quercus' && $h['via'] === 'Phalaena quercus'),
    'the old name leads to the species and to its nominate subspecies, both „über Phalaena quercus"');

// Fauna Europaea carries no German names, the Stuttgart list does: found
// through Fauna Europaea's old name, the species keeps its German one.
$eichenspinner = array_values(array_filter($r, static fn(array $h): bool =>
    $h['name'] === 'Lasiocampa quercus' && $h['via'] === 'Phalaena quercus'));
check(($eichenspinner[0]['german'] ?? '') === 'Eichenspinner',
    'found through a Fauna Europaea synonym, the species keeps its German name');

/* Where the two animal lists disagree about an author, the newer one's
   counts: the Stuttgart list, kept up to date at the SNSB, against Fauna
   Europaea's data of 2016 (26.09.2026). Until then the alphabet
   picked — „Nordmann" beat „J.G. Fischer". Where the Stuttgart list names
   none, Fauna Europaea's genus author stands, never one of its subgenera's,
   which sit under the genus's bare name. */
$authorOf = static function (string $input, string $name) use ($pdo): ?string {
    foreach (species_search($pdo, $input)['hits'] as $h) {
        if ($h['name'] === $name) return $h['author'];
    }
    return null;
};
check($authorOf('glareola nordmanni', 'Glareola nordmanni') === 'J.G. Fischer, 1842',
    'where the animal lists disagree, the newer list\'s author stands');
check($authorOf('acinopus', 'Acinopus') === 'Dejean, 1821' && $authorOf('acronicta', 'Acronicta') === 'Ochsenheimer, 1816',
    'a genus keeps its own author, not one of its subgenera\'s');

/* And 6,713 subspecies carried their own subgenus and epithets before the
   author — „(Lasiocampa) quercus quercus (Linnaeus, 1758)" (migration
   0136). The condition is the migration's own. */
check((int) $pdo->query("SELECT COUNT(*) FROM map_tax_faeu
                          WHERE SUBSTRING_INDEX(author, ' ', 1) LIKE '(%)'
                            AND author LIKE CONCAT(SUBSTRING_INDEX(author, ' ', 1), ' ',
                                                   SUBSTRING(binomial, CHAR_LENGTH(genus) + 2), ' %')")
          ->fetchColumn() === 0
      && $pdo->query("SELECT author FROM map_tax_faeu
                       WHERE binomial = 'Lasiocampa quercus quercus' AND taxnr = sipnr")->fetchColumn() === '(Linnaeus, 1758)',
    'no Fauna Europaea author carries its own name\'s subgenus and epithets');

echo "\nNo duplicates, and the count is right\n";

foreach (['arthonia', 'lecanora', 'anisomeridium', 'cladonia', 'boletus'] as $word) {
    $r = species_search($pdo, $word);
    $n = names($r);
    check(count($n) === count(array_unique($n)),
        "\"$word\": no name twice (" . count($n) . ')');
}

// The count has to match the page, otherwise you scroll into nothing. With
// a search that has few hits, both are directly comparable.
$r = species_search($pdo, 'peltigera canina');
check($r['total'] === count($r['hits']),
    'with short results the total equals the page length');

// Nothing may repeat across the page boundary.
$first  = species_search($pdo, 'cladonia', 0);
$second = species_search($pdo, 'cladonia', SPECIES_PER_PAGE);
check(array_intersect(names($first), names($second)) === [],
    'the second page does not repeat the first');
check($first['total'] === $second['total'],
    'the total stays the same across the pages');

echo "\nThe packages\n";

$packages = species_packages();
check(isset($packages['fungi'], $packages['lichens'], $packages['plants'], $packages['gbif']),
    'there are fungi, lichens, plants and GBIF');
check(species_package('gibtsnicht') === null, 'an unknown package gives null');

foreach ($packages as $packageId => $p) {
    $rev = species_package_rev($pdo, $packageId);
    check($rev['rows'] > 0, "$packageId: the table is filled ({$rev['rows']})");
    // … and the package's own revision, where one was counted up
    // (species_package_rev): the fungi package carries r1 since the names
    // took their sensu qualifier (13.09.2026).
    $expectedVersion = SPECIES_PACKAGE_FORMAT . '-' . $rev['rows']
        . (($p['revision'] ?? 0) > 0 ? '-r' . $p['revision'] : '');
    check($rev['version'] === $expectedVersion,
        "$packageId: the revision names format, row count and package revision");

    // Walk through row by row, never everything at once: the GBIF package
    // otherwise blows the same memory it would also blow on the server.
    $valid = [];
    $rows = $references = 0;
    $real = 0;
    $gz = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
    $buffer = '';
    $targets = [];

    foreach (species_package_rows($pdo, $packageId) as $row) {
        $rows++;
        if (count($row) === 4) { $valid[$row[0]] = true; }
        else { $references++; $targets[$row[4]] = true; }

        $buffer .= json_encode($row, JSON_UNESCAPED_UNICODE) . ',';
        if (strlen($buffer) > 1 << 20) {
            $real += strlen(deflate_add($gz, $buffer));
            $buffer = '';
        }
    }
    $real += strlen(deflate_add($gz, $buffer, ZLIB_FINISH));

    check($rows === $rev['rows'],
        "$packageId: the package holds as many rows as the revision says");

    // A synonym points to a name that also exists in the same package.
    // Otherwise the search leads someone to a name and then does not have it.
    $blind = 0;
    foreach ($targets as $target => $_) if (!isset($valid[$target])) $blind++;
    check($blind === 0, "$packageId: all $references synonym references hit a valid name"
        . ($blind ? " — $blind into the void" : ''));

    // The size figure is meant to match the compressed delivery, not just anything.
    $deviation = abs($rev['bytes'] - $real) / $real;
    check($deviation < 0.10, sprintf(
        '%s: estimated size is %.1f %% off the real %s',
        $packageId, $deviation * 100,
        $real > 1048576
            ? number_format($real / 1048576, 1, ',', '.') . ' MB'
            : number_format($real / 1024, 0, ',', '.') . ' kB'));
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
