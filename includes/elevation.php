<?php
/**
 * Terrain elevation from the DGM1 of the state surveying agencies.
 *
 * WHY NOT THE ELEVATION FROM THE RECEIVER
 * A satellite receiver is two to three times less accurate vertically than
 * horizontally: with ±4 m in the plane, ±10 m in elevation is normal. The
 * DGM1, by contrast, resolves to one meter and is accurate to ±15 cm, on a
 * steep slope to ±30 cm. And because the horizontal position is right to
 * ±4 m, all you inherit from there is the error from the slope gradient —
 * on a 20 percent slope under one meter. So ±10 m becomes roughly ±1 m.
 *
 * WHERE FROM
 * hoehendaten.de bundles the open-data elevation models of all sixteen
 * states and answers without a key. The elevations refer to DHHN2016 — the
 * same NHN the geoid conversion aims at, just without the detour.
 *
 * WHAT THIS COSTS, AND NOT IN MONEY
 * The request transmits the exact coordinate of a saved place to a third
 * party. The embedded map services only ever see the map viewport, never
 * the points — a tile is around 150 m wide, a point is a point. This
 * request is the exception to that rule, and it is taken knowingly.
 *
 * IT HAPPENS BY ITSELF, FOR EVERY FIND: when a point is created and when it
 * is moved (data.php, right after the commit), and the backfill catches up
 * what failed then or came in by an import (elevation_content.php, a job of
 * the five-minute cron bundle). The very first version asked only on a
 * button press, point by point, and this header went on saying so long
 * after the elevation had become part of creating a point — found on
 * 20.11.2026, when the privacy statement had to say what really leaves the
 * server. The statement names it (pages/privacy.php, section 6), and
 * whoever changes when or where this request goes changes that sentence
 * too.
 *
 * WHAT GOES ALONG is the coordinate, and the program's name as the request's
 * ID — no person's name, no account, no title of the find.
 *
 * The operator is a private individual (Klaus Tockloth, Münster), hosted at
 * Hetzner. The privacy policy promises to process input data only
 * transiently; the usual server logs with IP address still accrue at the
 * hoster regardless.
 *
 * The request goes out from the server, not from the browser. That way the
 * service only sees the address of this server and not that of the phone
 * currently standing in the woods.
 */

declare(strict_types=1);

const TERRAIN_ELEVATION_URL = 'https://api.hoehendaten.de:14444/v1/point';

/**
 * Time limit — short, because the query runs along with creating a point.
 *
 * Measured, the service answers in around 330 ms; the slowest of six
 * attempts took 391 ms. Five seconds is therefore plenty; it only kicks in
 * when something is really stuck. And even then the point is created —
 * just without an elevation, which the backfill fetches later.
 */
const TERRAIN_ELEVATION_TIMEOUT = 5;

/**
 * The spot lies outside the model.
 *
 * A class of its own so that the backfill can tell "in Italy, no German
 * model" apart from "the service was unreachable": the first is final and
 * needs no second attempt, the second does.
 */
class ElevationOutside extends RuntimeException
{
    public function isOutside(): bool
    {
        return $this->getCode() === 1;
    }
}

/**
 * Terrain elevation for a coordinate.
 *
 * @return array{height: float, as_of: string, origin: string, attribution: string}
 * @throws RuntimeException if the service does not answer or the spot is
 *                          not covered
 */
function elevation_get(float $lat, float $lon): array
{
    $request = json_encode([
        'Type'       => 'PointRequest',
        'ID'         => 'YASAIMAPP',
        'Attributes' => ['Longitude' => $lon, 'Latitude' => $lat],
    ], JSON_UNESCAPED_SLASHES);

    // 'Accept: application/json' is mandatory — without the header the
    // service answers with error 1060 even though everything else is right.
    $response = @file_get_contents(TERRAIN_ELEVATION_URL, false, stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content'       => $request,
            'timeout'       => TERRAIN_ELEVATION_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]));

    if ($response === false) {
        throw new RuntimeException('Der Höhendienst antwortet nicht.');
    }

    $d = json_decode($response, true);
    if (!is_array($d) || !isset($d['Attributes'])) {
        throw new RuntimeException('Der Höhendienst hat unverständlich geantwortet.');
    }

    $a = $d['Attributes'];

    if (!empty($a['IsError'])) {
        // The most common case is: the spot lies outside Germany. That is
        // not an error but a boundary, and should sound like one. The
        // service reports it in two ways — far outside with "invalid
        // latitude/longitude for Germany", just barely outside with a
        // missing tile.
        $detail = (string) ($a['Error']['Detail'] ?? '');
        $outside = str_contains($detail, 'not found')
            || str_contains($detail, 'for Germany');

        throw new ElevationOutside($outside
            ? 'Außerhalb des deutschen Höhenmodells.'
            : 'Der Höhendienst meldet einen Fehler.', $outside ? 1 : 0);
    }

    $elevation = $a['Elevation'] ?? null;
    if (!is_numeric($elevation) || $elevation < -500 || $elevation > 3000) {
        throw new RuntimeException('Der Höhendienst hat keinen brauchbaren Wert geliefert.');
    }

    return [
        'height'    => (float) $elevation,
        'as_of'    => (string) ($a['Actuality'] ?? ''),
        'origin'   => (string) ($a['Origin'] ?? ''),
        'attribution' => (string) ($a['Attribution'] ?? ''),
    ];
}

