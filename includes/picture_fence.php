<?php
/**
 * THE FENCE AROUND EVERY PICTURE THE SERVER HANDS ON
 * (01.11.2026: „dass diese nicht irgendwie mit einem Virus versehen sind").
 * Pictures reach a viewer from places nobody here controls — a friend's
 * cloud, a co-owner's micrograph, somebody's archive share — and every
 * one of them goes out under OUR address, with the viewer's session.
 *
 * WHAT A PICTURE CAN DO, measured against what it is fenced with:
 *
 * - RUN AS A PAGE. A file named „.jpg" may hold HTML or an SVG with
 *   script; opened as a document at our address it would run as the
 *   viewer. That is the real danger and the one this file closes: the
 *   type goes out by the FIRST BYTES (picture_type), never by the name or
 *   the provider's word, the browser is told not to guess (nosniff), and
 *   the answer is a sandbox that may run nothing even if it were a page.
 *   What is no raster picture by its bytes is never shown inline — it goes
 *   out as an opaque download.
 * - CRASH A DECODER. A picture built to break the browser's JPEG or WebP
 *   code (libwebp, September 2023) is no file one can recognise by
 *   looking; the fence is a current browser. What lowers the exposure is
 *   that almost everything one looks at is a PREVIEW, and a preview is
 *   drawn anew — by pCloud, Drive or OneDrive (getthumb and its kin), or
 *   here by thumb_shrink() — so the uploader's bytes reach a viewer only
 *   where he asks for the original.
 * - EXHAUST THE SERVER. A small file that declares 100,000 × 100,000
 *   pixels makes GD reserve tens of gigabytes. Nothing is decoded here
 *   above PICTURE_DECODE_PIXELS, and originals are streamed, never held
 *   in memory (cloud_deliver).
 *
 * Every path that sends picture bytes sends them through picture_headers():
 * pages/photo.php, cloud_deliver() for every provider's previews and
 * originals (cloud_thumb, point_file, plate_image, calibration_image,
 * the voucher photos in the cloud), thumb_serve() for the kept previews,
 * the picture archives (old_picture.php), the key figures and the aerial
 * relay. pages/rain.php draws its own and is left alone.
 */

declare(strict_types=1);

/** The ceiling above which nothing is decoded on this server — some 160 MB of GD memory. */
const PICTURE_DECODE_PIXELS = 40_000_000;

/**
 * The raster type the bytes really are, by their first bytes — or null.
 * Sixteen bytes are enough for every type here. A type here is one a
 * browser draws as a picture and nothing else; SVG is deliberately
 * missing, because it is a document that can carry script.
 */
