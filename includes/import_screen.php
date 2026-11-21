<?php
/**
 * What an import must not bring in a second time — the screening every
 * importer asks before its own „already there or new".
 *
 * 25.09.2026, asked what re-importing one's own export does.
 * Own points were never doubled (title and position to six places,
 * takeout_key), but two gaps stood open, and he wanted both closed:
 *
 *   THE TRASH — a point lying there was not compared, so the file brought
 *   it back as a new one. Now a point that matches one in the trash stays
 *   out: whoever threw it away does not want it slipped back in.
 *
 *   OTHER PEOPLE'S FINDS — an export carries the finds of containers,
 *   excursions and co-ownership too, and the import compared only with
 *   one's own points, so they came back as one's own copies. Now the
 *   finder decides („wenn der Finder nicht der eigene Name ist, ist es
 *   nicht ein eigener Fund"): our exports write it into the note as
 *   „Finder: …" (the CSV as a column, which the import turns into that
 *   same line). No finder, or one's own name among the finders: imported
 *   as ever. Someone else only: left out — and the preview names the
 *   finders it met with their counts, and says how to fix a spelling of
 *   one's own name the matching did not recognise („dann sagt dem
 *   Benutzer, bitte ändere den Finder so und so"). The finds where one
 *   is entered as the DETERMINER are asked about the same way („in einer
 *   extra Abfrage erst vom Nutzer bestätigen lassen").
 *
 * ONE'S OWN NAME IN ANY USUAL SPELLING counts: case, the order of first
 * and last name, a comma between them, umlauts written out („Schroeder").
 * An initial does not — „S. Schröder" may be somebody else of the
 * family, and the fix is one edit in the file. The rule lives in
 * person_names.php, because the DGfM delivery asks the same question of
 * the same field.
 *
 * AND THE FIND NUMBERS COME ALONG (25.09.2026: „beim Import
 * von alten Punkten sollten sie natürlich auch irgendwie die Möglichkeit
 * bekommen, ihre alten Fundnummern wiederherzustellen"). A number the file
 * brings — a CSV column, GeoJSON's property, the „Fundnummer:" line our
 * KML and GPX write — stays the find's number, and a find that is already
 * here WITHOUT one gets it from the file: whoever imported without the
 * numbers maps the column and imports the same file again. A number is
 * never overwritten, and the preview says what differs. Numbers another
 * find already carries are named before applying, since two vouchers of
 * the same number are a fact the file cannot settle. The counter goes on
 * above whatever came in (find_number_mint). SOMEBODY ELSE'S NUMBER is
 * no number of mine: it belongs to the finder's own collection, so on a
 * find of another finder it stays text in the note („Fundnummer von
 * Volker Maier: 0543") instead of running into my series.
 *
 * EVERY ENTRY IS JUDGED ON ITS OWN, AND ASKED ABOUT WHERE IT MUST BE
 * (25./26.09.2026, over eight drafts: „welcher Punkt angelegt
 * würde und welcher verworfen, sehe ich gar nicht … das muss beim Fund
 * stehen … ich möchte gleich eine Lösung mit angeboten bekommen … so ein
 * Import muss leicht laufen"). import_screen_row() gives each entry its
 * verdict and keeps it for the preview's table — new, here already, gets
 * its number, in the trash — and the conflicts with the way out beside
 * them: another finder (a tick, or all of that finder's at once), the
 * user as determiner (the same, and a number of their own series if the
 * voucher lies with them), a number another find carries (the next free
 * number, one typed by hand, or none), another number than the find here
 * has (keep or take), and „vielleicht umbenannt" (below). The per-finder
 * box and the one question for all determined finds that stood under the
 * preview went; everything is decided at the entry.
 *
 * AND THE FIELDS GO BACK INTO THE FIELDS (same day: „so wie der
 * Import momentan funktioniert, scheint er falsch zu funktionieren").
 * Every importer but Meine Pilze used to pour Finder, Bestimmer and the
 * other find fields into the note as text — the point then had no finder,
 * and wherever an empty finder falls back to the account holder, the
 * holder became the finder: in the DGfM delivery too (mykis_row: sammler).
 * Whoever took over Volker's finds would have sent them under their own
 * name. import_fields() takes them out of the note again — the lines our
 * export writes (export_note) and a CSV column becomes on the way in —
 * and a file that brings find fields makes its import list a species list,
 * where those fields exist (import_screen_species).
 */

