<?php
/**
 * German names for the lichens, from GBIF. Command line only.
 *
 *   php admin/lichens_german.php [--cache=<answers.json>] [--write=<migration.sql>]
 *
 * WHY (25.09.2026: „trag die deutschen Namen aus GBIF nach"):
 * the BfN's lichen list — our only list for lichens, the DGfM's holds
 * lichenicolous fungi under „Lichenes" but hardly a lichen — carries no
 * German names at all (data/taxonomie/LIESMICH.md). GBIF gathers the
 * vernacular names of many national checklists, and for a part of the
 * species one of them is German.
 *
 * WHAT IT DOES: every valid row of map_tax_lichens (taxnr = sipnr) is
 * matched against the GBIF backbone — exactly, kingdom Fungi —, and the
 * German names of the accepted taxon are gathered. One of them is chosen
 * as the main name (see lichens_german_choose) and written first, the
 * others after it, comma-separated like the DGfM list's — as an UPDATE of
 * that row: the names and the search text, which the import builds from
 * both (bfn_write). Synonym rows keep none — the names are the species'.
 *
 * WHY A MIGRATION AND NOT THE TAXONOMY TRANSFER: the server's import
 * (Administration → Taxonomie einspielen) fills a table up to its row
 * count — the same rows with new names would count as done, and getting
 * them there would mean emptying the table and uploading its file by
 * hand. A few hundred UPDATEs travel as a migration and are applied like
 * any other. The package revision of 'lichens' (species_packages.php)
 * tells the devices to fetch the names.
 *
 * --cache reads GBIF's answers from a file instead of asking again (and
 * writes what it asked into it) — a full run asks some 4,800 times and
 * takes a quarter of an hour, politely one question after the other.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

$options = getopt('', ['cache:', 'write:', 'json:']);
$cacheFile = $options['cache'] ?? null;
$writeFile = $options['write'] ?? null;

/** One GBIF call, three tries — the API answers slowly now and then. */
function lichens_gbif(string $url): ?array
{
    $context = stream_context_create(['http' => [
        'header'  => "User-Agent: YASAIMAPP lichen names (admin/lichens_german.php)\r\n",
        'timeout' => 40,
    ]]);
    for ($i = 0; $i < 3; $i++) {
        $body = @file_get_contents($url, false, $context);
        if ($body !== false) {
            $data = json_decode($body, true);
            return is_array($data) ? $data : null;
        }
        sleep(1 + $i);
    }
    return null;
}

/** GBIF's answer for one name, in the shape the cache keeps. */
function lichens_gbif_lookup(string $binomial): array
{
    $m = lichens_gbif('https://api.gbif.org/v1/species/match?'
        . http_build_query(['name' => $binomial, 'kingdom' => 'Fungi', 'strict' => 'true']));
    if ($m === null) {
        return ['error' => 'match'];
    }
    $rec = ['matchType' => $m['matchType'] ?? null, 'status' => $m['status'] ?? null,
            'usageKey' => $m['usageKey'] ?? null, 'acceptedUsageKey' => $m['acceptedUsageKey'] ?? null];
    $key = $m['acceptedUsageKey'] ?? $m['usageKey'] ?? null;
    if ($key !== null && ($m['matchType'] ?? '') === 'EXACT') {
        $v = lichens_gbif('https://api.gbif.org/v1/species/' . (int) $key . '/vernacularNames?limit=500');
        if ($v === null) {
            $rec['error'] = 'vernacular';
        } else {
            $rec['german'] = [];
            foreach ($v['results'] ?? [] as $r) {
                if (in_array($r['language'] ?? '', ['deu', 'ger'], true)) {
                    $rec['german'][] = ['name' => $r['vernacularName'] ?? '', 'source' => $r['source'] ?? null,
                                        'country' => $r['country'] ?? null, 'preferred' => $r['preferred'] ?? null];
                }
            }
        }
    }
    usleep(150000);
    return $rec;
}

/** A name as one line, trimmed. */
function lichens_norm(string $name): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $name));
}

/** The German genus word of a name: its last word, or what follows the last hyphen. */
function lichens_genus_word(string $name): string
{
    $parts = preg_split('/[\s-]+/u', mb_strtolower(lichens_norm($name))) ?: [];
    return (string) end($parts);
}

/** Genus and epithet alone — subspecies and varieties count as their species. */
function lichens_species_of(string $binomial): string
{
    return implode(' ', array_slice(explode(' ', $binomial), 0, 2));
}

