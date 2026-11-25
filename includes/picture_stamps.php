<?php
/**
 * THE FIND'S PLACE AND NAME INSIDE ITS PICTURES (05.10.2026).
 *
 * The question was how a picture can be told to belong to a find. A
 * camera without GPS meant, for years, tracking the walks with a second
 * app and matching the pictures to the track by their clocks — „das war
 * immer recht nervig". Now every picture is filed to a find in YASAIMAPP anyway, and
 * the find knows where it is: „dann könnten ja auch die Funddaten GPS mit
 * in die Bilddaten genommen werden. Damit hat man schon mal alles in einer
 * Datei." And the name: „Fundnummer nein, aber GPS und Name ja. Sobald der
 * Name auf sicher steht, also unsichere Namen nicht."
 *
 * WHAT GOES IN
 *   GPS        the find's latitude and longitude, its height where known,
 *              datum WGS-84 — REPLACING whatever the camera wrote: a
 *              micrograph photographed at the desk with a phone carries
 *              the desk's position, and a find's picture must not say it
 *              was found in a kitchen (his answer, „zu eins ja").
 *   the name   the find's title, once its „Bestimmungssicherheit" is one of
 *              the three „sicher" steps — a positive list, so a step added
 *              later is not certain by accident (his words: „wenn wir alle
 *              anderen Status ausfiltern, und wir später erweitern, könnte
 *              es zu Komplikationen kommen"). It goes into the XMP twice:
 *              as a keyword (dc:subject — what Lightroom, digiKam and the
 *              Windows Explorer show as tags) and as dwc:scientificName,
 *              Darwin Core's field, what biodiversity software reads.
 *   NOT        the find number (his „nein"), nor anything else.
 *
 * THE TITLE, NOT THE VALID NAME the folders use: when GBIF renames a genus,
 * the folders follow (cloud_names.php), but the picture keeps the name it
 * was given — „das wäre allerdings auch nicht schlimm, wenn da weiterhin
 * dann der alte Name drinstehen würde, weil über die Synonymität würde man
 * das auch rausfinden". A title one changes oneself — another species under
 * the microscope — is written again.
 *
 * NOTHING ELSE IN THE FILE IS TOUCHED. Not a pixel: the picture's data after
 * its header is copied byte for byte. Not the camera's entries: IFD0 is
 * written anew at the END of the EXIF block with the GPS pointer added, the
 * header pointed at it, and every byte before stays where it was — so the
 * makers' notes, whose insides point at absolute places, stay valid; the
 * old IFD0 remains as unread bytes. Not the XMP's other content: the
 * packet is parsed and only these two properties are set or taken out.
 * Measured against exiftool (tests/picture_stamps.php).
 *
 * WHEN. The worker looks at every picture whose find changed after its last
 * look (map_points.updated_at against stamped_at) and compares the stamp
 * line with what the find wants now; only where they differ does it fetch
 * the file, write and put it back — in the cloud through the storage seam,
 * replacing the file under its name; a voucher photo still waiting on this
 * server in place. „Nachschreiben, ja — das passiert ja jetzt nicht am
 * Fließband": a handful of finds a day, the stock once.
 */

declare(strict_types=1);

/** Bumped when what is written changes — every picture is then written again. */
const PICTURE_STAMPS_VERSION = 'v1';

/** The steps of „Bestimmungssicherheit" that make a name certain (find_fields.php). */
const PICTURE_STAMPS_CERTAIN = ['makroskopisch sicher', 'mikroskopisch sicher', 'genetisch sicher'];

/** The largest file written: it is held twice in memory, read and written. */
const PICTURE_STAMPS_MAX_BYTES = 32 * 1024 * 1024;

/** Rows looked at per table and run. */
const PICTURE_STAMPS_BATCH = 200;

