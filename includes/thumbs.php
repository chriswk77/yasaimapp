<?php
/**
 * The preview cache — every picture's small tile, kept on this server
 * (10.10.2026, after the first look at the Fundbrowser: „es
 * lädt halt ziemlich langsam … wie wird das erst, wenn da tausende Bilder
 * drauf sind? Aus verschiedenen Clouds. Sollten wir da einen lokalen
 * Thumbnail-Cache anlegen?" — and asked whether a small copy on the
 * server is all right, given that pictures otherwise live in one's own
 * cloud: „Ja, beides, bau den Cache mit Cronjob").
 *
 * WHAT IS KEPT: the 320 px preview of a cloud recording (map_cloud_files)
 * and of a voucher photo whose bytes went to a cloud (map_photos with a
 * cloud_fileid) — some twenty to forty kilobytes each. A voucher photo
 * still on this server needs none: its own preview lies beside it
 * (<name>_k.jpg). The large view and the original keep coming from the
 * cloud; a cache of those would be a copy of the picture, and that the
 * server does not hold.
 *
 * WHERE: data/users/<the picture's owner>/thumbs/<kind>-<id>.img, behind
 * the „Deny from all" of data/users, handed out only through photo.php
 * and cloud_thumb.php after the same rights check as ever. The owner's
 * folder, because it goes when the account goes.
 *
 * THE DATABASE IS THE TRUTH, the cloud is not asked whether a picture
 * still exists. Asking would cost the very round trip the cache exists to
 * save — what is slow is reaching the provider, not the bytes. So:
 *   - deleting a picture in the app deletes its preview (thumb_forget at
 *     photo_delete and the recordings' 'remove');
 *   - a row gone by any other road leaves an orphan, and thumbs_sweep()
 *     clears those on every cron run — one query, no provider;
 *   - a picture deleted in the cloud behind the app's back is noticed
 *     when somebody opens it large: that still goes to the cloud, and a
 *     failure there drops the preview too.
 *
 * A PREVIEW THAT CANNOT BE MADE — a raw file nobody draws, a cloud
 * disconnected — leaves a marker (<kind>-<id>.none), so the hourly job
 * does not knock at the provider for it every hour; after a week it tries
 * again. The tile meanwhile falls back to the live road.
 */

declare(strict_types=1);

require_once __DIR__ . '/user_store.php';
require_once __DIR__ . '/cloud.php';

/* PROVIDERS WHOSE PREVIEWS LOSE THE COLOUR PROFILE without converting the
   colours — measured for pCloud on 16.11.2026 with a picture in ROMM RGB:
   the tile showed the bare numbers (dull), the original in „Bearbeiten"
   the colours. Their previews get the original's profile back
   (thumb_profiled).

   DRIVE NEEDS NOTHING, measured 18.11.2026 with the same picture in a
   test Drive: its 320 preview comes CONVERTED to sRGB (252/155/110 where
   the bare numbers are 200/150/101), its 2048 one keeps the profile. So
   Drive must stay off this list — the profile put into its small preview
   would convert the colours a second time. OneDrive is not measured;
   until it is, its previews go out as they come. */
const THUMB_PROFILE_LOST = ['pcloud'];
/** How much of an original is read for its profile: it lies in the header, after EXIF (at most 64 KB) and XMP. */
const THUMB_HEAD_BYTES = 262_144;

/** The tile's edge — the one size the browser asks for. */
const THUMB_EDGE = 320;
/** What a provider's preview may weigh to be kept as it came. */
const THUMB_KEEP_BYTES = 300_000;
/** The most fetched for one preview — a provider without previews hands the picture itself. */
const THUMB_FETCH_BYTES = 25_000_000;
/** How long a preview that could not be made is left alone. */
const THUMB_RETRY_SECONDS = 7 * 86400;

/** The two kinds of picture, by the name the cache files carry. */
const THUMB_KINDS = ['cloud', 'photo'];

function thumb_directory(int $ownerId): string
{
    return user_directory($ownerId) . '/thumbs';
}

