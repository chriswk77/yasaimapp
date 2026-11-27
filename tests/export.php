<?php
/**
 * Tests the export writers (includes/export.php).
 *
 *   php tests/export.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE ROUND TRIP. Everything written here must come back in through our
 *   OWN reading side (csv_read, geojson_read, kml_read, gpx_read) with
 *   title, coordinates and time intact. Two modules that only look like
 *   they match is the failure this project has already had in other
 *   places — and an export nobody can import back is a dead end you only
 *   notice when you need it.
 *
 *   THE COORDINATE ORDER. GeoJSON and KML write [lon, lat], CSV and GPX
 *   write them named. A flip is the classic mistake of every exporter,
 *   and it is invisible in the file: 50.1/8.2 looks as plausible as
 *   8.2/50.1 until the point sits in the sea.
 *
 *   WHAT MUST NOT BE INVENTED. A missing elevation stays missing — a
 *   padded 0 would be read back as sea level (geojson.php and gpx.php
 *   both guard against exactly that).
 *
 *   THE CSV IS THE WIDE TABLE. Every find field that carries a value gets
 *   a column of its own; the first eight keep the names our own import
 *   recognizes.
 *
 * Writers are pure functions — no database is needed for any of this.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/export.php';
require_once __DIR__ . '/../includes/geojson.php';
require_once __DIR__ . '/../includes/kml.php';
require_once __DIR__ . '/../includes/gpx.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

/** A written file, read back through our own reader. */
function throughFile(string $body, string $extension, callable $read): array
{
    $path = tempnam(sys_get_temp_dir(), 'export') . '.' . $extension;
    file_put_contents($path, $body);
    try {
        return $read($path);
    } finally {
        @unlink($path);
    }
}

// One point with everything, one with as little as possible — the two
// cases every writer has to survive.
$rich = [
    'id' => 1, 'lat' => 50.123456, 'lon' => 8.654321,
    'title' => 'Boletus edulis', 'note' => "Am Wegrand\nzweite Zeile",
    'found_at' => '2026-08-14 09:20:00',
    'elevation_m' => 412, 'accuracy_m' => 6, 'find_number' => 'CW-2026-0042',
    /* AS AN OBJECT, because that is what a real point carries:
       point_extra_read() returns stdClass, always. The first fixture used
       a plain array — and with it every writer's is_array() guard looked
       right while it silently dropped all fields from real exports. A
       fixture that is friendlier than reality tests nothing. */
    'extra' => (object) ['Finder' => 'Anna Musterfrau', 'Substrat / Wirt' => 'Fagus sylvatica',
                'Eigenes Feld' => 'Wert'],
    'lists' => [7], 'list_names' => ['Bestimmte Funde'], 'tour' => 'Wetterau',
    'mtb' => '5816', 'mtb_quadrant' => '311', 'municipality' => 'Mittenwald',
    'region' => 'BAY', 'country' => 'DE', 'protected_area' => 'Altkönig',
    'red_list' => ['3'],
];
$bare = [
    'id' => 2, 'lat' => -33.5, 'lon' => -70.25,
    'title' => '', 'note' => null, 'found_at' => null,
    'elevation_m' => null, 'accuracy_m' => null, 'find_number' => null,
    // The empty object of a point that carries no fields — the shape that
    // made the report die with "Cannot use object of type stdClass".
    'extra' => new stdClass(), 'lists' => [], 'list_names' => [], 'tour' => null,
    'mtb' => null, 'mtb_quadrant' => null, 'municipality' => null,
    'region' => null, 'country' => null, 'protected_area' => null,
    'red_list' => [],
];
$points = [$rich, $bare];

echo "\nExport\n\n";

// ---------------------------------------------------------------------------
echo "CSV\n";
$csv = export_csv($points);
check(str_starts_with($csv, "\xEF\xBB\xBF"), 'the byte-order mark leads — otherwise Excel breaks every umlaut');
$read = throughFile($csv, 'csv', static fn($p) => csv_read($p));
// csv_guess() returns the interpretation itself: role => column index,
// '' where it found nothing.
$guess = csv_guess($read);
check($read['separator'] === ';', 'our own reader recognizes the semicolon');
check($guess['title'] !== '' && $guess['lat'] !== '' && $guess['lon'] !== ''
    && $guess['time'] !== '' && $guess['find_number'] !== '',
    'and maps title, coordinates, time and find number without anyone having to help');

$columns = $read['columns'];
check(in_array('Substrat / Wirt', $columns, true) && in_array('Eigenes Feld', $columns, true),
    'every field that carries a value has a column — ' . implode(' | ', array_slice($columns, 0, 12)));
check(array_search('Finder', $columns, true) < array_search('Eigenes Feld', $columns, true),
    'the find fields lead, the custom ones follow');
check(in_array('Tour', $columns, true) && in_array('Listen', $columns, true),
    'tour and lists stand in columns of their own');

/* THE SHEET REFERENCE, and it is not a detail: this column lands at a
   mapping office. All three digits go along ("311" — quarter, sixteenth,
   sixty-fourth); they are what makes a find locatable to about 1.4 km, and
   out of the file nobody can compute them back. */
$mtbColumn = array_search('MTB', $columns, true);
check($mtbColumn !== false, 'there is an MTB column');
check($mtbColumn !== false && $read['rows'][0][$mtbColumn] === '5816/311',
    'and it carries sheet, quarter, sixteenth and sixty-fourth'
        . ($mtbColumn === false ? '' : ' (found: ' . $read['rows'][0][$mtbColumn] . ')'));

$point = csv_point($read['rows'][0], $guess, $columns);
check($point !== null && abs($point['lat'] - 50.123456) < 1e-6
    && abs($point['lon'] - 8.654321) < 1e-6,
    'the coordinates come back unflipped');
check($point !== null && $point['title'] === 'Boletus edulis', 'the title comes back');
check($point !== null && str_starts_with((string) $point['found_at'], '2026-08-14'),
    'the find time comes back — ' . var_export($point['found_at'] ?? null, true));

// ---------------------------------------------------------------------------
echo "\nGeoJSON\n";
$geo = export_geojson($points);
$back = throughFile($geo, 'geojson', static fn($p) => geojson_read($p));
check(count($back['points']) === 2, 'both points come back');
check(abs($back['points'][0]['lat'] - 50.123456) < 1e-6
    && abs($back['points'][0]['lon'] - 8.654321) < 1e-6,
    'lon/lat order survives the round trip');
check($back['points'][0]['title'] === 'Boletus edulis', 'the title comes back');
check(($back['points'][0]['elevation_m'] ?? null) === 412, 'the elevation comes back');
check(($back['points'][1]['elevation_m'] ?? null) === null,
    'and a missing one stays missing — a padded 0 would mean sea level');
check(str_contains($geo, 'Fagus sylvatica'),
    'what the format has no field for rides along in the description');

// ---------------------------------------------------------------------------
echo "\nKML\n";
$kml = export_kml($points, 'Karte');
// kml_read groups by folder — the Document name becomes the one list, and
// that is where the points sit.
$back = throughFile($kml, 'kml', static fn($p) => kml_read($p));
check(($back['lists'][0]['name'] ?? '') === 'Karte',
    'the document name becomes the list the import offers');