const XMP_HEAD = "http://ns.adobe.com/xap/1.0/\0";
const XMP_RDF  = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
const XMP_DC   = 'http://purl.org/dc/elements/1.1/';
/* DARWIN CORE AT THE ADDRESS THE TOOLS KNOW. The standard's RDF terms live
   at http://rs.tdwg.org/dwc/terms/, but exiftool and exiv2 (digiKam's
   engine) file „dwc" in XMP under the older index.htm — measured: written
   with /terms/, exiftool called the field „XMP-tmp0:ScientificName", a
   stranger. The name is for people opening their pictures in those tools. */
const XMP_DWC  = 'http://rs.tdwg.org/dwc/index.htm';

function picture_stamps_ready(PDO $pdo): bool
{
    static $ready = null;
    return $ready ??= column_exists($pdo, 'map_photos', 'stamp')
        && column_exists($pdo, 'map_cloud_files', 'stamp')
        && column_exists($pdo, 'map_cloud_copies', 'stamp');
}

/**
 * What a find's pictures should carry.
 *
 * @param array $point a map_points row (lat, lon, elevation_m, title, extra)
 * @return array{lat: float, lon: float, alt: ?float, name: ?string}
 */
function picture_stamp_wanted(array $point): array
{
    $extra = json_decode((string) ($point['extra'] ?? ''), true);
    $certainty = is_array($extra) ? trim((string) ($extra['Bestimmungssicherheit'] ?? '')) : '';
    $title = trim((string) (preg_replace('/\s+/u', ' ', (string) ($point['title'] ?? '')) ?? ''));
    $height = $point['elevation_m'] ?? null;
    return [
        'lat'  => round((float) $point['lat'], 6),
        'lon'  => round((float) $point['lon'], 6),
        'alt'  => is_numeric($height) ? round((float) $height, 1) : null,
        'name' => in_array($certainty, PICTURE_STAMPS_CERTAIN, true) && $title !== ''
            ? mb_substr($title, 0, 120) : null,
    ];
}

/** The stamp line — what is written, as one comparable string. */
function picture_stamp_line(array $w): string
{
    return implode('|', [
        PICTURE_STAMPS_VERSION,
        number_format($w['lat'], 6, '.', ''),
        number_format($w['lon'], 6, '.', ''),
        $w['alt'] === null ? '' : number_format($w['alt'], 1, '.', ''),
        str_replace('|', '/', (string) ($w['name'] ?? '')),
    ]);
}

/** The name a stamp line wrote, or null — to take it out again. */
function picture_stamp_old_name(?string $line): ?string
{
    if ($line === null) {
        return null;
    }
    $line = preg_replace('/^(skip|fail)\|/', '', $line) ?? $line;
    $parts = explode('|', $line);
    return isset($parts[4]) && $parts[4] !== '' ? $parts[4] : null;
}

/* --- Writing into a JPEG ---------------------------------------------------- */

/**
 * The JPEG with the find's GPS and (if any) its name in it.
 *
 * @param array   $w       picture_stamp_wanted()
 * @param ?string $oldName the name the previous stamp wrote, taken out of the keywords
 * @throws RuntimeException for anything that is not a JPEG this can write into safely
 */
