<?php
/**
 * Tests the voucher photos against the database.
 *
 *   php tests/photos.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   The size check. It is the only place where an image gets rejected
 *   that did not pass through our downscaling. If it fails, a sender can
 *   fill the quota with raw images.
 *
 *   The path building. photo_file() gets a name from the database and
 *   builds a path from it. Whoever does not check here will at some point
 *   have directions in it that lead out of the directory.
 *
 *   The clean-up. A point that disappears for good takes its images
 *   along. If a file stays behind, it counts against the quota forever —
 *   and nobody can find it any more, because the row is gone.
 *
 *   And the quota itself: it is asked BEFORE storing.
 *
 * Everything created here is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/photos.php';
require_once __DIR__ . '/../includes/bucket.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

/** A real JPEG of the desired dimensions. */
function testImage(int $width, int $height, int $quality = 80): string
{
    $image = imagecreatetruecolor($width, $height);
    // Some structure, otherwise a single-colour area compresses to almost
    // nothing and the size check would run into a void.
    for ($i = 0; $i < 40; $i++) {
        imagefilledellipse(
            $image,
            random_int(0, $width), random_int(0, $height),
            60, 60,
            imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255))
        );
    }
    $path = tempnam(sys_get_temp_dir(), 'pruef') . '.jpg';
    imagejpeg($image, $path, $quality);
    return $path;
}

echo "\nVoucher photos\n\n";

// ---------------------------------------------------------------------------
echo "Readiness\n";

check(photos_ready($pdo), 'The table has the columns from migration 0031');

if (!photos_ready($pdo)) {
    echo "\nWithout migration 0031 there is nothing to test here.\n";
    exit(1);
}

// ---------------------------------------------------------------------------
echo "\nPaths\n";

check(photo_file(7, 'nichtsdergleichen') === null, 'A name without the expected form is rejected');
check(photo_file(7, '../../../etc/passwd') === null, 'Directions leading upwards are rejected');
check(photo_file(7, str_repeat('a', 32) . '.png') === null, 'A different extension is rejected');

$name = str_repeat('a', 32) . '.jpg';
$large = photo_file(7, $name);
$small = photo_file(7, $name, true);
check(is_string($large) && str_ends_with($large, '/7/photos/' . $name), 'The image lies in its user\'s directory');
check(is_string($small) && str_ends_with($small, '_k.jpg'), 'The preview image carries "_k"');
check(!str_contains((string) $large, '..'), 'The path contains no ".."');

// ---------------------------------------------------------------------------
echo "\nImage check\n";

$small2048 = testImage(2048, 1536);
$tooLarge  = testImage(3000, 2000);
$landscape = testImage(320, 240);

try {
    [$b, $h] = photo_image_check($small2048, PHOTO_EDGE_MAX, PHOTO_BYTES_MAX, 'Das Bild');
    check($b === 2048 && $h === 1536, 'An image with a 2048 px long edge is accepted');
} catch (Throwable $e) {
    check(false, 'An image with a 2048 px long edge is accepted (' . $e->getMessage() . ')');
}

try {
    photo_image_check($tooLarge, PHOTO_EDGE_MAX, PHOTO_BYTES_MAX, 'Das Bild');
    check(false, '3000 px are rejected');
} catch (InvalidArgumentException $e) {
    check(str_contains($e->getMessage(), '3000'), '3000 px are rejected, and the message names the dimensions');
}

// Something that is not a JPEG — the transmitted type does not help here.
$broken = tempnam(sys_get_temp_dir(), 'pruef') . '.jpg';
file_put_contents($broken, "Das ist kein Bild, sondern Text.\n");
try {
    photo_image_check($broken, PHOTO_EDGE_MAX, PHOTO_BYTES_MAX, 'Das Bild');
    check(false, 'A text file with the extension .jpg is rejected');
} catch (InvalidArgumentException $e) {
    check(str_contains($e->getMessage(), 'JPEG'), 'A text file with the extension .jpg is rejected');
}

$empty = tempnam(sys_get_temp_dir(), 'pruef') . '.jpg';
file_put_contents($empty, '');
try {
    photo_image_check($empty, PHOTO_EDGE_MAX, PHOTO_BYTES_MAX, 'Das Bild');
    check(false, 'An empty file is rejected');
} catch (InvalidArgumentException $e) {
    check(true, 'An empty file is rejected');
}

try {
    photo_image_check($landscape, PHOTO_PREVIEW_EDGE_MAX, PHOTO_PREVIEW_BYTES_MAX, 'Das Vorschaubild');
    check(true, 'A preview image with 320 px is accepted');
} catch (Throwable $e) {
    check(false, 'A preview image with 320 px is accepted (' . $e->getMessage() . ')');
}

try {
    photo_image_check($small2048, PHOTO_PREVIEW_EDGE_MAX, PHOTO_PREVIEW_BYTES_MAX, 'Das Vorschaubild');
    check(false, 'A 2048 one as preview image is rejected');
} catch (InvalidArgumentException $e) {
    check(true, 'A 2048 one as preview image is rejected');
}

// ---------------------------------------------------------------------------
echo "\nValues from a foreign source\n";

check(photo_datetime('2026-08-09 14:23:07') === '2026-08-09 14:23:07', 'A valid timestamp comes through');
check(photo_datetime('') === null, 'Empty yields null');
check(photo_datetime('völliger Unsinn') === null, 'Unreadable yields null');
check(photo_datetime('2099-01-01 00:00:00') === null, 'A capture from the future is discarded');

check(photo_degrees('51,5', 90) === null, 'A comma is not a number — machine output arrives here, not user input');
check(photo_degrees('51.5', 90) === 51.5, 'A degree value comes through');
check(photo_degrees(200, 180) === null, 'Out of range yields null');
check(photo_degrees(null, 90) === null, 'Nothing yields null');

check(photo_text("Apple\x00 iPhone", 120) === 'Apple iPhone', 'Control characters are thrown out');
check(photo_text('   ', 120) === null, 'Only spaces yields null');
check(mb_strlen((string) photo_text(str_repeat('x', 300), 120)) === 120, 'Overlong text is shortened');

// ---------------------------------------------------------------------------
echo "\nStorage, quota and clean-up\n";

$userId = (int) $pdo->query('SELECT id FROM map_users ORDER BY id LIMIT 1')->fetchColumn();
if ($userId <= 0) {
    echo "  (skipped — there is no user)\n";
    exit($failed > 0 ? 1 : 0);
}

// A point of our own to play with.
$pdo->prepare(
    'INSERT INTO map_points (user_id, lat, lon, title, created_at, updated_at)
     VALUES (?, 51.0, 7.0, ?, NOW(), NOW())'
)->execute([$userId, 'PRÜFUNG Belegfotos']);
$pointId = (int) $pdo->lastInsertId();

$directory = user_photo_directory_create($userId);
$created = [];

/** Store a photo the way photo_create would — without the upload route. */
$store = function (int $point, int $sortOrder) use ($pdo, $userId, $directory, &$created): int {
    $stem = bin2hex(random_bytes(16));
    $source = testImage(2048, 1536);
    copy($source, $directory . '/' . $stem . '.jpg');
    copy(testImage(320, 240), $directory . '/' . $stem . '_k.jpg');
    @unlink($source);

    $bytes = filesize($directory . '/' . $stem . '.jpg') + filesize($directory . '/' . $stem . '_k.jpg');
    $pdo->prepare(
        'INSERT INTO map_photos (user_id, point_id, filename, width, height, bytes, sort_order, created_at)
         VALUES (?, ?, ?, 2048, 1536, ?, ?, NOW())'
    )->execute([$userId, $point, $stem . '.jpg', $bytes, $sortOrder]);

    $created[] = $stem;
    return (int) $pdo->lastInsertId();
};