/**
 * THE CHOICE, when GBIF knows several German names for one species — 18
 * of 246, nearly all from the Catalogue of Life, so counting sources
 * decides nothing. In this order (25.09.2026, measured on the answers):
 *
 *   1. a genus word of THIS genus over one several genera share —
 *      „Gelbe Fuchsflechte" for Vulpicida, not „Gelbe Blattflechte":
 *      „Blattflechte" is a kind of lichen, not a genus;
 *   2. the genus word the sister species wear — „Sternchen-
 *      Schwarzpunktflechte" beside „Pionier-Schwarzpunktflechte", not
 *      „Sternchen-Buellie"; a list reads as one when a genus has one
 *      German name — and among the species' own names the genus word
 *      they share most („Jeckers Punktflechte" beside „Krausblättrige
 *      Punktflechte", not „Großlappige Blattflechte");
 *   3. „Gewöhnliche", „Gemeine", „Echte" first — the German way of naming
 *      the type or the common one of a genus („Gewöhnliche Blasenflechte"
 *      over „Blattförmige …");
 *   4. a lichen's name that ends in „flechte" over a folk word
 *      („Pflaumenflechte" over „Eichenmoos");
 *   5. the more specific name („Echte Hundsflechte" over „Hundsflechte");
 *   6. the alphabet, so a run repeats itself.
 *
 * The maintainer's own picks stand in LICHENS_GERMAN_CHOSEN and beat all of it.
 *
 * @param array<string, int> $genusWords  how many species of this genus wear each genus word
 * @param array<string, int> $spread      in how many genera each genus word stands
 */
function lichens_german_choose(array $names, array $genusWords, array $spread, bool $lichen): ?string
{
    if ($names === []) {
        return null;
    }
    $own = array_count_values(array_map('lichens_genus_word', $names));
    $rank = static function (string $n) use ($genusWords, $spread, $own, $lichen): array {
        $w = lichens_genus_word($n);
        return [
            ($spread[$w] ?? 1) === 1 ? 1 : 0,
            $genusWords[$w] ?? 0,
            $own[$w] ?? 0,
            (int) preg_match('/^(Gewöhnliche|Gewöhnlicher|Gemeine|Gemeiner|Echte|Echter)\b/u', $n),
            $lichen ? (int) preg_match('/flechte$/u', mb_strtolower($n)) : 0,
            count(preg_split('/[\s-]+/u', $n) ?: []),
        ];
    };
    usort($names, static fn(string $a, string $b): int => ($rank($b) <=> $rank($a)) ?: strcmp($a, $b));
    return $names[0];
}

/**
 * The maintainer's picks where the rule above is not enough (25.09.2026, from
 * the preview): the alphabet chose „Pokal-" and „Blatt-", and the source
 * misspells „Pfrimien-Säulenflechte". The other names stay, after these.
 */
const LICHENS_GERMAN_CHOSEN = [
    'Cladonia fimbriata' => 'Trompeten-Becherflechte',
    'Cladonia subulata'  => 'Pfriemengeweihflechte',
    'Ramalina fraxinea'  => 'Eschen-Astflechte',
];

$rows = $pdo->query('SELECT taxnr, binomial, group_name FROM map_tax_lichens WHERE taxnr = sipnr ORDER BY binomial')->fetchAll();
$cache = $cacheFile !== null && is_readable($cacheFile) ? (json_decode((string) file_get_contents($cacheFile), true) ?: []) : [];

$asked = 0;
foreach ($rows as $i => $r) {
    if (!isset($cache[$r['binomial']]) || isset($cache[$r['binomial']]['error'])) {
        $cache[$r['binomial']] = lichens_gbif_lookup($r['binomial']);
        $asked++;
        if ($cacheFile !== null && $asked % 100 === 0) {
            file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE));
            fprintf(STDERR, "  %d/%d gefragt\n", $i + 1, count($rows));
        }
    }
}
if ($cacheFile !== null && $asked > 0) {
    file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE));
}

/* THE NAMES OF EACH ROW, and which of them count. GBIF answers a synonym
   with its accepted taxon — and where our list keeps both as species of
   their own (Bilimbia accedens beside sabuletorum, nine such pairs), the
   accepted one's name would stand twice. So a row matched as a SYNONYM
   takes the names only when the accepted taxon is not in our list itself;
   Caloplaca decipiens, a synonym at GBIF with nothing of the kind beside
   it, keeps „Trügerischer Schönfleck". */
$acceptedHere = [];
foreach ($rows as $r) {
    $rec = $cache[$r['binomial']] ?? [];
    if (($rec['status'] ?? '') === 'ACCEPTED' && isset($rec['usageKey'])) {
        $acceptedHere[(int) $rec['usageKey']] = $r['binomial'];
    }
}
$names = [];
foreach ($rows as $r) {
    $rec = $cache[$r['binomial']] ?? [];
    $via = $rec['acceptedUsageKey'] ?? null;
    if ($via !== null && isset($acceptedHere[(int) $via]) && $acceptedHere[(int) $via] !== $r['binomial']) {
        continue;
    }
    foreach ($rec['german'] ?? [] as $c) {
        $n = lichens_norm((string) $c['name']);
        if ($n !== '' && !in_array(mb_strtolower($n), array_map('mb_strtolower', $names[$r['binomial']] ?? []), true)) {
            $names[$r['binomial']][] = $n;
        }
    }
}