function thumb_path(int $ownerId, string $kind, int $id): string
{
    return thumb_directory($ownerId) . '/' . $kind . '-' . $id . '.img';
}

function thumb_marker(int $ownerId, string $kind, int $id): string
{
    return thumb_directory($ownerId) . '/' . $kind . '-' . $id . '.none';
}

/** The preview and its marker go — the picture is gone or no longer what it was. */
function thumb_forget(int $ownerId, string $kind, int $id): void
{
    @unlink(thumb_path($ownerId, $kind, $id));
    @unlink(thumb_marker($ownerId, $kind, $id));
    @unlink(thumb_icc_path($ownerId, $kind, $id));
}

/** Where the original's colour profile is kept once read — an empty file: it has none (thumb_icc). */
function thumb_icc_path(int $ownerId, string $kind, int $id): string
{
    return thumb_directory($ownerId) . '/' . $kind . '-' . $id . '.icc';
}

/**
 * Hand a kept preview to the browser. The same fence as a picture from
 * the cloud (includes/picture_fence.php): the type by the bytes, an id
 * points at the same picture for ever, private so no cache along the way
 * keeps it, nothing executable.
 */
function thumb_serve(string $path): void
{
    $type = picture_type((string) file_get_contents($path, false, null, 0, 16));
    picture_headers($type, (int) filesize($path), 'private, max-age=31536000, immutable');
    readfile($path);
}

/**
 * The kept preview of a picture, made now if it is not there yet.
 * Null when it cannot be made — the caller then takes the live road.
 *
 * @param array $row the picture's row: map_cloud_files (kind 'cloud') or
 *                   map_photos (kind 'photo'), with user_id
 */
function thumb_ensure(PDO $pdo, string $kind, array $row, ?callable $open = null): ?string
{
    $owner = (int) $row['user_id'];
    $id    = (int) $row['id'];
    $path  = thumb_path($owner, $kind, $id);
    if (is_file($path)) {
        return $path;
    }
    $marker = thumb_marker($owner, $kind, $id);
    if (is_file($marker) && filemtime($marker) > time() - THUMB_RETRY_SECONDS) {
        return null;
    }
    return thumb_make($pdo, $kind, $row, $open);
}

/**
 * Fetch a picture's preview from its cloud and keep it. Null, with a
 * marker left, when no cloud hands one out.
 *
 * $open opens a user's cloud — cloud_storage_for() unless a test hands in
 * the provider played in memory (tests/thumbs.php).
 */
function thumb_make(PDO $pdo, string $kind, array $row, ?callable $open = null): ?string
{
    $open ??= static fn(int $user, string $provider): ?array => cloud_storage_for($pdo, $user, $provider);
    $owner = (int) $row['user_id'];
    $id    = (int) $row['id'];
    $bytes = null;
    $from = '';
    foreach (thumb_sources($pdo, $kind, $row) as [$source, $provider, $fileid]) {
        $s = $open($source, $provider);
        if ($s === null) {
            continue;
        }
        try {
            // S3's previews hang off the app's prefix; nobody else needs it.
            $home = $s['provider'] === 's3' ? (string) cloud_home_resolve($pdo, $source, $s) : null;
            $bytes = thumb_fetch(storage_file_fetch($s, $fileid, THUMB_EDGE, $home));
        } catch (RuntimeException $e) {
            error_log('thumb ' . $kind . ' ' . $id . ': ' . $e->getMessage());
            $bytes = null;
        }
        if ($bytes !== null) {
            $from = (string) $s['provider'];
            break;
        }
    }
    $small = $bytes === null ? null : thumb_shrink($bytes);
    // A provider's preview without the original's colour profile gets it back.
    if ($small !== null) {
        $small = thumb_profiled($pdo, $kind, $row, $small, $from, $open);
    }

    $dir = thumb_directory($owner);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        error_log('thumb: cannot create ' . $dir);
        return null;
    }
    if ($small === null) {
        @touch(thumb_marker($owner, $kind, $id));
        return null;
    }
    // Written aside and moved in: two requests making the same preview at
    // once each finish a whole file, and a reader never sees half of one.
    $path = thumb_path($owner, $kind, $id);
    $tmp  = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $small) === false || !@rename($tmp, $path)) {
        @unlink($tmp);
        return null;
    }
    @unlink(thumb_marker($owner, $kind, $id));
    return $path;
}

