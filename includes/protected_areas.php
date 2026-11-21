<?php
/**
 * Which protected area does a find lie in?
 *
 * ONLY FOR FINDS, not for every point (the call, 14.08.2026): a
 * campsite or a placed waymark does not need the answer. The query is
 * therefore made only for points hanging in a species-list branch — the
 * same yardstick the find fields and the species table hang on
 * (point_species_fields). That is also the answer to the waiting time: whoever
 * places a hundred waymarks never once waits for the BfN.
 *
 * ON THE SERVER AND NOT IN THE BROWSER, and that is the core of the design.
 * The same spot as terrain elevation and municipality: after the commit,
 * silently. Three reasons, each sufficient on its own:
 *
 *   The phone pays not a byte of mobile data and waits for nothing.
 *   It does not matter whether the protected-areas layer is switched on.
 *   A point created in the woods without reception gets its area as soon
 *   as the queue delivers it — in the browser exactly that would fail,
 *   because there is no network there at that moment.
 *
 * THE SERVICE CHECKS FOR ITSELF. The query uses a degenerate BBOX — the
 * point as both corners — and whatever comes back already contains it. A
 * point-in-polygon test of our own would be computing the same thing twice.
 * The box goes in the service's own grid, UTM 32N (see
 * protected_areas_utm32): since the provider's rebuild at the end of
 * September 2026 a box in degrees finds nothing, and every find saved
 * from then on was noted as lying in no protected area at all.
 *
 * MEASURED on 14.08.2026: point in the NSG Altkönig 5.0 kB and 0.23 s per
 * area kind, point with no hit 99 bytes. In the Bavarian Forest, however,
 * 282 kB for the national park alone — WFS delivers whole areas, not
 * clipped to the extent. That is why the call sits where a slow answer
 * holds nobody up, and not before saving.
 *
 * TWO TRAPS, both measured:
 *
 *   PROPERTYNAME=NAME delivers just 236 bytes instead of 282 kB, because
 *   the geometry drops out — and with it BROKEN JSON:
 *   '"geometry":{"type":"MultiPolygon",}' with a comma before the brace
 *   that no parser accepts. Tempting, unusable.
 *
 *   MULTIPLE TYPENAMES in one request answer with three FeatureCollections
 *   glued together — likewise not valid JSON. So one request per area
 *   kind.
 */

declare(strict_types=1);

require_once __DIR__ . '/map_layers.php';
require_once __DIR__ . '/point_species.php';

/** How long we wait for the BfN, per area kind. */
const PROTECTED_AREA_TIMEOUT = 8;

/**
 * Pause between two requests to the BfN.
 *
 * THE SERVICE BELONGS TO SOMEONE ELSE, and a find costs THREE requests
 * here — one per area kind. Without this pause they follow one another
 * immediately: on the first backfill run over 201 finds that was around 600
 * requests at roughly three and a half per second. It worked, but it is no
 * way to treat a public service nobody asked for permission.
 *
 * 400 ms on top of the measured ~250 ms response time yields a good one and
 * a half requests per second — in the order of magnitude of what Nominatim
 * explicitly allows (one per second), and far away from anything that hurts
 * a service.
 *
 * ON A SINGLE SAVE this costs about one second more. That carries no
 * weight: the call sits after the commit, the point has long been saved,
 * and nobody is waiting for it.
 */
const PROTECTED_AREA_PAUSE_MS = 400;

/**
 * The area kinds from the layer definition — NOT copied out.
 *
 * Type id and name field live in map_layers.php because the map needs
 * them for drawing. A second list here would drift apart at the first
 * rework: the service has named its name field differently per area kind
 * before (the FFH area said 'Gebietsname' until September 2026), and
 * whoever updates that in one of two places gets silently empty names.
 *
 * @return array{url: string, kinds: array<array{type: string, text: string, nameField: string}>}|null
 */
function protected_areas_service(): ?array
{
    $layer = map_layers()['protected_areas'] ?? null;
    if (!is_array($layer) || ($layer['url'] ?? '') === '' || !is_array($layer['kinds'] ?? null)) {
        return null;
    }

    $kinds = [];
    foreach ($layer['kinds'] as $kind) {
        if (($kind['type'] ?? '') === '' || ($kind['nameField'] ?? '') === '') {
            continue;
        }
        $kinds[] = [
            'type'        => (string) $kind['type'],
            // The legend's label, verbatim: the map says
            // "Naturschutzgebiet", and the point should carry the same word.
            'text'       => (string) ($kind['text'] ?? ''),
            'nameField' => (string) $kind['nameField'],
        ];
    }
    return $kinds === [] ? null : ['url' => (string) $layer['url'], 'kinds' => $kinds];
}

