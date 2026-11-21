<?php
/**
 * Buckets: the points bundled by time, each with a fingerprint.
 *
 * WHAT FOR. At a few thousand points, the whole inventory can no longer go
 * over the wire on every page load — measured 525 bytes per point, so 10 MB
 * at 20,000, and index.php keeps no no-store result in the cache. Instead:
 * the device asks for the fingerprints (measured 2.6 kB for 120 months),
 * compares them with what it has, and fetches only the buckets that have
 * changed. Past finds no longer change — a bucket "2019-03" is loaded once
 * and never again.
 *
 * A BUCKET IS ALWAYS REPLACED AS A WHOLE. No individual amendments, no
 * tombstones, no merging. That sounds wasteful and is the core: it solves in
 * one stroke permanent deletion (row gone, COUNT drops), moving between
 * buckets (someone changes a find date, BOTH fingerprints change) and the
 * trash (a soft delete is an UPDATE, MAX(touched_at) advances). A month bucket is
 * around 90 kB; fetching it whole costs less than the bookkeeping of not
 * fetching it whole.
 *
 * THE DEVICE NEVER COMPUTES A KEY. It only stores what the server gives it
 * under which key. Otherwise the rule COALESCE(found_at, created_at)
 * would exist twice — once here, once in JavaScript —, and the two drift
 * apart as soon as someone touches one of them. That then shows up as
 * "a point has vanished".
 */

declare(strict_types=1);

/**
 * What a point's bucket is derived from.
 *
 * THE SAME RULE AS timerange.js: found_at, falling back to created_at. A
 * point must lie in the bucket where the time filter and the tour
 * calculation expect it too — otherwise the list shows something different
 * from the map.
 *
 * MONTHS, NOT YEARS. A year bucket would be around 2,000 points at the
 * expected volume, so a megabyte that would come fresh on every change in
 * the current year. A month is around 90 kB. For the past the year would be
 * enough, but two rules side by side would mean two places where a point
 * can hide.
 */
function bucket_expression(string $table = ''): string
{
    $p = $table === '' ? '' : $table . '.';
    return "DATE_FORMAT(COALESCE({$p}found_at, {$p}created_at), '%Y-%m')";
}

/**
 * The same key, but for an already loaded point.
 *
 * THE RULE THUS EXISTS TWICE — once as the SQL expression above, once here
 * in PHP. That is exactly what the paragraph in the file header warns
 * about, and it is still the lesser evil here: the alternative would be one
 * query per bucket (a hundred and twenty instead of one on a cold start) or
 * an extra field on every point that serves only the assignment and would
 * be transferred along.
 *
 * It is only bearable because the two versions stand HERE SIDE BY SIDE —
 * and because tests/bucket.php checks them against each other on real
 * points. Whoever changes one sees the other, and if not, the check trips.
 */
function bucket_key_of(array $point): string
{
    $time = $point['found_at'] ?? null;
    if ($time === null || $time === '') {
        $time = $point['created_at'] ?? '';
    }
    return substr((string) $time, 0, 7);
}

/**
 * How many points one request returns at most.
 *
 * NEVER CUT A BUCKET OPEN. If half a bucket arrived at the device and were
 * stored there under its valid fingerprint, the device would consider it
 * complete — forever, because the fingerprint does match. Points would be
 * silently gone, and permanently so. Therefore whole buckets are delivered
 * until the limit is reached, and the rest is explicitly reported as open.
 */
const BUCKET_MAX = 5000;

/** Are the prerequisites in place? stand arrives with migration 0038. */
function bucket_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_points', 'touched_at');
}

/** Does this look like a bucket key? */
function bucket_key_valid(string $key): bool
{
    return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $key);
}