function jpeg_stamp(string $jpeg, array $w, ?string $oldName = null): string
{
    if (strncmp($jpeg, "\xFF\xD8", 2) !== 0) {
        throw new RuntimeException('Kein JPEG.');
    }
    // The segments up to the picture data; the rest is copied untouched.
    $segments = [];
    $pos = 2;
    $length = strlen($jpeg);
    while (true) {
        if ($pos + 4 > $length || $jpeg[$pos] !== "\xFF") {
            throw new RuntimeException('Der Aufbau des JPEG ist nicht lesbar.');
        }
        $marker = ord($jpeg[$pos + 1]);
        if ($marker === 0xFF) {   // a fill byte before a marker
            $pos++;
            continue;
        }
        if ($marker === 0xDA || $marker === 0xD9) {   // the picture data begins / ends
            break;
        }
        $size = unpack('n', substr($jpeg, $pos + 2, 2))[1];
        if ($size < 2 || $pos + 2 + $size > $length) {
            throw new RuntimeException('Ein Abschnitt des JPEG ist zu kurz.');
        }
        $segments[] = [$marker, substr($jpeg, $pos + 4, $size - 2)];
        $pos += 2 + $size;
    }
    $rest = substr($jpeg, $pos);

    $exifAt = $xmpAt = null;
    foreach ($segments as $i => [$marker, $data]) {
        if ($marker === 0xE1 && $exifAt === null && strncmp($data, "Exif\0\0", 6) === 0) {
            $exifAt = $i;
        } elseif ($marker === 0xE1 && $xmpAt === null && strncmp($data, XMP_HEAD, strlen(XMP_HEAD)) === 0) {
            $xmpAt = $i;
        }
    }

    // THE GPS, into the EXIF block — a new one where the file has none,
    // behind the JFIF header if there is one.
    $tiff = exif_tiff_with_gps($exifAt === null ? null : substr($segments[$exifAt][1], 6), $w);
    $exif = [0xE1, "Exif\0\0" . $tiff];
    if (strlen($exif[1]) + 2 > 0xFFFF) {
        throw new RuntimeException('Der EXIF-Block würde zu groß.');
    }
    if ($exifAt !== null) {
        $segments[$exifAt] = $exif;
    } else {
        $exifAt = ($segments[0][0] ?? null) === 0xE0 ? 1 : 0;
        array_splice($segments, $exifAt, 0, [$exif]);
        if ($xmpAt !== null && $xmpAt >= $exifAt) {
            $xmpAt++;
        }
    }

    // THE NAME, into the XMP — only touched when there is a name to write or
    // one of ours to take out.
    $packet = $xmpAt === null ? null : substr($segments[$xmpAt][1], strlen(XMP_HEAD));
    $newPacket = xmp_with_name($packet, $w['name'], $oldName);
    if ($newPacket !== null) {
        $xmp = [0xE1, XMP_HEAD . $newPacket];
        if (strlen($xmp[1]) + 2 > 0xFFFF) {
            throw new RuntimeException('Das XMP würde zu groß.');
        }
        if ($xmpAt !== null) {
            $segments[$xmpAt] = $xmp;
        } else {
            array_splice($segments, $exifAt + 1, 0, [$xmp]);
        }
    }

    $out = "\xFF\xD8";
    foreach ($segments as [$marker, $data]) {
        $out .= "\xFF" . chr($marker) . pack('n', strlen($data) + 2) . $data;
    }
    return $out . $rest;
}

/**
 * A TIFF block (the EXIF block's body) with the GPS IFD the find wants.
 *
 * APPENDED, NEVER INSERTED: the GPS IFD and a copy of IFD0 with the GPS
 * pointer in it go behind everything that is there, and the header's
 * IFD0 offset is the only byte changed in front of them. Every offset the
 * camera wrote stays true. In the file's own byte order.
 */