/* A WORD FOR A KIND, NOT A NAME: given to three or more SPECIES —
   subspecies count as their species, so Cladonia cervicornis keeps its
   „Hirschgeweihbecherflechte" on all three — a name drops out. */
$species = [];
foreach ($names as $binomial => $list) {
    foreach ($list as $n) {
        $species[mb_strtolower($n)][lichens_species_of($binomial)] = true;
    }
}
foreach ($names as $binomial => $list) {
    $names[$binomial] = array_values(array_filter($list, static fn(string $n): bool => count($species[mb_strtolower($n)]) < 3));
}

// Which genus words the species of each genus wear, counted per species —
// and in how many genera each word stands at all.
$genusWords = [];
$wordGenera = [];
foreach ($names as $binomial => $list) {
    $genus = explode(' ', $binomial)[0];
    foreach (array_unique(array_map('lichens_genus_word', $list)) as $w) {
        $genusWords[$genus][$w][lichens_species_of($binomial)] = true;
        $wordGenera[$w][$genus] = true;
    }
}
$wordSpread = array_map('count', $wordGenera);

$chosen = [];
$stats = ['rows' => count($rows), 'exact' => 0, 'with_german' => 0, 'chosen' => 0, 'errors' => 0];
foreach ($rows as $r) {
    $rec = $cache[$r['binomial']] ?? [];
    if (isset($rec['error'])) {
        $stats['errors']++;
    }
    if (($rec['matchType'] ?? '') === 'EXACT') {
        $stats['exact']++;
    }
    if (($rec['german'] ?? []) !== []) {
        $stats['with_german']++;
    }
    $list = $names[$r['binomial']] ?? [];
    $genus = explode(' ', $r['binomial'])[0];
    $counts = array_map('count', $genusWords[$genus] ?? []);
    $name = LICHENS_GERMAN_CHOSEN[$r['binomial']]
        ?? lichens_german_choose($list, $counts, $wordSpread, $r['group_name'] === 'Flechte');
    if ($name !== null) {
        /* ALL THE NAMES, THE CHOSEN ONE FIRST (25.09.2026:
           „können wir nicht alle deutschen Namen nehmen? In der TaxRef
           haben wir ja auch mehrere") — the DGfM list's own form: one
           field, comma-separated, the main name leading („Pfifferling,
           Eierschwamm"). The rest in the alphabet's order. */
        $rest = array_values(array_filter($list, static fn(string $n): bool => $n !== $name));
        sort($rest);
        $chosen[] = ['taxnr' => (int) $r['taxnr'], 'binomial' => $r['binomial'], 'main' => $name,
                     'german' => implode(', ', array_merge([$name], $rest)),
                     'group' => $r['group_name'], 'all' => $list];
        $stats['chosen']++;
    }
}

printf("  %d gültige Namen, %d von GBIF genau erkannt, %d mit deutschem Namen, %d gewählt, %d Fehler\n",
    $stats['rows'], $stats['exact'], $stats['with_german'], $stats['chosen'], $stats['errors']);

// For a look before anything is written — every choice with its alternatives.
if (isset($options['json'])) {
    file_put_contents($options['json'], json_encode($chosen, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

if ($writeFile === null) {
    echo "\nBeispiele (gewählt ← alle):\n";
    foreach (array_slice($chosen, 0, 25) as $c) {
        printf("  %-34s %-28s ← %s\n", $c['binomial'], $c['german'], implode(' · ', $c['all']));
    }
    echo "\nMit --write=<datei.sql> als Migration schreiben.\n";
    exit(0);
}

$quote = static fn(string $s): string => "'" . str_replace(["\\", "'"], ["\\\\", "''"], $s) . "'";
$sql = [];
foreach ($chosen as $c) {
    $sql[] = sprintf('UPDATE map_tax_lichens SET german_name = %s, search_text = %s WHERE taxnr = %d AND binomial = %s;',
        $quote($c['german']), $quote(str_replace('-', ' ', trim($c['binomial'] . ' ' . $c['german']))),
        $c['taxnr'], $quote($c['binomial']));
}
file_put_contents($writeFile, implode("\n", [
    '-- German names for the lichens, from GBIF (25.09.2026: „trag die',
    '-- deutschen Namen aus GBIF nach" — and „können wir nicht alle deutschen',
    '-- Namen nehmen? In der TaxRef haben wir ja auch mehrere").',
    '--',
    '-- GENERATED by admin/lichens_german.php, whose head says how the names were',
    '-- chosen. ' . count($sql) . ' species of the BfN list get their German names: the main',
    '-- name first, the others after it, comma-separated like the DGfM list\'s.',
    '-- UPDATEs and not the taxonomy transfer: the server\'s import fills a table',
    '-- up to its row count and would count these rows as done. Revision 1 of the',
    "-- 'lichens' package (species_packages.php) makes the devices fetch them.",
]) . "\n" . implode("\n", $sql) . "\n");
printf("  %d Zeilen nach %s geschrieben\n", count($sql), $writeFile);
