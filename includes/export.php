<?php
/**
 * Writing points out — CSV, GeoJSON, KML, GPX.
 *
 * WHAT WE CAN READ WE MUST BE ABLE TO WRITE (23.08.2026). The
 * four reading modules beside this one (csv.php, geojson.php, kml.php,
 * gpx.php) are the import side; this is their mirror. Takeout has no
 * counterpart on purpose: it is Google's export format, not an exchange
 * format — writing it would be imitating somebody else's product.
 *
 * THE ROUND TRIP IS THE TEST OF ALL FOUR: whatever leaves here must come
 * back through our own import without a mapping step. That is why the CSV
 * header carries exactly the names csv_guess() recognizes (CSV_ROLES /
 * CSV_NAMES in csv.php), why the GeoJSON properties use the names
 * geojson_property() fishes for, and why KML and GPX stay within the plain
 * elements kml_read() and gpx_read() take. tests/export.php walks a point
 * out and back in through each of them.
 *
 * EVERY FORMAT LOSES SOMETHING, and it must lose it openly. CSV holds one
 * line per point and thus no photos; GPX knows a waypoint and no outline;
 * KML and GeoJSON could carry the outline but our own reading side takes
 * points only. What none of them carries — lists, find fields, photos —
 * stays in the note where the format has a place for text, so a person
 * reading the file still sees it. The full stock with everything hanging
 * on it lives in the database dump (Verwaltung → Datenbankabzug), and this
 * here says so where a format runs short.
 *
 * NO STREAMING, NO CHUNKS. A find stock of a few thousand points is a
 * couple of megabytes of text; building it in memory and handing it over
 * in one piece keeps every writer a pure function — testable without a
 * web server, which is exactly what tests/export.php lives off.
 */

declare(strict_types=1);

require_once __DIR__ . '/csv.php';
require_once __DIR__ . '/find_fields.php';
// For mtb_reference(): the sheet reference is written here, and the rule for
// how it is written lives there — not a second time in this file.
require_once __DIR__ . '/mtb.php';
require_once __DIR__ . '/dgfm_marks.php';
require_once __DIR__ . '/mykis.php';
require_once __DIR__ . '/collections.php';

/** The offered formats: key => [label, extension, mime]. */
const EXPORT_FORMATS = [
    'csv'     => ['CSV-Tabelle',   'csv',     'text/csv; charset=utf-8'],
    'geojson' => ['GeoJSON',       'geojson', 'application/geo+json'],
    'kml'     => ['KML (Google Earth)', 'kml', 'application/vnd.google-earth.kml+xml'],
    'gpx'     => ['GPX (GPS-Geräte)',   'gpx', 'application/gpx+xml'],
    // Not an exchange format like the four above but a delivery: the sheet
    // the state coordinators import into MykIS (includes/mykis.php). No
    // round trip — it goes out coarsened on purpose.
    'mykis'   => ['MykIS-Tabelle',      'csv', 'text/csv; charset=utf-8'],
];

/**
 * The points the export may write out — own ones and the shared ones.
 *
 * FOREIGN FINDS BELONG IN (23.08.2026): an excursion report
 * lists what the group found, not what one person found, and the finds of
 * the others arrive through the containers. The first draft left them out
 * on the grounds that they belong to their owners — but so does what one
 * sees on the map, and seeing is what an export writes down.
 *
 * WHAT MAY BE WRITTEN IS WHAT THIS ACCOUNT MAY SEE, and the check happens
 * here rather than being trusted from the form: the foreign candidates are
 * fetched fresh through the containers this account is a member of
 * (container_points, which applies the birth filter), so a hand-crafted
 * request can name any id it likes and still only gets what a container
 * really hands over.
 *
 * @param int[] $pointIds  empty means: everything living, own and shared
 * @return array[] rows as point_prepare shapes them, foreign ones carrying
 *         'owner' — the name that turns a mixed report into a readable one
 */