/**
 * Where a picture's bytes lie: its owner's cloud first, then any finished
 * co-owner copy (map_cloud_copies) — so a preview can still be made when
 * the photographer disconnected his cloud while the circle keeps copies.
 *
 * @return list<array{int, string, string}> [user id, provider, file id]
 */
function thumb_sources(PDO $pdo, string $kind, array $row): array
{
    $out = [];
    if ($kind === 'cloud') {
        $out[] = [(int) $row['user_id'], (string) ($row['provider'] ?? '') ?: 'pcloud', (string) $row['fileid']];
    } elseif ((string) ($row['cloud_fileid'] ?? '') !== '') {
        $out[] = [(int) $row['user_id'], (string) ($row['cloud_provider'] ?? '') ?: 'pcloud', (string) $row['cloud_fileid']];
    }
    if (table_exists($pdo, 'map_cloud_copies')) {
        $q = $pdo->prepare('SELECT user_id, provider, fileid FROM map_cloud_copies
                             WHERE origin_kind = ? AND origin_id = ? AND status = "copied" AND fileid IS NOT NULL');
        $q->execute([$kind, (int) $row['id']]);
        foreach ($q as $c) {
            $out[] = [(int) $c['user_id'], (string) ($c['provider'] ?? '') ?: 'pcloud', (string) $c['fileid']];
        }
    }
    return $out;
}

/**
 * The bytes of the first candidate that answers with a picture — read with
 * a ceiling, the way storage_file_bytes() reads (one byte more than
 * allowed says enough). No redirect where the candidate forbids it (S3).
 */
function thumb_fetch(array $candidates): ?string
{
    foreach ($candidates as $at) {
        $bytes = @file_get_contents((string) $at['url'], false, stream_context_create(['http' => [
            'timeout'         => PCLOUD_TIMEOUT,
            'header'          => (string) ($at['headers'] ?? ''),
            'follow_location' => ($at['follow'] ?? true) ? 1 : 0,
            'ignore_errors'   => true,
        ]]), 0, THUMB_FETCH_BYTES + 1);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];   // the last one counts, after redirects
            }
        }
        if ($bytes === false || $bytes === '' || $status >= 400 || strlen($bytes) > THUMB_FETCH_BYTES) {
            continue;
        }
        return $bytes;
    }
    return null;
}

/**
 * A small raster picture out of whatever the cloud handed over.
 *
 * KEPT AS IT CAME when it is already a preview — pCloud's, Drive's and
 * OneDrive's are; drawing it once more would only lose quality. SHRUNK
 * HERE when the provider handed the picture itself (S3 without its
 * preview folder, Drive in the first minutes after an upload): then GD
 * draws it into the 320 box, upright by its EXIF orientation, as JPEG.
 * Null for what neither works on — a raw file, HEIC, a picture beyond
 * the pixel ceiling.
 */
