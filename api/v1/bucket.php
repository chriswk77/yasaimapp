<?php
/**
 * Time buckets for the sync.
 *
 *   GET bucket.php                       the fingerprints of all buckets
 *   GET bucket.php?get=2026-08,2026-07 the points of these buckets
 *   GET bucket.php?tracks=1[&get=…]     the same for the recorded tracks
 *
 * What for and why whole instead of in single parts: see includes/bucket.php.
 *
 * THE FETCH TAKES A LIST, not one bucket. Ten years are a hundred and twenty
 * buckets; picked up one by one that would be a hundred and twenty requests,
 * and on a mobile connection each one costs more in waiting time than in
 * bytes.
 *
 * READ ONLY. Hence no CSRF and no POST — _start.php demands the token only
 * for writing methods anyway.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/bucket.php';

$userId = (int) $me['id'];

if ($method !== 'GET') {
    json_error(t('Methode nicht unterstützt.'), 405);
}

if (!bucket_ready($pdo)) {
    // The code is on the server before the migration — that is the order of
    // deployment. In the window in between there is no stand and thus no
    // fingerprint. An empty directory would be the wrong answer: the device
    // would take the stock for empty and throw its store away.
    json_error(t('Die Eimer stehen noch nicht bereit — Migration 0038 fehlt.'), 503);
}

$get = trim((string) ($_GET['get'] ?? ''));

/* WITH ?excursion=ID come the buckets of one excursion — its guests'
   finds and those of the other leaders, everything except one's own.
   
   THE SAME TWO FORMS as everywhere here: without ?get the fingerprints
   with their counts, with ?get the points. And the same reason for
   existing as the containers' — an excursion's finds used to ride along
   in the document on every page load, which is fine for six of them and
   is not for a season of them. On the device they now lie like every
   other point: in the store, reconciled by fingerprint, and READABLE
   WITHOUT RECEPTION. That last one is why the maintainer asked for it.
   
   THE RIGHT IS THE EXCURSION'S, not the point's owner: whoever leads it —
   main or co — may read every find in it. excursion_bucket_points asks
   that once for the whole request. */
$excursionId = (int) ($_GET['excursion'] ?? 0);
if ($excursionId > 0 && empty($_GET['tracks'])) {
    require_once __DIR__ . '/../../includes/excursions.php';

    if (!excursions_ready($pdo)) {
        json_error(t('Exkursionen stehen noch nicht bereit — die Migration fehlt.'), 503);
    }
    // Leadership is the boundary. Not even the fingerprints for anybody
    // else: they alone would say how much there is to see.
    try {
        excursion_of_leader($pdo, $userId, $excursionId);
    } catch (InvalidArgumentException) {
        json_error(t('Kein Zugriff auf diese Exkursion.'), 403);
    }

    if ($get === '') {
        $status = excursion_bucket_status($pdo, $excursionId, $userId);
        json_response([
            'buckets'    => $status['finger'],
            'counts'     => $status['counts'],
            'touched_at' => now(),
        ]);
    }

    $keys     = array_slice(array_filter(array_map('trim', explode(',', $get))), 0, 240);
    $response = excursion_bucket_points($pdo, $userId, $excursionId, $keys);

    if ($response['buckets'] === [] && $response['open'] === []) {
        json_error(t('Keine brauchbaren Eimerschlüssel angegeben. Erwartet wird "2026-08".'), 422);
    }
    json_response($response);
}

/* WITH ?tracks=1 come the buckets of the RECORDED TRACKS (11.09.2026) —
   the same two forms, the same rules, on map_tracks instead of
   map_points. Why the tracks get buckets at all stands in
   includes/tracks.php above tracks_bucket_status(). Nothing here is
   shared with anybody: a track is its recorder's alone. */
if (!empty($_GET['tracks'])) {
    require_once __DIR__ . '/../../includes/tracks.php';

    if (!tracks_ready($pdo)) {
        json_error(t('Wegstrecken brauchen die Migration 0084 — sie ist noch nicht eingespielt.'), 503);
    }

    /* AND SINCE STEP TWO (12.09.2026) THE OTHERS' TRACKS TOO: with
       ?excursion=ID the walks of the other leaders and participants,
       with ?container=ID the walks whose outing has finds in a shared
       list. The same boundaries as for the points — leading or having
       mapped along, being a member — and the same two forms. The
       excursion and container branches above step aside for ?tracks=1
       so this one can read the same parameters. */
    $trackExcursion = (int) ($_GET['excursion'] ?? 0);
    $trackContainer = (int) ($_GET['container'] ?? 0);
    if ($trackExcursion > 0) {
        if (!track_excursion_visible($pdo, $userId, $trackExcursion)) {
            json_error(t('Kein Zugriff auf diese Exkursion.'), 403);
        }
        $scope = track_scope_excursion($trackExcursion, $userId);
    } elseif ($trackContainer > 0) {
        require_once __DIR__ . '/../../includes/container.php';
        if (!container_ready($pdo) || !container_visible($pdo, $userId, $trackContainer)) {
            json_error(t('Kein Zugriff auf diese Freigabe.'), 403);
        }
        $scope = track_scope_container($pdo, $trackContainer, $userId)
            ?? ['where' => '0', 'params' => [], 'foreign' => true, 'via' => ''];
    } else {
        $scope = track_scope_own($userId);
    }

    if ($get === '') {
        $status = tracks_bucket_status_scoped($pdo, $scope);
        json_response([
            'buckets'    => $status['finger'],
            'counts'     => $status['counts'],
            'touched_at' => now(),
        ]);
    }

    $keys     = array_slice(array_filter(array_map('trim', explode(',', $get))), 0, 240);
    $response = tracks_bucket_get_scoped($pdo, $scope, $keys);

    if ($response['buckets'] === [] && $response['open'] === []) {
        json_error(t('Keine brauchbaren Eimerschlüssel angegeben. Erwartet wird "2026-08".'), 422);
    }
    json_response($response);
}

