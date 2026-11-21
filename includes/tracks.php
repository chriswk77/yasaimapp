<?php
/**
 * Recorded tracks — the recorder app's counterpart on the server.
 *
 * WHAT A TRACK IS HERE. A list of positions with a time each, nothing more.
 * It is not a find and does not become one: whoever walks past a mushroom
 * sets a point, and the track only says where he walked. Both meet on the
 * map later, they do not merge.
 *
 * WHY THE SERVER RECOUNTS. Distance, duration, count and bounding box are
 * computed HERE from the positions, although the app knows them all. Not
 * out of distrust of the app but because they are DERIVED: two sources for
 * one number drift apart, and then nobody knows which one the list shows.
 * The app may send them; they are ignored.
 *
 * THE POSITIONS ARE SIEVED ON THE WAY IN (track_positions), and the sieve
 * is the DEVICE'S OWN ACCURACY. A phone lying still delivers a cloud that
 * jitters by ten metres and walks a kilometre during a lunch break; a
 * receiver under dense canopy reports a single position half a kilometre
 * away. Both are answered by the number the receiver hands over with every
 * fix: a step shorter than that accuracy is not movement, and a position
 * whose accuracy is worse than TRACK_MAX_ACCURACY_M is not evidence of
 * anything and is dropped whole.
 *
 * Measured on a synthetic walk of 1000 metres with 5 metres of scatter, a
 * ten-minute standing break and one artefact jump: a fixed five-metre
 * threshold reported 1588 metres and a bounding box two and a half
 * kilometres tall. With the accuracy as the threshold: 1010 metres, and
 * the box spans exactly the walk.
 *
 * THE SIEVE MEASURES, IT NO LONGER DECIDES WHAT IS STORED (12.09.2026,
 * migration 0100 — the day the app went from a fix every ten seconds to
 * one every three). Until then a position the sieve did not count was
 * never written; at three seconds that is two fixes out of three, and the
 * app's side promises never to thin what it sends precisely so the
 * original stays for later — an elevation profile, a re-sieve with better
 * rules. So `positions` holds every position that is a position at all
 * (in the world, accuracy under TRACK_MAX_ACCURACY_M), and the sieve
 * derives three things from it: the distance, the box, and the DRAWING.
 *
 * THE DRAWING IS A COPY FOR THE MAP (`drawing`), the sieved line
 * simplified by Douglas–Peucker (track_drawing). Seven thousand fixes a
 * day are harmless in a column; a hundred such days are seven hundred
 * thousand vertices for the browser's renderer on every pan, and most of
 * them lie within the receiver's own scatter of the line. The buckets
 * deliver the drawing, GET ?id= delivers the original, and a cut
 * (track_split) is placed on the original by the tapped spot — the two
 * sides no longer share an array whose indices would have to agree.
 */

declare(strict_types=1);

// t() for the refusal sentences. Usually bootstrap.php has long loaded it,
// but tests/tracks.php includes THIS file standalone — the sieve needs no
// database, and it should stay that way.
require_once __DIR__ . '/i18n.php';

/** The floor under the step threshold — below this nothing ever counts. */
const TRACK_MIN_STEP_M = 5.0;

/**
 * Worse than this, a position is not evidence of anything.
 *
 * A receiver coming out from under dense canopy reports its own doubt
 * honestly: 200, 300 metres. Such a position is dropped ENTIRELY, not just
 * kept out of the distance — measured on the test track, one of them
 * stretched the bounding box by two and a half kilometres, and the map
 * would have framed a walk twice its real size. Whoever does not trust a
 * position enough to measure with it must not let it decide the frame
 * either.
 */
const TRACK_MAX_ACCURACY_M = 50.0;

/** At most this many positions per track — a day of walking is far below. */
const TRACK_MAX_POSITIONS = 100000;

/**
 * How far the drawing may leave the sieved line — the Douglas–Peucker
 * tolerance of track_drawing(), in metres.
 *
 * MEASURED, NOT GUESSED (12.09.2026, on the real walk of that morning: 343
 * sieved positions over 4.4 km, and on a synthetic three-second day of
 * 7 200 fixes, of which the sieve keeps 2 588): at two metres the drawing
 * keeps 65 and 80 per cent of the sieved vertices, at three 55 and 70, at
 * five 43 and 45. The receiver scatters by about five metres, so anything
 * below that only decides how much of the scatter is drawn. Three: the
 * line still follows every real turn (a corner deviates by far more than
 * that from the chord across it), and the finest jitter is gone. What
 * the number does NOT do is the heavy lifting — that is the sieve's,
 * which already holds one position in three at this interval.
 */
const TRACK_DRAWING_TOLERANCE_M = 3.0;

/**
 * And how far it may leave the sieved line IN HEIGHT — the second
 * tolerance of track_drawing(), for the elevation profile
 * (13.09.2026: „bau doch mal in die Linieninfoblase das Höhenprofil mit
 * ein"). Douglas–Peucker in the plane keeps a vertex for where the line
 * bends, and a straight road over a crest bends nowhere: the crest would
 * fall out, and the profile drawn from the drawing would show a ramp
 * where there was a hill. So a vertex also stays when its elevation
 * leaves the straight line between its neighbours by more than this.
 *
 * TEN METRES, because that is the receiver's own doubt in height:
 * measured on the walk of 12.09.2026, consecutive sieved positions
 * differ by 3.8 m in the median and by 12 m at the ninetieth percentile
 * — a bump under ten metres is as likely noise as terrain, and the
 * profile is not the place to draw the noise. It costs little: the same
 * walk keeps 205 vertices instead of 189.
 */
const TRACK_DRAWING_TOLERANCE_ELE_M = 10.0;

function tracks_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = (bool) $pdo->query("SHOW TABLES LIKE 'map_tracks'")->fetchColumn();
    }
    return $ready;
}

/** The outing fields — tour, tour_id and excursion_id arrive with migration 0099. */
function tracks_assignment_ready(PDO $pdo): bool
{
    return tracks_ready($pdo) && column_exists($pdo, 'map_tracks', 'tour_id');
}

/**
 * A tour id as the devices mint it, or null. THE TWIN OF point_tour_id()
 * in data.php — the same twelve hex characters, because a track's tour IS
 * a point's tour (migration 0099). Written here a second time only so
 * that tests/tracks.php can load this file without the whole of data.php.
 */
/** Migration 0100: the drawing beside the original. */
function tracks_drawing_ready(PDO $pdo): bool
{
    static $ready = null;
    return $ready ??= column_exists($pdo, 'map_tracks', 'drawing');
}

function track_tour_id(mixed $value): ?string
{
    $id = strtolower(trim((string) ($value ?? '')));
    return preg_match('/^[0-9a-f]{12}$/', $id) === 1 ? $id : null;
}

/**
 * The excursions a track of this user may belong to: the ones he leads,
 * main or co, and the ones he mapped along in — those are the ones his
 * finds carry. An excursion of somebody else that merely ran that day
 * is not among them; a walk is not evidence of taking part.
 *
 * @return int[]
 */