declare(strict_types=1);

require_once __DIR__ . '/takeout.php';   // takeout_key
require_once __DIR__ . '/person_names.php';

/**
 * Set up the screening for one import run.
 *
 * THE DECISIONS OF THE PREVIEW come in with the apply form, one per entry
 * of the file, keyed by the entry's place in it — import_screen_row()
 * counts that place, and the preview and the apply run count alike,
 * reading the same file in the same order:
 *
 *   take[]              entries of another finder, or determined by the
 *                       user, to bring in
 *   twice[i]            next | own | none — a number another find carries
 *   twice_number[i]     the number typed for 'own'
 *   det_number[i]       next | none — a number of the user's own series for
 *                       a find they determined
 *   keep[i]             stored | file — a find here already, the file with
 *                       another number
 *   renamed[i]          rename | keep | new | skip — perhaps a find here
 *                       already, under a new name
 *   renamed_number[i]   stored | file — its number, when it is that find
 *
 * In the preview nothing is decided yet, so everything is asked. Values
 * outside these words count as no answer.
 */
function import_screen_start(PDO $pdo, int $userId, array $post = []): array
{
    require_once __DIR__ . '/find_number.php';
    $pattern = find_number_pattern($pdo, $userId);
    $existing = [];   // key => id of the account's own finds on the map
    $numbers = [];    // id => its stored number, null when it has none
    $given = [];      // find_number_key() => the find carrying it (title, find time), trash included
    $spots = [];      // 'lat|lon' => the own finds lying exactly there, for „vielleicht umbenannt"
    foreach (points_load($pdo, $userId) as $p) {
        $key = takeout_key((string) $p['title'], (float) $p['lat'], (float) $p['lon']);
        $id = (int) $p['id'];
        $existing[$key] = $id;
        $number = trim((string) ($p['find_number'] ?? ''));
        $numbers[$id] = $number === '' ? null : $number;
        if ($number !== '') {
            $given[find_number_key($pattern, $number)] ??= ['title' => (string) $p['title'], 'found_at' => $p['found_at'] ?? null];
        }
        $spots[import_screen_spot($p)][] = ['id' => $id, 'title' => (string) $p['title'], 'key' => $key,
            'number' => $numbers[$id], 'found_at' => $p['found_at'] ?? null,
            'minute' => substr((string) ($p['found_at'] ?? ''), 0, 16)];
    }
    $trash = [];
    foreach (points_load($pdo, $userId, ['trash' => true]) as $p) {
        $trash[takeout_key((string) $p['title'], (float) $p['lat'], (float) $p['lon'])] = true;
        $number = trim((string) ($p['find_number'] ?? ''));
        if ($number !== '') {
            $given[find_number_key($pattern, $number)] ??= ['title' => (string) $p['title'], 'found_at' => $p['found_at'] ?? null,
                                                            'trash' => true];
        }
    }
    $stmt = $pdo->prepare('SELECT first_name, last_name FROM map_users WHERE id = ?');
    $stmt->execute([$userId]);
    $me = $stmt->fetch() ?: ['first_name' => '', 'last_name' => ''];
    $name = trim(trim((string) $me['first_name']) . ' ' . trim((string) $me['last_name']));

    $pick = static function (string $field, array $words) use ($post): array {
        $out = [];
        foreach ((array) ($post[$field] ?? []) as $i => $value) {
            if (in_array((string) $value, $words, true)) {
                $out[(int) $i] = (string) $value;
            }
        }
        return $out;
    };
    $take = [];
    foreach ((array) ($post['take'] ?? []) as $i) {
        $take[(int) $i] = true;
    }
    $typed = [];
    foreach ((array) ($post['twice_number'] ?? []) as $i => $value) {
        $typed[(int) $i] = trim((string) $value);
    }

    return ['user_id' => $userId, 'existing' => $existing, 'numbers' => $numbers, 'given' => $given,
            'spots' => $spots, 'trash' => $trash, 'name' => $name, 'self' => person_name_tokens($name),
            'pattern' => $pattern,
            'take' => $take, 'twice' => $pick('twice', ['next', 'own', 'none']), 'twice_number' => $typed,
            'det_number' => $pick('det_number', ['next', 'none']), 'keep' => $pick('keep', ['stored', 'file']),
            'renamed' => $pick('renamed', ['rename', 'keep', 'new', 'skip']),
            'renamed_number' => $pick('renamed_number', ['stored', 'file']),
            'index' => 0, 'rows' => [], 'counts' => [], 'missing' => 0, 'file_keys' => [], 'claimed' => [],
            'proposed' => [], 'run_numbers' => [], 'fields' => 0];
}

