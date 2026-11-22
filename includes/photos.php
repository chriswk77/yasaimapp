<?php
/**
 * Record photos on the points.
 *
 * WHAT ARRIVES HERE IS ALREADY SMALL. The shrinking happens in the browser
 * before anything goes over the wire — in the forest that is the difference
 * between "works" and "does not": 6 MB over a fringe-area connection takes
 * minutes, 500 kB does not. The server measures anyway. What one browser
 * computes, another sender can skip.
 *
 * AND IT IS SMALL IN TWO SIZES, since 19.08.2026. With a cloud connected
 * this server is a waiting room — the 2048 px picture passes through and is
 * gone from the disk minutes later (photo_cloud.php). Without one it stays
 * for good, and then it comes at 1000 px within a budget of 125 kB. The
 * budget is not decoration: at 1000 px a detailed forest floor still
 * measures 234 kB, measured — the edge alone would have missed the target
 * on exactly the pictures one takes most. See KANTE_OHNE_CLOUD in photos.js.
 *
 * THE FILE NO LONGER CARRIES METADATA. The route via the canvas sheds EXIF;
 * what matters of it — capture time, camera, coordinates — the browser
 * sends separately as form fields, and it lands in columns. That is no
 * loss, but the better state:
 *
 *   Analyzable. In columns you can search and sort, in an APP1 block you
 *   cannot.
 *
 *   And above all tight. The find location is the most sensitive datum of
 *   this application. An image file with embedded coordinates carries it
 *   everywhere it goes — into every attachment, every messenger, every
 *   shared list. What lies on the disk here gives away nothing by itself.
 *
 * NONE OF IT LIES ON THE WEB. The images go to data/users/<id>/photos/,
 * that is, behind the "Deny from all" (see user_store.php). They are
 * delivered by photo.php, which first asks who they belong to.
 */

declare(strict_types=1);

require_once __DIR__ . '/user_store.php';
require_once __DIR__ . '/export_style.php';

/**
 * The longest edge allowed in here.
 *
 * 2048 is the target given to the browser; somewhat more is permitted.
 * Whoever picks an image from the gallery that happens to be 2100 px should
 * not get an error message whose cause they cannot see. What lies clearly
 * above never went through our shrinking.
 */
const PHOTO_EDGE      = 2048;
const PHOTO_EDGE_MAX  = 2600;
const PHOTO_BYTES_MAX  = 4 * 1024 * 1024;

/** And the same for the preview image. */
const PHOTO_PREVIEW_EDGE     = 320;
const PHOTO_PREVIEW_EDGE_MAX = 480;
const PHOTO_PREVIEW_BYTES_MAX = 256 * 1024;

/**
 * Is the table far enough along that work can happen here?
 *
 * The code is on the server before the migration — that is the order of
 * deployment, and it has been costly once already. As long as the columns
 * are missing, there simply are no photos: the interface hides the section,
 * the API answers cleanly. What must not happen is a point that can no
 * longer be loaded because of it.
 */
function photos_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_photos', 'user_id');
}

// ---------------------------------------------------------------------------
// Reading
// ---------------------------------------------------------------------------

/** The photos of several points at once, grouped by point id. */
function photos_for_points(PDO $pdo, int $userId, array $pointIds): array
{
    if ($pointIds === [] || !photos_ready($pdo)) {
        return [];
    }

    $space = implode(',', array_fill(0, count($pointIds), '?'));

    /* ALL PICTURES OF A CO-OWNED FIND, WHOEVER TOOK THEM (co-ownership,
       11.09.2026): a find with co-owners is a shared record, and its
       gallery shows the whole set to everyone in the circle — the holder
       sees the leader's micrograph, the leader sees the holder's voucher.
       A find without co-owners keeps the old rule, one's own pictures
       only. The photographer rides along on every picture, so the tile
       can say „von Anna" where it is not the viewer's own. */
    /* ONLY FOR THE CIRCLE, AND THE CIRCLE IS ASKED PER CALLER: the whole
       set opens to whoever holds the find or co-owns it — not to whoever
       merely names its id. The first cut said „the point has co-owners"
       and let a stranger list a co-owned find's pictures through
       photos.php; tests/point_owners.php caught it before it shipped. */
    $shared = [];
    if (table_exists($pdo, 'map_point_owners')) {
        $q = $pdo->prepare(
            "SELECT DISTINCT p.id
               FROM map_points p
               JOIN map_point_owners o ON o.point_id = p.id
              WHERE p.id IN ($space)
                AND (p.user_id = ? OR (o.user_id = ? AND o.deleted_at IS NULL))"
        );
        $q->execute([...$pointIds, $userId, $userId]);
        $shared = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }
    $sharedSpace = $shared === [] ? '0' : implode(',', array_fill(0, count($shared), '?'));
    $stmt  = $pdo->prepare(
        "SELECT ph.*, TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS photographer_name,
                u.email AS photographer_email
           FROM map_photos ph
           JOIN map_users u ON u.id = ph.user_id
          WHERE ph.point_id IN ($space)
            AND (ph.user_id = ? OR ph.point_id IN ($sharedSpace))
          ORDER BY ph.sort_order, ph.id"
    );
    $stmt->execute([...$pointIds, $userId, ...$shared]);

    $out = [];
    foreach ($stmt as $row) {
        $photo = photo_prepare($row);
        $photo['photographer_id'] = (int) $row['user_id'];
        $photo['photographer']    = $row['photographer_name'] !== '' ? $row['photographer_name'] : $row['photographer_email'];
        $out[(int) $row['point_id']][] = $photo;
    }
    return $out;
}

/** The photos of one point. */
function photos_of_point(PDO $pdo, int $userId, int $pointId): array
{
    return photos_for_points($pdo, $userId, [$pointId])[$pointId] ?? [];
}

/** Bring a database row into the form the interface expects. */
function photo_prepare(array $row): array
{
    return [
        'id'             => (int) $row['id'],
        'point_id'       => (int) $row['point_id'],
        'width'         => $row['width'] === null ? null : (int) $row['width'],
        'height'          => $row['height']  === null ? null : (int) $row['height'],
        'bytes'          => (int) $row['bytes'],
        'taken_at' => $row['taken_at'],
        'camera'         => $row['camera'],
        /* The picture's NAME (migration 0097) — „Hauptmotiv", „Sporen",
           „HDS" … Typed in the photo window, groundwork for renaming
           the files into a fixed pattern later. Travels like scale and
           measures: whoever may see the picture may read its name. */
        'title'          => $row['title'] ?? null,
        /* The calibration (migration 0088), or null. It travels to EVERY
           viewer of the photo — the foreign ones included: whoever may see
           a picture may read its scale bar and, later, measure in it
           (28.08.2026). Only SETTING it needs a right. */
        'scale'          => photo_scale_read($row['scale'] ?? null),
        /* The media it was taken in (0187), or null — they travel like the
           scale: whoever may see a micrograph may read what it lay in. */
        'media'          => photo_media_read($row['media'] ?? null),
        // How it is developed (0190), or null — rendered over the original in the browser.
        'develop'        => photo_develop_read($row['develop'] ?? null),
        // The other edits beside it (0192), or null for „only one".
        'develop_versions' => photo_develop_versions_read($row['develop_versions'] ?? null),
        /* Whether its bytes lie in a cloud (photo_cloud.php). Only then may
           it be EDITED (10.11.2026: „ohne Cloud geht einfach wie
           immer gar nichts … die einfachen kleinen pixeligen Belegbilder
           können gar nichts"): the photo window offers „Bearbeiten" by it,
           and the API refuses a development for a picture still here. */
        'in_cloud'       => (string) ($row['cloud_fileid'] ?? '') !== '',
        /* The persisted measures (migration 0092, stage two) — they travel
           with the scale for the same reason: whoever may see the picture
           may see what was measured in it. */
        'measures'       => photo_measures_read($row['measures'] ?? null),
        // The coordinates from the image stay in here — they can deviate
        // from the point's, and exactly that is the interesting datum: it
        // says where somebody stood.
        'exif_lat'       => $row['exif_lat'] === null ? null : (float) $row['exif_lat'],
        'exif_lon'       => $row['exif_lon'] === null ? null : (float) $row['exif_lon'],
        'sort_order'     => (int) $row['sort_order'],
        'created_at'    => $row['created_at'],
    ];
}

/**
 * The calibration off the column — or null, which is the ordinary state.
 *
 * VALIDATED ON THE WAY OUT TOO, not only on the way in: the column is
 * TEXT, and a row edited by hand or written by a future bug must come out
 * as "not calibrated" rather than as coordinates that draw garbage over
 * somebody's photo.
 *
 * @return array{line: array{array{float,float},array{float,float}},
 *               um: float, corner: string}|null
 */
function photo_scale_read(mixed $stored): ?array
{
    if (!is_string($stored) || $stored === '') {
        return null;
    }
    $d = json_decode($stored, true);
    if (!is_array($d)) {
        return null;
    }
    try {
        return photo_scale_write($d);
    } catch (InvalidArgumentException) {
        return null;
    }
}

/**
 * Bring a calibration into the stored shape — or refuse loudly.
 *
 * THE GATE FOR BOTH DIRECTIONS (see photo_scale_read): what the API takes
 * in and what the column hands out pass through the same rules, so the
 * two cannot drift apart.
 *
 * THE LINE MUST HAVE LENGTH. Two identical points would make µm/px a
 * division by zero — not at the gate but later, in the middle of drawing,
 * where nobody would look for it.
 *
 * @param array $d {line: [[x,y],[x,y]], um: number, corner?: string}
 * @return array the clean shape, ready for json_encode
 */
function photo_scale_write(array $d): array
{
    $line = $d['line'] ?? null;
    if (!is_array($line) || count($line) !== 2) {
        throw new InvalidArgumentException(t('Die Eichlinie braucht genau zwei Punkte.'));
    }
    $points = [];
    foreach ($line as $p) {
        $x = $p[0] ?? null;
        $y = $p[1] ?? null;
        if (!is_numeric($x) || !is_numeric($y)
            || !is_finite((float) $x) || !is_finite((float) $y)
            || (float) $x < 0 || (float) $y < 0) {
            throw new InvalidArgumentException(t('Die Eichlinie liegt nicht im Bild.'));
        }
        // A tenth of a pixel is more precision than any hand has; rounding
        // here keeps the stored JSON short and its equality testable.
        $points[] = [round((float) $x, 1), round((float) $y, 1)];
    }
    if (hypot($points[0][0] - $points[1][0], $points[0][1] - $points[1][1]) < 1) {
        throw new InvalidArgumentException(t('Die Eichlinie hat keine Länge.'));
    }

    $um = $d['um'] ?? null;
    // A metre in micrometres is the ceiling — no photographed thing this
    // application meets is longer, and a slipped unit (mm typed as µm
    // times a million) should fail here, not draw a nonsense bar.
    if (!is_numeric($um) || !is_finite((float) $um)
        || (float) $um <= 0 || (float) $um > 1000000) {
        throw new InvalidArgumentException(t('Die Länge der Eichlinie fehlt oder ist unbrauchbar.'));
    }

    $corner = (string) ($d['corner'] ?? 'br');
    if (!in_array($corner, ['tl', 'tr', 'bl', 'br'], true)) {
        $corner = 'br';
    }

    $out = ['line' => $points, 'um' => round((float) $um, 2), 'corner' => $corner];

    /* THE REFERENCE WIDTH (migration 0090): the pixel width of the image
       the line was drawn on. It is what lets a cloud recording be
       calibrated on its 2048-px PREVIEW and still measure truly on the
       6000-px original — every showing converts by widths. Optional:
       the calibrations of the first day lack it, and there the displayed
       image's own width holds, which for map_photos is the same number. */
    $width = $d['width'] ?? null;
    if (is_numeric($width) && (int) $width > 0 && (int) $width <= 100000) {
        $out['width'] = (int) $width;
    }

    // Where the scale CAME FROM, when it came from a preset — „10x", shown
    // in the viewer's chooser so the current state has a word. Optional
    // and purely descriptive; the numbers above stand on their own.
    $name = trim((string) ($d['name'] ?? ''));
    if ($name !== '') {
        $out['name'] = mb_substr($name, 0, 100);
    }

    /* WHICH PRESET AND WHICH DEVICE (02.11.2026): shown
       nowhere — „uninteressant, mit welchem Gerät ich gemessen habe" —
       but kept, so a calibration found wrong later („beim Reisemikroskop
       eine falsche Eichung gemacht") can find every picture it stamped
       and be corrected on all of them. The numbers above still stand on
       their own; a deleted preset leaves its ids behind harmlessly. */
    foreach (['preset_id', 'device_id'] as $key) {
        $id = $d[$key] ?? null;
        if (is_numeric($id) && (int) $id > 0) {
            $out[$key] = (int) $id;
        }
    }

    return $out;
}

/**
 * The measures off the column — an array, [] being the ordinary state.
 *
 * Validated on the way out like the scale is (photo_scale_read says why);
 * a bent row reads as "nothing measured", never as garbage drawing.
 *
 * @return array<int, array{type:string, points:array, width?:int}>
 */
function photo_measures_read(mixed $stored): array
{
    if (!is_string($stored) || $stored === '') {
        return [];
    }
    $d = json_decode($stored, true);
    if (!is_array($d)) {
        return [];
    }
    try {
        return photo_measures_write($d) ?? [];
    } catch (InvalidArgumentException) {
        return [];
    }
}

/**
 * A picture's name, cleaned — trimmed, capped at the column's 120, and
 * empty becomes NULL: „unbenannt" is the absence of a name, not a name
 * of zero letters, and the renaming that builds on this later must be
 * able to tell the two apart.
 */
function photo_title_clean(mixed $title): ?string
{
    $title = trim((string) $title);
    return $title === '' ? null : mb_substr($title, 0, 120);
}

/**
 * Store or clear a photo's name (migration 0097) — the same shape as
 * photo_scale_store, existence check and point nudge included.
 */
function photo_title_store(PDO $pdo, int $ownerId, int $photoId, ?string $title): bool
{
    if (!photos_ready($pdo) || !column_exists($pdo, 'map_photos', 'title')) {
        return false;
    }
    $photo = photo_load($pdo, $ownerId, $photoId);
    if ($photo === null) {
        return false;
    }
    $pdo->prepare('UPDATE map_photos SET title = ? WHERE id = ? AND user_id = ?')
        ->execute([$title, $photoId, $ownerId]);
    // The name travels in the point baggage like scale and measures.
    photo_point_touched($pdo, (int) $photo['point_id']);
    // And it is part of the file's name in the cloud (cloud_names.php).
    require_once __DIR__ . '/cloud_names.php';
    cloud_names_touch($pdo, (int) $photo['point_id']);
    return true;
}

/** The name twin on map_cloud_files — no photo_point_touched, for
 *  cloud_file_scale_store's reason: these rows never ride a bucket. */