/**
 * The fingerprint of each bucket.
 *
 * THREE FIGURES, NOT ONE GROUP_CONCAT. MD5(GROUP_CONCAT(...)) would be the
 * obvious choice and is a trap: GROUP_CONCAT truncates at
 * group_concat_max_len, 1024 bytes by default. Measured, the hash thereby
 * covered 35 of 156 points; at 20,000 it would be 35 of 20,000, and the
 * fingerprint would be blind to 99.8% of all changes — no warning, no
 * error, and it would look exactly like a proper one.
 *
 *   COUNT       catches every permanent deletion
 *   MAX(touched_at)  catches every change, because stand always moves forward
 *   SUM(CRC32)  catches the case where both happen to stay the same, and is
 *               independent of ordering
 *
 * All three get by without a buffer, no matter how many rows. Measured on
 * 20,000 rows: 21 ms, without any index.
 *
 * ONLY ACTIVE POINTS. The trash has its own path; counting it here would
 * mean every deletion moves two buckets.
 *
 * @return array<string,string>  key => fingerprint
 */
function bucket_fingerprints(PDO $pdo, int $userId): array
{
    return bucket_status($pdo, $userId)['finger'];
}

/**
 * Fingerprint AND count per bucket.
 *
 * THE COUNT IS THE BELT TO THE BRACES. The fingerprint says "something
 * changed"; the count says "this many there ought to be". The device can
 * count its own points but cannot recompute the print — that would mean
 * rebuilding MySQL's CRC32, its date format and the MD5 truncation
 * exactly, and writing that formula a second time in JavaScript is the
 * very duplication pointstore.js expressly forbids itself. A number, on
 * the other hand, is a number.
 *
 * It costs nothing: COUNT(*) already sits in the checksum anyway; here it
 * is merely handed out as well. No second pass, no second query
 * (20.08.2026).
 *
 * @return array{finger: array<string,string>, counts: array<string,int>}
 */
/**
 * WHAT THE POINTS DO NOT KNOW ABOUT THEMSELVES, folded into every
 * fingerprint. A delivered point carries values that are derived at
 * delivery from tables the point never touches — today the DGfM marks
 * (includes/dgfm_marks.php), looked up on the title species. The three
 * figures below see only the points; a reimported list moves none of
 * them, and a device that fetched its buckets before the import keeps
 * markless points for ever, every fingerprint matching (the maintainer's
 * device, 17.09.2026). This stamp is the fourth figure: whoever changes
 * the derived tables changes every fingerprint, once, for every device.
 *
 * The three statements that build fingerprints — here, the container's,
 * the excursion's — all take it; the tracks do not, they carry nothing
 * derived. Quoted, not bound: it stands inside an aggregate, and a
 * literal keeps the positional parameters of the three statements as
 * they are.
 */
function bucket_derived_stamp(PDO $pdo): string
{
    require_once __DIR__ . '/dgfm_marks.php';
    return $pdo->quote(dgfm_marks_stamp($pdo) . '|' . BUCKET_SHAPE);
}

/**
 * WHAT A DELIVERED POINT CARRIES, as a version — counted up whenever the
 * delivery starts carrying something new or right that the points
 * themselves never moved for. Every bucket of every device then counts as
 * stale ONCE, and the next start fetches them all.
 *
 *   2  (06.10.2026) the „bearbeitet" mark counts every recording on the
 *      find, and shared and excursion finds carry it at all — until then
 *      a flat 0 stood in their buckets (cloud_recordings_for_points).
 */
const BUCKET_SHAPE = 'shape2';

function bucket_status(PDO $pdo, int $userId): array
{
    if (!bucket_ready($pdo)) {
        return ['finger' => [], 'counts' => []];
    }

    $stmt = $pdo->prepare(
        'SELECT ' . bucket_expression() . ' AS bucket_key,
                COUNT(*) AS total,
                SUBSTRING(MD5(CONCAT_WS("|",
                    COUNT(*),
                    MAX(touched_at),
                    COALESCE(SUM(CRC32(CONCAT_WS(":", id, touched_at))), 0),
                    ' . bucket_derived_stamp($pdo) . '
                )), 1, 12) AS finger
           FROM map_points
          WHERE user_id = ? AND deleted_at IS NULL
          GROUP BY bucket_key
          ORDER BY bucket_key DESC'
    );
    $stmt->execute([$userId]);

    $out = [];
    $counts = [];
    foreach ($stmt as $z) {
        // A point without any time cannot occur — created_at is NOT NULL.
        // Should it ever come to that, it belongs nowhere, and silently
        // throwing it into a bucket named "" would be worse than skipping it
        // here: it would otherwise arrive at no device and nobody would know
        // why.
        if ($z['bucket_key'] === null || $z['bucket_key'] === '') {
            continue;
        }
        $out[(string) $z['bucket_key']]   = (string) $z['finger'];
        $counts[(string) $z['bucket_key']] = (int) $z['total'];
    }

    return ['finger' => $out, 'counts' => $counts];
}

