<?php
/**
 * THE ORTSLAGE FILE, built at the desk: Germany's field names, hamlets,
 * single farms, named woods and peaks from OpenStreetMap, as the gzipped
 * file the administration takes in (tab DGfM-Kartierung, „Ortslagen
 * hochladen"; includes/place_names.php says why not by the server).
 *
 *   php admin/place_names_build.php <folder>
 *
 * The folder keeps one file per tile, so a run that broke off — Overpass
 * is shared and sometimes says no — carries on where it stopped; run the
 * same line again. When all 80 tiles are there it writes
 * <folder>/ortslagen-<day>.tsv.gz. A whole run takes an hour or more:
 * a tile costs Overpass up to a minute, and between tiles it breathes.
 *
 * NEEDS NO DATABASE and sends nothing but tile edges — no find, no
 * coordinate of ours.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/place_names.php';

$folder = rtrim((string) ($argv[1] ?? ''), '/');
if ($folder === '' || (!is_dir($folder) && !mkdir($folder, 0775, true))) {
    fwrite(STDERR, "Bitte einen Ordner angeben: php admin/place_names_build.php <Ordner>\n");
    exit(1);
}

// A name that says what asks — never a person or an installation.
$agent = 'YASAIMAPP place-name build';
$tiles = place_name_tiles();
$missing = 0;
foreach ($tiles as $i => [$south, $west]) {
    $path = sprintf('%s/tile-%d-%d.json', $folder, $south, $west);
    if (is_file($path)) {
        continue;
    }
    $rows = null;
    for ($try = 1; $try <= 3 && $rows === null; $try++) {
        try {
            $rows = place_names_fetch_tile($south, $west, $agent);
        } catch (RuntimeException $e) {
            fwrite(STDERR, $e->getMessage() . " — Versuch $try\n");
            sleep(30 * $try);
        }
    }
    if ($rows === null) {
        $missing++;
        continue;
    }
    file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE));
    printf("Kachel %d/%d (%d° N, %d° O): %d Namen\n", $i + 1, count($tiles), $south, $west, count($rows));
    sleep(5);
}
if ($missing > 0) {
    echo "$missing Kacheln fehlen noch — bitte dieselbe Zeile noch einmal aufrufen.\n";
    exit(2);
}

// The tiles share their edges: a name on a seam would come twice.
$day = date('Y-m-d');
$out = gzopen("$folder/ortslagen-$day.tsv.gz", 'wb9');
gzwrite($out, "# YASAIMAPP Ortslagen $day\n");
$seen = [];
$count = 0;
foreach ($tiles as [$south, $west]) {
    foreach (json_decode((string) file_get_contents(sprintf('%s/tile-%d-%d.json', $folder, $south, $west)), true) as $r) {
        $line = sprintf("%s\t%s\t%.5f\t%.5f", $r['kind'], str_replace(["\t", "\n", "\r"], ' ', $r['name']), $r['lat'], $r['lon']);
        if (isset($seen[$line])) {
            continue;
        }
        $seen[$line] = true;
        gzwrite($out, $line . "\n");
        $count++;
    }
}
gzclose($out);
printf("Fertig: %d Namen in %s\n", $count, "$folder/ortslagen-$day.tsv.gz");
