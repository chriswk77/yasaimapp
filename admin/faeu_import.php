<?php
/**
 * Import Fauna Europaea. Command line only.
 *
 *   php admin/faeu_import.php [<fauna_europaea.tar.gz>] [--really]
 *
 * Without a file it fetches one — 7.8 MB at a fixed address:
 *
 *   https://hosted-datasets.gbif.org/datasets/fauna_europaea.tar.gz
 *
 * SOURCE AND LICENCE: Fauna Europaea (de Jong Y, 2016, Fauna Europaea
 * Consortium), DOI 10.15468/ymk1bx, CC-BY-SA 4.0. The licence is NOT in
 * GBIF's metadata — that field is empty — but in the archive's own eml.xml.
 * Two secondary sources claimed CC-BY; the primary one says ShareAlike, and
 * that is the one that counts.
 *
 * THE AUTHOR IS WHAT IS LEFT AFTER THE NAME PARTS, and getting there needs
 * more care than it looks. The archive carries genus, subgenus,
 * specificEpithet and infraspecificEpithet as their own columns — but
 * scientificName puts the subgenus in between, in brackets:
 *
 *   Zygaena (Zygaena) osterodensis Reiss, 1921
 *
 * Cutting off as many characters as the binomial "Zygaena osterodensis" is
 * long therefore lands in the middle of a word — the first attempt produced
 * authors like "terodensis Reiss, 1921". So the known parts are walked off
 * the front token by token, and whatever no longer matches is the author.
 *
 * SUBSPECIES COME ALONG. 25 912 of them, and in entomology they are what
 * one actually records; the Germany lists carry theirs too.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

const FAEU_SOURCE = 'https://hosted-datasets.gbif.org/datasets/fauna_europaea.tar.gz';

$file   = null;
$really = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--really') {
        $really = true;
    } else {
        $file = $arg;
    }
}

if ($file === null) {
    echo "Hole das Archiv …\n";
    $file = tempnam(sys_get_temp_dir(), 'faeu') . '.tar.gz';
    $bytes = @file_get_contents(FAEU_SOURCE, false, stream_context_create([
        'http' => ['timeout' => 300, 'ignore_errors' => true],
    ]));
    if ($bytes === false || strlen($bytes) < 1000000) {
        fwrite(STDERR, "Das Archiv ließ sich nicht holen. Von Hand laden und übergeben:\n  "
            . FAEU_SOURCE . "\n");
        exit(1);
    }
    file_put_contents($file, $bytes);
    printf("  %s Bytes\n", number_format(strlen($bytes), 0, ',', '.'));
}

if (!is_readable($file)) {
    fwrite(STDERR, "Nicht lesbar: $file\n");
    exit(1);
}

/**
 * The core table out of the tar.gz, line by line.
 *
 * STREAMED AND NOT UNPACKED. Unpacked the table is 43 MB, and PHP would
 * hold it as one string plus 291 428 arrays — several hundred megabytes for
 * a file that is read once from front to back. The compression stream costs
 * nothing and needs no temporary directory.
 */
function faeu_lines(string $archive): Generator
{
    $handle = @fopen('compress.zlib://' . $archive, 'rb');
    if ($handle === false) {
        fwrite(STDERR, "Das Archiv lässt sich nicht öffnen: $archive\n");
        exit(1);
    }

    // A tar is 512-byte blocks: header, then the content padded to 512.
    while (!feof($handle)) {
        $header = fread($handle, 512);
        if ($header === false || strlen($header) < 512 || trim($header) === '') {
            break;
        }
        $name = rtrim(substr($header, 0, 100), "\0");
        $size = (int) octdec(trim(str_replace("\0", '', substr($header, 124, 12))));
        $blocks = (int) ceil($size / 512);

        if (!str_ends_with($name, 'taxon.txt')) {
            // Skip past this entry.
            for ($i = 0; $i < $blocks; $i++) {
                fread($handle, 512);
            }
            continue;
        }

        $rest = '';
        $read = 0;
        for ($i = 0; $i < $blocks; $i++) {
            $block = fread($handle, 512);
            $take  = min(512, $size - $read);
            $rest .= substr((string) $block, 0, max(0, $take));
            $read += 512;

            $pos = strrpos($rest, "\n");
            if ($pos === false) {
                continue;
            }
            foreach (explode("\n", substr($rest, 0, $pos)) as $line) {
                if ($line !== '') {
                    yield $line;
                }
            }
            $rest = substr($rest, $pos + 1);
        }
        if (trim($rest) !== '') {
            yield $rest;
        }
        fclose($handle);
        return;
    }

    fclose($handle);
    fwrite(STDERR, "Im Archiv fehlt taxon.txt\n");
    exit(1);
}