$back = ['points' => $back['lists'][0]['points'] ?? []];
check(count($back['points']) === 2, 'both points come back');
check(abs($back['points'][0]['lat'] - 50.123456) < 1e-6
    && abs($back['points'][0]['lon'] - 8.654321) < 1e-6,
    'lon,lat order survives the round trip');
check($back['points'][0]['title'] === 'Boletus edulis', 'the title comes back');
check($back['points'][1]['title'] === 'Ohne Titel',
    'a point without a title gets the stand-in — an empty <name> would read as broken');
check(str_contains($kml, '<TimeStamp>'), 'the find time stands where Google Earth expects it');

// ---------------------------------------------------------------------------
echo "\nGPX\n";
$gpx = export_gpx($points, 'Karte');
$back = throughFile($gpx, 'gpx', static fn($p) => gpx_read($p));
check(count($back['points']) === 2, 'both points come back');
check(abs($back['points'][0]['lat'] - 50.123456) < 1e-6
    && abs($back['points'][0]['lon'] - 8.654321) < 1e-6,
    'the coordinates come back unflipped');
check($back['points'][0]['title'] === 'Boletus edulis', 'the title comes back');
check(str_starts_with((string) ($back['points'][0]['found_at'] ?? ''), '2026-08-14'),
    'the time comes back as the local moment it was — '
        . var_export($back['points'][0]['found_at'] ?? null, true));
check(!str_contains($gpx, '<ele></ele>'), 'no empty elevation element for a point without one');

// ---------------------------------------------------------------------------
echo "\nThe file itself\n";
$file = export_build($points, 'csv', 'Karte');
check(str_ends_with($file['name'], '.csv') && str_starts_with($file['name'], 'Karte-'),
    'name and extension fit the format — ' . $file['name']);
check(str_contains($file['mime'], 'csv'), 'and the mime type too');
$rejected = false;
try { export_build($points, 'docx', 'Karte'); } catch (InvalidArgumentException $e) { $rejected = true; }
check($rejected, 'an unknown format is refused instead of quietly producing something');

// ---------------------------------------------------------------------------
echo "\nThe species list — same species, one entry\n";
{
    $ref  = static fn(string $title, string $who) => ['id' => 0, 'title' => $title,
        'extra_species' => json_encode(['' => ['dgfm:' . crc32($title)]]), 'extra' => ['Finder' => $who]];
    $text = static fn(string $title, string $who) => ['id' => 0, 'title' => $title,
        'extra_species' => null, 'extra' => ['Finder' => $who]];
    $g = report_merge_groups([
        $ref('Boletus edulis', 'Anna'),          // the leader's, picked from the directory
        $text('Boletus edulis', 'PRUEF Paula'),  // a guest's, typed by hand
        $text('boletus  edulis', 'PRUEF Till'),  // typed again, other spacing and case
        $text('Amanita muscaria', 'PRUEF Paula'),
        $ref('Cantharellus cibarius', 'Anna'),
    ]);
    check(count($g) === 3, 'five finds, three species — ' . count($g) . ' groups');
    check(count($g[0]) === 3 && $g[0][0]['extra']['Finder'] === 'Anna' && $g[0][2]['extra']['Finder'] === 'PRUEF Till',
        'the guests\' typed names join the leader\'s referenced find, in the order seen');
    check(count($g[1]) === 1 && $g[1][0]['title'] === 'Amanita muscaria',
        'a species only the guests found stands on its own');
    $h = report_merge_groups([$text('Boletus edulis', 'A'), $text('Boletus edulis', 'B')]);
    check(count($h) === 1 && count($h[0]) === 2, 'two typed names without any reference still merge');
    $k = report_merge_groups([$ref('Boletus edulis', 'A'), $text('Boletus edulis agg.', 'B')]);
    check(count($k) === 2, 'a different name stays a different entry — no fuzziness');
    // Two people, two directories, one fungus — the case of the second report.
    $other = static fn(string $title, string $who) => ['id' => 0, 'title' => $title,
        'extra_species' => json_encode(['' => ['eigene:' . crc32($title)]]), 'extra' => ['Finder' => $who]];
    $m = report_merge_groups([$ref('Boletus edulis', 'Anna'), $other('Boletus edulis', 'Ben')]);
    check(count($m) === 1 && count($m[0]) === 2, 'the same name under two different references is one entry');
    // And the relation carries over: a third find with only Ben's reference joins too.
    $chain = report_merge_groups([$ref('Boletus edulis', 'Anna'), $other('Boletus edulis', 'Ben'),
        ['id' => 0, 'title' => 'Steinpilz', 'extra_species' => json_encode(['' => ['eigene:' . crc32('Boletus edulis')]]), 'extra' => []]]);
    check(count($chain) === 1 && count($chain[0]) === 3, 'a find sharing only the second reference joins through it');
}

// ---------------------------------------------------------------------------
/* THE MYKIS SHEET goes to a state coordinator and from there into the
   DGfM's database — a contract with a program we cannot see, so what it
   promises is held here: the template's head, the German spreadsheet's
   notation, and above all WHAT STAYS OUT. The coordinates are the point:
   they go only at the step the finder chose, and a writer that lets
   lat/lon slip through a coarser one gives away where the rare species
   stand. */
