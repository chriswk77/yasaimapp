<?php
/**
 * Tests the municipality lookup: abbreviation tables, name chain, folder path.
 *
 *   php tests/municipality.php          (without network, the usual)
 *   php tests/municipality.php --network   (really asks Nominatim)
 *
 * WITHOUT NETWORK AS THE DEFAULT. A test that hangs on a foreign service
 * fails at some point without anything being broken here — and then
 * nobody believes it any more. The service's answers therefore come along
 * as recorded versions; what is tested is what WE make of them.
 *
 * THE RECORDINGS ARE REAL. They come from ten actual queries and pin down
 * the two traps that showed up in them:
 *
 *   THE FIELD CHANGES WITH THE SIZE OF THE PLACE. Mittenwald comes as
 *   'town', Krün three kilometres away as 'village', Innsbruck as 'city'.
 *   Whoever reads a single field loses half the mapping — namely the
 *   rural half, so exactly the one this is about.
 *
 *   'municipality' IS A DISTRICT, NOT A MUNICIPALITY. For the Ticino
 *   point it says "Kreis Bellinzona". The field looks like the answer and
 *   is not; it must not go into the chain.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/municipality.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "\nMunicipality, subdivision, country\n";

// ---------------------------------------------------------------------------
echo "\nThe abbreviation tables are complete and unambiguous\n";

$expected = [
    'DE' => 16, 'AT' =>  9, 'CH' => 26, 'IT' => 20, 'ES' => 19,
    'NL' => 12, 'PL' => 16, 'CZ' => 14, 'SK' =>  8, 'HR' => 21,
    'DK' =>  5, 'SE' => 21, 'NO' => 15,
];

check(count(MUNICIPALITY_PARTS) === count($expected),
    'there are exactly ' . count($expected) . ' country tables — no untested one in between');

foreach ($expected as $country => $count) {
    $tab = MUNICIPALITY_PARTS[$country] ?? null;
    if ($tab === null) {
        check(false, "$country is missing entirely");
        continue;
    }
    check(count($tab) === $count, "$country has $count subdivisions");
    // THIS IS THE MOST IMPORTANT TEST OF THE WHOLE FILE. Two regions with
    // the same abbreviation throw their finds into the same folder, and
    // silently at that — on the disk you only see that there are more
    // files now.
    check(count(array_unique($tab)) === count($tab),
        "$country — no abbreviation duplicated"
        . (count(array_unique($tab)) === count($tab) ? ''
           : ' — duplicated: ' . implode(', ', array_keys(array_diff_key(
               $tab, array_unique($tab))))));
    check(count(array_filter($tab, static fn($k) => (bool) preg_match('/^[A-Z]{3}$/', $k))) === count($tab),
        "$country — exactly three capital letters everywhere");
    check(count(array_filter(array_keys($tab),
        static fn($k) => (bool) preg_match('/^[A-Z0-9]{1,3}$/', (string) $k))) === count($tab),
        "$country — the keys look like ISO remainders");
}

// The fine level: its keys must point to a coarse code that also exists.
// Otherwise a special case would hang on a code that never comes.
foreach (MUNICIPALITY_PARTS_FINE as $coarse => $fine) {
    [$country, $rest] = explode('-', $coarse, 2);
    check(isset(MUNICIPALITY_PARTS[$country][$rest]), "$coarse also exists in the coarse table");
    check(count(array_unique($fine)) === count($fine), "$coarse — no fine abbreviation duplicated");
    check(count(array_filter($fine, static fn($k) => (bool) preg_match('/^[A-Z]{3}$/', $k))) === count($fine),
        "$coarse — three capital letters at the fine level too");
}

// Berlin/Bremen and the three S-A states are the pairs on which a naive
// truncation would fail. They stand here by name so that nobody "unifies"
// them later.
check(MUNICIPALITY_PARTS['DE']['BE'] === 'BLN' && MUNICIPALITY_PARTS['DE']['HB'] === 'BRM',
    'Berlin is BLN and Bremen BRM — not BER and BRE');
check(MUNICIPALITY_PARTS['DE']['SL'] === 'SAL' && MUNICIPALITY_PARTS['DE']['SN'] === 'SAC'
      && MUNICIPALITY_PARTS['DE']['ST'] === 'SAH',
    'Saarland, Sachsen and Sachsen-Anhalt are distinguishable');
check(MUNICIPALITY_PARTS['CH']['BS'] === 'BST' && MUNICIPALITY_PARTS['CH']['BL'] === 'BSL',
    'Basel-Stadt and Basel-Landschaft mirror BS and BL');

// ---------------------------------------------------------------------------
echo "\nThe ISO code becomes the abbreviation\n";

foreach ([
    'DE-BY' => 'BAY',
    'DE-NW' => 'NRW',
    'AT-7'  => 'TIR',   // Austria delivers digits, hence the table
    'AT-5'  => 'SBG',
    'CH-VS' => 'VAL',
    'CH-TI' => 'TIC',
    'de-by' => 'BAY',   // lower case must not miss
] as $iso => $want) {
    check(municipality_subdivision($iso) === $want, "$iso → $want");
}

foreach ([
    'IT-32' => 'TAA',   // Italy: digits as in Austria
    'IT-82' => 'SIC',
    'ES-AR' => 'ARA',   // Spain: two letters
    'PL-12' => 'MAL',   // Poland: digits with a leading zero
    'PL-02' => 'DOL',
    'HR-09' => 'LIK',
    'CZ-52' => 'HRA',
    'SK-PV' => 'PRE',
    'NL-UT' => 'UTR',
    'DK-82' => 'MJY',
    'NO-33' => 'BUS',
    'SE-AB' => 'STO',
    'SE-C'  => 'UPP',   // Sweden also has single-character codes
] as $iso => $want) {
    check(municipality_subdivision($iso) === $want, "$iso → $want");
}

// THESE THREE NEED NO TABLE, because their ISO code already has three
// letters. They stand here so that nobody later adds a table "for the
// sake of completeness" and thereby creates a second version of the same
// statement.
foreach (['FR-ARA' => 'ARA', 'BE-WAL' => 'WAL', 'GB-ENG' => 'ENG'] as $iso => $want) {
    check(municipality_subdivision($iso) === $want, "$iso → $want (without a table)");
    check(!isset(MUNICIPALITY_PARTS[explode('-', $iso)[0]]),
        explode('-', $iso)[0] . ' deliberately has no table');
}

// The finer level where the region is too coarse: Bozen and Trient both
// lie in IT-32 but are two autonomous provinces.
check(municipality_subdivision('IT-32', 'IT-BZ') === 'SDT', 'IT-32 + IT-BZ → SDT (Südtirol)');
check(municipality_subdivision('IT-32', 'IT-TN') === 'TRN', 'IT-32 + IT-TN → TRN (Trentino)');
check(municipality_subdivision('IT-34', 'IT-VE') === 'VEN',
    'elsewhere the fine level stays without effect — Venice is Veneto, not "VE"');
check(municipality_subdivision('IT-32', 'IT-XX') === 'TAA',
    'an unknown fine code falls back to the region');

// Everything without a table keeps the ISO remainder.
check(municipality_subdivision('PT-11') === '11', 'PT-11 → 11 (no table, the remainder stays)');

foreach (['', 'DE', 'Unsinn', null] as $junk) {
    check(municipality_subdivision($junk) === null,
        var_export($junk, true) . ' — yields null');
}

// ---------------------------------------------------------------------------
echo "\nThe name comes from the right corner of the answer\n";

// Recorded answers, cut down to the fields we read.
$answers = [
    'Mittenwald (town)' => [
        ['addresstype' => 'town', 'name' => 'Mittenwald',
         'address' => ['town' => 'Mittenwald', 'ISO3166-2-lvl4' => 'DE-BY', 'country_code' => 'de']],
        'Mittenwald',
    ],
    'Krün (village)' => [
        ['addresstype' => 'village', 'name' => 'Krün',
         'address' => ['village' => 'Krün', 'ISO3166-2-lvl4' => 'DE-BY', 'country_code' => 'de']],
        'Krün',
    ],
    'Innsbruck (city)' => [
        ['addresstype' => 'city', 'name' => 'Innsbruck',
         'address' => ['city' => 'Innsbruck', 'ISO3166-2-lvl4' => 'AT-7', 'country_code' => 'at']],
        'Innsbruck',
    ],
    'Bellinzona — municipality is the district' => [
        ['addresstype' => 'town', 'name' => 'Bellinzona',
         'address' => ['town' => 'Bellinzona', 'municipality' => 'Kreis Bellinzona',
                       'ISO3166-2-lvl4' => 'CH-TI', 'country_code' => 'ch']],
        'Bellinzona',
    ],
    'Biel — in German without the slash' => [
        ['addresstype' => 'city', 'name' => 'Biel',
         'address' => ['city' => 'Biel', 'ISO3166-2-lvl4' => 'CH-BE', 'country_code' => 'ch']],
        'Biel',
    ],
];

foreach ($answers as $what => [$answer, $want]) {
    check(municipality_name($answer) === $want, "$what → $want");
}

// The catch-all: if the top carries a type we do not accept as a
// municipality, the address chain is read — and 'municipality' not even
// there.
check(municipality_name([
    'addresstype' => 'county', 'name' => 'Landkreis Irgendwo',
    'address' => ['municipality' => 'Kreis Falsch', 'village' => 'Richtig'],
]) === 'Richtig', 'with a foreign type village wins, not municipality');

// ---------------------------------------------------------------------------
echo "\nThree pieces become a path\n";

check(municipality_folder('DE', 'BAY', 'Mittenwald') === 'DE/BAY/Mittenwald',
    'DE/BAY/Mittenwald');
check(municipality_folder('CH', 'VAL', 'Zermatt') === 'CH/VAL/Zermatt',
    'CH/VAL/Zermatt');
// Without a region the level drops out. "IT//Bozen" would be a path with
// a hole, and that breaks every tool that splits it.
check(municipality_folder('IT', null, 'Bozen') === 'IT/Bozen',
    'without a subdivision no empty level remains');
check(municipality_folder('DE', 'BAY', null) === null, 'without a municipality there is no path');
check(municipality_folder(null, null, 'Mittenwald') === null, 'without a country neither');

// ---------------------------------------------------------------------------
echo "\nThe name survives as a folder name\n";

check(municipality_folder_part('Biel/Bienne') === 'Biel-Bienne',
    'the slash becomes a hyphen, not a level');
check(municipality_folder_part('Sankt Wolfgang im Salzkammergut') === 'Sankt Wolfgang im Salzkammergut',
    'spaces stay');
check(municipality_folder_part('Krün') === 'Krün',
    'umlauts stay — mangling them would destroy the name');
check(municipality_folder_part('.versteckt.') === 'versteckt',
    'leading and trailing dots fall away');
check(municipality_folder_part("Zei\x00le") === 'Zeile', 'control characters fall away');

// ---------------------------------------------------------------------------
echo "\nWhat the abbreviation table emits it also accepts as valid\n";

/*
 * THE LOOP THAT EXISTED ONCE BEFORE. municipality_outdated() collects points
 * whose abbreviation does not occur in their country's table and has them
 * re-queried. If an abbreviation only sits in the FINE table — "TRN" for
 * the Trentino —, the coarse one lacks it, the point counts as outdated
 * forever and is asked outside anew on every run. Noticed on real data,
 * not by reading. This test catches it without a database.
 */