function exif_tiff_with_gps(?string $tiff, array $w): string
{
    if ($tiff === null) {
        // No EXIF at all: a little-endian header and an empty IFD0, which the
        // rest of this function then treats like any other.
        $tiff = "II\x2A\x00" . pack('V', 8) . pack('v', 0) . pack('V', 0);
    }
    if (strlen($tiff) < 8) {
        throw new RuntimeException('Der EXIF-Block ist zu kurz.');
    }
    $order = substr($tiff, 0, 2);
    if ($order !== 'II' && $order !== 'MM') {
        throw new RuntimeException('Die Byte-Reihenfolge des EXIF-Blocks ist unbekannt.');
    }
    $le  = $order === 'II';
    $u16 = static fn(int $at): int => unpack($le ? 'v' : 'n', substr($tiff, $at, 2))[1];
    $u32 = static fn(int $at): int => unpack($le ? 'V' : 'N', substr($tiff, $at, 4))[1];
    $p16 = static fn(int $v): string => pack($le ? 'v' : 'n', $v);
    $p32 = static fn(int $v): string => pack($le ? 'V' : 'N', $v);
    if ($u16(2) !== 42) {
        throw new RuntimeException('Der EXIF-Block ist kein TIFF.');
    }

    $ifd0 = $u32(4);
    if ($ifd0 < 8 || $ifd0 + 2 > strlen($tiff)) {
        throw new RuntimeException('IFD0 liegt außerhalb des EXIF-Blocks.');
    }
    $count = $u16($ifd0);
    if ($ifd0 + 2 + 12 * $count + 4 > strlen($tiff)) {
        throw new RuntimeException('IFD0 reicht über den EXIF-Block hinaus.');
    }
    $entries = [];
    for ($i = 0; $i < $count; $i++) {
        $raw = substr($tiff, $ifd0 + 2 + 12 * $i, 12);
        $tag = $u16($ifd0 + 2 + 12 * $i);
        if ($tag !== 0x8825) {   // the old GPS pointer goes; ours comes
            $entries[$tag] = $raw;
        }
    }
    $next = $u32($ifd0 + 2 + 12 * $count);

    $out = $tiff . (strlen($tiff) % 2 ? "\0" : '');
    $gpsAt = strlen($out);
    $out .= gps_ifd($w, $gpsAt, $p16, $p32);
    $out .= strlen($out) % 2 ? "\0" : '';
    $entries[0x8825] = $p16(0x8825) . $p16(4) . $p32(1) . $p32($gpsAt);
    ksort($entries);
    $ifdAt = strlen($out);
    $out .= $p16(count($entries)) . implode('', $entries) . $p32($next);
    return substr($out, 0, 4) . $p32($ifdAt) . substr($out, 8);
}

/**
 * The GPS IFD standing at $at: version 2.3, the position in degrees,
 * minutes and seconds (to a ten-thousandth of a second, some three
 * millimetres), the height above the sea where the find knows it, WGS-84.
 */
function gps_ifd(array $w, int $at, callable $p16, callable $p32): string
{
    $rational = static fn(int $num, int $den): string => $p32($num) . $p32($den);
    $dms = static function (float $deg) use ($rational): string {
        $deg = abs($deg);
        $d = (int) floor($deg);
        $minutes = ($deg - $d) * 60;
        $m = (int) floor($minutes);
        $s = (int) round(($minutes - $m) * 60 * 10000);
        if ($s >= 600000) { $s -= 600000; $m++; }
        if ($m >= 60) { $m -= 60; $d++; }
        return $rational($d, 1) . $rational($m, 1) . $rational($s, 10000);
    };
    $list = [   // [tag, type, count, bytes] — 1 BYTE, 2 ASCII, 5 RATIONAL
        [0x0000, 1, 4, "\x02\x03\x00\x00"],
        [0x0001, 2, 2, ($w['lat'] < 0 ? 'S' : 'N') . "\0"],
        [0x0002, 5, 3, $dms($w['lat'])],
        [0x0003, 2, 2, ($w['lon'] < 0 ? 'W' : 'E') . "\0"],
        [0x0004, 5, 3, $dms($w['lon'])],
    ];
    if ($w['alt'] !== null) {
        $list[] = [0x0005, 1, 1, chr($w['alt'] < 0 ? 1 : 0)];
        $list[] = [0x0006, 5, 1, $rational((int) round(abs($w['alt']) * 10), 10)];
    }
    $list[] = [0x0012, 2, 7, "WGS-84\0"];

    $dataAt = $at + 2 + 12 * count($list) + 4;
    $ifd = $p16(count($list));
    $data = '';
    foreach ($list as [$tag, $type, $n, $bytes]) {
        $ifd .= $p16($tag) . $p16($type) . $p32($n);
        if (strlen($bytes) <= 4) {
            $ifd .= str_pad($bytes, 4, "\0");
        } else {
            $ifd .= $p32($dataAt + strlen($data));
            $data .= $bytes . (strlen($bytes) % 2 ? "\0" : '');
        }
    }
    return $ifd . $p32(0) . $data;
}

/**
 * The XMP packet with the name set — or taken out — and nothing else
 * changed; null when nothing is to be written (no packet and no name).
 *
 * @throws RuntimeException when an existing packet is not readable XML —
 *         a packet one cannot read is a packet one must not rewrite
 */
