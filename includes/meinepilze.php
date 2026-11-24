<?php
/**
 * Reading "Meine Pilze" (app) — one CSV per export.
 *
 * What the app writes: a semicolon CSV with the columns
 *
 *   Datum;Zeit;RefNr;Wiss.Name;Autor;Deut.Name;Finder;Bestimmer;Bemerkung;
 *   Breitengrad;Laengengrad;Genauigkeit;MTB
 *
 * and that in WINDOWS-1252, not UTF-8 — otherwise "Schleimrübling" turns
 * into character salad. Coordinates with a COMMA as the decimal separator,
 * the date with a two-digit year (08.09.24).
 *
 * WHAT GETS TAKEN OVER and what does not:
 *
 *   Wiss.Name        becomes the point's title — and is resolved against
 *                    the own name list (species_reference), so the find carries
 *                    its identifier for merging synonyms. The DGFM number
 *                    first, GBIF as the fallback option.
 *   Deut.Name        is IGNORED: German names come from the own taxonomy,
 *                    not from the app — two sources for the same name
 *                    drifted apart.
 *   Datum + Zeit     become the find time. Unlike with Takeout there ARE
 *                    real times here.
 *   Genauigkeit      in meters, as the app took it from the receiver.
 *   Finder,
 *   Bestimmer        go into the FIND FIELDS of the same name (since they
 *                    exist — includes/find_fields.php): the import list is
 *                    a species list, there the fields are present and
 *                    analyzable. An empty Finder becomes the importer —
 *                    the same prefill as when placing a point, and in the
 *                    app it was likewise the app user themselves.
 *   Bemerkung        becomes the note, alone.
 *   MTB              is IGNORED: the application computes the map sheet
 *                    itself from the coordinates (one rule, one place).
 *   RefNr            is IGNORED, and it is NO FIND NUMBER
 *                    (25.09.2026): it is the app's reference number of the
 *                    SPECIES, the folder it files one's own pictures of
 *                    that species under. Two finds of the same species
 *                    carry the same one — as a find number it would
 *                    collide on the second find.
 */

declare(strict_types=1);

/**
 * Read a Meine-Pilze CSV.
 *
 * @return array{points: array[], withoutPosition: array[], skipped: int}
 */
function pilze_read(string $path): array
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException(t('Datei nicht lesbar: %s', basename($path)));
    }

    // The app writes Windows-1252. Should clean UTF-8 arrive after all
    // (someone pulled the file through an editor), it stays untouched —
    // the check tells the two apart reliably, because 1252 umlauts are
    // invalid sequences in UTF-8.
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
    }
    $content = (string) preg_replace('~^\xEF\xBB\xBF~', '', $content);

    $rows = [];
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $content);
    rewind($handle);
    while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
        $rows[] = $row;
    }
    fclose($handle);

    $columns = array_map('trim', $rows[0] ?? []);
    if (!in_array('Wiss.Name', $columns, true) || !in_array('Breitengrad', $columns, true)) {
        throw new RuntimeException(
            'Das sieht nicht nach einer Ausfuhr von „Meine Pilze“ aus — '
            . 'die Spalten „Wiss.Name“ und „Breitengrad“ fehlen.');
    }

    $points = [];
    $withoutPosition = [];
    $skipped = 0;

    foreach (array_slice($rows, 1) as $row) {
        if (!array_filter($row, static fn($c) => trim((string) $c) !== '')) {
            continue;   // blank line
        }
        $e = [];
        foreach ($columns as $i => $column) {
            $e[$column] = trim((string) ($row[$i] ?? ''));
        }

        $title = $e['Wiss.Name'] ?? '';
        if ($title === '') {
            // A find row without a species is none — count, do not guess.
            $skipped++;
            continue;
        }

        $entry = [
            'title'         => $title,
            'note'         => ($e['Bemerkung'] ?? '') !== '' ? $e['Bemerkung'] : null,
            'finder'        => $e['Finder'] ?? '',
            'bestimmer'     => $e['Bestimmer'] ?? '',
            'found_at'   => pilze_datetime($e['Datum'] ?? '', $e['Zeit'] ?? ''),
            'accuracy_m' => ctype_digit($e['Genauigkeit'] ?? '') ? (int) $e['Genauigkeit'] : null,
        ];

        $lat = pilze_number($e['Breitengrad'] ?? '');
        $lon = pilze_number($e['Laengengrad'] ?? '');
        if ($lat === null || $lon === null || abs($lat) > 90 || abs($lon) > 180) {
            $withoutPosition[] = $entry;
            continue;
        }

        $points[] = $entry + ['lat' => $lat, 'lon' => $lon];
    }

    return ['points' => $points, 'withoutPosition' => $withoutPosition, 'skipped' => $skipped];
}