$photoA = $store($pointId, 0);
$photoB = $store($pointId, 1);

$loaded = photos_of_point($pdo, $userId, $pointId);
check(count($loaded) === 2, 'Both photos hang on the point');
check(($loaded[0]['sort_order'] ?? -1) === 0 && ($loaded[1]['sort_order'] ?? -1) === 1, 'The order is right');
check(($loaded[0]['width'] ?? 0) === 2048, 'The dimensions come back as numbers');

$inPoint = point_load($pdo, $userId, $pointId);
check(count($inPoint['photos'] ?? []) === 2, 'The point brings its photos along');

$many = points_load($pdo, $userId, []);
$thisOne = null;
foreach ($many as $p) { if ((int) $p['id'] === $pointId) { $thisOne = $p; } }
check(count($thisOne['photos'] ?? []) === 2, 'They are also there when loading all points');

check(photo_next_sort_order($pdo, $pointId) === 2, 'The next photo would go to the end');

// Foreign access
$foreign = $userId + 100000;
check(photo_load($pdo, $foreign, $photoA) === null, 'A foreign user does not get the photo');
check(photos_of_point($pdo, $foreign, $pointId) === [], 'And not the list either');

// ---------------------------------------------------------------------------
// EVERY PHOTO WRITE MUST MOVE THE BUCKET FINGERPRINT. The offline buckets
// fingerprint over map_points alone (bucket.php); the photos ride in the
// point baggage. A write that leaves touched_at alone therefore never
// reaches another device — proven 28.08.2026 on point 3228, whose bucket
// the browser trusted until touched_at was set by hand.
//
// The point's updated_at is primed to NOW() first, ON PURPOSE: the broken
// variant of the nudge wrote only updated_at = NOW() and relied on
// touched_at's ON UPDATE — which the database skips when the value does
// not actually change. With the priming, a write in the same second is
// exactly that no-op, and this check catches anyone who reverts
// photo_point_touched to it.
$bucketKey   = bucket_key_of(['found_at' => null, 'created_at' => date('Y-m-d H:i:s')]);
$fingerprint = static function () use ($pdo, $userId, $bucketKey): string {
    return bucket_status($pdo, $userId)['finger'][$bucketKey] ?? 'empty';
};

if (column_exists($pdo, 'map_photos', 'scale')) {
    $pdo->prepare('UPDATE map_points SET updated_at = NOW() WHERE id = ?')->execute([$pointId]);
    $print = $fingerprint();
    check(
        photo_scale_store($pdo, $userId, $photoB, ['line' => [[0, 0], [100, 0]], 'um' => 1000, 'corner' => 'br']),
        'A calibration can be stored'
    );
    check($fingerprint() !== $print, 'And it moves the bucket fingerprint of the point');
}

// ---------------------------------------------------------------------------
// THE GALLERY'S ORDER (photos_reorder, 30.09.2026) — a drag renumbers the
// whole set, and like every other photo write it must reach the other
// devices through the bucket fingerprint.
$pdo->prepare('UPDATE map_points SET updated_at = NOW() WHERE id = ?')->execute([$pointId]);
$print = $fingerprint();
check(photos_reorder($pdo, $pointId, [$photoB, $photoB, $photoA]) === ["photo:$photoB", "photo:$photoA"],
    'The gallery order can be turned round, a doubled id counts once');
$loaded = photos_of_point($pdo, $userId, $pointId);
check(array_column($loaded, 'id') === [$photoB, $photoA], 'And the point brings its photos in that order');
check(array_column($loaded, 'sort_order') === [0, 1], 'Numbered from nought, without gaps');
check($fingerprint() !== $print, 'Reordering moves the bucket fingerprint of the point');
check(photos_reorder($pdo, $pointId, [$photoA + 999999, $photoA]) === ["photo:$photoA", "photo:$photoB"],
    'An id from elsewhere is passed over, and what was not sent follows in its old order');

// ---------------------------------------------------------------------------
// THE CLOUD RECORDINGS IN THE SAME ROW (migration 0148) — the main part of
// a find, and the part the first cut left out: on the desktop nothing
// moved, because everything there came in through „Auswählen".
if (cloud_files_sortable($pdo)) {
    require_once __DIR__ . '/../includes/cloud.php';
    $record = function (string $fileid) use ($pdo, $userId, $pointId): int {
        $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, name, size, sort_order, uploaded_at)
                       VALUES (?, ?, "pcloud", ?, ?, 100, ?, NOW())')
            ->execute([$userId, $pointId, $fileid, "rec$fileid.jpg", photo_next_sort_order($pdo, $pointId)]);
        return (int) $pdo->lastInsertId();
    };
    $cloudA = $record('9001');
    $cloudB = $record('9002');
    $numbers = $pdo->query("SELECT sort_order FROM map_cloud_files WHERE id IN ($cloudA, $cloudB) ORDER BY id")
        ->fetchAll(PDO::FETCH_COLUMN);
    check(array_map('intval', $numbers) === [2, 3], 'A new recording goes behind the photos, the next one behind it');
    check(photo_next_sort_order($pdo, $pointId) === 4, 'And a new photo behind both recordings');

    check(photos_reorder($pdo, $pointId, ["cloud:$cloudB", "photo:$photoB", "cloud:$cloudA", $photoA])
        === ["cloud:$cloudB", "photo:$photoB", "cloud:$cloudA", "photo:$photoA"],
        'Photos and recordings swap places with each other, a bare number still counts as a photo');
    check(array_column(cloud_files_rows($pdo, $userId, $pointId), 'id') === [$cloudB, $cloudA]
        && array_column(cloud_files_rows($pdo, $userId, $pointId), 'sort_order') === [0, 2],
        'The cloud listing comes in that order and carries its places');
    $between = photos_of_point($pdo, $userId, $pointId);
    check(array_column($between, 'id') === [$photoB, $photoA] && array_column($between, 'sort_order') === [1, 3],
        'And the photos between them keep their own places');
    check(photos_reorder($pdo, $pointId, ["cloud:$photoA", "photo:$cloudB"]) === ["cloud:$cloudB", "photo:$photoB", "cloud:$cloudA", "photo:$photoA"],
        'A key of the wrong kind moves nothing');

    $pdo->exec("DELETE FROM map_cloud_files WHERE id IN ($cloudA, $cloudB)");
    photos_reorder($pdo, $pointId, [$photoA, $photoB]);
}

// Deleting a single one takes the files along
$before = user_used($userId);
$pdo->prepare('UPDATE map_points SET updated_at = NOW() WHERE id = ?')->execute([$pointId]);
$print = $fingerprint();
check(photo_delete($pdo, $userId, $photoA), 'A photo can be deleted');
check($fingerprint() !== $print, 'Deleting moves the bucket fingerprint of the point');
check(!is_file($directory . '/' . $created[0] . '.jpg'), 'The image file is gone');
check(!is_file($directory . '/' . $created[0] . '_k.jpg'), 'The preview image is gone');
check(user_used($userId) < $before, 'The space is free again');
check(!photo_delete($pdo, $userId, $photoA), 'A second deletion of the same photo reports itself as without effect');
check(count(photos_of_point($pdo, $userId, $pointId)) === 1, 'The other photo still stands');

// Quota
$limit = user_used($userId) + 1000;
$pdo->prepare('UPDATE map_users SET quota_bytes = ? WHERE id = ?')->execute([$limit, $userId]);
check(!user_fits($pdo, $userId, 500000), 'An image that no longer fits is recognized beforehand');
check(user_fits($pdo, $userId, 500), 'What fits goes through');
$pdo->prepare('UPDATE map_users SET quota_bytes = NULL WHERE id = ?')->execute([$userId]);
check(user_fits($pdo, $userId, 999999999), 'Without a quota everything fits');