function xmp_with_name(?string $packet, ?string $name, ?string $oldName): ?string
{
    if ($packet === null && $name === null) {
        return null;
    }
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = true;
    if ($packet === null) {
        $doc->loadXML('<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="' . XMP_RDF . '">'
            . '<rdf:Description rdf:about=""/></rdf:RDF></x:xmpmeta>');
    } elseif (!@$doc->loadXML(trim($packet, "\0 \t\r\n"), LIBXML_NONET)) {
        throw new RuntimeException('Das XMP im Bild ist kein lesbares XML.');
    }
    $rdf = $doc->getElementsByTagNameNS(XMP_RDF, 'RDF')->item(0);
    if ($rdf === null) {
        throw new RuntimeException('Das XMP im Bild hat keinen RDF-Teil.');
    }
    $descriptions = [];
    foreach ($rdf->childNodes as $node) {
        if ($node instanceof DOMElement && $node->namespaceURI === XMP_RDF && $node->localName === 'Description') {
            $descriptions[] = $node;
        }
    }
    if ($descriptions === []) {
        $first = $doc->createElementNS(XMP_RDF, 'rdf:Description');
        $first->setAttributeNS(XMP_RDF, 'rdf:about', '');
        $rdf->appendChild($first);
        $descriptions[] = $first;
    }

    // Darwin Core's scientific name: one value, ours — written as an
    // attribute, as exiftool writes a simple property.
    foreach ($descriptions as $d) {
        $d->removeAttributeNS(XMP_DWC, 'scientificName');
        foreach (iterator_to_array($d->getElementsByTagNameNS(XMP_DWC, 'scientificName')) as $old) {
            $old->parentNode->removeChild($old);
        }
    }
    if ($name !== null) {
        $descriptions[0]->setAttributeNS(XMP_DWC, 'dwc:scientificName', $name);
    }

    // The keywords: a bag shared with whatever else tagged the picture —
    // only our name goes in, and only our previous one comes out.
    $bag = null;
    foreach ($descriptions as $d) {
        foreach ($d->getElementsByTagNameNS(XMP_DC, 'subject') as $subject) {
            $bag ??= $subject->getElementsByTagNameNS(XMP_RDF, 'Bag')->item(0);
        }
    }
    if ($bag !== null) {
        foreach (iterator_to_array($bag->getElementsByTagNameNS(XMP_RDF, 'li')) as $li) {
            $text = trim($li->textContent);
            if (($oldName !== null && $text === $oldName) || ($name !== null && $text === $name)) {
                $bag->removeChild($li);
            }
        }
    }
    if ($name !== null) {
        if ($bag === null) {
            $subject = $doc->createElementNS(XMP_DC, 'dc:subject');
            $bag = $doc->createElementNS(XMP_RDF, 'rdf:Bag');
            $subject->appendChild($bag);
            $descriptions[0]->appendChild($subject);
        }
        $li = $doc->createElementNS(XMP_RDF, 'rdf:li');
        $li->appendChild($doc->createTextNode($name));
        $bag->appendChild($li);
    } elseif ($bag !== null && $bag->getElementsByTagNameNS(XMP_RDF, 'li')->length === 0) {
        $bag->parentNode->parentNode->removeChild($bag->parentNode);   // an empty dc:subject goes
    }

    return "<?xpacket begin=\"\u{FEFF}\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?>\n"
        . $doc->saveXML($doc->documentElement)
        . "\n<?xpacket end=\"w\"?>";
}

/* --- The worker -------------------------------------------------------------- */

/**
 * Bring the pictures whose finds changed in line, until the deadline — the
 * five-minute bundle's job. $open is the tests' door to a fake cloud, as at
 * the names (cloud_names_run).
 *
 * @return array{stamped: int, checked: int, skipped: int, failed: int, open: int}
 */