function export_points(PDO $pdo, int $userId, array $pointIds): array
{
    $points = points_load($pdo, $userId);

    require_once __DIR__ . '/container.php';
    if (container_ready($pdo)) {
        $seen = [];
        foreach ($points as $p) {
            $seen[(int) $p['id']] = true;
        }
        foreach (container_mine($pdo, $userId) as $c) {
            foreach (container_points($pdo, (int) $c['id'], [], $userId) as $p) {
                $id = (int) $p['id'];
                // An own point mounted in a container is already in the
                // stock above — it must not stand in the file twice.
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $points[]  = $p;
            }
        }
    }

    /* THE FINDS I CO-OWN (co-ownership, step 6, 11.09.2026) — a report
       over an excursion is exactly where the leader's micrographs on a
       guest's find belong, and they hang on a find that is not hers.
       Same de-duplication as the containers: an id already there wins. */
    require_once __DIR__ . '/point_owners.php';
    require_once __DIR__ . '/excursions.php';
    $seen = [];
    foreach ($points as $p) {
        $seen[(int) $p['id']] = true;
    }
    foreach (co_owned_points($pdo, $userId) as $p) {
        if (!isset($seen[(int) $p['id']])) {
            $seen[(int) $p['id']] = true;
            $points[] = $p;
        }
    }
    /* AND THE FINDS OF MY EXCURSIONS (12.09.2026, after the first real
       one — „die Fundliste, die ich bei Organisieren erstellen
       kann, hat die Gastfunde nicht dabei"). Organisieren has shown the
       guests' finds since stage 5 and let one tick them; the report and
       the file export then took the ticked ids to THIS list, which held
       containers and co-owned finds and nothing of excursions — so the
       guests' rows silently fell through the sieve below. The leader's
       view first, the participant's after it; an id already there wins,
       as with the two sources above. */
    foreach ([excursion_points($pdo, $userId), excursion_participant_points($pdo, $userId)] as $source) {
        foreach ($source as $p) {
            if (!isset($seen[(int) $p['id']])) {
                $seen[(int) $p['id']] = true;
                $points[] = $p;
            }
        }
    }
    if ($pointIds !== []) {
        $wanted = array_flip(array_map('intval', $pointIds));
        $points = array_values(array_filter($points,
            static fn(array $p): bool => isset($wanted[(int) $p['id']])));
    }
    /* THE FIND NUMBER WITHOUT A PATTERN travels too
       (25.09.2026: „exportiere Y-100 auch") — the mark the map shows,
       find_number_fallback(), and like there on finds only: a point in a
       species branch, its own or a foreign one (list_branch_root walks
       past a mounted sublist), or a guest's excursion find. One place for
       every format — CSV, GeoJSON, KML, GPX and the report all read
       find_number from here.

       A STORED NUMBER OBEYS THE SAME GATE (26.09.2026: numbers
       belong to finds in species lists). A point set into a species list
       draws its number at birth and keeps it when it wanders out — on
       purpose, or a real find taken out by mistake and put back would
       come home with a new number that no voucher label carries
       (find_number_mint). While it lies outside, the map shows no
       number, so the file shows none either: a parking spot in the
       „Fundnummer" column would read like a find. The search still
       finds it by that number (point_haystack) — a misplaced find must
       stay findable by the number on its label. */
    require_once __DIR__ . '/find_number.php';
    $speciesBranch = [];
    foreach ($points as &$p) {
        if ((int) $p['id'] <= 0) {
            continue;
        }
        $isFind = isset($p['excursion_id']);
        foreach ($isFind ? [] : ($p['lists'] ?? []) as $listId) {
            $speciesBranch[(int) $listId] ??= list_branch_root($pdo, (int) $listId)['is_species'];
            if ($speciesBranch[(int) $listId]) {
                $isFind = true;
                break;
            }
        }
        if (!empty($p['is_collection'])) {
            // The box number, in every format — no „Y-" mark for a box.
            $p['find_number'] = ($p['collection_number'] ?? '') !== '' ? (string) $p['collection_number'] : null;
        } elseif (!$isFind) {
            $p['find_number'] = null;
        } elseif ((string) ($p['find_number'] ?? '') === '') {
            $p['find_number'] = find_number_fallback((int) $p['id']);
        }
    }
    unset($p);

    /* AND THE CLOUD RECORDINGS OF EACH FIND — mine, and on a co-owned
       find the whole circle's, with photographer and my copy's state
       (cloud_files_rows). Until now the report knew only the voucher
       photos on this server; the micrographs, the reason the cloud
       exists, never reached the page. One query per find, and a report
       is a selection, not the whole stock. */
    require_once __DIR__ . '/cloud.php';
    foreach ($points as &$p) {
        $p['recordings_list'] = cloud_files_for_point($pdo, $userId, (int) $p['id']);
    }
    unset($p);

    /* THE LIST NAMES, resolved here and once: a point carries its lists as
       ids, and a column of numbers would be a column nobody can read.
       The foreign lists come along — a shared find sits in ITS OWNER'S
       list, and "Bens Beobachtungen" is exactly the answer the report
       wants; without them a foreign row's list column would stand empty. */
    $names = [];
    foreach (lists_load($pdo, $userId) as $l) {
        $names[(int) $l['id']] = (string) $l['name'];
    }
    if (container_ready($pdo)) {
        foreach (container_mine($pdo, $userId) as $c) {
            foreach (container_shares($pdo, (int) $c['id'], $userId) as $share) {
                foreach ($share['lists'] ?? [] as $l) {
                    $names[(int) $l['id']] ??= (string) $l['name'];
                }
            }
        }
    }
    /* THE TAXON REFERENCES, fetched separately: point_prepare() does not
       carry extra_species — the browsers get the derived endangerment
       instead, and the raw reference is nobody's business out there. The
       report needs it as the key for "same species" (report_merge_groups),
       so the export fetches it for exactly the points it is about to
       write. The ids have passed the access check above. */
    $references = [];
    $ids = array_map(static fn(array $p): int => (int) $p['id'], $points);
    if ($ids !== [] && column_exists($pdo, 'map_points', 'extra_species')) {
        $space = implode(',', array_fill(0, count($ids), '?'));
        $stmt  = $pdo->prepare("SELECT id, extra_species FROM map_points WHERE id IN ($space)");
        $stmt->execute($ids);
        foreach ($stmt as $row) {
            $references[(int) $row['id']] = $row['extra_species'];
        }
    }

    /* THE BOX A SPECIES GREW IN, by its number (0174) — the files name it
       („ex cultura (Aufsammlung A-2026-01)"), and the species carries only
       the id. Fetched for the ids that are there; the sample need not be
       in the selection, its number is no secret to whoever sees what grew
       from it. */
    $boxes = collection_numbers($pdo, array_map(static fn(array $p) => $p['collection_id'] ?? 0, $points));

    foreach ($points as &$p) {
        if (($p['collection_id'] ?? null) !== null) {
            $p['collection_parent_number'] = $boxes[(int) $p['collection_id']] ?? null;
        }
        $p['list_names'] = array_values(array_filter(array_map(
            static fn($id): ?string => $names[(int) $id] ?? null,
            (array) ($p['lists'] ?? []))));
        $p['extra_species'] = $references[(int) $p['id']] ?? null;
    }
    unset($p);

    return $points;
}