/** Where a find lies, to six places — the spot „vielleicht umbenannt" compares. */
function import_screen_spot(array $p): string
{
    return number_format((float) $p['lat'], 6, '.', '') . '|' . number_format((float) $p['lon'], 6, '.', '');
}

/**
 * The whole file once, before the first entry is judged: which names lie
 * where in it. „Vielleicht umbenannt" offers a find here only when ITS
 * name is gone from the file at that spot — while the file still carries
 * it, the entry under a new name is a second species at the same tree.
 */
function import_screen_prepare(array &$screen, array $points): void
{
    foreach ($points as $p) {
        $screen['file_keys'][takeout_key((string) $p['title'], (float) $p['lat'], (float) $p['lon'])] = true;
    }
}

/**
 * The verdict on one entry of the file — what happens to it, with the
 * user's decisions applied, and the entry kept for the preview's table.
 *
 * @return array{action: string, p: array, point_id: ?int}  'create' (the
 *         caller creates $p), 'existing' (a find here already: point_id)
 *         or 'skip'
 */
function import_screen_row(PDO $pdo, array &$screen, array $p, bool $forReal): array
{
    $i = $screen['index']++;
    $key = takeout_key((string) $p['title'], (float) $p['lat'], (float) $p['lon']);
    /* THE FILE'S NUMBER IN THE SERIES' SPELLING where it has the series'
       shape — „wk-1" comes in as wk-00001, as the point window stores a
       typed one (review of 26.09.2026: verbatim, it sat beside a stored
       „wk-00001" unnoticed). Anything of another shape stays as the file
       writes it. */
    $number = import_number_of($p);
    if ($number !== null && $screen['pattern'] !== null) {
        $number = find_number_normalize($screen['pattern'], $number);
    }
    $row = ['i' => $i, 'row' => (int) ($p['_row'] ?? $i + 1), 'title' => (string) $p['title'],
            'lat' => (float) $p['lat'], 'lon' => (float) $p['lon'], 'found_at' => $p['found_at'] ?? null,
            'number' => $number, 'finder' => import_person($p, 'Finder'), 'note' => $p['note'] ?? null,
            'extra' => (array) ($p['extra'] ?? []), 'verdict' => 'new', 'kinds' => []];

    /* ONE'S OWN FIND, ALREADY HERE — whoever found it, it is no copy. A
       missing number comes from the file (never overwritten on its own);
       another number is asked about. The number of somebody else's entry
       is theirs and fills nothing. */
    if (isset($screen['existing'][$key])) {
        $id = $screen['existing'][$key];
        $row['verdict'] = 'there';
        if ($number !== null && import_screen_own($screen, $p) && array_key_exists($id, $screen['numbers'])) {
            $stored = $screen['numbers'][$id];
            if ($stored === null) {
                $row['verdict'] = 'filled';
                import_screen_set_number($pdo, $screen, $id, $number, $p, $forReal, false);
            } elseif (import_screen_key($screen, $stored) !== import_screen_key($screen, $number)) {
                $row['kinds'][] = 'number';
                $row['stored'] = $stored;
                if (($screen['keep'][$i] ?? 'stored') === 'file') {
                    import_screen_set_number($pdo, $screen, $id, $number, $p, $forReal, true);
                }
            }
        }
        import_screen_record($screen, $row, $forReal);
        return ['action' => 'existing', 'p' => $p, 'point_id' => $id];
    }

    // Thrown away once: whoever did it does not want it slipped back in.
    if (isset($screen['trash'][$key])) {
        $row['verdict'] = 'trash';
        import_screen_record($screen, $row, $forReal);
        return ['action' => 'skip', 'p' => $p, 'point_id' => null];
    }

    /* SOMEBODY ELSE'S FIND — or one the user determined — comes in only
       with its tick, and its finder stays the finder. Its number belongs
       to the finder's collection: it goes into the note, and a number of
       the user's own series only where they ask for one (the voucher lies
       with them). */
    if (!import_screen_own($screen, $p)) {
        $determiner = import_person($p, 'Bestimmer');
        $kind = $determiner !== '' && $screen['self'] !== [] && person_names_self($determiner, $screen['self'])
            ? 'determiner' : 'finder';
        $row['kinds'][] = $kind;
        if ($number !== null) {
            $p['note'] = trim(trim((string) ($p['note'] ?? '')) . "\n"
                . 'Fundnummer von ' . $row['finder'] . ': ' . $number);
        }
        $p['find_number'] = null;
        if (!isset($screen['take'][$i])) {
            import_screen_record($screen, $row, $forReal);
            return ['action' => 'skip', 'p' => $p, 'point_id' => null];
        }
        if ($kind === 'determiner' && $screen['pattern'] !== null
            && ($screen['det_number'][$i] ?? 'none') === 'next') {
            $p['find_number_draw'] = true;
            import_screen_propose($pdo, $screen, $p['found_at'] ?? null);
        }
        import_screen_record($screen, $row, $forReal);
        return ['action' => 'create', 'p' => $p, 'point_id' => null];
    }

    /* PERHAPS A FIND HERE ALREADY, UNDER A NEW NAME — the species
       corrected, an „s. l." taken off, and a second find would stand
       beside the first. Asked, never guessed, and nothing is preselected
       (26.09.2026): „eine Auswahl muss man treffen". */
    $candidate = import_screen_candidate($screen, $p, $key, $number);
    if ($candidate !== null) {
        $row['kinds'][] = 'renamed';
        $row['candidate'] = ['title' => $candidate['title'], 'number' => $candidate['number'],
                             'found_at' => $candidate['found_at']];
        $screen['claimed'][$candidate['id']] = true;
        // For „Neuen Fund anlegen": is the file's number free, or which
        // one would the series give instead?
        if ($number !== null) {
            $row['new_carrier'] = $screen['given'][import_screen_key($screen, $number)] ?? null;
            if ($row['new_carrier'] !== null && $screen['pattern'] !== null) {
                $row['new_proposal'] = import_screen_peek($pdo, $screen, $p['found_at'] ?? null);
            }
        }
        $choice = $screen['renamed'][$i] ?? null;
        if ($choice === null) {
            $screen['missing']++;
            if (!$forReal) {
                import_screen_record($screen, $row, $forReal);
                return ['action' => 'skip', 'p' => $p, 'point_id' => null];
            }
            // A run without the preview (tests, the command line): the
            // behaviour before the question existed.
            $choice = 'new';
        }
        if ($choice === 'skip') {
            import_screen_record($screen, $row, $forReal);
            return ['action' => 'skip', 'p' => $p, 'point_id' => null];
        }
        if ($choice === 'rename' || $choice === 'keep') {
            $id = $candidate['id'];
            if ($choice === 'rename' && $forReal) {
                point_update($pdo, $screen['user_id'], $id, ['title' => (string) $p['title']]);
            }
            if ($number !== null) {
                if ($candidate['number'] === null) {
                    import_screen_set_number($pdo, $screen, $id, $number, $p, $forReal, false);
                } elseif (import_screen_key($screen, $candidate['number']) !== import_screen_key($screen, $number)) {
                    $row['kinds'][] = 'number';
                    $row['stored'] = $candidate['number'];
                    if (($screen['renamed_number'][$i] ?? 'stored') === 'file') {
                        import_screen_set_number($pdo, $screen, $id, $number, $p, $forReal, true);
                    }
                }
            }
            import_screen_record($screen, $row, $forReal);
            return ['action' => 'existing', 'p' => $p, 'point_id' => $id];
        }
        // 'new': a find of its own — on to its number, like any new one.
    }

    /* A NEW FIND OF THE USER'S — with the number the file brings, unless
       another find carries it already: then the next free number of the
       series (preselected where there is a pattern), one typed by hand,
       or none. */
    $p['find_number'] = null;
    if ($number !== null) {
        $carrier = $screen['given'][import_screen_key($screen, $number)] ?? null;
        if ($carrier === null) {
            $p['find_number'] = $number;
        } else {
            $row['kinds'][] = 'twice';
            $row['carrier'] = $carrier;
            $way = $screen['twice'][$i] ?? ($screen['pattern'] !== null ? 'next' : 'none');
            if ($way === 'next' && $screen['pattern'] !== null) {
                $p['find_number_draw'] = true;
                $row['proposal'] = import_screen_propose($pdo, $screen, $p['found_at'] ?? null);
            } elseif ($way === 'own') {
                $typed = mb_substr($screen['twice_number'][$i] ?? '', 0, 80);
                if ($typed !== '' && !find_number_reserved($typed)) {
                    $p['find_number'] = $screen['pattern'] !== null ? find_number_normalize($screen['pattern'], $typed) : $typed;
                }
            }
            if ($screen['pattern'] !== null && !isset($row['proposal'])) {
                // what „nächste freie Nummer" would give, shown beside the choice
                $row['proposal'] = import_screen_peek($pdo, $screen, $p['found_at'] ?? null);
            }
        }
    }
    import_screen_record($screen, $row, $forReal);
    return ['action' => 'create', 'p' => $p, 'point_id' => null];
}