/** A number with comma OR point as decimal separator — the app writes comma. */
function pilze_number(string $value): ?float
{
    $value = str_replace(',', '.', $value);
    return is_numeric($value) ? (float) $value : null;
}

/**
 * The app's date and time as a database timestamp.
 *
 * Two-digit years land in the 21st century — the app has not existed long
 * enough for 97 to ever mean 1997. If the time is missing, it stays at
 * the start of the day; if the date is missing, there is no find time and
 * NOTHING is invented, as with Takeout.
 */
function pilze_datetime(string $date, string $time): ?string
{
    if (!preg_match('~^(\d{1,2})\.(\d{1,2})\.(\d{2}(?:\d{2})?)$~', $date, $t)) {
        return null;
    }
    $year = (int) $t[3] + (strlen($t[3]) === 2 ? 2000 : 0);
    if (!checkdate((int) $t[2], (int) $t[1], $year)) {
        return null;
    }

    $clock = preg_match('~^(\d{1,2}):(\d{2})(?::(\d{2}))?$~', $time, $u)
        ? sprintf('%02d:%02d:%02d', min(23, (int) $u[1]), (int) $u[2], (int) ($u[3] ?? 0))
        : '00:00:00';

    return sprintf('%04d-%02d-%02d %s', $year, (int) $t[2], (int) $t[1], $clock);
}


/**
 * Apply a parsed export or dry-run it — the same two-step mechanic as
 * with Takeout, and for the same reasons (see takeout_import).
 *
 * EVERYTHING INTO THE IMPORT LIST "Import <Datum>" (migration 0045), with
 * is_species: then the title counts as a find species, and the species
 * table and the genus search take hold. Unlike with Takeout there are no
 * sublists — the app knows none; the import list itself is the leaf.
 *
 * THE NAME REFERENCE is created on insert: first the DGFM reference
 * ('fungi'), otherwise GBIF — via the same species_reference() route that
 * typing takes too. What is in no name list gets created anyway (the
 * name is right there, after all) and is named in the report as "ohne
 * Namensverweis".
 *
 * WHAT THE HUMAN UNTICKED IS ALREADY GONE when this runs: the
 * selection is applied to $data before the call (import_fungi_selection
 * in import_content.php), not here. A find left out is then simply not
 * in $data['points'] any more — this function never learns of it, and
 * therefore cannot count it into the report by accident.
 *
 * @return array{import: string, created: int, assigned: int,
 *               withoutReference: array[], withoutPosition: array[], skipped: int}
 */