function picture_type(string $head): ?string
{
    $brand = substr($head, 4, 4) === 'ftyp' ? substr($head, 8, 4) : '';
    return match (true) {
        str_starts_with($head, "\xFF\xD8\xFF") => 'image/jpeg',
        str_starts_with($head, "\x89PNG\r\n\x1A\n") => 'image/png',
        str_starts_with($head, 'GIF87a'), str_starts_with($head, 'GIF89a') => 'image/gif',
        str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'image/webp',
        in_array($brand, ['avif', 'avis'], true) => 'image/avif',
        in_array($brand, ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis'], true) => 'image/heic',
        in_array($brand, ['mif1', 'msf1'], true) => 'image/heif',
        str_starts_with($head, "II*\0"), str_starts_with($head, "MM\0*") => 'image/tiff',
        str_starts_with($head, 'BM') => 'image/bmp',
        default => null,
    };
}

/**
 * The headers every picture goes out with. $type null means: no picture by
 * its bytes — then an opaque download, never inline.
 *
 * PHP'S SESSION HEADERS GO FIRST. session_start() stamps every answer with
 * „Expires: 1981" and „Pragma: no-cache" — right for a page, wrong for a
 * picture; Cache-Control wins by the standard, but a header saying the
 * opposite of the one below it is a trap for the next reader.
 */
function picture_headers(?string $type, ?int $length, string $cache, string $fileName = ''): void
{
    header_remove('Expires');
    header_remove('Pragma');
    header('Content-Type: ' . ($type ?? 'application/octet-stream'));
    header('Content-Disposition: ' . ($type === null ? 'attachment' : 'inline')
        . ($fileName === '' ? '' : '; filename="' . $fileName . '"'));
    if ($length !== null) {
        header('Content-Length: ' . $length);
    }
    header('Cache-Control: ' . $cache);
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
}

/** Whether GD may decode a picture of these bytes — known size, below the ceiling. */
function picture_decodable(string $bytes): bool
{
    $info = @getimagesizefromstring($bytes);
    return is_array($info) && $info[0] > 0 && $info[1] > 0 && $info[0] * $info[1] <= PICTURE_DECODE_PIXELS;
}

// --- the coordinates in a picture --------------------------------------------

/**
 * A JPEG with its GPS taken out — for an archive whose owner hides his find
 * spots from his friends (02.11.2026: „dass man das GPS
 * rausnimmt, so dass man die Fundorte verschleiern kann").
 *
 * IN PLACE, NOT CUT OUT: every byte keeps its position, so the length the
 * provider announced stays true and the picture can still be streamed —
 * the GPS of an EXIF block is overwritten with zeros (its entries, the
 * values they point at, and the block's count, so a reader finds an empty
 * GPS block), and the exif:GPS… values of an XMP block with spaces. The
 * rest of EXIF stays: the day, the camera, and above all the orientation,
 * without which a phone's picture would lie on its side.
 *
 * Only the leading metadata blocks are looked at (APP0–APP15 and comments,
 * up to the first block that belongs to the picture itself); the pixels
 * are never touched. A malformed block is left as it is rather than
 * guessed at — what cannot be read can hold no GPS a reader would find.
 */
function picture_without_gps(string $jpeg): string
{
    if (!str_starts_with($jpeg, "\xFF\xD8")) {
        return $jpeg;
    }
    $pos = 2;
    $size = strlen($jpeg);
    while ($pos + 4 <= $size && $jpeg[$pos] === "\xFF") {
        $marker = ord($jpeg[$pos + 1]);
        if (!(($marker >= 0xE0 && $marker <= 0xEF) || $marker === 0xFE)) break;
        $length = unpack('n', substr($jpeg, $pos + 2, 2))[1];
        $start = $pos + 4;
        $end = $pos + 2 + $length;
        if ($length < 2 || $end > $size) break;
        if ($marker === 0xE1 && substr($jpeg, $start, 6) === "Exif\0\0") {
            $jpeg = picture_exif_without_gps($jpeg, $start + 6, $end);
        } elseif ($marker === 0xE1) {
            // XMP and its extension blocks: text, so the values become spaces of the same length.
            $block = preg_replace_callback(
                ['~(exif:GPS[A-Za-z]+=")([^"]*)(")~', '~(<exif:GPS([A-Za-z]+)>)(.*?)(</exif:GPS\2>)~s'],
                static fn(array $m) => count($m) === 4
                    ? $m[1] . str_repeat(' ', strlen($m[2])) . $m[3]
                    : $m[1] . str_repeat(' ', strlen($m[3])) . $m[4],
                substr($jpeg, $start, $end - $start)
            );
            if ($block !== null && strlen($block) === $end - $start) {
                $jpeg = substr_replace($jpeg, $block, $start, $end - $start);
            }
        }
        $pos = $end;
    }
    return $jpeg;
}

/** The EXIF block from $tiff to $end with its GPS block emptied — see picture_without_gps(). */
function picture_exif_without_gps(string $jpeg, int $tiff, int $end): string
{
    $order = substr($jpeg, $tiff, 2);
    if ($order !== 'II' && $order !== 'MM') return $jpeg;
    $u16 = static fn(int $at): ?int => $at + 2 <= $end ? unpack($order === 'II' ? 'v' : 'n', substr($jpeg, $at, 2))[1] : null;
    $u32 = static fn(int $at): ?int => $at + 4 <= $end ? unpack($order === 'II' ? 'V' : 'N', substr($jpeg, $at, 4))[1] : null;
    $zero = static function (int $at, int $n) use (&$jpeg, $end): void {
        $n = min($n, $end - $at);
        if ($at >= 0 && $n > 0) $jpeg = substr_replace($jpeg, str_repeat("\0", $n), $at, $n);
    };

    $ifd0 = $u32($tiff + 4);
    $count = $ifd0 === null ? null : $u16($tiff + $ifd0);
    $gps = null;
    for ($i = 0; $count !== null && $i < $count; $i++) {
        $entry = $tiff + $ifd0 + 2 + 12 * $i;
        if ($u16($entry) === 0x8825) {
            $gps = $u32($entry + 8);
            break;
        }
    }
    $entries = $gps === null ? null : $u16($tiff + $gps);
    if ($entries === null || $entries > 200) return $jpeg;

    // Bytes per value of each EXIF type: BYTE, ASCII, SHORT, LONG, RATIONAL, … — 0 for an unknown one.
    $width = [1 => 1, 2 => 1, 3 => 2, 4 => 4, 5 => 8, 6 => 1, 7 => 1, 8 => 2, 9 => 4, 10 => 8, 11 => 4, 12 => 8];
    for ($i = 0; $i < $entries; $i++) {
        $entry = $tiff + $gps + 2 + 12 * $i;
        $type = $u16($entry + 2);
        $n = $u32($entry + 4);
        if ($type === null || $n === null) break;
        $bytes = ($width[$type] ?? 0) * $n;
        $at = $u32($entry + 8);
        if ($bytes > 4 && $at !== null) {
            $zero($tiff + $at, $bytes);
        }
        $zero($entry, 12);
    }
    $zero($tiff + $gps, 2);
    return $jpeg;
}

/**
 * The leading metadata blocks of a JPEG being streamed, read up to the
 * first block of the picture itself — what picture_without_gps() needs
 * before the rest can pass through untouched. At most 1 MB: EXIF is
 * limited to 64 KB a block, and a file that goes on past that with
 * metadata is no camera's.
 *
 * @param resource $stream
 */
function picture_jpeg_head($stream, string $head): string
{
    $more = static function (int $need) use ($stream, &$head): bool {
        while (strlen($head) < $need && !feof($stream)) {
            $chunk = fread($stream, $need - strlen($head));
            if ($chunk === false || $chunk === '') break;
            $head .= $chunk;
        }
        return strlen($head) >= $need;
    };
    $pos = 2;
    while ($pos < 1_048_576 && $more($pos + 4) && $head[$pos] === "\xFF") {
        $marker = ord($head[$pos + 1]);
        if (!(($marker >= 0xE0 && $marker <= 0xEF) || $marker === 0xFE)) break;
        $pos += 2 + unpack('n', substr($head, $pos + 2, 2))[1];
        if (!$more($pos)) break;
    }
    return $head;
}
