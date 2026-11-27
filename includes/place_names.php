<?php
/**
 * THE ORTSLAGE: the nearest named place below the municipality — a field
 * name, a hamlet, a named wood, a peak — for the MykIS column
 * BASIS_ortslage (migration 0205).
 *
 * WHY OPENSTREETMAP. A coordinator missed the Ortslage (27.11.2026), and
 * below the municipality there seemed to be nothing free to ask — no
 * cadastral map, no gazetteer. But OpenStreetMap carries the field names
 * as place=locality: some 14,400 in Hessen alone, one per 1.5 km², beside
 * 1,400 hamlets and single farms, 781 named woods and 3,745 peaks — and
 * 71 % of the finds there have one of them within a kilometre. MykIS takes
 * the Ortslage as free text (it keeps no list of Germany's woods and
 * fields).
 *
 * BUILT AT THE DESK, UPLOADED AS A FILE. The plan was the server fetching
 * the names state by state from Overpass. Measured the same day, a state
 * took Overpass one to three minutes and Bayern did not finish at all —
 * more than a web request or the hosting's 120-second cron may last. So
 * admin/place_names_build.php fetches Germany in one-degree tiles on a
 * desk computer, where waiting costs nothing, and writes one gzipped
 * file; the administration (tab DGfM-Kartierung) takes it in, the same
 * way as MykIS' host list. A find's coordinate never leaves the server
 * for this, and the lookup is a query of our own. OpenStreetMap's data
 * (ODbL) wants its source named: the coordinator's mail does it.
 *
 * THE FILE, gzipped, one name per line, tab-separated:
 *   # YASAIMAPP Ortslagen 2026-11-27
 *   locality<TAB>Am Hirschberg<TAB>50.12345<TAB>8.67890
 *
 * ONLY FOR FINDERS WHO HAND OVER THAT MUCH (27.11.2026, „alle drei Punkte
 * passen"): a field name within a kilometre tells the place about as
 * precisely as the 64th quadrant (1.4 × 1.5 km). Whoever chose the quarter
 * or the 16th would be given away by it, so mykis_row() asks only at 'q64'
 * and 'exact'.
 */

declare(strict_types=1);

const PLACE_NAME_URL = 'https://overpass-api.de/api/interpreter';

/** How far a name may lie from the find and still be its Ortslage. */
const PLACE_NAME_RADIUS_M = 1000;

/** The sentence the coordinator's mail carries for OpenStreetMap's licence. */
const PLACE_NAME_CREDIT = '© OpenStreetMap-Mitwirkende';

/** Whether migration 0205 is in — the code goes live before it. */
function place_names_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_place_names');
}

/**
 * The database the sheet's rows look names up in — set by whoever builds
 * a sheet (export, delivery, sample), because mykis_row() is a function of
 * the find alone and has no connection of its own. Unset, or before the
 * migration, the column stays empty.
 */
function place_names_source(?PDO $pdo = null, bool $set = false): ?PDO
{
    static $source = null;
    if ($set) {
        $source = $pdo !== null && place_names_ready($pdo)
            && $pdo->query('SELECT 1 FROM map_place_names LIMIT 1')->fetchColumn() !== false
            ? $pdo : null;
    }
    return $source;
}

/** Metres between two points — near enough on a kilometre's scale. */
function place_name_distance(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $x = deg2rad($lon2 - $lon1) * cos(deg2rad(($lat1 + $lat2) / 2));
    $y = deg2rad($lat2 - $lat1);
    return sqrt($x * $x + $y * $y) * 6371000;
}

/**
 * The nearest of the candidates within the radius, or ''.
 *
 * @param list<array{name: string, lat: float|string, lon: float|string}> $candidates
 */
function place_name_nearest(array $candidates, float $lat, float $lon, float $radius = PLACE_NAME_RADIUS_M): string
{
    $best = '';
    $bestDistance = $radius;
    foreach ($candidates as $c) {
        $d = place_name_distance($lat, $lon, (float) $c['lat'], (float) $c['lon']);
        if ($d <= $bestDistance) {
            [$best, $bestDistance] = [(string) $c['name'], $d];
        }
    }
    return $best;
}