/**
 * A created find, entered into the run's memory: a later entry with the
 * same title and spot is „schon da", and its number is given.
 */
function import_screen_made(array &$screen, array $p, int $pointId): void
{
    $screen['existing'][takeout_key((string) $p['title'], (float) $p['lat'], (float) $p['lon'])] = $pointId;
    $number = trim((string) ($p['find_number'] ?? ''));
    if ($number !== '') {
        $screen['given'][import_screen_key($screen, $number)] ??= ['title' => (string) $p['title'], 'found_at' => $p['found_at'] ?? null,
                                                                   'file' => true];
        $screen['run_numbers'][] = $number;
    }
}

/**
 * The find of the account „vielleicht umbenannt" offers for an entry —
 * at exactly its spot, not claimed by another entry, its own name gone
 * from the file there, and either carrying the entry's number or found in
 * the same minute. Two finds at one tree in the same minute under two
 * names are near impossible from a phone; from an app that
 * copies the spot, the vanished old name keeps two species apart.
 */
function import_screen_candidate(array $screen, array $p, string $key, ?string $number): ?array
{
    $minute = substr((string) ($p['found_at'] ?? ''), 0, 16);
    foreach ($screen['spots'][import_screen_spot($p)] ?? [] as $there) {
        if ($there['key'] === $key || isset($screen['claimed'][$there['id']]) || isset($screen['file_keys'][$there['key']])) {
            continue;
        }
        $sameNumber = $number !== null && $there['number'] !== null
            && import_screen_key($screen, $there['number']) === import_screen_key($screen, $number);
        $sameMinute = $minute !== '' && strlen($minute) === 16 && $there['minute'] === $minute;
        if ($sameNumber || $sameMinute) {
            return $there;
        }
    }
    return null;
}