function thumb_shrink(string $bytes): ?string
{
    $info = @getimagesizefromstring($bytes);
    if (!is_array($info) || $info[0] < 1 || $info[1] < 1) {
        return null;
    }
    [$w, $h] = $info;
    $mime = (string) $info['mime'];
    $drawable = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    if ($drawable && strlen($bytes) <= THUMB_KEEP_BYTES && max($w, $h) <= 2 * THUMB_EDGE) {
        return $bytes;
    }
    if (!$drawable || !function_exists('imagecreatefromstring') || $w * $h > PICTURE_DECODE_PIXELS) {
        return null;
    }
    $image = @imagecreatefromstring($bytes);
    if ($image === false) {
        return null;
    }
    $image = thumb_upright($image, $bytes, $mime);
    $w = imagesx($image);
    $h = imagesy($image);
    $scale = min(1.0, THUMB_EDGE / max($w, $h));
    $tw = max(1, (int) round($w * $scale));
    $th = max(1, (int) round($h * $scale));
    $small = imagecreatetruecolor($tw, $th);
    // A transparent PNG on white rather than on black — JPEG has no alpha.
    imagefill($small, 0, 0, imagecolorallocate($small, 255, 255, 255));
    imagecopyresampled($small, $image, 0, 0, 0, 0, $tw, $th, $w, $h);
    imagedestroy($image);
    ob_start();
    imagejpeg($small, null, 82);
    imagedestroy($small);
    $out = (string) ob_get_clean();
    if ($out === '') {
        return null;
    }
    // The original's colour profile goes with it — GD leaves the numbers as they were and drops the profile (below).
    $icc = picture_icc($bytes, $mime);
    return $icc === null ? $out : jpeg_with_icc($out, $icc);
}

/* --- THE COLOUR PROFILE GOES WITH THE PICTURE (16.11.2026) ----
   „Mir kommt das große Bild anders vor als das kleine Thumbnail daneben in
   der Sidebar. Und genauso sieht die Farbe in der Tafel dann so aus wie
   das kleine Bild in der Sidebar, aber nicht so wie das im
   Bearbeitungsfenster." Many cameras, phones and stacking programs write
   their pictures in a wider colour space than the screen's sRGB — Adobe
   RGB, Display P3 — and say so in an embedded ICC profile. The browser
   reads it: the large view, drawn from the original, shows the colours
   as they were taken. GD does not: it hands the numbers through unchanged
   and writes a JPEG WITHOUT the profile, and a picture without one is read
   as sRGB. Measured on one colour, 200/150/100 in Adobe RGB: the original
   showed as 217/151/98, the preview as 200/150/101 — duller and colder,
   and in P3 the same way. So the profile is copied from the original into
   the preview. That is exact, not an approximation: the numbers GD wrote
   ARE the original's numbers, shrunk, and the profile says again how to
   read them. No conversion to sRGB is attempted — GD has none, and it
   would only lose what the profile keeps.

   Read from JPEG (APP2 „ICC_PROFILE", possibly in several parts), PNG
   (iCCP, compressed) and WebP (the ICCP chunk); written as JPEG APP2 parts
   right after the start of the file. */