function track_excursion_candidates(PDO $pdo, int $userId): array
{
    require_once __DIR__ . '/excursions.php';
    if (!excursions_ready($pdo)) {
        return [];
    }
    $ids = array_map(static fn(array $e): int => (int) $e['id'], excursions_mine($pdo, $userId));
    if (column_exists($pdo, 'map_points', 'excursion_id')) {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT excursion_id FROM map_points
              WHERE user_id = ? AND excursion_id IS NOT NULL AND deleted_at IS NULL');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[] = (int) $id;
        }
    }
    return array_values(array_unique($ids));
}

/**
 * Which outing a walk belongs to, read off the finds and the excursions.
 *
 * 12.09.2026: „Können die Wegstrecken auch einer Tagestour
 * oder einer Exkursion zugeordnet werden? Oder ist das eher Aufgabe der
 * App?" — the web half's, because only it knows tours and excursions. The
 * app stays a recorder. WHERE THE APP DOES SEND A TOUR it wins (the
 * shell will ask for the tour name when a recording starts, the way the
 * map asks at the first find); this derivation fills the gap until then
 * and wherever the app did not ask.
 *
 * THE TOUR is the one the user's finds of that day carry — the most
 * frequent, where a day happens to hold two. THE EXCURSION is one of his
 * candidates (track_excursion_candidates) whose window overlaps the walk.
 * Both are guesses in the honest sense: right nearly always, and
 * correctable by hand in the track's popup.
 *
 * @return array{tour: ?string, tour_id: ?string, excursion_id: ?int}
 */