// The point goes for good — its images with it
check(!point_delete_permanently($pdo, $userId, $pointId), 'A point outside the trash is not permanently deleted');
check(is_file($directory . '/' . $created[1] . '.jpg'), 'And its images stay untouched in the process');

point_delete($pdo, $userId, $pointId);
check(point_delete_permanently($pdo, $userId, $pointId), 'From the trash it works');
check(!is_file($directory . '/' . $created[1] . '.jpg'), 'The image file went along');
check(!is_file($directory . '/' . $created[1] . '_k.jpg'), 'The preview image too');

$remaining = $pdo->prepare('SELECT COUNT(*) FROM map_photos WHERE point_id = ?');
$remaining->execute([$pointId]);
check((int) $remaining->fetchColumn() === 0, 'And the rows are gone');

// ---------------------------------------------------------------------------
// A NEW STOCK NAME REACHES A SAVED LIST (30.09.2026, PHOTO_TITLES_KNOWN_BEFORE)
// — and a name the list knew and dropped stays dropped. Pure functions, no
// database: the style is the JSON the column holds.
echo "Own things of a known length:\n";
check(measure_style_read(null)['scale_objects'] === [], 'Nobody has own things to begin with');
$things = scale_objects_clean([
    ['name' => ' Feuerzeug ', 'um' => 81000], ['name' => '', 'um' => 5000], ['name' => 'Zu klein', 'um' => 0.5],
    ['name' => 'Lupe', 'um' => '30000.12345'], 'kein Objekt', ['name' => 'Zu groß', 'um' => 2000000],
]);
check($things === [['name' => 'Feuerzeug', 'um' => 81000.0], ['name' => 'Lupe', 'um' => 30000.123]],
    'A name is trimmed, a length between 1 µm and 1 m kept to three decimals, the rest falls away');
check(count(scale_objects_clean(array_fill(0, 9, ['name' => 'x', 'um' => 10]))) === SCALE_OBJECTS_MAX,
    'At most ' . SCALE_OBJECTS_MAX . ' — a row of buttons');
$withThings = measure_style_read(null);
$withThings['scale_objects'] = $things;
check(measure_style_read(measure_style_write($withThings))['scale_objects'] === $things,
    'Written and read back, the things are the same');
check(measure_style_write(measure_style_read(null)) === null, 'Without things nothing extra is stored');

echo "New subject names reach saved lists:\n";
$fresh = measure_style_read(null);
check($fresh['photo_titles_macro'] === PHOTO_TITLES_MACRO && $fresh['photo_titles_macro_known'] === PHOTO_TITLES_MACRO,
    'Without a saved list the stock stands, and the stock counts as known');

// Saved before the ledger: Habitat and the rest were known and dropped,
// Kollektion and Detail came later.
$old = json_encode(['photo_titles_macro' => ['Belegfoto', 'Hauptmotiv', 'Eigenes Wort']]);
$read = measure_style_read($old);
check($read['photo_titles_macro'] === ['Belegfoto', 'Hauptmotiv', 'Eigenes Wort', 'Kollektion',
                                        'Größenvergleich', 'Detail', 'Makrochemie', 'Makrochemie SPP', 'Sklerotium'],
    'A list saved before the ledger gets the names added since at its end, in stock order, not the ones it dropped');
check($read['photo_titles_micro'] === PHOTO_TITLES_MICRO, 'The untouched micro list stays the stock');
$oldMicro = measure_style_read(json_encode(['photo_titles_micro' => ['Sporen', 'Basidien', 'Mein Wort']]));
check($oldMicro['photo_titles_micro'] === ['Sporen', 'Basidien', 'Mein Wort', 'Übersichtsschnitt', 'Konidien', 'Velum', 'Hymenium', 'Randhaare', 'Excipulum',
                                          'Hyphen hymenial', 'Hyphen Kontext', 'Hyphen Stiel', 'Myzel', 'Capillitium', 'Sklerotium'],
    'A micro list saved before the ledger gets the names added since — the baseline is written out, not the stock');
$retired = measure_style_read(json_encode(['photo_titles_micro' => ['Sporen', 'Generative Hyphen', 'Bindehyphen', 'Mein Wort'],
                                          'photo_titles_micro_known' => PHOTO_TITLES_MICRO]));
check($retired['photo_titles_micro'] === ['Sporen', 'Mein Wort'] && $retired['photo_titles_micro_retired'] === PHOTO_TITLES_RETIRED,
    'A retired stock word leaves a saved list on reading, and the reader is told it was micro');
$both = measure_style_read(null)['photo_titles'];
check(in_array('Velum', PHOTO_TITLES_MACRO, true) && in_array('Velum', PHOTO_TITLES_MICRO, true)
      && count($both) === count(array_unique($both)) && array_is_list($both),
    'A word in both lists (Velum) stands once in the combined list');

$again = measure_style_read(measure_style_write($read));
check($again['photo_titles_macro'] === $read['photo_titles_macro'],
    'Written back and read again, nothing is added twice');

$dropped = $read;
$dropped['photo_titles_macro'] = ['Belegfoto', 'Hauptmotiv', 'Eigenes Wort', 'Kollektion', 'Makrochemie'];
$stored = measure_style_write($dropped);
check(measure_style_read($stored)['photo_titles_macro'] === ['Belegfoto', 'Hauptmotiv', 'Eigenes Wort', 'Kollektion', 'Makrochemie'],
    'A stock name deleted in the form stays deleted');
check(json_decode($stored, true)['photo_titles_macro_known'] === PHOTO_TITLES_MACRO,
    'The saved list carries the stock it knew');

// „Aufnehmen" in full size over mobile data (03.10.2026): off by default,
// and only the ON is stored.
check($fresh['full_size_mobile'] === false, 'Full size over mobile data starts switched off');
$on = $read;
$on['full_size_mobile'] = true;
check(measure_style_read(measure_style_write($on))['full_size_mobile'] === true, 'Ticked, it is stored and read back');
check(!array_key_exists('full_size_mobile', json_decode((string) measure_style_write($read), true) ?? []),
    'Off is the default and is not stored');

// A stock name of tomorrow, by the helper itself.
check(photo_titles_unmet(['A'], ['A', 'B'], ['A', 'B', 'C']) === ['C'],
    'A name the stock gains later is the one that arrives');
check(photo_titles_unmet(['kollektion'], [], ['Kollektion', 'Detail']) === ['Detail'],
    'Upper and lower case do not make a second name');

// A form from before the hidden field sends no known set: the baseline.
// The plates' hand (06.10.2026): the default stores nothing, a deviation comes back, nonsense falls back.
check(measure_style_read(null)['plate'] === PLATE_STYLE_DEFAULTS
      && !array_key_exists('plate', json_decode((string) measure_style_write($read), true) ?? []),
    'A plate style at its default is not stored');
$plated = measure_style_read(measure_style_write([...$read, 'plate' => ['gutter' => 1.5, 'gutter_color' => '#000000', 'text_pt' => 14, 'values_pt' => 999]]));
check($plated['plate'] === ['gutter' => 1.5, 'gutter_color' => '#000000', 'text_pt' => 14, 'details_pt' => 9, 'values_pt' => 9],
    'A plate style is stored and read back, a size out of range falls back');

$noKnown = measure_style_write(['scale_color' => '#ff0000', 'scale_seam_color' => '#000000', 'line_color' => '#ff0000',
    'seam_color' => '#000000', 'font_px' => 16, 'photo_titles_macro' => ['Belegfoto']]);