/** The Ortslage of a coordinate, from map_place_names, or ''. */
function place_name_at(PDO $pdo, float $lat, float $lon): string
{
    // A box around the point first — the index on lat narrows it, the
    // exact distance decides among the few left.
    $dLat = PLACE_NAME_RADIUS_M / 111320;
    $dLon = $dLat / max(0.1, cos(deg2rad($lat)));
    $stmt = $pdo->prepare('SELECT name, lat, lon FROM map_place_names
                            WHERE lat BETWEEN ? AND ? AND lon BETWEEN ? AND ?');
    $stmt->execute([$lat - $dLat, $lat + $dLat, $lon - $dLon, $lon + $dLon]);
    return place_name_nearest($stmt->fetchAll(PDO::FETCH_ASSOC), $lat, $lon);
}

/**
 * Overpass' answer (tab-separated: type, lat, lon, name, place, natural,
 * landuse) as rows to keep. A wood's coordinate is its centre.
 *
 * @return list<array{kind: string, name: string, lat: float, lon: float}>
 */
function place_names_parse(string $tsv): array
{
    $rows = [];
    foreach (preg_split('/\R/u', $tsv) ?: [] as $line) {
        $cells = explode("\t", $line);
        if (count($cells) < 7 || !is_numeric($cells[1]) || !is_numeric($cells[2])) {
            continue;
        }
        [, $lat, $lon, $name, $place, $natural, $landuse] = $cells;
        $name = trim($name);
        // A wood may carry a place tag of its own (an islet); what it was
        // fetched as decides.
        $kind = in_array($place, ['locality', 'hamlet', 'isolated_dwelling'], true) ? $place
            : ($natural === 'peak' ? 'peak' : ($natural === 'wood' || $landuse === 'forest' ? 'wood' : ''));
        if ($name === '' || $kind === '') {
            continue;
        }
        $rows[] = ['kind' => $kind, 'name' => mb_substr($name, 0, 200), 'lat' => (float) $lat, 'lon' => (float) $lon];
    }
    return $rows;
}

/**
 * Germany in one-degree tiles — south and west edge of each. The border
 * tiles carry the neighbours' names too; a find near the border may take
 * the nearest across it, which is still where it lies.
 *
 * @return list<array{0: int, 1: int}>
 */
function place_name_tiles(): array
{
    $tiles = [];
    for ($south = 47; $south < 55; $south++) {
        for ($west = 5; $west < 15; $west++) {
            $tiles[] = [$south, $west];
        }
    }
    return $tiles;
}

/**
 * One tile's names from Overpass.
 *
 * @return list<array{kind: string, name: string, lat: float, lon: float}>
 * @throws RuntimeException when Overpass does not answer usefully
 */
function place_names_fetch_tile(int $south, int $west, string $agent): array
{
    $query = '[out:csv(::type,::lat,::lon,name,place,natural,landuse;false;"\t")][timeout:300]'
        . '[bbox:' . $south . ',' . $west . ',' . ($south + 1) . ',' . ($west + 1) . '];('
        . 'node["place"~"^(locality|hamlet|isolated_dwelling)$"]["name"];'
        . 'node["natural"="peak"]["name"];'
        . 'way["landuse"="forest"]["name"];way["natural"="wood"]["name"];'
        . 'relation["landuse"="forest"]["name"];relation["natural"="wood"]["name"];'
        . ');out center;';
    $answer = @file_get_contents(PLACE_NAME_URL, false, stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => 'User-Agent: ' . $agent . "\r\nContent-Type: application/x-www-form-urlencoded\r\n",
        'content'       => http_build_query(['data' => $query]),
        'timeout'       => 330,
        'ignore_errors' => true,
    ]]));
    $status = (int) preg_replace('/^\S+ (\d+).*$/', '$1', (string) ($http_response_header[0] ?? ''));
    // Overpass reports a timeout INSIDE a 200 answer, as a remark line —
    // half a tile must not pass for a whole one.
    if ($answer === false || $status !== 200 || str_contains($answer, 'runtime error')) {
        throw new RuntimeException('Overpass ' . ($status ?: 'no answer') . " for tile $south/$west");
    }
    return place_names_parse($answer);
}

/**
 * An uploaded file taken in, in place of everything before — read line by
 * line, because Germany's quarter of a million names would not fit in a
 * request's memory as one array. A file that is not ours, or too small to
 * be Germany, is refused and nothing touched.
 *
 * @return int|null  how many names, null when refused
 */
function place_names_import(PDO $pdo, string $path): ?int
{
    $file = @gzopen($path, 'rb');
    if ($file === false) {
        return null;
    }
    $head = (string) gzgets($file);
    if (!preg_match('/^# YASAIMAPP Ortslagen (\d{4}-\d{2}-\d{2})\s*$/', $head, $m)) {
        gzclose($file);
        return null;
    }
    $count = 0;
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM map_place_names');
        $chunk = [];
        $flush = static function () use (&$chunk, $pdo): void {
            if ($chunk === []) {
                return;
            }
            $pdo->prepare('INSERT INTO map_place_names (kind, name, lat, lon) VALUES '
                . implode(',', array_fill(0, count($chunk) / 4, '(?, ?, ?, ?)')))->execute($chunk);
            $chunk = [];
        };
        while (($line = gzgets($file)) !== false) {
            $cells = explode("\t", rtrim($line, "\r\n"));
            if (count($cells) !== 4 || $cells[0] === '' || $cells[1] === '' || !is_numeric($cells[2]) || !is_numeric($cells[3])) {
                continue;
            }
            array_push($chunk, mb_substr($cells[0], 0, 20), mb_substr($cells[1], 0, 200), (float) $cells[2], (float) $cells[3]);
            $count++;
            if (count($chunk) >= 4000) {
                $flush();
            }
        }
        $flush();
        if ($count < 10000) {
            $pdo->rollBack();
            gzclose($file);
            return null;
        }
        $pdo->prepare('INSERT INTO map_place_name_imports (imported_at, made, names) VALUES (NOW(), ?, ?)')
            ->execute([$m[1], $count]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        gzclose($file);
        throw $e;
    }
    gzclose($file);
    return $count;
}

/**
 * The last file taken in, or null.
 *
 * @return array{made: string, names: int, imported_at: string}|null
 */
function place_names_state(PDO $pdo): ?array
{
    $row = $pdo->query('SELECT made, names, imported_at FROM map_place_name_imports ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    return $row ? ['made' => (string) $row['made'], 'names' => (int) $row['names'], 'imported_at' => (string) $row['imported_at']] : null;
}