function cloud_file_title_store(PDO $pdo, int $userId, int $fileId, ?string $title): bool
{
    if (!table_exists($pdo, 'map_cloud_files')
        || !column_exists($pdo, 'map_cloud_files', 'title')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT point_id FROM map_cloud_files WHERE id = ? AND user_id = ?');
    $stmt->execute([$fileId, $userId]);
    $pointId = $stmt->fetchColumn();
    if ($pointId === false) {
        return false;
    }
    $pdo->prepare('UPDATE map_cloud_files SET title = ? WHERE id = ? AND user_id = ?')
        ->execute([$title, $fileId, $userId]);
    // The subject name is part of the file's name (cloud_names.php).
    require_once __DIR__ . '/cloud_names.php';
    cloud_names_touch($pdo, (int) $pointId);
    return true;
}

/**
 * THE MEDIA A MICROGRAPH WAS TAKEN IN (migration 0187,
 * 04.11.2026) — a list of stable keys from assets/js/media.js („h2o",
 * „koh_3", „cotton_blue"), never the words: those are translated and read
 * in the browser. The server knows no vocabulary, only the shape — a key
 * it has never heard of is kept, so a word added to the list needs no
 * deployment here. Null is „nicht angegeben".
 */
const PHOTO_MEDIA_MAX = 12;

function photo_media_clean(mixed $media): ?array
{
    if (!is_array($media)) {
        return null;
    }
    $out = [];
    foreach ($media as $key) {
        if (is_string($key) && preg_match('~^[a-z0-9_]{1,40}$~', $key) === 1 && !in_array($key, $out, true)) {
            $out[] = $key;
        }
        if (count($out) >= PHOTO_MEDIA_MAX) {
            break;
        }
    }
    return $out === [] ? null : $out;
}

/** The column's JSON back into a list — through the same gate. */
function photo_media_read(mixed $json): ?array
{
    return is_string($json) && $json !== '' ? photo_media_clean(json_decode($json, true)) : null;
}

/**
 * THE PICTURE DEVELOPED (migration 0190, 06.11.2026) — the
 * viewer's „Bearbeiten": the sliders, each -100 … 100 (sharpening 0 …
 * 100), and the ellipses laid over the picture, rendered live over the
 * untouched original by assets/js/develop.js. Only the shape is checked
 * here: known keys, numbers in their ranges, a slider at 0 left out. An
 * ellipse's centre is a share of the picture's width and height, both
 * radii shares of its WIDTH (so a circle stays one when turned), the angle
 * in degrees. Null is „nicht bearbeitet".
 */
const PHOTO_DEVELOP_SLIDERS = ['exposure', 'contrast', 'highlights', 'shadows', 'whites', 'blacks', 'temp', 'tint',
                               'vibrance', 'saturation', 'clarity', 'dehaze'];
// Every global slider since 11.11.2026 — a mask is a small development of its own.
const PHOTO_DEVELOP_RADIAL  = ['temp', 'tint', 'contrast', 'vibrance', 'saturation', 'exposure', 'highlights', 'shadows',
                               'whites', 'blacks', 'clarity', 'dehaze', 'sharpen'];
const PHOTO_DEVELOP_RADIALS = 12;
/** „Farbmischer" (22.11.2026): the eight colours — the twin of MIX_COLOURS in assets/js/develop.js. */
/** „Gradationskurve": the points a channel may have — CURVE_POINTS_MAX in develop.js. */
const PHOTO_DEVELOP_CURVE_POINTS = 16;
/** The pipette's points of „Farbstich" — CAST_PICKS_MAX in develop.js. */
const PHOTO_DEVELOP_CAST_PICKS = 8;
const PHOTO_DEVELOP_MIX = ['red', 'orange', 'yellow', 'green', 'aqua', 'blue', 'purple', 'magenta'];
/** The spots' caps — the twins of SPOTS_MAX and PATH_POINTS_MAX in assets/js/develop.js. The points
 *  are ONE budget for the spots and the brush strokes together. */
const PHOTO_DEVELOP_SPOTS = 1000;
const PHOTO_DEVELOP_PATH_POINTS = 20000;
/** The AI spots' pieces, all together — AI_PIECES_MAX and AI_PATCH_ID in develop.js. */
const PHOTO_DEVELOP_AI_PIECES = 1000;
const PHOTO_DEVELOP_AI_PATCH_ID = '/^[a-z0-9]{8,32}$/';
/** The filled border of a crop past the picture — FILL_PIECES_MAX and FILL_EDGE_MAX in develop.js. */
const PHOTO_DEVELOP_FILL_PIECES = 400;
const PHOTO_DEVELOP_FILL_EDGE = 16384;
/** A border falls into this many areas at the most (develop.js, FILL_AREAS_MAX). */
const PHOTO_DEVELOP_FILL_AREAS = 12;
/** Painted masks — BRUSHES_MAX in develop.js: four, the channels of one texture. */
const PHOTO_DEVELOP_BRUSHES = 4;

function photo_develop_clean(mixed $d): ?array
{
    if (!is_array($d)) {
        return null;
    }
    $num = static fn($v, float $lo, float $hi): ?float => is_numeric($v) ? round(max($lo, min($hi, (float) $v)), 2) : null;
    $out = [];
    foreach (PHOTO_DEVELOP_SLIDERS as $k) {
        $v = $num($d[$k] ?? null, -100, 100);
        if ($v !== null && $v != 0) $out[$k] = $v;
    }
    $sharpen = $num($d['sharpen'] ?? null, 0, 100);
    if ($sharpen !== null && $sharpen != 0) $out['sharpen'] = $sharpen;
    /* THE WHITE BALANCE („Mikrofotos", 09.11.2026; develop.js developWB): a
       gain per channel, 0.25 to 4, to four places; kept only where it moves
       a colour. */
    if (is_array($d['wb'] ?? null)) {
        $wb = [];
        foreach (['r', 'g', 'b'] as $c) {
            $v = $d['wb'][$c] ?? null;
            $wb[$c] = is_numeric($v) ? round(max(.25, min(4, (float) $v)), 4) : 1.0;
        }
        if ($wb['r'] != 1 || $wb['g'] != 1 || $wb['b'] != 1) $out['wb'] = $wb;
    }
    /* „FARBSTICH ENTFERNEN" (14.11.2026; develop.js castMeasure): the grey's
       red and blue against its green in each of five brightness ranges,
       0.25 to 4 to four places, and three sliders 0 to 100. All five pairs
       or nothing — a range missing would leave the shader a hole. */
    $cast = is_array($d['cast'] ?? null) ? $d['cast'] : null;
    $pairs = static function (mixed $list): ?array {
        if (!is_array($list) || count($list) !== 5) return null;
        $n = [];
        foreach (array_values($list) as $pair) {
            $pair = is_array($pair) ? array_values($pair) : [];
            if (!is_numeric($pair[0] ?? null) || !is_numeric($pair[1] ?? null)) return null;
            $n[] = [round(max(.25, min(4, (float) $pair[0])), 4), round(max(.25, min(4, (float) $pair[1])), 4)];
        }
        return $n;
    };
    $n = $cast !== null ? $pairs($cast['n'] ?? null) : null;
    if ($n !== null) {
        $share = static fn($v): int => (int) round(is_numeric($v) ? max(0, min(100, (float) $v)) : 100);
        $out['cast'] = ['n' => $n, 'amount' => $share($cast['amount'] ?? null),
            'shadows' => $share($cast['shadows'] ?? null), 'highlights' => $share($cast['highlights'] ?? null)];
        /* THE PIPETTE (22.11.2026; develop.js castFromPicks): the
           measurement beneath the points, five pairs like n, and up to
           eight points — px, py a share of the original, lum the linear
           brightness, r and b like a pair of n. n stays what is applied;
           these are kept so the points can change later. */
        $base = $pairs($cast['base'] ?? null);
        if ($base !== null) $out['cast']['base'] = $base;
        $picks = [];
        foreach (is_array($cast['picks'] ?? null) ? $cast['picks'] : [] as $p) {
            if (count($picks) >= PHOTO_DEVELOP_CAST_PICKS) break;
            if (!is_array($p) || !is_numeric($p['lum'] ?? null) || (float) $p['lum'] <= 0
                || !is_numeric($p['r'] ?? null) || !is_numeric($p['b'] ?? null)) continue;
            $picks[] = [
                'px'  => is_numeric($p['px'] ?? null) ? round(max(0, min(1, (float) $p['px'])), 4) : 0.0,
                'py'  => is_numeric($p['py'] ?? null) ? round(max(0, min(1, (float) $p['py'])), 4) : 0.0,
                'lum' => round(min(1, (float) $p['lum']), 5),
                'r'   => round(max(.25, min(4, (float) $p['r'])), 4),
                'b'   => round(max(.25, min(4, (float) $p['b'])), 4),
            ];
        }
        if ($picks !== []) $out['cast']['picks'] = $picks;
    }
    /* „FARBMISCHER" (22.11.2026; develop.js MIX_COLOURS): per kind — h
       Farbton, s Sättigung, l Luminanz — the colours that are set, -100 to
       100. A kind without a set colour is left out, the whole without one
       too. */
    if (is_array($d['mix'] ?? null)) {
        $mix = [];
        foreach (['h', 's', 'l'] as $kind) {
            $row = [];
            foreach (PHOTO_DEVELOP_MIX as $colour) {
                $v = $num(is_array($d['mix'][$kind] ?? null) ? ($d['mix'][$kind][$colour] ?? null) : null, -100, 100);
                if ($v !== null && $v != 0) $row[$colour] = $v;
            }
            if ($row !== []) $mix[$kind] = $row;
        }
        if ($mix !== []) $out['mix'] = $mix;
    }
    /* „GRADATIONSKURVE" (22.11.2026; develop.js curvePoints): per channel
       — rgb, r, g, b — its points [x, y], whole numbers 0 to 255, x rising
       and each x once (a later point on the same x wins), at most sixteen;
       fewer than two, or the bare diagonal, is no curve. */
    if (is_array($d['curve'] ?? null)) {
        $curve = [];
        foreach (['rgb', 'r', 'g', 'b'] as $ch) {
            $byX = [];
            foreach (is_array($d['curve'][$ch] ?? null) ? $d['curve'][$ch] : [] as $p) {
                $p = is_array($p) ? array_values($p) : [];
                if (!is_numeric($p[0] ?? null) || !is_numeric($p[1] ?? null)) continue;
                $byX[(int) round(max(0, min(255, (float) $p[0])))] = (int) round(max(0, min(255, (float) $p[1])));
            }
            ksort($byX);
            $pts = [];
            foreach (array_slice($byX, 0, PHOTO_DEVELOP_CURVE_POINTS, true) as $x => $y) $pts[] = [$x, $y];
            if (count($pts) < 2 || $pts === [[0, 0], [255, 255]]) continue;
            $curve[$ch] = $pts;
        }
        if ($curve !== []) $out['curve'] = $curve;
    }
    /* „FARBKORREKTUR NACH HELLIGKEIT" (22.11.2026; develop.js
       GRADE_RANGES): per range h 0 to 359 (whole degrees, 360 is 0), s 0
       to 100, l -100 to 100, what is 0 left out; balance -100 to 100, kept
       only beside a range — alone it moves a border between nothing. */
    if (is_array($d['grade'] ?? null)) {
        $grade = [];
        foreach (['highlights', 'midtones', 'shadows'] as $range) {
            $g = is_array($d['grade'][$range] ?? null) ? $d['grade'][$range] : [];
            $one = [];
            $h = is_numeric($g['h'] ?? null) ? ((int) round((float) $g['h']) % 360 + 360) % 360 : 0;
            if ($h !== 0) $one['h'] = $h;
            $sv = $num($g['s'] ?? null, 0, 100);
            if ($sv !== null && $sv != 0) $one['s'] = $sv;
            $l = $num($g['l'] ?? null, -100, 100);
            if ($l !== null && $l != 0) $one['l'] = $l;
            if ($one !== []) $grade[$range] = $one;
        }
        $balance = $num($d['grade']['balance'] ?? null, -100, 100);
        if ($grade !== [] && $balance !== null && $balance != 0) $grade['balance'] = $balance;
        if ($grade !== []) $out['grade'] = $grade;
    }
    /* THE SPOTS („Ausbessern", 10.11.2026; develop.js spotsApply): places
       mended from elsewhere in the picture, as descriptions — a path (one
       point or a stroke) and a radius, the source the same shape moved by
       dx, dy. Points and offsets as shares of width and height to four
       places, the radius a share of the width. CAPPED, because the column
       is TEXT: a hundred spots and two thousand points in all, about forty
       kilobytes at the worst, and a save that overflowed would fail whole. */
    $spots = [];
    $points = 0;
    $aiPieces = 0;
    foreach (is_array($d['spots'] ?? null) ? $d['spots'] : [] as $sp) {
        if (!is_array($sp) || count($spots) >= PHOTO_DEVELOP_SPOTS || $points >= PHOTO_DEVELOP_PATH_POINTS) continue;
        $path = [];
        foreach (is_array($sp['path'] ?? null) ? $sp['path'] : [] as $pt) {
            if ($points + count($path) >= PHOTO_DEVELOP_PATH_POINTS) break;
            if (!is_array($pt) || !is_numeric($pt[0] ?? null) || !is_numeric($pt[1] ?? null)) continue;
            $path[] = [round(max(-1, min(2, (float) $pt[0])), 4), round(max(-1, min(2, (float) $pt[1])), 4)];
        }
        if ($path === []) continue;
        $one = [
            'mode'    => ($sp['mode'] ?? '') === 'clone' ? 'clone' : 'heal',
            'path'    => $path,
            'radius'  => is_numeric($sp['radius'] ?? null) ? round(max(.0005, min(.15, (float) $sp['radius'])), 5) : .01,
            'feather' => (int) round($num($sp['feather'] ?? null, 0, 100) ?? 50),
            'opacity' => (int) round($num($sp['opacity'] ?? null, 0, 100) ?? 100),
        ];
        /* „ENTFERNEN" (11.11.2026, develop.js aiPieces): no source, but
           pieces — each the name of a patch the model made, its square on
           the picture and its stretch of the path. Without a single valid
           piece the spot is dropped: it would remove nothing. */
        // The KI's pieces as stored: a patch name, a box inside the picture, a stretch of the path.
        $clean = static function ($list, int $room): array {
            $pieces = [];
            foreach (is_array($list) ? $list : [] as $pc) {
                if (count($pieces) >= $room) break;
                if (!is_array($pc) || !preg_match(PHOTO_DEVELOP_AI_PATCH_ID, (string) ($pc['id'] ?? ''))) continue;
                $box = is_array($pc['box'] ?? null) ? array_values($pc['box']) : [];
                if (count($box) !== 4 || array_filter($box, static fn($v) => !is_numeric($v)) !== []) continue;
                $box = array_map(static fn($v) => round(max(0, min(1, (float) $v)), 5), $box);
                if ($box[2] <= 0 || $box[3] <= 0) continue;
                $pieces[] = [
                    'id'  => (string) $pc['id'],
                    'box' => $box,
                    'a'   => is_numeric($pc['a'] ?? null) ? round(max(0, min(1, (float) $pc['a'])), 5) : 0.0,
                    'b'   => is_numeric($pc['b'] ?? null) ? round(max(0, min(1, (float) $pc['b'])), 5) : 1.0,
                ];
            }
            return $pieces;
        };
        if (($sp['mode'] ?? '') === 'ai') {
            $pieces = $clean($sp['pieces'] ?? null, PHOTO_DEVELOP_AI_PIECES - $aiPieces);
            if ($pieces === []) continue;
            $aiPieces += count($pieces);
            $one['mode'] = 'ai';
            $one['pieces'] = $pieces;
            /* TWO MODELS, ONE PLACE (14.11.2026, develop.js): `other` are
               the pieces of the model NOT shown, kept so that switching
               needs no new computing; `strong` says the shown ones are the
               strong model's. Both sets count against the one budget. */
            $other = $clean($sp['other'] ?? null, PHOTO_DEVELOP_AI_PIECES - $aiPieces);
            $aiPieces += count($other);
            if ($other !== []) $one['other'] = $other;
            if (!empty($sp['strong'])) $one['strong'] = true;
            /* THE FILL REACHES THE BRUSH'S OUTER CIRCLE (22.11.2026,
               develop.js aiReach): set on every place filled since; one
               without it fades over half a radius, as its patches were cut. */
            if (!empty($sp['wide'])) $one['wide'] = true;
            // Drawn round its thing (11.11.2026, develop.js aiLoopCloses): the inside goes too.
            if (!empty($sp['fill'])) $one['fill'] = true;
            /* ONE TOOL, THREE METHODS (14.11.2026, develop.js): the method
               of a place may change at any time, so a place shown as
               „Entfernen" keeps the source and the turn it had before. */
            $dx = is_numeric($sp['dx'] ?? null) ? round(max(-3, min(3, (float) $sp['dx'])), 4) : 0.0;
            $dy = is_numeric($sp['dy'] ?? null) ? round(max(-3, min(3, (float) $sp['dy'])), 4) : 0.0;
            if ($dx != 0 || $dy != 0) {
                $one['dx'] = $dx;
                $one['dy'] = $dy;
            }
            $rot = is_numeric($sp['rot'] ?? null) ? round(max(-180, min(180, (float) $sp['rot'])), 1) : 0.0;
            if ($rot != 0) $one['rot'] = $rot;
        } else {
            $one['dx'] = is_numeric($sp['dx'] ?? null) ? round(max(-3, min(3, (float) $sp['dx'])), 4) : 0.0;
            $one['dy'] = is_numeric($sp['dy'] ?? null) ? round(max(-3, min(3, (float) $sp['dy'])), 4) : 0.0;
            // „Reparieren" and „Kopieren" drawn round their thing fill the inside too (11.11.2026).
            if (!empty($sp['fill'])) $one['fill'] = true;
            // The source turned (11.11.2026, develop.js spotSourceMap), in tenths of a degree.
            $rot = is_numeric($sp['rot'] ?? null) ? round(max(-180, min(180, (float) $sp['rot'])), 1) : 0.0;
            if ($rot != 0) $one['rot'] = $rot;
            /* And a heal or a clone keeps the fills it had as „Entfernen" —
               not drawn, only kept, so switching back computes nothing. */
            $kept = $clean($sp['pieces'] ?? null, PHOTO_DEVELOP_AI_PIECES - $aiPieces);
            if ($kept !== []) {
                $aiPieces += count($kept);
                $one['pieces'] = $kept;
                $other = $clean($sp['other'] ?? null, PHOTO_DEVELOP_AI_PIECES - $aiPieces);
                $aiPieces += count($other);
                if ($other !== []) $one['other'] = $other;
                if (!empty($sp['strong'])) $one['strong'] = true;
                if (!empty($sp['wide'])) $one['wide'] = true;
            }
        }
        $points += count($path);
        if (!empty($sp['off'])) $one['off'] = true;
        $spots[] = $one;
    }
    if ($spots !== []) $out['spots'] = $spots;
    $radials = [];
    foreach (is_array($d['radials'] ?? null) ? $d['radials'] : [] as $r) {
        if (!is_array($r) || count($radials) >= PHOTO_DEVELOP_RADIALS) continue;
        /* PLACE AND SIZE TO FOUR PLACES (07.11.2026): the hundredths $num
           rounds to are a hundredth of the picture's width — an ellipse of
           four hundredths came back a quarter off its drawn shape after a
           reload, measured while building Shift-scaling. */
        $fine = static fn($v, float $lo, float $hi): ?float => is_numeric($v) ? round(max($lo, min($hi, (float) $v)), 4) : null;
        $one = [
            'x'       => $fine($r['x'] ?? null, -1, 2) ?? .5,
            'y'       => $fine($r['y'] ?? null, -1, 2) ?? .5,
            // Up to five widths across: an ellipse far beyond the picture darkens its edges alone (07.11.2026).
            'rx'      => $fine($r['rx'] ?? null, .005, 5) ?? .2,
            'ry'      => $fine($r['ry'] ?? null, .005, 5) ?? .15,
            'angle'   => $num($r['angle'] ?? null, -180, 180) ?? 0,
            'feather' => $num($r['feather'] ?? null, 0, 100) ?? 50,
            'invert'  => !empty($r['invert']),
        ];
        // Switched off by its eye (09.11.2026): kept, but it does nothing.
        if (!empty($r['off'])) $one['off'] = true;
        foreach (PHOTO_DEVELOP_RADIAL as $k) {
            $v = $num($r[$k] ?? null, -100, 100);
            if ($v !== null && $v != 0) $one[$k] = $v;
        }
        $radials[] = $one;
    }
    if ($radials !== []) $out['radials'] = $radials;
    /* THE GRADIENTS („Verlauf", 07.11.2026): full effect up to the line
       through (x1|y1), none from the line through (x2|y2), both lines
       square to the way between them — points as shares of width and
       height, finer than the ellipses' hundredths, because two points
       close together set the gradient's whole width. One budget with the
       ellipses: the renderer holds twelve masks in all (develop.js). */
    $linears = [];
    $p4 = static fn($v, float $fallback): float => is_numeric($v) ? round(max(-2, min(3, (float) $v)), 4) : $fallback;
    foreach (is_array($d['linears'] ?? null) ? $d['linears'] : [] as $r) {
        if (!is_array($r) || count($radials) + count($linears) >= PHOTO_DEVELOP_RADIALS) continue;
        $one = [
            'x1' => $p4($r['x1'] ?? null, .5), 'y1' => $p4($r['y1'] ?? null, .3),
            'x2' => $p4($r['x2'] ?? null, .5), 'y2' => $p4($r['y2'] ?? null, .7),
            'invert' => !empty($r['invert']),
        ];
        if (!empty($r['off'])) $one['off'] = true;
        foreach (PHOTO_DEVELOP_RADIAL as $k) {
            $v = $num($r[$k] ?? null, -100, 100);
            if ($v !== null && $v != 0) $one[$k] = $v;
        }
        $linears[] = $one;
    }
    if ($linears !== []) $out['linears'] = $linears;
    /* THE BRUSHES („Pinsel", 10.11.2026; develop.js brushField): painted
       masks, their strokes paths as the spots have them, from the same
       budget of points. One budget with the other masks, four at most. */
    $brushes = [];
    foreach (is_array($d['brushes'] ?? null) ? $d['brushes'] : [] as $b) {
        if (!is_array($b) || count($brushes) >= PHOTO_DEVELOP_BRUSHES
            || count($radials) + count($linears) + count($brushes) >= PHOTO_DEVELOP_RADIALS) continue;
        $strokes = [];
        foreach (is_array($b['strokes'] ?? null) ? $b['strokes'] : [] as $st) {
            if (!is_array($st) || $points >= PHOTO_DEVELOP_PATH_POINTS) continue;
            $path = [];
            foreach (is_array($st['path'] ?? null) ? $st['path'] : [] as $pt) {
                if ($points + count($path) >= PHOTO_DEVELOP_PATH_POINTS) break;
                if (!is_array($pt) || !is_numeric($pt[0] ?? null) || !is_numeric($pt[1] ?? null)) continue;
                $path[] = [round(max(-1, min(2, (float) $pt[0])), 4), round(max(-1, min(2, (float) $pt[1])), 4)];
            }
            if ($path === []) continue;
            $points += count($path);
            $one = [
                'path'    => $path,
                'radius'  => is_numeric($st['radius'] ?? null) ? round(max(.0005, min(.15, (float) $st['radius'])), 5) : .01,
                'feather' => (int) round($num($st['feather'] ?? null, 0, 100) ?? 50),
                'flow'    => (int) round($num($st['flow'] ?? null, 0, 100) ?? 100),
            ];
            if (!empty($st['erase'])) $one['erase'] = true;
            $strokes[] = $one;
        }
        $one = ['strokes' => $strokes, 'invert' => !empty($b['invert'])];
        if (!empty($b['off'])) $one['off'] = true;
        foreach (PHOTO_DEVELOP_RADIAL as $k) {
            $v = $num($b[$k] ?? null, -100, 100);
            if ($v !== null && $v != 0) $one[$k] = $v;
        }
        $brushes[] = $one;
    }
    if ($brushes !== []) $out['brushes'] = $brushes;
    /* TURNED AND CUT (stage 2, 07.11.2026): a quarter turn, the straightening
       in degrees, and the crop — its centre as shares of width and height,
       its sides as shares of the WIDTH (assets/js/develop.js, geoView). */
    $g = is_array($d['geo'] ?? null) ? $d['geo'] : null;
    if ($g !== null) {
        $turn = (int) round((float) ($g['turn'] ?? 0));
        $turn = in_array((($turn % 360) + 360) % 360, [0, 90, 180, 270], true) ? (($turn % 360) + 360) % 360 : 0;
        $angle = $num($g['angle'] ?? null, -45, 45) ?? 0.0;
        $crop = null;
        if (is_array($g['crop'] ?? null)) {
            $c = $g['crop'];
            $x = $num($c['x'] ?? null, -1, 2);
            $y = $num($c['y'] ?? null, -1, 2);
            $w = is_numeric($c['w'] ?? null) ? round(max(.01, min(3, (float) $c['w'])), 4) : null;
            $h = is_numeric($c['h'] ?? null) ? round(max(.01, min(3, (float) $c['h'])), 4) : null;
            if ($x !== null && $y !== null && $w !== null && $h !== null) {
                $crop = ['x' => round((float) $c['x'], 4), 'y' => round((float) $c['y'], 4), 'w' => $w, 'h' => $h];
                $crop['x'] = max(-1, min(2, $crop['x']));
                $crop['y'] = max(-1, min(2, $crop['y']));
            }
        }
        // Mirrored before it is turned (09.11.2026; develop.js, geoView).
        $flip = in_array($g['flip'] ?? null, ['h', 'v', 'hv'], true) ? $g['flip'] : null;
        /* „ERWEITERN" (15.11.2026; develop.js, geo.fill): the border of a
           crop that reaches past the picture, invented by a model — one
           image of w × h pixels over the crop's rectangle, stored as the
           patches of „Entfernen" are, in squares of 512 by column and row.
           `at` names the cut it was made for (develop.js, geoSig); the
           browser draws a fill only where that still is the cut, so here it
           is kept as a word and not read. Without a crop, or without one
           valid piece, there is no fill. */
        $fill = null;
        $f = is_array($g['fill'] ?? null) ? $g['fill'] : null;
        // One image of a fill: its size, and its squares — valid ids, each square once, inside the image.
        $fillSize = static function ($o): ?array {
            if (!is_array($o) || !is_numeric($o['w'] ?? null) || !is_numeric($o['h'] ?? null)) return null;
            $w = (int) round((float) $o['w']);
            $h = (int) round((float) $o['h']);
            return $w >= 1 && $h >= 1 && $w <= PHOTO_DEVELOP_FILL_EDGE && $h <= PHOTO_DEVELOP_FILL_EDGE ? [$w, $h] : null;
        };
        $fillPieces = static function ($list, int $w, int $h): array {
            $pieces = [];
            $seen = [];
            foreach (is_array($list) ? $list : [] as $pc) {
                if (!is_array($pc) || !preg_match(PHOTO_DEVELOP_AI_PATCH_ID, (string) ($pc['id'] ?? ''))
                    || !is_numeric($pc['c'] ?? null) || !is_numeric($pc['r'] ?? null)) continue;
                $c = (int) round((float) $pc['c']);
                $r = (int) round((float) $pc['r']);
                if ($c < 0 || $r < 0 || $c * 512 >= $w || $r * 512 >= $h || isset($seen[$c . ',' . $r])) continue;
                $seen[$c . ',' . $r] = true;
                $pieces[] = ['id' => (string) $pc['id'], 'c' => $c, 'r' => $r];
            }
            return $pieces;
        };
        $fillMade = static fn(array $o): array => [
            'level' => (int) max(1, min(4, round((float) ($o['level'] ?? 1)))),
            'strength' => (int) max(0, min(100, round((float) ($o['strength'] ?? 0)))),
        ];
        $size = $fillSize($f);
        if ($f !== null && $crop !== null && $size !== null
            && is_string($f['at'] ?? null) && preg_match('/^[0-9hv.|\-]{1,80}$/', $f['at'])) {
            // One budget for the border, the quick model's image and the strong model's areas together.
            $room = $roomAtFirst = max(0, min(PHOTO_DEVELOP_FILL_PIECES, PHOTO_DEVELOP_AI_PIECES - $aiPieces));
            $pieces = array_slice($fillPieces($f['pieces'] ?? null, $size[0], $size[1]), 0, $room);
            if ($pieces !== []) {
                $room -= count($pieces);
                $fill = ['w' => $size[0], 'h' => $size[1], 'pieces' => $pieces, 'at' => $f['at']];
                if (!empty($f['strong'])) {
                    // The form of the first day: the strong model's border in the place of the quick one's.
                    $fill = $fill + ['strong' => true] + $fillMade($f);
                }
                /* EVERY AREA OF THE BORDER CHOOSES ITS MODEL
                   (15.11.2026; develop.js says how an area gets its
                   number): `fine` holds what the strong model made of
                   single areas — one image each, whole or not kept at
                   all —, `pick` the areas that show it. */
                $fine = [];
                foreach (empty($f['strong']) && is_array($f['fine'] ?? null) ? $f['fine'] : [] as $layer) {
                    $ls = $fillSize($layer);
                    $area = is_array($layer) && is_numeric($layer['area'] ?? null) ? (int) round((float) $layer['area']) : 0;
                    if ($ls === null || $area < 1 || $area > PHOTO_DEVELOP_FILL_AREAS || isset($fine[$area])) continue;
                    $lp = $fillPieces($layer['pieces'] ?? null, $ls[0], $ls[1]);
                    if ($lp === [] || count($lp) > $room) continue;
                    $room -= count($lp);
                    $fine[$area] = ['area' => $area, 'w' => $ls[0], 'h' => $ls[1], 'pieces' => $lp] + $fillMade($layer);
                }
                if ($fine !== []) {
                    $fill['fine'] = array_values($fine);
                    $pick = [];
                    foreach (is_array($f['pick'] ?? null) ? $f['pick'] : [] as $n) {
                        $n = is_numeric($n) ? (int) round((float) $n) : 0;
                        if (isset($fine[$n])) $pick[$n] = $n;
                    }
                    sort($pick);
                    if ($pick !== []) $fill['pick'] = $pick;
                }
                $aiPieces += $roomAtFirst - $room;
            }
        }
        if ($turn !== 0 || $angle != 0 || $crop !== null || $flip !== null) {
            $out['geo'] = ['turn' => $turn, 'angle' => $angle] + ($crop !== null ? ['crop' => $crop] : [])
                + ($flip !== null ? ['flip' => $flip] : []) + ($fill !== null ? ['fill' => $fill] : []);
        }
    }
    return $out === [] ? null : $out;
}

/** The column's JSON back into settings — through the same gate. */
function photo_develop_read(mixed $json): ?array
{
    return is_string($json) && $json !== '' ? photo_develop_clean(json_decode($json, true)) : null;
}

/** At most this many edits of one picture — the key V walks them, the header numbers them. */
const PHOTO_DEVELOP_VERSIONS_MAX = 9;

/**
 * THE OTHER EDITS OF A PICTURE (11.11.2026; migration 0192):
 * { at, list } — `list` every edit in its numbered order, `at` the valid
 * one's place, whose settings live in `develop` and whose slot here stays
 * null. Every other slot goes through photo_develop_clean, so an empty
 * edit is null too; `at` alone tells the two nulls apart.
 *
 * Null for „one edit only": a single entry, or anything that is not that
 * shape. Too many are cut to the first nine, the valid one kept among them.
 *
 * `at` = -1: THE ORIGINAL IS VALID (15.11.2026: „bei der
 * Bearbeitung noch den Platz 0 … das Original dann gültig machen … die
 * Position 1 löschen und 0 ist dann gültig. Das wäre quasi das gleiche
 * wie zurücksetzen"). Then `develop` is null, every slot of `list` holds
 * an edit, and one edit alone is already worth keeping — it is not the
 * valid one, so nothing else holds it.
 *
 * @return array{at: int, list: list<array|null>}|null
 */
function photo_develop_versions_clean(mixed $v): ?array
{
    if (!is_array($v) || !is_array($v['list'] ?? null) || !is_numeric($v['at'] ?? null)) {
        return null;
    }
    $list = array_values($v['list']);
    $at = (int) $v['at'];
    if ($at < -1 || $at >= count($list) || ($at === -1 && $list === [])) {
        return null;
    }
    if (count($list) > PHOTO_DEVELOP_VERSIONS_MAX) {
        $keep = array_slice($list, 0, PHOTO_DEVELOP_VERSIONS_MAX);
        if ($at >= PHOTO_DEVELOP_VERSIONS_MAX) {
            $keep[PHOTO_DEVELOP_VERSIONS_MAX - 1] = null;
            $at = PHOTO_DEVELOP_VERSIONS_MAX - 1;
        }
        $list = $keep;
    }
    if ($at >= 0 && count($list) < 2) {
        return null;
    }
    $out = [];
    foreach ($list as $i => $d) {
        $out[] = $i === $at ? null : photo_develop_clean($d);
    }
    return ['at' => $at, 'list' => $out];
}

/** The column's JSON back — through the same gate. */
function photo_develop_versions_read(mixed $json): ?array
{
    return is_string($json) && $json !== '' ? photo_develop_versions_clean(json_decode($json, true)) : null;
}

/** Does it hold any settings — the valid edit or one of the others? */
function photo_develop_any(?array $develop, ?array $versions): bool
{
    return $develop !== null || array_filter($versions['list'] ?? []) !== [];
}

/**
 * Does the edit fit its column? `develop` is MEDIUMTEXT since migration
 * 0193 and was TEXT before — 64 KB, which the caps raised tenfold on
 * 11.11.2026 can outgrow. Until 0193 has run such an edit is refused here
 * (the caller says why) rather than cut off by the database in the middle
 * of its JSON. The other edits beside it were MEDIUMTEXT from the start.
 */
function photo_develop_fits(PDO $pdo, string $table, ?array $develop): bool
{
    if ($develop === null || strlen((string) json_encode($develop)) <= 65535) {
        return true;
    }
    static $types = [];
    if (!isset($types[$table])) {
        $stmt = $pdo->prepare(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'develop'"
        );
        $stmt->execute([$table]);
        $types[$table] = strtolower((string) $stmt->fetchColumn());
    }
    return $types[$table] !== 'text';
}

/** Store a voucher photo's development, in the owner's name — the twin of
 *  photo_media_store; false where the photo is not his or 0190 is not there. */
function photo_develop_store(PDO $pdo, int $ownerId, int $photoId, ?array $develop, array|null|false $versions = false): bool
{
    if (!photos_ready($pdo) || !column_exists($pdo, 'map_photos', 'develop')) {
        return false;
    }
    $photo = photo_load($pdo, $ownerId, $photoId);
    if ($photo === null) {
        return false;
    }
    $pdo->prepare('UPDATE map_photos SET develop = ? WHERE id = ? AND user_id = ?')
        ->execute([$develop === null ? null : json_encode($develop), $photoId, $ownerId]);
    photo_develop_versions_put($pdo, 'map_photos', $photoId, $ownerId, $versions);
    photo_point_touched($pdo, (int) $photo['point_id']);
    return true;
}

/**
 * The other edits beside it (0192) — false leaves them as they are (a
 * client that does not know them, or the column not there yet); null
 * removes them. In the same request as `develop`, so the two never stand
 * apart for longer than a breath.
 */
function photo_develop_versions_put(PDO $pdo, string $table, int $id, int $userId, array|null|false $versions): void
{
    if ($versions === false || !column_exists($pdo, $table, 'develop_versions')) {
        return;
    }
    $pdo->prepare('UPDATE ' . $table . ' SET develop_versions = ? WHERE id = ? AND user_id = ?')
        ->execute([$versions === null ? null : json_encode($versions), $id, $userId]);
}

/** The same for a cloud recording, in its owner's name. */
function cloud_file_develop_store(PDO $pdo, int $userId, int $fileId, ?array $develop, array|null|false $versions = false): bool
{
    if (!column_exists($pdo, 'map_cloud_files', 'develop')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM map_cloud_files WHERE id = ? AND user_id = ?');
    $stmt->execute([$fileId, $userId]);
    if ($stmt->fetchColumn() === false) {
        return false;
    }
    $pdo->prepare('UPDATE map_cloud_files SET develop = ? WHERE id = ? AND user_id = ?')
        ->execute([$develop === null ? null : json_encode($develop), $fileId, $userId]);
    photo_develop_versions_put($pdo, 'map_cloud_files', $fileId, $userId, $versions);
    return true;
}

/** Store a voucher photo's media, in the owner's name — the twin of
 *  photo_title_store (the viewer's „Medium" menu, 06.11.2026); false when
 *  the photo is not his or the column is not there yet. */
function photo_media_store(PDO $pdo, int $ownerId, int $photoId, ?array $media): bool
{
    if (!photos_ready($pdo) || !column_exists($pdo, 'map_photos', 'media')) {
        return false;
    }
    $photo = photo_load($pdo, $ownerId, $photoId);
    if ($photo === null) {
        return false;
    }
    $pdo->prepare('UPDATE map_photos SET media = ? WHERE id = ? AND user_id = ?')
        ->execute([$media === null ? null : json_encode($media), $photoId, $ownerId]);
    // It travels in the point baggage like scale, measures and name.
    photo_point_touched($pdo, (int) $photo['point_id']);
    return true;
}

/** Store a cloud recording's media, in the owner's name; false when the
 *  row is not his or the column is not there yet. */
function cloud_file_media_store(PDO $pdo, int $userId, int $fileId, ?array $media): bool
{
    if (!column_exists($pdo, 'map_cloud_files', 'media')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM map_cloud_files WHERE id = ? AND user_id = ?');
    $stmt->execute([$fileId, $userId]);
    if ($stmt->fetchColumn() === false) {
        return false;
    }
    $pdo->prepare('UPDATE map_cloud_files SET media = ? WHERE id = ? AND user_id = ?')
        ->execute([$media === null ? null : json_encode($media), $fileId, $userId]);
    return true;
}

/**
 * One measure's tap list, cleaned — shared by the top level and a
 * cutout's inner measures, so the two rule sets can never drift apart.
 *
 * @throws InvalidArgumentException with the same sentences the top
 *                                  level always threw
 */
function measure_points_clean(string $type, mixed $points): array
{
    $count = is_array($points) ? count($points) : 0;
    $fits = match ($type) {
        'line' => $count === 2,
        'rect', 'crop' => $count === 4,
        'path' => $count >= 2 && $count <= 100,
        'text' => $count === 1,
        'arrow' => $count === 2,
        'box' => $count === 1,
        'sector' => $count === 3,
    };
    if (!$fits) {
        throw new InvalidArgumentException(t('Eine Messung hat die falsche Punktzahl.'));
    }
    $clean = [];
    foreach ($points as $p) {
        $x = $p[0] ?? null;
        $y = $p[1] ?? null;
        if (!is_numeric($x) || !is_numeric($y)
            || !is_finite((float) $x) || !is_finite((float) $y)
            || (float) $x < 0 || (float) $y < 0) {
            throw new InvalidArgumentException(t('Ein Messpunkt liegt nicht im Bild.'));
        }
        $clean[] = [round((float) $x, 1), round((float) $y, 1)];
    }
    // One point has no length to lack: a text's anchor, a box's centre.
    if ($type !== 'text' && $type !== 'box') {
        [$a, $b] = $clean;
        if (hypot($a[0] - $b[0], $a[1] - $b[1]) < 1) {
            throw new InvalidArgumentException(t('Eine Messung hat keine Länge.'));
        }
    }
    return $clean;
}

/**
 * Bring the measure list into the stored shape — or refuse loudly.
 *
 * THE GATE FOR BOTH DIRECTIONS, like photo_scale_write. A measure is
 * {type, points, width}: 'line' with two points, 'rect' with the four
 * clicks of the spore gesture (axis first, then the flanks — the CLIENT
 * projects, the stored points stay the raw taps, so nothing is lost if
 * the projection ever changes). width is the pixel width of the image
 * the taps landed on, the same reference-width idea as the scale's
 * (migration 0090) — it is what lets a spore measured on the preview lie
 * right on the original.
 *
 * NULL back means: nothing measured, store nothing — the column stays
 * NULL instead of holding '[]' a thousand times.
 *
 * @param array $d list of {type: 'line'|'rect'|'path'|'text'|'arrow'|'box'|'sector', points: [[x,y],…], width?: int}
 */
function photo_measures_write(array $d): ?array
{
    if ($d === []) {
        return null;
    }
    // Thirty spores are a session, five hundred are a mistake — most
    // likely a client looping. The cap keeps the TEXT column honest.
    if (count($d) > 500) {
        throw new InvalidArgumentException(t('Mehr als 500 Messungen an einem Bild — das kann nicht stimmen.'));
    }
    $out = [];
    foreach ($d as $m) {
        if (!is_array($m)) {
            throw new InvalidArgumentException(t('Eine Messung ist keine.'));
        }
        $type = (string) ($m['type'] ?? '');
        if (!in_array($type, ['line', 'rect', 'path', 'text', 'arrow', 'box', 'crop', 'sector'], true)) {
            throw new InvalidArgumentException(t('Unbekannte Messart.'));
        }
        /* 'path' is the connected polyline for curved structures
           (29.08.2026 — a bent paraphysis, an ascus): two to
           a hundred taps, the length is the sum of its segments.
           'text' writes onto the picture: ONE anchor point and the
           sentence itself. 'arrow' points at something
           (05.10.2026: „Pfeil gleich mit dazu"): its tail and its tip, and
           it measures nothing. 'box' is a frame of a fixed size
           (06.10.2026 — pores per square millimetre, lamellae
           per centimetre of cap margin): its centre, and its size in µm.
           'crop' is a CUTOUT OF THE PICTURE, no measure
           (07.10.2026: „ein paar angefärbte Ascusspitzen … ein Ausschnitt
           aus einem vorhandenen Bild … ausrichten, also drehen … eine
           Bildausschnittreihe, ohne Messen"): four taps like a rectangle —
           base to tip, then the breadth — and a NAME of the user's own,
           in the layer it was cut in; the plate lines the cutouts of one
           name up in a row. It counts nowhere. */
        $clean = ['type' => $type, 'points' => measure_points_clean($type, $m['points'] ?? null)];
        if ($type === 'crop') {
            $name = trim((string) ($m['name'] ?? ''));
            if ($name !== '') {
                $clean['name'] = mb_substr($name, 0, 40);
            }
        }
        if ($type === 'text') {
            // Several lines since 07.10.2026 — one kind of break, stored as \n.
            $written = trim(str_replace(["\r\n", "\r"], "\n", (string) ($m['text'] ?? '')));
            if ($written === '') {
                throw new InvalidArgumentException(t('Der Text fehlt.'));
            }
            $clean['text'] = mb_substr($written, 0, 200);
            /* SIZE AND TURN, since the text is written straight into the
               picture and dressed by a box (05.10.2026: „mit
               einer Box versehen, um die Schriftgröße zu ziehen, und
               Rotation setzen"). The size is in the measure's own pixel
               space — beside `width`, like the points — so the writing
               belongs to the picture and grows with it under the zoom; a
               text without one is drawn at the account's font size as
               before. The angle turns it about its anchor, in degrees.
               Nonsense is dropped, not refused: a text stays a text. */
            $size = $m['size'] ?? null;
            if (is_numeric($size) && is_finite((float) $size) && (float) $size > 0 && (float) $size <= 10000) {
                $clean['size'] = round((float) $size, 1);
            }
        }
        /* THE BOX IS ITS MICROMETRES — width and height, as typed, and the
           drawing takes its pixels from the picture's scale every time. So
           unlike a text's size they are refused, not dropped: a box
           without its size is no box. Up to a metre a side, to a tenth of a
           µm. The unit says only how it was typed („1 × 1 mm", „50 × 50
           µm"), and is dropped when it is neither. */
        if ($type === 'box') {
            $um = $m['um'] ?? null;
            $sides = is_array($um) && array_is_list($um) && count($um) === 2 ? $um : null;
            foreach ($sides ?? [null] as $side) {
                if (!is_numeric($side) || !is_finite((float) $side)
                    || round((float) $side, 1) <= 0 || (float) $side > 1000000) {
                    throw new InvalidArgumentException(t('Ein Rahmen braucht Breite und Höhe.'));
                }
            }
            $clean['um'] = [round((float) $sides[0], 1), round((float) $sides[1], 1)];
            if (in_array($m['unit'] ?? null, ['mm', 'um'], true)) {
                $clean['unit'] = $m['unit'];
            }
            /* THE POINTS COUNTED IN IT (18.11.2026 — pores in a
               square millimetre, lamellae along a cut): in the frame's own
               µm from its middle, along its sides, so they go along when
               it is moved or turned. Up to 5000; one outside the frame, or
               no number, is dropped — a count stays a count. */
            $count = $m['count'] ?? null;
            if (is_array($count) && array_is_list($count)) {
                $points = [];
                foreach (array_slice($count, 0, 5000) as $q) {
                    if (!is_array($q) || !array_is_list($q) || count($q) !== 2
                        || !is_numeric($q[0]) || !is_numeric($q[1])) {
                        continue;
                    }
                    $u = (float) $q[0];
                    $v = (float) $q[1];
                    if (!is_finite($u) || !is_finite($v)
                        || abs($u) > $clean['um'][0] / 2 + 1 || abs($v) > $clean['um'][1] / 2 + 1) {
                        continue;
                    }
                    $points[] = [round($u, 1), round($v, 1)];
                }
                if ($points !== []) {
                    $clean['count'] = $points;
                }
            }
        }
        /* A SECTOR (18.11.2026 — the lamellae that reach the
           stipe, counted in a slice of the cap and taken round to the whole
           of it): its three taps — the cap's middle, the rim in the long
           direction, the rim in the short one — make the ellipse a cap
           photographed askew is. `arc` is the slice: from and to, in degrees
           of the cap as seen from straight above (photowindow.js,
           sectorGeometry), the second past the first in the way it was
           swept, at most a full turn. `count`: the points counted in it,
           in the measure's own pixels like its taps, up to 5000. */
        if ($type === 'sector') {
            $arc = $m['arc'] ?? null;
            if (is_array($arc) && array_is_list($arc) && count($arc) === 2
                && is_numeric($arc[0]) && is_numeric($arc[1])
                && is_finite((float) $arc[0]) && is_finite((float) $arc[1])) {
                $from = round((float) $arc[0], 2);
                $to = round((float) $arc[1], 2);
                if (abs($to - $from) > 0.05 && abs($to - $from) <= 360 && abs($from) <= 720) {
                    $clean['arc'] = [$from, $to];
                }
            }
            /* Two kinds since the same day („Lamellen und Lamelletten"):
               `count` the lamellae that reach the stipe, `lamellulae` the
               short ones between them — the same points, the same rules. */
            foreach (['count', 'lamellulae'] as $kind) {
                $list = $m[$kind] ?? null;
                if (!is_array($list) || !array_is_list($list)) {
                    continue;
                }
                $points = [];
                foreach (array_slice($list, 0, 5000) as $q) {
                    if (!is_array($q) || !array_is_list($q) || count($q) !== 2
                        || !is_numeric($q[0]) || !is_numeric($q[1])
                        || !is_finite((float) $q[0]) || !is_finite((float) $q[1])
                        || (float) $q[0] < 0 || (float) $q[1] < 0) {
                        continue;
                    }
                    $points[] = [round((float) $q[0], 1), round((float) $q[1], 1)];
                }
                if ($points !== [] && isset($clean['arc'])) {
                    $clean[$kind] = $points;
                }
            }
            /* THE RIM THAT CARRIES LAMELLAE (18.11.2026, measurestats.js,
               sectorRimArc): from and to in degrees like `arc` — where an
               eccentric cap has rim with lamellae at all; absent, the whole
               round. */
            $rim = $m['rim'] ?? null;
            if (is_array($rim) && array_is_list($rim) && count($rim) === 2
                && is_numeric($rim[0]) && is_numeric($rim[1])
                && is_finite((float) $rim[0]) && is_finite((float) $rim[1])) {
                $from = round((float) $rim[0], 2);
                $to = round((float) $rim[1], 2);
                if (abs($to - $from) > 0.05 && abs($to - $from) <= 360 && abs($from) <= 720) {
                    $clean['rim'] = [$from, $to];
                }
            }
            /* THE STEM WHERE IT SITS (18.11.2026, photowindow.js and
               measurestats.js, sectorRimT): where the lamellae run together
               when it is not the cap's middle — Crepidotus, oyster
               mushrooms. A point in the measure's pixels; absent is the
               middle. */
            $stem = $m['stem'] ?? null;
            if (is_array($stem) && array_is_list($stem) && count($stem) === 2
                && is_numeric($stem[0]) && is_numeric($stem[1])
                && is_finite((float) $stem[0]) && is_finite((float) $stem[1])
                && (float) $stem[0] >= 0 && (float) $stem[1] >= 0) {
                $clean['stem'] = [round((float) $stem[0], 1), round((float) $stem[1], 1)];
            }
        }
        /* The angle turns a text about its anchor and a box about its
           centre, in degrees; nonsense is dropped, upright is no angle. */
        if ($type === 'text' || $type === 'box') {
            $angle = $m['angle'] ?? null;
            if (is_numeric($angle) && is_finite((float) $angle)) {
                $turn = fmod((float) $angle, 360.0);
                if ($turn > 180) $turn -= 360;
                if ($turn <= -180) $turn += 360;
                if (round($turn, 1) != 0.0) {
                    $clean['angle'] = round($turn, 1);
                }
            }
        }
        $width = $m['width'] ?? null;
        if (is_numeric($width) && (int) $width > 0 && (int) $width <= 100000) {
            $clean['width'] = (int) $width;
        }
        /* WHAT was measured (28.08.2026): „Sporen",
           „Basidien", „Cheilozystiden" … — the statistics groups by this
           across all pictures of a find. User content, hence German and
           free-form; absent means „Sporen", because stage two measured
           nothing else and the old rows are exactly that. */
        $label = trim((string) ($m['label'] ?? ''));
        if ($label !== '') {
            $clean['label'] = mb_substr($label, 0, 40);
        }
        /* AN ARROW'S STRENGTH, one of eight steps (05.10.2026:
           „den Pfeil … stufenweise dicker machen") — anything else falls
           back to the drawing's default. */
        if ($type === 'arrow' && is_int($m['weight'] ?? null) && $m['weight'] >= 1 && $m['weight'] <= 8) {
            $clean['weight'] = $m['weight'];
        }
        /* AN ARROW'S OWN TEXT (30.10.2026: „Pfeil und Text würden
           eine Einheit bilden") — the words at its tail and their size like
           a text's; where they stand follows the arrow alone
           (measuredraw.js, arrowTextAt), so no side is kept — one stored by
           the first draft goes at the next save. An arrow without words is
           an arrow; nonsense is dropped, not refused. */
        if ($type === 'arrow') {
            $written = trim(str_replace(["\r\n", "\r"], "\n", (string) ($m['text'] ?? '')));
            if ($written !== '') {
                $clean['text'] = mb_substr($written, 0, 200);
                $size = $m['size'] ?? null;
                if (is_numeric($size) && is_finite((float) $size) && (float) $size > 0 && (float) $size <= 10000) {
                    $clean['size'] = round((float) $size, 1);
                }
            }
        }
        /* THE MARK — what kind of the layer's structure the rectangle
           frames, beside where it sits (07.10.2026: a
           chrysocystidium is a pleurocystidium and a chrysocystidium).
           User content like the label, German and verbatim; only a
           rectangle carries one, since only a rectangle counts. */
        $mark = $type === 'rect' ? trim((string) ($m['mark'] ?? '')) : '';
        if ($mark !== '') {
            $clean['mark'] = mb_substr($mark, 0, 40);
        }
        /* THE PART a line measures (07.10.2026): the germ pore,
           the ornament's height, an oil drop, a sterigma, a cystidium's
           head — a part of the layer's structure, counted under its name
           (partRows in measurestats.js). Only on a plain line; a line
           without one counts nowhere, as before. */
        $part = $type === 'line' ? trim((string) ($m['part'] ?? '')) : '';
        if ($part !== '') {
            $clean['part'] = mb_substr($part, 0, 40);
        }
        /* HOW THE MEASURE STANDS IN THE STATISTICS' TILE
           (04.10.2026: „Apiculus oben, Scheitel unten, und rechts und links
           dann auch noch gleich rum … unabhängig von der Klickreihenfolge").
           'h' swaps left and right, 'v' top and bottom, 'hv' both — the
           tile's view only; no value, no point moves. A PART MEASURE'S TILE
           TOO since 07.10.2026 („die einzelnen Bilder horizontal und
           vertikal spiegeln, um sie auszurichten"): a line with a part has
           a tile, a plain line none to mirror. */
        // A picture cutout mirrors in its tile like a spore (07.10.2026).
        if (($type === 'rect' || $type === 'crop' || isset($clean['part'])) && in_array($m['mirror'] ?? null, ['h', 'v', 'hv'], true)) {
            $clean['mirror'] = $m['mirror'];
        }
        /* THE RECTANGLE AS A CUTOUT (30.08.2026): up to two
           measures INSIDE a rect — a polyline for a curved structure's
           length, a line for its tip width. The rectangle then only
           frames what the sidebar shows; the statistics count the inner
           lengths. Inner measures share the parent's stored space, so
           they carry neither width nor label nor text of their own. */
        /* A CYSTIDIUM IN PARTS (05.11.2026, „Zystide in Teilen"):
           the frame names its method, and the method its FOUR straight
           measures — length and three breadths for Hebeloma, length,
           belly, head and neck for Conocybe (MEASURE_CYSTID_PROFILES in
           measures.php, CYSTID_PROFILES in measurestats.js; the names are
           repeated here because this file does not load measures.php).
           Until 06.11.2026 this cleaner knew neither: it refused the third
           measure with „more than two", so every cystidium failed to save,
           and it would have dropped the method besides. */
        /* AND THE PARAPHYSIS since 18.11.2026 (three measures: bent length,
           breadth, head). A method missing here is a method that never
           saves — whoever adds one to CYSTID_PROFILES adds it here too. */
        $profile = $type === 'rect' && in_array($m['profile'] ?? null, ['hebeloma', 'conocybe', 'paraphysis'], true)
            ? $m['profile'] : null;
        if ($profile !== null) {
            $clean['profile'] = $profile;
        }
        if ($type === 'rect' && is_array($m['inner'] ?? null) && $m['inner'] !== []) {
            if (count($m['inner']) > ($profile !== null ? 4 : 2)) {
                throw new InvalidArgumentException(t('Mehr als zwei Maße in einem Ausschnitt — das kann nicht stimmen.'));
            }
            $clean['inner'] = [];
            foreach ($m['inner'] as $mi) {
                $miType = is_array($mi) ? (string) ($mi['type'] ?? '') : '';
                if (!in_array($miType, ['line', 'path'], true)) {
                    throw new InvalidArgumentException(t('Unbekannte Messart.'));
                }
                $clean['inner'][] = ['type' => $miType,
                    'points' => measure_points_clean($miType, $mi['points'] ?? null)];
            }
        }
        $out[] = $clean;
    }
    return $out;
}

/**
 * Store or clear a photo's measures, in the owner's name — the twin of
 * photo_scale_store, the RIGHTS question stays the caller's.
 */
function photo_measures_store(PDO $pdo, int $ownerId, int $photoId, ?array $measures): bool
{
    if (!photos_ready($pdo) || !column_exists($pdo, 'map_photos', 'measures')) {
        return false;
    }
    $photo = photo_load($pdo, $ownerId, $photoId);
    if ($photo === null) {
        return false;
    }
    $pdo->prepare('UPDATE map_photos SET measures = ? WHERE id = ? AND user_id = ?')
        ->execute([
            $measures === null ? null : json_encode($measures, JSON_UNESCAPED_SLASHES),
            $photoId,
            $ownerId,
        ]);
    // Same nudge as the scale's: the measures travel in the point baggage.
    photo_point_touched($pdo, (int) $photo['point_id']);
    return true;
}

/**
 * The measures twin on map_cloud_files. NO photo_point_touched, for
 * cloud_file_scale_store's reason: these rows never ride a bucket.
 */
function cloud_file_measures_store(PDO $pdo, int $userId, int $fileId, ?array $measures): bool
{
    if (!table_exists($pdo, 'map_cloud_files')
        || !column_exists($pdo, 'map_cloud_files', 'measures')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM map_cloud_files WHERE id = ? AND user_id = ?');
    $stmt->execute([$fileId, $userId]);
    if ($stmt->fetchColumn() === false) {
        return false;
    }
    $pdo->prepare('UPDATE map_cloud_files SET measures = ? WHERE id = ? AND user_id = ?')
        ->execute([
            $measures === null ? null : json_encode($measures, JSON_UNESCAPED_SLASHES),
            $fileId,
            $userId,
        ]);
    return true;
}

/**
 * Store or clear a CLOUD recording's calibration (migration 0090).
 *
 * The twin of photo_scale_store, on map_cloud_files — own recordings
 * only: they live in the owner's own storage, and nobody else is ever
 * shown them.
 */
function cloud_file_scale_store(PDO $pdo, int $userId, int $fileId, ?array $scale): bool
{
    if (!table_exists($pdo, 'map_cloud_files')
        || !column_exists($pdo, 'map_cloud_files', 'scale')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM map_cloud_files WHERE id = ? AND user_id = ?');
    $stmt->execute([$fileId, $userId]);
    if ($stmt->fetchColumn() === false) {
        return false;
    }
    $pdo->prepare('UPDATE map_cloud_files SET scale = ? WHERE id = ? AND user_id = ?')
        ->execute([
            $scale === null ? null : json_encode($scale, JSON_UNESCAPED_SLASHES),
            $fileId,
            $userId,
        ]);
    /* NO photo_point_touched here, deliberately: cloud recordings do not
       travel in the point baggage. The browser fetches them per point via
       api/v1/cloud.php (cloud_files_for_point) — fresh on every opening,
       never out of an offline bucket — so there is no fingerprint that
       could go stale on this write. */
    return true;
}

/**
 * How the measuring overlay dresses — defaults, and the gate around the
 * stored deviation (migration 0091).
 *
 * RED FOR BAR AND LINES, A BLACK SEAM, 16 PX (26.09.2026:
 * „Bei der Darstellung bin ich mittlerweile wieder zurückgekehrt zu
 * Farbe des Maßstabs Rot, Farbe der Messlinien Rot, Farbe des Randes
 * Schwarz. Das würde ich so auch als Standard nehmen. Für alle, die es
 * noch nicht selbst eingestellt haben" — „und Schriftgröße 16"). The
 * values of his own account after a month of measuring with them.
 * Before, the bar was white and the lines orange (#ff8800, 29.08.2026,
 * from a preview he liked) at 14 px.
 * Whoever wants otherwise picks their own colour in Verwaltung →
 * Einstellungen → Fotos → Messen — per ACCOUNT, so the phone in
 * the woods draws like the desk. Flipping a DEFAULT retints everyone who
 * never chose — and, since only deviations are stored, also whoever
 * saved the old default unchanged: white or orange chosen on purpose
 * looks exactly like never chosen. With this house's few users that is
 * the point, not a hazard.
 */
/* THE PICTURE-NAME SUGGESTIONS (30.08.2026: „Hauptmotiv,
   Habitat, Schnittbild, Unterseite … für die Mikrobilder Sporen,
   Cheilozystiden … lass dir ein paar einfallen"). USER-CONTENT
   VOCABULARY like MEASURE_STRUCTURES, hence German and stored verbatim —
   and a DELIBERATE topical default like the species-group preselection:
   the maintainer dictated the list's existence, the account edited it in
   Einstellungen → Fotos → Motivnamen until 14.11.2026 (settings_content.php
   says why that tab went). The micro names repeat the measuring layers on
   purpose — one word per structure, wherever it appears. */
/* „BELEGFOTO" STANDS FIRST (10.09.2026: „ein einfaches
   Belegfoto brauchen wir auch zur Auswahl, was quasi nur eine Notiz ist
   für eine lange Tour, dass man sich genau an die Kollektion erinnert,
   die man eingesammelt hat"). It is the most frequent pick and the least
   demanding — no subject in the list's sense, but a reminder note. In
   front, because it is the first thing one taps in the woods. */
/* TWO LISTS SINCE 10.09.2026 („vielleicht machen wir auch
   zwei Listen, eine für makroskopische Fotos und eine für mikroskopische,
   sodass wir zuerst auswählen, ist das ein makroskopisches Foto oder ein
   mikroskopisches"). The split is not only tidiness: the kind decides
   whether a recording is shrunk before uploading (micro_shrink below) —
   a micrograph shows no details that would need 24 megapixels, a macro
   photo perhaps does. */
/* „KOLLEKTION" AND „DETAIL" (30.09.2026). Kollektion is the
   deliberately laid-out picture of a find — one standing, one lying, one
   cut through — and thus neither the Hauptmotiv (one mushroom in focus)
   nor the Belegfoto (the quick reminder note). „Übersicht" was discarded
   because it sounds like landscape and collides with Habitat and
   Standort; „Habitus" would be the technical term, but is less clear to
   beginners. Detail gathers what is no component of its own: gill
   attachment, cap margin, surface structure. Since the ledger, an
   account with a saved list gets new words by itself
   (PHOTO_TITLES_KNOWN_BEFORE). */
/* „MAKROCHEMIE" AND „MAKROCHEMIE SPORENPULVER" (30.09.2026:
   „eine makrochemische Verfärbung. Und eine makrochemische Verfärbung des
   Sporenpulvers") — the reaction of a reagent, KOH, iron sulphate,
   guaiac, Melzer. NOT „Verfärbung": on a mushroom that word often means
   the discolouring on cutting or pressing, the bluing of the boletes, and
   that is no chemistry out of a bottle. The spore print's reaction
   stands right behind Sporenpulver — that is where one looks for it.
   „MAKROCHEMIE SPP" SINCE 15.11.2026 („Können wir abkürzen
   in Makrochemie SPP … jedenfalls in Dateinamen"): the name is the file
   name's word, so the short form is the name. The old one is carried
   over in saved lists (PHOTO_TITLES_RENAMED) and in pictures (migration
   0197). */
/* THE NAMES ARE FOR FILE NAMES, NOT FOR EVERY PART
   (30.09.2026). One day they will name the pictures — „Boletus edulis
   2018-10-25 DE,HE, Rambach, Goldsteintal" and behind it number and name,
   „Sporen 1", „Sporen 2" — and for that a few coarse categories are
   needed. „Ring" and „Cortina" stood beside „Velum" for an hour and went
   again: both are a velum, and in the file name „Velum" does the same
   service. Likewise peristome, gill edge and cap margin get no name of
   their own — that is „Detail" — and gleba, subgleba, diaphragm are
   never photographed on their own: that is the Schnittbild, on which one
   labels them with „Text schreiben". Whoever wants to add a name asks
   first: does one photograph that as a picture of its own, with a
   purpose of its own? */
/* „GRÖSSENVERGLEICH" (08.10.2026: „ein Foto, das den Pilz oder
   das Motiv mit einem Maßstab zeigt, das sich dann zum Einmessen braucht
   … bei mir läuft das jetzt unter Belegfoto und da passt es nicht hin"):
   the find beside a ruler, a coin or whatever stands for a length — the
   picture one calibrates a scale on and measures the whole fruit body
   from. Next to Kollektion, the other whole-find picture. */
/* „STUDIO" (22.11.2026: „Als Makro-Label bitte noch fest Studio"): the
   find photographed at home under set light, before a plain background —
   neither the Hauptmotiv out in the woods nor the quick Belegfoto. A
   stock name, so it reaches saved lists through the ledger. */
const PHOTO_TITLES_MACRO = [
    'Belegfoto',
    'Hauptmotiv', 'Kollektion', 'Studio', 'Größenvergleich', 'Habitat', 'Standort', 'Hutoberseite',
    'Unterseite', 'Schnittbild', 'Stielbasis', 'Velum', 'Detail',
    'Makrochemie', 'Sporenpulver', 'Makrochemie SPP', 'Exsikkat',
    /* SKLEROTIUM in both lists (18.11.2026: „Als Motivname fehlt,
       glaube ich, noch Sklerotium … Das mikroskopiert man, oft um die Zellen
       zu sehen … man misst es eben nicht. Also ich würde es als Makromerkmal
       und Mikromerkmal … geben"): the tuber on the ground and its rind
       under the lens. A picture name only — no measuring layer. */
    'Sklerotium',
];
/* A STOCK NAME RENAMED: old => new. Reading a saved list puts the new
   word in the old one's place and counts it as known, so the list holds
   it once — without this the old word would stay as if it were the
   account's own, and the new one would be appended behind it as unmet.
   The pictures that carry the old word are renamed by a migration. */
const PHOTO_TITLES_RENAMED = [
    'Makrochemie Sporenpulver' => 'Makrochemie SPP',
];

/* A STOCK NAME RETIRED: dropped from every saved list on reading. Without
   this a list saved once — and adding one name of one's own saves it —
   would keep the old word as if the account had made it up, and the
   menu would offer it beside its successors. Pictures keep the word and
   still count as micrographs (photo_titles_micro_retired, read by
   pictureIsMicro / pictureScale in photowindow.js). */
const PHOTO_TITLES_RETIRED = ['Generative Hyphen', 'Skeletthyphen', 'Bindehyphen'];

/** A list with every renamed stock word in its new form — once, in the old one's place — and no retired one. */
function photo_titles_renamed(?array $list): ?array
{
    return $list === null ? null
        : photo_titles_clean(array_values(array_filter(
            array_map(static fn(string $w): string => PHOTO_TITLES_RENAMED[$w] ?? $w, $list),
            static fn(string $w): bool => !in_array($w, PHOTO_TITLES_RETIRED, true))));
}
/* THE THREE KINDS OF HYPHAE AND THE CAPILLITIUM (30.09.2026:
   „für Porlinge und Schichtpilze eventuell Skeletthyphen" — and, asked
   about the hyphal system, „alle drei", plus „bei den Stäublingen gibt
   es noch das Capillitium"). Mono-, di- or trimitic is determined from
   all three, which is why they stand together; behind the Schnallen,
   because one looks at them in the same mount. All four are measuring
   layers too (MEASURE_STRUCTURES in photowindow.js): one measures their
   width.

   NO „CHRYSOZYSTIDEN" HERE, though they are a measuring layer: they
   stood in this list for half an hour on 07.10.2026 and went again on
   the word („als Label würde ich es rausnehmen"). A
   chrysocystidium is a kind of cystidium that sits at the gill edge or
   on its face, and for a file name the place is the coarse word that
   holds it — Pleuro- or Cheilozystiden, with „Text schreiben" on the
   picture; a handful of genera's term is no picture name (30.09.2026).

   THE CUP FUNGI'S OWN TWO (07.10.2026: „uns fehlen noch ein
   paar Labels bei den Mikrofotos … bei Ascomyceten … Randhaare … die
   einzelnen Schichten, Fleischschichten … Excipulum"): the hairs at an
   apothecium's rim, photographed and measured for themselves, and the
   excipulum, the flesh of the cup in section, whose layers and cell
   shapes a key asks about. Beside Asci and Paraphysen, where they are
   looked at in the same sitting.

   „ÜBERSICHTSSCHNITT" STANDS FIRST (the same day: „Bilder, wo
   ich einen Ascus komplett in eine dünne Scheibe geschnitten habe und
   komplett fotografiert habe, mit zehnfacher Vergrößerung … vielleicht
   ein eigenes Label"). A thin section through the whole fruit body at
   low power — an apothecium, a gill, any group, which is why it is not
   „Medianschnitt", the term few beginners know and not every section
   is. First, because a description and a plate go from the whole to
   the parts.

   „VELUM" IN BOTH LISTS (09.11.2026: „soll einfach Velum
   heißen. Das brauche ich ja auch oft bei Tintlingen. Oder bei
   Lepioten"): the veil's hyphae and sphaerocysts under the microscope
   are the same subject as the veil on the find, and one word per thing
   wins over a second name. The combined list keeps it once, at
   macro's place (measure_style_read). The one cost: a macro picture named Velum
   counts as a micrograph where that is asked (pictureIsMicro in
   photowindow.js offers it the medium).

   „HYMENIUM" (09.11.2026): a squash of the fertile layer —
   asci and paraphyses side by side in one tuft, or basidia among
   cystidia — is neither picture alone. Not „Quetschpräparat": that says
   how the mount was made, and the names say WHAT one sees (the method
   belongs to the remark or the medium). Asci and Paraphysen stay for the
   picture where one of them is the point. Under both tabs.

   „KONIDIEN" (15.11.2026: „für Mikro Konidien"): the spores of
   the asexual stage, which a teleomorph's picture does not show — chains
   on a conidiophore, a powdery coat on a Hypocrea, chlamydospores in an
   Asterophora. Right after Sporen, the other spores. Under „Allgemein" in
   both tabs: they occur in asco- and basidiomycetes and belong to none of
   the parts.

   THE HYPHAE BY THEIR PLACE (18.11.2026: „wir brauchen kein
   allgemeines Label generative Hyphen, sondern wir müssen sie
   ortsgebunden als Bildlabel machen … wenn ich das nicht gleich auswähle,
   muss ich es später irgendwo auf dem Bild mit einem Text vermerken").
   The measuring layers' rule (photowindow.js, MEASURE_COLUMNS 'hyphae'):
   the name is the PLACE, the kind — generative, skeletal, binding — is
   the mark one sets while measuring. Hence the same words as those
   layers: „Hyphen hymenial" right behind the basidia (gill or tube
   trama, „vielleicht allgemeiner auch für Röhrenpilze"), „Hyphen
   Kontext" the cap's flesh, „Hyphen Stiel", „Myzel". No „Hyphen
   Hutdeckschicht" as a picture: „HDS (Hutdeckschicht)" already means its
   hyphae. The three kinds went from the list (PHOTO_TITLES_RETIRED);
   pictures that carry them keep their name — nobody knows their place.

   THE CLAMPS BY THEIR PLACE TOO (22.11.2026: „merke dir auch, wo die
   Schnallen gewählt wurden … Ob ich das bei Hut, Hymenium oder Stiel
   angeklickt habe, ist wichtig"). Two days earlier the one word
   „Schnallen" had been set into the groups Hymenium, Hut and Stiel — and
   the picture forgot at once in which of them it was clicked: one name,
   three places. Whether the clamps sit at the base of the basidia, in the
   cap's skin or on the stem's hyphae is a character of its own, and the
   hyphae's reason holds word for word (what is not chosen at the shot has
   to be written onto the picture later). So the place goes INTO THE NAME,
   as with „Hyphen Stiel": no second field beside the name, because the
   name is what the file in the cloud, the rails and the plate show — a
   place kept anywhere else would be seen nowhere. Plain „Schnallen" stays
   for the mount without a place (Allgemein, Hyphen, the polypores) and
   for every picture that already carries it.

   AND THE VEIL (the same day, asked whether the other word standing under
   two parts should follow: „Ja, Velum auch"): „Velum Hut" and „Velum
   Stiel" — the remnants on the cap and the ring or the zones on the stem
   are two mounts and two characters. MICRO ONLY: the macro list keeps its
   one „Velum", a picture of the whole fruit body shows both at once. The
   plain word stays in the micro list as well — it is the one name shared
   with macro (above, 09.11.2026) and the name of every veil picture taken
   so far — and stands under „Allgemein", like the plain clamps. */
const PHOTO_TITLES_MICRO = [
    'Übersichtsschnitt',
    'Sporen', 'Konidien', 'Basidien', 'Cheilozystiden', 'Pleurozystiden',
    'Kaulozystiden', 'Pileozystiden', 'HDS (Hutdeckschicht)', 'Velum',
    'Velum Hut', 'Velum Stiel',
    'Hymenium', 'Asci', 'Paraphysen', 'Randhaare', 'Excipulum', 'Schnallen',
    'Schnallen Hymenium', 'Schnallen Hut', 'Schnallen Stiel',
    'Hyphen hymenial', 'Hyphen Kontext', 'Hyphen Stiel', 'Myzel', 'Capillitium',
    // In both lists, as the macro one says (PHOTO_TITLES_MACRO).
    'Sklerotium',
];
/* ASCO OR BASIDIO, AND WITHIN EACH BY PART (09.11.2026: „oben
   eine Tab-Leiste, die unterscheidet zwischen Askomycet und Basidiomycet"
   — then „die Labels auch noch ein bisschen gruppieren … Hymenium, wo wir
   die Sporen haben … den Hut, wo wir die Hutdeckschicht haben … so
   allgemeine Sachen wie Übersichtsschnitt … Spezialsachen wie
   Skeletthyphen, die es eigentlich nur bei Porlingen und Corticiaceen
   gibt"). The microscope's naming window shows the micro names under two
   tabs, and in each tab under heads in this order. Keys are the heads
   (microscope.js has no say; photogallery.js gives them their words).
   A word may stand in both tabs — Sporen, Hymenium, Übersichtsschnitt.
   CLAMPS STAND WITH THE HYPHAE, not with the polypores: they are looked
   for in every basidiomycete, at the base of the basidia as much as on
   any hypha, and their absence is a character too. Skeletal and binding
   hyphae are what decides mono-, di- or trimitic, and that is asked of
   polypores and crusts. Every word of the account's list that stands in
   NO group — one it added itself — comes under „Weitere" in both tabs,
   so nothing an account wrote can vanish from the window. Keyed by the
   word, so a renamed or removed stock word simply drops out.
   CAP AND STEM ARE TWO GROUPS (18.11.2026: „ein gemeinsames
   Gruppe Hutstiele ist unübersichtlich … wenn da nur Kaulozystiden drin
   sind, dann sind da nur Kaulozystiden drin").
   A WORD MAY STAND IN TWO GROUPS OF ONE TAB (18.11.2026: „das
   ist ja nicht schlimm, wenn wir das bei der Schnellauswahl doppelt
   haben"): the groups are where one looks, not where a word lives. Velum
   stood under cap AND stem as one word until 22.11.2026 — its remnants
   sit on both, the ring on the stem; since then each carries its part
   (below).
   The hyphae stand by their place (PHOTO_TITLES_MICRO says why) — under
   „Hyphen" all of them, and each again where it sits: hymenial under
   Hymenium, Kontext under Hut, Stiel under Stiel. The polypores keep
   theirs and the clamps; mono-, di- or trimitic is the marks' business
   now.
   THE CLAMPS STAND WHEREVER ONE LOOKS FOR THEM (20.11.2026:
   „Schnallen muss auf jeden Fall zu Allgemein, zu Hymenium, zu Hut und zu
   Stiel"): one checks them in whatever mount lies under the lens — the
   gill, the cap's skin, the stem — and whoever stands in that group
   should not have to walk to „Hyphen" for the word. Last in each group,
   behind the part's own words. UNDER A PART THE WORD CARRIES THE PART
   (22.11.2026, PHOTO_TITLES_MICRO says why): „Schnallen Hymenium",
   „Schnallen Hut", „Schnallen Stiel" — each in its group only, so the
   click itself says the place. The groups that are no place keep the
   plain word. The veil the same way: „Velum Hut", „Velum Stiel", and the
   plain word under „Allgemein". */
const PHOTO_TITLES_MICRO_GROUPS = [
    'asco' => [
        'general'   => ['Übersichtsschnitt', 'Konidien', 'Sklerotium'],
        'hymenium'  => ['Hymenium', 'Sporen', 'Asci', 'Paraphysen'],
        'excipulum' => ['Excipulum', 'Randhaare'],
        // No clamps: ascomycetes have none.
        'hyphae'    => ['Hyphen Stiel', 'Myzel'],
    ],
    'basidio' => [
        'general'   => ['Übersichtsschnitt', 'Konidien', 'Sklerotium', 'Schnallen', 'Velum'],
        'hymenium'  => ['Hymenium', 'Sporen', 'Basidien', 'Cheilozystiden', 'Pleurozystiden',
                        'Schnallen Hymenium', 'Hyphen hymenial'],
        'cap'       => ['HDS (Hutdeckschicht)', 'Pileozystiden', 'Velum Hut', 'Hyphen Kontext', 'Schnallen Hut'],
        'stem'      => ['Kaulozystiden', 'Velum Stiel', 'Hyphen Stiel', 'Schnallen Stiel'],
        'hyphae'    => ['Hyphen hymenial', 'Hyphen Kontext', 'Hyphen Stiel', 'Myzel', 'Schnallen'],
        'polypores' => ['Hyphen hymenial', 'Hyphen Kontext', 'Schnallen'],
        'gasteroid' => ['Capillitium'],
    ],
];
/* Both one after the other — the order in which the gallery arranges its
   groups and the photo window shows its suggestions. Keeps the old name,
   because the old readers exist. */
// A word in both lists (Velum) stands here twice — a constant cannot
// filter; measure_style_read() keeps it once (photo_titles_both).
const PHOTO_TITLE_SUGGESTIONS = [...PHOTO_TITLES_MACRO, ...PHOTO_TITLES_MICRO];

/** Split a single, old list into the two new ones: what stands in the
 *  micro stock is micro, everything else macro. A word one made up
 *  oneself therefore lands with macro — it is only rarely wrong. */
function photo_titles_split(array $titles): array
{
    $micro = array_map('mb_strtolower', PHOTO_TITLES_MICRO);
    $out = ['macro' => [], 'micro' => []];
    foreach ($titles as $t) {
        $out[in_array(mb_strtolower($t), $micro, true) ? 'micro' : 'macro'][] = $t;
    }
    return $out;
}

/** Clean a list from the form or the bundle. */
function photo_titles_clean(mixed $titles): ?array
{
    if (!is_array($titles)) {
        return null;
    }
    $clean = [];
    foreach ($titles as $title) {
        $title = mb_substr(trim((string) $title), 0, 120);
        if ($title !== '' && !in_array($title, $clean, true)) {
            $clean[] = $title;
        }
    }
    return $clean;
}

/* A NEW STOCK NAME REACHES EVERY ACCOUNT (30.09.2026: „ich
   werde noch mehr irgendwann dazufügen, auch Mikrofotos … dass die neuen
   Labels auch bei allen anderen immer ankommen"). An account that never
   saved its lists rides the stock anyway; one that did kept its saved
   list and never saw a name added after it — „Kollektion" and „Detail"
   would have stayed away from it for good.

   NOT A MIGRATION PER NAME, which is what he first suggested: one would
   carry today's names over once, the next name would need the next one,
   and the day somebody forgets it nobody notices. Instead a saved list
   carries WHICH STOCK NAMES IT KNEW (photo_titles_macro_known /
   _micro_known, in the same style bundle). Reading appends every stock
   name the list never met, at its end; a name the list knew and does not
   hold was taken out on purpose and stays out.

   THE LISTS SAVED BEFORE THE LEDGER knew the stock of their time — the
   macro and the micro list as they stood before the first names of
   30.09.2026. Measured when it was built: no account had saved a list
   yet; the baseline is for the one that might have.

   WRITTEN OUT, NEVER THE CONSTANT: the micro half first said
   PHOTO_TITLES_MICRO, and the next micro name would have joined the
   baseline the moment it joined the stock — an old list would have
   „known" it and never got it. Caught when the three hyphae went in. */
const PHOTO_TITLES_KNOWN_BEFORE = [
    'photo_titles_macro' => ['Belegfoto', 'Hauptmotiv', 'Habitat', 'Standort', 'Hutoberseite',
                             'Unterseite', 'Schnittbild', 'Stielbasis', 'Velum', 'Sporenpulver', 'Exsikkat'],
    'photo_titles_micro' => ['Sporen', 'Basidien', 'Cheilozystiden', 'Pleurozystiden', 'Kaulozystiden',
                             'Pileozystiden', 'HDS (Hutdeckschicht)', 'Asci', 'Paraphysen', 'Schnallen'],
];

/** The stock names a saved list has never met — neither holds nor knew —
 *  in stock order. Compared without case, as photo_titles_split does. */
function photo_titles_unmet(array $list, array $known, array $stock): array
{
    $met = array_flip(array_map('mb_strtolower', [...$list, ...$known]));
    return array_values(array_filter($stock, static fn(string $t): bool => !isset($met[mb_strtolower($t)])));
}

/** A plate's style when the account never set one. */
const PLATE_STYLE_DEFAULTS = ['gutter' => 0.6, 'gutter_color' => '#ffffff', 'text_pt' => 10, 'details_pt' => 9, 'values_pt' => 9];

/* BLACK ON WHITE SINCE 06.10.2026 („unsere neue Standard-
   Textfarbe und Maßstabfarbe ist schwarz. Die Umrandung ist weiß. Hier
   machen wir einfach nur noch schwarz-weiß. Das passt dann auch gut zu
   unseren Tafeln"). Until then red lines and bar with a black seam. Only
   deviations are stored, so every account that never chose its own
   colours takes the new pair; a chosen colour stays the account's. */
const MEASURE_STYLE_DEFAULTS = [
    'scale_color' => '#000000',   // the bar together with its lettering
    // The bar's OWN edge (26.09.2026: „eigentlich bräuchten
    // wir bei Farbe des Maßstabs zwei Farbfelder … Dadurch muss der Rand
    // immer zur Hauptfarbe passen. Wenn wir einheitlich die Randfarbe für
    // beide Farben nehmen, muss man immer einen Kompromiss schließen").
    // Before, the bar's edge was fixed black in the stylesheet.
    'scale_seam_color' => '#ffffff',
    'line_color'  => '#000000',   // measuring lines, rectangles, their labels
    // The SEAM under lines and lettering (28.08.2026: „die
    // Umrandungsfarbe … dass ich die auch selbst wählen kann") — white
    // by default since 06.10.2026, the light half of the two-tone stroke
    // that reads on any photograph. The LINES' edge alone since the bar
    // got its own; the key keeps its name because stored styles carry it.
    'seam_color'  => '#ffffff',
    'font_px'     => 16,          // label size on the screen
    // The margin around the cut-outs, PER LAYER in percent
    // (28.08.2026: first a slider, then „in der Datenbank … sowie in der
    // JSON. Natürlich pro Ebene") — a map label → percent, empty when
    // every layer sits at the snug 118: ornamented ascospores want 250,
    // basidia do not. Account-wide like the rest of the style.
    'spore_air'   => [],
    /* THE LAYERS WHOSE BENT LENGTHS ARE READ STRAIGHT, end to end, as the
       books measure an allantoid spore (07.10.2026: „zwei
       Messstatistiken … einmal die klassische, einmal die neue, die
       wirklich mit Polygon die Länge misst"). A list of labels, empty =
       along the bend everywhere, as before; switched in the photo
       window's statistics per layer, like the margin beside it
       (measurestats.js, frameValues, says what changes). */
    'straight_length' => [],
    /* WHICH WAY A PART MEASURE'S LINE POINTS IN ITS TILES
       (07.10.2026: „eine Hauptposition, wie diese Strecke in den Kacheln
       dargestellt wird, also ob sie von rechts nach links läuft, von links
       nach rechts, von oben nach unten"): from the first click to the
       second, per part — „Sporen · Ornamenthöhe" => 'up'. Absent = 'right',
       as the tiles began. */
    'part_direction' => [],
    // The name suggestions above, replaceable wholesale per account —
    // in the style bundle because Einstellungen → Fotos → Motivnamen was their form
    // and the photo window their reader, the same two homes as the rest.
    'photo_titles_macro' => PHOTO_TITLES_MACRO,
    'photo_titles_micro' => PHOTO_TITLES_MICRO,
    // What a reader has now been offered: the whole stock, since reading
    // appends whatever a saved list had not met. Written back with the
    // list, so a later save knows which absences are choices.
    'photo_titles_macro_known' => PHOTO_TITLES_MACRO,
    'photo_titles_micro_known' => PHOTO_TITLES_MICRO,
    // Retired micro words — pictures named so are micrographs all the same.
    'photo_titles_micro_retired' => PHOTO_TITLES_RETIRED,
    // Both together, DERIVED and never stored: the reader that needs only
    // one order (gallery, photo window) goes on reading here.
    'photo_titles' => PHOTO_TITLE_SUGGESTIONS,
    // The micro names by tab and head (the microscope's naming window).
    'photo_titles_micro_groups' => PHOTO_TITLES_MICRO_GROUPS,
    /* MICROGRAPHS ARE SHRUNK BEFORE UPLOADING
       (10.09.2026: „mikroskopische Fotos sind bei mir in der Größe viel
       zu groß. Sie zeigen ja eigentlich keine Details, die kann man ruhig
       verkleinern"). To a 2048 px long edge, like the preview he measures
       on anyway. The scale does not suffer: the recording remembers its
       CAMERA DIMENSIONS (0095), and all measuring computes through the
       ratio — for the shrunk file exactly as for the preview. As a
       switch, because a camera with few pixels gains nothing from it. */
    'micro_shrink' => true,
    /* „AUFNEHMEN" IN FULL SIZE OVER MOBILE DATA TOO (03.10.2026:
       „im Wald hat natürlich keiner WLAN … allerdings könnten da manche
       Nutzer etwas dagegen haben, die keinen Unlimited-Vertrag haben").
       With a cloud that takes the browser's uploads and with reception, a
       shot goes up whole, straight into the cloud (map.js, the camera's
       handler) — on Wi-Fi always, over mobile data only with this tick.
       OFF by default: five to ten megabytes a shot is a cost nobody should
       find on the bill without having chosen it. Without reception a shot
       is always shrunk, so the phone's storage does not fill up. */
    'full_size_mobile' => false,
    /* THE WARNING BEFORE A MICROGRAPH GOES UP SHRUNK, seen and waved off
       (04.10.2026: „bei beiden Sachen ein Haken, ich habe
       verstanden, nicht mehr anzeigen … weil zum hundertsten Mal eine
       Warnnachricht nervt, wenn man genau weiß, was man tut"). On the
       account, not the device — his wish, so the phone and the desk both
       stop asking. Set from the map's window (api/v1/measure_style.php). */
    'micro_shrink_warned' => false,
    /* THE PLATES' OWN HAND (06.10.2026: a style remembered,
       but only „das Minimum an Gemeinsamkeiten" — „jede Art, jede Gattung
       evtl. individuelle Merkmale"): the lines between the pictures and
       their colour, and the size each kind of words on a plate starts at
       — what the editor last set (plates.js), so the next plate begins
       there. Deliberately NOT the bar's corner („ziemlich individuell pro
       Bild"), the values' lines („individuell pro Messreihe") or the
       ticked details (a name above and the rest below are two fields with
       two sets of ticks).
       SINCE 08.11.2026 ALSO THE HAND PER KIND, under 'hand' (
       „können sich die ganzen Tafeleinstellungen auch ihre Einstellungen
       merken … die Fundangaben, wie ich die auswähle, ob ich die einzeilig
       will … ob ich den Maßstab im Bild haben will oder als Zeile
       drunter"): the details' ticks and arrangement, a words' ground, a
       row's lines, margin and bar, a picture's medium and label — the
       last one set, as the start of the next (plates.js, HAND_KEYS). The
       bar's corner on a picture stays out, as decided above; the values'
       lines came in the same evening („Die Zeilen der Werte auch
       merken") — the three of every layer, never its part lines.
       plate_hand_clean says what each kind may hold. */
    'plate' => PLATE_STYLE_DEFAULTS,
    /* ONE'S OWN THINGS OF A KNOWN LENGTH (07.10.2026: „zwei,
       drei Werte, die man selbst setzen kann und benennen kann. Wie zum
       Beispiel Feuerzeug … was halt so in der Fotoausrüstung rumfliegt").
       [{name, um}], in the order made; offered as buttons in the photo
       window's „Maßstab setzen" beside the coins, made and removed there
       (api/v1/measure_style.php). On the account, so the phone in the
       woods knows the lighter the desk was told about. */
    'scale_objects' => [],
    /* HOW A DEVELOPED PICTURE LEAVES THE APP (08.11.2026) — author, rights,
       file name and the watermark templates; includes/export_style.php. */
    'export' => EXPORT_STYLE_DEFAULTS,
];

/** At most this many own things — a row of buttons, not a catalogue. */
const SCALE_OBJECTS_MAX = 6;

/** The own things into shape: a name of up to 40 characters and a length
 *  between a micrometre and a metre; whatever is off falls away. */
function scale_objects_clean(mixed $list): array
{
    $clean = [];
    foreach (is_array($list) ? $list : [] as $o) {
        if (!is_array($o) || count($clean) >= SCALE_OBJECTS_MAX) {
            continue;
        }
        $name = mb_substr(trim((string) ($o['name'] ?? '')), 0, 40);
        $um = is_numeric($o['um'] ?? null) ? (float) $o['um'] : 0.0;
        if ($name !== '' && $um >= 1 && $um <= 1000000) {
            $clean[] = ['name' => $name, 'um' => round($um, 3)];
        }
    }
    return $clean;
}

/** A plate style into its ranges; whatever is off falls back to the default. */
function plate_style_clean(mixed $p): array
{
    $out = PLATE_STYLE_DEFAULTS;
    if (!is_array($p)) {
        return $out;
    }
    if (is_numeric($p['gutter'] ?? null) && (float) $p['gutter'] >= 0 && (float) $p['gutter'] <= 5) {
        $out['gutter'] = round((float) $p['gutter'], 1);
    }
    if (in_array($p['gutter_color'] ?? null, ['#ffffff', '#000000'], true)) {
        $out['gutter_color'] = $p['gutter_color'];
    }
    foreach (['text_pt', 'details_pt', 'values_pt'] as $key) {
        if (is_numeric($p[$key] ?? null) && (int) $p[$key] >= 4 && (int) $p[$key] <= 72) {
            $out[$key] = (int) $p[$key];
        }
    }
    // Only where something was set — an empty hand is the default and is not stored.
    $hand = plate_hand_clean($p['hand'] ?? null);
    if ($hand !== []) {
        $out['hand'] = $hand;
    }
    return $out;
}

/**
 * A cell's ink or soft ground per piece of writing (plates.js, writingOf):
 * {bar, med, label}, only what differs from the default kept. The first
 * build of 09.11.2026 held one word or one bool for the whole cell — it
 * still counts, for all three.
 */
function plate_writing(mixed $v, string $what): array
{
    if (is_string($v) || is_bool($v)) {
        $v = ['bar' => $v, 'med' => $v, 'label' => $v];
    }
    $out = [];
    foreach (['bar', 'med', 'label'] as $key) {
        $x = is_array($v) ? ($v[$key] ?? null) : null;
        /* A soft ground is a choice for all three since 20.11.2026, each
           with its default (plateparts.js, softWanted): the bar's true and
           a medium's or label's false are what get stored. */
        if ($what === 'ink' ? in_array($x, ['black', 'white'], true) : is_bool($x)) {
            $out[$key] = $x;
        }
    }
    return $out;
}

/**
 * THE HAND PER KIND (plates.js, HAND_KEYS): what the plate editor last set
 * for each kind of thing, as the start of the next. Whatever a kind may not
 * hold, or holds out of range, is left out — the editor's default stands
 * in for it. The details' fields are kept as words and not checked against
 * plates.php's list: this file does not load it, and the editor shows only
 * the fields it knows.
 */
function plate_hand_clean(mixed $h): array
{
    if (!is_array($h)) {
        return [];
    }
    $grounds = ['none', 'white', 'black'];
    $corners = ['br', 'bl', 'tr', 'tl', 'off'];
    $words = static function (array $k) use ($grounds): array {
        $o = [];
        if (in_array($k['ground'] ?? null, $grounds, true)) {
            $o['ground'] = $k['ground'];
        }
        if (is_numeric($k['groundOpacity'] ?? null) && $k['groundOpacity'] >= 10 && $k['groundOpacity'] <= 100) {
            $o['groundOpacity'] = (int) (round($k['groundOpacity'] / 5) * 5);
        }
        return $o;
    };
    $out = [];
    if (is_array($h['info'] ?? null)) {
        $k = $h['info'];
        $o = [];
        if (is_array($k['fields'] ?? null)) {
            // Each key once — but a Leerzeile ('gap', plates.php) as often as it stands.
            $o['fields'] = [];
            foreach ($k['fields'] as $f) {
                if (is_string($f) && preg_match('/^[a-z_]{1,30}$/', $f) === 1
                    && ($f === 'gap' ? count(array_keys($o['fields'], 'gap', true)) < 10 : !in_array($f, $o['fields'], true))) {
                    $o['fields'][] = $f;
                }
            }
        }
        if (is_bool($k['labels'] ?? null) || ($k['labels'] ?? null) === 'icons') {
            $o['labels'] = $k['labels'];
        }
        if (in_array($k['flow'] ?? null, ['lines', 'one'], true)) {
            $o['flow'] = $k['flow'];
        }
        $out['info'] = $o + $words($k);
    }
    if (is_array($h['values'] ?? null)) {
        $o = is_bool($h['values']['name'] ?? null) ? ['name' => $h['values']['name']] : [];
        if (is_array($h['values']['lines'] ?? null)) {
            // The three of every layer, in their order; part lines are a layer's own.
            $o['lines'] = array_values(array_intersect(['range', 'qn', 'mean', 'spread'], $h['values']['lines']));
        }
        $out['values'] = $o + $words($h['values']);
    }
    // A keying way's choices (plates.php, kind 'keying'; 09.11.2026).
    if (is_array($h['keying'] ?? null)) {
        $k = $h['keying'];
        $o = in_array($k['show'] ?? null, ['both', 'path', 'remark'], true) ? ['show' => $k['show']] : [];
        if (in_array($k['label'] ?? null, ['both', 'book', 'key'], true)) {
            $o['label'] = $k['label'];
        }
        $out['keying'] = $o + $words($k);
    }
    if (is_array($h['row'] ?? null)) {
        $k = $h['row'];
        $o = [];
        foreach (['gap' => 5, 'pad' => 10] as $key => $max) {
            if (is_numeric($k[$key] ?? null) && $k[$key] >= 0 && $k[$key] <= $max) {
                $o[$key] = round((float) $k[$key], 1);
            }
        }
        if (in_array($k['gapColor'] ?? null, ['#ffffff', '#000000'], true)) {
            $o['gapColor'] = $k['gapColor'];
        }
        $out['row'] = $o;
    }
    if (is_array($h['rowCell'] ?? null)) {
        $k = $h['rowCell'];
        $o = in_array($k['bar'] ?? null, $corners, true) ? ['bar' => $k['bar']] : [];
        if (is_bool($k['barOver'] ?? null)) {
            $o['barOver'] = $k['barOver'];
        }
        // A row's medium and label since 09.11.2026, as a picture's.
        foreach (['med', 'label'] as $key) {
            if (in_array($k[$key] ?? null, $corners, true)) {
                $o[$key] = $k[$key];
            }
        }
        foreach (['medFull', 'labelSub', 'medLine', 'labelLine', 'labelShort'] as $key) {
            if (is_bool($k[$key] ?? null)) {
                $o[$key] = $k[$key];
            }
        }
        // Each piece's ink and soft ground (09.11.2026; plates.php, plate_writing).
        foreach (['ink', 'soft'] as $key) {
            // Only what was chosen — an empty one would go out as [] (plates.php, plate_node).
            $w = plate_writing($k[$key] ?? null, $key);
            if ($w !== []) {
                $o[$key] = $w;
            }
        }
        $out['rowCell'] = $o;
    }
    // A distribution's ground and an inset's frame (09.11.2026).
    $o = [];
    if (in_array($h['plot']['ground'] ?? null, ['white', 'black'], true)) {
        $o['ground'] = $h['plot']['ground'];
    }
    if (is_bool($h['plot']['spread'] ?? null)) {
        $o['spread'] = $h['plot']['spread'];
    }
    $op = $h['plot']['groundOpacity'] ?? null;
    if (is_numeric($op) && $op >= 10 && $op <= 100) {
        $o['groundOpacity'] = (int) (round($op / 5) * 5);
    }
    if ($o !== []) {
        $out['plot'] = $o;
    }
    if (is_bool($h['inset']['frame'] ?? null)) {
        $out['inset'] = ['frame' => $h['inset']['frame']];
    }
    if (is_array($h['pic'] ?? null)) {
        $k = $h['pic'];
        $o = [];
        foreach (['med', 'label'] as $key) {
            if (in_array($k[$key] ?? null, $corners, true)) {
                $o[$key] = $k[$key];
            }
        }
        foreach (['medFull', 'labelShort'] as $key) {
            if (is_bool($k[$key] ?? null)) {
                $o[$key] = $k[$key];
            }
        }
        foreach (['ink', 'soft'] as $key) {
            // Only what was chosen — an empty one would go out as [] (plates.php, plate_node).
            $w = plate_writing($k[$key] ?? null, $key);
            if ($w !== []) {
                $o[$key] = $w;
            }
        }
        $out['pic'] = $o;
    }
    return array_filter($out, static fn(array $o): bool => $o !== []);
}

/** The margin all layers start from — what an absent entry means. */
const SPORE_AIR_DEFAULT = 118;

/* THE MARGIN PER FIND (migration 0156, 07.10.2026: „die bessere
   Lösung wäre individuell … geräteübergreifend"). One row per person,
   find and layer — „Sporen", or a part's tab „Sporen · Keimporus". A find
   without its own falls back to the account's per layer (spore_air above),
   then to the stock; the photo window merges the three in that order. */
function measure_air_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_measure_air');
}

/** One person's margins on one find: label => percent. */
function measure_air_for_point(PDO $pdo, int $userId, int $pointId): array
{
    if (!measure_air_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT label, air FROM map_measure_air WHERE user_id = ? AND point_id = ?');
    $stmt->execute([$userId, $pointId]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(string) $row['label']] = (int) $row['air'];
    }
    return $out;
}

/** Set one layer's margin on one find — 100–300 percent, or refused. */
function measure_air_set(PDO $pdo, int $userId, int $pointId, string $label, int $air): void
{
    $label = mb_substr(trim($label), 0, 40);
    if ($label === '' || $air < 100 || $air > 300) {
        throw new InvalidArgumentException(t('Ebene und Rand (100–300) werden gebraucht.'));
    }
    $pdo->prepare('INSERT INTO map_measure_air (user_id, point_id, label, air, updated_at) VALUES (?, ?, ?, ?, NOW())
                   ON DUPLICATE KEY UPDATE air = VALUES(air), updated_at = VALUES(updated_at)')
        ->execute([$userId, $pointId, $label, $air]);
}

/** Both lists one after the other; a word in both (Velum) once, where
 *  macro has it. */
function photo_titles_both(array $macro, array $micro): array
{
    return [...$macro, ...array_values(array_diff($micro, $macro))];
}

/** The stored deviation, completed with the defaults. Bad values fall
 *  back silently — a style must never be able to break the drawing. */
function measure_style_read(mixed $stored): array
{
    $d = is_string($stored) ? json_decode($stored, true) : null;
    $out = MEASURE_STYLE_DEFAULTS;
    $out['photo_titles'] = photo_titles_both(PHOTO_TITLES_MACRO, PHOTO_TITLES_MICRO);
    if (!is_array($d)) {
        return $out;
    }
    foreach (['scale_color', 'scale_seam_color', 'line_color', 'seam_color'] as $color) {
        $value = (string) ($d[$color] ?? '');
        if (preg_match('/^#[0-9a-f]{6}$/i', $value)) {
            $out[$color] = strtolower($value);
        }
    }
    $px = $d['font_px'] ?? null;
    if (is_numeric($px) && (int) $px >= 8 && (int) $px <= 36) {
        $out['font_px'] = (int) $px;
    }
    $air = $d['spore_air'] ?? null;
    if (is_array($air)) {
        $clean = [];
        foreach ($air as $label => $value) {
            $label = mb_substr(trim((string) $label), 0, 40);
            if ($label !== '' && is_numeric($value)
                && (int) $value >= 100 && (int) $value <= 300) {
                $clean[$label] = (int) $value;
            }
        }
        $out['spore_air'] = $clean;
    }
    $out['straight_length'] = straight_length_clean($d['straight_length'] ?? null);
    $out['part_direction'] = part_direction_clean($d['part_direction'] ?? null);
    /* The two lists — and the TAKEOVER of the one old list: whoever had
       a list of their own before 10.09.2026 finds it again, split
       (photo_titles_split). An account that emptied a list on purpose
       keeps it empty — absence of the KEY means default, an empty list
       means „keine". */
    $macro = photo_titles_renamed(photo_titles_clean($d['photo_titles_macro'] ?? null));
    $micro = photo_titles_renamed(photo_titles_clean($d['photo_titles_micro'] ?? null));
    if ($macro === null && $micro === null) {
        $legacy = photo_titles_renamed(photo_titles_clean($d['photo_titles'] ?? null));
        if ($legacy !== null) {
            $split = photo_titles_split($legacy);
            $macro = $split['macro'];
            $micro = $split['micro'];
        }
    }
    // A saved list gets the stock names it never met, at its end.
    foreach (['photo_titles_macro' => [$macro, PHOTO_TITLES_MACRO],
              'photo_titles_micro' => [$micro, PHOTO_TITLES_MICRO]] as $key => [$list, $stock]) {
        if ($list === null) {
            continue;
        }
        // A renamed word that was known is known in its new form (PHOTO_TITLES_RENAMED).
        $known = photo_titles_renamed(photo_titles_clean($d[$key . '_known'] ?? null)) ?? PHOTO_TITLES_KNOWN_BEFORE[$key];
        $out[$key] = [...$list, ...photo_titles_unmet($list, $known, $stock)];
    }
    $out['photo_titles'] = photo_titles_both($out['photo_titles_macro'], $out['photo_titles_micro']);
    if (array_key_exists('micro_shrink', $d)) {
        $out['micro_shrink'] = (bool) $d['micro_shrink'];
    }
    if (array_key_exists('full_size_mobile', $d)) {
        $out['full_size_mobile'] = (bool) $d['full_size_mobile'];
    }
    if (array_key_exists('micro_shrink_warned', $d)) {
        $out['micro_shrink_warned'] = (bool) $d['micro_shrink_warned'];
    }
    $out['plate'] = plate_style_clean($d['plate'] ?? null);
    $out['scale_objects'] = scale_objects_clean($d['scale_objects'] ?? null);
    $out['export'] = export_style_clean($d['export'] ?? null);
    return $out;
}

/** The layers read straight — labels as the measures carry them, each
 *  once; anything else falls away, a list cannot be wrong enough to refuse. */
function straight_length_clean(mixed $labels): array
{
    $clean = [];
    foreach (is_array($labels) ? $labels : [] as $label) {
        $label = is_string($label) ? mb_substr(trim($label), 0, 40) : '';
        if ($label !== '' && !in_array($label, $clean, true) && count($clean) < 100) {
            $clean[] = $label;
        }
    }
    return $clean;
}

/** The parts' directions — „<layer> · <part>" => right|left|up|down;
 *  anything else falls away. */
function part_direction_clean(mixed $map): array
{
    $clean = [];
    foreach (is_array($map) ? $map : [] as $key => $value) {
        $key = mb_substr(trim((string) $key), 0, 40);
        if ($key !== '' && in_array($value, ['right', 'left', 'up', 'down'], true) && count($clean) < 200) {
            $clean[$key] = $value;
        }
    }
    return $clean;
}

/**
 * Bring a submitted style into the stored shape — or refuse loudly.
 * NULL back means: everything at default, store nothing.
 */
function measure_style_write(array $d): ?string
{
    $out = [];
    foreach (['scale_color', 'scale_seam_color', 'line_color', 'seam_color'] as $color) {
        $value = strtolower(trim((string) ($d[$color] ?? '')));
        if (!preg_match('/^#[0-9a-f]{6}$/', $value)) {
            throw new InvalidArgumentException(t('Eine der Farben ist keine.'));
        }
        if ($value !== MEASURE_STYLE_DEFAULTS[$color]) {
            $out[$color] = $value;
        }
    }
    $px = (int) ($d['font_px'] ?? 0);
    if ($px < 8 || $px > 36) {
        throw new InvalidArgumentException(t('Die Schriftgröße muss zwischen 8 und 36 liegen.'));
    }
    if ($px !== MEASURE_STYLE_DEFAULTS['font_px']) {
        $out['font_px'] = $px;
    }
    /* The per-layer margins ride along as they are — the admin form
       does not speak them (the pane's slider does, through
       api/v1/measure_style.php), so the caller passes the STORED map
       through and a style save cannot wipe it. Only deviations from
       the shared default are kept. */
    $air = $d['spore_air'] ?? null;
    if (is_array($air)) {
        $clean = [];
        foreach ($air as $label => $value) {
            $label = mb_substr(trim((string) $label), 0, 40);
            $value = (int) $value;
            if ($label === '' || $value < 100 || $value > 300) {
                throw new InvalidArgumentException(t('Der Rand je Ebene muss zwischen 100 und 300 Prozent liegen.'));
            }
            if ($value !== SPORE_AIR_DEFAULT) {
                $clean[$label] = $value;
            }
        }
        if ($clean !== []) {
            $out['spore_air'] = $clean;
        }
    }
    // Carried through like the margins — the pane's switch is their editor.
    $straight = straight_length_clean($d['straight_length'] ?? null);
    if ($straight !== []) {
        $out['straight_length'] = $straight;
    }
    // The same for the parts' directions; 'right' is the default and not kept.
    $direction = array_filter(part_direction_clean($d['part_direction'] ?? null), static fn(string $v): bool => $v !== 'right');
    if ($direction !== []) {
        $out['part_direction'] = $direction;
    }
    /* The name suggestions: stored only as a DEVIATION from the stock
       list — whoever never touched them rides every future default
       change; an emptied list IS a deviation and stays empty. A stored
       list takes along which stock names its sender knew (see
       PHOTO_TITLES_KNOWN_BEFORE): what measure_style_read handed out, or
       the settings form's hidden field — a form left open across a
       deployment knew the stock of its own time, not today's. Without
       either, the baseline: a name added since then still arrives. */
    $macro = photo_titles_clean($d['photo_titles_macro'] ?? null);
    $micro = photo_titles_clean($d['photo_titles_micro'] ?? null);
    // A caller that still knows only the old ONE list gets it split —
    // the same path as on reading.
    if ($macro === null && $micro === null) {
        $legacy = photo_titles_clean($d['photo_titles'] ?? null);
        if ($legacy !== null) {
            $split = photo_titles_split($legacy);
            $macro = $split['macro'];
            $micro = $split['micro'];
        }
    }
    foreach ([['photo_titles_macro', $macro, PHOTO_TITLES_MACRO],
              ['photo_titles_micro', $micro, PHOTO_TITLES_MICRO]] as [$key, $clean, $stock]) {
        if ($clean === null) {
            continue;
        }
        if (count($clean) > 200) {
            throw new InvalidArgumentException(t('Mehr als 200 Bildtitel-Vorgaben — das kann nicht stimmen.'));
        }
        if ($clean !== $stock) {
            $out[$key] = $clean;
            $out[$key . '_known'] = photo_titles_clean($d[$key . '_known'] ?? null)
                ?? PHOTO_TITLES_KNOWN_BEFORE[$key];
        }
    }
    // Only the deviation: the default is ON, what gets stored is the OFF.
    if (array_key_exists('micro_shrink', $d) && !(bool) $d['micro_shrink']) {
        $out['micro_shrink'] = false;
    }
    // The other way round: the default is OFF, what gets stored is the ON.
    if (!empty($d['full_size_mobile'])) {
        $out['full_size_mobile'] = true;
    }
    // The same: unseen is the default, what gets stored is the „seen".
    if (!empty($d['micro_shrink_warned'])) {
        $out['micro_shrink_warned'] = true;
    }
    // The plates' hand: only where it differs from the default.
    $plate = plate_style_clean($d['plate'] ?? null);
    if ($plate !== PLATE_STYLE_DEFAULTS) {
        $out['plate'] = $plate;
    }
    $objects = scale_objects_clean($d['scale_objects'] ?? null);
    if ($objects !== []) {
        $out['scale_objects'] = $objects;
    }
    $export = export_style_clean($d['export'] ?? null);
    if ($export !== EXPORT_STYLE_DEFAULTS) {
        $out['export'] = $export;
    }
    return $out === [] ? null : json_encode($out, JSON_UNESCAPED_SLASHES);
}

/**
 * The sidecar file's name — the image's full name plus a suffix, so the
 * two sort side by side and the pairing is unambiguous even for a human
 * browsing the folder: „IMG_1234.jpg" and „IMG_1234.jpg.messwerte.json".
 *
 * German like everything the user reads in their own storage; and
 * „messwerte", not „massstab": the file is built to carry the MEASURES
 * too, the day they learn to persist.
 */
function measure_sidecar_name(string $imageName): string
{
    return $imageName . '.messwerte.json';
}

/**
 * What the sidecar holds — the safety copy beside the picture
 * (28.08.2026: „dass das immer zusammen ist und nicht bei uns
 * in der Datenbank alleine … bei einem Datenbank-Crash das alles wieder
 * aus der Cloud der Nutzer holen").
 *
 * PRETTY-PRINTED, because a person may open this in three years with
 * nothing but a text editor and the wish to understand it. 'format'
 * counts the shape; since migration 0092 'measures' carries the real
 * list — the slot was reserved from the first day, so files written
 * before and after read the same.
 */
function measure_sidecar_json(?array $scale, array $measures,
                              string $imageName, ?int $width, ?int $height,
                              ?string $title = null, ?string $original = null,
                              ?array $media = null, ?array $develop = null): string
{
    // What the camera called it (04.10.2026): since the names are derived
    // (cloud_names.php), „IMG_1234.jpg" stands nowhere in the folder any
    // more — and it is how the raw file on the card is found again.
    $image = ['name' => $imageName] + ($original !== null && $original !== '' && $original !== $imageName
        ? ['original' => $original] : []) + ['width' => $width, 'height' => $height];
    return json_encode([
        'format'   => 1,
        'image'    => $image,
        // The picture's name (0097) — the very word the later renaming
        // will want, so the safety copy carries it from day one.
        'title'    => $title,
        'scale'    => $scale,
        'measures' => $measures,
        /* The media it was taken in (0187, 04.11.2026: „wie die
           Maße … auch in die JSON-Datei speichern"), as their keys — what a
           plate shows beside the picture, and what a crashed database
           would want back. Only where there are any: older files stay as
           they were. */
    ] + ($media !== null ? ['media' => $media] : [])
      /* How it is developed (0190, „das muss natürlich dann auch in die
         JSON-Datei rein") — the original never changes, so this is all
         there is to the edited picture. Only where it is developed. */
      + ($develop !== null ? ['develop' => $develop, 'develop_help' => measure_develop_help()] : []),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * WHAT THE DEVELOP KEYS MEAN, beside them in the sidecar
 * (09.11.2026: „wenn ich mal den Cloud-Ordner eines Fundes einfach
 * rauskopiere … in den JSON-Einstellungen zur Not sehe, was ich geändert
 * habe und ich einer KI sagen könnte, wende mir diese Änderungen auf das
 * Bild an. Dazu müssten die Bezeichner natürlich klar sein" — in English,
 * his choice, as the language a model reads most surely). The keys stay
 * as the code speaks them; renaming them would touch every stored picture
 * for the sake of one file. Instead the file explains itself: the order
 * the edit is applied in, and each key's meaning, unit and range.
 *
 * A TWIN OF THE RENDERER: every sentence here describes what
 * assets/js/develop.js does (geoView, the shader's main(), radialNew,
 * linearNew) and what photo_develop_clean keeps. Whoever changes a
 * slider's effect or the geometry changes this text in the same commit.
 */
function measure_develop_help(): array
{
    return [
        'about' => 'Non-destructive edits of the original image named under "image". The original file is never changed; '
            . 'apply these settings to it to obtain the edited picture. Every position refers to the ORIGINAL image in its '
            . 'own pixels: width W and height H as given under "image", origin top left, y pointing down. '
            . 'A key that is absent means "unchanged" (0).',
        'order' => [
            '1. spots (retouching), on the original as it came — every entry in its order',
            '2. colour cast ("cast"), then white balance: wb, then temp and tint — gains per channel on the picture as it came',
            '3. sharpen',
            '4. light: exposure, highlights, shadows, whites, blacks (one gain on linear light), then contrast, then clarity',
            '5. dehaze',
            '6. colour: vibrance, saturation',
            '7. tone curve ("curve")',
            '8. colour mixer ("mix")',
            '9. colour grading by brightness ("grade")',
            '10. masks: every entry of "radials", then of "linears", then of "brushes", each applying its own local adjustment with every '
                . 'setting multiplied by its mask value m (0 to 1) at the pixel — its temp and tint first, as gains per channel exactly as in '
                . 'step 2 raised to the power m, on what the steps before have made; then its light, dehaze, colour and sharpen. A mask\'s '
                . 'exposure reaches 4 stops at ±100 (the picture\'s 3)',
            '11. geometry ("geo"): mirror, then turn, then crop — all about the crop centre',
        ],
        'sliders' => 'Every global slider runs from -100 to +100, 0 = unchanged; "sharpen" runs from 0 to 100.',
        'keys' => [
            'exposure'   => 'Brightness in stops on linear light: +100 = +3 EV, -100 = -3 EV.',
            'contrast'   => 'Contrast per channel on the display-encoded picture: + blends each channel towards smoothstep(0, 1, v) '
                . 'by 0.32 × contrast/100 (an S-curve that keeps black and white; +100 is 1.16 times as steep at middle grey), '
                . '- flattens about middle grey: (v − 0.5) × (1 + 0.6 × contrast/100) + 0.5.',
            'highlights' => 'The bright tones: + brighter, - darker — a gain on linear light of up to 1.5 stops, all channels in the same ratio, '
                . 'weighted by the square of the brightness, read half off the pixel and half off the picture under a wide blur (as clarity).',
            'shadows'    => 'The dark tones: + lifted, - sunk — a gain on linear light of up to 3 stops, all channels in the same ratio, so black stays '
                . 'black and colours keep their saturation; weighted by the square of the darkness, read half off the pixel and half off the '
                . 'picture under a wide blur (as clarity).',
            'whites'     => 'The very brightest tones (white point): + brighter, - darker — a gain on linear light of up to 1 stop, all channels in '
                . 'the same ratio, weighted by the fourth power of the brightness, read as highlights are.',
            'blacks'     => 'The very darkest tones (black point): + opened, - deepened — a gain on linear light of up to 2 stops, all channels in '
                . 'the same ratio, so pure black stays black; weighted by the fourth power of the darkness, read as shadows are.',
            'clarity'    => 'Local contrast of the midtones (texture), taken against a wide blur of about W/400 pixels.',
            'dehaze'     => '+ removes a grey haze and adds a little saturation, - lays a grey veil over the picture.',
            'temp'       => 'White balance, a gain per channel in step 2: red multiplied by 2^s, blue by 2^-s, where s = 0.25 x + 0.75 x³ '
                . 'and x = temp / 100 — so +100 = one stop warmer, -100 = one stop cooler.',
            'tint'       => 'White balance, a gain per channel in step 2: green multiplied by 2^(-2n/3), red and blue by 2^(n/3), where '
                . 'n = 0.25 x + 0.75 x³ and x = tint / 100 — + towards magenta, - towards green; the brightness stays.',
            'vibrance'   => 'Saturation that mainly reaches weakly saturated colours.',
            'saturation' => 'Saturation of all colours; -100 = greyscale.',
            'sharpen'    => 'Unsharp mask with a small radius, 0 to 100.',
            'curve'      => 'Tone curve, step 7, on the display-encoded picture with values 0 to 255: rgb applies to all three '
                . 'channels, then r, g, b each to its own (absent = unchanged). Each is a list of points [x, y] — input, output —, '
                . 'x rising; the curve through them is a monotone cubic Hermite spline (Fritsch–Carlson tangents), flat '
                . 'beyond the first and the last point.',
            'grade'      => 'Colour grading by brightness, step 9, on the display-encoded picture (gamma 2.2). Three ranges, '
                . 'shadows, midtones, highlights, weighted by the pixel\'s luma y (Rec. 709): with the pivot p = 0.5 − 0.3 × balance/100, '
                . 'shadows 1 − smoothstep(0, p, y), highlights smoothstep(p, 1, y), midtones the rest. Per range h (degrees, the HSV '
                . 'hue the range is pushed towards), s (0 to 100), l (-100 to +100), absent = 0. The direction is the fully saturated '
                . 'colour of h less its luma, scaled so its largest channel is ±1; on linear light every channel is multiplied by '
                . '2^(direction × s/100), the pixel\'s luma restored, then all three by 2^(l/100). The push (not l) is multiplied by '
                . '1 − smoothstep(0.96, 1, the pixel\'s largest channel), so a burned-out white stays white. The ranges\' numbers are blended by '
                . 'their weights before they are applied.',
            'mix'        => 'Colour mixer, step 8, on the display-encoded picture (gamma 2.2). Eight colours at hues (HSV, degrees) '
                . 'red 0, orange 30, yellow 60, green 120, aqua 180, blue 240, purple 270, magenta 300; h, s and l each hold, per colour, '
                . 'a value from -100 to +100 (absent = 0). A pixel\'s hue lies between two neighbouring colours and its adjustment is '
                . 'blended between theirs with a smoothstep of the position. h: the hue turned by up to ±30 degrees. s: the colour '
                . 'scaled about its luminance (Rec. 709) by 1 + s/100. l: all three channels multiplied on linear light by 2^(1.2 × l/100 × w), '
                . 'w = smoothstep(0, 0.25, saturation).',
            'cast'       => 'Colour cast removal, step 2 before the white balance. n: five pairs [r, b] — the colour of the light '
                . 'measured on the greyest pixels in five brightness ranges of linear light (gamma 2.2), Y 0.003–0.02, 0.02–0.06, '
                . '0.06–0.18, 0.18–0.5, 0.5–1, as red and blue against green. Each range is corrected by a Bradford chromatic '
                . 'adaptation in linear sRGB that turns the grey [r, 1, b] into a neutral grey of the same luminance; a pixel\'s '
                . 'correction is blended linearly between the two ranges around its luminance on a log scale (range centres at the '
                . 'geometric mean of their bounds). amount, shadows, highlights: 0 to 100 — each correction is blended from the '
                . 'identity by amount/100 × (s × shadows + (1 − s) × highlights)/100, where s is 1, 1, 0.5, 0, 0 for the five ranges. '
                . 'base and picks (optional) only record how n came about and need not be applied: picks are points clicked on '
                . 'something meant to be grey — px, py their place as shares of W and H, lum their linear luma, r and b as in n —, '
                . 'base the automatic measurement beneath them.',
            'wb'         => 'White balance of older edits: r, g, b are gains each colour channel of the original is multiplied by '
                . '(0.25 to 4, their mean about 1), applied right after the spots, before temp and tint. A measured white balance is '
                . 'written into temp and tint since 12.11.2026.',
            'spots'      => 'Retouched places, each mended from another part of the same picture, applied in their '
                . 'order (a later one may mend over an earlier one). path: the place as one point [x, y] or a brush '
                . 'stroke of several, as shares of W and H; the region is everything within "radius" (a share of W) '
                . 'of that path. dx, dy: where the source lies — the same region moved by dx × W and dy × H. '
                . 'mode "clone": the source pixels are copied as they are. mode "heal": the source\'s texture is '
                . 'copied, and its brightness and colour are matched to the surroundings — the difference between '
                . 'target and source along the region\'s rim is interpolated smoothly over the region and added to '
                . 'the source. feather: 0 to 100, the outer share of the radius over which the mended picture fades '
                . 'into the old one. opacity: 0 to 100. off: true = switched off, only kept. rot (heal and clone '
                . 'only): degrees, -180 to 180 — what is copied appears turned clockwise by it about the centre of the '
                . 'region\'s bounding box; the target point p takes its pixel from centre + (dx × W, dy × H) + '
                . 'R(-rot) · (p - centre). fill: true (heal and ai '
                . 'only) = the path was drawn round the place and is closed; everything it encloses belongs to the '
                . 'region as well, fully. '
                . 'mode "ai": the place was filled by an inpainting model and carries no dx, dy (strong: true = by the '
                . 'strong model, Moebius; otherwise by the quick one, LaMa; other: the pieces of the model not shown, '
                . 'kept in the same form — ignore them when rendering); its pixels lie '
                . 'as PNG files of 512 × 512 in this folder, named "<image name>.flicken-<id>.png". pieces: the parts '
                . 'of the path in their order, each with its "id" (the file), "box" [x, y, w, h] (the square of the '
                . 'original the PNG stands for, as shares of W and H; scale the PNG onto it) and "a", "b" (the stretch '
                . 'of the path it fills, as shares of the path\'s length). Lay each PNG in where it is not transparent, '
                . 'within radius × (1 + feather/200) of its stretch — radius × (1 + feather/50) where wide: true —: '
                . 'fully up to "radius", fading out beyond it, '
                . 'times opacity. A later piece lies over an earlier one. With fill: true the inside is filled as '
                . 'well and the spot has a single piece.',
            'radials'    => 'Elliptical masks, each with an adjustment of its own. x, y: the centre as shares of W and H. '
                . 'rx, ry: the two radii, BOTH as shares of W. angle: the ellipse turned clockwise, in degrees. '
                . 'feather: 0 to 100, the soft rim as a share of the radius — full effect inside, none outside the '
                . 'ellipse, a smooth passage over the outer "feather" per cent. invert: true = the adjustment applies '
                . 'outside the ellipse instead of inside. Its own sliders, -100 to +100 and meaning as above: every global '
                . 'slider — temp, tint, contrast, vibrance, saturation, exposure, highlights, shadows, whites, blacks, clarity, dehaze, and sharpen (here -100 to +100: below 0 it softens the masked area, down to a blur of about a 400th of the picture at -100). off: true = the mask is switched off and '
                . 'does nothing (it is only kept).',
            'linears'    => 'Graduated masks. Full effect up to the line through (x1, y1), none from the line through '
                . '(x2, y2); both lines stand perpendicular to the way from point 1 to point 2, with a smooth passage '
                . 'between them. Points as shares of W and H. invert, off and the own sliders as for "radials".',
            'brushes'    => 'Painted masks. strokes: brush strokes in their order, each a path as under "spots" (points as '
                . 'shares of W and H) with a "radius" (share of W), "feather" (0 to 100, the outer share of the radius '
                . 'that fades out) and "flow" (0 to 100). A stroke adds its flow to what is painted: mask = mask + '
                . 'stroke × (1 − mask); a stroke with erase: true removes instead: mask = mask × (1 − stroke). Within '
                . 'one stroke the highest value counts (it does not build up on itself). invert, off and the own '
                . 'sliders as for "radials".',
            'geo'        => 'Geometry, applied after all colour and light work, in this order about the crop centre: '
                . '"flip", then "turn" + "angle", then "crop".',
            'geo.flip'   => '"h" mirrors left and right, "v" top and bottom, "hv" both — in the ORIGINAL\'s own axes, '
                . 'BEFORE turning.',
            'geo.turn'   => 'Quarter turns clockwise: 0, 90, 180 or 270 degrees.',
            'geo.angle'  => 'Straightening in degrees clockwise, -45 to +45, added to "turn".',
            'geo.crop'   => 'The rectangle kept, upright in the turned picture. x, y: its centre in the ORIGINAL, as '
                . 'shares of W and H. w, h: its width and height in the result, BOTH as shares of W. Without "crop": '
                . 'the largest upright rectangle inside the turned picture (with angle 0 the whole turned picture). '
                . 'The rectangle may reach past the picture; what lies outside is empty unless "geo.fill" covers it.',
            'geo.fill'   => 'The border of a crop that reaches past the picture, invented by an inpainting model '
                . '(by the quick one, LaMa; strong: true = by the strong model, Moebius, at "level" stages). '
                . 'One image of w × h pixels standing for the WHOLE crop rectangle: scale it onto the result. It is '
                . 'opaque where the crop shows no picture, fades out over a few pixels into the picture and is '
                . 'transparent elsewhere. Draw the turned picture first, this image over it, and apply steps 1 to 7 '
                . 'to the result as if it were the picture (a painted mask is empty outside the original). The image '
                . 'lies in this folder in squares of 512 × 512 from its top left corner, named like the patches of '
                . '"spots": pieces lists each stored square with its "id" (the file) and its column "c" and row "r"; '
                . 'a square not listed is empty. "at" names the cut the image was made for and can be ignored. '
                . '"fine": further images over the SAME rectangle, each with its own w, h and pieces, each holding '
                . 'what the strong model made of one connected area of the border ("area"). Those whose "area" is '
                . 'listed in "pick" are shown: scale each onto the result and draw it over the first image; the '
                . 'others are kept for the user to come back to and are not drawn.',
        ],
    ];
}

/**
 * The user's named scale presets — for the viewer's chooser.
 *
 * IN THE START PAYLOAD, not fetched on demand: a handful of rows, and the
 * chooser has to work in the woods, where the cached page is all there is.
 *
 * @return array<int, array{id:int, name:string, um_per_px:float}>
 */
function photo_scales(PDO $pdo, int $userId): array
{
    if (!table_exists($pdo, 'map_photo_scales')) {
        return [];
    }
    // width/height only from migration 0093 on, fileid from 0094 — the
    // code is live before both.
    $hasDims = column_exists($pdo, 'map_photo_scales', 'width');
    $hasFile = column_exists($pdo, 'map_photo_scales', 'fileid');
    // And where the image lies, from 0138 on — before that only pCloud.
    $hasProvider = column_exists($pdo, 'map_photo_scales', 'provider');
    // And the device each objective belongs to, from 0183 on — its name
    // rides along, so the viewer can offer the devices without a second
    // list (a device without presets has nothing to offer there).
    $hasDevice = scale_devices_ready($pdo);
    $stmt = $pdo->prepare(
        'SELECT s.id, s.name, s.um_per_px' . ($hasDims ? ', s.width, s.height' : '')
        . ($hasFile ? ', s.fileid' : '') . ($hasProvider ? ', s.provider' : '')
        . ($hasDevice ? ', s.device_id, d.name AS device' : '')
        . ' FROM map_photo_scales s'
        . ($hasDevice ? ' JOIN map_scale_devices d ON d.id = s.device_id' : '')
        . ' WHERE s.user_id = ?'
    );
    $stmt->execute([$userId]);

    /* NATURAL ORDER, NOT THE DATABASE'S (28.08.2026: his
       objectives sorted „100, 10, 40, 4"): ORDER BY name compares
       character by character, and '0' < 'x' puts 100x before 10x before
       4x. strnatcasecmp reads the digits as numbers — 4x, 10x, 40x,
       100x — and does so anywhere in the string, so „Olympus 10x" and
       „Olympus 4x" sort right too. A handful of rows; PHP may sort. */
    $rows = $stmt->fetchAll();
    usort($rows, static fn(array $a, array $b): int
        => strnatcasecmp((string) $a['name'], (string) $b['name']));

    return array_map(static fn(array $r): array => [
        'id'        => (int) $r['id'],
        'name'      => (string) $r['name'],
        'um_per_px' => (float) $r['um_per_px'],
        // The calibration image's pixel grid — the stamping warning's
        // yardstick; null means „ohne Bild geeicht", nothing to warn on.
        'width'     => isset($r['width']) ? (int) $r['width'] : null,
        'height'    => isset($r['height']) ? (int) $r['height'] : null,
        // And its place in the cloud (0094) — what re-measuring loads. A
        // string since 0138: Drive, OneDrive and S3 name their files.
        'fileid'    => isset($r['fileid']) && (string) $r['fileid'] !== '' ? (string) $r['fileid'] : null,
        'provider'  => isset($r['fileid']) && (string) $r['fileid'] !== ''
            ? ((string) ($r['provider'] ?? '') ?: 'pcloud') : null,
        // Null before 0183: then there is one nameless device, as before.
        'device_id' => isset($r['device_id']) ? (int) $r['device_id'] : null,
        'device'    => isset($r['device']) ? (string) $r['device'] : null,
    ], $rows);
}

/** Do the presets know their device yet (migration 0183)? */
function scale_devices_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_scale_devices')
        && column_exists($pdo, 'map_photo_scales', 'device_id');
}

/**
 * The account's devices — „Mikroskop", „Reisemikroskop", „Stereolupe" —
 * in natural order, the empty ones included: the settings show them and
 * the microscope window may calibrate into one.
 *
 * @return array<int, array{id:int, name:string}>
 */
function scale_devices(PDO $pdo, int $userId): array
{
    if (!scale_devices_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT id, name FROM map_scale_devices WHERE user_id = ?');
    $stmt->execute([$userId]);
    $rows = array_map(static fn(array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name']],
        $stmt->fetchAll());
    usort($rows, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
    return $rows;
}

function scale_device_owned(PDO $pdo, int $userId, int $deviceId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM map_scale_devices WHERE id = ? AND user_id = ?');
    $stmt->execute([$deviceId, $userId]);
    return $stmt->fetchColumn() !== false;
}

/**
 * The device a preset goes into when none was named: the oldest one, or
 * „Mikroskop", made on the spot. Whoever has never thought of devices
 * calibrates as before and finds his objectives under the same word the
 * migration gave everybody else's.
 */
function scale_device_default(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT id FROM map_scale_devices WHERE user_id = ? ORDER BY id LIMIT 1');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    return scale_device_save($pdo, $userId, 0, t('Mikroskop'));
}

/**
 * Make a device (id 0) or rename one. Answers its id.
 */
function scale_device_save(PDO $pdo, int $userId, int $deviceId, string $name): int
{
    if (!scale_devices_ready($pdo)) {
        // Invalid and not Runtime: the settings' processing catches that
        // kind and says it as a sentence, where a Runtime one breaks the page.
        throw new InvalidArgumentException(t('Die Geräte brauchen die Migration 0183 — sie ist noch nicht eingespielt.'));
    }
    $name = mb_substr(trim($name), 0, 100);
    if ($name === '') {
        throw new InvalidArgumentException(t('Ein Gerät braucht einen Namen.'));
    }
    if ($deviceId > 0 && !scale_device_owned($pdo, $userId, $deviceId)) {
        throw new InvalidArgumentException(t('Dieses Gerät gibt es nicht mehr.'));
    }
    try {
        if ($deviceId > 0) {
            $pdo->prepare('UPDATE map_scale_devices SET name = ? WHERE id = ? AND user_id = ?')
                ->execute([$name, $deviceId, $userId]);
            return $deviceId;
        }
        $pdo->prepare('INSERT INTO map_scale_devices (user_id, name, created_at) VALUES (?, ?, NOW())')
            ->execute([$userId, $name]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            throw new InvalidArgumentException(t('Ein Gerät mit diesem Namen gibt es schon.'));
        }
        throw $e;
    }
}

/**
 * Drop a device and, through the foreign key, its presets. Pictures that
 * got a scale from one keep it — the stamp carries its own numbers.
 */
function scale_device_forget(PDO $pdo, int $userId, int $deviceId): bool
{
    if (!scale_devices_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM map_scale_devices WHERE id = ? AND user_id = ?');
    $stmt->execute([$deviceId, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * Remember a calibration under a name — same name on the same device
 * replaces (the unique key), so re-calibrating „10x" heals it instead of
 * breeding „10x (2)". Without a device of one's own it goes into the
 * first one, „Mikroskop" if there is none yet (scale_device_default).
 */
function photo_scale_remember(PDO $pdo, int $userId, string $name, float $umPerPx,
                              ?int $width = null, ?int $height = null,
                              ?string $fileid = null, ?string $provider = null,
                              ?int $deviceId = null): void
{
    if (!table_exists($pdo, 'map_photo_scales')) {
        throw new RuntimeException(t('Die Vorlagen brauchen die Migration 0089 — sie ist noch nicht eingespielt.'));
    }
    $name = mb_substr(trim($name), 0, 100);
    if ($name === '' || !is_finite($umPerPx) || $umPerPx <= 0) {
        throw new InvalidArgumentException(t('Eine Vorlage braucht einen Namen und eine brauchbare Eichung.'));
    }
    /* The dimensions of the picture the calibration was drawn on
       (migration 0093) — what the stamping warning compares against —
       the calibration image's place in the cloud (0094, for re-measuring
       later), where it lies beside its id (0138), and the DEVICE the
       objective belongs to (0183). OVERWRITTEN PLAINLY on re-minting,
       nulls included: the latest calibration defines the preset entirely,
       and a hand-typed factor (no image, no dims) rightly ends a stale
       warning's basis rather than inheriting one. One column list, each
       guarded on its own, because the code is live before its
       migrations; it used to be four written-out INSERTs, one per step. */
    $cols = ['user_id' => $userId, 'name' => $name, 'um_per_px' => $umPerPx];
    if (scale_devices_ready($pdo)) {
        // The device is part of the key, so „40x" replaces the 40x of THIS
        // device and leaves the travel microscope's alone.
        $cols['device_id'] = $deviceId !== null && scale_device_owned($pdo, $userId, $deviceId)
            ? $deviceId : scale_device_default($pdo, $userId);
    }
    if (column_exists($pdo, 'map_photo_scales', 'width')) {
        $cols['width'] = $width;
        $cols['height'] = $height;
    }
    if (column_exists($pdo, 'map_photo_scales', 'fileid')) {
        $cols['fileid'] = $fileid;
    }
    if (column_exists($pdo, 'map_photo_scales', 'provider')) {
        $cols['provider'] = $fileid === null ? null : ($provider ?? 'pcloud');
    }
    $names = array_keys($cols);
    $updates = array_map(static fn(string $c): string => "$c = VALUES($c)",
        array_diff($names, ['user_id', 'name', 'device_id']));
    $pdo->prepare(
        'INSERT INTO map_photo_scales (' . implode(', ', $names) . ', created_at)
         VALUES (' . implode(', ', array_fill(0, count($names), '?')) . ', NOW())
         ON DUPLICATE KEY UPDATE ' . implode(', ', $updates)
    )->execute(array_values($cols));
}

/**
 * Edit one preset in place: rename, and — when a fresh measurement rides
 * along — re-calibrate (28.08.2026: „den Namen ändern oder
 * nochmal neu nacheichen"). By ID, not by name: renaming through the
 * remember-INSERT would mint a SECOND preset and leave the old one
 * standing.
 */
function photo_scale_update(PDO $pdo, int $userId, int $presetId, string $name,
                            ?float $umPerPx = null, ?int $width = null,
                            ?int $height = null, ?string $fileid = null,
                            ?string $provider = null): void
{
    if (!table_exists($pdo, 'map_photo_scales')) {
        throw new RuntimeException(t('Die Vorlagen brauchen die Migration 0089 — sie ist noch nicht eingespielt.'));
    }
    $name = mb_substr(trim($name), 0, 100);
    if ($name === '') {
        throw new InvalidArgumentException(t('Eine Vorlage braucht einen Namen.'));
    }
    $stmt = $pdo->prepare('SELECT 1 FROM map_photo_scales WHERE id = ? AND user_id = ?');
    $stmt->execute([$presetId, $userId]);
    if ($stmt->fetchColumn() === false) {
        throw new InvalidArgumentException(t('Diese Vorlage gibt es nicht mehr.'));
    }

    $sets = ['name = ?'];
    $args = [$name];
    if ($umPerPx !== null) {
        if (!is_finite($umPerPx) || $umPerPx <= 0) {
            throw new InvalidArgumentException(t('Die neue Eichung ist unbrauchbar.'));
        }
        $sets[] = 'um_per_px = ?';
        $args[] = $umPerPx;
        if (column_exists($pdo, 'map_photo_scales', 'width')) {
            $sets[] = 'width = ?';  $args[] = $width;
            $sets[] = 'height = ?'; $args[] = $height;
        }
    }
    if ($fileid !== null && column_exists($pdo, 'map_photo_scales', 'fileid')) {
        $sets[] = 'fileid = ?';
        $args[] = $fileid;
        if (column_exists($pdo, 'map_photo_scales', 'provider')) {
            $sets[] = 'provider = ?';
            $args[] = $provider ?? 'pcloud';
        }
    }
    $args[] = $presetId;
    $args[] = $userId;
    try {
        $pdo->prepare('UPDATE map_photo_scales SET ' . implode(', ', $sets)
            . ' WHERE id = ? AND user_id = ?')->execute($args);
    } catch (PDOException $e) {
        // The unique key on (user_id, name) — the one collision a rename
        // can cause, and it deserves a sentence, not a stack trace.
        if ((string) $e->getCode() === '23000') {
            throw new InvalidArgumentException(t('Eine Vorlage mit diesem Namen gibt es schon.'));
        }
        throw $e;
    }
}

/**
 * Drop a preset. Photos already stamped with it keep their scale — the
 * stamp MATERIALISED the factor into the photo (see photowindow.js), exactly so
 * that no photo ever depends on a preset still existing.
 */
function photo_scale_forget(PDO $pdo, int $userId, int $presetId): bool
{
    if (!table_exists($pdo, 'map_photo_scales')) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM map_photo_scales WHERE id = ? AND user_id = ?');
    $stmt->execute([$presetId, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * Store or clear a photo's calibration, in the owner's name.
 *
 * The RIGHTS question is the caller's (api/v1/photos.php) — this here
 * only writes, like photo_delete does.
 */
function photo_scale_store(PDO $pdo, int $ownerId, int $photoId, ?array $scale): bool
{
    if (!photos_ready($pdo) || !column_exists($pdo, 'map_photos', 'scale')) {
        return false;
    }
    /* EXISTENCE FIRST, THEN UPDATE — not rowCount() on the UPDATE alone:
       MySQL counts 0 changed rows when the new value equals the old, and
       re-saving an unchanged calibration would then read as "photo not
       found" at the API. */
    $photo = photo_load($pdo, $ownerId, $photoId);
    if ($photo === null) {
        return false;
    }
    $pdo->prepare('UPDATE map_photos SET scale = ? WHERE id = ? AND user_id = ?')
        ->execute([
            $scale === null ? null : json_encode($scale, JSON_UNESCAPED_SLASHES),
            $photoId,
            $ownerId,
        ]);
    /* The scale travels to every viewer inside the point baggage
       (photo_prepare) — without this nudge a fresh calibration never
       leaves the device it was drawn on. */
    photo_point_touched($pdo, (int) $photo['point_id']);
    return true;
}

/** One photo including ownership. Null if it does not exist for this user. */
function photo_load(PDO $pdo, int $userId, int $photoId): ?array
{
    if (!photos_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM map_photos WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$photoId, $userId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

// ---------------------------------------------------------------------------
// Where the files live
// ---------------------------------------------------------------------------

/**
 * The path of an image file.
 *
 * The preview image is named like the big one, with "_k" before the
 * extension. A dedicated column for it would be a second truth about the
 * same fact — and the sort of column that at some point no longer holds.
 *
 * The filename comes from the database and was assigned there by us. It is
 * checked anyway: between "comes from us" and "can only come from us" lies
 * every path description that ever led out of a directory.
 */
function photo_file(int $userId, string $fileName, bool $small = false): ?string
{
    if (!preg_match('/^[0-9a-f]{32}\.jpg$/', $fileName)) {
        return null;
    }
    if ($small) {
        $fileName = substr($fileName, 0, 32) . '_k.jpg';
    }
    return user_photo_directory($userId) . '/' . $fileName;
}

// ---------------------------------------------------------------------------
// Creating
// ---------------------------------------------------------------------------

/**
 * Check an uploaded image.
 *
 * The file is checked, not what was claimed about it. The transmitted type
 * is a statement by the sender and worthless as such; getimagesize() looks
 * into the bytes.
 *
 * @return array{0:int,1:int} width and height
 * @throws InvalidArgumentException
 */
function photo_image_check(string $path, int $edgeMax, int $bytesMax, string $what): array
{
    $bytes = (int) filesize($path);
    if ($bytes <= 0) {
        throw new InvalidArgumentException(t('%s ist leer.', $what));
    }
    if ($bytes > $bytesMax) {
        throw new InvalidArgumentException(t('%s ist zu gross (%s).', $what, bytes_readable($bytes)));
    }

    $dimensions = @getimagesize($path);
    if ($dimensions === false || ($dimensions[2] ?? 0) !== IMAGETYPE_JPEG) {
        throw new InvalidArgumentException(t('%s ist kein JPEG.', $what));
    }

    [$width, $height] = $dimensions;
    if (max($width, $height) > $edgeMax) {
        throw new InvalidArgumentException(
            t('%s ist zu gross: %d×%d Pixel, erlaubt sind %d.', $what, $width, $height, $edgeMax)
        );
    }

    return [(int) $width, (int) $height];
}

/**
 * Store a photo.
 *
 * @param array $image     entry from $_FILES for the big image
 * @param array|null $preview entry from $_FILES for the preview image
 * @param array $meta     taken_at, camera, exif_lat, exif_lon, client_id
 * @throws InvalidArgumentException|RuntimeException
 */
function photo_create(PDO $pdo, int $userId, int $pointId, array $image, ?array $preview, array $meta): array
{
    if (!photos_ready($pdo)) {
        throw new RuntimeException(t('Die Fotoverwaltung ist auf diesem Server noch nicht eingerichtet.'));
    }

    /* MAY THIS USER ATTACH A PICTURE HERE? Until 11.09.2026 the answer
       was „only to his own points". Since co-ownership it is the three
       doors that already let one tend a find's photos, plus being a
       co-owner (point_owners.php) — the excursion leader attaching a
       micrograph to a guest's find is the case that asked for it. The
       picture stays the PHOTOGRAPHER's (user_id below, his folder, his
       quota); only its point is somebody else's. */
    require_once __DIR__ . '/point_owners.php';
    $holder = point_attach_scope($pdo, $userId, $pointId);
    $stmt = $pdo->prepare('SELECT id FROM map_points WHERE id = ?');
    $stmt->execute([$holder === null ? 0 : $pointId]);
    if ($stmt->fetchColumn() === false) {
        throw new InvalidArgumentException(t('Punkt nicht gefunden.'));
    }

    // The same photo a second time is the same photo — see migration 0031.
    $clientId = point_client_key($meta['client_id'] ?? null);
    if ($clientId !== null) {
        $stmt = $pdo->prepare('SELECT * FROM map_photos WHERE user_id = ? AND client_id = ?');
        $stmt->execute([$userId, $clientId]);
        $existing = $stmt->fetch();
        if ($existing !== false) {
            return photo_prepare($existing);
        }
    }

    if (($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($image['tmp_name'] ?? '')) {
        throw new InvalidArgumentException(t('Es ist kein Bild angekommen.'));
    }

    [$width, $height] = photo_image_check($image['tmp_name'], PHOTO_EDGE_MAX, PHOTO_BYTES_MAX, t('Das Bild'));

    $hasPreview = $preview !== null
        && ($preview['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        && is_uploaded_file($preview['tmp_name'] ?? '');
    if ($hasPreview) {
        photo_image_check(
            $preview['tmp_name'],
            PHOTO_PREVIEW_EDGE_MAX,
            PHOTO_PREVIEW_BYTES_MAX,
            t('Das Vorschaubild')
        );
    }

    // Asked BEFORE storing, and about both files together.
    $needed = (int) filesize($image['tmp_name']) + ($hasPreview ? (int) filesize($preview['tmp_name']) : 0);
    if (!user_fits($pdo, $userId, $needed)) {
        $free = user_free($pdo, $userId);
        throw new InvalidArgumentException(
            t('Der Speicherplatz reicht nicht: %s werden gebraucht, frei sind %s.',
              bytes_readable($needed), bytes_readable($free))
        );
    }

    $directory = user_photo_directory_create($userId);
    $stem       = bin2hex(random_bytes(16));
    $fileName   = $stem . '.jpg';

    if (!move_uploaded_file($image['tmp_name'], $directory . '/' . $fileName)) {
        throw new RuntimeException(t('Das Bild lässt sich nicht ablegen.'));
    }
    if ($hasPreview && !move_uploaded_file($preview['tmp_name'], $directory . '/' . $stem . '_k.jpg')) {
        // No reason to abandon the whole thing: photo.php then simply
        // delivers the big image. Annoying for the wire, but nothing is
        // broken.
        error_log('Vorschaubild lässt sich nicht ablegen: ' . $stem);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO map_photos
                (user_id, point_id, client_id, filename, original_name, width, height, bytes,
                 taken_at, exif_lat, exif_lon, camera, sort_order, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId,
            $pointId,
            $clientId,
            $fileName,
            mb_substr(trim((string) ($meta['original_name'] ?? '')), 0, 190),
            $width,
            $height,
            $needed,
            photo_datetime($meta['taken_at'] ?? null),
            photo_degrees($meta['exif_lat'] ?? null, 90),
            photo_degrees($meta['exif_lon'] ?? null, 180),
            photo_text($meta['camera'] ?? null, 120),
            photo_next_sort_order($pdo, $pointId),
        ]);
        $photoId = (int) $pdo->lastInsertId();

        /* THE MOTIVNAME NOW COMES ALONG RIGHT AWAY (10.09.2026:
           „Aufnahmen mit der Handykamera sollten immer eine Abfrage
           bekommen, was der Motivname ist, dass dieser gleich
           feststeht"). It travels as an ordinary field in the same form
           as camera model and capture time — the queue attaches it when
           a recording was prepared with a name.

           AS A STATEMENT OF ITS OWN AND NOT IN THE COLUMN LIST ABOVE: the
           column came with 0097, and the code goes live before the
           migration. An INSERT that names it would fail completely on a
           server without it — the picture with it, which then stands
           nowhere any more. */
        $title = photo_title_clean($meta['title'] ?? null);
        if ($title !== null && column_exists($pdo, 'map_photos', 'title')) {
            $pdo->prepare('UPDATE map_photos SET title = ? WHERE id = ?')
                ->execute([$title, $photoId]);
        }
        // Co-ownership and the copies (point_owners.php) — bookkeeping
        // only, the bytes travel later; it never throws past here.
        point_photo_attached($pdo, $pointId, $userId, 'photo', $photoId);
    } catch (Throwable $e) {
        // The row did not come into being — then the file must not stay
        // behind either. An orphaned file counts against the quota and can
        // no longer be reached by anything.
        @unlink($directory . '/' . $fileName);
        @unlink($directory . '/' . $stem . '_k.jpg');
        throw $e;
    }

    photo_point_touched($pdo, $pointId);
    // A new picture can turn „Sporen" into „Sporen 1" beside its
    // namesake — the find's names are due (cloud_names.php).
    require_once __DIR__ . '/cloud_names.php';
    cloud_names_touch($pdo, $pointId);

    $stmt = $pdo->prepare('SELECT * FROM map_photos WHERE id = ?');
    $stmt->execute([$photoId]);
    return photo_prepare($stmt->fetch());
}

/** Whether the cloud recordings take part in the gallery's order — they
 *  do from migration 0148 on, and the code is live before it. */
function cloud_files_sortable(PDO $pdo): bool
{
    return table_exists($pdo, 'map_cloud_files') && column_exists($pdo, 'map_cloud_files', 'sort_order');
}

/**
 * To the end of the row — behind the photos AND the cloud recordings,
 * which share one number space per point since migration 0148. Asked for
 * a new photo and for a new recording alike (api/v1/cloud.php), so
 * whatever comes last stands last, whichever door it came through.
 */
function photo_next_sort_order(PDO $pdo, int $pointId): int
{
    if (cloud_files_sortable($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT GREATEST(
                    (SELECT COALESCE(MAX(sort_order), -1) FROM map_photos WHERE point_id = ?),
                    (SELECT COALESCE(MAX(sort_order), -1) FROM map_cloud_files WHERE point_id = ?)) + 1'
        );
        $stmt->execute([$pointId, $pointId]);
    } else {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM map_photos WHERE point_id = ?');
        $stmt->execute([$pointId]);
    }
    return min(65535, (int) $stmt->fetchColumn());
}

/**
 * Put a point's pictures into the order a drag in the gallery left them
 * in (30.09.2026: „eine eigene Sortierung festlegen, indem
 * ich die Bilder einfach an den neuen Platz schiebe").
 *
 * THE WHOLE SET IS RENUMBERED, 0 … n-1: first the pictures sent, in
 * their order, then every other picture of the point in its old order.
 * The client sends only what it saw, and a picture that arrived from
 * another device while the finger was still on the glass must not fall
 * out of the row — it lands at the end, where a fresh upload lands
 * anyway (photo_next_sort_order). A key that is not this point's is
 * passed over, not refused: it cannot be moved into a find it does not
 * belong to, and the rest of the drag is still worth keeping.
 *
 * BOTH KINDS IN ONE ROW (same evening): a key is „photo:63" for a
 * voucher photo and „cloud:17" for a cloud recording — which are the
 * main part of a find, spore prints and stacks from the proper camera,
 * and which the first cut left out (migration 0148 says how that was
 * found). A bare number is a photo: the gallery of the first push sent
 * ids alone, and a page still open from then must not have its drag
 * thrown away. Without the column the recordings simply stay out.
 *
 * ONE ORDER FOR THE WHOLE CIRCLE. sort_order lies on the picture row,
 * and a find is a shared record — whoever may attach a picture
 * (point_attach_scope, the caller's job) arranges the gallery for all
 * co-owners, the report and the excursion alike. Asked when it was
 * built, the maintainer chose exactly that over an order per viewer.
 *
 * @param array<int|string> $keys
 * @return string[] the order as stored — empty when the point has none
 */
function photos_reorder(PDO $pdo, int $pointId, array $keys): array
{
    if (!photos_ready($pdo)) {
        return [];
    }
    $cloud = cloud_files_sortable($pdo);
    $rows = [];
    foreach (['photo' => 'map_photos', 'cloud' => 'map_cloud_files'] as $kind => $table) {
        if ($kind === 'cloud' && !$cloud) {
            continue;
        }
        $stmt = $pdo->prepare("SELECT id, sort_order FROM $table WHERE point_id = ?");
        $stmt->execute([$pointId]);
        foreach ($stmt as $r) {
            $rows[] = [$kind, (int) $r['id'], (int) $r['sort_order']];
        }
    }
    if ($rows === []) {
        return [];
    }
    // The order as it stands: number, then the photo before the recording,
    // then the id — the gallery breaks its ties the same way.
    usort($rows, static fn(array $a, array $b): int =>
        [$a[2], $a[0] === 'cloud', $a[1]] <=> [$b[2], $b[0] === 'cloud', $b[1]]);
    $present = array_map(static fn(array $r): string => $r[0] . ':' . $r[1], $rows);

    // Keys keep the order and swallow the duplicates in one go.
    $known = array_flip($present);
    $order = [];
    foreach ($keys as $key) {
        if (is_int($key) || (is_string($key) && ctype_digit($key))) {
            $key = 'photo:' . (int) $key;
        }
        if (is_string($key) && isset($known[$key])) {
            $order[$key] = true;
        }
    }
    foreach ($present as $key) {
        $order[$key] = true;
    }
    $order = array_keys($order);

    $pdo->beginTransaction();
    try {
        $update = [
            'photo' => $pdo->prepare('UPDATE map_photos SET sort_order = ? WHERE id = ? AND point_id = ?'),
            'cloud' => $cloud
                ? $pdo->prepare('UPDATE map_cloud_files SET sort_order = ? WHERE id = ? AND point_id = ?')
                : null,
        ];
        foreach ($order as $i => $key) {
            [$kind, $id] = explode(':', $key);
            $update[$kind]->execute([min(65535, $i), (int) $id, $pointId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    photo_point_touched($pdo, $pointId);
    // The numbers in the pictures' names follow the gallery (cloud_names.php).
    require_once __DIR__ . '/cloud_names.php';
    cloud_names_touch($pdo, $pointId);
    return $order;
}

/**
 * Note the point as changed — after EVERY write that a viewer of the
 * point could see.
 *
 * Photos travel as part of their point (photos_for_points in points_load);
 * the offline buckets, however, fingerprint over map_points ALONE — COUNT,
 * MAX(touched_at), CRC over id:touched_at, see bucket.php. A photo written
 * without this nudge therefore reaches no other device, EVER: the stored
 * bucket keeps matching its fingerprint and is trusted for good. Proven
 * 28.08.2026 on point 3228 — the browser kept the one-photo bucket until
 * touched_at was set by hand.
 *
 * touched_at IS SET EXPLICITLY, not left to its ON UPDATE: the database
 * fires that only when a value really changes, and updated_at = NOW()
 * within the same second as the previous write of that row changes
 * nothing — the very trap list_delete() in data.php has already written
 * down. CURRENT_TIMESTAMP(6) is guaranteed to advance. Behind
 * column_exists because the code goes live before the migrations.
 *
 * updated_at moves along on purpose: adding, deleting or calibrating a
 * photo is work on this point, and the older ?since= sync in points_load
 * reads only that column.
 *
 * THE CALL SITS IN THE WRITE FUNCTIONS THEMSELVES, not with their
 * callers — an API route added later cannot forget it there.
 *
 * THE POINT ALONE NAMES THE ROW, and for the same reason: nobody passes
 * whose it is (30.09.2026). Until then every caller handed in an owner id
 * that went into the WHERE, and since co-ownership (11.09.2026) that id
 * could be the wrong one — a picture belongs to its photographer, the
 * point row to its holder. Upload, name, scale and measures of a
 * co-owner's picture passed the photographer, and so did the removal of
 * his cloud recording; the UPDATE hit no row, and the holder's other
 * devices went on trusting their bucket. Only photo_delete had looked
 * the holder up. The row to nudge is simply the one the picture hangs
 * on — the callers have checked the right before they got here.
 */
function photo_point_touched(PDO $pdo, int $pointId): void
{
    $touched = column_exists($pdo, 'map_points', 'touched_at')
        ? ', touched_at = CURRENT_TIMESTAMP(6)'
        : '';
    $stmt = $pdo->prepare(
        "UPDATE map_points SET updated_at = NOW()$touched WHERE id = ?"
    );
    $stmt->execute([$pointId]);
}

// ---------------------------------------------------------------------------
// Deleting
// ---------------------------------------------------------------------------

/**
 * Remove a photo — row and file.
 *
 * Without a trash, and the migration says why: an image lying in the trash
 * keeps occupying space, and a trash that does not free the quota is none.
 * The confirmation lives in the interface.
 */
function photo_delete(PDO $pdo, int $userId, int $photoId): bool
{
    // THE RAW ROW, because photo_prepare() does not carry the cloud columns —
    // they are nobody's business in the browser. Reading the prepared shape
    // here would mean the picture is deleted on this side and stays lying in
    // the storage, and nothing would say so.
    $stmt = $pdo->prepare('SELECT * FROM map_photos WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$photoId, $userId]);
    $photo = $stmt->fetch();
    if ($photo === false) {
        return false;
    }

    $stmt = $pdo->prepare('DELETE FROM map_photos WHERE id = ? AND user_id = ?');
    $stmt->execute([$photoId, $userId]);
    if ($stmt->rowCount() === 0) {
        return false;
    }
    // The friends' edits of it go with it (migration 0194) — there is nothing left to lay them on.
    require_once __DIR__ . '/picture_edits.php';
    picture_edits_forget($pdo, 'photo', $photoId);

    // First the row, then the file. The other way round, an error in
    // between would leave an entry pointing at nothing — a broken image in
    // the view. This way, at worst a file stays behind that nobody sees;
    // photos_orphans_remove() finds those.
    photo_files_remove($userId, (string) $photo['filename']);
    photo_cloud_forget($pdo, $userId, $photo);
    // And its kept preview (includes/thumbs.php).
    require_once __DIR__ . '/thumbs.php';
    thumb_forget($userId, 'photo', $photoId);
    /* „DAS WIRD DANN BEI ALLEN MIT AUS DER CLOUD GELÖSCHT"
       (11.09.2026). It cuts against his own rule that work, once done, is
       never lost — and he chose it anyway, for two reasons he named: the
       photographer is the picture's real owner and holds the rights to
       it; and on a shared find the TIDYING has to run in sync, or every
       collaborator of an excursion would have to weed out the guest's
       dozens of pictures himself, of which one or two are worth keeping.
       The worker removes the copies' files, then their rows. */
    require_once __DIR__ . '/cloud_copies_content.php';
    cloud_copies_retire($pdo, 'photo', $photoId);
    photo_point_touched($pdo, (int) $photo['point_id']);
    // The pictures behind it move up a number (cloud_names.php).
    require_once __DIR__ . '/cloud_names.php';
    cloud_names_touch($pdo, (int) $photo['point_id']);
    return true;
}

/**
 * THE WHOLE PICTURE REPLACES ITS SHRUNK COPY (03.10.2026,
 * variant A of „Fotos in die Galerie, später im WLAN in die Cloud"). In the
 * Android app a shot lands whole in the phone's gallery; without reception
 * or without the mobile-data tick its 2048 px copy goes up first, so the
 * find shows a picture at once — and when the app has carried the original
 * into the cloud on Wi-Fi, that copy has done its job. The recording takes
 * the copy's Motivname and its place in the gallery, and the copy goes,
 * from the server, the cloud and the co-owners' copies alike (photo_delete).
 *
 * KEPT, NOT REPLACED, WHEN SOMEBODY MEASURED ON THE COPY: a scale or a
 * measure is in the small picture's pixels, and moving it onto the
 * original would be a guess. Then both stand at the find — the price of
 * work that must not be lost.
 *
 * @return bool whether the copy was replaced
 */
function photo_replace_with_recording(PDO $pdo, int $userId, int $pointId, int $photoId, int $recordingId): bool
{
    $stmt = $pdo->prepare('SELECT * FROM map_photos WHERE id = ? AND user_id = ? AND point_id = ? LIMIT 1');
    $stmt->execute([$photoId, $userId, $pointId]);
    $photo = $stmt->fetch();
    if ($photo === false) {
        return false;
    }
    if (photo_scale_read($photo['scale'] ?? null) !== null || photo_measures_read($photo['measures'] ?? null) !== []) {
        return false;
    }
    $title = photo_title_clean($photo['title'] ?? null);
    $sets   = [];
    $values = [];
    if ($title !== null) {
        // Only where the recording carries none — a name given on the way wins.
        $sets[]   = 'title = COALESCE(title, ?)';
        $values[] = $title;
    }
    if (cloud_files_sortable($pdo)) {
        $sets[]   = 'sort_order = ?';
        $values[] = (int) ($photo['sort_order'] ?? 0);
    }
    if ($sets !== []) {
        $pdo->prepare('UPDATE map_cloud_files SET ' . implode(', ', $sets) . ' WHERE id = ? AND user_id = ? AND point_id = ?')
            ->execute([...$values, $recordingId, $userId, $pointId]);
    }
    return photo_delete($pdo, $userId, $photoId);
}

/**
 * Away with it over there too, if it went there.
 *
 * WITHOUT THIS THE PICTURE OUTLIVES ITS RECORD. It would lie in a folder
 * whose find no longer knows of it — and nothing in this application would
 * ever mention it again. pCloud puts it in its own trash, so a deletion by
 * mistake is still recoverable there for a while (measured 19.08.2026).
 *
 * Best effort, and deliberately after the row is gone: whoever presses
 * delete has said what they want, and a provider that is unreachable must
 * not keep a photo alive here.
 *
 * Stands in this file and not in photo_cloud.php so that every route to
 * deletion passes it. It loads the cloud side only when there is something
 * to do — photos.php has no business knowing pCloud otherwise.
 */
function photo_cloud_forget(PDO $pdo, int $userId, array $photo): void
{
    $fileid = (string) ($photo['cloud_fileid'] ?? '');
    if ($fileid === '') {
        return;
    }

    // Deleted where it LIES — the picture's own cloud (26.09.2026), not the
    // one connected now; rows from before the column was read are pCloud's.
    require_once __DIR__ . '/photo_cloud.php';
    $provider = (string) ($photo['cloud_provider'] ?? '') ?: 'pcloud';
    $s = cloud_storage_for($pdo, $userId, $provider);
    if ($s === null) {
        return;
    }

    /* The sidecar goes FIRST — finding it needs the picture's name, and
       once the picture is deleted nobody can ask for it any more. A
       sidecar surviving its picture would be exactly the clutter the
       tombstone decision avoided (measure_sidecar_store). */
    measure_sidecar_store($pdo, $userId, $provider, $fileid, null, [], null, null);

    try {
        // Gone already is the desired state — the seam counts it as done.
        // At S3 the app's own previews go along (storage_picture_delete).
        storage_picture_delete($pdo, $userId, $s, $fileid);
    } catch (RuntimeException $e) {
        error_log('photo_cloud_forget (' . $provider . ' ' . $fileid . '): ' . $e->getMessage());
    }
}

/**
 * Files with nobody left to point at them.
 *
 * THIS IS THE ONLY WAY A COPY CAN STAY BEHIND. Every ordinary route removes
 * the file itself: deleting takes it, moving into the cloud takes it, and a
 * failed INSERT takes it back out again. What remains is the gap between
 * two steps — the row already says "lies in the cloud" and the process dies
 * before the unlink, or a deletion gets as far as the row and no further.
 * Then a picture lies on this server that nothing mentions any more, and it
 * counts against the quota forever.
 *
 * A comment named this function long before it existed, which is its own
 * small lesson: a reference to something that is not there reads exactly
 * like a reassurance.
 *
 * THE DIRECTORY IS THE TRUTH, and the rows say what may be in it: only a
 * photo that is NOT in the cloud belongs here. Everything else in there —
 * whatever it is called — has no owner.
 *
 * @return int how many files went
 */
function photos_orphans_remove(PDO $pdo, int $userId): int
{
    $directory = user_photo_directory($userId);
    if (!is_dir($directory) || !photos_ready($pdo)) {
        return 0;
    }

    $cloudKnown = column_exists($pdo, 'map_photos', 'cloud_fileid');
    $stmt = $pdo->prepare(
        'SELECT filename FROM map_photos WHERE user_id = ?'
        . ($cloudKnown ? ' AND cloud_fileid IS NULL' : '')
    );
    $stmt->execute([$userId]);

    $allowed = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $allowed[$name] = true;
        $allowed[substr((string) $name, 0, 32) . '_k.jpg'] = true;
    }

    $gone = 0;
    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || isset($allowed[$entry])) {
            continue;
        }
        if (is_file($directory . '/' . $entry) && @unlink($directory . '/' . $entry)) {
            $gone++;
        }
    }

    return $gone;
}

/** Take a photo's two files off the disk. */
function photo_files_remove(int $userId, string $fileName): void
{
    foreach ([false, true] as $small) {
        $path = photo_file($userId, $fileName, $small);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * Clear away all files of a point.
 *
 * To be called BEFORE the point vanishes for good: the foreign key then
 * clears away the rows, and whoever did not look beforehand no longer
 * knows afterwards which files belonged to it.
 */
function photos_of_point_remove(PDO $pdo, int $userId, int $pointId): int
{
    if (!photos_ready($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT * FROM map_photos WHERE user_id = ? AND point_id = ?');
    $stmt->execute([$userId, $pointId]);

    $count = 0;
    foreach ($stmt->fetchAll() as $photo) {
        photo_files_remove($userId, (string) $photo['filename']);
        photo_cloud_forget($pdo, $userId, $photo);
        $count++;
    }
    return $count;
}

// ---------------------------------------------------------------------------
// Preparing values
// ---------------------------------------------------------------------------

/** Bring the device's time to the database format, or null. */
function photo_datetime(mixed $value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    $time = strtotime($value);
    if ($time === false) {
        return null;
    }
    // Capture times in the future do not exist; a wrongly set clock in the
    // camera does. Better nothing than something wrong.
    if ($time > time() + 86400) {
        return null;
    }
    return date('Y-m-d H:i:s', $time);
}

/** A value in degrees, or null. */
function photo_degrees(mixed $value, float $limit): ?float
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return abs($number) <= $limit ? $number : null;
}

/** Short text from a foreign source. */
function photo_text(mixed $value, int $length): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $clean = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');
    return $clean === '' ? null : mb_substr($clean, 0, $length);
}