/**
 * The protected areas at this spot, as "area kind name".
 *
 * WITH THE AREA KIND IN FRONT, and that is no ornament: at the Altkönig the
 * nature reserve and the FFH area are BOTH called "Altkönig" (measured).
 * Without the kind they would collapse into one entry, and the answer
 * "two kinds of law apply here" would have quietly vanished — the very one
 * you ask for. The word is the same as in the map legend.
 *
 * Order as in the layer definition — national park, nature reserve, FFH
 * area — and thus from the strictest to the widest. Whoever truncates the
 * column later then loses the less important first.
 *
 * An area kind that does not answer is skipped rather than letting the
 * whole call fail: half a note is better than none, and the missing kind
 * comes along at the next move.
 *
 * @return string[]
 * @throws RuntimeException if NO area kind could be reached
 */
function protected_areas_get(float $lat, float $lon): array
{
    $service = protected_areas_service();
    if ($service === null) {
        throw new RuntimeException('Für Schutzgebiete fehlt die Ebenendefinition.');
    }

    $names    = [];
    $reached = false;
    $first    = true;

    foreach ($service['kinds'] as $kind) {
        // BEFORE every request except the first — that way a single point
        // never waits for nothing, and there is always air between two
        // requests.
        if (!$first) {
            usleep(PROTECTED_AREA_PAUSE_MS * 1000);
        }
        $first = false;

        // THE POINT IN THE SERVICE'S OWN GRID, with the grid named — the
        // same trap as in layers.js (outlineKindData), see the comment
        // there: in degrees the service has found nothing since the end of
        // September 2026.
        [$east, $north] = protected_areas_utm32($lat, $lon);
        $url = $service['url'] . '?' . http_build_query([
            'SERVICE'      => 'WFS',
            'VERSION'      => '2.0.0',
            'REQUEST'      => 'GetFeature',
            'TYPENAMES'    => $kind['type'],
            'OUTPUTFORMAT' => 'GEOJSON',
            'COUNT'        => 20,
            'BBOX'         => sprintf('%.1F,%.1F,%.1F,%.1F,urn:ogc:def:crs:EPSG::25832', $east, $north, $east, $north),
        ]);

        // WITHOUT A BROWSER IDENTIFIER THE BfN ANSWERS WITH 403. Measured:
        // a call without a User-Agent gets an HTML error page with status
        // 403, and does so even when everything else is right. That is the
        // sort of error you spend an hour hunting for in your own URL.
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: Mozilla/5.0 (compatible; Karte)\r\n"
                           . "Accept: application/json\r\n",
                'timeout' => PROTECTED_AREA_TIMEOUT,
            ],
        ]));
        if ($response === false) {
            continue;
        }

        $d = json_decode($response, true);
        if (!is_array($d) || !is_array($d['features'] ?? null)) {
            continue;
        }
        $reached = true;

        foreach ($d['features'] as $f) {
            $name = trim((string) ($f['properties'][$kind['nameField']] ?? ''));
            if ($name === '') {
                continue;
            }
            $entry = trim($kind['text'] . ' ' . $name);
            // Deduplication goes over the FINISHED entry, not over the bare
            // name: two areas of the same name are two areas as long as
            // their kind differs.
            if (!in_array($entry, $names, true)) {
                $names[] = $entry;
            }
        }
    }

    if (!$reached) {
        throw new RuntimeException('Der Schutzgebietsdienst antwortet nicht.');
    }
    return $names;
}

/**
 * WGS84 → ETRS89 / UTM zone 32N (EPSG:25832), the protected-area service's
 * own grid.
 *
 * A TWIN of utm32() in assets/js/layers.js, which asks the same service for
 * the map's view — Snyder's transverse Mercator series in both, line for
 * line. ETRS89 and WGS84 lie well under a metre apart here, and zone 32's
 * grid runs on across the east of the country as the service uses it there
 * too. tests/protected_areas.php holds it to the service itself: a find at
 * the Altkönig must come back with its two areas.
 *
 * @return array{0: float, 1: float} east, north in metres
 */
