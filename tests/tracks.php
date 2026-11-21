<?php
/**
 * Test of the track sieve (includes/tracks.php).
 *
 *   php tests/tracks.php
 *
 * WHY THIS IS TESTED. The sieve decides what a walk was: how long, how far,
 * and which rectangle a map has to frame to show it. Every one of those
 * numbers is DERIVED, and a derived number that is quietly wrong is the
 * worst kind — it looks like a measurement. "14,8 km" reads exactly as
 * trustworthy as "9,6 km", and only one of them is the walk.
 *
 * The rules under test were not invented at the desk. They come from a
 * synthetic walk of 1000 metres with 5 metres of scatter, a ten-minute
 * standing break and one artefact jump, sent through the real endpoint on
 * 25.08.2026: a fixed five-metre threshold reported 1588 metres and a
 * bounding box two and a half kilometres tall. Both numbers are held here.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/tracks.php';

$failed = 0;
function check(string $name, bool $ok, string $extra = ''): void
{
    global $failed;
    if ($ok) {
        echo "  ok    $name\n";
        return;
    }
    $failed++;
    echo "  FAILED  $name" . ($extra !== '' ? ' — ' . $extra : '') . "\n";
}

/** One position: [lat, lon, ele, t, acc] — the shape that goes over the wire. */
function p(float $lat, float $lon, ?float $acc = 5.0, int $t = 0): array
{
    return [$lat, $lon, 120.0, $t, $acc];
}

echo "\nThe distance between two positions\n";
// Two points 1000 metres apart on the same meridian — one degree of
// latitude is 111.32 km, so 1000 m is 0.008983 degrees.
$d = track_distance_m(50.0, 8.0, 50.0 + 1000 / 111320, 8.0);
check('a thousand metres north measure a thousand metres', abs($d - 1000) < 2, round($d, 1) . ' m');
check('the same spot is zero', track_distance_m(50.0, 8.0, 50.0, 8.0) < 0.001);
check('east and west are symmetric',
    abs(track_distance_m(50.0, 8.0, 50.0, 8.01) - track_distance_m(50.0, 8.01, 50.0, 8.0)) < 0.001);

echo "\nStanding still is not walking\n";
{
    // A device lying still, scattering by about three metres, half an hour
    // long. Real distance: zero.
    mt_srand(7);
    $rows = [];
    for ($i = 0; $i < 900; $i++) {
        $rows[] = p(50.1 + (mt_rand(-30, 30) / 1e6), 8.7 + (mt_rand(-30, 30) / 1e6), 8.0, 1756000000000 + $i * 2000);
    }
    $read = track_positions($rows);
    check('a half hour of standing walks under 50 metres',
        $read['distance'] < 50, round($read['distance']) . ' m');
    // SINCE 0100 THE SIEVE MEASURES AND NO LONGER DECIDES WHAT IS STORED:
    // the original keeps every fix, the sieved line is what counted.
    check('every fix stays in the original', count($read['positions']) === 900,
        count($read['positions']) . ' positions');
    check('and the sieved line is almost nothing', count($read['sieved']) < 20,
        count($read['sieved']) . ' sieved');
    check('the drawing of it is shorter still', count(track_drawing($read['sieved'])) <= count($read['sieved']));
}

echo "\nA position that doubts itself is dropped\n";
{
    $read = track_positions([
        p(50.100, 8.700, 5.0, 1000),
        p(50.120, 8.720, 300.0, 2000),   // the artefact under the canopy
        p(50.101, 8.700, 5.0, 3000),
    ]);
    check('it does not end up in the line', count($read['positions']) === 2,
        count($read['positions']) . ' positions');
    // THE BOUNDING BOX IS THE POINT. The artefact used to stay in the line
    // and stretched the frame by two and a half kilometres, although it
    // was already excluded from the distance — trusted for the one, not
    // for the other.
    check('and above all not in the bounding box', $read['box']['lat_max'] < 50.11,
        (string) $read['box']['lat_max']);
}
check('unknown accuracy is not bad accuracy',
    count(track_positions([p(50.100, 8.700, null), p(50.110, 8.700, null)])['positions']) === 2);

echo "\nThe walk itself survives the sieve\n";
{
    // 500 metres north, two metres per step, with five metres of scatter —
    // and the scatter is what a fixed threshold used to count along.
    mt_srand(11);
    $rows = [];
    $lat  = 50.1;
    for ($i = 0; $i < 250; $i++) {
        $lat += 2.0 / 111320;
        $rows[] = p($lat + (mt_rand(-20, 20) / 1e6), 8.7 + (mt_rand(-20, 20) / 1e6), 5.0, 1756000000000 + $i * 2000);
    }
    $read = track_positions($rows);
    check('500 metres walked are measured as 500, not as 800',
        abs($read['distance'] - 500) < 60, round($read['distance']) . ' m');
    check('the sieved line keeps enough shape to draw',
        count($read['sieved']) > 50, count($read['sieved']) . ' sieved');
    check('the original keeps every step', count($read['positions']) === 250,
        count($read['positions']) . ' positions');
}