// Every abbreviation municipality_subdivision() can ever emit for a country,
// municipality_codes() must also know as valid. Otherwise the reset collects
// exactly the points that are already right.
foreach (MUNICIPALITY_PARTS as $country => $table) {
    $valid = municipality_codes($country);

    foreach ($table as $rest => $abbr) {
        check(in_array(municipality_subdivision("$country-$rest"), $valid, true),
            "$country-$rest yields an abbreviation that $country also knows");
    }
}

foreach (MUNICIPALITY_PARTS_FINE as $coarse => $fine) {
    $country = explode('-', $coarse, 2)[0];
    $valid   = municipality_codes($country);

    foreach (array_keys($fine) as $fineCode) {
        $result = municipality_subdivision($coarse, $fineCode);
        check(in_array($result, $valid, true),
            "$coarse + $fineCode yields $result — and $country knows it too");
    }
}

// And the other way round: a country without a table may report nothing
// as valid, otherwise the reset would consider something done there that
// it does not check.
check(municipality_codes('FR') === [], 'FR has no abbreviations — and needs none');

// ---------------------------------------------------------------------------
if (in_array('--network', $argv, true)) {
    echo "\nAnd now really asked (one request per second)\n";

    foreach ([
        [47.4419, 11.2622, 'DE', 'BAY', 'Mittenwald'],
        [47.5100, 11.2900, 'DE', 'BAY', 'Krün'],
        [46.0207,  7.7491, 'CH', 'VAL', 'Zermatt'],
        [47.2692, 11.4041, 'AT', 'TIR', 'Innsbruck'],
        // The fine level, at the real border: Bozen and Trient both lie
        // in IT-32 and must still come apart.
        [46.4983, 11.3548, 'IT', 'SDT', 'Bozen'],
        [46.0679, 11.1211, 'IT', 'TRN', 'Trient'],
        // And one point from each group: table with digits, table with
        // letters, and a country entirely without a table.
        [49.2990, 19.9496, 'PL', 'MAL', 'Zakopane'],
        [42.6300, -0.1100, 'ES', 'ARA', 'Torla-Ordesa'],
        [45.9237,  6.8694, 'FR', 'ARA', 'Chamonix-Mont-Blanc'],
    ] as [$lat, $lon, $country, $part, $place]) {
        try {
            $t = municipality_get($lat, $lon);
            check($t['country'] === $country && $t['region'] === $part && $t['municipality'] === $place,
                sprintf('%.4f/%.4f → %s/%s/%s (got %s/%s/%s)', $lat, $lon, $country, $part, $place,
                    $t['country'], (string) $t['region'], $t['municipality']));
        } catch (Throwable $e) {
            check(false, sprintf('%.4f/%.4f — %s', $lat, $lon, $e->getMessage()));
        }
        sleep(1);
    }
} else {
    echo "\n  (network query skipped — with --network it really asks)\n";
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