/**
 * Set a number on a find that is here already — a missing one filled in,
 * or the file's taken where the user chose it.
 *
 * THROUGH point_update(), NOT A BARE UPDATE (review of 26.09.2026): the
 * number written past it reached neither the other devices — their sync
 * asks for what changed since, and the bare UPDATE moved no updated_at —
 * nor the automatic lists that search by number, and it bore no field
 * time. Where it only fills, the field is asked once more first, in case
 * a device gave the find a number in between.
 */
function import_screen_set_number(PDO $pdo, array &$screen, int $pointId, string $number, array $p,
                                  bool $forReal, bool $overwrite): void
{
    $screen['numbers'][$pointId] = $number;
    $screen['given'][import_screen_key($screen, $number)] ??= ['title' => (string) $p['title'], 'found_at' => $p['found_at'] ?? null];
    if ($forReal) {
        if (!$overwrite) {
            $stmt = $pdo->prepare('SELECT find_number FROM map_points WHERE id = ? AND user_id = ?');
            $stmt->execute([$pointId, $screen['user_id']]);
            if (trim((string) $stmt->fetchColumn()) !== '') {
                return;
            }
        }
        point_update($pdo, $screen['user_id'], $pointId, ['find_number' => mb_substr($number, 0, 80)]);
    }
}