const TERRAIN_ELEVATION_WORLD_URL = 'https://api.open-meteo.com/v1/elevation';

/**
 * Terrain elevation outside Germany.
 *
 * The German DGM1 stops at the border — and the Alpine points lie beyond
 * it. Here the Copernicus elevation model GLO-90 answers, worldwide and
 * without a key, provided by Open-Meteo.
 *
 * WHAT THIS COSTS IN ACCURACY, and it is no small thing: the grid has a
 * mesh width of 90 meters instead of one. Measured in the Taunus, where
 * both sources answer: DGM1 104.8 m, GLO-90 109 m — a good four meters of
 * difference on flat terrain. On a steep slope it gets worse, because a
 * 90-meter cell averages over a whole slope and flattens summits.
 *
 * Still clearly better than the receiver's +/-10 m, but a different order
 * of magnitude than the German model. That is why the origin is stored on
 * the point.
 *
 * @return array{height: float, as_of: string, origin: string, attribution: string}
 * @throws RuntimeException
 */
function elevation_worldwide(float $lat, float $lon): array
{
    $url = TERRAIN_ELEVATION_WORLD_URL . '?' . http_build_query([
        'latitude'  => round($lat, 6),
        'longitude' => round($lon, 6),
    ]);

    $response = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => "Accept: application/json\r\n",
            'timeout'       => TERRAIN_ELEVATION_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]));

    if ($response === false) {
        throw new RuntimeException('Der weltweite Höhendienst antwortet nicht.');
    }

    $d = json_decode($response, true);
    $elevation = $d['elevation'][0] ?? null;

    // Limits set wide: from the Dead Sea (-430 m) to Everest.
    if (!is_numeric($elevation) || $elevation < -500 || $elevation > 9000) {
        throw new RuntimeException('Der weltweite Höhendienst hat keinen brauchbaren Wert geliefert.');
    }

    return [
        'height'    => (float) $elevation,
        'as_of'    => '',
        'origin'   => 'GLO-90',
        'attribution' => 'Copernicus DEM GLO-90 über Open-Meteo',
    ];
}

/**
 * Fetch the terrain elevation and record it on the point.
 *
 * The one place where this happens — creating a point, backfilling existing
 * points and the API call all pass through here.
 *
 * elevation_accuracy_m is cleared: it belonged to the receiver's measurement.
 * For the terrain model it is wrong — that model's uncertainty is around
 * ±0.3 m and not the ±10 m that would stand there.
 *
 * @return array|null the service's answer, or null on failure
 * @throws ElevationOutside if $silent is false
 */
function elevation_record(PDO $pdo, int $userId, int $pointId, bool $silent = false): ?array
{
    $stmt = $pdo->prepare('SELECT lat, lon FROM map_points WHERE id = ? AND user_id = ?');
    $stmt->execute([$pointId, $userId]);
    $p = $stmt->fetch();
    if ($p === false) {
        return null;
    }

    // The German model first — it is two orders of magnitude more accurate.
    // Only when the spot lies outside it, the worldwide one.
    $source = 'dgm1';
    try {
        $result = elevation_get((float) $p['lat'], (float) $p['lon']);
    } catch (ElevationOutside $e) {
        if (!$e->isOutside()) {
            if ($silent) return null;
            throw $e;
        }
        try {
            $result = elevation_worldwide((float) $p['lat'], (float) $p['lon']);
            $source  = 'worldwide';
        } catch (RuntimeException $e2) {
            if ($silent) return null;
            throw $e2;
        }
    } catch (RuntimeException $e) {
        if ($silent) return null;
        throw $e;
    }

    $pdo->prepare(
        'UPDATE map_points
            SET elevation_m = ?, elevation_source = ?, elevation_accuracy_m = NULL, updated_at = NOW()
          WHERE id = ? AND user_id = ?'
    )->execute([(int) round($result['height']), $source, $pointId, $userId]);

    $result['source'] = $source;
    return $result;
}