function pilze_import(PDO $pdo, int $userId, array $data, bool $apply,
                          ?string $name = null, array $post = []): array
{
    require_once __DIR__ . '/takeout.php';   // takeout_key, takeout_list_add
    require_once __DIR__ . '/imports.php';

    /* THE TRASH AND OTHER PEOPLE'S FINDS stay out, and the preview says
       which and why (includes/import_screen.php). */
    require_once __DIR__ . '/import_screen.php';
    $screen = import_screen_start($pdo, $userId, $post);
    import_screen_prepare($screen, $data['points']);

    $import  = $apply ? import_create($pdo, $userId, 'fungi', rootIsSpecies: true, desiredName: $name) : null;
    $listId = $import['list_id'] ?? null;

    // For the empty Finder: whoever imports has found — the same prefill
    // as when placing a point on the map, with first and last name: a
    // find belongs to a person, not to a login handle. The nick remains
    // the fallback when no name is on file.
    $stmt = $pdo->prepare('SELECT first_name, last_name, email FROM map_users WHERE id = ?');
    $stmt->execute([$userId]);
    $user   = $stmt->fetch() ?: [];
    $importer = user_full_name($user) !== ''
        ? user_full_name($user) : user_display_name($user);

    $report = ['import' => $import['name'] ?? import_name($pdo, $userId),
                'import_id' => $import['id'] ?? null,
                'created' => 0, 'assigned' => 0,
                'withoutReference' => [], 'withoutPosition' => $data['withoutPosition'],
                'skipped' => $data['skipped']];

    foreach ($data['points'] as $p) {
        $p = import_fields($p);
        /* ONE VERDICT PER ENTRY, the decisions of the preview applied
           (import_screen_row): skip it, file a find here already, or create. */
        $verdict = import_screen_row($pdo, $screen, $p, $apply);
        if ($verdict['action'] === 'skip') {
            continue;
        }
        $key = takeout_key((string) $p['title'], (float) $p['lat'], (float) $p['lon']);
        if ($verdict['action'] === 'existing') {
            $report['assigned']++;
            if ($apply && $listId !== null && $verdict['point_id'] > 0) {
                takeout_list_add($pdo, $userId, $verdict['point_id'], $listId);
            }
            continue;
        }
        $p = $verdict['p'];

        // The source of the name reference — the dry run computes it too,
        // so "ohne Namensverweis" already shows in the preview.
        $source = null;
        foreach (['fungi', 'gbif'] as $q) {
            if (species_reference($pdo, (string) $p['title'], $q) !== null) {
                $source = $q;
                break;
            }
        }
        if ($source === null) {
            /* THE KEY RIDES ALONG so the preview can offer a checkbox per
               find (25.08.2026). The title alone would not do
               it: the same wrong name may stand on two finds at two
               places, and unticking one of them must not take the other
               with it. takeout_key is title plus position — the same
               identity by which duplicates are recognised above. */
            $report['withoutReference'][] = ['title' => $p['title'], 'found_at' => $p['found_at'],
                                        'key' => $key];
        }

        $report['created']++;
        import_screen_created($screen, $p);
        if ($apply) {
            // The title rides along as the species under the EMPTY field
            // name — exactly there point_species_set() reads it back out.
            // The empty name does not make it into the stored extra
            // block, point_extra_write() throws it out. Finder and
            // Bestimmer go into the find fields of the same name — the
            // import list is a species list, there they exist.
            $extra = [
                'Finder'    => trim((string) $p['finder']) !== '' ? $p['finder'] : $importer,
                'Bestimmer' => $p['bestimmer'],
            ];
            if ($source !== null) {
                $extra[''] = $p['title'];
            }
            // Fields the note brought (import_fields) join — the app's own
            // finder and determiner above win.
            $extra += (array) ($p['extra'] ?? []);
            $created = point_create($pdo, $userId, [
                'lat'           => $p['lat'],
                'lon'           => $p['lon'],
                'title'         => $p['title'],
                'note'         => $p['note'],
                'found_at'   => $p['found_at'],
                'accuracy_m' => $p['accuracy_m'],
                'find_number' => $p['find_number'] ?? null,
                'find_number_draw' => $p['find_number_draw'] ?? false,
                'import_id'     => $import['id'],
                'extra'        => $extra,
                'extra_sources' => $source !== null ? ['' => [$p['title'] => $source]] : [],
                'lists'        => $listId !== null ? [$listId] : [],
            ]);
        import_screen_made($screen, $p, $created);
        } else {
            import_screen_made($screen, $p, 0);
        }
    }

    if ($apply) {
        import_finish($pdo, $import['id'], $report['created'], $report['assigned']);
    }

    $report['screen'] = import_screen_report($screen);
    return $report;
}