/** The key two numbers of this account are compared by (find_number_key). */
function import_screen_key(array $screen, string $number): string
{
    return find_number_key($screen['pattern'] ?? null, $number);
}

/**
 * What „nächste freie Nummer" gives for a find of $foundAt — and taken,
 * so the next entry that asks gets the one after. The preview shows these
 * numbers; the apply run draws the same ones (find_number_draw), entry
 * after entry, from the same state.
 */
function import_screen_propose(PDO $pdo, array &$screen, ?string $foundAt): string
{
    [$section, $n] = import_screen_next($pdo, $screen, $foundAt);
    $screen['proposed'][$section] = $n + 1;
    return find_number_render($screen['pattern'], $foundAt, $n);
}

/** The same without taking it — for a choice not (yet) made. */
function import_screen_peek(PDO $pdo, array &$screen, ?string $foundAt): string
{
    [, $n] = import_screen_next($pdo, $screen, $foundAt);
    return find_number_render($screen['pattern'], $foundAt, $n);
}

/**
 * The next free running number in the section of $foundAt: the counter's
 * own answer (floor and the highest number given, find_number_mint), then
 * past every number this run has proposed or brought in already.
 *
 * @return array{0: string, 1: int} the section and the number
 */
function import_screen_next(PDO $pdo, array &$screen, ?string $foundAt): array
{
    $pattern = $screen['pattern'];
    $period = find_number_restart($pdo, $screen['user_id']) ? find_number_period($pattern, $foundAt) : null;
    $section = $period['key'] ?? '*';
    if (!isset($screen['proposed'][$section])) {
        $floor = $period === null || $period['key'] === find_number_counter_period($pdo, $screen['user_id'])
            ? find_number_next($pdo, $screen['user_id']) : 1;
        $screen['proposed'][$section] = max($floor,
            find_number_highest($pdo, $screen['user_id'], $pattern, $period, $foundAt) + 1);
    }
    $n = $screen['proposed'][$section];
    // Numbers the file brought and the run kept count too.
    $when = $foundAt !== null ? (DateTime::createFromFormat('Y-m-d H:i:s', $foundAt) ?: new DateTime()) : new DateTime();
    $fixed = match ($period['unit'] ?? null) {
        'Tag' => ['JJJJ', 'JJ', 'MM', 'TT'], 'Monat' => ['JJJJ', 'JJ', 'MM'], 'Jahr' => ['JJJJ', 'JJ'], default => [],
    };
    [$regex] = find_number_matcher($pattern, $fixed, $when);
    foreach ($regex === null ? [] : $screen['run_numbers'] as $given) {
        if (preg_match($regex, $given, $m) === 1 && strlen(ltrim($m[1], '0')) <= 8) {
            $n = max($n, (int) ltrim($m[1], '0') + 1);
        }
    }
    return [$section, $n];
}

/** Keep an entry for the preview's table — only the preview draws it. */
function import_screen_record(array &$screen, array $row, bool $forReal): void
{
    foreach ($row['kinds'] as $kind) {
        $screen['counts'][$kind] = ($screen['counts'][$kind] ?? 0) + 1;
    }
    $screen['counts'][$row['verdict']] = ($screen['counts'][$row['verdict']] ?? 0) + 1;
    if (!$forReal) {
        $screen['rows'][] = $row;
    }
}

/**
 * The number a point of the file brings — null when none, or when it is
 * YASAIMAPP's own mark „Y-…", which names the find it was exported from
 * (point_create drops it too).
 */
function import_number_of(array $p): ?string
{
    require_once __DIR__ . '/find_number.php';
    $number = trim((string) ($p['find_number'] ?? ''));
    return $number === '' || find_number_reserved($number) ? null : $number;
}

/**
 * Is it the account's own find — no finder, or the account's name among
 * them, in any usual spelling (person_names.php)? An account without a
 * name cannot tell and keeps everything as its own.
 */
function import_screen_own(array $screen, array $p): bool
{
    $finder = import_person($p, 'Finder');
    return $finder === '' || $screen['self'] === [] || person_names_self($finder, $screen['self']);
}