/**
 * What the formats agree on: the fields every writer puts down.
 *
 * The find time goes out as the point carries it ('Y-m-d H:i:s') for CSV —
 * csv_datetime() reads exactly that back — and as ISO 8601 for GPX and
 * KML, where the schema prescribes it.
 */
function export_row(array $p): array
{
    return [
        'title'       => (string) ($p['title'] ?? ''),
        'lat'         => (float) $p['lat'],
        'lon'         => (float) $p['lon'],
        'found_at'    => $p['found_at'] ?? null,
        'elevation_m' => $p['elevation_m'] ?? null,
        'accuracy_m'  => $p['accuracy_m'] ?? null,
        'find_number' => $p['find_number'] ?? null,
        'note'        => export_note($p),
    ];
}

/**
 * The note, plus everything the formats have no field for.
 *
 * WHAT WOULD OTHERWISE FALL SILENT goes in here as "Name: Wert" lines —
 * the find fields, the lists, the tour. That is the same move the import
 * makes in the other direction (geojson_note, csv unmapped columns): a
 * value nobody can place still belongs in the text rather than nowhere.
 * The reading side hangs it back onto the note; nothing is lost twice.
 */
function export_note(array $p): string
{
    $parts = [];
    $note = trim((string) ($p['note'] ?? ''));
    if ($note !== '') {
        $parts[] = $note;
    }

    /* (array) AND NOT is_array(): point_extra_read() returns an OBJECT,
       always — so an is_array() guard here quietly yielded the empty set
       for every real point, and no find field ever reached a file. The
       first version had exactly that guard, and the test did not catch it
       because its fixture handed in a plain array. It hands in an object
       now (tests/export.php). */
    foreach ((array) ($p['extra'] ?? []) as $name => $value) {
        $value = trim((string) $value);
        if ($name !== '' && $value !== '') {
            $parts[] = $name . ': ' . $value;
        }
    }
    if (($p['tour'] ?? null) !== null && trim((string) $p['tour']) !== '') {
        $parts[] = 'Tour: ' . $p['tour'];
    }
    // „ex cultura, bestimmt am …" — no colon on purpose: a labelled line
    // would come back through the import as a field nobody has.
    $culture = collection_note($p);
    if ($culture !== '') {
        $parts[] = $culture;
    }
    return implode("\n", $parts);
}

/**
 * The note for KML and GPX, with the find number as one more line. Neither
 * format has a place of its own for it, so it was lost on the way out and
 * back — GeoJSON carries it as a property, CSV as a column. The import
 * takes the line out again (import_fields_from_note), and a „Y-" number
 * there names the find it came from and is dropped.
 */