// WITH ?container=ID come the buckets of a collection container instead of
// one's own — the same two forms (fingerprints, points), the same rules,
// only over the points of the shares. See includes/container.php.
$containerId = (int) ($_GET['container'] ?? 0);
if ($containerId > 0 && empty($_GET['tracks'])) {
    require_once __DIR__ . '/../../includes/container.php';

    if (!container_ready($pdo)) {
        json_error(t('Die Freigaben stehen noch nicht bereit — Migration 0042 fehlt.'), 503);
    }
    // Membership is the boundary: whoever is not (or no longer) a member
    // gets nothing here — not even fingerprints, for those alone would
    // reveal how much there would be to see.
    if (!container_visible($pdo, $userId, $containerId)) {
        json_error(t('Kein Zugriff auf diese Freigabe.'), 403);
    }

    if ($get === '') {
        /* WITH THE COUNT PER BUCKET. The device can count its stored
           points and hold that number against this — the fingerprint it
           cannot recompute without building MySQL's CRC32 and its date
           format a second time in JavaScript. A bucket that matches the
           fingerprint and holds too few points counted as current for
           ever; that is exactly what the maintainer's container hung on
           (20.08.2026).

           IN ADDITION AND NOT INSTEAD: 'buckets' stays as it was, so that
           a device with an older program in its cache keeps running. */
        $status = container_bucket_status($pdo, $containerId, $userId);
        json_response([
            'buckets'  => $status['finger'],
            'counts' => $status['counts'],
            'touched_at'  => now(),
        ]);
    }

    $keys = array_slice(array_filter(array_map('trim', explode(',', $get))), 0, 240);
    $response    = container_bucket_points($pdo, $containerId, $keys, $userId);

    if ($response['buckets'] === [] && $response['open'] === []) {
        json_error(t('Keine brauchbaren Eimerschlüssel angegeben. Erwartet wird "2026-08".'), 422);
    }
    json_response($response);
}

/* WITH ?hints=1 come the finds friends showed me (migration 0149,
   includes/point_hints.php) — the same two forms as the containers, over
   the points of the hints that reach me. No membership to ask: the
   hint's recipient IS the boundary, and the query asks for him. */
if (!empty($_GET['hints']) && empty($_GET['tracks'])) {
    require_once __DIR__ . '/../../includes/point_hints.php';

    if (!point_hints_ready($pdo)) {
        json_error(t('Direkt teilen braucht die Migration 0149 — sie ist noch nicht eingespielt.'), 503);
    }
    if ($get === '') {
        $status = point_hints_bucket_status($pdo, $userId);
        json_response([
            'buckets'    => $status['finger'],
            'counts'     => $status['counts'],
            'touched_at' => now(),
        ]);
    }
    $keys     = array_slice(array_filter(array_map('trim', explode(',', $get))), 0, 240);
    $response = point_hints_bucket_points($pdo, $userId, $keys);
    if ($response['buckets'] === [] && $response['open'] === []) {
        json_error(t('Keine brauchbaren Eimerschlüssel angegeben. Erwartet wird "2026-08".'), 422);
    }
    json_response($response);
}

if ($get === '') {
    // The same for one's own stock — see above.
    $status = bucket_status($pdo, $userId);
    json_response([
        'buckets'  => $status['finger'],
        'counts' => $status['counts'],
        'touched_at'  => now(),
    ]);
}

// An upper bound on the NUMBER of requested keys, independent of the upper
// bound on the points: an address with ten thousand keys inside would no
// longer be a request, but an attempt.
$keys = array_slice(array_filter(array_map('trim', explode(',', $get))), 0, 240);

$response = bucket_points($pdo, $userId, $keys);

if ($response['buckets'] === [] && $response['open'] === []) {
    json_error(t('Keine brauchbaren Eimerschlüssel angegeben. Erwartet wird "2026-08".'), 422);
}

json_response($response);