/**
 * A person field of a point on its way in: the find field (after
 * import_fields), Meine Pilze's own key, or the note line.
 */
function import_person(array $p, string $field): string
{
    $extra = (array) ($p['extra'] ?? []);
    foreach ([$extra[$field] ?? null, $p[mb_strtolower($field)] ?? null] as $value) {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return import_note_field((string) ($p['note'] ?? ''), $field);
}

/**
 * THE FIELDS OUT OF THE NOTE, back into the fields: the find fields (and
 * the tour) that our export wrote as „Name: Wert" lines, or that a CSV
 * column became on the way in.
 *
 * WHAT IS RECOGNISED: the find fields by their FULL names, case aside —
 * what our export writes („Substrat / Wirt", „Anzahl / Menge"). Not the
 * short forms a foreign file may carry: a KML placemark's „Substrat: Erle"
 * stays in the note, for a field in a list that is no species list is
 * shown nowhere, and the tests' foreign files (tests/kml.php, geojson.php)
 * would have become species lists over one word.
 * „Bestimmungssicherheit" only with one of its stored values — anything
 * else stays text in the note rather than a value the field cannot show.
 * A MULTI-LINE FIELD takes the lines that follow it, up to the next
 * „Name:" line: the export writes Begleitvegetation one plant per line.
 * Whatever is not recognised stays in the note, in its order — the old
 * rule that nothing a file brings is lost still holds.
 *
 * THE FIND NUMBER TOO: KML and GPX have no place of their own for it, so
 * our export writes it as a „Fundnummer:" line (export_note_numbered).
 * Every such line is taken out; the number is the first that is not
 * YASAIMAPP's „Y-" mark — that one names the find it was exported from.
 *
 * @return array{0: string, 1: array<string, string>, 2: ?string, 3: ?string}
 *         note, fields, tour, find number
 */
function import_fields_from_note(string $note): array
{
    require_once __DIR__ . '/find_fields.php';
    require_once __DIR__ . '/find_number.php';
    $known = [];
    $types = [];
    $options = [];
    foreach (find_fields() as $f) {
        $known[mb_strtolower($f['name'])] = $f['name'];
        $types[$f['name']] = $f['type'];
        $options[$f['name']] = $f['options'] ?? null;
    }

    $keep = [];
    $fields = [];
    $tour = null;
    $number = null;
    $current = null;
    foreach (preg_split('/\R/u', $note) ?: [] as $line) {
        $labelled = preg_match('/^[ \t]*([^:\r\n]{1,40}?)[ \t]*:[ \t]*(.*?)[ \t]*$/u', $line, $m) === 1;
        if ($labelled && mb_strtolower(trim($m[1])) === 'fundnummer') {
            if ($number === null && trim($m[2]) !== '' && !find_number_reserved($m[2])) {
                $number = trim($m[2]);
            }
            $current = null;
            continue;
        }
        $name = $labelled ? ($known[mb_strtolower(trim($m[1]))] ?? null) : null;
        if ($labelled && mb_strtolower(trim($m[1])) === 'tour' && $tour === null && trim($m[2]) !== '') {
            $tour = trim($m[2]);
            $current = null;
            continue;
        }
        if ($name !== null && !isset($fields[$name]) && trim($m[2]) !== '') {
            $value = trim($m[2]);
            /* A TICK (a choice with a single answer, „Beleg aufbewahrt")
               reads the usual ways a sheet writes yes and no: „x", „1",
               „✓", „yes" set it; „nein", „0", „-" leave it empty — the
               empty tick says it, so the line goes, not into the note. */
            if ($options[$name] !== null && count($options[$name]) === 1) {
                $said = mb_strtolower($value);
                if (in_array($said, ['nein', 'n', 'no', '0', 'false', 'falsch', '-', '–', '—'], true)) {
                    $current = null;
                    continue;
                }
                if (in_array($said, ['ja', 'j', 'yes', 'y', 'x', '1', 'true', 'wahr', '✓', '✔', '☑'], true)) {
                    $value = $options[$name][0];
                }
            }
            if ($options[$name] !== null) {
                $match = array_values(array_filter($options[$name],
                    static fn(string $o): bool => mb_strtolower($o) === mb_strtolower($value)));
                if ($match === []) {
                    $keep[] = $line;
                    $current = null;
                    continue;
                }
                $value = $match[0];
            }
            $fields[$name] = $value;
            $current = $types[$name] === 'multiline' ? $name : null;
            continue;
        }
        if ($current !== null && !$labelled && trim($line) !== '') {
            $fields[$current] .= "\n" . trim($line);
            continue;
        }
        $current = null;
        $keep[] = $line;
    }
    return [trim(implode("\n", $keep)), $fields, $tour, $number];
}

/**
 * One point of a file with its fields taken out of the note — the step
 * every importer takes before the screening and before point_create.
 * Fields the point already brings (Meine Pilze) win over the note's.
 */
function import_fields(array $p): array
{
    [$note, $fields, $tour, $number] = import_fields_from_note((string) ($p['note'] ?? ''));
    $p['note'] = $note === '' ? null : $note;
    $p['extra'] = (array) ($p['extra'] ?? []) + $fields;
    /* A FIELD A TICK REVEALS, BROUGHT FILLED, SETS THE TICK: a voucher
       number means a voucher (26.09.2026 — „wenn es händisch
       ausgefüllt ist … sollte auch der Haken gesetzt sein"). */
    require_once __DIR__ . '/find_fields.php';
    foreach (find_fields() as $f) {
        if (isset($f['reveals']) && trim((string) ($p['extra'][$f['reveals']] ?? '')) !== ''
            && trim((string) ($p['extra'][$f['name']] ?? '')) === '') {
            $p['extra'][$f['name']] = $f['options'][0];
        }
    }
    if ($tour !== null && trim((string) ($p['tour'] ?? '')) === '') {
        $p['tour'] = $tour;
    }
    // A number the point brings itself (a CSV column, GeoJSON's property) wins.
    if ($number !== null && trim((string) ($p['find_number'] ?? '')) === '') {
        $p['find_number'] = $number;
    }
    return $p;
}

/**
 * Count a created point's find fields — a file that brings any makes its
 * import list a species list (import_screen_species).
 */
function import_screen_created(array &$screen, array $p): void
{
    require_once __DIR__ . '/find_fields.php';
    $extra = (array) ($p['extra'] ?? []);
    foreach (find_fields() as $f) {
        if (trim((string) ($extra[$f['name']] ?? '')) !== '') {
            $screen['fields']++;
            return;
        }
    }
}

/**
 * THE IMPORT LIST BECOMES A SPECIES LIST when the file brought find fields
 * — there, and only there, those fields exist and are shown, and the
 * points count as finds (numbers, the DGfM delivery). The mark sits on the
 * import's root, which governs its whole branch; Meine Pilze sets it from
 * the start.
 */
function import_screen_species(PDO $pdo, int $userId, array $screen, ?int $rootListId): void
{
    if ($rootListId === null || $screen['fields'] === 0) {
        return;
    }
    $pdo->prepare('UPDATE map_lists SET is_species = 1 WHERE id = ? AND user_id = ?')
        ->execute([$rootListId, $userId]);
}

/** What the preview shows of the screening. */
function import_screen_report(array $screen): array
{
    /* The numbers already given ride along only where a number can be
       typed (a number twice, or a renamed find that may become a new
       one) — the field says at once whether a typed one is free. */
    $typing = array_filter($screen['rows'], static fn(array $r): bool
        => in_array('twice', $r['kinds'], true) || in_array('renamed', $r['kinds'], true));
    return ['rows' => $screen['rows'], 'counts' => $screen['counts'], 'missing' => $screen['missing'],
            'name' => $screen['name'], 'fields' => $screen['fields'], 'pattern' => $screen['pattern'] !== null,
            // The keys are find_number_key()'s; the pattern rides along so
            // the field's line keys a typed number the same way.
            'series_pattern' => $typing === [] ? null : $screen['pattern'],
            'given' => $typing === [] ? [] : array_map('strval', array_keys($screen['given']))];
}

/** The value of a „Name: value" line in a note — '' when there is none. */
function import_note_field(string $note, string $field): string
{
    return preg_match('/^[ \t]*' . preg_quote($field, '/') . '[ \t]*:[ \t]*(.+?)[ \t]*$/miu', $note, $m)
        ? $m[1] : '';
}