check(json_decode($noKnown, true)['photo_titles_macro_known'] === PHOTO_TITLES_KNOWN_BEFORE['photo_titles_macro']
    && measure_style_read($noKnown)['photo_titles_macro'] === ['Belegfoto', 'Kollektion', 'Größenvergleich', 'Detail',
                                                               'Makrochemie', 'Makrochemie SPP', 'Sklerotium'],
    'Without a known set the baseline decides, and the newer names still arrive');

// A stock word renamed (PHOTO_TITLES_RENAMED): a list saved with the old word holds the new one once, in its place.
$renamed = measure_style_read(json_encode(['photo_titles_macro' => ['Hauptmotiv', 'Makrochemie Sporenpulver', 'Eigenes'],
    'photo_titles_macro_known' => [...PHOTO_TITLES_KNOWN_BEFORE['photo_titles_macro'], 'Kollektion', 'Größenvergleich', 'Detail',
                                   'Makrochemie', 'Makrochemie Sporenpulver']]));
// Sklerotium (18.11.2026) was never known to this list, so it arrives at the end.
check($renamed['photo_titles_macro'] === ['Hauptmotiv', 'Makrochemie SPP', 'Eigenes', 'Sklerotium'],
    'A saved list with a renamed stock word reads the new word in its place, not twice');