/** The ICC profile a picture carries — its bytes, or null where it has none or it cannot be read. */
function picture_icc(string $bytes, string $mime): ?string
{
    if ($mime === 'image/jpeg') {
        $parts = [];
        $at = 2;
        $len = strlen($bytes);
        while ($at + 4 <= $len && $bytes[$at] === "\xFF") {
            $marker = ord($bytes[$at + 1]);
            // Fill bytes before a marker, and the markers without a length.
            if ($marker === 0xFF) { $at++; continue; }
            if ($marker === 0xD8 || ($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) { $at += 2; continue; }
            // The image data begins: no header follows.
            if ($marker === 0xDA || $marker === 0xD9) break;
            $size = unpack('n', substr($bytes, $at + 2, 2))[1];
            if ($size < 2 || $at + 2 + $size > $len) break;
            if ($marker === 0xE2 && substr($bytes, $at + 4, 12) === "ICC_PROFILE\0" && $size >= 16) {
                $parts[ord($bytes[$at + 16])] = substr($bytes, $at + 18, $size - 16);
            }
            $at += 2 + $size;
        }
        if ($parts === []) return null;
        ksort($parts);
        $icc = implode('', $parts);
    } elseif ($mime === 'image/png') {
        $icc = null;
        $at = 8;
        $len = strlen($bytes);
        while ($at + 12 <= $len) {
            $size = unpack('N', substr($bytes, $at, 4))[1];
            $type = substr($bytes, $at + 4, 4);
            if ($type === 'IDAT' || $type === 'IEND' || $at + 12 + $size > $len) break;
            if ($type === 'iCCP') {
                $data = substr($bytes, $at + 8, $size);
                $nul = strpos($data, "\0");
                // Name, its zero, the method (0 = zlib), the compressed profile.
                $raw = $nul === false ? false : @gzuncompress(substr($data, $nul + 2));
                $icc = $raw === false ? null : $raw;
                break;
            }
            $at += 12 + $size;
        }
    } elseif ($mime === 'image/webp') {
        $icc = null;
        $at = 12;
        $len = strlen($bytes);
        while ($at + 8 <= $len) {
            $type = substr($bytes, $at, 4);
            $size = unpack('V', substr($bytes, $at + 4, 4))[1];
            if ($at + 8 + $size > $len) break;
            if ($type === 'ICCP') { $icc = substr($bytes, $at + 8, $size); break; }
            $at += 8 + $size + ($size & 1);
        }
    } else {
        return null;
    }
    // A profile begins with its own length and says „acsp" at byte 36; anything else is not one.
    if ($icc === null || strlen($icc) < 132 || substr($icc, 36, 4) !== 'acsp') return null;
    return $icc;
}

/** A JPEG with an ICC profile written in: APP2 parts of at most 65,519 bytes, right after the start of the file. */
function jpeg_with_icc(string $jpeg, string $icc): string
{
    if (substr($jpeg, 0, 2) !== "\xFF\xD8") return $jpeg;
    $chunks = str_split($icc, 65519);
    $count = count($chunks);
    if ($count > 255) return $jpeg;
    $segments = '';
    foreach ($chunks as $i => $chunk) {
        $payload = "ICC_PROFILE\0" . chr($i + 1) . chr($count) . $chunk;
        $segments .= "\xFF\xE2" . pack('n', strlen($payload) + 2) . $payload;
    }
    // After JFIF's APP0 where there is one: some readers want that first.
    $at = 2;
    if (substr($jpeg, 2, 2) === "\xFF\xE0") $at = 4 + unpack('n', substr($jpeg, 4, 2))[1];
    return substr($jpeg, 0, $at) . $segments . substr($jpeg, $at);
}

/** Turn a camera's picture the way it was held — browsers read EXIF, GD does not. */
function thumb_upright(GdImage $image, string $bytes, string $mime): GdImage
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $image;
    }
    $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
    $turn = (int) ($exif['Orientation'] ?? 1);
    $rotated = match ($turn) {
        3, 4 => imagerotate($image, 180, 0),
        5, 6 => imagerotate($image, -90, 0),
        7, 8 => imagerotate($image, 90, 0),
        default => $image,
    };
    if ($rotated === false) {
        return $image;
    }
    if (in_array($turn, [2, 4, 5, 7], true)) {
        imageflip($rotated, IMG_FLIP_HORIZONTAL);
    }
    return $rotated;
}

// ---------------------------------------------------------------------------
// The run — the cron job's and the administration's
// ---------------------------------------------------------------------------

/**
 * Every picture that wants a kept preview, newest first — the finds one
 * browses first are the recent ones.
 *
 * $onlyUser narrows it to one account's pictures — for the test, which
 * must not mark the pictures of the real accounts beside it.
 *
 * @return list<array{0: string, 1: array}> [kind, row]
 */