function track_assignment_derive(PDO $pdo, int $userId, ?string $startedAt, ?string $endedAt): array
{
    $out = ['tour' => null, 'tour_id' => null, 'excursion_id' => null];
    if ($startedAt === null) {
        return $out;
    }
    if (column_exists($pdo, 'map_points', 'tour_id')) {
        $stmt = $pdo->prepare(
            'SELECT tour_id, tour, COUNT(*) AS n FROM map_points
              WHERE user_id = ? AND deleted_at IS NULL AND tour_id IS NOT NULL
                AND DATE(COALESCE(found_at, created_at)) = DATE(?)
              GROUP BY tour_id, tour ORDER BY n DESC LIMIT 1');
        $stmt->execute([$userId, $startedAt]);
        $row = $stmt->fetch();
        if ($row) {
            $out['tour_id'] = (string) $row['tour_id'];
            $out['tour']    = $row['tour'] !== null && $row['tour'] !== '' ? (string) $row['tour'] : null;
        }
    }
    $candidates = track_excursion_candidates($pdo, $userId);
    if ($candidates !== []) {
        $space = implode(',', array_fill(0, count($candidates), '?'));
        $stmt = $pdo->prepare(
            "SELECT id FROM map_excursions
              WHERE id IN ($space) AND starts_at <= ? AND ends_at >= ?
              ORDER BY starts_at DESC LIMIT 1");
        $stmt->execute(array_merge($candidates, [$endedAt ?? $startedAt, $startedAt]));
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            $out['excursion_id'] = (int) $id;
        }
    }
    return $out;
}

/**
 * Assign a track by hand — the correction to the derivation above.
 *
 * ONLY THE KEYS THAT ARE SENT change; a key sent as null clears. So the
 * popup can set the tour and leave the excursion alone, or the other way
 * round. An excursion must be one of the user's candidates: a walk cannot
 * be filed under an outing he was not on. A tour needs only its id; the
 * name is looked up from his finds when it is not sent along.
 */
/*
 * THE NAME OF A TOUR IS WHAT ITS FINDS SAY (13.09.2026: „ich
 * habe gestern die Tagestour Nummer fünf umbenannt in nur Tagestour, bei
 * der Wegstrecke steht aber noch eine Verbindung zu Tagestour Nummer
 * fünf … dass man beim Umbenennen der Tagestouren auch gleich die
 * Wegstrecken-Verknüpfung mit anpackt").
 *
 * A track carries `tour` beside `tour_id` for the same reason the points
 * do — a listing must not join — and it is a COPY: the identity is the
 * id, the name is a label the points own. Two things keep the copy true.
 * When a find writes its tour name, every track of that id takes it
 * (track_tour_name_follow, called from point_update and point_create);
 * and when a track arrives with a tour the finds already know, it takes
 * THEIR name instead of the app's (track_tour_name_known, in
 * track_store) — the recorder minted „Tagestour #5" before anyone could
 * rename it, and a re-upload must not bring that back.
 *
 * updated_at moves with the name: a device shows the popup from its
 * bucket, and only a moved fingerprint fetches it a fresh copy.
 */

/** The finds' name for a tour, or null when no find carries the id. */
function track_tour_name_known(PDO $pdo, int $userId, string $tourId): ?string
{
    if (!column_exists($pdo, 'map_points', 'tour_id')) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT tour FROM map_points
          WHERE user_id = ? AND tour_id = ? AND tour IS NOT NULL AND deleted_at IS NULL
          ORDER BY updated_at DESC, id DESC LIMIT 1');
    $stmt->execute([$userId, $tourId]);
    $name = $stmt->fetchColumn();
    return $name === false || $name === null || $name === '' ? null : (string) $name;
}

/** Every track of this tour takes the name — the finds have just spoken. */
function track_tour_name_follow(PDO $pdo, int $userId, ?string $tourId, ?string $name): void
{
    if ($tourId === null || $name === null || $name === '' || !tracks_assignment_ready($pdo)) {
        return;
    }
    $pdo->prepare(
        'UPDATE map_tracks SET tour = ?, updated_at = NOW()
          WHERE user_id = ? AND tour_id = ? AND (tour IS NULL OR tour <> ?)'
    )->execute([$name, $userId, $tourId, $name]);
}

function track_assign(PDO $pdo, int $userId, string $clientId, array $data): array
{
    if (!tracks_assignment_ready($pdo)) {
        throw new InvalidArgumentException(t('Die Zuordnung braucht die Migration 0099 — sie ist noch nicht eingespielt.'));
    }
    $set = [];
    $values = [];
    if (array_key_exists('tour_id', $data)) {
        $tourId = track_tour_id($data['tour_id']);
        if ($tourId === null && ($data['tour_id'] ?? null) !== null && (string) $data['tour_id'] !== '') {
            throw new InvalidArgumentException(t('Diese Tour-Kennung ist nicht lesbar.'));
        }
        $tour = trim((string) ($data['tour'] ?? ''));
        if ($tourId !== null && $tour === '') {
            $q = $pdo->prepare('SELECT tour FROM map_points WHERE user_id = ? AND tour_id = ? AND tour IS NOT NULL LIMIT 1');
            $q->execute([$userId, $tourId]);
            $tour = (string) ($q->fetchColumn() ?: '');
        }
        $set[] = 'tour_id = ?'; $values[] = $tourId;
        $set[] = 'tour = ?';    $values[] = $tourId === null ? null : ($tour !== '' ? mb_substr($tour, 0, 120) : null);
    }
    if (array_key_exists('excursion_id', $data)) {
        $exc = (int) ($data['excursion_id'] ?? 0) ?: null;
        if ($exc !== null && !in_array($exc, track_excursion_candidates($pdo, $userId), true)) {
            throw new InvalidArgumentException(t('Zu dieser Exkursion gehörst du nicht.'));
        }
        $set[] = 'excursion_id = ?'; $values[] = $exc;
    }
    if ($set === []) {
        throw new InvalidArgumentException(t('Nichts zuzuordnen — weder Tour noch Exkursion angegeben.'));
    }
    $values[] = $userId;
    $values[] = $clientId;
    $stmt = $pdo->prepare('UPDATE map_tracks SET ' . implode(', ', $set) . ', updated_at = NOW()
                            WHERE user_id = ? AND client_id = ?');
    $stmt->execute($values);
    if ($stmt->rowCount() === 0) {
        // Unchanged values count as zero rows too — then the track must
        // simply exist; track_one() says if it does not.
    }
    return track_one($pdo, $userId, $clientId);
}

/**
 * Distance between two positions in metres — haversine on a sphere.
 *
 * A SPHERE IS ENOUGH HERE, although the earth is an ellipsoid: over the few
 * metres between two positions the difference is millimetres, and it does
 * not accumulate because every step is measured on its own. Vincenty would
 * cost an iteration per step and buy nothing that a GPS receiver could
 * resolve.
 */
function track_distance_m(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $r = 6371008.8;                 // mean earth radius, metres
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
}

/**
 * Read the positions out of the request and sieve them.
 *
 * ACCEPTED IS what an app can produce without contortions: a list of
 * entries, each either [lat, lon, ele, t, acc] or an object with those
 * names. Anything without usable coordinates falls out silently — a single
 * broken entry must not cost a whole walk.
 *
 * TWO LISTS COME BACK. `positions` is every position that is one — the
 * original, what the column stores. `sieved` is what counts as movement,
 * and the distance, the box and the drawing are read from that one alone
 * (the file's header says why the two came apart).
 *
 * @return array{positions: array, sieved: array, distance: float, box: array|null}
 */
function track_positions(mixed $raw): array
{
    if (!is_array($raw)) {
        throw new InvalidArgumentException(t('Die Positionen fehlen oder sind keine Liste.'));
    }
    if (count($raw) > TRACK_MAX_POSITIONS) {
        throw new InvalidArgumentException(
            t('Mehr als %s Positionen — das ist keine Wegstrecke mehr.',
              number_format(TRACK_MAX_POSITIONS, 0, ',', '.')));
    }

    $out      = [];
    $sieved   = [];
    $distance = 0.0;
    $last     = null;
    $box      = null;

    foreach ($raw as $entry) {
        // Both shapes, one reader: the compact array is what travels over
        // the wire, the object is what a hand-written test sends.
        if (is_array($entry) && array_is_list($entry)) {
            [$lat, $lon, $ele, $t, $acc] = array_pad($entry, 5, null);
        } elseif (is_array($entry)) {
            $lat = $entry['lat'] ?? null;
            $lon = $entry['lon'] ?? $entry['lng'] ?? null;
            $ele = $entry['ele'] ?? $entry['elevation_m'] ?? null;
            $t   = $entry['t']   ?? $entry['time'] ?? null;
            $acc = $entry['acc'] ?? $entry['accuracy_m'] ?? null;
        } else {
            continue;
        }

        if (!is_numeric($lat) || !is_numeric($lon)) {
            continue;
        }
        $lat = (float) $lat;
        $lon = (float) $lon;
        if (abs($lat) > 90 || abs($lon) > 180) {
            continue;
        }

        // A POSITION THAT DOUBTS ITSELF IS NO POSITION. Unknown accuracy
        // is NOT bad accuracy, though — an old device may report none, and
        // throwing its track away for that would be the opposite mistake.
        if (is_numeric($acc) && (float) $acc > TRACK_MAX_ACCURACY_M) {
            continue;
        }

        $position = [
            round($lat, 6),
            round($lon, 6),
            is_numeric($ele) ? round((float) $ele, 1) : null,
            is_numeric($t) ? (int) $t : null,
            is_numeric($acc) ? round((float) $acc, 1) : null,
        ];
        // Into the original whatever happens below: the sieve measures
        // from here on, it no longer decides what is kept.
        $out[] = $position;

        if ($last !== null) {
            $step = track_distance_m($last[0], $last[1], $lat, $lon);

            /* THE DEVICE'S OWN DOUBT IS THE THRESHOLD. A step shorter than
               the accuracy of the position that ends it is not measured
               movement — it is the receiver breathing. TRACK_MIN_STEP_M is
               only the floor for a device that claims to be perfect.

               Measured on a synthetic walk of 1000 metres with 5 metres of
               scatter: a fixed threshold of five metres counted 1588,
               because the scatter rode along on every step it did let
               through. With the accuracy as the threshold the standing
               break falls silent completely — there the device itself
               reported 12 metres. */
            $threshold = max(TRACK_MIN_STEP_M, is_numeric($acc) ? (float) $acc : 0.0);
            if ($step < $threshold) {
                continue;
            }
            $distance += $step;
        }

        $sieved[] = $position;
        $last = [$lat, $lon];

        $box = $box === null
            ? ['lat_min' => $lat, 'lat_max' => $lat, 'lon_min' => $lon, 'lon_max' => $lon]
            : ['lat_min' => min($box['lat_min'], $lat), 'lat_max' => max($box['lat_max'], $lat),
               'lon_min' => min($box['lon_min'], $lon), 'lon_max' => max($box['lon_max'], $lon)];
    }

    if ($out === []) {
        throw new InvalidArgumentException(t('Keine brauchbare Position dabei.'));
    }

    return ['positions' => $out, 'sieved' => $sieved, 'distance' => $distance, 'box' => $box];
}

/**
 * A spot against the segment A → B, in a flat local frame — metres east
 * and north of A.
 *
 * Shared by the cut (track_split_between), the search for the segment a
 * tap meant (track_nearest_segment) and the drawing (track_drawing): the
 * same frame in three places would be three chances to disagree about
 * where on a line a point is. Flat is enough — a segment is a few metres,
 * and the earth is not curved at that scale.
 *
 * @return array{u: float, seg: float, dist: float}  u: 0 at A, 1 at B,
 *         clamped to the segment; seg: its length; dist: from the spot to
 *         the nearest point of the segment
 */
function track_project(array $a, array $b, float $lat, float $lon): array
{
    $kx = 111320 * cos(deg2rad((float) $a[0]));
    $ky = 111320;
    $abx = ((float) $b[1] - (float) $a[1]) * $kx;
    $aby = ((float) $b[0] - (float) $a[0]) * $ky;
    $apx = ($lon - (float) $a[1]) * $kx;
    $apy = ($lat - (float) $a[0]) * $ky;
    $len2 = $abx * $abx + $aby * $aby;
    $u = $len2 > 0 ? max(0.0, min(1.0, ($apx * $abx + $apy * $aby) / $len2)) : 0.0;
    $dx = $apx - $u * $abx;
    $dy = $apy - $u * $aby;
    return ['u' => $u, 'seg' => sqrt($len2), 'dist' => sqrt($dx * $dx + $dy * $dy)];
}

/**
 * The line for the map: the sieved positions, simplified.
 *
 * DOUGLAS–PEUCKER, against the SEGMENT rather than the infinite line: a
 * walk that comes back the way it went has its first and last position
 * nearly on top of each other, and the line through two such points is
 * whatever the scatter makes it. The distance to the segment stays honest
 * there — Leaflet's own simplify does it the same way.
 *
 * A STACK, NOT RECURSION: a day is a few thousand sieved positions,
 * TRACK_MAX_POSITIONS allows a hundred thousand, and PHP's call depth is
 * not a limit worth finding out about in the woods. The first and the
 * last position always stay — a drawing begins and ends where the walk
 * did, and the map draws a dot on each.
 *
 * Every vertex of the drawing IS a position of the original: nothing is
 * interpolated, so elevation, time and accuracy ride along unchanged.
 *
 * TWO TOLERANCES, THE PLANE FIRST. Where a stretch is straight enough in
 * the plane, its elevations are held against the straight line between
 * the two ends (interpolated by the position along the chord), and the
 * vertex that leaves it by more than $eleTolerance stays — the crest on
 * a straight road, see TRACK_DRAWING_TOLERANCE_ELE_M. A vertex without
 * an elevation cannot be such a crest and is judged in the plane alone.
 */
function track_drawing(array $sieved, float $tolerance = TRACK_DRAWING_TOLERANCE_M,
                       float $eleTolerance = TRACK_DRAWING_TOLERANCE_ELE_M): array
{
    $n = count($sieved);
    if ($n < 3) {
        return array_values($sieved);
    }
    $keep = array_fill(0, $n, false);
    $keep[0] = $keep[$n - 1] = true;
    $stack = [[0, $n - 1]];
    while ($stack !== []) {
        [$s, $e] = array_pop($stack);
        if ($e - $s < 2) {
            continue;
        }
        $a = $sieved[$s];
        $b = $sieved[$e];
        $eleA = is_numeric($a[2] ?? null) ? (float) $a[2] : null;
        $eleB = is_numeric($b[2] ?? null) ? (float) $b[2] : null;
        $best  = -1;
        $bestD = -1.0;
        $high  = -1;
        $highD = -1.0;
        for ($i = $s + 1; $i < $e; $i++) {
            $p = track_project($a, $b, (float) $sieved[$i][0], (float) $sieved[$i][1]);
            if ($p['dist'] > $bestD) {
                $bestD = $p['dist'];
                $best  = $i;
            }
            if ($eleA !== null && $eleB !== null && is_numeric($sieved[$i][2] ?? null)) {
                $off = abs((float) $sieved[$i][2] - ($eleA + $p['u'] * ($eleB - $eleA)));
                if ($off > $highD) {
                    $highD = $off;
                    $high  = $i;
                }
            }
        }
        if ($bestD > $tolerance) {
            $keep[$best] = true;
            $stack[] = [$s, $best];
            $stack[] = [$best, $e];
        } elseif ($highD > $eleTolerance) {
            $keep[$high] = true;
            $stack[] = [$s, $high];
            $stack[] = [$high, $e];
        }
    }
    $out = [];
    foreach ($sieved as $i => $position) {
        if ($keep[$i]) {
            $out[] = $position;
        }
    }
    return $out;
}

/**
 * Which segment of a line a spot is nearest to — the index of its first
 * position, or -1 for a line too short to have one.
 *
 * THE SERVER SEARCHES, NOT THE BROWSER (12.09.2026): the map draws the
 * simplified copy and the cut goes into the original, so an index from
 * over there would point into the wrong array. The tapped spot is the one
 * thing both sides mean the same by. In metres, because "nearest" on a
 * map is a distance and not a count of pixels — on an out-and-back walk
 * the two directions lie a few metres apart, and the nearer one is the
 * one that was tapped.
 */
function track_nearest_segment(array $positions, float $lat, float $lon): int
{
    $n = count($positions);
    if ($n < 2) {
        return -1;
    }
    $best  = -1;
    $bestD = INF;
    for ($i = 0; $i < $n - 1; $i++) {
        $d = track_project($positions[$i], $positions[$i + 1], $lat, $lon)['dist'];
        if ($d < $bestD) {
            $bestD = $d;
            $best  = $i;
        }
    }
    return $best;
}

/** The moment of a position as a database timestamp, or null. */
function track_moment(?int $ms): ?string
{
    if ($ms === null || $ms <= 0) {
        return null;
    }
    return date('Y-m-d H:i:s', (int) ($ms / 1000));
}

/**
 * Store a track, or replace the one already standing under this client_id.
 *
 * REPLACING INSTEAD OF REFUSING is what makes the upload repeatable: the
 * app may send the same walk after a lost connection, and it may send it
 * again once it has grown. Whoever wants to append sends the whole thing —
 * a few thousand positions are a hundred kilobytes, and that is cheaper
 * than a second code path that has to stitch two halves together.
 */
function track_store(PDO $pdo, int $userId, array $data, string $device = ''): array
{
    $clientId = trim((string) ($data['client_id'] ?? ''));
    if ($clientId === '' || mb_strlen($clientId) > 36) {
        throw new InvalidArgumentException(t('Ohne client_id lässt sich die Strecke nicht wiedererkennen.'));
    }

    $read = track_positions($data['positions'] ?? null);
    $name = trim((string) ($data['name'] ?? ''));

    // The moments are the original's: a recording begins with its first
    // fix and ends with its last, whether or not the walker was moving
    // at either — the ten minutes at the car belong to the outing.
    $first = $read['positions'][0][3] ?? null;
    $last  = $read['positions'][array_key_last($read['positions'])][3] ?? null;

    $row = [
        'user_id'     => $userId,
        'client_id'   => $clientId,
        'name'        => $name !== '' ? mb_substr($name, 0, 200) : null,
        'device'      => $device !== '' ? mb_substr($device, 0, 100) : null,
        'started_at'  => track_moment($first),
        'ended_at'    => track_moment($last),
        'point_count' => count($read['positions']),
        'distance_m'  => (int) round($read['distance']),
        'lat_min'     => $read['box']['lat_min'],
        'lat_max'     => $read['box']['lat_max'],
        'lon_min'     => $read['box']['lon_min'],
        'lon_max'     => $read['box']['lon_max'],
        'positions'   => json_encode($read['positions'], JSON_UNESCAPED_UNICODE),
    ];
    // The drawing (0100) — built here, at upload, and again on every
    // re-upload; only once the columns exist, the code goes live first.
    $drawn = '';
    if (tracks_drawing_ready($pdo)) {
        $drawing = track_drawing($read['sieved']);
        $row['drawing']       = json_encode($drawing, JSON_UNESCAPED_UNICODE);
        $row['drawing_count'] = count($drawing);
        $drawn = ', drawing = VALUES(drawing), drawing_count = VALUES(drawing_count)';
    }

    /* THE OUTING (migration 0099). What the sender says wins — tour and
       tour_id from the app, excursion_id from the split, which hands the
       original's fields to both halves. What it does not say is derived
       from the finds and the excursions. And on a RE-UPLOAD the two are
       told apart: a sent value replaces, a derived one only fills a hole,
       so a tour somebody corrected by hand survives the app sending the
       same walk again. */
    $assignment = '';
    if (tracks_assignment_ready($pdo)) {
        $sentTourId = track_tour_id($data['tour_id'] ?? null);
        $sentTour   = trim((string) ($data['tour'] ?? ''));
        $sentExc    = (int) ($data['excursion_id'] ?? 0) ?: null;
        $derived    = ($sentTourId === null || $sentExc === null)
            ? track_assignment_derive($pdo, $userId, $row['started_at'], $row['ended_at'])
            : ['tour' => null, 'tour_id' => null, 'excursion_id' => null];
        $row['tour_id']      = $sentTourId ?? $derived['tour_id'];
        // The finds' name for a sent tour, the app's only where no find
        // knows the id yet — see track_tour_name_known.
        $row['tour']         = $sentTourId !== null
            ? (track_tour_name_known($pdo, $userId, $sentTourId)
                ?? ($sentTour !== '' ? mb_substr($sentTour, 0, 120) : null))
            : $derived['tour'];
        $row['excursion_id'] = $sentExc ?? $derived['excursion_id'];
        $assignment = ($sentTourId !== null
                ? ', tour = VALUES(tour), tour_id = VALUES(tour_id)'
                : ', tour = COALESCE(tour, VALUES(tour)), tour_id = COALESCE(tour_id, VALUES(tour_id))')
            . ($sentExc !== null
                ? ', excursion_id = VALUES(excursion_id)'
                : ', excursion_id = COALESCE(excursion_id, VALUES(excursion_id))');
    }

    /* ON DUPLICATE KEY, not "select and then decide": between the question
       and the answer a second upload of the same walk can slip through, and
       the unique key would then throw where nothing is wrong. */
    $columns = array_keys($row);
    $pdo->prepare(
        'INSERT INTO map_tracks (' . implode(', ', $columns) . ', created_at, updated_at)
         VALUES (' . implode(', ', array_map(static fn($c) => ':' . $c, $columns)) . ', NOW(), NOW())
         ON DUPLICATE KEY UPDATE
             name = VALUES(name), device = VALUES(device),
             started_at = VALUES(started_at), ended_at = VALUES(ended_at),
             point_count = VALUES(point_count), distance_m = VALUES(distance_m),
             lat_min = VALUES(lat_min), lat_max = VALUES(lat_max),
             lon_min = VALUES(lon_min), lon_max = VALUES(lon_max),
             positions = VALUES(positions)' . $drawn . $assignment . ', updated_at = NOW()'
    )->execute($row);

    return track_one($pdo, $userId, $clientId);
}

/**
 * Cut a list of positions in two — the pure half of track_split().
 *
 * THE CUT POSITION BELONGS TO BOTH HALVES. A walk cut at a point should
 * still look like one line on the map, and the halves should later be able
 * to meet again; a gap of one step between them would be neither. Both
 * halves must keep at least two positions — one position is not a line,
 * and the sieve would refuse to store it anyway.
 *
 * @param array $positions  as stored: [[lat, lon, ele, t, acc], …]
 * @param int   $at         index of the cut position
 * @return array{0: array, 1: array}  head and tail
 */
function track_split_positions(array $positions, int $at): array
{
    $n = count($positions);
    if ($n < 4 || $at < 1 || $at > $n - 2) {
        throw new InvalidArgumentException(t('Zum Teilen braucht es eine Stelle zwischen Anfang und Ende.'));
    }
    return [
        array_slice($positions, 0, $at + 1),
        array_slice($positions, $at),
    ];
}

/**
 * Cut BETWEEN two positions — at a spot on the segment after $at.
 *
 * 12.09.2026: „Ich kann die Strecke gar nicht zwischen zwei
 * Wegpunkten trennen, sondern immer nur an einem Wegpunkt — kannst Du das
 * so machen, dass ich auch zwischendrin trennen kann?" The tapped spot is
 * projected onto the segment, so the cut lies ON the line, not beside
 * it; time and elevation are interpolated along it.
 *
 * SNAPPED TO AN END WHEN IT IS AS GOOD AS ONE. Closer than the floor to
 * the segment's start, or closer than that position's own accuracy to
 * its end, and the cut IS that position (track_split_positions): nobody
 * sees the difference between a cut and a cut three metres on, and a
 * vertex standing on top of another would only be a step the sieve
 * measures as nothing. (Until 0100 this was also what kept the halves
 * touching — the sieve then decided what was STORED, and a cut two
 * metres before a position would have lost that position on the tail.)
 *
 * @param float $lat spot to cut at, near the segment $at → $at + 1
 * @return array{0: array, 1: array}
 */
function track_split_between(array $positions, int $at, float $lat, float $lon): array
{
    $n = count($positions);
    if ($n < 3 || $at < 0 || $at > $n - 2) {
        throw new InvalidArgumentException(t('Zum Teilen braucht es eine Stelle zwischen Anfang und Ende.'));
    }
    $a = $positions[$at];
    $b = $positions[$at + 1];

    ['u' => $u, 'seg' => $seg] = track_project($a, $b, $lat, $lon);

    $toA = $u * $seg;
    $toB = (1 - $u) * $seg;
    $accB = is_numeric($b[4] ?? null) ? (float) $b[4] : 0.0;
    if ($toA < TRACK_MIN_STEP_M) {
        return track_split_positions($positions, $at);
    }
    if ($toB < max(TRACK_MIN_STEP_M, $accB)) {
        return track_split_positions($positions, $at + 1);
    }

    $cut = [
        round((float) $a[0] + $u * ((float) $b[0] - (float) $a[0]), 6),
        round((float) $a[1] + $u * ((float) $b[1] - (float) $a[1]), 6),
        is_numeric($a[2] ?? null) && is_numeric($b[2] ?? null)
            ? round((float) $a[2] + $u * ((float) $b[2] - (float) $a[2]), 1) : null,
        is_numeric($a[3] ?? null) && is_numeric($b[3] ?? null)
            ? (int) round((float) $a[3] + $u * ((float) $b[3] - (float) $a[3])) : null,
        // No accuracy of its own: the spot was chosen by hand, and the
        // sieve reads a missing figure as „nothing to doubt".
        null,
    ];
    return [
        array_merge(array_slice($positions, 0, $at + 1), [$cut]),
        array_merge([$cut], array_slice($positions, $at + 1)),
    ];
}

/** A client id of the same shape the devices mint — a UUID, 36 characters. */
function track_client_id_mint(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/**
 * Split a track at one of its positions into two tracks.
 *
 * 12.09.2026: „Mir ist das schon oft passiert, dass ich
 * vergessen habe, die Aufzeichnung auszuschalten — dann ist der
 * Nachhauseweg mit dem Auto mit drauf. Ich hätte gerne die Wegstrecken
 * bearbeitbar: Teilen an einem Punkt und dann Löschen." Cutting twice and
 * deleting the middle answers the drive between two sites the same way.
 *
 * BOTH HALVES GET FRESH IDS AND THE ORIGINAL GOES. Keeping the original id
 * on the head would be the obvious economy, and it is a trap: the app may
 * send the whole walk again under that id — after a lost connection, or
 * because somebody presses "hochladen" twice — and the upload REPLACES.
 * The car ride would be back, silently, over a head somebody had just
 * trimmed. With fresh ids a late re-send creates the original BESIDE the
 * halves: a duplicate one can see and delete, never a loss one cannot.
 *
 * THROUGH track_store(), NOT A HAND-WRITTEN INSERT: the halves go through
 * the same sieve and the same derivation as an upload, so distance, box,
 * times and the drawing come out the way every other track's do.
 *
 * @return array{tracks: array}  head and tail, as the API hands tracks out
 */
function track_split(PDO $pdo, int $userId, string $clientId, int $at, ?array $spot = null): array
{
    $stmt = $pdo->prepare('SELECT * FROM map_tracks WHERE user_id = ? AND client_id = ?');
    $stmt->execute([$userId, $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new InvalidArgumentException(t('Diese Wegstrecke gibt es nicht.'));
    }
    $positions = json_decode((string) $row['positions'], true) ?: [];
    /* WITH A SPOT the server finds the segment itself and cuts between its
       two positions (track_split_between): the map draws the simplified
       copy, the cut goes into the original, and an index from over there
       would point into the wrong array — track_nearest_segment says so
       at length. Without one, at the position $at itself: the older form,
       and the only one in which an index means anything. */
    if ($spot !== null && is_numeric($spot[0] ?? null) && is_numeric($spot[1] ?? null)) {
        $at = track_nearest_segment($positions, (float) $spot[0], (float) $spot[1]);
        [$head, $tail] = track_split_between($positions, $at, (float) $spot[0], (float) $spot[1]);
    } else {
        [$head, $tail] = track_split_positions($positions, $at);
    }

    $pdo->beginTransaction();
    try {
        $device = (string) ($row['device'] ?? '');
        $name   = (string) ($row['name'] ?? '');
        // The outing goes with both halves — cutting a walk does not
        // change which outing it was.
        $outing = [
            'tour'         => $row['tour'] ?? '',
            'tour_id'      => $row['tour_id'] ?? null,
            'excursion_id' => $row['excursion_id'] ?? null,
        ];
        $a = track_store($pdo, $userId,
            ['client_id' => track_client_id_mint(), 'name' => $name, 'positions' => $head] + $outing, $device);
        $b = track_store($pdo, $userId,
            ['client_id' => track_client_id_mint(), 'name' => $name, 'positions' => $tail] + $outing, $device);
        track_remove($pdo, $userId, $clientId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['tracks' => [$a, $b]];
}

/** One track including its positions, addressed by its device-minted id. */
function track_one(PDO $pdo, int $userId, string $clientId): array
{
    $stmt = $pdo->prepare('SELECT * FROM map_tracks WHERE user_id = ? AND client_id = ?');
    $stmt->execute([$userId, $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new InvalidArgumentException(t('Diese Wegstrecke gibt es nicht.'));
    }
    return track_shape($row, true);
}

/**
 * The list — WITHOUT the positions.
 *
 * That is the whole reason the derived numbers are their own columns: an
 * overview of a season would otherwise drag a few megabytes of coordinates
 * over the wire to print "12,4 km".
 */
function tracks_list(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, client_id, name, device, started_at, ended_at, point_count,
                distance_m, lat_min, lat_max, lon_min, lon_max, created_at, updated_at'
        . (tracks_assignment_ready($pdo) ? ', tour, tour_id, excursion_id' : '') . '
           FROM map_tracks WHERE user_id = ?
          ORDER BY started_at DESC, id DESC'
    );
    $stmt->execute([$userId]);
    return array_map(static fn(array $r): array => track_shape($r, false), $stmt->fetchAll());
}

/**
 * One row as the API hands it out.
 *
 * TWO LINES, NEVER BOTH: the buckets carry the DRAWING (what the map
 * shows), GET ?id= and the upload's answer carry the ORIGINAL. A row from
 * before 0100, or one whose drawing has not been built yet, hands its
 * positions out as the drawing — the picture is then what it was before
 * that migration, only heavier.
 */
function track_shape(array $row, bool $withPositions, bool $withDrawing = false): array
{
    $out = [
        'id'          => (int) $row['id'],
        'client_id'   => (string) $row['client_id'],
        'name'        => $row['name'],
        'device'      => $row['device'],
        'started_at'  => $row['started_at'],
        'ended_at'    => $row['ended_at'],
        'point_count' => (int) $row['point_count'],
        'distance_m'  => $row['distance_m'] === null ? null : (int) $row['distance_m'],
        'box'         => $row['lat_min'] === null ? null : [
            'lat_min' => (float) $row['lat_min'], 'lat_max' => (float) $row['lat_max'],
            'lon_min' => (float) $row['lon_min'], 'lon_max' => (float) $row['lon_max'],
        ],
        'created_at'  => $row['created_at'],
        'updated_at'  => $row['updated_at'],
    ];
    // The outing (0099) — only once the columns exist; before that the
    // shape stays what version 1 of the store expects.
    if (array_key_exists('tour_id', $row)) {
        $out['tour']         = $row['tour'] ?? null;
        $out['tour_id']      = $row['tour_id'] ?? null;
        $out['excursion_id'] = $row['excursion_id'] === null ? null : (int) $row['excursion_id'];
    }
    // A foreign walk (excursion or container scope): whose, and through
    // which shared list it arrived. Own rows never carry these keys — the
    // frozen key set in tests/trackbuckets.php is theirs.
    if (array_key_exists('owner_id', $row)) {
        $out['owner_id'] = (int) $row['owner_id'];
        $out['owner']    = (string) ($row['owner'] ?? '');
    }
    if (array_key_exists('via_list', $row)) {
        $out['via_list'] = $row['via_list'] === null ? null : (int) $row['via_list'];
    }
    if ($withPositions) {
        $out['positions'] = json_decode((string) $row['positions'], true) ?: [];
    }
    if ($withDrawing) {
        $out['drawing'] = json_decode((string) ($row['drawing'] ?? $row['positions']), true) ?: [];
    }
    return $out;
}

/**
 * Build the drawing of a row that has none — the stock from before 0100.
 *
 * On first delivery rather than through a tool: SQL cannot simplify a
 * line, and the stock is six rows. Idempotent on a row the sieve has
 * already been over — every step it let through once, it lets through
 * again — and it leaves updated_at ALONE: the devices' fingerprints must
 * not move for a column they never see by name (the shape change is
 * VERSION 3 of trackstore.js). A row with nothing usable in it gets an
 * empty drawing rather than an error on every delivery.
 */
function track_drawing_build(PDO $pdo, array &$row): void
{
    try {
        $read    = track_positions(json_decode((string) $row['positions'], true));
        $drawing = track_drawing($read['sieved']);
    } catch (InvalidArgumentException) {
        $drawing = [];
    }
    $row['drawing']       = json_encode($drawing, JSON_UNESCAPED_UNICODE);
    $row['drawing_count'] = count($drawing);
    $pdo->prepare('UPDATE map_tracks SET drawing = ?, drawing_count = ? WHERE id = ?')
        ->execute([$row['drawing'], $row['drawing_count'], (int) $row['id']]);
}

/** Delete a track. It is gone — a track is a recording, not a find. */
function track_remove(PDO $pdo, int $userId, string $clientId): void
{
    $stmt = $pdo->prepare('DELETE FROM map_tracks WHERE user_id = ? AND client_id = ?');
    $stmt->execute([$userId, $clientId]);
    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException(t('Diese Wegstrecke gibt es nicht.'));
    }
}

// ---------------------------------------------------------------------------
// The buckets of the tracks — the same idea as the points', on their own table
// ---------------------------------------------------------------------------

/*
 * 11.09.2026, asking whether hundreds of recorded walks would
 * burst the device: „Mir schwebt vor, dass man Wegstrecken pauschal an-
 * oder ausschalten kann … die Frage ist, ob das irgendwann den Speicher
 * sprengt." Measured through the sieve above: a sieved walk costs about
 * 8 kB per kilometre, so three hundred ten-kilometre walks are 24 MB —
 * the offline tiles of one area weigh more. What has to be avoided is not
 * the storing but the FETCHING OF EVERYTHING ON EVERY START, and that is
 * exactly what the buckets of includes/bucket.php solve for the points.
 *
 * SO THE TRACKS GET THE SAME BUCKETS: a month per bucket, a fingerprint
 * per bucket, whole buckets only, the fingerprints read before the rows.
 * Every reason stands over there and is not repeated here; what differs
 * is written down at the spot.
 *
 * THE KEY IS THE MONTH THE WALK BEGAN, falling back to the upload —
 * the same shape of rule as the points' found_at/created_at. A track
 * changes in exactly two ways: a re-upload under the same client_id
 * (updated_at moves) and a deletion (the row goes, COUNT drops). Both
 * are in the print; nothing else can touch a track.
 */

require_once __DIR__ . '/bucket.php';   // bucket_key_valid(), nothing else

/** What a track's bucket is derived from — see bucket_expression(). */
function track_bucket_expression(string $table = ''): string
{
    $p = $table === '' ? '' : $table . '.';
    return "DATE_FORMAT(COALESCE({$p}started_at, {$p}created_at), '%Y-%m')";
}

/**
 * The same key for an already loaded row — the rule thus exists twice,
 * for the same reason as bucket_key_of(): the rows are handed back
 * bundled without a query per bucket. tests/trackbuckets.php holds the
 * two against each other on real rows.
 */
function track_bucket_key_of(array $row): string
{
    $time = $row['started_at'] ?? null;
    if ($time === null || $time === '') {
        $time = $row['created_at'] ?? '';
    }
    return substr((string) $time, 0, 7);
}

/**
 * How many VERTICES one request delivers at most — not how many tracks.
 *
 * The points' limit counts rows because every row weighs the same. A
 * track weighs what it holds: a ten-minute stroll is thirty positions, a
 * day in the hills is four thousand. Counted in tracks, one draw could be
 * a few kilobytes or twenty megabytes. Sixty thousand positions are
 * around 2.7 MB — one bucket of a busy month, or a dozen quiet ones.
 * NEVER CUT A BUCKET OPEN holds here too: the first requested bucket is
 * always delivered whole, however heavy it is.
 *
 * Counted in what is DELIVERED — the drawing's vertices (drawing_count),
 * not the original's positions: since 0100 those are two numbers, and a
 * limit on the one the wire never carries would only make the draws
 * smaller than they need to be.
 */
const TRACK_BUCKET_MAX_POSITIONS = 60000;

/**
 * Fingerprint, track count and position count per bucket.
 *
 * THE SAME THREE FIGURES as bucket_status(), and the same reason for
 * them (its comment says why not one GROUP_CONCAT). updated_at instead
 * of touched_at: a track knows no derivation that would bypass it, so
 * the ordinary column is enough. The position sum rides along for the
 * limit above — it costs nothing, point_count is a column.
 *
 * @return array{finger: array<string,string>, counts: array<string,int>, positions: array<string,int>}
 */
function tracks_bucket_status(PDO $pdo, int $userId): array
{
    return tracks_bucket_status_scoped($pdo, track_scope_own($userId));
}

/*
 * WHOSE TRACKS A REQUEST IS ABOUT (step two, 12.09.2026: „Strecken der
 * anderen in Exkursions- und Freigabe-Eimern"). A scope is a WHERE clause
 * over the alias t plus its parameters, and the two bucket functions take
 * one instead of a user id — the own stock, an excursion, a container are
 * then the same query three times with a different sentence in it.
 *
 *   OWN         t.user_id = me
 *   EXCURSION   t.excursion_id = E and not mine — the walks of the other
 *               leaders and participants; my own lie in my own buckets.
 *   CONTAINER   not mine, and the walk's outing has finds in a list the
 *               container shares — „der Weg des Ausflugs, dessen Funde du
 *               sehen darfst". A track without a tour travels nowhere.
 *
 * Foreign rows carry the recorder's name (owner, owner_id) and, in a
 * container, via_list: the shared list through which the walk arrived,
 * so the device can tie it to the checkmark of that list.
 *
 * @return array{where: string, params: array, foreign: bool, via: string}
 */
function track_scope_own(int $userId): array
{
    return ['where' => 't.user_id = ?', 'params' => [$userId], 'foreign' => false, 'via' => ''];
}

function track_scope_excursion(int $excursionId, int $askerId): array
{
    return ['where' => 't.excursion_id = ? AND t.user_id <> ?', 'params' => [$excursionId, $askerId],
            'foreign' => true, 'via' => ''];
}

function track_scope_container(PDO $pdo, int $containerId, int $askerId): ?array
{
    require_once __DIR__ . '/container.php';
    if (!column_exists($pdo, 'map_points', 'tour_id')) {
        return null;
    }
    $lists = container_list_ids($pdo, $containerId);
    if ($lists === []) {
        return null;
    }
    $space = implode(',', array_fill(0, count($lists), '?'));
    $tour  = "SELECT pl.list_id FROM map_points p JOIN map_point_lists pl ON pl.point_id = p.id
               WHERE p.user_id = t.user_id AND p.tour_id = t.tour_id AND p.deleted_at IS NULL
                 AND pl.list_id IN ($space)";
    return [
        'where'   => "t.user_id <> ? AND t.tour_id IS NOT NULL AND EXISTS ($tour)",
        'params'  => array_merge([$askerId], $lists),
        'foreign' => true,
        // The same subquery once more, as a column: MySQL cannot reuse
        // the EXISTS, and the list is what the device ties the walk to.
        'via'     => ", ($tour LIMIT 1) AS via_list",
        'via_params' => $lists,
    ];
}

/**
 * May this user see the tracks of an excursion — as a leader, or as a
 * registered participant?
 *
 * BY THE GUEST LIST, NOT BY OWNING A STAMPED FIND (security review,
 * 23.09.2026). The old test was „has a point with this excursion id", and
 * the id on a point could be set by the request itself: one forged point
 * opened every participant's GPS track. excursion_of_participant() asks
 * the leader tables and the guest rows under the account — the same door
 * mapping along goes through. Measured before the switch: every find
 * stamped with an excursion belonged to a leader, the guest account, or a
 * user with a guest row, so no rightful viewer loses a track.
 */
function track_excursion_visible(PDO $pdo, int $userId, int $excursionId): bool
{
    require_once __DIR__ . '/excursions.php';
    try {
        excursion_of_participant($pdo, $userId, $excursionId);
        return true;
    } catch (InvalidArgumentException) {
        return false;
    }
}

function tracks_bucket_status_scoped(PDO $pdo, array $scope): array
{
    if (!tracks_ready($pdo)) {
        return ['finger' => [], 'counts' => [], 'positions' => []];
    }
    // What the wire carries: the drawing's vertices — for a row not yet
    // built (NULL drawing, the stock before 0100) its positions, which is
    // what it will hand out until then.
    $delivered = tracks_drawing_ready($pdo)
        ? 'IF(t.drawing IS NULL, t.point_count, t.drawing_count)'
        : 't.point_count';
    $stmt = $pdo->prepare(
        'SELECT ' . track_bucket_expression('t') . ' AS bucket_key,
                COUNT(*) AS total,
                COALESCE(SUM(' . $delivered . '), 0) AS positions,
                SUBSTRING(MD5(CONCAT_WS("|",
                    COUNT(*),
                    MAX(t.updated_at),
                    COALESCE(SUM(CRC32(CONCAT_WS(":", t.id, t.updated_at))), 0)
                )), 1, 12) AS finger
           FROM map_tracks t
          WHERE ' . $scope['where'] . '
          GROUP BY bucket_key
          ORDER BY bucket_key DESC'
    );
    $stmt->execute($scope['params']);

    $finger = [];
    $counts = [];
    $positions = [];
    foreach ($stmt as $z) {
        // created_at is NOT NULL, so a key is always there — the guard is
        // the same belt as in bucket_status(), for the same reason.
        if ($z['bucket_key'] === null || $z['bucket_key'] === '') {
            continue;
        }
        $k = (string) $z['bucket_key'];
        $finger[$k]    = (string) $z['finger'];
        $counts[$k]    = (int) $z['total'];
        $positions[$k] = (int) $z['positions'];
    }
    return ['finger' => $finger, 'counts' => $counts, 'positions' => $positions];
}

/**
 * The tracks of the requested buckets — whole buckets, drawings included
 * (the simplified line the map shows; the original stays behind GET ?id=).
 *
 * THE FINGERPRINTS ARE READ BEFORE THE ROWS, and bucket_points() explains
 * at length why the other order silently loses data. The answer is
 * bundled by bucket, so the device never assigns a track to a month
 * itself.
 *
 * @param string[] $keys
 * @return array{buckets: array<string,array{finger:string,tracks:array}>, open: string[]}
 */
function tracks_bucket_get(PDO $pdo, int $userId, array $keys): array
{
    return tracks_bucket_get_scoped($pdo, track_scope_own($userId), $keys);
}

function tracks_bucket_get_scoped(PDO $pdo, array $scope, array $keys): array
{
    $wanted = array_values(array_unique(array_filter($keys, 'bucket_key_valid')));
    if (!tracks_ready($pdo) || $wanted === []) {
        return ['buckets' => [], 'open' => []];
    }

    $status = tracks_bucket_status_scoped($pdo, $scope);

    $take = [];
    $open = [];
    $sum  = 0;
    foreach ($wanted as $k) {
        // A bucket that does not exist (any more) is still reported —
        // empty, so the device throws its copy away.
        $n = $status['positions'][$k] ?? 0;
        if ($take !== [] && $sum + $n > TRACK_BUCKET_MAX_POSITIONS) {
            $open[] = $k;
            continue;
        }
        $take[] = $k;
        $sum   += $n;
    }

    $delivered = [];
    foreach ($take as $k) {
        $delivered[$k] = ['finger' => $status['finger'][$k] ?? 'empty', 'tracks' => []];
    }

    if ($take !== []) {
        $space = implode(',', array_fill(0, count($take), '?'));
        // Foreign walks carry their recorder's name — a line on the map
        // that says nothing about whose it is would be a riddle.
        $owner = $scope['foreign']
            ? ', t.user_id AS owner_id, ' . sql_display_name('u') . ' AS owner' . ($scope['via'] ?? '')
            : '';
        $join  = $scope['foreign'] ? ' JOIN map_users u ON u.id = t.user_id' : '';
        $stmt  = $pdo->prepare(
            'SELECT t.*' . $owner . '
               FROM map_tracks t' . $join . '
              WHERE ' . $scope['where'] . ' AND ' . track_bucket_expression('t') . " IN ($space)
              ORDER BY t.started_at DESC, t.id DESC"
        );
        // The via subquery sits in the SELECT list and is bound FIRST —
        // MySQL numbers placeholders in text order.
        $stmt->execute(array_merge($scope['via_params'] ?? [], $scope['params'], $take));
        $drawingReady = tracks_drawing_ready($pdo);
        foreach ($stmt as $row) {
            $k = track_bucket_key_of($row);
            // Cannot occur — see the same line in bucket_points(). Better
            // nowhere than into the wrong bucket.
            if (!isset($delivered[$k])) {
                continue;
            }
            // The stock from before 0100 gets its drawing now, once.
            if ($drawingReady && $row['drawing'] === null) {
                track_drawing_build($pdo, $row);
            }
            $delivered[$k]['tracks'][] = track_shape($row, false, true);
        }
    }

    return ['buckets' => $delivered, 'open' => $open];
}