echo "\nThe MykIS sheet\n";
{
    /* MykIS' lists in miniature — the real ones live on the server only
       (mykis_hosts.php) and this test must not depend on that file. */
    mykis_lists([
        'made'  => '2026-11-27',
        'hosts' => [['Fagus sylvatica', '', 'Rot-Buche'], ['Castanea sativa', '', 'Ess-Kastanie'],
                    ['Salix alba', '', 'Weide'], ['Salix caprea', '', 'Weide'], ['Acer sp.', '', 'Ahorn-Art'],
                    ['Hausrind', 'Bos primigenius taurus', 'Hausrind']],
        'organ' => ['Laubstreu', 'Zapfen', 'liegender Ast'],
    ]);
    $parse = static function (string $csv): array {
        $lines = explode("\r\n", rtrim(substr($csv, 3), "\r\n"));
        return array_map(static fn(string $l): array => str_getcsv($l, ';', '"', ''), $lines);
    };
    /* A FIND THAT MAY GO: own, named after the DGfM's reference list (the
       title linked to a 'fungi:' entry) and determined with certainty —
       everything else below differs from it in exactly one thing. */
    $sure = ['extra_species' => json_encode(['' => ['Boletus edulis' => 'fungi:1234']]),
             'extra' => (object) ['Finder' => 'Anna Musterfrau', 'Substrat / Wirt' => 'Fagus sylvatica',
                                  'Begleitvegetation' => "Quercus robur\nCarpinus betulus",
                                  'Bestimmungssicherheit' => 'makroskopisch sicher']] + $rich;
    $austria = ['country' => 'AT', 'mtb' => null] + $sure;
    $place   = ['title' => 'Allee'] + $sure;
    $undated = ['found_at' => null] + $sure;
    $foreign = ['owner' => 'Finn Horn'] + $sure;
    $gbif    = ['extra_species' => json_encode(['' => ['Boletus edulis' => 'gbif:5']])] + $sure;
    $unsure  = ['extra' => (object) ['Bestimmungssicherheit' => 'unsicher']] + $sure;
    // No mark at all: sure by its name (25.09.2026) — unless the
    // name hedges.
    $unmarked = ['extra' => (object) ['Finder' => 'Anna Musterfrau']] + $sure;
    $hedged   = ['title' => 'Cortinarius anomalus cf.',
                 'extra_species' => json_encode(['' => ['Cortinarius anomalus cf.' => 'fungi:9']])] + $unmarked;
    // An aggregate is no hedge (27.09.2026): a mapping unit of
    // its own, the TaxRef carries some of them.
    $aggregate = ['title' => 'Cortinarius anomalus agg.',
                  'extra_species' => json_encode(['' => ['Cortinarius anomalus agg.' => 'fungi:9']])] + $unmarked;
    $genetic  = ['extra' => (object) ['Bestimmungssicherheit' => 'genetisch sicher']] + $sure;
    $undetermined = ['extra' => (object) ['Bestimmungssicherheit' => 'unbestimmt']] + $sure;
    $tricky  = ['title' => 'Laccaria laccata var. pallidifolia',
                'extra_species' => json_encode(['' => ['Laccaria laccata var. pallidifolia' => 'fungi:77']]),
                'extra' => (object) ['Finder' => '=HYPERLINK("x")', 'Bestimmer' => 'Sebastian Schröder und Volker Maier',
                                     'Bestimmungssicherheit' => 'mikroskopisch sicher', 'Beleg-Nr.' => '11234']] + $rich;
    $csv  = mykis_csv(mykis_rows([$sure, $bare, $austria, $place, $undated, $foreign, $gbif, $unsure,
                                  $undetermined, $hedged, $tricky], 'Schröder, Sebastian', 'exact'));
    $rows = $parse($csv);
    $at   = static fn(array $row, string $column) => $row[array_search($column, MYKIS_COLUMNS, true)] ?? null;

    check(str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, "\r\n"),
        'byte-order mark and Windows line ends — a German Excel opens it without asking');
    /* THE HEAD AGAINST A FROZEN COPY of mykdaten.xls (Frank Dämmrich, May
       2021), not against the constant itself — those names are MykIS's, not
       ours, and a well-meant „cleanup" into English or a trimmed „MTB"
       would pass a test that compares the constant with itself (the trap
       tests/services.php guards for the map services). Whoever changes
       this line states that the DGfM changed its template. */
    $template = 'BASIS_artgruppe;BASIS_methode;BASIS_datum1;datum2;erfasser;nachweisquelle;beobachtung_bemerkungen;'
        . 'BASIS_ort;BASIS_ortslage;name_staat;name_provinz;    MTB;name_kreis;plz;hoehenstufe;ozeanitaet;zonalitaet;'
        . 'hoehe;rechtswert1;hochwert1;ostwert1;nordwert1;geolgrad1;geolminute1;geolsekunde1;geobgrad1;geobminute1;'
        . 'geobsekunde1;geolgrad2;geolminute2;geolsekunde2;geobgrad2;geobminute2;geobsekunde2;fo_bemerkung;GATTUNG;ART;'
        . 'sammler;bestimmer;Wirt;stadium;art_bemerkung;rev;verbleib;organ_substrat;substrat_text;substratzustand;'
        . 'wuchsstelle;sonderstandort;beleg_nr;art_nr;Foto_Zeichnung;Beleg;Ungenauigkeit;Qualität';
    check(implode(';', MYKIS_COLUMNS) === $template && $rows[0] === MYKIS_COLUMNS,
        'the head is the template\'s 55 columns word for word, „    MTB" with its spaces included');
    check(count($rows) === 3, 'only own, German, dated TaxRef finds determined with certainty are rows — ' . (count($rows) - 1));
    check(!mykis_eligible($foreign) && !mykis_eligible($gbif) && !mykis_eligible($unsure) && !mykis_eligible($undetermined),
        'a foreign find, a GBIF name, an uncertain and an undetermined one stay home');
    check(mykis_eligible($unmarked) && !mykis_eligible($hedged),
        'an unmarked find goes as macroscopically sure — unless its name hedges („cf.")');
    check(mykis_eligible($aggregate),
        'an unmarked aggregate goes too — „agg." names what is mapped, it hedges nothing');
    check($at(mykis_row($aggregate, 'X'), 'ART') === 'anomalus agg.',
        'and the „agg." travels on in the ART column');
    check(mykis_eligible($genetic), 'the new level „genetisch sicher" goes');
    /* OTHER FINDERS' FINDS ONLY ON THE ACCOUNT'S WORD (0133): Anna's
       account sends her finds in any spelling of her name, and a find of
       Volker's in her stock — one she only determined — only with the
       tick. */
    $anna    = ['first_name' => 'Anna', 'last_name' => 'Musterfrau'];
    $self    = mykis_self($anna);
    $spelled = ['extra' => (object) ['Finder' => 'Musterfrau, anna']] + $sure;
    $nobody  = ['extra' => (object) []] + $sure;
    $volker  = ['extra' => (object) ['Finder' => 'Volker Maier', 'Bestimmer' => 'Anna Musterfrau']] + $sure;
    check(mykis_eligible($sure, $self) && mykis_eligible($spelled, $self) && mykis_eligible($nobody, $self),
        'the account\'s own finds go — its name in any spelling, or no finder at all');
    check(!mykis_eligible($volker, $self),
        'a find with somebody else as finder stays home, even one the account determined');
    check(mykis_self(['mykis_foreign' => '1'] + $anna) === null && mykis_eligible($volker, null),
        'with the old single tick it goes, under its own finder — before 0144 that tick stands for both');
    /* TWO TICKS SINCE 0144 (27.09.2026): the finds the account
       determined, and the rest — a list somebody handed in, a deceased
       member's finds. An empty Bestimmer on another finder's find is the
       rest: the account's name is not on it. */
    $handed  = ['extra' => (object) ['Finder' => 'Volker Maier', 'Bestimmer' => 'Volker Maier']] + $sure;
    $bare    = ['extra' => (object) ['Finder' => 'Volker Maier']] + $sure;
    $onlyDetermined = mykis_self(['mykis_foreign' => 0, 'mykis_determined' => 1] + $anna);
    $onlyRest       = mykis_self(['mykis_foreign' => 1, 'mykis_determined' => 0] + $anna);
    check(mykis_eligible($volker, $onlyDetermined) && !mykis_eligible($handed, $onlyDetermined)
        && !mykis_eligible($bare, $onlyDetermined) && mykis_eligible($sure, $onlyDetermined),
        'the first tick takes the finds the account determined — not a list it was handed');
    check(!mykis_eligible($volker, $onlyRest) && mykis_eligible($handed, $onlyRest) && mykis_eligible($bare, $onlyRest),
        'the second takes the rest, an empty Bestimmer among it');
    check(mykis_self(['mykis_foreign' => 1, 'mykis_determined' => 1] + $anna) === null
        && mykis_self(['mykis_foreign' => 0, 'mykis_determined' => 0] + $anna)['tokens'] === $self['tokens'],
        'both ticks mean every find; none means own finds only');
    check(count(mykis_rows([$sure, $volker], 'Musterfrau, Anna', null, $self)) === 1
        && count(mykis_rows([$sure, $volker], 'Musterfrau, Anna')) === 2,
        'the sheet by hand asks the same question');
    check(mykis_row($unmarked, 'X')[array_search('Qualität', MYKIS_COLUMNS, true)] === '',
        'and an unmarked find\'s Qualität stays empty — assumed, not claimed');
    /* THE TWIN: mykis_open_marks() reads a title exactly as openMarksIn()
       in identification.js does — the cases and traps of
       tests/identification.mjs, held here to the PHP side. */
    $twinOpen = ['Ramariopsis cf. luteo-ochracea', 'Cortinarius cf anomalus', 'Russula Cf. Vesca', 'Amanita cfr. rubescens',
        'Russula c.f. vesca', 'Russula aff. nigricans', 'Entoloma affin. sericeum', 'Scutellinia spec', 'Amanita sp.',
        'Entoloma spp.', 'Hygrocybe species', 'Russula s.l.', 'Russula s. l.', 'Russula sl', 'Clitocybe s. lat.',
        'Armillaria sensu lato', 'Galerina marginata agg.', 'Galerina marginata aggr.'];
    $twinSure = ['Inocybe splendens', 'Suillus luteus', 'Sparassis crispa', 'Boletus speciosus', 'Russula affinis',
        'Lactarius affinis', 'Inocybe affinis Peck', 'Clavulinopsis helveola', 'Amanita muscaria L.', 'Boletus edulis Bull.',
        'Agaricus campestris L. s.str.', 'Hygrocybe conica', 'Boletus edulis s. str.', 'Boletus edulis sensu stricto'];
    check(array_filter($twinOpen, static fn(string $n): bool => mykis_open_marks($n) === []) === [],
        'every open mark the filter knows is read — cf., aff., sp., s. l., agg. in all their spellings');
    check(array_filter($twinOpen, static fn(string $n): bool
            => !mykis_hedged($n) && array_intersect(['agg', 'sl'], mykis_open_marks($n)) === []) === []
        && !mykis_hedged('Galerina marginata agg.') && !mykis_hedged('Galerina marginata aggr.')
        && !mykis_hedged('Russula s. l.') && !mykis_hedged('Armillaria sensu lato') && mykis_hedged('Russula cf. vesca'),
        'and all of them hedge a name for MykIS — except the aggregate and the broad sense, agg. and s. l.');
    check(array_filter($twinSure, static fn(string $n): bool => mykis_hedged($n)) === [],
        'and the filter\'s traps hold: affinis, splendens, Suillus, Linné\'s „L.", s. str.');
    check(mykis_open_marks('Boletus edulis s. str.') === ['sstr'] && mykis_open_marks('Russula s. l.') === ['sl'],
        'the families come out as the filter names them');
    check(mykis_hedged('Tricholoma frondosae o. equestre') && mykis_hedged('Boletus edulis?'),
        'and for MykIS only: a choice between two names, a question mark');

    /* THE FINDER'S RULES (0143, 27.09.2026): exclusions, a step
       for chosen Red List categories, one for edible mushrooms, lists of
       genera and species with steps of their own — and where several fit,
       THE COARSEST STEP WINS. */
    $rules = mykis_rules_read(json_encode([
        'exclude' => ['genera' => ['Morchella', 'morchella', ' '], 'species' => ['Craterellus cornucopioides']],
        'redlist' => ['categories' => ['1', '2', '3', '*', 'x'], 'step' => 'q16'],
        'edible'  => 'q64',
        'genera'  => ['Boletus' => 'q4', 'Cortinarius' => 'exact', 'Russula' => 'nonsense'],
        'species' => ['Boletus regius' => 'exact', 'Hericium coralloides' => 'q16'],
    ]));
    check($rules['exclude']['genera'] === ['Morchella'] && $rules['redlist'] === ['1' => 'q16', '2' => 'q16', '3' => 'q16']
        && !isset($rules['genera']['Russula']) && mykis_rules_read('{bent') === mykis_rules_read(null),
        'the rules are read clean: doubles, blanks, unknown categories and steps fall away; bent JSON means none'
        . ' — and the Red List of before 28.09.2026 (ticked categories, one step) comes back one step per category');
    /* ONE STEP PER CATEGORY since 28.09.2026 („jede Stufe einzeln
       in der Schärfe einstellen") — and where a find carries two, the
       coarser wins, as everywhere. */
    $perCategory = mykis_rules_read(json_encode(['redlist' => ['1' => 'q4', '3' => 'q64', 'V' => 'nonsense', '*' => 'q4']]));
    check($perCategory['redlist'] === ['1' => 'q4', '3' => 'q64'],
        'each category its own step; an unknown step or a category without threat falls away');
    // Categories 0 and 1 are array keys 0 and 1 — json_encode writes them
    // as a LIST, and a list must read back as the same categories.
    $stored = json_encode(mykis_rules_read(['redlist' => ['0' => 'q4', '1' => 'q16']]));
    check(str_contains($stored, '"redlist":["q4","q16"]') && mykis_rules_read($stored)['redlist'] === ['0' => 'q4', '1' => 'q16'],
        'categories 0 and 1 stored as a JSON list come back as categories 0 and 1 — ' . $stored);
    // Without Red List and DGfM marks unless a check gives them — the base
    // find stands on the Red List (category 3), and the rule for 1–3 would
    // fit every one of them.
    $taxon = static fn(string $title, array $more = []): array
        => ['title' => $title] + $more + ['red_list' => [], 'dgfm' => []] + $sure;
    check(!mykis_eligible($taxon('Morchella esculenta'), null, $rules)
        && !mykis_eligible($taxon('Craterellus cornucopioides agg.'), null, $rules)
        && mykis_eligible($taxon('Craterellus tubaeformis'), null, $rules),
        'an excluded genus or species is not eligible at all — the aggregate of an excluded species neither');
    check(count(mykis_rows([$taxon('Morchella esculenta'), $sure], 'X', null, null, $rules)) === 1,
        'and it leaves the sheet, not only the delivery');
    check(mykis_step($taxon('Amanita muscaria'), $rules, 'q16') === 'q16',
        'where no rule fits, the step for „alle übrigen" holds');
    check(mykis_step($taxon('Cortinarius caperatus'), $rules, 'q4') === 'exact',
        'a genus rule may be finer than „alle übrigen"');
    check(mykis_step($taxon('Boletus regius'), $rules, 'q4') === 'q4',
        'genus Boletus coarse and species Boletus regius exact: THE COARSEST WINS — his call, „am vorsichtigsten"');
    check(mykis_step($taxon('Hericium coralloides', ['red_list' => ['2']]), $rules, 'exact') === 'q16'
        && mykis_step($taxon('Hericium erinaceus', ['red_list' => ['2']]), $rules, 'exact') === 'q16'
        && mykis_step($taxon('Hericium erinaceus', ['red_list' => ['V']]), $rules, 'exact') === 'exact',
        'a ticked Red List category brings its step, an unticked one does not');
    check(mykis_step($taxon('Cantharellus cibarius', ['dgfm' => [['list' => 'edible']]]), $rules, 'exact') === 'q64'
        && mykis_step($taxon('Cantharellus cibarius', ['dgfm' => [['list' => 'edible']], 'red_list' => ['3']]), $rules, 'exact') === 'q16',
        'an edible mushroom brings the edible step — and a red-listed edible one the coarser of the two');
    check(mykis_step($taxon('Hericium erinaceus', ['red_list' => ['3']]), $perCategory, 'exact') === 'q64'
        && mykis_step($taxon('Hericium erinaceus', ['red_list' => ['1']]), $perCategory, 'exact') === 'q4'
        && mykis_step($taxon('Hericium erinaceus', ['red_list' => ['3', '1']]), $perCategory, 'exact') === 'q4'
        && mykis_step($taxon('Hericium erinaceus', ['red_list' => ['2']]), $perCategory, 'exact') === 'exact',
        'a category brings its own step, two bring the coarser, one without a rule none');
    check(mykis_rules_any($rules) && !mykis_rules_any(mykis_rules_read(null)) && mykis_rules_any($perCategory),
        'whether any rule is set, for the coordinator\'s line');
    /* THE SHEET BY HAND ASKS ITSELF (F1): a fixed step for every find, or
       the finder's rules — the exclusions hold either way, and without the
       consent there are no rules at all, whatever the request says. */
    $account = ['first_name' => 'Anna', 'last_name' => 'Musterfrau', 'mykis_consent_at' => '2026-09-27 08:00:00',
                'mykis_precision' => 'q16', 'mykis_foreign' => 0, 'mykis_rules' => json_encode($rules)];
    $byRules = mykis_manual_options($account, ['precision' => 'rules']);
    check($byRules['precision'] === 'q16' && $byRules['rules'] === $rules,
        'by the rules: the account\'s step for all others and every rule');
    $fixed = mykis_manual_options($account, ['precision' => 'exact']);
    check($fixed['precision'] === 'exact' && $fixed['rules']['exclude'] === $rules['exclude']
        && $fixed['rules']['genera'] === [] && $fixed['rules']['redlist'] === [] && $fixed['rules']['edible'] === null,
        'a fixed step: the steps of the rules give way, the exclusions stay');
    check(count(mykis_rows([$taxon('Morchella esculenta'), $taxon('Boletus regius')], 'X', $fixed['precision'], null, $fixed['rules'])) === 1
        && mykis_step($taxon('Boletus regius'), $fixed['rules'], $fixed['precision']) === 'exact',
        'so an excluded genus stays out while Boletus regius goes at the fixed step, not its genus rule');
    $unagreed = mykis_manual_options(['mykis_consent_at' => null] + $account, ['precision' => 'rules']);
    check($unagreed['rules'] === mykis_rules_read(null) && $unagreed['precision'] === 'q16',
        'without the consent no rules act, not even when a request asks for them');
    $asked = static fn(array $a, array $more = []): ?array => mykis_manual_options($more + $account, $a)['self'];
    check($asked(['determined' => '1', 'foreign' => '1']) === null
        && $asked(['determined' => '1', 'foreign' => '0'])['determined'] === true
        && $asked(['determined' => '1', 'foreign' => '0'])['rest'] === false
        && $asked(['determined' => '0', 'foreign' => '1'])['rest'] === true,
        'other finders\' finds as the window\'s two ticks answered');
    check($asked([], ['mykis_foreign' => 1]) === null && $asked([]) !== null
        && $asked(['foreign' => '', 'determined' => ''], ['mykis_foreign' => 1, 'mykis_determined' => 1]) === null
        && $asked([], ['mykis_foreign' => 0, 'mykis_determined' => 1])['determined'] === true,
        'and as the account chose where the window did not ask — the old single tick standing for both');
    $next = static fn(string $day): string => mykis_next_delivery(new DateTimeImmutable($day));
    check($next('2026-01-15') === 'Anfang Juni' && $next('2026-05-31') === 'Anfang Juni'
        && $next('2026-06-01') === 'Anfang Dezember' && $next('2026-11-30') === 'Anfang Dezember'
        && $next('2026-12-01') === 'Anfang Juni',
        'the next delivery the settings name: June\'s run took what was there on the 1st, so June waits for December');
    $rowsByRule = mykis_rows([$taxon('Boletus regius'), $taxon('Cortinarius caperatus')], 'X', 'q16', null, $rules);
    check($at($rowsByRule[0], '    MTB') === '5816,3' && $at($rowsByRule[1], '    MTB') === '5816,311',
        'each row carries its own step: the coarse Boletus to the quarter, the exact Cortinarius to the 64th');
    $r = $rows[1];
    check($at($r, 'BASIS_datum1') === '14.08.2026' && $at($r, 'datum2') === '14.08.2026', 'the day as a German Excel reads a date');
    check($at($r, '    MTB') === '5816,311', 'the MTB with all three digits, to the 64th, with a decimal comma — ' . $at($r, '    MTB'));
    check($at($r, 'erfasser') === 'Schröder, Sebastian', 'erfasser is the account that recorded the find (Volker)');
    check($at($r, 'GATTUNG') === 'Boletus' && $at($r, 'ART') === 'edulis', 'genus and species from the title');
    check($at($r, 'name_staat') === 'Deutschland' && $at($r, 'name_provinz') === 'Bayern' && $at($r, 'BASIS_ort') === 'Mittenwald',
        'state, province and place spelled out');
    check($at($r, 'sammler') === 'Musterfrau, Anna' && $at($r, 'bestimmer') === 'Musterfrau, Anna',
        'the finder field turned round; an empty determiner is the finder\'s, not the holder\'s (1a)');
    $ownRow = mykis_row(['extra' => (object) ['Bestimmungssicherheit' => 'makroskopisch sicher']] + $sure, 'Schröder, Sebastian', 'q4');
    check($at($ownRow, 'sammler') === 'Schröder, Sebastian' && $at($ownRow, 'bestimmer') === 'Schröder, Sebastian',
        'with no finder either, finder and determiner are the holder');
    check($at($r, 'Wirt') === 'Fagus sylvatica' && $at($r, 'organ_substrat') === '' && $at($r, 'substrat_text') === '',
        'a host of MykIS\' list goes to Wirt alone — ' . $at($r, 'Wirt'));
    $placed = static fn(string $v): array => array_values(mykis_substrate($v));
    check($placed('Laubstreu') === ['', 'Laubstreu', ''], 'a word of the substrate list goes to organ_substrat');
    check($placed('Kiefer totholz') === ['', '', 'Kiefer totholz'], 'anything else to the free substrat_text');
    check($placed('Esskastanie') === ['Castanea sativa', '', '']
        && $placed('Bos taurus') === ['', '', 'Bos taurus']
        && $placed('Bos primigenius taurus') === ['Hausrind', '', '']
        && $placed('Acer spec.') === ['Acer sp.', '', ''] && $placed('fagus  sylvatica') === ['Fagus sylvatica', '', ''],
        'a unique German name, a synonym, „spec." and loose spelling become MykIS\' own name');
    check($placed('Weide') === ['', '', 'Weide'], 'a German name two hosts share names none of them');
    check($placed('Fagus sylvatica, Laubstreu; Zapfen') === ['Fagus sylvatica', 'Laubstreu', 'Zapfen'],
        'a value is cut at commas and semicolons, one entry per checked column, the second into the free text');
    mykis_lists(false);
    $path = mykis_lists_path();
    $hadFile = is_file($path);
    if (!$hadFile) {
        check($placed('Fagus sylvatica') === ['', '', 'Fagus sylvatica'],
            'without the lists on the server everything goes to the free text — a row MykIS takes');
    }
    /* THE ORTSLAGE: the nearest name within a kilometre, from what
       Overpass answered — and nothing for a coarse finder. */
    $parsed = place_names_parse("node\t50.1\t8.6\tAm Hirschberg\tlocality\t\t\n"
        . "way\t50.2\t8.7\tStadtwald\t\t\tforest\n"
        . "node\t50.3\t8.8\tFeldberg\t\tpeak\t\n"
        . "node\t50.4\t8.9\t\tlocality\t\t\n"
        . "relation\t\t\tKaputt\tlocality\t\t\n");
    check(array_column($parsed, 'kind') === ['locality', 'wood', 'peak']
        && $parsed[0]['name'] === 'Am Hirschberg' && $parsed[1]['lat'] === 50.2,
        'Overpass\' lines become field name, wood (its centre) and peak; nameless and placeless lines go');
    $near = [['name' => 'Am Hirschberg', 'lat' => 50.0, 'lon' => 8.0],
             ['name' => 'Im Grund', 'lat' => 50.005, 'lon' => 8.0],
             ['name' => 'Fernwald', 'lat' => 50.02, 'lon' => 8.0]];
    check(place_name_nearest($near, 50.004, 8.0) === 'Im Grund'
        && place_name_nearest($near, 50.0, 8.0) === 'Am Hirschberg'
        && place_name_nearest($near, 50.012, 8.03) === '',
        'the nearest name wins, and none beyond a kilometre');
    place_names_source(null, true);
    check(mykis_place_name(['lat' => 50.0, 'lon' => 8.0], 'exact') === ''
        && $at(mykis_row($sure, 'X', 'exact'), 'BASIS_ortslage') === '',
        'without names in the database the Ortslage stays empty');
    check(mykis_lists_store('{"hosts": [["Fagus sylvatica"]], "organ": ["Laubstreu"]}') === null
        && mykis_lists_store('kein JSON') === null && is_file($path) === $hadFile,
        'a file that is not the lists is refused and nothing written');
    check($at($r, 'beobachtung_bemerkungen') === 'Begleitvegetation: Quercus robur, Carpinus betulus',
        'the plants around go, labelled, to beobachtung_bemerkungen — ' . $at($r, 'beobachtung_bemerkungen'));
    check($at($r, 'BASIS_ortslage') === '' && !str_contains($csv, 'Wetterau'), 'no Ortslage — the tour name stays home');
    check([$at($r, 'geobgrad1'), $at($r, 'geobminute1'), $at($r, 'geobsekunde1')] === ['50', '7', '24,4']
        && [$at($r, 'geolgrad1'), $at($r, 'geolminute1'), $at($r, 'geolsekunde1')] === ['8', '39', '15,6'],
        'the coordinates go along in degrees, minutes and seconds — latitude in geob…, longitude in geol…');
    check($at($r, 'Ungenauigkeit') === '6', 'with the GPS accuracy in metres');
    check(mykis_dms(50 + 7 / 60 + 59.96 / 3600) === ['50', '8', '0,0'],
        'rounding carries into the minute — never „60" seconds');
    /* THE FINDER'S STEP (migrations 0129, 0130): only 'exact' carries
       coordinates and their inaccuracy; every coarser step cuts the MTB and
       leaves both out. A coarse step that let a coordinate slip through
       would hand over exactly what the finder chose to keep. */
    $step = static fn(?string $precision): array => array_combine(MYKIS_COLUMNS, mykis_row($sure, 'X', $precision));
    check(array_map(static fn(string $p): string => $step($p)['    MTB'], array_keys(MYKIS_PRECISIONS))
        === ['5816,3', '5816,31', '5816,311', '5816,311'],
        'each step cuts the MTB to its digits, from the quarter up — the 64th and exact keep all three');
    $located = ['geobgrad1', 'geobminute1', 'geobsekunde1', 'geolgrad1', 'geolminute1', 'geolsekunde1', 'Ungenauigkeit'];
    check(array_filter(array_keys(MYKIS_PRECISIONS), static fn(string $p): bool =>
            $p !== 'exact' && array_filter($located, static fn(string $c): bool => $step($p)[$c] !== '') !== []) === []
        && $step('exact')['geobgrad1'] === '50' && $step('exact')['Ungenauigkeit'] === '6',
        'no step but the exact one carries a coordinate or its inaccuracy');
    check(mykis_precision(null) === 'q4' && mykis_precision('') === 'q4' && $step(null)['    MTB'] === '5816,3'
        && $step(null)['geobgrad1'] === '',
        'a finder who never chose hands over the quarter and no coordinates — nobody gets more than they gave');
    check(mykis_precision('sheet') === 'q4' && mykis_precision('bogus') === 'q4' && mykis_precision('exact') === 'exact',
        'a step that is gone — the sheet alone, from 0129\'s first hour — never falls towards finer');
    check(!str_contains($csv, 'Wegrand') && !str_contains($csv, 'Altkönig'), 'neither the note nor the protected area');
    $t = array_values(array_filter($rows, static fn(array $row): bool => $at($row, 'GATTUNG') === 'Laccaria'))[0] ?? [];
    check($at($t, 'GATTUNG') === 'Laccaria' && $at($t, 'ART') === 'laccata var. pallidifolia', 'a variety stays with the species');
    check($at($t, 'sammler') === '\'=HYPERLINK("x")', 'a value that starts like a formula is defused');
    check($at($t, 'bestimmer') === 'Sebastian Schröder und Volker Maier', 'two people in one field are not turned round');
    check($at($t, 'Qualität') === 'mikroskopisch sicher' && $at($t, 'beleg_nr') === '11234' && $at($t, 'Beleg') === 'ja',
        'certainty as we write it, voucher number and its „ja"');
    /* WITHOUT A VOUCHER NUMBER THE FIND NUMBER GOES (26.09.2026:
       „der Eintrag darin gewinnt immer … ansonsten … die Fundnummer") —
       its own, or the „Y-" mark; „Beleg: ja" stays the field's alone. */
    $numbered = array_combine(MYKIS_COLUMNS, mykis_row(['find_number' => 'wk-00554', 'id' => 3111] + $sure, 'X'));
    check($numbered['beleg_nr'] === 'wk-00554' && $numbered['Beleg'] === '',
        'no voucher number: the find number stands in its place — and claims no voucher');
    $marked = array_combine(MYKIS_COLUMNS, mykis_row(['find_number' => null, 'id' => 3112] + $sure, 'X'));
    check($marked['beleg_nr'] === 'Y-3112', 'without a number of its own the find\'s „Y-" mark goes, as everywhere');
    $both = array_combine(MYKIS_COLUMNS, mykis_row(['find_number' => 'wk-00554', 'id' => 3111,
        'extra' => (object) (['Beleg-Nr.' => 'HB-77'] + (array) $sure['extra'])] + $sure, 'X'));
    check($both['beleg_nr'] === 'HB-77' && $both['Beleg'] === 'ja', 'an entered voucher number always wins');
    $kept = array_combine(MYKIS_COLUMNS, mykis_row(['find_number' => 'wk-00554', 'id' => 3111,
        'extra' => (object) (['Beleg aufbewahrt' => 'ja'] + (array) $sure['extra'])] + $sure, 'X'));
    check($kept['beleg_nr'] === 'wk-00554' && $kept['Beleg'] === 'ja',
        'the tick „Beleg aufbewahrt" says „Beleg: ja" — under the find number, which stands on the voucher');
    check(mykis_name('Urlaub 2019') === null && mykis_name('Ramariopsis cf. luteo-ochracea') === ['Ramariopsis', 'cf. luteo-ochracea'],
        'a place is no name, a „cf." is');
    $file = export_build([$sure], 'mykis', 'YASAIMAPP', ['holder' => 'Schröder, Sebastian']);
    check(str_starts_with($file['name'], 'MykIS-') && str_ends_with($file['name'], '.csv'), 'the file says what it is — ' . $file['name']);

    // The delivery's pure parts: whom a find goes to, and when.
    $coordinators = [
        ['id' => 1, 'region' => 'HES', 'part' => null, 'email' => 'pilze-hessen@dgfm-ev.de'],
        ['id' => 2, 'region' => 'BAW', 'part' => 'Süd', 'email' => 'pilze-bawue@dgfm-ev.de'],
        ['id' => 3, 'region' => 'BAW', 'part' => 'Nord', 'email' => 'pilze-rlp@dgfm-ev.de'],
    ];
    check((mykis_coordinator_for($coordinators, ['region' => 'HES'])['id'] ?? null) === 1,
        'a find in Hessen goes to Hessen');
    check(mykis_coordinator_for($coordinators, ['region' => 'BAW']) === null,
        'a find in a split state waits — the line between north and south is not settled');
    check(mykis_coordinator_for($coordinators, ['region' => 'NRW']) === null, 'no coordinator, no delivery');
    check(mykis_cutoff(new DateTimeImmutable('2026-06-03 04:00')) === '2026-06-01 00:00:00'
        && mykis_cutoff(new DateTimeImmutable('2026-12-01 00:10')) === '2026-12-01 00:00:00',
        'in June and December, what was there on the 1st is due');
    check(mykis_cutoff(new DateTimeImmutable('2026-09-25')) === null
        && mykis_cutoff(new DateTimeImmutable('2027-01-02')) === null, 'in every other month nothing is');
    // The collective mail: one per coordinator address, the finders in shared sheets (26.11.2026).
    $anna = ['first_name' => 'Anna', 'last_name' => 'Musterfrau', 'email' => 'anna@example.org', 'mykis_precision' => 'exact'];
    $finn = ['first_name' => 'Finn', 'last_name' => 'Horn', 'email' => 'finn@example.org', 'mykis_precision' => 'q16'];
    $hessen = [['name' => 'Harald Zühlsdorf', 'region' => 'HES', 'part' => null]];
    $entries = [mykis_finder_entry($anna, [[$sure, 8], [$sure, 8]]), mykis_finder_entry($finn, [[$sure, 8]])];
    $files = mykis_packages($entries, 'Hessen', '2026-12-01');
    check(count($files) === 1 && $files[0]['name'] === 'Funde-Hessen-2026-12-01.csv'
        && substr_count($files[0]['content'], "\n") === 4,
        'every finder in ONE sheet, named after the state — a head and three rows');
    check(array_map(static fn(array $e): string => $e['finder']['last_name'], $files[0]['finders']) === ['Horn', 'Musterfrau'],
        'the finders in the order of their names');
    $mail = mykis_coordinator_mail($hessen, $files, 1, 1,
        ['first_name' => 'Sebastian', 'last_name' => 'Schröder', 'email' => 'sebastian@example.org']);
    check(str_contains($mail['body'], 'Guten Tag Harald Zühlsdorf')
        && str_contains($mail['body'], 'in einer Tabelle mit den Spalten der MykIS-Importvorlage (mykdaten.xls), zum Öffnen in jedem Tabellenprogramm (Funde-Hessen-2026-12-01.csv)')
        && str_contains($mail['body'], 'Musterfrau, Anna <anna@example.org> — 2 Funde — genaue Koordinaten')
        && str_contains($mail['body'], 'Horn, Finn <finn@example.org> — ein Fund — nur Messtischblatt bis zum 16tel'),
        'the collective mail lists every finder with address, count and precision');
    check($mail['subject'] === 'Pilzfunde für MykIS — Hessen (2 Finder, 3 Funde)' && !str_contains($mail['body'], 'Teil 1'),
        'its subject says state, finders and finds — ' . $mail['subject']);
    check(str_contains($mail['body'], 'geht an Sebastian Schröder (sebastian@example.org), zuständig für den Versand'),
        'a reply goes to the operator, and the mail names them with their address');
    check(!str_contains(mykis_coordinator_mail($hessen, $files, 1, 1, null)['body'], 'Eine Antwort'),
        'without an operator the sentence stays out');
    // Cut into sheets of about one size, a finder never split.
    check(mykis_package_groups([3, 3, 3, 3], 5) === [[0, 1], [2, 3]]
        && mykis_package_groups([2, 2, 2], 5) === [[0], [1, 2]]
        && mykis_package_groups([4, 4, 4], 5) === [[0], [1], [2]],
        'sheets about the same size — rather one a little past the mark than a finder split');
    check(mykis_package_groups([10, 1, 1], 5) === [[0], [1, 2]] && mykis_package_groups([], 5) === []
        && mykis_package_groups([7], 5) === [[0]],
        'a finder above the mark goes whole and alone, and makes no sheet more than needed');
    $split = mykis_packages($entries, 'Hessen', '2026-12-01', 2);
    $splitMail = mykis_coordinator_mail($hessen, $split, 1, 1, null)['body'];
    check(count($split) === 2 && $split[1]['name'] === 'Funde-Hessen-2026-12-01-Datei-2.csv'
        && str_contains($splitMail, 'in 2 Tabellen')
        && str_contains($splitMail, 'Funde-Hessen-2026-12-01-Datei-1.csv — Horn, Finn, ein Fund:')
        && str_contains($splitMail, 'Funde-Hessen-2026-12-01-Datei-2.csv — Musterfrau, Anna, 2 Funde:'),
        'several sheets: each under its file name with its finders');
    $partOf = static fn(int $part, int $parts): string => mykis_coordinator_mail($hessen, [$split[1]], $part, $parts, null)['body'];
    $second = mykis_coordinator_mail($hessen, [$split[1]], 2, 2, null);
    check(str_ends_with($second['subject'], '— Teil 2 von 2')
        && str_contains($second['body'], 'Dies ist Teil 2 von 2, der letzte; die übrigen Tabellen kamen in der vorigen Mail.')
        && !str_contains($second['body'], 'folgen'),
        'a split mail says which part it is — and the last one announces nothing more');
    check(str_contains($partOf(1, 3), 'Teil 1 von 3; die übrigen Tabellen folgen in weiteren Mails.')
        && str_contains($partOf(2, 3), 'Teil 2 von 3; die übrigen Tabellen folgen in einer weiteren Mail.')
        && str_contains($partOf(3, 3), 'Teil 3 von 3, der letzte; die übrigen Tabellen kamen in den vorigen Mails.'),
        'and counts what is still to come');
    check(mykis_file_name('Sebastian Schröder', '2026-12-01') === 'Funde-Sebastian-Schroeder-2026-12-01.csv'
        && mykis_file_name('Thüringen', '2026-12-01', 3) === 'Funde-Thueringen-2026-12-01-Datei-3.csv',
        'an umlaut is written out in the file name, and a sheet of several carries its number');
    $sizes = static fn(array $parts): array => array_map(static fn(array $p): array =>
        array_map(static fn(array $f): int => strlen($f['content']), $p), $parts);
    $blob = static fn(int $n): array => ['content' => str_repeat('x', $n)];
    check($sizes(mykis_parts([$blob(4), $blob(4), $blob(4)], 10)) === [[4, 4], [4]]
        && $sizes(mykis_parts([$blob(12), $blob(3)], 10)) === [[12], [3]],
        'the sheets split into mails at a sheet\'s border; one too large goes alone');
    check(array_map('count', mykis_parts(array_fill(0, 20, $blob(1)), MYKIS_MAIL_MAX_BYTES, MYKIS_FILES_PER_MAIL)) === [15, 5],
        'twenty sheets are two mails: fifteen, then five');
    $confirmation = mykis_confirmation_mail($anna, [
        ['state' => 'Hessen', 'name' => 'Harald Zühlsdorf', 'email' => 'pilze-hessen@dgfm-ev.de', 'count' => 2],
        ['state' => 'Bayern', 'name' => 'Peter Karasch', 'email' => 'pilze-bayern@dgfm-ev.de', 'count' => 1],
    ]);
    check(str_contains($confirmation['body'], 'Hallo Anna,')
        && str_contains($confirmation['body'], 'Hessen (Harald Zühlsdorf, pilze-hessen@dgfm-ev.de): 2 Funde')
        && str_contains($confirmation['body'], 'Bayern (Peter Karasch, pilze-bayern@dgfm-ev.de): ein Fund'),
        'the finder\'s confirmation says what went where');
    check(str_contains($confirmation['body'], 'Einstellungen → DGfM-Kartierung'),
        'and where the copy is switched off — the tab by its full name');
    check(!mykis_wants_copy([]) && !mykis_wants_copy(['mykis_copy' => 0]) && !mykis_wants_copy(['mykis_copy' => '0'])
        && mykis_wants_copy(['mykis_copy' => 1]) && mykis_wants_copy(['mykis_copy' => '1']),
        'a copy only for whoever ticked it (0131) — none without the column');
}