function export_note_numbered(array $row): string
{
    $number = trim((string) ($row['find_number'] ?? ''));
    return trim($row['note'] . ($number !== '' ? "\nFundnummer: " . $number : ''));
}

/** ISO 8601 in UTC — what GPX and KML prescribe. */
function export_iso_time(?string $foundAt): ?string
{
    if ($foundAt === null || $foundAt === '') {
        return null;
    }
    $when = DateTime::createFromFormat('Y-m-d H:i:s', $foundAt, new DateTimeZone(date_default_timezone_get()));
    if ($when === false) {
        return null;
    }
    return $when->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
}

/**
 * The CSV columns for this stock: the fixed ones, then every find field
 * and custom field that really carries a value here.
 *
 * DATA-DRIVEN, NOT A FIXED LIST: which fields exist depends on the
 * account, and a column set written out here would be wrong for everybody
 * but its author. The find fields lead, in their own order (find_fields.php
 * — Finder before Bestimmer before certainty, a scale, not an alphabet),
 * everything else follows sorted, so two exports of the same stock have
 * the same column order.
 *
 * @return string[] the extra field names, in column order
 */
function export_csv_fields(array $points): array
{
    $present = [];
    foreach ($points as $p) {
        // (array), see export_note: the extra block arrives as an object.
        foreach ((array) ($p['extra'] ?? []) as $name => $value) {
            if (trim((string) $value) !== '') {
                $present[(string) $name] = true;
            }
        }
    }

    $columns = [];
    foreach (find_fields() as $f) {
        if (isset($present[$f['name']])) {
            $columns[] = $f['name'];
            unset($present[$f['name']]);
        }
    }
    $rest = array_keys($present);
    sort($rest, SORT_NATURAL | SORT_FLAG_CASE);
    return array_merge($columns, $rest);
}

/**
 * CSV — the WIDE table, one column per thing.
 *
 * FREELY SHAPED (23.08.2026): "Meine Pilze" has no CSV import,
 * so this file has no foreign format to hit — it is written for the
 * spreadsheet it will be opened in. That is why every find field gets its
 * own column instead of being folded into the note: whoever exports a find
 * stock wants to sort by Bestimmer, count by Substrat, filter by
 * certainty, and none of that works on a text blob.
 *
 * THE CORE COLUMNS STILL CARRY THE NAMES OUR OWN IMPORT RECOGNIZES
 * (CSV_ROLES in csv.php), so the file comes back in without a mapping
 * step; the extra columns land on the note there, which is the import's
 * documented way with columns it cannot place. Free shape and round trip
 * are not in conflict — the first eight columns are the contract, the
 * rest is the convenience.
 *
 * SEMICOLON AND BOM, both for the same reason: this file lands in a German
 * Excel more often than anywhere else. Excel splits on the semicolon in a
 * German locale and reads UTF-8 only with the byte-order mark in front —
 * without it, every umlaut arrives broken, and the user blames the export.
 * Our own reader detects both (csv_read).
 */
