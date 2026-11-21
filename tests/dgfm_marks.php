<?php
/**
 * Tests the DGfM marks (migration 0108) — the four lists as marks on the
 * fungus reference.
 *
 *   php tests/dgfm_marks.php
 *
 * WHY THIS IS TESTED. The lists carry names, not numbers, and the whole
 * value of the feature is the resolution: „Galerina marginata agg." has
 * to reach the Gifthäubling's taxon, „Cortinarius spp." every
 * Cortinarius, a synonym its accepted name — and a heading like
 * „Agaricus" nothing at all. Whoever sifts by „Giftpilz" and the
 * resolution is off gets a map that says a poisonous species is not one.
 *
 * THE CHAIN IS TESTED WHOLE, like the endangerment in tests/redlist.php:
 * a CSV of its own (not the repository's — that one changes with every
 * issue) is imported into the real table, looked up by taxon, attached to
 * a point's title keys, and finally answered the way the browser answers
 * (edibilityMatches / responsibilityMatches in assets/js/filter.js).
 *
 * NEEDS the fungus reference (map_tax_dgfm) — without it there is nothing
 * to resolve against. The table is rebuilt from the repository's CSV at
 * the end, so the machine is left the way it was found.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

if (!dgfm_marks_ready($pdo)) {
    echo "Migration 0108 missing — without the table there is nothing to check.\n";
    exit(1);
}
if (!table_exists($pdo, 'map_tax_dgfm')
    || (int) $pdo->query('SELECT COUNT(*) FROM map_tax_dgfm')->fetchColumn() === 0) {
    echo "The fungus reference (map_tax_dgfm) is empty — nothing to resolve against.\n";
    exit(1);
}

// Real numbers from the reference, so the test does not depend on the
// DGfM's spelling of the day.
$taxnr = static function (string $binomial) use ($pdo): ?int {
    $q = $pdo->prepare("SELECT taxnr FROM map_tax_dgfm WHERE binomial = ? AND taxon_rank = 'Art' LIMIT 1");
    $q->execute([$binomial]);
    $v = $q->fetchColumn();
    return $v === false ? null : (int) $v;
};
$edulis    = $taxnr('Boletus edulis');
$galerina  = $taxnr('Galerina marginata');
$orellanus = $taxnr('Cortinarius orellanus');
$vesca     = $taxnr('Russula vesca');
if ($edulis === null || $galerina === null || $orellanus === null || $vesca === null) {
    echo "The reference lacks one of the three sample species — nothing to check.\n";
    exit(1);
}
// A synonym row pointing at one of them, if the reference has one.
$q = $pdo->prepare("SELECT taxnr FROM map_tax_dgfm WHERE sipnr = ? AND taxnr <> sipnr LIMIT 1");
$q->execute([$edulis]);
$edulisSynonym = $q->fetchColumn();

$csv = tempnam(sys_get_temp_dir(), 'dgfm');
file_put_contents($csv, implode("\n", [
    'list;name;genus;species;german;value;remark;syndrome;source_date',
    'edible;Boletus edulis;;Boletus edulis;Steinpilz;;§;;2026-05-01',
    'poisonous;Galerina marginata agg.;;Galerina marginata;Gifthäubling;;;Amatoxin-Syndrom;2026-05-01',
    'poisonous;Cortinarius spp.;Cortinarius;;Schleierlinge;;grüne, gelbe Pilze;Orellanus-Syndrom;2026-05-01',
    'inconsistent;Boletus edulis;;Boletus edulis;Steinpilz;;roh unverträglich;;2026-05-01',
    'responsibility;Boletus edulis;;Boletus edulis;Steinpilz;!!;;;2026-09-17',
    'edible;Tubera aestivum;;Tubera aestivum;Sommertrüffel;;;;2026-05-01',
    'poisonous;Trogia venenata;;Trogia venenata;;;China;Yunnan sudden death syndrome;2026-05-01',
    'poisonous;Russula spp.;Russula;;Täublinge;;Scharfe und bittere Arten;Gastrointestinales Syndrom;2026-05-01',
    'edible;Unbekannt spp.;Unbekannt;;;;;;2026-05-01',
]) . "\n");

try {
    echo "\nImporting\n";
    $r = dgfm_marks_import($pdo, $csv);
    check($r['rows'] === 9, 'nine rows read');
    check($r['matched'] === 6, 'six of them resolved');
    check($r['genera'] === 1, 'one of those by genus');
    check($r['subsets'] === 1, 'one genus row means only part of the genus — „scharfe und bittere Arten"');
    check($r['aliased'] === 1, 'one through the spelling table — Tubera is Tuber');
    check($r['unmatched'] === ['Trogia venenata', 'Unbekannt spp.'],
        'a species Germany does not have and the unknown genus are named, not guessed');
    $row = $pdo->query("SELECT species, matched FROM map_dgfm_marks WHERE name = 'Tubera aestivum'")->fetch();
    check($row['species'] === 'Tubera aestivum' && $row['matched'] === 'species',
        'the aliased row keeps the DGfM\'s spelling and counts as matched');
    $row = $pdo->query("SELECT taxnr, matched FROM map_dgfm_marks WHERE name = 'Galerina marginata agg.'")->fetch();
    check((int) $row['taxnr'] === $galerina && $row['matched'] === 'species',
        '„agg." is dropped and the species reaches its taxon');

    echo "\nLooking up on the taxon\n";
    $marks = species_dgfm_marks($pdo, ["fungi:$edulis", "fungi:$galerina", "fungi:$orellanus", 'plants:abc']);
    $lists = static fn(array $m): array => array_column($m, 'list');
    check($lists($marks["fungi:$edulis"] ?? []) === ['edible', 'inconsistent', 'responsibility'],
        'the Steinpilz carries its three marks in the lists\' order');
    check(($marks["fungi:$edulis"][2]['value'] ?? null) === '!!', 'the responsibility keeps its !!');
    check($lists($marks["fungi:$galerina"] ?? []) === ['poisonous'], 'the Gifthäubling is poisonous');
    check(($marks["fungi:$galerina"][0]['syndrome'] ?? null) === 'Amatoxin-Syndrom', 'with its syndrome');
    check($lists($marks["fungi:$orellanus"] ?? []) === ['poisonous']
        && ($marks["fungi:$orellanus"][0]['genus'] ?? false) === true,
        'Cortinarius orellanus is poisonous through its genus, and says so');
    check(!isset($marks['plants:abc']), 'a plant is passed over');
    // 18.09.2026: Russula nigricans and ionochlora stood as
    // poisonous through „Russula spp. — scharfe und bittere Arten".
    $russula = species_dgfm_marks($pdo, ["fungi:$vesca"]);
    check(!isset($russula["fungi:$vesca"]), 'a mild Russula carries nothing from a genus row that means only the acrid ones');
    check(dgfm_genus_scope(null) === 'genus' && dgfm_genus_scope('Alle Arten sind verdächtig') === 'genus'
        && dgfm_genus_scope('roh sehr giftig') === 'genus' && dgfm_genus_scope('häufig individuell gastrointestinale Beschwerden') === 'genus',
        'no remark, „alle Arten" and a remark about the effect mean the whole genus');
    check(dgfm_genus_scope('Scharfe und bittere Arten') === 'subset' && dgfm_genus_scope('Weitere verdächtige Arten') === 'subset'
        && dgfm_genus_scope('Cortinarien mit grünen, gelben, orangenen und roten Pigmenten sind verdächtig') === 'subset',
        'a remark that names a part means a part');
    if ($edulisSynonym !== false) {
        $viaSynonym = species_dgfm_marks($pdo, ['fungi:' . $edulisSynonym]);
        check($lists($viaSynonym['fungi:' . $edulisSynonym] ?? []) === ['edible', 'inconsistent', 'responsibility'],
            'a synonym of the Steinpilz carries the accepted name\'s marks');
    }

    echo "\nOn the point, and through the filter\n";
    $keys = json_encode(['' => ['Boletus edulis' => "fungi:$edulis"], 'Wirt' => ['Cortinarius orellanus' => "fungi:$orellanus"]]);
    $own = point_dgfm_marks($keys, $marks);
    check($lists($own) === ['edible', 'inconsistent', 'responsibility'],
        'only the title species counts — the host\'s poison does not');
    check(dgfm_marks_match([], false, $own), 'nothing chosen lets everything through');
    check(dgfm_marks_match(['edible'], false, $own), 'edible matches the Steinpilz');
    check(dgfm_marks_match(['poisonous'], false, $own) === false, 'poisonous does not');
    check(dgfm_marks_match(['poisonous', 'inconsistent'], false, $own), 'two chosen means one suffices');
    check(dgfm_marks_match([], true, $own), 'the responsibility hook passes the Steinpilz');
    check(dgfm_marks_match([], true, point_dgfm_marks(json_encode(['' => ['Galerina marginata' => "fungi:$galerina"]]), $marks)) === false,
        'and stops the Gifthäubling');
    check(dgfm_marks_match(['edible'], false, []) === false, 'a point without marks drops out once a mark is chosen');

    echo "\nBy the title, where the reference has no taxon\n";
    // Trogia venenata resolved to nothing above — a find with that name at
    // the head of its title carries the mark all the same
    // (17.09.2026, for the species not yet recorded in Germany).
    $none = species_dgfm_marks($pdo, []);
    check($lists(point_dgfm_marks(null, $none, 'Trogia venenata')) === ['poisonous'],
        'the unmatched species marks a find by its title');
    check(($none['name:Trogia venenata'][0]['syndrome'] ?? null) === 'Yunnan sudden death syndrome',
        'with its syndrome');
    check($lists(point_dgfm_marks(null, $none, 'Trogia venenata cf. (Fundort Yunnan)')) === ['poisonous'],
        'whatever follows the binomial is not part of the key');
    check(point_dgfm_marks(null, $none, 'Boletus edulis') === [],
        'a resolved species does NOT match by name — its marks hang on the number');
    check(point_dgfm_marks(null, $none, 'Trogia') === [] && point_dgfm_marks(null, $none, null) === [],
        'a bare genus or no title carries nothing');

    echo "\nThe bucket fingerprints move with the table\n";
    // The failure of 17.09.2026: buckets fetched before the import kept
    // matching afterwards. Now every import moves every fingerprint.
    $anna = (int) $pdo->query("SELECT id FROM map_users WHERE email = 'anna@demo.local'")->fetchColumn();
    if ($anna > 0) {
        $before = bucket_fingerprints($pdo, $anna);
        dgfm_marks_import($pdo, $csv);
        $after = bucket_fingerprints($pdo, $anna);
        check($before !== [] && $before !== $after && array_keys($before) === array_keys($after),
            'a reimport changes every bucket\'s fingerprint and no bucket\'s key');
    }

    echo "\nThe words\n";
    check(dgfm_marks_words($own) === 'Speisepilz, uneinheitlich beurteilt, Verantwortungsart !!',
        'the export column reads the marks out with the value');
} finally {
    unlink($csv);
    // The machine as it was: the repository's CSV back into the table.
    if (is_file(dgfm_marks_csv())) {
        dgfm_marks_import($pdo);
    }
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