/**
 * Split a scientific name into its name part and its author.
 *
 * @return array{0: string, 1: string} name without author, author
 */
function faeu_author(string $full, string $genus, string $sub, string $epithet, string $infra): string
{
    // The archive's „(null)" may stand in the full name too — it is no
    // name part and no author (faeu_value() says where it comes from).
    $parts = array_values(array_filter(preg_split('/\s+/u', trim($full)) ?: [],
        static fn(string $s): bool => $s !== '(null)'));
    $expected = array_values(array_filter([
        $genus,
        $sub !== '' ? '(' . $sub . ')' : '',
        $epithet,
        $infra,
    ], static fn(string $s): bool => $s !== ''));

    $i = 0;
    foreach ($expected as $k => $part) {
        /* A SUBGENUS THE FULL NAME CARRIES AND ITS COLUMN DOES NOT — the
           archive's subspecies, 6,713 of them: „Lasiocampa (Lasiocampa)
           quercus quercus (Linnaeus, 1758)" with an empty subgenus. The
           walk expected none, stuck on it, and the author became
           „(Lasiocampa) quercus quercus (Linnaeus, 1758)" (found
           26.09.2026, migration 0136 cleaned the stock). Stepped over only
           right after the genus: that is where a subgenus stands, while an
           author in brackets comes after the epithet. */
        if ($k === 1 && $sub === '' && $i === 1
            && preg_match('/^\(\p{Lu}[\p{L}-]*\)$/u', $parts[1] ?? '')) {
            $i++;
        }
        if (($parts[$i] ?? null) === $part) {
            $i++;
        }
    }

    return trim(implode(' ', array_slice($parts, $i)));
}

/**
 * '\N' is the source's null — AND SO IS '(null)'.
 *
 * The second spelling was missed until 26.09.2026: 11,678 synonyms of
 * subspecies carry „(null)" as their specific epithet. They are old
 * species-rank combinations that Fauna Europaea hangs on the nominate
 * subspecies — Phalaena quercus under Lasiocampa quercus quercus —, so the
 * archive has the epithet in the infraspecific column and nothing in the
 * specific one. Read as text, the binomial became „Phalaena (null)
 * quercus", and every suggestion said „über Phalaena (null) quercus".
 * Migration 0135 cleaned the stock the same way.
 */
function faeu_value(array $cells, int $i): string
{
    $v = trim($cells[$i] ?? '');
    return ($v === '' || $v === '\\N' || $v === '(null)') ? '' : $v;
}

// --- first pass: the class per taxon ----------------------------------------
//
// The rank chain lies in parentNameUsageID. Walking it needs everything in
// memory at once, so this pass keeps only what the walk needs — parent and
// rank per id, not the whole row.

$parentOf   = [];
$acceptedOf = [];
$rankOf     = [];
$nameOf     = [];
$count = 0;

foreach (faeu_lines($file) as $line) {
    $c = explode("\t", $line);
    if (count($c) < 12) {
        continue;
    }
    $id = (int) faeu_value($c, 0);
    if ($id === 0) {
        continue;
    }
    $parentOf[$id]   = (int) faeu_value($c, 4);
    $acceptedOf[$id] = (int) faeu_value($c, 3);
    $rank = faeu_value($c, 1);
    $rankOf[$id]     = $rank;

    // ONLY THE NAMES THAT THE WALK EVER READS. Keeping all 291 428 blew the
    // 128 MB memory limit — and of those names the chain only ever asks for
    // the classes and phyla, of which there are a few dozen.
    if ($rank === 'class' || $rank === 'phylum') {
        $nameOf[$id] = faeu_value($c, 2);
    }
    $count++;
}
printf("Gelesen: %s Zeilen\n", number_format($count, 0, ',', '.'));