echo "\nThe drawing — the sieved line, simplified\n";
{
    // A straight walk north, 300 m, five metres a step, half a metre of
    // jitter: the drawing is its two ends — everything between lies well
    // inside the tolerance.
    $straight = [];
    for ($i = 0; $i <= 60; $i++) {
        $straight[] = p(50.1 + $i * 5 / 111320, 8.7 + (($i % 2) ? 0.5 : -0.5) / 71000, 5.0, $i * 3000);
    }
    $d = track_drawing($straight);
    check('a straight walk draws as its two ends', count($d) === 2, count($d) . ' vertices');
    check('the first and the last position always stay', $d[0] === $straight[0] && $d[1] === $straight[60]);

    // The same walk with a right angle in the middle: the corner survives.
    $corner = [];
    for ($i = 0; $i <= 30; $i++) $corner[] = p(50.1 + $i * 5 / 111320, 8.7, 5.0, $i * 3000);
    for ($i = 1; $i <= 30; $i++) $corner[] = p(50.1 + 30 * 5 / 111320, 8.7 + $i * 5 / 71000, 5.0, (30 + $i) * 3000);
    $d = track_drawing($corner);
    check('a corner keeps its corner', count($d) === 3 && $d[1] === $corner[30], count($d) . ' vertices');

    // Every vertex of the drawing is a position of the walk — nothing is
    // interpolated, so elevation, time and accuracy ride along.
    $own = true;
    foreach ($d as $v) { $own = $own && in_array($v, $corner, true); }
    check('the drawing is made of the walk\'s own positions', $own);

    // The tolerance holds: on a scattered walk no sieved position lies
    // farther from the drawing than TRACK_DRAWING_TOLERANCE_M.
    mt_srand(23);
    $rows = [];
    $lat = 50.1; $lon = 8.7;
    for ($i = 0; $i < 400; $i++) {
        $lat += 4 * cos($i / 50) / 111320;
        $lon += 4 * sin($i / 50) / 71000;
        $rows[] = p($lat + mt_rand(-40, 40) / 1e6, $lon + mt_rand(-60, 60) / 1e6, 6.0, 1756000000000 + $i * 3000);
    }
    $read = track_positions($rows);
    $d = track_drawing($read['sieved']);
    $worst = 0.0;
    foreach ($read['sieved'] as $pos) {
        $near = INF;
        for ($i = 0; $i < count($d) - 1; $i++) {
            $near = min($near, track_project($d[$i], $d[$i + 1], (float) $pos[0], (float) $pos[1])['dist']);
        }
        $worst = max($worst, $near);
    }
    check('no sieved position lies farther from the drawing than the tolerance',
        $worst <= TRACK_DRAWING_TOLERANCE_M + 0.01, round($worst, 2) . ' m');
    check('and the drawing is the shorter list', count($d) < count($read['sieved']),
        count($d) . ' of ' . count($read['sieved']));
    check('a line of two is its own drawing', track_drawing([p(50.1, 8.7), p(50.2, 8.7)]) === [p(50.1, 8.7), p(50.2, 8.7)]);

    // A STRAIGHT ROAD OVER A CREST: in the plane nothing bends, so the
    // plane alone would draw a ramp where there was a hill. Thirty metres
    // up over the first half, thirty down over the second.
    $hill = [];
    for ($i = 0; $i <= 60; $i++) {
        $hill[] = [50.1 + $i * 5 / 111320, 8.7, 200.0 + 30 - abs($i - 30), $i * 3000, 5.0];
    }
    $d = track_drawing($hill);
    check('a crest on a straight road stays in the drawing', count($d) === 3 && $d[1] === $hill[30],
        count($d) . ' vertices');
    // The same road with a bump of six metres: the receiver's own doubt in
    // height, and not worth a vertex.
    $bump = [];
    for ($i = 0; $i <= 60; $i++) {
        $bump[] = [50.1 + $i * 5 / 111320, 8.7, 200.0 + max(0, 6 - abs($i - 30) * 0.2), $i * 3000, 5.0];
    }
    check('a bump under the height tolerance does not', count(track_drawing($bump)) === 2);
    // Without elevations the plane decides alone — no warning, no vertex.
    $flat = array_map(static fn(array $v): array => [$v[0], $v[1], null, $v[3], $v[4]], $hill);
    check('a walk without elevations is judged in the plane alone', count(track_drawing($flat)) === 2);
}