function thumbs_candidates(PDO $pdo, ?int $onlyUser = null): array
{
    $out = [];
    $mine = $onlyUser === null ? '' : ' AND user_id = ' . $onlyUser;
    if (table_exists($pdo, 'map_cloud_files')) {
        foreach ($pdo->query('SELECT id, user_id, provider, fileid FROM map_cloud_files WHERE 1' . $mine . ' ORDER BY id DESC') as $r) {
            $out[] = ['cloud', $r];
        }
    }
    if (column_exists($pdo, 'map_photos', 'cloud_fileid')) {
        foreach ($pdo->query('SELECT id, user_id, cloud_provider, cloud_fileid FROM map_photos
                               WHERE cloud_fileid IS NOT NULL AND cloud_fileid <> ""' . $mine . ' ORDER BY id DESC') as $r) {
            $out[] = ['photo', $r];
        }
    }
    return $out;
}

/**
 * How things stand: pictures with a kept preview, without one yet, and
 * those whose preview could not be made this week.
 *
 * @return array{kept: int, open: int, failed: int}
 */
function thumbs_state(PDO $pdo): array
{
    $state = ['kept' => 0, 'open' => 0, 'failed' => 0];
    foreach (thumbs_candidates($pdo) as [$kind, $row]) {
        $owner = (int) $row['user_id'];
        $id    = (int) $row['id'];
        if (is_file(thumb_path($owner, $kind, $id))) {
            $state['kept']++;
        } elseif (is_file($m = thumb_marker($owner, $kind, $id)) && filemtime($m) > time() - THUMB_RETRY_SECONDS) {
            $state['failed']++;
        } else {
            $state['open']++;
        }
    }
    return $state;
}

function thumbs_pending(PDO $pdo): int
{
    return thumbs_state($pdo)['open'];
}

/**
 * Make the missing previews until the deadline. A short pause between the
 * clouds' answers — one account's thousand pictures are a thousand
 * requests at one provider.
 *
 * @return array{made: int, failed: int, swept: int}
 */
function thumbs_run(PDO $pdo, float $deadline, ?callable $open = null, ?int $onlyUser = null): array
{
    $done = ['made' => 0, 'failed' => 0, 'swept' => thumbs_sweep($pdo), 'profiled' => 0];
    foreach (thumbs_candidates($pdo, $onlyUser) as [$kind, $row]) {
        if (microtime(true) >= $deadline) {
            break;
        }
        $owner = (int) $row['user_id'];
        $id    = (int) $row['id'];
        if (is_file(thumb_path($owner, $kind, $id))) {
            continue;
        }
        $marker = thumb_marker($owner, $kind, $id);
        if (is_file($marker) && filemtime($marker) > time() - THUMB_RETRY_SECONDS) {
            continue;
        }
        if (thumb_make($pdo, $kind, $row, $open) !== null) {
            $done['made']++;
        } else {
            $done['failed']++;
        }
        usleep(150_000);
    }
    /* THE PREVIEWS KEPT BEFORE 16.11.2026 lack the profile: each gets it
       once, behind the missing ones — one short read of its original's
       head, and the kept file is written again. The profile's file says
       it was done, with a profile or without. */
    foreach (thumbs_candidates($pdo, $onlyUser) as [$kind, $row]) {
        if (microtime(true) >= $deadline) {
            break;
        }
        $owner = (int) $row['user_id'];
        $id    = (int) $row['id'];
        $path  = thumb_path($owner, $kind, $id);
        $provider = (string) ($kind === 'cloud' ? ($row['provider'] ?? '') : ($row['cloud_provider'] ?? '')) ?: 'pcloud';
        if (!in_array($provider, THUMB_PROFILE_LOST, true) || !is_file($path) || is_file(thumb_icc_path($owner, $kind, $id))) {
            continue;
        }
        $kept = (string) @file_get_contents($path);
        $better = thumb_profiled($pdo, $kind, $row, $kept, $provider, $open);
        if ($better !== $kept) {
            $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, $better) !== false && @rename($tmp, $path)) {
                $done['profiled']++;
            } else {
                @unlink($tmp);
            }
        }
        usleep(150_000);
    }
    return $done;
}

/**
 * The colour profile of a picture's ORIGINAL — read once from the first
 * bytes of the file in its cloud (THUMB_HEAD_BYTES, asked for as a range)
 * and kept beside the preview (thumb_icc_path). Null where it has none,
 * and where no cloud answered — then it is asked again next time.
 */