echo "\nAufsammlungen — what grew in a culture says so\n";
{
    /* A SPECIES FROM A CULTURE (0174): its find date is the collecting day,
       so the files have to say that it did not fruit there and then. The
       sample itself is no record of a species and never goes to MykIS. */
    $box = ['id' => 50, 'title' => 'Aufsammlung A-2026-01', 'is_collection' => true, 'collection_id' => null,
            'collection_number' => 'A-2026-01', 'cultured_at' => '2026-09-04', 'culture_ended_at' => '2026-10-10',
            'identified_at' => null, 'find_number' => null, 'extra_species' => null] + $sure;
    $grown = ['id' => 51, 'collection_id' => 50, 'collection_parent_number' => 'A-2026-01',
              'identified_at' => '2026-09-14', 'found_at' => '2026-09-03 10:00:00'] + $sure;
    check(collection_note($grown) === 'ex cultura (Aufsammlung A-2026-01), bestimmt am 14.09.2026, 11 Tage nach dem Sammeln',
        'the species: ex cultura, its box, the day it was determined and how long after — ' . collection_note($grown));
    check(collection_note(['identified_at' => '2026-09-04'] + $grown) === 'ex cultura (Aufsammlung A-2026-01), bestimmt am 04.09.2026, 1 Tag nach dem Sammeln',
        'one day is „1 Tag"');
    check(collection_note(['collection_parent_number' => null, 'identified_at' => null] + $grown) === 'ex cultura',
        'without a box number or a day it still says ex cultura');
    check(collection_note($box) === 'Aufsammlung A-2026-01, in Kultur seit 04.09.2026, Kultur beendet 10.10.2026',
        'the sample: its number and the culture\'s days');
    check(collection_note($sure) === '', 'a find from the field says nothing of the sort');

    check(!mykis_eligible($box), 'the sample never goes to MykIS');
    check(mykis_eligible($grown), 'what grew from it goes, a find like any other');
    $row = array_combine(MYKIS_COLUMNS, mykis_row(['collection_parent_number' => null] + $grown, 'X'));
    check($row['art_bemerkung'] === 'ex cultura, bestimmt am 14.09.2026, 11 Tage nach dem Sammeln'
        && $row['BASIS_datum1'] === '03.09.2026',
        'in MykIS: „ex cultura" in art_bemerkung, the collecting day as the find date');
    check(array_combine(MYKIS_COLUMNS, mykis_row($sure, 'X'))['art_bemerkung'] === '',
        'a field find leaves art_bemerkung empty, as before');

    $read = throughFile(export_csv([$box, $grown, $sure]), 'csv', static fn($p) => csv_read($p));
    $col = static fn(string $name) => array_search($name, $read['columns'], true);
    check($col('Aufsammlung') !== false && $col('ex cultura') !== false && $col('Bestimmt am') !== false,
        'the CSV gets the culture\'s columns when it holds one');
    check($read['rows'][0][$col('Aufsammlung')] === 'A-2026-01' && $read['rows'][0][$col('In Kultur seit')] === '2026-09-04'
        && $read['rows'][1][$col('Aufsammlung')] === 'A-2026-01' && $read['rows'][1][$col('ex cultura')] === 'ja'
        && $read['rows'][1][$col('Bestimmt am')] === '2026-09-14' && $read['rows'][2][$col('ex cultura')] === '',
        'the box number on both, „ja" and the day on the species, nothing on the field find');
    $plain = throughFile(export_csv([$sure]), 'csv', static fn($p) => csv_read($p));
    check(!in_array('Aufsammlung', $plain['columns'], true), 'and none of them where nobody cultured anything');
    check(str_ends_with(export_note($grown), 'ex cultura (Aufsammlung A-2026-01), bestimmt am 14.09.2026, 11 Tage nach dem Sammeln'),
        'KML, GPX and GeoJSON carry it in the note');
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