function protected_areas_utm32(float $lat, float $lon): array
{
    $a   = 6378137.0;
    $f   = 1 / 298.257222101;               // GRS80
    $k0  = 0.9996;
    $e2  = $f * (2 - $f);
    $ep2 = $e2 / (1 - $e2);
    $phi = deg2rad($lat);
    $sin = sin($phi);
    $cos = cos($phi);
    $tan = tan($phi);
    $n   = $a / sqrt(1 - $e2 * $sin * $sin);
    $t   = $tan * $tan;
    $c   = $ep2 * $cos * $cos;
    $A   = $cos * deg2rad($lon - 9);
    $e4  = $e2 * $e2;
    $e6  = $e4 * $e2;
    $m   = $a * ((1 - $e2 / 4 - 3 * $e4 / 64 - 5 * $e6 / 256) * $phi
        - (3 * $e2 / 8 + 3 * $e4 / 32 + 45 * $e6 / 1024) * sin(2 * $phi)
        + (15 * $e4 / 256 + 45 * $e6 / 1024) * sin(4 * $phi)
        - (35 * $e6 / 3072) * sin(6 * $phi));
    $east = 500000 + $k0 * $n * ($A + (1 - $t + $c) * $A ** 3 / 6
        + (5 - 18 * $t + $t * $t + 72 * $c - 58 * $ep2) * $A ** 5 / 120);
    $north = $k0 * ($m + $n * $tan * ($A * $A / 2 + (5 - $t + 9 * $c + 4 * $c * $c) * $A ** 4 / 24
        + (61 - 58 * $t + $t * $t + 600 * $c - 330 * $ep2) * $A ** 6 / 720));
    return [$east, $north];
}

/**
 * All species lists — across all accounts.
 *
 * For the backfill: it must be able to find in ONE query which points are
 * finds at all, and point_species_fields() answers that only per point.
 * Since the Mappen (0168) every list carries the flag itself; nothing has
 * to be passed down a tree any more.
 *
 * @return int[] List ids
 */
function protected_areas_species_lists(PDO $pdo): array
{
    return array_map('intval', $pdo->query(
        'SELECT id FROM map_lists WHERE deleted_at IS NULL AND is_species = 1')->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Record the protected area on the point. The one place where that happens.
 *
 * THREE STATES IN ONE COLUMN, and the distinction carries the whole
 * mechanics:
 *
 *   NULL   not yet asked — or the service stayed silent
 *   ''     asked, the point lies in no protected area
 *   'A, B' asked, namely these
 *
 * Without the empty text as a state of its own, "lies in none" could not be
 * told apart from "was never asked", and a later backfill would run over
 * the same points in the middle of the field again on every pass.
 *
 * COMMA-SEPARATED IN ONE COLUMN (the call, 14.08.2026): a find
 * often lies in the nature reserve and the FFH area at once — the Altkönig
 * is exactly such a case.
 *
 * updated_at stays untouched, stand advances: the same as with
 * municipality and elevation — a server derivation is not a user change and
 * must not take precedence away from them during sync; the buckets'
 * fingerprint still has to notice the new value.
 *
 * @return string|null the recorded value, or null if nothing happened
 * @throws RuntimeException if $silently is false
 */
function protected_area_record(PDO $pdo, int $userId, int $pointId,
                                bool $silently = false): ?string
{
    if (!column_exists($pdo, 'map_points', 'protected_area')) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT lat, lon FROM map_points WHERE id = ? AND user_id = ?');
    $stmt->execute([$pointId, $userId]);
    $p = $stmt->fetch();
    if ($p === false) {
        return null;
    }

    // ONLY FOR FINDS. The question goes to the branch, not the leaf: whoever
    // collects in "Meine Funde › Pilze › Röhrlinge" has usually set the
    // species-list checkmark at the very top (which is why point_species_fields
    // climbs).
    $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
    $stmt->execute([$pointId]);
    $lists = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if ($lists === [] || !point_species_fields($pdo, $lists)['title']) {
        return null;
    }

    try {
        $names = protected_areas_get((float) $p['lat'], (float) $p['lon']);
    } catch (RuntimeException $e) {
        // The column stays as it was — NULL still means "not yet asked",
        // and the next occasion asks again.
        if ($silently) {
            return null;
        }
        throw $e;
    }

    $value = mb_substr(implode(', ', $names), 0, 255);

    $pdo->prepare('UPDATE map_points SET protected_area = ? WHERE id = ? AND user_id = ?')
        ->execute([$value, $pointId, $userId]);

    if (column_exists($pdo, 'map_points', 'touched_at')) {
        $pdo->prepare('UPDATE map_points SET touched_at = CURRENT_TIMESTAMP(6) WHERE id = ? AND user_id = ?')
            ->execute([$pointId, $userId]);
    }

    return $value;
}