function thumb_icc(PDO $pdo, string $kind, array $row, ?callable $open = null): ?string
{
    $owner = (int) $row['user_id'];
    $id    = (int) $row['id'];
    $kept  = thumb_icc_path($owner, $kind, $id);
    if (is_file($kept)) {
        $icc = (string) @file_get_contents($kept);
        return $icc === '' ? null : $icc;
    }
    $open ??= static fn(int $user, string $provider): ?array => cloud_storage_for($pdo, $user, $provider);
    foreach (thumb_sources($pdo, $kind, $row) as [$source, $provider, $fileid]) {
        $s = $open($source, $provider);
        if ($s === null) {
            continue;
        }
        try {
            $home = $s['provider'] === 's3' ? (string) cloud_home_resolve($pdo, $source, $s) : null;
            $head = thumb_head(storage_file_fetch($s, $fileid, null, $home));
        } catch (RuntimeException $e) {
            error_log('thumb_icc ' . $kind . ' ' . $id . ': ' . $e->getMessage());
            $head = null;
        }
        if ($head === null) {
            continue;
        }
        $type = picture_type(substr($head, 0, 16));
        $icc = $type === null ? null : picture_icc($head, $type);
        $dir = thumb_directory($owner);
        if (is_dir($dir) || @mkdir($dir, 0775, true)) {
            @file_put_contents($kept, (string) $icc);
        }
        return $icc;
    }
    return null;
}

/** The first THUMB_HEAD_BYTES of the first candidate that answers — a range where the provider honours one, cut off where not. */
function thumb_head(array $candidates): ?string
{
    foreach ($candidates as $at) {
        $headers = trim((string) ($at['headers'] ?? ''));
        $headers = ($headers === '' ? '' : $headers . "\r\n") . 'Range: bytes=0-' . (THUMB_HEAD_BYTES - 1);
        $bytes = @file_get_contents((string) $at['url'], false, stream_context_create(['http' => [
            'timeout'         => PCLOUD_TIMEOUT,
            'header'          => $headers,
            'follow_location' => ($at['follow'] ?? true) ? 1 : 0,
            'ignore_errors'   => true,
        ]]), 0, THUMB_HEAD_BYTES);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($bytes === false || $bytes === '' || $status >= 400) {
            continue;
        }
        return $bytes;
    }
    return null;
}

/**
 * A provider's preview with the original's colour profile in it, where
 * the provider lost it (THUMB_PROFILE_LOST) — the numbers in its preview
 * are still the original's, so the profile reads them right again.
 * Anything else comes back as it was: another provider, a preview that
 * carries a profile of its own, one that is no JPEG, an original without.
 */
function thumb_profiled(PDO $pdo, string $kind, array $row, string $bytes, string $provider, ?callable $open = null): string
{
    if (!in_array($provider, THUMB_PROFILE_LOST, true) || picture_type(substr($bytes, 0, 16)) !== 'image/jpeg'
        || picture_icc($bytes, 'image/jpeg') !== null) {
        return $bytes;
    }
    $icc = thumb_icc($pdo, $kind, $row, $open);
    return $icc === null ? $bytes : jpeg_with_icc($bytes, $icc);
}

/**
 * Previews whose picture is gone — a row deleted by a road that did not
 * call thumb_forget (a find removed for good, an account's cleanup, a
 * split). Only the database is asked.
 */
function thumbs_sweep(PDO $pdo): int
{
    $alive = [];
    foreach (thumbs_candidates($pdo) as [$kind, $row]) {
        $alive[(int) $row['user_id'] . '/' . $kind . '-' . (int) $row['id']] = true;
    }
    $swept = 0;
    foreach (glob(user_root() . '/*/thumbs/*') ?: [] as $file) {
        if (!preg_match('#/(\d+)/thumbs/(cloud|photo)-(\d+)\.(img|none|icc)$#', $file, $m)) {
            // A write that died between its two steps leaves a .tmp behind.
            if (str_ends_with($file, '.tmp') && filemtime($file) < time() - 3600) {
                @unlink($file);
            }
            continue;
        }
        if (!isset($alive[$m[1] . '/' . $m[2] . '-' . $m[3]])) {
            @unlink($file);
            $swept++;
        }
    }
    return $swept;
}