function picture_stamps_run(PDO $pdo, float $deadline, ?callable $open = null): array
{
    require_once __DIR__ . '/cloud.php';
    require_once __DIR__ . '/photos.php';
    $sum = ['stamped' => 0, 'checked' => 0, 'skipped' => 0, 'failed' => 0, 'open' => 0];
    if (!picture_stamps_ready($pdo)) {
        return $sum;
    }
    $storages = [];
    $storage = static function (int $user, string $provider) use ($pdo, $open, &$storages): ?array {
        $key = $user . ':' . $provider;
        if (!array_key_exists($key, $storages)) {
            $storages[$key] = $open !== null ? $open($user, $provider) : cloud_storage_for($pdo, $user, $provider);
        }
        return $storages[$key];
    };
    foreach (picture_stamps_due($pdo, PICTURE_STAMPS_BATCH) as $item) {
        if (microtime(true) >= $deadline) {
            break;
        }
        $result = picture_stamp_item($pdo, $item, $storage);
        $sum[$result]++;
    }
    $sum['open'] = count(picture_stamps_due($pdo, PICTURE_STAMPS_BATCH));
    return $sum;
}

/**
 * The pictures to look at: never looked at, their find changed since, or
 * failed more than a day ago. Each with the find's columns the stamp is
 * made of. Pictures of finds in the trash wait for the find to come back.
 *
 * @return list<array{kind: string, row: array, point: array}>
 */
function picture_stamps_due(PDO $pdo, int $limit): array
{
    $due = '(x.stamp IS NULL OR x.stamped_at IS NULL OR x.stamped_at <= p.updated_at
             OR (x.stamp LIKE "fail|%" AND x.stamped_at < NOW() - INTERVAL 1 DAY))';
    $pointColumns = 'p.lat AS p_lat, p.lon AS p_lon, p.elevation_m AS p_elevation_m,
                     p.title AS p_title, p.extra AS p_extra';
    $items = [];
    $sets = [
        'photo' => "SELECT x.*, $pointColumns FROM map_photos x JOIN map_points p ON p.id = x.point_id
                     WHERE p.deleted_at IS NULL AND $due ORDER BY x.id LIMIT $limit",
        'cloud' => "SELECT x.*, $pointColumns FROM map_cloud_files x JOIN map_points p ON p.id = x.point_id
                     WHERE p.deleted_at IS NULL AND x.fileid <> '' AND $due ORDER BY x.id LIMIT $limit",
        'copy'  => "SELECT x.*, $pointColumns FROM map_cloud_copies x
                      LEFT JOIN map_photos o1 ON x.origin_kind = 'photo' AND o1.id = x.origin_id
                      LEFT JOIN map_cloud_files o2 ON x.origin_kind = 'cloud' AND o2.id = x.origin_id
                      JOIN map_points p ON p.id = COALESCE(o1.point_id, o2.point_id)
                     WHERE x.status = 'copied' AND x.fileid IS NOT NULL AND x.fileid <> ''
                       AND p.deleted_at IS NULL AND $due ORDER BY x.id LIMIT $limit",
    ];
    foreach ($sets as $kind => $sql) {
        foreach ($pdo->query($sql) as $row) {
            $point = ['lat' => $row['p_lat'], 'lon' => $row['p_lon'], 'elevation_m' => $row['p_elevation_m'],
                      'title' => $row['p_title'], 'extra' => $row['p_extra']];
            $items[] = ['kind' => $kind, 'row' => $row, 'point' => $point];
        }
    }
    return $items;
}

/**
 * One picture: compare, and write where it differs.
 *
 * @param callable(int, string): ?array $storage
 * @return 'stamped'|'checked'|'skipped'|'failed'
 */