function export_csv(array $points): string
{
    $fields = export_csv_fields($points);

    /* WHOSE FIND IT IS — but only when the file really carries several
       people's (23.08.2026: the excursion report). In an
       own-stock export the column would say the same name in every row
       and answer nothing. Data-driven like the field columns. */
    $mixed = false;
    foreach ($points as $p) {
        if (($p['owner'] ?? null) !== null || ($p['owner_name'] ?? null) !== null) {
            $mixed = true;
            break;
        }
    }

    /* THE CULTURE'S COLUMNS (0174), likewise only when the file carries an
       Aufsammlung or something grown from one: the box's number on both,
       „ex cultura" on the species, and the three days — whoever counts
       how long Ascobolus takes on roe-deer dung sorts by them. */
    $cultures = false;
    foreach ($points as $p) {
        if (!empty($p['is_collection']) || ($p['collection_id'] ?? null) !== null) {
            $cultures = true;
            break;
        }
    }

    $out = fopen('php://temp', 'r+');
    fputcsv($out, [
        // The labels of CSV_ROLES — they ARE the recognized names.
        ...array_values(CSV_ROLES),
        ...$fields,
        'Tour', 'Listen', 'MTB', 'Gemeinde', 'Region', 'Land',
        'Schutzgebiet', 'Rote Liste', 'DGfM-Listen',
        ...($mixed ? ['Geteilt von'] : []),
        ...($cultures ? ['Aufsammlung', 'ex cultura', 'In Kultur seit', 'Kultur beendet', 'Bestimmt am'] : []),
    ], ';', '"', '');

    foreach ($points as $p) {
        $extra = (array) ($p['extra'] ?? []);   // an object, see export_note
        $line = [
            (string) ($p['title'] ?? ''),
            // Point as the decimal separator, always: a German Excel would
            // write commas, and our own reader would then have to guess
            // between "50,1" and a thousands separator.
            number_format((float) $p['lat'], 6, '.', ''),
            number_format((float) $p['lon'], 6, '.', ''),
            (string) ($p['found_at'] ?? ''),
            (string) ($p['elevation_m'] ?? ''),
            (string) ($p['accuracy_m'] ?? ''),
            (string) ($p['find_number'] ?? ''),
            // The bare note here — the fields have columns of their own now.
            (string) ($p['note'] ?? ''),
        ];
        foreach ($fields as $name) {
            $line[] = trim((string) ($extra[$name] ?? ''));
        }
        $line[] = (string) ($p['tour'] ?? '');
        $line[] = implode(', ', array_map('strval', $p['list_names'] ?? []));
        // SHEET AND ALL THREE DIGITS — quarter, sixteenth, sixty-fourth.
        // See mtb_full(): the sixteenth and the sixty-fourth are the
        // difference between 5.5 km and 1.4 km of precision, and nobody
        // gets it back out of the file.
        $line[] = mtb_full($p['mtb'] ?? null, $p['mtb_quadrant'] ?? null);
        $line[] = (string) ($p['municipality'] ?? '');
        $line[] = (string) ($p['region'] ?? '');
        $line[] = (string) ($p['country'] ?? '');
        $line[] = (string) ($p['protected_area'] ?? '');
        $line[] = implode(', ', array_map('strval', $p['red_list'] ?? []));
        $line[] = dgfm_marks_words($p['dgfm'] ?? []);
        if ($mixed) {
            $line[] = (string) ($p['owner'] ?? $p['owner_name'] ?? '');
        }
        if ($cultures) {
            $grown = ($p['collection_id'] ?? null) !== null;
            $line[] = !empty($p['is_collection']) ? (string) ($p['collection_number'] ?? '')
                : (string) ($p['collection_parent_number'] ?? '');
            $line[] = $grown ? 'ja' : '';
            // ISO days, like the find time beside them — a sheet sorts them.
            $line[] = (string) (collection_day($p['cultured_at'] ?? null) ?? '');
            $line[] = (string) (collection_day($p['culture_ended_at'] ?? null) ?? '');
            $line[] = (string) (collection_day($p['identified_at'] ?? null) ?? '');
        }

        fputcsv($out, $line, ';', '"', '');
    }
    rewind($out);
    return "\xEF\xBB\xBF" . stream_get_contents($out);
}

/**
 * GeoJSON (RFC 7946) — coordinates as [lon, lat], the elevation as the
 * third number when there is one, and the properties under the names
 * geojson_property() fishes for.
 */