// ---------------------------------------------------------------------------
// THE WHOLE PICTURE REPLACES ITS SHRUNK COPY (03.10.2026, the app's gallery
// path) — and leaves a measured copy standing. Rows only: the copy's file
// does not exist, photo_delete() removes what is there.
echo "A recorded original replaces its shrunk copy:\n";
if (cloud_files_sortable($pdo)) {
    // A point of its own: the one above went to the trash for good already.
    $replacePoint = point_create($pdo, $userId, ['lat' => 50.5, 'lon' => 9.5, 'title' => 'PRUEF Ersatz']);
    $copyRow = static function (?string $scale) use ($pdo, $userId, $replacePoint): int {
        $pdo->prepare(
            "INSERT INTO map_photos (user_id, point_id, filename, original_name, width, height, bytes, title, scale, sort_order, created_at)
             VALUES (?, ?, ?, 'aufnahme.jpg', 2048, 1365, 1000, 'Hauptmotiv', ?, 4, NOW())"
        )->execute([$userId, $replacePoint, bin2hex(random_bytes(16)), $scale]);
        return (int) $pdo->lastInsertId();
    };
    $recordingRow = static function () use ($pdo, $userId, $replacePoint): int {
        $pdo->prepare(
            "INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, name, path, size, sort_order, uploaded_at)
             VALUES (?, ?, 'pcloud', ?, 'aufnahme.jpg', 'Test', 9000000, 9, NOW())"
        )->execute([$userId, $replacePoint, (string) random_int(100000, 999999)]);
        return (int) $pdo->lastInsertId();
    };
    $copy = $copyRow(null);
    $whole = $recordingRow();
    check(photo_replace_with_recording($pdo, $userId, $replacePoint, $copy, $whole), 'The copy is replaced');
    $row = $pdo->query("SELECT title, sort_order FROM map_cloud_files WHERE id = $whole")->fetch();
    check($row['title'] === 'Hauptmotiv' && (int) $row['sort_order'] === 4,
        'The original takes its Motivname and its place in the gallery');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_photos WHERE id = $copy")->fetchColumn() === 0, 'and the copy is gone');

    $measured = $copyRow(json_encode(['line' => [[10, 10], [110, 10]], 'um' => 50]));
    $whole2 = $recordingRow();
    check(!photo_replace_with_recording($pdo, $userId, $replacePoint, $measured, $whole2)
        && (int) $pdo->query("SELECT COUNT(*) FROM map_photos WHERE id = $measured")->fetchColumn() === 1,
        'A copy with a scale on it stays — its measures are in the small picture\'s pixels');
    $pdo->prepare('DELETE FROM map_photos WHERE id = ?')->execute([$measured]);
    $pdo->prepare('DELETE FROM map_cloud_files WHERE id IN (?, ?)')->execute([$whole, $whole2]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ?')->execute([$replacePoint]);
}

// ---------------------------------------------------------------------------
// Clean-up
foreach ($created as $stem) {
    @unlink($directory . '/' . $stem . '.jpg');
    @unlink($directory . '/' . $stem . '_k.jpg');
}
foreach ([$small2048, $tooLarge, $landscape, $broken, $empty] as $file) {
    @unlink($file);
}
$pdo->prepare('DELETE FROM map_points WHERE id = ?')->execute([$pointId]);

echo "\nThe spore tile's mirror (04.10.2026)\n";
$rect = ['type' => 'rect', 'points' => [[0, 5], [10, 5], [5, 3], [5, 7]], 'width' => 100, 'label' => 'Sporen'];
$kept = photo_measures_write([$rect + ['mirror' => 'hv'], $rect + ['mirror' => 'v']]);
check(($kept[0]['mirror'] ?? null) === 'hv' && ($kept[1]['mirror'] ?? null) === 'v',
    'a rectangle keeps how its tile is mirrored');
$dropped = photo_measures_write([$rect + ['mirror' => 'x'], ['type' => 'line', 'points' => [[0, 0], [1, 1]], 'mirror' => 'h']]);
check(!isset($dropped[0]['mirror']) && !isset($dropped[1]['mirror']),
    'nonsense is dropped, and a plain line has no tile to mirror');
$partMirror = photo_measures_write([['type' => 'line', 'points' => [[0, 0], [1, 1]], 'part' => 'Keimporus', 'mirror' => 'v']]);
check(($partMirror[0]['mirror'] ?? null) === 'v', 'a part measure keeps how its tile is mirrored (07.10.2026)');

echo "\nText in the picture and the arrow (05.10.2026)\n";
$written = photo_measures_write([
    ['type' => 'text', 'points' => [[40, 50]], 'text' => 'Schnalle', 'width' => 100, 'size' => 12.345, 'angle' => 450],
    ['type' => 'text', 'points' => [[40, 50]], 'text' => 'alt', 'width' => 100],
    ['type' => 'text', 'points' => [[40, 50]], 'text' => 'Unsinn', 'size' => -3, 'angle' => 'quer'],
    ['type' => 'text', 'points' => [[40, 50]], 'text' => 'herum', 'angle' => -190],
    ['type' => 'arrow', 'points' => [[10, 10], [30, 25]], 'width' => 100, 'label' => 'Sporen', 'weight' => 5],
    ['type' => 'arrow', 'points' => [[10, 10], [30, 25]], 'weight' => 9],
]);
check(($written[0]['size'] ?? null) === 12.3 && ($written[0]['angle'] ?? null) === 90.0,
    'a text keeps its size and its turn, the turn brought into one round');
check(!isset($written[1]['size']) && !isset($written[1]['angle']),
    'a text without size or turn stays as it was written before');
check(!isset($written[2]['size']) && !isset($written[2]['angle']) && $written[2]['text'] === 'Unsinn',
    'nonsense size and turn are dropped, the text stays');
check(($written[3]['angle'] ?? null) === 170.0, 'a turn past -180 comes round to the other side');
$lines = photo_measures_write([['type' => 'text', 'points' => [[40, 50]], 'text' => "Hals\r\nund Kopf\n"]]);
check($lines[0]['text'] === "Hals\nund Kopf", 'a text keeps its lines, every break stored as \\n, the last trimmed (07.10.2026)');
check($written[4]['type'] === 'arrow' && count($written[4]['points']) === 2
    && ($written[4]['label'] ?? null) === 'Sporen', 'an arrow is stored with tail, tip and layer');
check(($written[4]['weight'] ?? null) === 5 && !isset($written[5]['weight']),
    'an arrow keeps its strength step, a ninth step is dropped');
$labelled = photo_measures_write([
    ['type' => 'arrow', 'points' => [[10, 10], [30, 25]], 'text' => " Ast\r\nmit Fund ", 'size' => 14.26, 'place' => 'sw'],
    ['type' => 'arrow', 'points' => [[10, 10], [30, 25]], 'text' => 'Ast', 'place' => 'oben', 'size' => 'groß'],
    ['type' => 'arrow', 'points' => [[10, 10], [30, 25]], 'text' => '  ', 'size' => 14, 'place' => 'n'],
]);
check($labelled[0]['text'] === "Ast\nmit Fund" && $labelled[0]['size'] === 14.3 && !isset($labelled[0]['place']),
    'an arrow keeps its own words and their size; no side by hand any more (30.10.2026)');
check($labelled[1]['text'] === 'Ast' && !isset($labelled[1]['size']),
    'a size that is no size is dropped, the words stay');
check(!isset($labelled[2]['text']) && !isset($labelled[2]['size']),
    'an arrow without words keeps no size');
foreach ([
    'an arrow with one point' => ['type' => 'arrow', 'points' => [[10, 10]]],
    'an arrow without length' => ['type' => 'arrow', 'points' => [[10, 10], [10, 10]]],
] as $what => $wrong) {
    try {
        photo_measures_write([$wrong]);
        check(false, "$what is refused");
    } catch (InvalidArgumentException) {
        check(true, "$what is refused");
    }
}

echo "\nThe mark of a measure (07.10.2026)\n";
$marked = photo_measures_write([
    ['type' => 'rect', 'points' => [[10, 10], [40, 50], [10, 10], [2, 16]], 'label' => 'Pleurozystiden', 'mark' => '  Chrysozystide '],
    ['type' => 'rect', 'points' => [[10, 10], [40, 50], [10, 10], [2, 16]], 'mark' => '   '],
    ['type' => 'line', 'points' => [[0, 0], [30, 40]], 'mark' => 'Chrysozystide'],
    ['type' => 'rect', 'points' => [[10, 10], [40, 50], [10, 10], [2, 16]], 'mark' => str_repeat('x', 50)],
]);
check(($marked[0]['mark'] ?? null) === 'Chrysozystide', 'a rectangle keeps its mark, trimmed');
check(!isset($marked[1]['mark']) && !isset($marked[2]['mark']), 'an empty mark is none, and only a rectangle carries one');
check(mb_strlen($marked[3]['mark'] ?? '') === 40, 'a mark is cut at forty characters, like a layer');

echo "\nThe part a line measures (07.10.2026)\n";
$parts = photo_measures_write([
    ['type' => 'line', 'points' => [[10, 10], [40, 50]], 'label' => 'Sporen', 'part' => ' Keimporus '],
    ['type' => 'line', 'points' => [[10, 10], [40, 50]], 'part' => ''],
    ['type' => 'rect', 'points' => [[10, 10], [40, 50], [10, 10], [2, 16]], 'part' => 'Keimporus'],
]);
check(($parts[0]['part'] ?? null) === 'Keimporus', 'a line keeps what it measures, trimmed');
check(!isset($parts[1]['part']) && !isset($parts[2]['part']), 'an empty part is none, and only a line carries one');

echo "\nThe box of a fixed size (06.10.2026)\n";
$written = photo_measures_write([
    ['type' => 'box', 'points' => [[40, 50]], 'um' => [1000, 1000], 'unit' => 'mm', 'width' => 100,
     'label' => 'Poren', 'angle' => 372.04],
    ['type' => 'box', 'points' => [[40, 50]], 'um' => ['10000', 2000.06], 'unit' => 'inch', 'angle' => 0],
    ['type' => 'box', 'points' => [[40, 50]], 'um' => [50, 50], 'unit' => 'um'],
]);
check($written[0]['um'] === [1000.0, 1000.0] && $written[0]['unit'] === 'mm'
    && $written[0]['points'] === [[40.0, 50.0]] && ($written[0]['label'] ?? null) === 'Poren',
    'a box is stored with its centre, its size in µm, its unit and its layer');
check(($written[0]['angle'] ?? null) === 12.0, 'a box keeps its turn, brought into one round');
check($written[1]['um'] === [10000.0, 2000.1] && !isset($written[1]['unit']) && !isset($written[1]['angle']),
    'sizes are read as numbers to a tenth of a µm, an unknown unit and an upright turn are dropped');
check(($written[2]['unit'] ?? null) === 'um', 'micrometres are a unit of their own');
// The points counted in it (18.11.2026): in the frame's own µm, those outside it or without numbers dropped.
$counted = photo_measures_write([
    ['type' => 'box', 'points' => [[40, 50]], 'um' => [1000, 1000], 'count' => [
        [100.04, -200], ['-499.9', 499.9], [600, 0], [0], ['x', 1], 'nought']],
    ['type' => 'box', 'points' => [[40, 50]], 'um' => [1000, 1000], 'count' => [[700, 700]]],
]);
check(($counted[0]['count'] ?? null) === [[100.0, -200.0], [-499.9, 499.9]],
    'a box keeps its counted points inside it, to a tenth of a µm, and drops the rest');
check(!isset($counted[1]['count']), 'a count with no point left is none');

echo "\nThe sector (18.11.2026)\n";
$sectors = photo_measures_write([
    ['type' => 'sector', 'points' => [[100, 100], [180, 100], [100, 150]], 'label' => 'Lamellen', 'width' => 400,
     'arc' => [10.004, 74.5], 'count' => [[120, 90], [130.04, 95], ['x', 1], [-5, 3]]],
    ['type' => 'sector', 'points' => [[100, 100], [180, 100], [100, 150]], 'count' => [[120, 90]]],
    ['type' => 'sector', 'points' => [[100, 100], [180, 100], [100, 150]], 'arc' => [0, 400]],
]);
check(($sectors[0]['arc'] ?? null) === [10.0, 74.5] && ($sectors[0]['label'] ?? null) === 'Lamellen',
    'a sector keeps its three taps, its slice in degrees and its layer');
check(($sectors[0]['count'] ?? null) === [[120.0, 90.0], [130.0, 95.0]], 'and its counted points, the wrong ones dropped');
check(!isset($sectors[1]['count']) && !isset($sectors[1]['arc']), 'without a slice nothing is counted');
$kinds = photo_measures_write([['type' => 'sector', 'points' => [[100, 100], [180, 100], [100, 150]], 'arc' => [0, 90],
    'count' => [[120, 90]], 'lamellulae' => [[110, 95], ['y', 2]]]]);
check(($kinds[0]['lamellulae'] ?? null) === [[110.0, 95.0]] && ($kinds[0]['count'] ?? null) === [[120.0, 90.0]],
    'the lamellulae are kept beside the lamellae, by the same rules');
$stems = photo_measures_write([
    ['type' => 'sector', 'points' => [[100, 100], [180, 100], [100, 150]], 'arc' => [0, 360], 'stem' => [140.04, 120]],
    ['type' => 'sector', 'points' => [[100, 100], [180, 100], [100, 150]], 'arc' => [0, 90], 'stem' => [-3, 120]],
    ['type' => 'sector', 'points' => [[100, 100], [180, 100], [100, 150]], 'arc' => [0, 90], 'stem' => 'mitte'],
]);
check(($stems[0]['stem'] ?? null) === [140.0, 120.0] && ($stems[0]['arc'] ?? null) === [0.0, 360.0],
    'a sector keeps the stem where the lamellae meet, and the whole cap as a slice of the full round');
check(!isset($stems[1]['stem']) && !isset($stems[2]['stem']), 'a stem off the picture or no point at all is none — the middle');
$rims = photo_measures_write([
    ['type' => 'sector', 'points' => [[100, 100], [180, 100], [100, 150]], 'arc' => [20, 90], 'stem' => [150, 100], 'rim' => [10.004, 300]],
    ['type' => 'sector', 'points' => [[100, 100], [180, 100], [100, 150]], 'arc' => [20, 90], 'rim' => [0, 500]],
]);
check(($rims[0]['rim'] ?? null) === [10.0, 300.0] && !isset($rims[1]['rim']),
    'an eccentric cap keeps the rim that carries lamellae, in degrees, at most the whole round');
check(!isset($sectors[2]['arc']), 'a slice of more than a full turn is none');
$threw = false;
try { photo_measures_write([['type' => 'sector', 'points' => [[1, 1], [5, 5]]]]); } catch (InvalidArgumentException) { $threw = true; }
check($threw, 'a sector needs exactly three taps');
foreach ([
    'a box without a size'        => ['type' => 'box', 'points' => [[10, 10]]],
    'a box with one side'         => ['type' => 'box', 'points' => [[10, 10]], 'um' => [1000]],
    'a box with a side of nought' => ['type' => 'box', 'points' => [[10, 10]], 'um' => [1000, 0.01]],
    'a box larger than a metre'   => ['type' => 'box', 'points' => [[10, 10]], 'um' => [1000001, 10]],
    'a box with words for a side' => ['type' => 'box', 'points' => [[10, 10]], 'um' => [1000, 'breit']],
    'a box with two points'       => ['type' => 'box', 'points' => [[10, 10], [20, 20]], 'um' => [1000, 1000]],
] as $what => $wrong) {
    try {
        photo_measures_write([$wrong]);
        check(false, "$what is refused");
    } catch (InvalidArgumentException) {
        check(true, "$what is refused");
    }
}

echo "\nThe development (0190)\n";
check(photo_develop_clean(null) === null && photo_develop_clean(['exposure' => 0, 'radials' => []]) === null,
    'nothing set is „nicht bearbeitet"');
$dev = photo_develop_clean(['exposure' => 250, 'contrast' => '-12.5', 'sharpen' => -40, 'clarity' => 'viel', 'colour' => 9,
    'radials' => [['x' => .4, 'y' => .6, 'rx' => .2, 'ry' => .1, 'angle' => 400, 'feather' => 70, 'invert' => 1,
                   'exposure' => -35, 'bogus' => 3]]]);
check($dev['exposure'] === 100.0 && $dev['contrast'] === -12.5 && !isset($dev['sharpen']) && !isset($dev['clarity']) && !isset($dev['colour']),
    'a slider into -100 … 100, sharpening never below 0, words and unknown keys dropped');
$r = $dev['radials'][0] ?? [];
check($r['angle'] === 180.0 && $r['invert'] === true && $r['exposure'] === -35.0 && !isset($r['bogus']) && $r['feather'] === 70.0,
    'an ellipse keeps its place, turn, edge, inversion and its own sliders');
check((photo_develop_clean(['radials' => [['x' => .123456, 'rx' => .040512, 'ry' => .02349]]])['radials'][0] ?? null)
        ['rx'] === 0.0405,
    'an ellipse keeps place and size to four places — a hundredth of the width is a quarter of a small one');
check(count(photo_develop_clean(['radials' => array_fill(0, 20, ['exposure' => 10])])['radials']) === 12,
    'no more than twelve ellipses');
check(photo_develop_read(json_encode($dev)) === $dev, 'what is stored reads back the same');
// The gradients (07.11.2026): two points finer than an ellipse's, sliders of their own, one budget with the ellipses.
$lin = photo_develop_clean(['linears' => [['x1' => .123456, 'y1' => -9, 'x2' => '0.5', 'y2' => .7, 'invert' => 1, 'exposure' => -90, 'width' => 4]]]);
check(($lin['linears'] ?? null) === [['x1' => 0.1235, 'y1' => -2.0, 'x2' => 0.5, 'y2' => 0.7, 'invert' => true, 'exposure' => -90.0]],
    'a gradient keeps its two points to four places, inside reach, its side and its own sliders — nothing else');
$both = photo_develop_clean(['radials' => array_fill(0, 10, ['exposure' => 10]), 'linears' => array_fill(0, 5, ['exposure' => 10])]);
check(count($both['radials']) + count($both['linears']) === 12, 'ellipses and gradients together no more than twelve');
check((photo_develop_clean(['radials' => [['rx' => 9, 'ry' => 4.5]]])['radials'][0]['rx'] ?? null) == 5,
    'an ellipse may reach five widths across — far past the picture, to darken its edges alone');
$geo = photo_develop_clean(['geo' => ['turn' => -90, 'angle' => 60, 'crop' => ['x' => .5, 'y' => .4, 'w' => .8, 'h' => 5]]])['geo'] ?? null;
check($geo === ['turn' => 270, 'angle' => 45.0, 'crop' => ['x' => 0.5, 'y' => 0.4, 'w' => 0.8, 'h' => 3.0]],
    'turned a quarter back is 270, the straightening at most 45°, a crop\'s side at most three widths');
check(photo_develop_clean(['geo' => ['turn' => 45, 'angle' => 0]]) === null && photo_develop_clean(['geo' => ['turn' => 0, 'angle' => 0, 'crop' => ['x' => 'a']]]) === null,
    'a turn that is no quarter, and a crop with words in it, count as nothing');
check((photo_develop_clean(['geo' => ['flip' => 'hv']])['geo'] ?? null) === ['turn' => 0, 'angle' => 0.0, 'flip' => 'hv']
    && (photo_develop_clean(['geo' => ['turn' => 90, 'flip' => 'h']])['geo']['flip'] ?? null) === 'h'
    && photo_develop_clean(['geo' => ['flip' => 'x']]) === null,
    'a mirror alone is kept, beside a turn too; an axis that is none counts as nothing');
$mix = photo_develop_clean(['mix' => ['s' => ['yellow' => -60, 'orange' => '-25.5', 'teal' => 40, 'red' => 0],
    'l' => ['yellow' => 300], 'h' => ['blue' => 0], 'x' => ['red' => 10]]])['mix'] ?? null;
check($mix === ['s' => ['orange' => -25.5, 'yellow' => -60.0], 'l' => ['yellow' => 100.0]],
    'the colour mixer keeps its eight colours in their order and ranges, drops zeros, unknown colours and kinds');
check(photo_develop_clean(['mix' => ['h' => ['red' => 0]]]) === null && photo_develop_clean(['mix' => 'gelb']) === null,
    'a colour mixer with nothing set counts as nothing');
$grade = photo_develop_clean(['grade' => ['shadows' => ['h' => 405, 's' => 30, 'l' => 0], 'midtones' => ['s' => 0],
    'highlights' => ['h' => -150, 's' => 250, 'l' => '-20'], 'balance' => 40, 'global' => ['s' => 5]]])['grade'] ?? null;
check($grade === ['highlights' => ['h' => 210, 's' => 100.0, 'l' => -20.0], 'shadows' => ['h' => 45, 's' => 30.0], 'balance' => 40.0],
    'the grading keeps its three ranges, turns the hue into 0 … 359, caps the strength, drops zeros and unknown ranges');
check(photo_develop_clean(['grade' => ['balance' => 30]]) === null,
    'a balance without a range counts as nothing');
$five = array_fill(0, 5, [1.1, .9]);
$cast = photo_develop_clean(['cast' => ['n' => $five, 'base' => [[1, 1]], 'picks' => array_merge(
    [['px' => 1.4, 'py' => .25, 'lum' => .6, 'r' => 9, 'b' => .8], ['lum' => 0, 'r' => 1, 'b' => 1], ['px' => .2, 'r' => 1]],
    array_fill(0, 12, ['px' => .5, 'py' => .5, 'lum' => .1, 'r' => 1, 'b' => 1]))]])['cast'] ?? null;
check(($cast['picks'][0] ?? null) === ['px' => 1.0, 'py' => 0.25, 'lum' => 0.6, 'r' => 4.0, 'b' => 0.8] && count($cast['picks']) === 8
    && !isset($cast['base']),
    'the pipette keeps up to eight points within their ranges, drops points without brightness or colour, and a base that is not five pairs');
check((photo_develop_clean(['cast' => ['n' => $five, 'base' => $five]])['cast']['base'] ?? null) === array_fill(0, 5, [1.1, 0.9])
    && photo_develop_clean(['cast' => ['picks' => [['lum' => .5, 'r' => 1, 'b' => 1]]]]) === null,
    'a base of five pairs is kept beside n, and points without n are no cast');
$curve = photo_develop_clean(['curve' => ['rgb' => [[255, 240], [0, 10], [128.4, 140], [128, 150]], 'r' => [[0, 0], [255, 255]],
    'g' => [[30, 40]], 'b' => array_map(static fn($i) => [$i * 10, $i * 10 + 5], range(0, 25)), 'x' => [[0, 5], [255, 250]]]])['curve'] ?? null;
check(($curve['rgb'] ?? null) === [[0, 10], [128, 150], [255, 240]] && !isset($curve['r']) && !isset($curve['g']) && !isset($curve['x'])
    && count($curve['b'] ?? []) === 16,
    'the tone curve sorts its points, keeps one per x, drops the bare diagonal, a single point and unknown channels, caps at sixteen');
$ai = photo_develop_clean(['spots' => [
    ['mode' => 'ai', 'path' => [[.5, .5]], 'radius' => .02, 'feather' => 50, 'wide' => true, 'pieces' => [['id' => 'abcdefgh12', 'box' => [.4, .4, .2, .2]]]],
    ['mode' => 'ai', 'path' => [[.3, .3]], 'radius' => .02, 'pieces' => [['id' => 'abcdefgh13', 'box' => [.2, .2, .2, .2]]]],
    ['mode' => 'heal', 'path' => [[.6, .6]], 'radius' => .02, 'dx' => .1, 'wide' => true, 'pieces' => [['id' => 'abcdefgh14', 'box' => [.5, .5, .2, .2]]]],
]])['spots'] ?? [];
check(($ai[0]['wide'] ?? null) === true && !isset($ai[1]['wide']) && ($ai[2]['wide'] ?? null) === true,
    'an AI place keeps wide (its fill reaches the brush\'s outer circle), an older one stays without, a heal keeps it beside its kept fills');
check((photo_develop_clean(['wb' => ['r' => 1.2, 'g' => 1, 'b' => .8]])['wb'] ?? null) == ['r' => 1.2, 'g' => 1.0, 'b' => 0.8]
    && photo_develop_clean(['wb' => ['r' => 1, 'g' => 1, 'b' => 1]]) === null
    && (photo_develop_clean(['wb' => ['r' => 9, 'g' => 'x', 'b' => .1]])['wb'] ?? null) == ['r' => 4.0, 'g' => 1.0, 'b' => 0.25],
    'a white balance keeps its three gains within 0.25 to 4; three ones are none');
$castIn = ['n' => [[1.08, .94], [1.085, 1.097], ['1.14', 1.02], [9, .1], [1.14, 1.05]], 'amount' => 140, 'shadows' => 'x', 'highlights' => 30.4];
$cast = photo_develop_clean(['cast' => $castIn])['cast'] ?? null;
check($cast === ['n' => [[1.08, .94], [1.085, 1.097], [1.14, 1.02], [4.0, .25], [1.14, 1.05]], 'amount' => 100, 'shadows' => 100, 'highlights' => 30]
    && photo_develop_clean(['cast' => ['n' => [[1, 1], [1, 1]]]]) === null
    && photo_develop_clean(['cast' => ['n' => [[1, 1], [1, 1], [1, 'a'], [1, 1], [1, 1]]]]) === null,
    'a colour cast keeps five pairs in range and its sliders 0 … 100, or nothing at all');
$spots = photo_develop_clean(['spots' => [
    ['mode' => 'clone', 'path' => [[.123456, .5], [.2, 'x'], [2.5, .6]], 'radius' => 1, 'feather' => 140, 'opacity' => 30, 'dx' => .05, 'dy' => -.02, 'off' => 1],
    ['mode' => 'other', 'path' => [[.3, .3]]],
    ['path' => []],
]])['spots'] ?? [];
check(count($spots) === 2
    && $spots[0] === ['mode' => 'clone', 'path' => [[0.1235, 0.5], [2.0, 0.6]], 'radius' => 0.15, 'feather' => 100, 'opacity' => 30,
                      'dx' => 0.05, 'dy' => -0.02, 'off' => true]
    && $spots[1]['mode'] === 'heal' && $spots[1]['feather'] === 50 && $spots[1]['opacity'] === 100 && $spots[1]['radius'] === 0.01,
    'a spot keeps its path to four places, its radius at most 0.15; an unknown mode heals; an empty path is none');
$many = photo_develop_clean(['spots' => array_fill(0, 300, ['path' => array_fill(0, 90, [.5, .5]), 'dx' => .1])])['spots'] ?? [];
$dabs = photo_develop_clean(['spots' => array_fill(0, 1500, ['path' => [[.1234, .5678]], 'dx' => .1])])['spots'] ?? [];
$worst = strlen(json_encode(photo_develop_clean(['spots' => array_fill(0, 2000, ['path' => array_fill(0, 30, [.1234, .5678]),
    'radius' => .01234, 'dx' => -.1234, 'dy' => .5678])])));
check(array_sum(array_map(static fn($sp) => count($sp['path']), $many)) === PHOTO_DEVELOP_PATH_POINTS && count($dabs) === PHOTO_DEVELOP_SPOTS
    && $worst > 65535 && $worst < 2 * 1024 * 1024,
    'the spots stop at a thousand places and twenty thousand points (0193); the worst case outgrows the old TEXT, stays far within MEDIUMTEXT');
/* „Entfernen" (11.11.2026): an AI spot keeps its pieces and is dropped without a
   valid one — it would remove nothing. Since „Ausbessern" is one tool (14.11.2026)
   it also keeps the source it had as „Angleichen" or „Kopieren", to come back to. */
$ai = photo_develop_clean(['spots' => [
    ['mode' => 'ai', 'path' => [[.1, .2], [.3, .4]], 'radius' => .02, 'feather' => 20, 'dx' => .5,
     'pieces' => [['id' => 'abcd1234ef', 'box' => [.05, .1, .3, '.45'], 'a' => 0, 'b' => .5],
                  ['id' => '../evil', 'box' => [0, 0, 1, 1]], ['id' => 'abcd1234eg', 'box' => [0, 0, 0, 1]],
                  ['id' => 'abcd1234eh', 'box' => [-1, .2, 2, .3], 'a' => .5]]],
    ['mode' => 'ai', 'path' => [[.5, .5]], 'pieces' => []],
]])['spots'] ?? [];
check(count($ai) === 1
    && $ai[0] === ['mode' => 'ai', 'path' => [[0.1, 0.2], [0.3, 0.4]], 'radius' => 0.02, 'feather' => 20, 'opacity' => 100,
                   'pieces' => [['id' => 'abcd1234ef', 'box' => [0.05, 0.1, 0.3, 0.45], 'a' => 0.0, 'b' => 0.5],
                                ['id' => 'abcd1234eh', 'box' => [0.0, 0.2, 1.0, 0.3], 'a' => 0.5, 'b' => 1.0]],
                   'dx' => 0.5, 'dy' => 0.0],
    'an AI spot keeps valid pieces (a patch name, a box inside the picture) and the source it had before; without pieces it is gone');
$round = photo_develop_clean(['spots' => [
    ['mode' => 'ai', 'path' => [[.1, .1], [.2, .1], [.15, .2], [.1, .1]], 'fill' => 1, 'pieces' => [['id' => 'abcd1234ef', 'box' => [0, 0, .5, .5]]]],
    ['mode' => 'heal', 'path' => [[.5, .5]], 'fill' => 1],
    ['mode' => 'clone', 'path' => [[.5, .5]], 'fill' => 1],
]])['spots'] ?? [];
check(($round[0]['fill'] ?? null) === true && ($round[1]['fill'] ?? null) === true && ($round[2]['fill'] ?? null) === true,
    'a place drawn round keeps its fill — „Entfernen", „Reparieren" and „Kopieren" alike');
/* Two models, one place (14.11.2026): the strong model's fill shown, the quick
   one's kept as `other` — and a heal never carries either. */
$both = photo_develop_clean(['spots' => [
    ['mode' => 'ai', 'path' => [[.1, .1], [.2, .2]], 'strong' => 1,
     'pieces' => [['id' => 'strong0001aa', 'box' => [0, 0, .5, .5]]],
     'other'  => [['id' => 'quick00001aa', 'box' => [0, 0, .5, .5]], ['id' => 'no', 'box' => [0, 0, .5, .5]]]],
    ['mode' => 'ai', 'path' => [[.3, .3]], 'pieces' => [['id' => 'quick00002aa', 'box' => [0, 0, .5, .5]]], 'other' => 'nonsense'],
    ['mode' => 'heal', 'path' => [[.5, .5]], 'strong' => true, 'other' => [['id' => 'quick00003aa', 'box' => [0, 0, .5, .5]]]],
]])['spots'] ?? [];
check(($both[0]['strong'] ?? null) === true && array_column($both[0]['other'] ?? [], 'id') === ['quick00001aa']
    && array_column($both[0]['pieces'], 'id') === ['strong0001aa'],
    'an AI spot keeps the other model\'s pieces beside the shown ones, and says which model is shown');
check(!array_key_exists('other', $both[1]) && !array_key_exists('strong', $both[1]),
    'a spot filled once carries neither — nonsense in their place is dropped');
check(!array_key_exists('other', $both[2]) && !array_key_exists('strong', $both[2]), 'a heal without kept pieces carries neither');
/* One tool, three methods (14.11.2026): a place switched back to „Angleichen" keeps
   the fills it had as „Entfernen" — shown or not is `mode`'s to say. */
$keptFills = photo_develop_clean(['spots' => [
    ['mode' => 'heal', 'path' => [[.5, .5]], 'dx' => .1, 'strong' => true,
     'pieces' => [['id' => 'strong0009aa', 'box' => [0, 0, .5, .5]]], 'other' => [['id' => 'quick00009aa', 'box' => [0, 0, .5, .5]]]],
]])['spots'] ?? [];
check(($keptFills[0]['mode'] ?? '') === 'heal' && array_column($keptFills[0]['pieces'] ?? [], 'id') === ['strong0009aa']
    && array_column($keptFills[0]['other'] ?? [], 'id') === ['quick00009aa'] && ($keptFills[0]['strong'] ?? null) === true
    && $keptFills[0]['dx'] === 0.1,
    'a heal keeps the fills it had as „Entfernen" beside its source — kept, not shown');
$bothMany = photo_develop_clean(['spots' => array_fill(0, 300, ['mode' => 'ai', 'path' => [[.1, .1]],
    'pieces' => array_fill(0, 3, ['id' => str_repeat('a1', 8), 'box' => [0, 0, .5, .5]]),
    'other'  => array_fill(0, 3, ['id' => str_repeat('b2', 8), 'box' => [0, 0, .5, .5]])])]);
check(array_sum(array_map(static fn($sp) => count($sp['pieces']) + count($sp['other'] ?? []), $bothMany['spots'])) === PHOTO_DEVELOP_AI_PIECES,
    'shown and kept pieces share the one budget of a thousand');
$turned = photo_develop_clean(['spots' => [
    ['mode' => 'clone', 'path' => [[.5, .5]], 'dx' => .1, 'rot' => 33.33],
    ['mode' => 'heal', 'path' => [[.5, .5]], 'dx' => .1, 'rot' => 400],
    ['mode' => 'heal', 'path' => [[.5, .5]], 'dx' => .1, 'rot' => 0],
]])['spots'] ?? [];
check(($turned[0]['rot'] ?? null) === 33.3 && ($turned[1]['rot'] ?? null) === 180.0 && !isset($turned[2]['rot']),
    'a turned source keeps its angle to a tenth, within ±180; none where it is not turned');
$aiMany = photo_develop_clean(['spots' => array_fill(0, 300, ['mode' => 'ai', 'fill' => true, 'path' => array_fill(0, 20, [.1234, .5678]),
    'radius' => .01234, 'pieces' => array_fill(0, 5, ['id' => str_repeat('a1', 16), 'box' => [.12345, .23456, .34567, .45678], 'a' => .12345, 'b' => .23456])])]);
check(array_sum(array_map(static fn($sp) => count($sp['pieces']), $aiMany['spots'])) === PHOTO_DEVELOP_AI_PIECES
    && strlen(json_encode($aiMany)) < 2 * 1024 * 1024,
    'the AI pieces stop at a thousand in all (0193), and the worst case stays far within MEDIUMTEXT');
$br = photo_develop_clean(['brushes' => [
    ['strokes' => [['path' => [[.1, .2], [.3, .4]], 'radius' => .02, 'feather' => 30, 'flow' => 140, 'erase' => 1], ['path' => []]],
     'invert' => 1, 'exposure' => -40],
    [], [], [], [],
]])['brushes'] ?? [];
check(count($br) === 4
    && $br[0] === ['strokes' => [['path' => [[0.1, 0.2], [0.3, 0.4]], 'radius' => 0.02, 'feather' => 30, 'flow' => 100, 'erase' => true]],
                   'invert' => true, 'exposure' => -40.0]
    && $br[1] === ['strokes' => [], 'invert' => false],
    'a brush keeps its strokes (flow at most 100, an empty path dropped), its side and sliders; four at most');
$shared = photo_develop_clean(['spots' => [['path' => array_fill(0, PHOTO_DEVELOP_PATH_POINTS - 10, [.5, .5]), 'dx' => .1]],
    'brushes' => [['strokes' => [['path' => array_fill(0, 30, [.2, .2])]]]]]);
check(count($shared['brushes'][0]['strokes'][0]['path'] ?? []) === 10,
    'spots and brush strokes share ONE budget of points');
$offs = photo_develop_clean(['radials' => [['off' => true], []], 'linears' => [['off' => 1]]]);
check(($offs['radials'][0]['off'] ?? null) === true && !isset($offs['radials'][1]['off']) && ($offs['linears'][0]['off'] ?? null) === true,
    'a mask switched off by its eye keeps its off, one switched on carries none');
$json = json_decode(measure_sidecar_json(null, [], 'a.jpg', 10, 10, null, null, null, $dev), true);
check(($json['develop']['exposure'] ?? null) === 100 || ($json['develop']['exposure'] ?? null) === 100.0,
    'the sidecar carries the development');
check(isset($json['develop_help']['keys']['geo.crop'], $json['develop_help']['order'])
    && !isset(json_decode(measure_sidecar_json(null, [], 'a.jpg', 10, 10), true)['develop_help']),
    'beside a development the sidecar explains its keys — and only there');

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