function picture_stamp_item(PDO $pdo, array $item, callable $storage): string
{
    $table = ['photo' => 'map_photos', 'cloud' => 'map_cloud_files', 'copy' => 'map_cloud_copies'][$item['kind']];
    $row = $item['row'];
    $id = (int) $row['id'];
    $want = picture_stamp_wanted($item['point']);
    $line = picture_stamp_line($want);
    $mark = static function (string $stamp, array $more = []) use ($pdo, $table, $id): void {
        $set = 'stamp = ?, stamped_at = NOW()';
        $args = [$stamp];
        foreach ($more as $column => $value) {
            $set .= ", $column = ?";
            $args[] = $value;
        }
        $args[] = $id;
        $pdo->prepare("UPDATE $table SET $set WHERE id = ?")->execute($args);
    };

    // Already carries it — or cannot carry it, for as long as nothing changed.
    $have = (string) ($row['stamp'] ?? '');
    if ($have === $line || $have === 'skip|' . $line) {
        $pdo->prepare("UPDATE $table SET stamped_at = NOW() WHERE id = ?")->execute([$id]);
        return 'checked';
    }
    $oldName = picture_stamp_old_name($row['stamp'] ?? null);

    try {
        // A VOUCHER PHOTO STILL WAITING ON THIS SERVER is written in place —
        // pushed into the cloud later, it takes the stamp along.
        if ($item['kind'] === 'photo' && (string) ($row['cloud_fileid'] ?? '') === '') {
            $path = photo_file((int) $row['user_id'], (string) $row['filename']);
            if ($path === null || !is_file($path) || filesize($path) > PICTURE_STAMPS_MAX_BYTES) {
                $mark('skip|' . $line);
                return 'skipped';
            }
            $stamped = jpeg_stamp((string) file_get_contents($path), $want, $oldName);
            $temporary = $path . '.stamp';
            if (file_put_contents($temporary, $stamped) !== strlen($stamped) || !rename($temporary, $path)) {
                @unlink($temporary);
                throw new RuntimeException('Das Foto ließ sich nicht zurückschreiben.');
            }
            $mark($line, ['bytes' => strlen($stamped)]);
            return 'stamped';
        }

        [$user, $provider, $fileid] = match ($item['kind']) {
            'photo' => [(int) $row['user_id'], (string) ($row['cloud_provider'] ?? '') ?: 'pcloud', (string) $row['cloud_fileid']],
            'cloud' => [(int) $row['user_id'], (string) ($row['provider'] ?? '') ?: 'pcloud', (string) $row['fileid']],
            'copy'  => [(int) $row['user_id'], (string) ($row['provider'] ?? '') ?: 'pcloud', (string) $row['fileid']],
        };
        $s = $storage($user, $provider);
        if ($s === null) {
            return 'failed';   // no cloud to reach now — looked at again next run
        }
        $meta = storage_file_meta($s, $fileid);
        if ($meta === null || !preg_match('/\.jpe?g$/i', (string) $meta['name'])
            || (int) $meta['size'] > PICTURE_STAMPS_MAX_BYTES) {
            $mark('skip|' . $line);
            return 'skipped';
        }
        $stamped = jpeg_stamp(storage_file_bytes($s, $fileid, PICTURE_STAMPS_MAX_BYTES), $want, $oldName);
        // REPLACED UNDER ITS NAME, in its folder — the providers keep the id
        // (Drive, OneDrive, S3's key); should one hand out a new one, the
        // row follows it.
        $up = storage_upload_bytes($s, $meta['parent'], $stamped, (string) $meta['name'], null, true, 'image/jpeg');
        $more = [];
        if ((string) $up['fileid'] !== $fileid) {
            $more[$item['kind'] === 'photo' ? 'cloud_fileid' : 'fileid'] = (string) $up['fileid'];
        }
        if ($item['kind'] !== 'photo') {
            $more['size'] = (int) $up['size'];
        }
        $mark($line, $more);
        return 'stamped';
    } catch (RuntimeException $e) {
        // Not writable this way: noted, not tried again until the find changes.
        error_log('picture_stamp_item (' . $item['kind'] . ' ' . $id . '): ' . $e->getMessage());
        $mark((str_starts_with($e->getMessage(), 'Kein JPEG') ? 'skip|' : 'fail|') . $line);
        return 'failed';
    } catch (Throwable $e) {
        error_log('picture_stamp_item (' . $item['kind'] . ' ' . $id . '): ' . $e->getMessage());
        $mark('fail|' . $line);
        return 'failed';
    }
}