$classCache = [];
$classOf = static function (int $id) use (&$classCache, $parentOf, $acceptedOf, $rankOf, $nameOf): string {
    if (isset($classCache[$id])) {
        return $classCache[$id];
    }
    $classCache[$id] = '';
    $phylum = '';

    /* A SYNONYM HAS NO PARENT, only its valid name — measured: of 291 428
       rows 117 413 carry no parentNameUsageID, and 117 412 of those carry an
       acceptedNameUsageID instead. Starting the walk at the id itself
       therefore ended immediately for two rows in five, and they came out
       without a group. So a synonym begins at the name it belongs to. */
    $at = ($parentOf[$id] ?? 0) === 0 && ($acceptedOf[$id] ?? 0) > 0
        ? $acceptedOf[$id]
        : $id;

    /* THE CLASS, AND WHERE THERE IS NONE THE PHYLUM. Fauna Europaea does not
       carry every rank on every branch: an annelid goes species → genus →
       family → (unranked) → phylum, with no class anywhere. Insisting on
       'class' left 111 596 of 284 704 rows without a group — a third of the
       list, and the cloud folders would have put them all in one heap.
       Measured on Aeolosoma beddardi, whose chain ends at Annelida. */
    // Fauna Europaea nests deeply — order, superfamily, family, subfamily,
    // tribe, genus, subgenus. Twenty steps are room to spare, not a limit.
    for ($guard = 0; $guard < 20 && $at > 0; $guard++) {
        $rank = $rankOf[$at] ?? '';
        if ($rank === 'class') {
            $classCache[$id] = $nameOf[$at] ?? '';
            return $classCache[$id];
        }
        if ($rank === 'phylum' && $phylum === '') {
            $phylum = $nameOf[$at] ?? '';
        }
        $at = $parentOf[$at] ?? 0;
    }

    $classCache[$id] = $phylum;
    return $classCache[$id];
};

if (!$really) {
    echo "\nProbelauf. Mit --really wird geschrieben.\n\n";
    $shown = 0;
    foreach (faeu_lines($file) as $line) {
        $c = explode("\t", $line);
        if (count($c) < 12 || faeu_value($c, 1) !== 'species' || $shown >= 6) {
            continue;
        }
        $id = (int) faeu_value($c, 0);
        $binomial = trim(faeu_value($c, 7) . ' ' . faeu_value($c, 9));
        $author = faeu_author(faeu_value($c, 2), faeu_value($c, 7), faeu_value($c, 8),
            faeu_value($c, 9), faeu_value($c, 10));
        printf("  %-38s %-26s %s\n", $binomial, mb_substr($author, 0, 24), $classOf($id) ?: '—');
        $shown++;
    }
    exit(0);
}

// --- second pass: write -----------------------------------------------------

if (!table_exists($pdo, 'map_tax_faeu')) {
    fwrite(STDERR, "map_tax_faeu gibt es nicht — Migration 0058 fehlt.\n");
    exit(1);
}

$pdo->beginTransaction();
$pdo->exec('DELETE FROM map_tax_faeu');

$insert = $pdo->prepare(
    'INSERT INTO map_tax_faeu
            (taxnr, sipnr, taxon_rank, binomial, genus, epithet, author, group_name, search_text)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$written = 0;
foreach (faeu_lines($file) as $line) {
    $c = explode("\t", $line);
    if (count($c) < 12) {
        continue;
    }
    $id = (int) faeu_value($c, 0);
    if ($id === 0) {
        continue;
    }

    // Name from the parts, not split off the whole — see the note at the top.
    $genus   = faeu_value($c, 7);
    $epithet = faeu_value($c, 9);
    $infra   = faeu_value($c, 10);
    // From the parts that are there: without a specific epithet (see
    // faeu_value) the name is genus and infraspecific epithet, with one
    // space between them, not two.
    $binomial = implode(' ', array_filter([$genus, $epithet, $infra], static fn(string $s): bool => $s !== ''));
    if ($binomial === '' || $genus === '') {
        continue;
    }

    $author = faeu_author(faeu_value($c, 2), $genus, faeu_value($c, 8), $epithet, $infra);

    $valid = (int) faeu_value($c, 3);

    $insert->execute([
        $id,
        $valid > 0 ? $valid : $id,
        faeu_value($c, 1) ?: null,
        $binomial,
        $genus,
        $epithet !== '' ? $epithet : null,
        $author !== '' ? $author : null,
        $classOf($id) ?: null,
        $binomial,
    ]);
    $written++;
}

$pdo->commit();

printf("\nGeschrieben: %s Zeilen.\n", number_format($written, 0, ',', '.'));
foreach (['species' => 'Arten', 'subspecies' => 'Unterarten'] as $rank => $label) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM map_tax_faeu WHERE taxon_rank = ?');
    $stmt->execute([$rank]);
    printf("  %-12s %s\n", $label, number_format((int) $stmt->fetchColumn(), 0, ',', '.'));
}