function export_geojson(array $points): string
{
    $features = [];
    foreach ($points as $p) {
        $row = export_row($p);
        $position = [$row['lon'], $row['lat']];
        // Only a real elevation goes along: a padded 0 would be read back
        // as sea level — the very case geojson.php guards against.
        if ($row['elevation_m'] !== null) {
            $position[] = (int) $row['elevation_m'];
        }
        $properties = ['name' => $row['title'], 'description' => $row['note']];
        if ($row['found_at'] !== null)    $properties['time'] = $row['found_at'];
        if ($row['find_number'] !== null) $properties['Fundnummer'] = $row['find_number'];

        $features[] = [
            'type'       => 'Feature',
            'geometry'   => ['type' => 'Point', 'coordinates' => $position],
            'properties' => $properties,
        ];
    }

    return json_encode(['type' => 'FeatureCollection', 'features' => $features],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/** KML — one Placemark per point, coordinates as lon,lat[,ele]. */
function export_kml(array $points, string $title): string
{
    $x = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $body = '';
    foreach ($points as $p) {
        $row = export_row($p);
        $coords = number_format($row['lon'], 6, '.', '') . ','
                . number_format($row['lat'], 6, '.', '')
                . ($row['elevation_m'] !== null ? ',' . (int) $row['elevation_m'] : '');
        $when = export_iso_time($row['found_at']);
        $note = export_note_numbered($row);

        $body .= "    <Placemark>\n"
              .  '      <name>' . $x($row['title'] !== '' ? $row['title'] : 'Ohne Titel') . "</name>\n"
              .  ($note !== '' ? '      <description>' . $x($note) . "</description>\n" : '')
              // TimeStamp is where Google Earth expects the moment; our own
              // reader ignores it, other programs put it on the timeline.
              .  ($when !== null ? "      <TimeStamp><when>$when</when></TimeStamp>\n" : '')
              .  "      <Point><coordinates>$coords</coordinates></Point>\n"
              .  "    </Placemark>\n";
    }

    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<kml xmlns="http://www.opengis.net/kml/2.2">' . "\n"
        . "  <Document>\n"
        . '    <name>' . $x($title) . "</name>\n"
        . $body
        . "  </Document>\n</kml>\n";
}

/** GPX 1.1 — every point a waypoint, which is what gpx_read() takes. */
function export_gpx(array $points, string $title): string
{
    $x = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $body = '';
    foreach ($points as $p) {
        $row = export_row($p);
        $when = export_iso_time($row['found_at']);
        $note = export_note_numbered($row);
        $body .= '  <wpt lat="' . number_format($row['lat'], 6, '.', '')
              .  '" lon="' . number_format($row['lon'], 6, '.', '') . "\">\n"
              .  ($row['elevation_m'] !== null ? '    <ele>' . (int) $row['elevation_m'] . "</ele>\n" : '')
              .  ($when !== null ? "    <time>$when</time>\n" : '')
              .  '    <name>' . $x($row['title'] !== '' ? $row['title'] : 'Ohne Titel') . "</name>\n"
              .  ($note !== '' ? '    <desc>' . $x($note) . "</desc>\n" : '')
              .  "  </wpt>\n";
    }

    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<gpx version="1.1" creator="YASAIMAPP" xmlns="http://www.topografix.com/GPX/1/1">' . "\n"
        . "  <metadata><name>" . $x($title) . "</name></metadata>\n"
        . $body
        . "</gpx>\n";
}

/**
 * The columns a find report can show — key => [label, reader].
 *
 * THE SAME KEYS THE TABLE USES (the catalogue in organize.js): whoever
 * ticks columns for the table has ticked them for the report, and the
 * dialog can offer the same words. Field columns carry the 'field:'
 * prefix, exactly as over there — that is why they are built here from
 * the account's own fields instead of being written out.
 *
 * EVERY FIELD IS OFFERED, whether the current stock fills it or not
 * (23.08.2026: "jede mögliche sonst was Fizzel-Information").
 * The first version built the field columns from the values PRESENT —
 * which meant a ticked "Substrat" quietly disappeared from a report whose
 * finds happened to leave it empty, and the user was left to wonder what
 * they had done wrong. Whoever ticks a column has decided; an empty
 * column is an answer too.
 *
 * @param array[] $points the stock the report covers — for fields that
 *        exist only on foreign points (a container's own field set)
 * @return array<string, array{0: string, 1: callable}>
 */
function report_columns(PDO $pdo, int $userId, array $points): array
{
    $columns = [
        'title'          => ['Titel',        static fn(array $p) => (string) ($p['title'] ?? '')],
        'find_number'    => ['Fund-Nr.',     static fn(array $p) => (string) ($p['find_number'] ?? '')],
        'found_at'       => ['Fundzeit',     static fn(array $p) => report_time($p['found_at'] ?? null)],
        // 'Geteilt von' is the ACCOUNT the find came from — not the
        // Finder field, which stands in this list under its own name.
        'owner'          => ['Geteilt von',  static fn(array $p) => (string) ($p['owner'] ?? '')],
        'lists'          => ['Listen',       static fn(array $p) => implode(', ', $p['list_names'] ?? [])],
        'tour'           => ['Tour',         static fn(array $p) => (string) ($p['tour'] ?? '')],
        'note'           => ['Notiz',        static fn(array $p) => (string) ($p['note'] ?? '')],
        'municipality'   => ['Gemeinde',     static fn(array $p) => (string) ($p['municipality'] ?? '')],
        'region'         => ['Region',       static fn(array $p) => (string) ($p['region'] ?? '')],
        'country'        => ['Land',         static fn(array $p) => (string) ($p['country'] ?? '')],
        // Same notation as the CSV column, same function — a find report
        // goes to the same place a table does.
        'mtb'            => ['MTB',          static fn(array $p)
            => mtb_full($p['mtb'] ?? null, $p['mtb_quadrant'] ?? null)],
        'protected_area' => ['Schutzgebiet', static fn(array $p) => (string) ($p['protected_area'] ?? '')],
        'elevation'      => ['Höhe',         static fn(array $p) => $p['elevation_m'] === null ? '' : $p['elevation_m'] . ' m'],
        'rl'             => ['Rote Liste',   static fn(array $p) => implode(', ', array_map('strval', $p['red_list'] ?? []))],
        'dgfm'           => ['DGfM-Listen',  static fn(array $p) => dgfm_marks_words($p['dgfm'] ?? [])],
        'coordinates'    => ['Koordinaten',  static fn(array $p) => number_format((float) $p['lat'], 5, ',', '')
            . ' / ' . number_format((float) $p['lon'], 5, ',', '')],
    ];
    /* „ex cultura, bestimmt am …" or the Aufsammlung's days (0174) —
       offered only where the stock holds a culture, or every account
       would find a column in its choice that can never say anything. */
    foreach ($points as $p) {
        if (!empty($p['is_collection']) || ($p['collection_id'] ?? null) !== null) {
            $columns['culture'] = ['Kultur', static fn(array $p) => collection_note($p)];
            break;
        }
    }

    foreach (report_field_names($pdo, $userId, $points) as $name) {
        /* (array) AND NOT ?? []: a point without any fields carries the
           empty JSON object, and that arrives here as a stdClass — the
           report died on the first such find with "Cannot use object of
           type stdClass as array". Every reader beside this one already
           guarded against it (export_note, export_csv); this one was
           written a day later and did not. */
        $columns['field:' . $name] = [$name,
            static fn(array $p) => trim((string) (((array) ($p['extra'] ?? []))[$name] ?? ''))];
    }
    return $columns;
}

/**
 * Every field name this account can put in a report — the find set, the
 * own lists' fields, and whatever stands on the points on top of that.
 *
 * The find fields lead in their own order (find_fields.php — Finder before
 * Bestimmer before the certainty, a scale, not an alphabet), the rest
 * follows sorted, so two reports of the same account offer the same order.
 *
 * @return string[]
 */
function report_field_names(PDO $pdo, int $userId, array $points): array
{
    $leading = [];
    foreach (find_fields() as $f) {
        $leading[] = (string) $f['name'];
    }

    $rest = [];
    foreach (lists_load($pdo, $userId) as $list) {
        foreach ($list['fields'] ?? [] as $f) {
            $rest[(string) $f['name']] = true;
        }
    }
    // What only stands on the points — a container's fields belong to
    // their owner, and their definitions never come along.
    foreach ($points as $p) {
        foreach ((array) ($p['extra'] ?? []) as $name => $value) {
            $rest[(string) $name] = true;
        }
    }
    foreach ($leading as $name) {
        unset($rest[$name]);
    }

    $rest = array_keys($rest);
    sort($rest, SORT_NATURAL | SORT_FLAG_CASE);
    return array_merge($leading, $rest);
}

/**
 * The species list's groups: finds of the same species as one entry.
 *
 * ONE ENTRY PER SPECIES, WHATEVER KEY A FIND CARRIES
 * (12.09.2026, twice on the same day). The first version merged by taxon
 * reference and let name-only finds join a referenced group of the same
 * name — and still printed a species twice when two people had picked
 * it from different directories: two references, one fungus. „Eigentlich
 * soll das ja eine Artenliste sein — jede Art wird nur einmal
 * aufgeführt."
 *
 * So two finds are the same species when they share a REFERENCE or share
 * a NAME, and the relation carries over: A has the DGfM reference and the
 * name, B the same name and another list's reference, C only B's
 * reference — all three are one entry. The reference still folds what
 * the name cannot (spellings, synonyms); the name folds what the
 * references cannot (the same species picked from two lists, or typed
 * by a guest who had no list at all). A typo is a different name and
 * stays a different entry — no fuzziness, as before.
 *
 * @return array<int, array> groups, each a list of points, in first-seen order
 */
function report_merge_groups(array $points): array
{
    $n = count($points);
    $parent = range(0, max(0, $n - 1));
    $find = static function (int $i) use (&$parent, &$find): int {
        while ($parent[$i] !== $i) {
            $parent[$i] = $parent[$parent[$i]];
            $i = $parent[$i];
        }
        return $i;
    };
    $union = static function (int $a, int $b) use (&$parent, $find): void {
        $a = $find($a);
        $b = $find($b);
        // The earlier one stays the root, so the group keeps its first-seen place.
        if ($a !== $b) {
            $parent[max($a, $b)] = min($a, $b);
        }
    };

    $byRef  = [];
    $byName = [];
    foreach ($points as $i => $p) {
        foreach (report_species_refs($p) as $ref) {
            if (isset($byRef[$ref])) {
                $union($i, $byRef[$ref]);
            } else {
                $byRef[$ref] = $i;
            }
        }
        $name = report_title_key($p);
        if ($name !== null) {
            if (isset($byName[$name])) {
                $union($i, $byName[$name]);
            } else {
                $byName[$name] = $i;
            }
        }
    }

    $groups = [];
    foreach ($points as $i => $p) {
        $groups[$find($i)][] = $p;
    }
    ksort($groups);
    return array_values($groups);
}

/** Every taxon reference a find carries, as keys. */
function report_species_refs(array $p): array
{
    $out = [];
    $references = json_decode((string) ($p['extra_species'] ?? ''), true);
    if (is_array($references) && is_array($references[''] ?? null)) {
        foreach ($references[''] as $reference) {
            $reference = trim((string) $reference);
            if ($reference !== '') {
                $out[] = 'ref:' . $reference;
            }
        }
    }
    return $out;
}

/** The name a find is typed as, folded for comparison — or null without one. */
function report_title_key(array $p): ?string
{
    $title = preg_replace('/\s+/u', ' ', trim((string) ($p['title'] ?? '')));
    return ($title ?? '') === '' ? null : mb_strtolower($title);
}

/** "14.08.2026 09:20" — the clock only when there is one. */
function report_time(?string $foundAt): string
{
    if ($foundAt === null || $foundAt === '') {
        return '';
    }
    [$date, $clock] = array_pad(explode(' ', $foundAt), 2, '');
    [$y, $m, $d]    = array_pad(explode('-', $date), 3, '');
    return "$d.$m.$y" . ($clock !== '' && $clock !== '00:00:00' ? ' ' . substr($clock, 0, 5) : '');
}

/**
 * The report's headline: WHERE the finds are from.
 *
 * Municipalities and map sheets of the covered stock
 * (23.08.2026) — that is what names an excursion, better than any title
 * one would have to type. Several of each are listed, many are cut off
 * with an ellipsis: a heading is a heading, not an index.
 *
 * @return array{area: string, sheets: string, span: string}
 */
function report_heading(array $points): array
{
    $places = [];
    $sheets = [];
    $days   = [];
    foreach ($points as $p) {
        $place = trim((string) ($p['municipality'] ?? ''));
        if ($place !== '') {
            $places[$place] = true;
        }
        if (($p['mtb'] ?? null) !== null) {
            $sheets[(string) $p['mtb']] = true;
        }
        $day = substr((string) ($p['found_at'] ?? $p['created_at'] ?? ''), 0, 10);
        if ($day !== '') {
            $days[$day] = true;
        }
    }
    $shorten = static function (array $keys, int $most): string {
        $names = array_keys($keys);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        $shown = array_slice($names, 0, $most);
        return implode(', ', $shown) . (count($names) > $most ? ' …' : '');
    };

    $days = array_keys($days);
    sort($days);
    $span = '';
    if ($days !== []) {
        $first = report_time($days[0] . ' 00:00:00');
        $last  = report_time(end($days) . ' 00:00:00');
        $span  = $first === $last ? $first : "$first – $last";
    }

    return [
        'area'   => $shorten($places, 4),
        'sheets' => $shorten($sheets, 6),
        'span'   => $span,
    ];
}

/**
 * One file, ready to hand over.
 *
 * @return array{name: string, mime: string, body: string}
 */
/**
 * @param array $options  for 'mykis': 'holder' — the exporting account as
 *        „Nachname, Vorname", for finds whose finder field is empty —,
 *        'precision', the account's own step (MYKIS_PRECISIONS), and
 *        'self', mykis_self() of the account — without it the finds of
 *        other finders come along
 */
function export_build(array $points, string $format, string $title = 'YASAIMAPP', array $options = []): array
{
    if (!isset(EXPORT_FORMATS[$format])) {
        throw new InvalidArgumentException('Dieses Format gibt es nicht.');
    }
    [, $extension, $mime] = EXPORT_FORMATS[$format];

    $body = match ($format) {
        'csv'     => export_csv($points),
        'geojson' => export_geojson($points),
        'kml'     => export_kml($points, $title),
        'gpx'     => export_gpx($points, $title),
        'mykis'   => mykis_csv(mykis_rows($points, (string) ($options['holder'] ?? ''),
                                          $options['precision'] ?? null, $options['self'] ?? null,
                                          $options['rules'] ?? null)),
    };

    // A file name that survives every operating system: letters, digits,
    // dash. The date makes two exports of the same stock distinguishable
    // in the download folder, which is where they pile up. The MykIS sheet
    // says what it is for — it lands in a coordinator's inbox, not ours.
    $stem = preg_replace('/[^A-Za-z0-9-]+/', '-', $format === 'mykis' ? 'MykIS' : $title) ?: 'karte';
    return [
        'name' => trim($stem, '-') . '-' . date('Y-m-d') . '.' . $extension,
        'mime' => $mime,
        'body' => $body,
    ];
}