echo "\nWhich segment a spot means\n";
{
    // Out and back on one path: north for six positions, then south again
    // ten metres to the east. A spot two metres east of the return leg
    // means the return leg, not the outbound one eight metres away.
    $walk = [];
    for ($i = 0; $i < 6; $i++) $walk[] = p(50.1 + $i * 0.001, 8.7, 5.0, $i * 60000);
    for ($i = 5; $i >= 0; $i--) $walk[] = p(50.1 + $i * 0.001, 8.7 + 10 / 71000, 5.0, (11 - $i) * 60000);
    check('a spot beside the outbound leg names its segment',
        track_nearest_segment($walk, 50.1 + 2.5 * 0.001, 8.7 - 2 / 71000) === 2);
    check('a spot beside the return leg names the return leg',
        track_nearest_segment($walk, 50.1 + 2.5 * 0.001, 8.7 + 12 / 71000) === 8);
    check('one position is no line', track_nearest_segment([p(50.1, 8.7)], 50.1, 8.7) === -1);
}

echo "\nWhat comes in, and in which shape\n";
check('the object shape reads the same as the list shape',
    track_positions([['lat' => 50.1, 'lon' => 8.7, 'acc' => 4],
                     ['lat' => 50.11, 'lon' => 8.7, 'acc' => 4]])['positions'][0][0] === 50.1);
check('lng is accepted beside lon — Leaflet says lng',
    count(track_positions([['lat' => 50.1, 'lng' => 8.7]])['positions']) === 1);
check('a broken entry does not cost the walk',
    count(track_positions([['lat' => 'nein', 'lon' => 'auch nicht'],
                           p(50.1, 8.7), p(50.11, 8.7)])['positions']) === 2);
check('coordinates out of the world fall out',
    count(track_positions([p(91.0, 8.7), p(50.1, 8.7)])['positions']) === 1);

foreach ([['no list at all', null], ['nothing usable', [['lat' => 'x']]]] as [$what, $rows]) {
    $threw = false;
    try { track_positions($rows); } catch (InvalidArgumentException) { $threw = true; }
    check($what . ' is refused, not silently stored', $threw);
}

echo "\nCutting a walk in two\n";
{
    $walk = [];
    for ($i = 0; $i < 6; $i++) $walk[] = p(50.1 + $i * 0.001, 8.7, 5.0, 1756000000000 + $i * 60000);
    [$head, $tail] = track_split_positions($walk, 2);
    check('the head runs up to the cut, cut included', count($head) === 3 && $head[2] === $walk[2]);
    check('the tail starts AT the cut, so the halves still touch', count($tail) === 4 && $tail[0] === $walk[2]);
    check('nothing is lost between them', count($head) + count($tail) === count($walk) + 1);
    foreach ([['at the first position', 0], ['at the last position', 5], ['beyond the end', 9]] as [$what, $at]) {
        $threw = false;
        try { track_split_positions($walk, $at); } catch (InvalidArgumentException) { $threw = true; }
        check('a cut ' . $what . ' is refused — a half must still be a line', $threw);
    }
    // Between two positions: the walk runs north 111 m per step; a spot
    // 40 m past position 2, 30 m off to the east, lands on the line.
    [$h2, $t2] = track_split_between($walk, 2, 50.1 + 2 * 0.001 + 40 / 111320, 8.7 + 30 / 71000);
    check('a cut between two positions inserts a point on the line',
        count($h2) === 4 && count($t2) === 4 && abs($h2[3][1] - 8.7) < 1e-6 && abs($h2[3][0] - (50.102 + 40 / 111320)) < 1e-5);
    check('the inserted point is shared by both halves', $h2[3] === $t2[0]);
    check('its time lies between its neighbours',
        $h2[3][3] > $walk[2][3] && $h2[3][3] < $walk[3][3] && abs($h2[3][3] - ($walk[2][3] + 0.36 * 60000)) < 1000);
    check('it carries no accuracy of its own — nothing for the sieve to doubt', $h2[3][4] === null);
    [$h3, ] = track_split_between($walk, 2, 50.1 + 2 * 0.001 + 2 / 111320, 8.7);
    check('two metres past a position snaps to that position', count($h3) === 3 && $h3[2] === $walk[2]);
    [$h4, ] = track_split_between($walk, 2, 50.1 + 3 * 0.001 - 3 / 111320, 8.7);
    check('three metres before the next one snaps to the next one', count($h4) === 4 && $h4[3] === $walk[3]);

    check('a tour id is twelve hex characters, as the points mint them',
        track_tour_id('3F2A9C0B11ee') === '3f2a9c0b11ee' && track_tour_id('nope') === null && track_tour_id(null) === null);
    $id = track_client_id_mint();
    check('a minted client id has the shape the devices use', (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id), $id);
}

echo "\nThe moment\n";
check('milliseconds become a timestamp',
    track_moment(1756000000000) === date('Y-m-d H:i:s', 1756000000));
check('without a time no moment', track_moment(null) === null);
check('zero is no time either', track_moment(0) === null);

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