/**
 * The points of requested buckets — whole buckets, until the limit is hit.
 *
 * THE FINGERPRINTS ARE READ BEFORE THE POINTS, and that is no small matter.
 * If someone writes in between, there are two possibilities:
 *
 *   read before  →  the device stores NEWER points under an OLDER
 *                   fingerprint. On the next sync it does not match, the
 *                   bucket comes once more. Bearable.
 *   read after   →  the device stores OLDER points under a NEWER
 *                   fingerprint. It matches on the next sync, the device
 *                   considers its outdated state current — forever.
 *
 * One ordering occasionally costs a superfluous request, the other silently
 * loses data. Hence before, and hence it is written here.
 *
 * THE POINTS COME BACK BUNDLED, not as a flat list. Otherwise the device
 * would have to assign them to the buckets itself — that is, implement the
 * rule COALESCE(found_at, created_at) a third time, this time in
 * JavaScript. This way the structure is the answer, and the rule never even
 * exists in the browser.
 *
 * @param string[] $keys
 * @return array{buckets: array<string,array{finger:string,points:array}>, open: string[]}
 */
function bucket_points(PDO $pdo, int $userId, array $keys): array
{
    $wanted = array_values(array_unique(array_filter($keys, 'bucket_key_valid')));

    if (!bucket_ready($pdo) || $wanted === []) {
        return ['buckets' => [], 'points' => [], 'open' => []];
    }

    // The fingerprint FIRST — see above.
    $all = bucket_fingerprints($pdo, $userId);

    // How big is each requested bucket? Needed to cut off at whole buckets
    // instead of stopping in the middle of one.
    $space = implode(',', array_fill(0, count($wanted), '?'));
    $stmt  = $pdo->prepare(
        'SELECT ' . bucket_expression() . ' AS bucket_key, COUNT(*) AS n
           FROM map_points
          WHERE user_id = ? AND deleted_at IS NULL AND ' . bucket_expression() . " IN ($space)
          GROUP BY bucket_key"
    );
    $stmt->execute(array_merge([$userId], $wanted));

    $size = [];
    foreach ($stmt as $z) {
        $size[(string) $z['bucket_key']] = (int) $z['n'];
    }

    $take = [];
    $open  = [];
    $sum  = 0;

    foreach ($wanted as $s) {
        // Still report a bucket that does not exist (anymore): the device
        // thereby learns that it is empty and discards its version. Without
        // this it would keep a bucket whose points were all deleted.
        $n = $size[$s] ?? 0;

        if ($take !== [] && $sum + $n > BUCKET_MAX) {
            $open[] = $s;
            continue;
        }
        $take[] = $s;
        $sum   += $n;
    }

    // Return ONLY THE DELIVERED buckets, and each with its points. The
    // device must never store a fingerprint whose points it has not
    // received.
    $delivered = [];
    foreach ($take as $s) {
        $delivered[$s] = ['finger' => $all[$s] ?? 'empty', 'points' => []];
    }

    foreach ($take === [] ? [] : points_load($pdo, $userId, ['buckets' => $take]) as $point) {
        $s = bucket_key_of($point);
        // Cannot occur — the query asked for exactly these buckets. If it
        // did happen, it would mean the SQL expression and the PHP version
        // have drifted apart, and then the point had better go nowhere than
        // into the wrong bucket: there it would lie under a fingerprint
        // that does not know it, and stay forever.
        if (isset($delivered[$s])) {
            $delivered[$s]['points'][] = $point;
        }
    }

    return ['buckets' => $delivered, 'open' => $open];
}
