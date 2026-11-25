<?php
/**
 * Organisieren — processing and view (Mein Bereich → Organisieren).
 *
 * The organizer for the masses: on the left the list tree as in the
 * sidebar, on the right the points of the chosen branch with checkboxes,
 * search field and time filter. Built for the situation after an import —
 * hundreds of points that need to go into one's own lists —, but just as
 * much for any later tidying up.
 *
 * THE ONE RULE OF THE LISTS DIALOG: only what you touch changes. Per list
 * one checkbox with three states (all selected points / some / none).
 * Setting the check means: added to all. Clearing it: removed from all.
 * A mixed check that nobody touches stays mixed — the unequal
 * assignments of the points are NEVER silently unified. The server
 * therefore does not receive a finished list set but two deltas (add,
 * remove) and applies them per point to its own assignment — that way,
 * whatever the selection does not share is preserved.
 *
 * The table comes into being in the browser (assets/js/organize.js):
 * filtering and checking are finger exercises without server
 * involvement, only the actions write — as ordinary POSTs via the
 * existing punkt_* routes, point by point, so each one gets its stand
 * bump and the change syncs to all devices.
 */

declare(strict_types=1);

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/find_fields.php';
require_once __DIR__ . '/find_number.php';
require_once __DIR__ . '/container.php';
require_once __DIR__ . '/excursions.php';
require_once __DIR__ . '/mykis.php';

/**
 * Must run BEFORE any output. 'section' tells the administration's forms
 * apart — see invitations_content.php.
 *
 * @return array{message: ?string, error: string[], state: array}
 */
function organize_process(PDO $pdo, int $userId): array
{
    $result = ['message' => null, 'error' => [], 'state' => []];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'organize') {
        return $result;
    }
    csrf_check();

    // The view (chosen list, filters) travels along as form fields and
    // comes back into the page after processing — whoever works through
    // 143 points in several passes should not have to navigate from
    // scratch after each of them.
    foreach (organize_state_fields() as $field) {
        $result['state'][$field] = (string) ($_POST['state'][$field] ?? '');
    }

    $pointIds = organize_ids((string) ($_POST['points'] ?? ''));

    try {
        switch ((string) ($_POST['action'] ?? '')) {
            case 'lists':
                if ($pointIds === []) {
                    throw new InvalidArgumentException(t('Keine Punkte gewählt.'));
                }
                // The check on a CONTAINER (add_container) becomes an
                // ordinary list here: one's own that is mounted there —
                // if need be it comes into being first
                // (organize_container_target). After that it is the same
                // sorting-in as everywhere.
                $add = organize_ids((string) ($_POST['add'] ?? ''));
                foreach (organize_ids((string) ($_POST['add_container'] ?? '')) as $containerId) {
                    $add[] = organize_container_target($pdo, $userId, $containerId);
                }

                // OR AS ORIGINALS (add_container_original): if the
                // selection contains whole lists and the maintainer chose the
                // growing share in the confirmation prompt, the lists
                // THEMSELVES are mounted — only the rest of the selection
                // goes into the vehicle list.
                $changed   = [];
                $mounted = [];
                foreach (organize_ids((string) ($_POST['add_container_original'] ?? '')) as $containerId) {
                    [$names, $rest] = organize_container_originals($pdo, $userId, $containerId, $pointIds);
                    $mounted = array_merge($mounted, $names);
                    if ($rest !== []) {
                        $changed = array_merge($changed, organize_lists_apply(
                            $pdo, $userId, $rest,
                            [organize_container_target($pdo, $userId, $containerId)], []));
                    }
                }

                // Removing via the container check (remove_container): the
                // points leave all OWN leaves mounted there — original
                // lists too, because their assignment IS the membership in
                // the container. The dialog announces that beforehand.
                $remove = organize_ids((string) ($_POST['remove'] ?? ''));
                foreach (organize_ids((string) ($_POST['remove_container'] ?? '')) as $containerId) {
                    $remove = array_merge($remove, organize_container_leaves($pdo, $userId, $containerId));
                }

                $changed = array_merge($changed, organize_lists_apply(
                    $pdo, $userId, $pointIds,
                    array_values(array_unique($add)),
                    array_values(array_unique($remove))));

                $n = count(array_unique($changed));
                $result['message'] = match (true) {
                    $n === 0 && $mounted === []
                             => t('Nichts zu ändern — die Zuordnung stimmte schon.'),
                    $n === 0 => '',
                    $n === 1 => t('Die Listen eines Punktes sind angepasst.'),
                    default  => t('Die Listen von %d Punkten sind angepasst.', $n),
                };
                if ($mounted !== []) {
                    $result['message'] = trim($result['message'] . ' '
                        . t('Eingehängt: „%s".', implode('", „', array_unique($mounted))));
                }
                break;

            /* THE PENCIL AT THE ROW (the sketch, 24.08.2026):
               one point, everything at once — title, note, find time and
               the fields, saved in one piece from the table. */
            case 'point_save':
                organize_point_save($pdo, $userId, (int) ($_POST['point'] ?? 0));
                $result['message'] = t('Punkt gespeichert.');
                break;

            case 'trash':
                if ($pointIds === []) {
                    throw new InvalidArgumentException(t('Keine Punkte gewählt.'));
                }
                $n = organize_to_trash($pdo, $userId, $pointIds);
                $result['message'] = $n === 1
                    ? t('Ein Punkt liegt im Papierkorb.')
                    : t('%d Punkte liegen im Papierkorb.', $n);
                break;

            case 'field':
                if ($pointIds === []) {
                    throw new InvalidArgumentException(t('Keine Punkte gewählt.'));
                }
                $field = mb_substr(trim((string) ($_POST['field'] ?? '')), 0, 100);
                $n = organize_field_set($pdo, $userId, $pointIds, $field,
                    mb_substr(trim((string) ($_POST['value'] ?? '')), 0, 2000),
                    ($_POST['clear'] ?? '') === '1');
                $name = $field === 'title' ? t('Titel') : ($field === 'note' ? t('Notiz') : $field);
                $result['message'] = match (true) {
                    $n === 0 => t('Nichts zu ändern — der Wert stand schon überall so.'),
                    $n === 1 => t('„%s" ist bei einem Punkt angepasst.', $name),
                    default  => t('„%s" ist bei %d Punkten angepasst.', $name, $n),
                };
                break;

            /* MANY FIND NUMBERS AT ONCE (26.09.2026: „das
               mehrfach umbenennen der Fundnummern"). The points arrive in
               the order the window numbered them — its answer to „In
               welcher Reihenfolge?" —, and every answer the window asked
               for travels along; organize_numbers_apply() refuses what is
               missing, as the window did before sending. */
            case 'numbers':
                if ($pointIds === []) {
                    throw new InvalidArgumentException(t('Keine Punkte gewählt.'));
                }
                $done = organize_numbers_apply($pdo, $userId, $pointIds, [
                    'mode'    => (string) ($_POST['mode'] ?? ''),
                    'start'   => mb_substr((string) ($_POST['start'] ?? ''), 0, 80),
                    'restart' => (string) ($_POST['restart'] ?? ''),
                    'scope'   => (string) ($_POST['scope'] ?? ''),
                    'old'     => (string) ($_POST['old'] ?? ''),
                    'adopt'   => (string) ($_POST['adopt'] ?? ''),
                ]);
                $result['message'] = organize_numbers_message($done);
                break;

            /* A NEW LIST FROM THE ORGANIZE TAB (22.08.2026) —
               until now one had to go to the map for that. Only the name;
               colour and shape take the defaults and are groomed where
               every list is groomed, at the pencil or on the map. */
            case 'list_new':
                list_create($pdo, $userId, ['name' => (string) ($_POST['name'] ?? '')]);
                $result['message'] = t('Liste angelegt.');
                break;

            case 'list_name':
                $list = organize_own_list($pdo, $userId, (int) ($_POST['list'] ?? 0));
                list_update($pdo, $userId, (int) $list['id'],
                    ['name' => (string) ($_POST['name'] ?? '')]);
                $result['message'] = t('Umbenannt.');
                break;

            case 'list_parent':
                $list = organize_own_list($pdo, $userId, (int) ($_POST['list'] ?? 0));
                // list_parent_check (in list_update) stops cycles and
                // throws on nonsense — the message lands as an error above.
                list_update($pdo, $userId, (int) $list['id'],
                    ['parent_id' => ($_POST['target'] ?? '') === '' ? null : (int) $_POST['target']]);
                $result['message'] = t('Verschoben.');
                break;

            /* INTO A MAPPE OR OUT OF IT (0168) — what „Verschieben" means
               since lists no longer hang under lists. folder_check (in
               list_update) refuses somebody else's Mappe. */
            case 'list_folder':
                $list = organize_own_list($pdo, $userId, (int) ($_POST['list'] ?? 0));
                list_update($pdo, $userId, (int) $list['id'],
                    ['folder_id' => ($_POST['target'] ?? '') === '' ? null : (int) $_POST['target']]);
                $result['message'] = t('Verschoben.');
                break;

            case 'list_merge':
                $n = organize_merge($pdo, $userId,
                    (int) ($_POST['list'] ?? 0), (int) ($_POST['target'] ?? 0));
                $result['message'] = $n === 1
                    ? t('Zusammengeführt — ein Punkt ist umgezogen, die Liste ist weg.')
                    : t('Zusammengeführt — %d Punkte sind umgezogen, die Liste ist weg.', $n);
                // The source no longer exists; the view jumps to the
                // target, where what was just moved now lies.
                $result['state']['list'] = (string) (int) ($_POST['target'] ?? 0);
                break;

            case 'list_delete':
                $list = organize_own_list($pdo, $userId, (int) ($_POST['list'] ?? 0));
                $gone = list_delete($pdo, $userId, (int) $list['id']);
                // A joint list went to another member instead (joint lists, step 3).
                $result['message'] = isset($gone['handed_over'])
                    ? t('Die Liste ist an %s übergegangen — sie bleibt der Gruppe, du findest sie unter „Geteilt".', $gone['handed_over']['to_name'])
                    : t('Die Liste ist weg — ihre Punkte bleiben.');
                $result['state']['list'] = 'alle';
                break;

            /* THE THREE TOUR ACTIONS ARE ONE OPERATION
               (23.08.2026): a tour is a label on its points, so renaming,
               merging and deleting all say "every point carrying X now
               carries Y" — merging is a rename onto an existing name,
               deleting is a rename onto nothing. One case instead of
               three that would have to stay in step. What differs is only
               what the browser asks beforehand and what the message says
               afterwards. */
            case 'tour_set':
                $from = trim((string) ($_POST['tour_from'] ?? ''));
                $to   = trim((string) ($_POST['tour_to'] ?? ''));
                if ($from === '') {
                    throw new InvalidArgumentException(t('Welche Tour? Der Name fehlt.'));
                }
                $fromId = point_tour_id($_POST['tour_from_id'] ?? null);
                $toId   = point_tour_id($_POST['tour_to_id'] ?? null);
                $n = organize_tour_set($pdo, $userId, $from, $to,
                    (string) ($fromId ?? ''), (string) ($toId ?? ''));
                $result['message'] = match (true) {
                    $n === 0  => t('Nichts zu ändern — diese Tour trägt kein Punkt (mehr).'),
                    $to === '' => $n === 1
                        ? t('Die Tour ist weg — ihr Punkt bleibt, er trägt jetzt keine Tour mehr.')
                        : t('Die Tour ist weg — ihre %d Punkte bleiben, sie tragen jetzt keine Tour mehr.', $n),
                    default  => $n === 1
                        ? t('Ein Punkt trägt jetzt „%s".', $to)
                        : t('%d Punkte tragen jetzt „%s".', $n, $to),
                };
                /* The view follows the outing, and since migration 0079
                   that means its KEY: after a rename the name changed,
                   after a merge it is the target's row — a remembered
                   name would point at nothing and leave the table
                   standing empty. */
                $result['state']['tours'] = $to === '' ? ''
                    : ((($toId ?? $fromId) !== null) ? 'i:' . ($toId ?? $fromId) : 'n:' . $to);
                break;

            /* AN EMPTY TOUR, and deliberately without taking the checked
               points along (23.08.2026). It is the name that
               is created here — the registry of migration 0073 exists for
               exactly this moment: a tour that has no points yet. It gets
               them in the field, where the tour picker of the point
               window offers the registered names. */
            /* THE CHOSEN POINTS INTO ONE TOUR (23.08.2026) —
               the counterpart of the lists dialog, and the way a tour
               created empty gets filled. One name for all of them: a
               point is on exactly one outing. An empty name takes the
               tour off, which is how a point leaves one without joining
               another. */
            /* INTO AN EXCURSION, AFTERWARDS (12.09.2026: „Gibt es
               einen Weg für die Exkursionsleiter, nachträglich Punkte in
               eine Exkursion zu setzen?" — there was none: the reference
               was written at creation only, on the map-along path). The
               same gesture as the tour: one target for all chosen points,
               the empty entry takes them out again. */
            case 'excursion_assign':
                if ($pointIds === []) {
                    throw new InvalidArgumentException(t('Keine Punkte gewählt.'));
                }
                $target = (int) ($_POST['excursion_to'] ?? 0);
                $done = organize_excursion_apply($pdo, $userId, $pointIds, $target > 0 ? $target : null);
                $n = $done['n'];
                $result['message'] = match (true) {
                    $n === 0      => t('Nichts zu ändern — die Punkte standen schon so.'),
                    $target === 0 => $n === 1
                        ? t('Ein Punkt gehört zu keiner Exkursion mehr.')
                        : t('%d Punkte gehören zu keiner Exkursion mehr.', $n),
                    default       => $n === 1
                        ? t('Ein Punkt gehört jetzt zur Exkursion „%s".', $done['name'])
                        : t('%d Punkte gehören jetzt zur Exkursion „%s".', $n, $done['name']),
                };
                /* SAID, NOT REFUSED: a find outside the window is exactly
                   the case this exists for — the point set at the car
                   park after the end, the one forgotten in the evening.
                   The window says when guests may come in; it does not
                   say what a leader may file. */
                if ($target > 0 && $done['outside'] > 0) {
                    $result['message'] .= ' ' . ($done['outside'] === 1
                        ? t('Einer liegt außerhalb ihres Zeitfensters.')
                        : t('%d liegen außerhalb ihres Zeitfensters.', $done['outside']));
                }
                if ($target > 0) {
                    $result['state']['tours'] = 'e:' . $target;
                    $result['state']['pane']  = 'tours';
                }
                break;

            case 'tour_assign':
                if ($pointIds === []) {
                    throw new InvalidArgumentException(t('Keine Punkte gewählt.'));
                }
                $to = trim((string) ($_POST['tour_to'] ?? ''));
                /* THE TARGET'S IDENTITY comes along (migration 0079):
                   assigning points to „Tagestour" must put them into THE
                   one meant, not into a namesake. Where the browser knows
                   no id — an old client, a name typed by hand — one is
                   minted for the new outing, so it has an identity from
                   its first point on. */
                $targetId = point_tour_id($_POST['tour_to_id'] ?? null)
                    ?? ($to === '' ? null : bin2hex(random_bytes(6)));
                $n  = organize_tour_apply($pdo, $userId, $pointIds,
                    $to === '' ? null : mb_substr($to, 0, 120), $targetId);
                $result['message'] = match (true) {
                    $n === 0   => t('Nichts zu ändern — die Punkte standen schon so.'),
                    $to === '' => $n === 1
                        ? t('Ein Punkt steht jetzt unter „Ohne Tour".')
                        : t('%d Punkte stehen jetzt unter „Ohne Tour".', $n),
                    default    => $n === 1
                        ? t('Ein Punkt gehört jetzt zur Tour „%s".', $to)
                        : t('%d Punkte gehören jetzt zur Tour „%s".', $n, $to),
                };
                // The view follows what was just done — as after a merge.
                if ($to !== '') {
                    $result['state']['tours'] = $targetId !== null ? 'i:' . $targetId : 'n:' . $to;
                    $result['state']['pane']  = 'tours';
                }
                break;

            case 'tour_new':
                $name = trim((string) ($_POST['tour_to'] ?? ''));
                if ($name === '') {
                    throw new InvalidArgumentException(t('Die Tour braucht einen Namen.'));
                }
                organize_tour_register($pdo, $userId, mb_substr($name, 0, 120));
                $result['message'] = t('Die Tour „%s" steht bereit — noch ohne Punkte.', $name);
                $result['state']['pane']  = 'tours';
                break;

            case 'autolist':
                // THE AUTO LIST ARISES FROM THE FILTER, NOT FROM THE
                // SELECTION (the call, 14.08.2026: "no checkmarks
                // needed — the result gives the list"). That is why no
                // points field arrives here; instead the zustand itself
                // becomes the stored filter.
                $name = trim((string) ($_POST['name'] ?? ''));
                if ($name === '') {
                    throw new InvalidArgumentException(t('Die Autoliste braucht einen Namen.'));
                }
                [$listId, $n] = autolist_create($pdo, $userId, $name,
                    organize_autofilter($pdo, $userId, $result['state']));
                // ZERO IS ALLOWED, deliberately (22.08.2026):
                // an auto list may be built for FUTURE finds — the
                // message says the emptiness and, right after, that it
                // will feed itself.
                $result['message'] = match (true) {
                    $n === 0 => t('Autoliste „%s" angelegt — ihr Filter trifft gerade keinen Punkt. Sie führt sich ab jetzt von selbst nach.', $name),
                    $n === 1 => t('Autoliste „%s" angelegt — ihr Filter trifft gerade einen Punkt. Sie führt sich ab jetzt von selbst nach.', $name),
                    default  => t('Autoliste „%s" angelegt — ihr Filter trifft gerade %d Punkte. Sie führt sich ab jetzt von selbst nach.', $name, $n),
                };
                // The view jumps to the new list: you see right away what
                // the stored filter REALLY caught.
                $result['state']['list'] = (string) $listId;
                break;

            default:
                /* AN UNKNOWN ACTION FELL OUT OF THE SWITCH AND WAS NEVER
                   HEARD FROM AGAIN — the same silence that made the
                   field-name mismatch so expensive on 21.08.2026. There
                   is no legitimate case: every action comes from a hidden
                   field that organize.js writes, so an unknown value can
                   only be a name that has come apart between the two
                   files. It costs one line to make that say so. */
                throw new InvalidArgumentException(
                    t('Unbekannte Aktion „%s" — das ist ein Fehler im Programm, nicht in deiner Eingabe. Es wurde nichts geändert.',
                        (string) ($_POST['action'] ?? '')));
        }
    } catch (InvalidArgumentException $e) {
        $result['error'][] = error_for_user($e);
    }

    return $result;
}

/**
 * Bring the view's filter into the fixed form of the auto list.
 *
 * The browser sends the same zustand every Organisieren action carries — here
 * it becomes what the list stores permanently. Not everything may go in,
 * and what falls out is announced BEFOREHAND by the creation window
 * (organize.js, autoFilterText), nothing falls silently:
 *
 *   Container views ('c:', 'f:') carry other people's points, and those
 *   cannot live in an own list — points belong to their owner.
 *
 *   Auto lists are no good as the source of an auto list: A feeds B
 *   feeds A would be a cycle, and autolist_matches would compute a
 *   different result on every pass.
 *
 * Checked against the STORED state, not against what the browser claims
 * — the same stance as in list_parent_check.
 */
function organize_autofilter(PDO $pdo, int $userId, array $state): array
{
    $sources = [];
    $numbers = [];
    foreach (explode(',', (string) ($state['list'] ?? '')) as $part) {
        $part = trim($part);
        if ($part === 'none') {
            $sources[] = 'none';
        } elseif ($part !== '' && ctype_digit($part)) {
            $numbers[(int) $part] = true;
        }
    }
    if ($numbers !== []) {
        $space = implode(',', array_fill(0, count($numbers), '?'));
        $stmt  = $pdo->prepare(
            "SELECT id FROM map_lists
              WHERE user_id = ? AND deleted_at IS NULL AND id IN ($space)"
            . (autolists_ready($pdo) ? ' AND filter IS NULL' : '')
        );
        $stmt->execute(array_merge([$userId], array_keys($numbers)));
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $sources[] = (int) $id;
        }
    }

    // The term rows travel along as one string ("a~Buche|n~Fomes", see
    // writeTerms in assets/js/filter.js) — through the same field
    // as the single search text before.
    $terms = [];
    foreach (explode('|', (string) ($state['search'] ?? '')) as $part) {
        $kind  = substr($part, 0, 2);
        $text = trim(substr($part, 2));
        if ($text === '' || ($kind !== 'a~' && $kind !== 'n~')) {
            continue;
        }
        $terms[] = [
            'type'  => $kind === 'n~' ? 'contains_not' : 'contains',
            'text' => mb_substr($text, 0, 100),
        ];
    }

    // autolist_filter_read normalizes once more on creation anyway —
    // gathering things up is enough here.
    return [
        'sources'  => $sources,
        'terms' => $terms,
        'from'      => (string) ($state['from'] ?? ''),
        'until'      => (string) ($state['to'] ?? ''),
        'undated' => ($state['no_time'] ?? '') === '1',
        // The threat categories as "1,3" — the same form writeRl() in
        // assets/js/filter.js produces. Which of them count is sorted out
        // once more by autolist_filter_read() right after.
        'rl'       => array_filter(explode(',', (string) ($state['rl'] ?? ''))),
        // The DGfM marks the same way — "edible,poisonous" as
        // writeEdibility() produces it, and the hook as '1'.
        'edibility'      => array_filter(explode(',', (string) ($state['edibility'] ?? ''))),
        'responsibility' => ($state['responsibility'] ?? '') === '1',
        // The drawn areas travel as JSON (stateFields in organize.js) —
        // corner validation happens in autolist_filter_read, like
        // everything else here it is only gathered up.
        'areas'    => json_decode((string) ($state['areas'] ?? ''), true) ?: [],
        // Region codes as "THU,BAY", municipalities '|'-separated (a name
        // carries a comma sooner than a bar), the map-sheet text raw —
        // all normalized once more in autolist_filter_read.
        'regions'        => array_filter(explode(',', (string) ($state['regions'] ?? ''))),
        'municipalities' => array_filter(explode('|', (string) ($state['municipalities'] ?? ''))),
        'mtb'            => (string) ($state['mtb'] ?? ''),
        // The height band as two numbers — normalized once more in
        // autolist_filter_read, like everything else here.
        'elevation_from' => (string) ($state['elevation_from'] ?? ''),
        'elevation_to'   => (string) ($state['elevation_to'] ?? ''),
        // The systematic ranks — gathered up flat, normalized once more in
        // autolist_filter_read like everything else here.
        'taxonomy'       => array_filter([
            'kingdom' => (string) ($state['taxonomy_kingdom'] ?? ''),
            'phylum'  => (string) ($state['taxonomy_phylum'] ?? ''),
            'class'   => (string) ($state['taxonomy_class'] ?? ''),
            'order'   => (string) ($state['taxonomy_order'] ?? ''),
            'family'  => (string) ($state['taxonomy_family'] ?? ''),
            'genus'   => (string) ($state['taxonomy_genus'] ?? ''),
        ], static fn(string $v): bool => $v !== ''),
    ];
}

/** The own, living list — or the fitting error message. */
function organize_own_list(PDO $pdo, int $userId, int $listId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, is_species FROM map_lists
          WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$listId, $userId]);
    $list = $stmt->fetch();
    if ($list === false) {
        throw new InvalidArgumentException(t('Diese Liste gibt es nicht (mehr).'));
    }
    return $list;
}

/** Does the list have living sublists? Then it is a heading, not a leaf. */
function organize_has_children(PDO $pdo, int $userId, int $listId): bool
{
    if (!column_exists($pdo, 'map_lists', 'parent_id')) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM map_lists WHERE user_id = ? AND parent_id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([$userId, $listId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Set one field to the same value on all selected points.
 *
 * 'title' and 'note' are the point fields themselves, everything else is
 * an extra field: the whole extra block is read, ONE key changed, the
 * block handed back — point_update derives field times and species
 * references from it as with any input at the point window. Unchanged
 * points are skipped: no stand bump without a change, as in the lists
 * dialog.
 *
 * @return int how many points really changed
 */
function organize_field_set(PDO $pdo, int $userId, array $pointIds,
                            string $field, string $value, bool $clear): int
{
    if ($field === '') {
        throw new InvalidArgumentException(t('Kein Feld gewählt.'));
    }
    if ($field === 'title' && ($clear || $value === '')) {
        // A point without a title is a nobody on the map — empty exists
        // at creation as "Ohne Titel", but never through mass clearing.
        throw new InvalidArgumentException(t('Der Titel lässt sich nicht leeren.'));
    }
    if (!$clear && $value === '' && $field !== 'note') {
        throw new InvalidArgumentException(t('Ohne Wert bitte „Feld leeren" ankreuzen.'));
    }

    $changed = 0;
    foreach ($pointIds as $pointId) {
        // Own first — else the LEADER'S right at the excursion reference
        // (stage 5), else the PARTICIPANT'S at his own old guest find
        // (stage 7). Either way the write runs AS the owner (the guest
        // account), so field times and sync behave like any edit. A
        // stranger's id resolves to nothing and is skipped, as unknown
        // ids always were.
        $as = $userId;
        $point = point_load($pdo, $userId, $pointId);
        if ($point === null) {
            $owner = excursion_point_leader_scope($pdo, $userId, $pointId)
                ?? excursion_point_participant_scope($pdo, $userId, $pointId);
            if ($owner !== null) {
                $as = $owner;
                $point = point_load($pdo, $owner, $pointId);
            }
        }
        if ($point === null || $point['deleted_at'] !== null) {
            continue;
        }

        if ($field === 'title') {
            if ((string) $point['title'] === $value) {
                continue;
            }
            point_update($pdo, $as, $pointId, ['title' => $value]);
        } elseif ($field === 'note') {
            $new = $clear || $value === '' ? null : $value;
            if (($point['note'] ?? null) === $new) {
                continue;
            }
            point_update($pdo, $as, $pointId, ['note' => $new]);
        } else {
            $extra = (array) $point['extra'];
            $old    = $extra[$field] ?? null;
            if ($clear) {
                if ($old === null) {
                    continue;
                }
                // CLEARING MEANS AN EMPTY VALUE, NOT OMITTING: the field-
                // times review merges the extra block — a missing key
                // stays put (so old clients delete nothing by omission),
                // only '' takes it out. An unset here would be a clearing
                // that never arrives.
                $extra[$field] = '';
            } else {
                if ((string) $old === $value) {
                    continue;
                }
                $extra[$field] = $value;
            }
            point_update($pdo, $as, $pointId, ['extra' => $extra]);
        }
        $changed++;
    }
    return $changed;
}

/**
 * Many find numbers at once — Organisieren → Fundnummern.
 *
 * WHAT IS NUMBERED: the account's OWN finds among the chosen points —
 * alive and in a species branch (26.09.2026: „es muss eine
 * Artenliste sein"). A waypoint has no collection number; a guest's
 * excursion find and a co-owned find carry their finder's, and that is
 * the finder's to give. The window sends only these; whatever else
 * arrives falls out here as well.
 *
 * THE PLAN IS DRAWN UNDER THE ACCOUNT'S LOCK — the same row
 * find_number_draw() takes, so a phone handing in a find this very
 * moment waits, and for „die nächsten freien" the counter moves on past
 * the whole batch before the lock opens: the phone's find draws behind
 * it, never into it. The numbers themselves are then written find by
 * find through point_update(), like every mass action here — each one
 * gets its field time and its stand bump, and the change reaches every
 * device. One narrow gap is left open on purpose: a find of an OLDER
 * section handed in between the lock and the writes could draw a number
 * of that section the batch is about to write. That needs a phone
 * handing in yesterday's find in the very second somebody renumbers
 * yesterday at the desk; the alternative — holding the lock through a
 * thousand saves — would stall every device of the account instead.
 *
 * Every question the window asked must have its answer; one missing is
 * refused before anything is written, as the window refused it before
 * sending.
 *
 * @param int[] $pointIds in the order to number them
 * @param array{mode: string, start: string, restart: string, scope: string, old: string, adopt: string} $ask
 * @return array{mode: string, given: int, taken: int, undated: int, noted: bool, adopted: ?string}
 * @throws InvalidArgumentException
 */
function organize_numbers_apply(PDO $pdo, int $userId, array $pointIds, array $ask): array
{
    require_once __DIR__ . '/find_number.php';
    if (!find_number_ready($pdo)) {
        throw new InvalidArgumentException(t('Die Fundnummern brauchen die Migration 0070 — sie ist noch nicht eingespielt.'));
    }
    $mode = $ask['mode'];
    if (!in_array($mode, ['next', 'from', 'remove'], true)) {
        throw new InvalidArgumentException(t('Welche Nummern die Funde bekommen sollen, fehlt noch.'));
    }
    $pattern = find_number_pattern($pdo, $userId);
    if ($mode === 'next' && $pattern === null) {
        throw new InvalidArgumentException(t('Für die nächsten freien Nummern brauchst du ein Muster unter Einstellungen → Fundnummer.'));
    }
    $start = null;
    if ($mode === 'from') {
        $start = find_number_start($pattern, $ask['start']);
        if ($start === null || isset($start['error'])) {
            throw new InvalidArgumentException($start['error'] ?? t('Die erste Nummer fehlt noch.'));
        }
    }
    $newPattern = ($start['kind'] ?? '') === 'pattern' ? (string) $start['pattern'] : null;
    if ($newPattern !== null && ($start['unit'] ?? null) !== null && !in_array($ask['restart'], ['yes', 'no'], true)) {
        throw new InvalidArgumentException(t('Ob die laufende Nummer neu beginnt, fehlt noch.'));
    }
    if ($newPattern !== null && !in_array($ask['adopt'], ['yes', 'no'], true)) {
        throw new InvalidArgumentException(t('Ob neue Funde danach auch so nummeriert werden, fehlt noch.'));
    }
    if ($newPattern !== null && $ask['adopt'] === 'yes' && mb_strlen($newPattern) > 60) {
        throw new InvalidArgumentException(t('Das Muster ist zu lang. Es darf höchstens 60 Zeichen haben.'));
    }

    $finds = organize_number_finds($pdo, $userId, $pointIds);
    if ($finds === []) {
        throw new InvalidArgumentException(t('Unter den gewählten Punkten ist kein eigener Fund aus einer Artenliste.'));
    }
    $numbered = count(array_filter($finds, static fn(array $f): bool => $f['number'] !== null));
    $mixed = $numbered > 0 && $numbered < count($finds);
    $scope = $ask['scope'];
    if ($mode !== 'remove' && $mixed && !in_array($scope, ['missing', 'all'], true)) {
        throw new InvalidArgumentException(t('Welche Funde eine neue Nummer bekommen sollen, fehlt noch.'));
    }
    if ($mode === 'remove' || !$mixed) {
        $scope = 'all';
    }

    $restartReady = column_exists($pdo, 'map_users', 'find_counter_period');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT find_counter'
            . ($restartReady ? ', find_number_restart, find_counter_period' : '')
            . ' FROM map_users WHERE id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $row = $stmt->fetch() ?: [];
        $series = $pattern === null ? null : [
            'pattern' => $pattern,
            'counter' => max(1, (int) ($row['find_counter'] ?? 1)),
            'restart' => !empty($row['find_number_restart']),
            'period'  => isset($row['find_counter_period']) ? (string) $row['find_counter_period'] : null,
        ];
        $plan = find_number_plan($series, $finds, organize_number_taken($pdo, $userId, $finds),
            $mode, $start, $ask['restart'] === 'yes', $scope);

        $lost = 0;
        foreach ($plan['rows'] as $i => $r) {
            if ($r['state'] === 'new' && $finds[$i]['number'] !== null) {
                $lost++;
            }
        }
        if ($lost > 0 && !in_array($ask['old'], ['note', 'drop'], true)) {
            throw new InvalidArgumentException(t('Was mit den bisherigen Nummern passiert, fehlt noch.'));
        }

        // The counter moves on past the batch, as each draw would have
        // moved it — before the lock opens.
        if ($mode === 'next' && $series !== null && $plan['counter'] !== null
            && ($plan['counter'] !== $series['counter'] || $plan['period'] !== $series['period'])) {
            if ($restartReady && $plan['period'] !== null) {
                $pdo->prepare('UPDATE map_users SET find_counter = ?, find_counter_period = ? WHERE id = ?')
                    ->execute([$plan['counter'], $plan['period'], $userId]);
            } else {
                $pdo->prepare('UPDATE map_users SET find_counter = ? WHERE id = ?')
                    ->execute([$plan['counter'], $userId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    /* THE OLD NUMBER INTO THE NOTE, if asked for: a voucher label in a box
       carries it still, and the search finds the find by its note. One
       line at the end, and not twice when the same number goes twice. */
    $given = $taken = $undated = 0;
    foreach ($plan['rows'] as $i => $r) {
        $taken += $r['state'] === 'taken' ? 1 : 0;
        $undated += $r['state'] === 'undated' ? 1 : 0;
        if ($r['state'] !== 'new') {
            continue;
        }
        $find = $finds[$i];
        $data = ['find_number' => $r['number']];
        if ($ask['old'] === 'note' && $find['number'] !== null) {
            $line = t('Frühere Fundnummer: %s', $find['number']);
            $note = trim((string) ($find['note'] ?? ''));
            if (!str_contains($note, $line)) {
                $data['note'] = $note === '' ? $line : $note . "\n" . $line;
            }
        }
        point_update($pdo, $userId, $find['id'], $data);
        $given++;
    }

    /* THE PATTERN FOR NEW FINDS, if asked for — after the writes, so the
       numbers above were spelled by the pattern that was in force, as the
       window showed them. The counter goes to 1: the new series was just
       built out of the finds themselves, and the mint goes on above its
       highest number by itself (find_number_draw). */
    $adopted = null;
    if ($newPattern !== null && $ask['adopt'] === 'yes') {
        find_number_settings_save($pdo, $userId, $newPattern, '1', $ask['restart'] === 'yes');
        $adopted = $newPattern;
    }

    return ['mode' => $mode, 'given' => $given, 'taken' => $taken, 'undated' => $undated,
            'noted' => $ask['old'] === 'note' && $lost > 0, 'adopted' => $adopted];
}

/**
 * The chosen points that are own finds, in the order given — each with
 * what the plan needs and the note, for the old number.
 *
 * @return array<int, array{id: int, title: string, number: ?string, found_at: ?string, note: ?string}>
 */
function organize_number_finds(PDO $pdo, int $userId, array $pointIds): array
{
    $pointIds = array_values(array_unique(array_map('intval', $pointIds)));
    if ($pointIds === []) {
        return [];
    }
    $space = implode(',', array_fill(0, count($pointIds), '?'));
    $stmt = $pdo->prepare("SELECT id, title, note, find_number, found_at FROM map_points
                            WHERE user_id = ? AND deleted_at IS NULL AND id IN ($space)");
    $stmt->execute([$userId, ...$pointIds]);
    $byId = [];
    foreach ($stmt->fetchAll() as $p) {
        $byId[(int) $p['id']] = $p;
    }
    $lists = [];
    $stmt = $pdo->prepare("SELECT point_id, list_id FROM map_point_lists WHERE point_id IN ($space)");
    $stmt->execute($pointIds);
    foreach ($stmt->fetchAll() as $r) {
        $lists[(int) $r['point_id']][] = (int) $r['list_id'];
    }

    $species = [];
    $finds = [];
    foreach ($pointIds as $id) {
        if (!isset($byId[$id])) {
            continue;
        }
        $isFind = false;
        foreach ($lists[$id] ?? [] as $listId) {
            $species[$listId] ??= find_number_species_branch($pdo, $userId, [$listId]);
            if ($species[$listId]) {
                $isFind = true;
                break;
            }
        }
        if (!$isFind) {
            continue;
        }
        $p = $byId[$id];
        $number = trim((string) ($p['find_number'] ?? ''));
        $finds[] = ['id' => $id, 'title' => (string) $p['title'], 'number' => $number === '' ? null : $number,
                    'found_at' => $p['found_at'] === null ? null : (string) $p['found_at'], 'note' => $p['note']];
    }
    return $finds;
}

/**
 * The numbers that stay where they are: every own number outside the
 * choice, the trash included — a find put back keeps its number, so its
 * number is not free (find_number_highest counts it too).
 *
 * @return array<string, string> find_number_key() => title
 */
function organize_number_taken(PDO $pdo, int $userId, array $finds): array
{
    $chosen = array_flip(array_column($finds, 'id'));
    $pattern = find_number_pattern($pdo, $userId);
    $stmt = $pdo->prepare('SELECT id, title, find_number, deleted_at FROM map_points
                            WHERE user_id = ? AND find_number IS NOT NULL AND find_number <> \'\'');
    $stmt->execute([$userId]);
    $taken = [];
    foreach ($stmt->fetchAll() as $p) {
        if (isset($chosen[(int) $p['id']])) {
            continue;
        }
        $taken[find_number_key($pattern, (string) $p['find_number'])] = $p['deleted_at'] === null
            ? (string) $p['title'] : t('%s (im Papierkorb)', (string) $p['title']);
    }
    return $taken;
}

/** What the numbering did, as the toast says it — short, one thought each. */
function organize_numbers_message(array $done): string
{
    $n = $done['given'];
    $parts = [match (true) {
        $n === 0 => t('Nichts zu ändern — die Nummern standen schon so.'),
        $done['mode'] === 'remove' => $n === 1
            ? t('Ein Fund trägt wieder seine Nummer von YASAIMAPP.')
            : t('%d Funde tragen wieder ihre Nummer von YASAIMAPP.', $n),
        default => $n === 1
            ? t('Ein Fund trägt jetzt eine neue Nummer.')
            : t('%d Funde tragen jetzt eine neue Nummer.', $n),
    }];
    if ($done['taken'] > 0) {
        $parts[] = $done['taken'] === 1
            ? t('Einer blieb, wie er war: Seine neue Nummer trägt schon ein anderer Fund.')
            : t('%d blieben, wie sie waren: Ihre neuen Nummern tragen schon andere Funde.', $done['taken']);
    }
    if ($done['undated'] > 0) {
        $parts[] = $done['undated'] === 1
            ? t('Einer ohne Fundzeit blieb ohne neue Nummer.')
            : t('%d ohne Fundzeit blieben ohne neue Nummer.', $done['undated']);
    }
    if ($done['noted']) {
        $parts[] = t('Die bisherigen Nummern stehen in der Notiz.');
    }
    if ($done['adopted'] !== null) {
        $parts[] = t('Neue Funde bekommen ihre Nummer jetzt nach %s.', $done['adopted']);
    }
    return implode(' ', $parts);
}

/**
 * Save one point whole — the row pencil's action. The same resolution
 * chain as field and trash (own → leader → participant), and the same
 * liberality as organize_field_set for one's own points: the user may
 * put any field name on his own finds. An EXCURSION find takes only the
 * fixed set, a choice only its own options — and its finder never falls
 * empty: without him the find would lose the one thing the guest's
 * registration was for.
 *
 * The offered form is the truth: an empty value CLEARS the field (the
 * '' the field-times review understands), an absent name stays as it
 * was.
 *
 * @throws InvalidArgumentException
 */
function organize_point_save(PDO $pdo, int $userId, int $pointId): void
{
    $as = $userId;
    $excursionFind = false;
    $point = point_load($pdo, $userId, $pointId);
    if ($point === null) {
        $owner = excursion_point_leader_scope($pdo, $userId, $pointId)
            ?? excursion_point_participant_scope($pdo, $userId, $pointId);
        if ($owner !== null) {
            $as = $owner;
            $excursionFind = true;
            $point = point_load($pdo, $owner, $pointId);
        }
    }
    if ($point === null || $point['deleted_at'] !== null) {
        throw new InvalidArgumentException(t('Diesen Punkt gibt es nicht (mehr).'));
    }

    $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 200);
    if ($title === '') {
        throw new InvalidArgumentException(t('Ohne Titel geht es nicht — ein Punkt ohne Namen ist ein Niemand.'));
    }
    $note = trim((string) ($_POST['note'] ?? ''));

    $extra = (array) $point['extra'];
    $allowed = null;
    if ($excursionFind) {
        require_once __DIR__ . '/find_fields.php';
        $allowed = [];
        foreach (find_fields() as $f) {
            $allowed[$f['name']] = $f;
        }
    }
    foreach ((array) ($_POST['fields'] ?? []) as $name => $value) {
        $name = mb_substr(trim((string) $name), 0, 100);
        $value = mb_substr(trim((string) $value), 0, 2000);
        if ($name === '') {
            continue;
        }
        if ($allowed !== null) {
            if (!isset($allowed[$name])) {
                continue;
            }
            if ($value !== '' && $allowed[$name]['type'] === 'choice'
                && !in_array($value, (array) $allowed[$name]['options'], true)) {
                continue;
            }
        }
        $extra[$name] = $value;
    }
    if ($excursionFind && trim((string) ($extra['Finder'] ?? '')) === '') {
        $extra['Finder'] = (string) (((array) $point['extra'])['Finder'] ?? '');
    }

    point_update($pdo, $as, $pointId, [
        'title'    => $title,
        'note'     => $note === '' ? null : $note,
        'found_at' => trim((string) ($_POST['found_at'] ?? '')) ?: null,
        'extra'    => $extra,
    ]);
}

/**
 * Let one list be absorbed into another.
 *
 * ONLY LEAF INTO LEAF: points live only in leaves, and a target that
 * became a heading through children pushed underneath would lose its own
 * points as legacy stock. Whoever wants to dissolve a branch merges the
 * leaves one by one and then deletes the empty superstructure — that is
 * also the honest order, because each leaf may belong somewhere else.
 *
 * The points move one by one via point_update (stand bump, sync);
 * whoever already hangs in the target is not assigned twice. Then the
 * source falls — a species list too: it is empty at this moment, and the
 * lock in list_delete only protects species lists that still carry
 * finds (list_branch_has_points, data.php).
 *
 * @return int how many points moved
 */
function organize_merge(PDO $pdo, int $userId, int $sourceId, int $targetId): int
{
    $source = organize_own_list($pdo, $userId, $sourceId);
    $target   = organize_own_list($pdo, $userId, $targetId);
    if ($sourceId === $targetId) {
        throw new InvalidArgumentException(t('Eine Liste kann nicht in sich selbst aufgehen.'));
    }
    // A JOINT LIST IS NOT MERGED AWAY (joint lists, step 3, 22.09.2026):
    // merging moves only the own points and would then hand the emptied
    // shell over to the group — nobody asked for that.
    require_once __DIR__ . '/joint_lists.php';
    if (joint_list_is_hung($pdo, $userId, $sourceId)) {
        throw new InvalidArgumentException(t('Eine gemeinsame Liste lässt sich nicht zusammenführen — sie gehört der Gruppe. Erst „gemeinsam bearbeiten" ausschalten, dann geht es.'));
    }
    // Auto lists are not up for choice on either side: as a target that
    // would be filling by hand (forbidden, see point_lists_set), as a
    // source a dissolving whose result the filter would restore right
    // away — whoever wants rid of them deletes them.
    if (autolists_ready($pdo)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM map_lists WHERE id IN (?, ?) AND filter IS NOT NULL');
        $stmt->execute([$sourceId, $targetId]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException(
                t('Eine Autoliste führt ihren Inhalt über ihren Filter — zusammenführen lässt sie sich nicht.'));
        }
    }
    if (organize_has_children($pdo, $userId, $sourceId) || organize_has_children($pdo, $userId, $targetId)) {
        throw new InvalidArgumentException(
            t('Zusammenführen geht nur von Blatt zu Blatt — erst die Unterlisten zusammenführen, dann den leeren Überbau löschen.'));
    }

    $stmt = $pdo->prepare(
        'SELECT pl.point_id FROM map_point_lists pl
           JOIN map_points p ON p.id = pl.point_id AND p.deleted_at IS NULL
          WHERE pl.list_id = ? AND p.user_id = ?'
    );
    $stmt->execute([$sourceId, $userId]);

    $moved = 0;
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $pointId) {
        $point  = point_load($pdo, $userId, $pointId);
        if ($point === null) {
            continue;
        }
        $lists = array_map('intval', (array) $point['lists']);
        $new    = array_values(array_unique([...array_diff($lists, [$sourceId]), $targetId]));
        point_update($pdo, $userId, $pointId, ['lists' => $new]);
        $moved++;
    }

    list_delete($pdo, $userId, $sourceId);

    return $moved;
}

/** Comma-separated ids from a form field, deduplicated, without junk. */
function organize_ids(string $raw): array
{
    $ids = [];
    foreach (explode(',', $raw) as $part) {
        $part = trim($part);
        if ($part !== '' && ctype_digit($part)) {
            $ids[(int) $part] = true;
        }
    }
    return array_keys($ids);
}

/**
 * The own target list in a container — for the container check in the
 * lists dialog.
 *
 * Points never hang directly in a container, but in an OWN list mounted
 * there. The check on the container should still feel like a list check
 * (the call, 14.08.2026: "I want checkboxes") — so this function
 * takes care of the intermediate step instead of handing it to the user
 * as a separate work step:
 *
 *   1. If an own LEAF list already hangs in the container, it is the
 *      target. The check only shows in the dialog when nothing hangs,
 *      but a doubly submitted form must not create a second list.
 *   2. Otherwise: if there is an own leaf list named exactly like the
 *      container, THAT one is mounted — whoever keeps a list "Kartierung
 *      Nordhang" and checks the container of the same name means it.
 *   3. Otherwise a new list with the container's name comes into being
 *      and is mounted.
 *
 * share_create checks membership and overlap with already shared
 * branches — if that fails, its message comes onto the page as an error,
 * and nothing is half done: the list from step 3 would then be created,
 * but empty and unmounted — a second attempt picks it up again via
 * step 2.
 */
function organize_container_target(PDO $pdo, int $userId, int $containerId): int
{
    // Leaf means: no (living) sublist — only leaves carry points.
    $leafCondition = 'NOT EXISTS (SELECT 1 FROM map_lists k
                                    WHERE k.parent_id = l.id AND k.deleted_at IS NULL)';

    // ONLY THE VEHICLE counts as an existing target, not just any mounted
    // leaf: if an ORIGINAL list hangs in the container and you check the
    // container, the new points must not slip silently into the tended
    // original list — that one is filled via its own check. Before
    // migration 0051 the marking does not exist; then another leaf simply
    // comes into being via the name search below, and that is the lesser
    // damage.
    if (column_exists($pdo, 'map_lists', 'is_container_list')) {
        $stmt = $pdo->prepare(
            "SELECT cl.list_id
               FROM map_container_lists cl
               JOIN map_lists l ON l.id = cl.list_id AND l.deleted_at IS NULL
              WHERE cl.container_id = ? AND cl.user_id = ?
                AND l.is_container_list = 1 AND $leafCondition
              ORDER BY cl.list_id
              LIMIT 1"
        );
        $stmt->execute([$containerId, $userId]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }
    }

    $stmt = $pdo->prepare('SELECT name FROM map_containers WHERE id = ?');
    $stmt->execute([$containerId]);
    $name = $stmt->fetchColumn();
    if ($name === false) {
        throw new InvalidArgumentException(t('Diese Freigabe gibt es nicht (mehr).'));
    }

    // No auto list as the vehicle, not even via name equality: nothing
    // could be put into it (point_lists_set sifts it out), and the
    // container check would run into the void without anyone seeing it.
    $stmt = $pdo->prepare(
        "SELECT l.id FROM map_lists l
          WHERE l.user_id = ? AND l.name = ? AND l.deleted_at IS NULL AND $leafCondition"
        . (autolists_ready($pdo) ? ' AND l.filter IS NULL' : '') . '
          ORDER BY l.id
          LIMIT 1'
    );
    $stmt->execute([$userId, $name]);
    $listId = $stmt->fetchColumn();

    if ($listId !== false) {
        $listId = (int) $listId;
    } else {
        $listId = list_create($pdo, $userId, ['name' => $name]);
        // MARK AS A CONTAINER LIST (migration 0051): nobody created this
        // list deliberately — it is the vehicle of the container check and
        // shall appear only under its container, not under "Meine
        // Listen". ONLY the self-created one: whoever mounts their
        // existing list via step 2 keeps it deliberately and keeps it in
        // the tree.
        if (column_exists($pdo, 'map_lists', 'is_container_list')) {
            $pdo->prepare('UPDATE map_lists SET is_container_list = 1 WHERE id = ?')
                ->execute([$listId]);
        }
    }

    share_create($pdo, $userId, $containerId, $listId);
    return $listId;
}

/**
 * The leaves of one's own branches mounted in a container — that is
 * where the points that are "in the container" lie. For removal via the
 * container check: remove acts on exactly these leaves.
 *
 * @return int[]
 */
function organize_container_leaves(PDO $pdo, int $userId, int $containerId): array
{
    $stmt = $pdo->prepare(
        'SELECT list_id FROM map_container_lists WHERE container_id = ? AND user_id = ?'
    );
    $stmt->execute([$containerId, $userId]);
    $roots = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if ($roots === []) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT id, parent_id FROM map_lists WHERE user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$userId]);
    $children = [];
    foreach ($stmt->fetchAll() as $z) {
        $children[(int) ($z['parent_id'] ?? 0)][] = (int) $z['id'];
    }

    $leaves = [];
    $stack   = $roots;
    while ($stack !== []) {
        $id = array_pop($stack);
        if (($children[$id] ?? []) === []) {
            $leaves[] = $id;
            continue;
        }
        foreach ($children[$id] as $child) {
            $stack[] = $child;
        }
    }
    return $leaves;
}

/**
 * Mount whole lists from the selection into the container as ORIGINALS —
 * the growing answer of the confirmation prompt in the lists dialog.
 *
 * If the selection contains every living point assignment of a list, the
 * list is completely selected; contiguous complete branches are mounted
 * at their topmost node (one share instead of five, as when mounting via
 * the administration too). Empty leaves do not count (they contain
 * nothing one could share), container lists do not (vehicles of other
 * containers do not belong in this one), and whatever overlaps an
 * already mounted own branch stays out — its points ARE already in the
 * container.
 *
 * What comes back are the names of the mounted roots and the REST of the
 * selection: points that afterwards lie in no mounted own branch. For
 * those the caller creates the vehicle list — snapshot as before.
 *
 * The server recomputes the completeness itself instead of believing the
 * browser: between showing and submitting a point may have been added on
 * another device, and mounting a list that is no longer fully selected
 * as an original would silently share more than the selection.
 *
 * @return array{0: string[], 1: int[]}  [names of the mounted, rest]
 */
function organize_container_originals(PDO $pdo, int $userId, int $containerId, array $pointIds): array
{
    $selected  = array_fill_keys(array_map('intval', $pointIds), true);
    $withParents = column_exists($pdo, 'map_lists', 'parent_id');
    $withVehicle = column_exists($pdo, 'map_lists', 'is_container_list');

    $stmt = $pdo->prepare(
        'SELECT id, name' . ($withParents ? ', parent_id' : '')
        . ($withVehicle ? ', is_container_list' : '') . '
           FROM map_lists WHERE user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$userId]);
    $lists = [];
    $children = [];
    foreach ($stmt->fetchAll() as $z) {
        $id = (int) $z['id'];
        $lists[$id] = $z;
        $children[(int) ($z['parent_id'] ?? 0)][] = $id;
    }
    if ($lists === []) {
        return [[], array_keys($selected)];
    }

    // Living point assignments per list, in one go.
    $space = implode(',', array_fill(0, count($lists), '?'));
    $stmt  = $pdo->prepare(
        "SELECT pl.list_id, pl.point_id
           FROM map_point_lists pl
           JOIN map_points p ON p.id = pl.point_id AND p.deleted_at IS NULL
          WHERE pl.list_id IN ($space)"
    );
    $stmt->execute(array_keys($lists));
    $pointsPer = [];
    foreach ($stmt->fetchAll() as $z) {
        $pointsPer[(int) $z['list_id']][] = (int) $z['point_id'];
    }

    // A node is complete when every non-empty leaf below it is completely
    // selected — and at least one of them exists.
    $branchPoints = function (int $id) use (&$branchPoints, $children, $pointsPer): array {
        $all = $pointsPer[$id] ?? [];
        foreach ($children[$id] ?? [] as $child) {
            $all = array_merge($all, $branchPoints($child));
        }
        return $all;
    };
    $complete = function (int $id) use (&$complete, $children, $pointsPer, $selected): array {
        // Returns: [non-empty leaves, complete ones among them]
        if (($children[$id] ?? []) === []) {
            $own = $pointsPer[$id] ?? [];
            if ($own === []) {
                return [0, 0];
            }
            foreach ($own as $p) {
                if (!isset($selected[$p])) {
                    return [1, 0];
                }
            }
            return [1, 1];
        }
        $leaves = 0;
        $full     = 0;
        foreach ($children[$id] as $child) {
            [$b, $v] = $complete($child);
            $leaves += $b;
            $full     += $v;
        }
        return [$leaves, $full];
    };

    // Already mounted own branches of this container, as id sets.
    $stmt = $pdo->prepare(
        'SELECT list_id FROM map_container_lists WHERE container_id = ? AND user_id = ?'
    );
    $stmt->execute([$containerId, $userId]);
    $covered = [];    // pointId => true, already lies in the container
    $occupied    = [];    // listId  => true, belongs to a mounted branch
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rootId) {
        $stack = [(int) $rootId];
        while ($stack !== []) {
            $id = array_pop($stack);
            $occupied[$id] = true;
            foreach ($pointsPer[$id] ?? [] as $p) {
                $covered[$p] = true;
            }
            foreach ($children[$id] ?? [] as $child) {
                $stack[] = $child;
            }
        }
    }

    // Collect the topmost complete nodes and mount them.
    $names = [];
    $descend = function (int $parentId) use (&$descend, &$names, &$covered, &$occupied,
                                           $children, $lists, $complete, $branchPoints,
                                           $pdo, $userId, $containerId, $withVehicle): void {
        foreach ($children[$parentId] ?? [] as $id) {
            if ($withVehicle && (int) ($lists[$id]['is_container_list'] ?? 0) === 1) {
                continue;
            }
            // If the node overlaps a mounted branch, the level below
            // decides — the root itself must not hang a second time.
            if (isset($occupied[$id])) {
                continue;
            }
            [$leaves, $full] = $complete($id);
            if ($leaves > 0 && $leaves === $full) {
                try {
                    share_create($pdo, $userId, $containerId, $id);
                } catch (InvalidArgumentException) {
                    // Overlap or race — then not as an original after all;
                    // the points fall into the rest and take the snapshot
                    // route.
                    $descend($id);
                    continue;
                }
                $names[] = (string) $lists[$id]['name'];
                foreach ($branchPoints($id) as $p) {
                    $covered[$p] = true;
                }
                continue;
            }
            $descend($id);
        }
    };
    $descend(0);

    $rest = [];
    foreach (array_keys($selected) as $p) {
        if (!isset($covered[$p])) {
            $rest[] = $p;
        }
    }
    return [$names, $rest];
}

/**
 * Apply the two deltas of the lists dialog to every point.
 *
 * Per point: load its own assignment, subtract remove, add add — whatever
 * the selection does not share stays untouched. Unchanged points are
 * skipped so they get no stand bump (otherwise every device would load
 * points on the next sync at which nothing happened).
 *
 * Writing goes through point_update: that checks the owner, updates the
 * field times and pulls the species table along — a bare
 * point_lists_set would leave all that out.
 *
 * @return int[] the points that really changed
 */
function organize_lists_apply(PDO $pdo, int $userId, array $pointIds,
                                array $add, array $remove): array
{
    // Sift the additions down to the own leaves beforehand — point_lists_set
    // would do that per point itself, but silently AFTER the comparison:
    // a foreign or parent leaf smuggled in would otherwise count as a
    // "change" every time without ever being one. Auto lists fall through
    // the sieve as well — they are no target.
    $withAuto = autolists_ready($pdo);
    if ($add !== []) {
        $space = implode(',', array_fill(0, count($add), '?'));
        $stmt  = $pdo->prepare(
            "SELECT l.id FROM map_lists l
              WHERE l.user_id = ? AND l.deleted_at IS NULL AND l.id IN ($space)
                AND NOT EXISTS (SELECT 1 FROM map_lists kind
                                 WHERE kind.parent_id = l.id AND kind.deleted_at IS NULL)"
            . ($withAuto ? ' AND l.filter IS NULL' : '')
        );
        $stmt->execute([$userId, ...$add]);
        $add = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // Auto lists stay out of the COMPARISON too: remove can carry their ids
    // (the container check collects all mounted leaves, one of them may
    // hang among those), but point_lists_set preserves their rows
    // anyway — if they stood in the comparison, every such order would
    // count as a "change" without ever being one, stand bump included.
    $autoIds = [];
    if ($withAuto) {
        $stmt = $pdo->prepare('SELECT id FROM map_lists WHERE user_id = ? AND filter IS NOT NULL');
        $stmt->execute([$userId]);
        $autoIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $changed = [];
    foreach ($pointIds as $pointId) {
        $point = point_load($pdo, $userId, $pointId);
        if ($point === null || $point['deleted_at'] !== null) {
            continue;   // foreign or already in the trash — neither is prey
        }

        $current = array_map('intval', (array) $point['lists']);
        if ($autoIds !== []) {
            $current = array_values(array_diff($current, $autoIds));
        }
        $new    = array_values(array_unique([...array_diff($current, $remove), ...$add]));

        $a = $current; sort($a);
        $b = $new;    sort($b);
        if ($a === $b) {
            continue;
        }

        point_update($pdo, $userId, $pointId, ['lists' => $new]);
        $changed[] = $pointId;
    }
    // The IDS, not merely the count: since the container checks make
    // their own partial calls (rest into the vehicle list), the message
    // counts the union — and the same id from two calls must not count
    // twice.
    return $changed;
}

/**
 * Write a tour name onto points — the one operation behind renaming,
 * merging, deleting and creating a tour.
 *
 * THROUGH point_update, one point at a time, like every other mass action
 * here: that way each point gets its stand bump and travels to the other
 * devices. A single UPDATE across the table would be one statement and
 * would leave every phone believing the old name until its next full
 * load.
 *
 * @param int[] $pointIds
 * @return int how many really changed
 */
function organize_tour_apply(PDO $pdo, int $userId, array $pointIds, ?string $to,
                             ?string $tourId = null): int
{
    if (!column_exists($pdo, 'map_points', 'tour')) {
        throw new InvalidArgumentException(t('Die Touren brauchen die Migration 0071 — sie ist noch nicht eingespielt.'));
    }
    $n = 0;
    /* THE IDENTITY TRAVELS WITH THE NAME (migration 0079): a point that
       joins a tour takes ITS id, one that leaves loses both. Without this
       the two would drift apart — a point labelled „Tagestour" carrying
       the id of a different outing is a point in two tours at once. */
    $withId = column_exists($pdo, 'map_points', 'tour_id');
    foreach ($pointIds as $pointId) {
        $change = ['tour' => $to];
        if ($withId) {
            $change['tour_id'] = $to === null ? null : $tourId;
        }
        if (point_update($pdo, $userId, (int) $pointId, $change)) {
            $n++;
        }
    }
    return $n;
}

/**
 * Put one's own points into an excursion one leads — or take them out.
 *
 * OWN POINTS ONLY. A leader files his own finds; somebody else's stay
 * where their owner put them, whoever is chosen in the table. The WHERE
 * carries the owner, so a foreign id in the list changes nothing and
 * counts nothing.
 *
 * LEADERSHIP IS THE GATE, not the window: excursion_of_leader() refuses
 * an excursion one does not lead; the time window is reported, not
 * enforced (see the action). The leader gets his guest row on the way,
 * as mapping along gives it — that is how he stands in the participant
 * list and how the report knows the finder. excursion_guest_id stays
 * empty on the point itself, exactly as on a point mapped along: the
 * account is the finder, the guest row only the listing.
 *
 * TOUCHED, so the devices notice: the bucket fingerprints read
 * touched_at, and a point whose excursion changed must arrive anew — on
 * the leader's own device and, through the excursion's buckets, on the
 * co-leaders'.
 *
 * @return array{n: int, outside: int, name: string}
 */
function organize_excursion_apply(PDO $pdo, int $userId, array $pointIds, ?int $excursionId): array
{
    if (!excursions_ready($pdo) || !column_exists($pdo, 'map_points', 'excursion_id')) {
        throw new InvalidArgumentException(t('Exkursionen brauchen die Migration 0068 — sie ist noch nicht eingespielt.'));
    }
    $ids = array_values(array_filter(array_map('intval', $pointIds), static fn(int $i): bool => $i > 0));
    if ($ids === []) {
        return ['n' => 0, 'outside' => 0, 'name' => ''];
    }
    $e = null;
    if ($excursionId !== null) {
        $e = excursion_of_leader($pdo, $userId, $excursionId);
        if (excursion_guests_ready($pdo)) {
            // The account row itself, not user_current(): this runs from
            // the tests without a session, and the guest row wants a name.
            $u = $pdo->prepare('SELECT * FROM map_users WHERE id = ?');
            $u->execute([$userId]);
            $account = $u->fetch() ?: ['id' => $userId];
            excursion_join_as_user($pdo, $e, $account);
        }
    }
    $space = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = $pdo->prepare(
        "UPDATE map_points
            SET excursion_id = ?, excursion_guest_id = NULL, touched_at = NOW()
          WHERE user_id = ? AND deleted_at IS NULL AND id IN ($space)
            AND NOT (excursion_id <=> ?)"
    );
    $stmt->execute(array_merge([$excursionId, $userId], $ids, [$excursionId]));
    $n = $stmt->rowCount();

    $outside = 0;
    if ($e !== null && $n > 0) {
        $q = $pdo->prepare(
            "SELECT COUNT(*) FROM map_points
              WHERE user_id = ? AND id IN ($space) AND excursion_id = ?
                AND COALESCE(found_at, created_at) NOT BETWEEN ? AND ?"
        );
        $q->execute(array_merge([$userId], $ids, [$excursionId, $e['starts_at'], $e['ends_at']]));
        $outside = (int) $q->fetchColumn();
    }
    return ['n' => $n, 'outside' => $outside, 'name' => (string) ($e['name'] ?? '')];
}

/** Is the name registry of migration 0073 there? Code goes live first. */
/**
 * Every picture of the rows shown, voucher photos AND cloud recordings, in
 * one order per point — the gallery's (30.09.2026: „die
 * Cloud-Aufnahmen auch dort zeigen"). The Bilder column and its viewer
 * knew only map_photos, and a find's pictures are mostly recordings: spore
 * prints and stacks from the proper camera, which come in through
 * „Auswählen". On Anna's demo find the viewer said „1 / 4" while the
 * gallery held more.
 *
 * KEYS AS THE GALLERY SPEAKS THEM — „photo:63", „cloud:17" — in the order
 * sort_order gives (migration 0148: one number space for both), ties to
 * the photo, then the lower id. Before the column the recordings stand
 * behind the photos, oldest first.
 *
 * ONE SWEEP FOR ALL ROWS, own and foreign, deliberately without an owner
 * scope — the foreign sweep's reasoning: whoever gets the row delivered
 * may know its pictures exist, and pages/photo.php and cloud_thumb.php
 * ask the rights question again for every single image.
 *
 * @param int[] $pointIds
 * @return array<int, string[]> point id => keys; points without pictures absent
 */
function organize_pictures(PDO $pdo, array $pointIds): array
{
    if ($pointIds === [] || !photos_ready($pdo)) {
        return [];
    }
    $space = implode(',', array_fill(0, count($pointIds), '?'));
    $rows = [];
    $stmt = $pdo->prepare("SELECT id, point_id, sort_order FROM map_photos WHERE point_id IN ($space)");
    $stmt->execute($pointIds);
    foreach ($stmt as $r) {
        $rows[] = [(int) $r['point_id'], (int) $r['sort_order'], 0, (int) $r['id']];
    }
    if (table_exists($pdo, 'map_cloud_files')) {
        $sortable = column_exists($pdo, 'map_cloud_files', 'sort_order');
        $stmt = $pdo->prepare(
            'SELECT id, point_id' . ($sortable ? ', sort_order' : '') . "
               FROM map_cloud_files WHERE point_id IN ($space)"
        );
        $stmt->execute($pointIds);
        foreach ($stmt as $r) {
            // Without a place of its own: behind every photo, by id.
            $rows[] = [(int) $r['point_id'], $sortable ? (int) $r['sort_order'] : 65536, 1, (int) $r['id']];
        }
    }
    sort($rows);
    $out = [];
    foreach ($rows as [$pointId, , $kind, $id]) {
        $out[$pointId][] = ($kind === 0 ? 'photo:' : 'cloud:') . $id;
    }
    return $out;
}

function organize_tours_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_tours');
}

/** Put a name into the registry — an empty tour, waiting for its points. */
function organize_tour_register(PDO $pdo, int $userId, string $name): void
{
    if (!organize_tours_ready($pdo)) {
        throw new InvalidArgumentException(t('Leere Touren brauchen die Migration 0073 — sie ist noch nicht eingespielt.'));
    }
    $pdo->prepare('INSERT IGNORE INTO map_tours (user_id, name, created_at) VALUES (?, ?, NOW())')
        ->execute([$userId, $name]);
}

/** The registered names — the tours that exist without (yet) having points. */
function organize_tour_names(PDO $pdo, int $userId): array
{
    if (!organize_tours_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT name FROM map_tours WHERE user_id = ? ORDER BY name');
    $stmt->execute([$userId]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Every point of MINE carrying this tour gets the new name — or none,
 * when $to is empty. Renaming, merging and deleting in one function; see
 * the 'tour_set' case for why they are the same thing.
 *
 * @return int how many points changed
 */
function organize_tour_set(PDO $pdo, int $userId, string $from, string $to,
                           string $fromId = '', string $toId = ''): int
{
    if (!column_exists($pdo, 'map_points', 'tour')) {
        throw new InvalidArgumentException(t('Die Touren brauchen die Migration 0071 — sie ist noch nicht eingespielt.'));
    }
    $withId  = column_exists($pdo, 'map_points', 'tour_id');
    $fromId = point_tour_id($fromId);
    $toId   = point_tour_id($toId);

    /* WHICH POINTS ARE MEANT — by IDENTITY where there is one (migration
       0079), by name only for what predates it. That is the whole reason
       for the id: since 24.08.2026 two outings may share a name, and
       renaming „Tagestour" must then rename ONE of them, not both. */
    if ($withId && $fromId !== null) {
        $stmt = $pdo->prepare(
            'SELECT id FROM map_points WHERE user_id = ? AND tour_id = ? AND deleted_at IS NULL');
        $stmt->execute([$userId, $fromId]);
    } else {
        // Living points only: what lies in the trash keeps its label and
        // brings it back along when it is restored.
        $stmt = $pdo->prepare(
            'SELECT id FROM map_points WHERE user_id = ? AND tour = ? AND deleted_at IS NULL'
            . ($withId ? ' AND tour_id IS NULL' : ''));
        $stmt->execute([$userId, $from]);
    }

    /* MERGING MEANS TAKING THE TARGET'S IDENTITY, renaming means keeping
       one's own: the caller says which by passing a target id or not. */
    $n = organize_tour_apply($pdo, $userId,
        array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)),
        $to === '' ? null : mb_substr($to, 0, 120),
        $toId ?? $fromId);

    /* THE REGISTRY FOLLOWS ALONG, or an emptied tour would rise from the
       dead as a nameless entry on the next visit: renaming carries the
       name over, deleting takes it out. Guarded, because the registry
       arrives one migration later than the tours themselves. */
    if (organize_tours_ready($pdo)) {
        $pdo->prepare('DELETE FROM map_tours WHERE user_id = ? AND name = ?')
            ->execute([$userId, $from]);
        if ($to !== '') {
            organize_tour_register($pdo, $userId, mb_substr($to, 0, 120));
        }
    }
    return $n;
}

/**
 * The selection into the trash — via point_delete per point, so each
 * one gets its stand bump and vanishes on all devices. Nothing here is
 * final: the trash remains the safety net.
 *
 * @return int how many really moved
 */
function organize_to_trash(PDO $pdo, int $userId, array $pointIds): int
{
    $n = 0;
    foreach ($pointIds as $pointId) {
        if (point_delete($pdo, $userId, $pointId)) {
            $n++;
            continue;
        }
        // The leader's right at the excursion reference (stage 5), or
        // the participant's at his own old guest find (stage 7): either
        // way it goes into the GUEST ACCOUNT'S trash — the leader tidies
        // his excursion, the participant his memory, and nothing is
        // beyond recall.
        $owner = excursion_point_leader_scope($pdo, $userId, $pointId)
            ?? excursion_point_participant_scope($pdo, $userId, $pointId);
        if ($owner !== null && point_delete($pdo, $owner, $pointId)) {
            $n++;
        }
    }
    return $n;
}

/**
 * One excursion find into the foreign index — the shared shape of the
 * leader's and the participant's view (stage 5/7). Foreign in ownership,
 * editable in right; the owner label is the GUEST'S name. Raw rows come
 * through here — point_haystack takes both shapes, the browser wants the
 * decoded extra block.
 */
/**
 * The ranks for a batch of raw point rows — over the genus of their title
 * species (includes/taxonomy.php).
 *
 * Its own little function because TWO loops need it, and because a raw row
 * carries no genus: it sits in map_point_species, and asking per point
 * would be one query per find.
 *
 * @return array<int, array> point id => ranks
 */
function organize_taxonomy_for(PDO $pdo, array $ids): array
{
    if ($ids === [] || !column_exists($pdo, 'map_point_species', 'genus')) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT point_id, genus, source FROM map_point_species
          WHERE point_id IN ($marks) AND field = '' AND genus <> ''");
    $stmt->execute($ids);
    $keyOf = [];
    $entries = [];
    foreach ($stmt as $z) {
        $key = taxonomy_key((string) $z['genus'], (string) ($z['source'] ?? ''));
        $keyOf[(int) $z['point_id']] = $key;
        $entries[$key] = ['genus' => $z['genus'], 'source' => $z['source'] ?? ''];
    }
    $ranks = taxonomy_of_genera($pdo, array_values($entries));
    $out = [];
    foreach ($keyOf as $pointId => $key) {
        if (isset($ranks[$key])) {
            $out[$pointId] = $ranks[$key];
        }
    }
    return $out;
}

function organize_excursion_point(array &$foreignIndex, array $p,
                                  ?array $taxonomy = null): void
{
    if (isset($foreignIndex[$p['id']])) {
        $foreignIndex[$p['id']]['editable'] = true;
        return;
    }
    $foreignIndex[$p['id']] = [
        'id'          => (int) $p['id'],
        'title'       => (string) ($p['title'] ?? ''),
        'lists'       => [],
        'found_at'    => $p['found_at'],
        'lat'         => (float) $p['lat'],
        'lon'         => (float) $p['lon'],
        'owner'       => (string) ($p['guest_name'] ?? t('Gast')),
        'editable'    => true,
        'note'           => $p['note'],
        'region'         => $p['region'],
        'country'        => $p['country'],
        'mtb'            => $p['mtb'],
        'mtb_quadrant'   => $p['mtb_quadrant'],
        'protected_area' => $p['protected_area'],
        'elevation_m'    => $p['elevation_m'],
        'extra'          => point_extra_read($p['extra'] ?? null) ?: new stdClass(),
        'photos'      => [],
        'tag'         => substr((string) ($p['found_at'] ?? $p['created_at'] ?? ''), 0, 10) ?: null,
        'municipality' => $p['municipality'],
        'rl'          => [],
        'dgfm'        => $p['dgfm'] ?? [],
        // A GUEST'S FIND IS FILTERED LIKE ANY OTHER. Left out, every
        // excursion find would drop out of the systematic filter without a
        // visible reason — the same gap that had to be closed for the
        // shared points in container.php.
        'taxonomy'    => $taxonomy,
        'find_number' => $p['find_number'] ?? null,
    ];
}

/**
 * The hidden state[] fields of every organize form — ONE list, printed
 * wherever a form needs it. It stood copied into seven forms, and the
 * copy bit on 24.08.2026: a new form took the list from an old
 * neighbour, two state fields were missing, and submitForm's loud stop
 * (rightly) refused every save. The list lives once now; whoever adds a
 * state field adds it here and is done.
 */
function organize_state_fields(): array
{
    return ['list', 'search', 'from', 'to', 'no_time', 'unidentified', 'marks', 'certainty',
            'filter_open', 'rl', 'edibility', 'responsibility', 'areas', 'regions', 'municipalities', 'mtb',
            'elevation_from', 'elevation_to',
            'taxonomy_kingdom', 'taxonomy_phylum', 'taxonomy_class',
            'taxonomy_order', 'taxonomy_family', 'taxonomy_genus',
            'tours', 'pane'];
}

function organize_state_inputs(): void
{
    foreach (organize_state_fields() as $field) {
        echo '<input type="hidden" name="state[' . $field . ']" value="">' . "\n";
    }
}

/** The view. $result comes from organize_process(). */
function organize_render(PDO $pdo, int $userId, array $result): void
{
    $target = url('pages/administration.php') . '#organize';

    notice('error', $result['error'], true);
    if ($result['message'] !== null) {
        notice('success', $result['message'], true);
    }

    // The data for the browser: the tree and a lean point list. Do NOT
    // pass point_prepare through — photos, field times and extra
    // would weigh, with thousands of points, a multiple of what the
    // table needs.
    $lists = array_map(static fn(array $l): array => [
        'id'        => (int) $l['id'],
        'name'      => (string) $l['name'],
        'color'     => (string) $l['color'],
        // The list's own drawing, as the sidebar shows it (shapes.php).
        'symbol'    => (string) ($l['symbol'] ?? 'circle'),
        'parent_id' => $l['parent_id'],
        'folder_id' => $l['folder_id'] ?? null,
        'is_species' => (bool) $l['is_species'],
        // Container lists (migration 0051) do not stand in the own tree —
        // only under their container.
        'is_container_list' => (bool) ($l['is_container_list'] ?? false),
        // Auto lists (migration 0052) stand in the tree but are no
        // target: the dialog leaves them out, the pencil shows fewer
        // actions.
        'is_autolist' => (bool) ($l['is_autolist'] ?? false),
        // The list's own fields — for "Feld setzen" in the browser. As on
        // the map: only the root of a branch carries fields.
        'fields'    => array_map(static fn(array $f): array => [
            'name' => $f['name'], 'type' => $f['type'], 'options' => $f['options'],
        ], $l['fields']),
    ], lists_load($pdo, $userId));

    /* NO 'haystack' ANY MORE (25.08.2026). It used to ride along as
       finished searchable text, and the note here argued it was cheaper
       than its ingredients — true when it was written, and overtaken
       since: the payload has meanwhile grown to carry EVERY one of those
       ingredients anyway, because the table's columns show them. Counted
       on a stock of 1.640 points: 130 KB of 786 KB, 17 %, and not one
       byte of it new. The browser now builds the text itself with
       pointHaystack() from assets/js/haystack.js — the same builder the
       sidebar has always used. */
    $points = array_map(static fn(array $p): array => [
        'id'          => $p['id'],
        'title'       => $p['title'],
        'lists'      => $p['lists'],
        'found_at' => $p['found_at'],
        // For the place filter (drawn areas) — two numbers per point, the
        // cheapest field of the whole payload.
        'lat'         => $p['lat'],
        'lon'         => $p['lon'],
        // The choosable columns of the table (22.08.2026): what
        // a column can show must come along per field — the haystack
        // holds the same text, but mashed into one search string. Photos
        // and field times stay out; they are what made point_prepare too
        // heavy for this payload in the first place.
        'note'           => $p['note'],
        'region'         => $p['region'],
        'country'        => $p['country'],
        'mtb'            => $p['mtb'],
        'mtb_quadrant'   => $p['mtb_quadrant'],
        'protected_area' => $p['protected_area'],
        'elevation_m'    => $p['elevation_m'],
        'extra'          => $p['extra'],
        // Only the IDS — the picture column fetches its thumbnails from
        // pages/photo.php per id, and the prepared photo objects would
        // multiply a payload that is lean on purpose.
        'photos'         => array_column((array) ($p['photos'] ?? []), 'id'),
        // The day for the time filter — find time, creation time as the
        // substitute, the same rule as dayOf() in assets/js/timerange.js.
        // Sent along ready-made, because the point data here otherwise
        // carries no created_at.
        'tag'         => substr((string) ($p['found_at'] ?? $p['created_at'] ?? ''), 0, 10) ?: null,
        // The named outing (migration 0071) — the Touren pane of the left
        // column lists and filters by it.
        'tour'        => $p['tour'],
        // The tour's identity (migration 0079) — the browser groups by
        // it, so two outings called „Tagestour" stay two.
        'tour_id'     => $p['tour_id'] ?? null,
        // The find number for its table column — minted, or null: then the
        // id IS the number, and the browser reads it off the point (the
        // same rule as the window's fact row, fillDetailUneditable).
        'find_number' => $p['find_number'],
        // For the time filter's day list: it names the municipality for
        // each day, because when leafing back you are not looking for
        // "when" but for "where" (the call, 14.08.2026).
        'municipality'    => $p['municipality'],
        // The threat categories of the title species — already looked up
        // by points_load() (species_endangerment), only passed through
        // here. Mostly an empty list; very few finds carry a rating.
        'rl'          => $p['red_list'],
        // The DGfM marks beside it, looked up the same way (dgfm_marks.php).
        'dgfm'        => $p['dgfm'] ?? [],
        // The cloud-recording count — the „bearbeitet" material filter
        // (workedMatches) reads it, like the sidebar's mark.
        'recordings'  => $p['recordings'] ?? 0,
        // Kingdom, phylum, class, order and family of the title species —
        // derived in includes/taxonomy.php and handed on unchanged, like
        // the endangerment beside it.
        'taxonomy'    => $p['taxonomy'] ?? null,
    ], points_load($pdo, $userId));

    // THE CONTAINERS AS THEIR OWN SECTION IN THE TREE — the call,
    // 14.08.2026: when organizing you also want to see what hangs in the
    // containers (your own and other people's) and sort into them.
    // "Sorting into the container" means in this model: putting a point
    // into the OWN list mounted there — points never hang directly in a
    // container.
    //
    // PER CONTAINER THE POINT IDS SPLIT into own and foreign, not
    // computed client-side across the branches: container_points()
    // applies the birth filter, and only that way does the tree count
    // what the container REALLY contains — not everything the list at
    // home has. The foreign points themselves go along once, collected
    // (a point can arrive via two containers; its lists are then
    // united), lean like the own ones.
    $shared    = [];
    $foreignIndex = [];
    if (container_ready($pdo)) {
        foreach (container_mine($pdo, $userId) as $c) {
            $own = $foreign = [];
            foreach (container_points($pdo, (int) $c['id'], [], $userId) as $p) {
                if ((int) $p['owner_id'] === $userId) {
                    $own[] = $p['id'];
                    continue;
                }
                $foreign[] = $p['id'];
                if (isset($foreignIndex[$p['id']])) {
                    $foreignIndex[$p['id']]['lists'] = array_values(array_unique(
                        array_merge($foreignIndex[$p['id']]['lists'], $p['lists'])));
                } else {
                    $foreignIndex[$p['id']] = [
                        'id'          => $p['id'],
                        'title'       => (string) ($p['title'] ?? ''),
                        'lists'      => $p['lists'],
                        'found_at' => $p['found_at'],
                        'lat'         => (float) $p['lat'],
                        'lon'         => (float) $p['lon'],
                        'owner'    => $p['owner'],
                        // The same column fields as on one's own points —
                        // a foreign row under the same head must fill the
                        // same cells. container_points() runs its rows
                        // through point_prepare too, so extra is already
                        // decoded — a second point_extra_read here was a
                        // TypeError that tore the whole page off mid-render.
                        'note'           => $p['note'] ?? null,
                        'region'         => $p['region'] ?? null,
                        'country'        => $p['country'] ?? null,
                        'mtb'            => $p['mtb'] ?? null,
                        'mtb_quadrant'   => $p['mtb_quadrant'] ?? null,
                        'protected_area' => $p['protected_area'] ?? null,
                        'elevation_m'    => $p['elevation_m'] ?? null,
                        'extra'          => $p['extra'] ?? new stdClass(),
                        'tag'         => substr((string) ($p['found_at'] ?? $p['created_at'] ?? ''), 0, 10) ?: null,
                        'municipality'    => $p['municipality'] ?? null,
                        'photos'      => [],
                        'rl'          => $p['red_list'] ?? [],
                        'dgfm'        => $p['dgfm'] ?? [],
                        'taxonomy'    => $p['taxonomy'] ?? null,
                        // The find number travels for foreign finds too
                        // (23.08.2026): it is the shorthand
                        // friends cite to each other — "Nummer 544" beats
                        // describing species, day and place.
                        'find_number' => $p['find_number'] ?? null,
                    ];
                }
            }
            $shared[] = [
                'id'           => (int) $c['id'],
                'name'         => $c['name'],
                'owner'     => $c['owner_name'],
                'is_mine'      => (int) $c['owner_user_id'] === $userId,
                'own_points'     => $own,
                'foreign_points' => $foreign,
                'shares'    => array_map(static fn(array $f): array => [
                    'display_name' => $f['display_name'],
                    // "meine" instead of one's own name at the branch —
                    // decided here, so the browser needs no user id.
                    'is_mine'      => (int) $f['user_id'] === $userId,
                    'lists'      => array_map(static fn(array $l): array => [
                        'id'        => (int) $l['id'],
                        'name'      => $l['name'],
                        'color'     => $l['color'],
                        'symbol'    => (string) ($l['symbol'] ?? 'circle'),
                        'parent_id' => $l['parent_id'] === null ? null : (int) $l['parent_id'],
                        // Branch-rooted at the owner (list_branch_root) —
                        // the find-number column reads it off the leaf.
                        'is_species' => (bool) ($l['is_species'] ?? false),
                    ], $f['lists']),
                ], container_shares($pdo, (int) $c['id'], $userId)),
            ];
        }
    }

    // THE LEADER'S VIEW (stage 5, "eine Exkursion würde ich
    // wie eine Tour behandeln"): every excursion the user leads — main
    // or co — stands in the Touren pane, and ALL its finds come along.
    // They are foreign (the guest account owns them), but EDITABLE: the
    // leader's right hangs on the excursion reference, and the browser
    // learns it through the flag instead of guessing from ownership.
    // The owner label is the GUEST'S name, not the account's — "von
    // Paula" answers the question the row actually raises.
    $excursions = [];
    if (excursions_ready($pdo) && excursion_guests_ready($pdo)) {
        $guestNames = [];
        foreach (excursions_mine($pdo, $userId) as $e) {
            $stmt = $pdo->prepare(
                'SELECT p.*, g.name AS guest_name
                   FROM map_points p
                   LEFT JOIN map_excursion_guests g ON g.id = p.excursion_guest_id
                  WHERE p.excursion_id = ? AND p.deleted_at IS NULL
                  ORDER BY p.updated_at DESC');
            $stmt->execute([(int) $e['id']]);
            $rows = $stmt->fetchAll();
            // The ranks in one sweep, not per find — see
            // organize_taxonomy_for().
            $taxonomy = organize_taxonomy_for($pdo, array_map(
                static fn(array $r): int => (int) $r['id'], $rows));
            $ids = [];
            foreach ($rows as $p) {
                $ids[] = (int) $p['id'];
                /* ONE'S OWN FINDS STAY ONE'S OWN (24.08.2026, since a
                   logged-in participant maps into his own stock): a
                   leader who took part himself would otherwise see his
                   find twice — once as his, once as a stranger's, and
                   labelled „von Gast" at that. The id stays in the list,
                   so it counts and can be chosen; the browser looks it up
                   among the own points (see matches in organize.js). */
                if ((int) $p['user_id'] === $userId) {
                    continue;
                }
                organize_excursion_point($foreignIndex, $p, $taxonomy[(int) $p['id']] ?? null);
            }
            $excursions[] = [
                'id'     => (int) $e['id'],
                'name'   => (string) $e['name'],
                'tag'    => substr((string) $e['starts_at'], 0, 10),
                'phase'  => excursion_phase($e),
                'points' => $ids,
            ];
        }
    }

    // THE PARTICIPANT'S VIEW (stage 7): excursions this account took part
    // in as a GUEST — the own old finds, tied via the e-mail claim at
    // login. Editable like the leader's view, only scoped to one's own:
    // the participant scope carries field and trash actions. Where one
    // both leads and took part, the leader's entry wins — it already
    // holds every find.
    if (excursion_attribution_ready($pdo)) {
        $led = array_flip(array_column($excursions, 'id'));
        foreach (excursions_participated($pdo, $userId) as $part) {
            if (isset($led[(int) $part['id']])) {
                continue;
            }
            $stmt = $pdo->prepare(
                'SELECT p.*, ? AS guest_name FROM map_points p
                  WHERE p.excursion_id = ? AND p.excursion_guest_id = ?
                    AND p.deleted_at IS NULL
                  ORDER BY p.updated_at DESC');
            $stmt->execute([(string) $part['guest_name'], (int) $part['id'], (int) $part['guest_id']]);
            $rows = $stmt->fetchAll();
            // Its own batch — without it $taxonomy would still hold the
            // ranks of the LAST excursion of the loop above, and every
            // find here would be labelled with a stranger's systematics.
            $taxonomy = organize_taxonomy_for($pdo, array_map(
                static fn(array $r): int => (int) $r['id'], $rows));
            $ids = [];
            foreach ($rows as $p) {
                $ids[] = (int) $p['id'];
                organize_excursion_point($foreignIndex, $p, $taxonomy[(int) $p['id']] ?? null);
            }
            $excursions[] = [
                'id'     => (int) $part['id'],
                'name'   => (string) $part['name'],
                'tag'    => substr((string) $part['starts_at'], 0, 10),
                'phase'  => excursion_phase($part),
                'points' => $ids,
                'as_guest' => true,
            ];
        }
    }

    // The photo ids of the FOREIGN rows, one sweep — deliberately without
    // an owner scope: whoever gets the row delivered may know its
    // pictures exist, and pages/photo.php asks the rights question again
    // for every single image before a byte leaves the server.
    if ($foreignIndex !== [] && photos_ready($pdo)) {
        $space = implode(',', array_fill(0, count($foreignIndex), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, point_id FROM map_photos WHERE point_id IN ($space)
              ORDER BY sort_order, id");
        $stmt->execute(array_keys($foreignIndex));
        foreach ($stmt as $z) {
            $foreignIndex[(int) $z['point_id']]['photos'][] = (int) $z['id'];
        }
    }

    /* AND ALL THEIR PICTURES, recordings included, as the viewer walks
       them (organize_pictures). 'photos' stays as it was: a page still
       holding the organize.js of before reads only that, and the delete
       goes by it. */
    $pictures = organize_pictures($pdo, [
        ...array_map(static fn(array $p): int => (int) $p['id'], $points),
        ...array_map('intval', array_keys($foreignIndex)),
    ]);
    foreach ($points as &$p) {
        if (isset($pictures[(int) $p['id']])) {
            $p['pictures'] = $pictures[(int) $p['id']];
        }
    }
    unset($p);
    foreach ($foreignIndex as $pointId => &$p) {
        if (isset($pictures[(int) $pointId])) {
            $p['pictures'] = $pictures[(int) $pointId];
        }
    }
    unset($p);
    ?>

<?php /* NO LESSON AT THE TOP ANY MORE (25.08.2026). Six lines
     explained the three-state checkmark, the trash and the syncing to
     everyone who came here for the hundredth time — and they cost the
     table the height they took. A help of its own is coming; until then
     the tools explain themselves where they stand (the dialog's own
     text, the buttons' titles). */ ?>

<div class="organize">
    <?php /* ordnen-links carried no class for a long time — it came in when
         the header row was to stretch to the divider and had to be
         addressable for that. */ ?>
    <div class="organize-left">
        <div class="column-header">
            <?php /* TWO PANES, ONE COLUMN (23.08.2026): Listen or
                 Touren — the active pane ALONE governs the table, and
                 switching resets the other pane's choice. Two filters at
                 once would intersect to something nobody holds in their
                 head; the reset makes the switch mean what it says. */ ?>
            <button type="button" class="organize-pane-tab is-active"
                    id="organize-pane-lists" aria-pressed="true"><?= icon('list') ?><?= h(t('Listen')) ?></button>
            <button type="button" class="organize-pane-tab"
                    id="organize-pane-tours" aria-pressed="false"><?= icon('hiker') ?><?= h(t('Touren')) ?></button>
            <?php /* How many rows in the ACTIVE pane are currently checked —
                 the same meaning as the number on the sidebar's lists tab
                 (leiste-reiter, map.js): selected, not present in
                 total. 0 means "Alle Punkte", not "no list". */ ?>
            <span class="tab-count" id="organize-list-count"></span>
        </div>
        <?php /* The structural actions (rename, move, merge, delete) hang on
             the pencil of each row — organize.js builds them as a menu.
             A button bar used to stand here, tied to THE selected list;
             since several can be selected, it no longer exists. */ ?>
        <nav class="organize-tree" id="organize-tree" aria-label="<?= h(t('Listen')) ?>"></nav>
        <nav class="organize-tree" id="organize-tours" aria-label="<?= h(t('Touren')) ?>" hidden></nav>
    </div>
    <div class="organize-right">
        <div class="column-header">
            <h3><?= icon('crosshair') ?><?= h(t('Punkte')) ?></h3>
            <?php /* SELECTED OF SHOWN ("0/5"). This used to stand in a row of
                 its own above the table, together with two buttons ("all
                 wählen", "Auswahl leeren") — the header checkbox on the
                 left can do the buttons itself (on for all, again for
                 empty), what remains are the two numbers, here at the
                 column header. */ ?>
            <span class="tab-count" id="organize-point-count"></span>
        </div>
        <?php /* The same fold button as in the sidebar (filter.js): the whole
             filter area goes away when you do not need it, and the button
             says whether something applies regardless. */ ?>
        <div class="filter-header">
            <button type="button" class="filter-chip filter-button" id="organize-filter-open">
                <?= icon('fold-triangle', ['class' => 'collapse-triangle']) ?><?= icon('filter') ?><?= h(t('Filter')) ?><span class="filter-button-count"></span>
            </button>
            <?php /* The table's columns are the user's choice
                 (22.08.2026): which ones, and in which order. The chip sits
                 beside the filter because both shape the same table — one
                 sifts the rows, one picks the columns. */ ?>
            <button type="button" class="filter-chip filter-button" id="organize-columns-open">
                <?= icon('sliders') ?><?= h(t('Spalten')) ?>
            </button>
        </div>

        <div class="organize-filter" id="organize-filter-section" hidden>
            <div class="filter-group" data-group="text">
                <h4 class="filter-title"><?= h(t('Text')) ?></h4>
                <?php /* The rows are built by assets/js/filter.js: text with
                     "enthält" / "enthält nicht" behind it, across ALL
                     fields. A single field "Titel durchsuchen" used to
                     stand here — whoever searched the sidebar for a
                     substrate and typed the same thing here got nothing
                     and looked for the mistake in themselves. */ ?>
                <div class="filter-rows" id="organize-search-rows"></div>
            </div>

            <div class="filter-group" data-group="time">
                <h4 class="filter-title"><?= h(t('Zeit')) ?></h4>
                <?php /* Day list, both date fields and the clear button are
                     built by assets/js/timerange.js — the same module fills
                     the box in the sidebar. Two bare date fields used to
                     stand here; since 14.08.2026 it is the same time filter
                     on both sides, day list included. */ ?>
                <div id="organize-time-range"></div>
                <label class="filter-check">
                    <input type="checkbox" id="organize-no-time">
                    <?= h(t('ohne Fundzeit')) ?>
                </label>
            </div>

            <div class="filter-group" data-group="place">
                <h4 class="filter-title"><?= h(t('Ort')) ?></h4>
                <?php /* The same areas as in the sidebar — they live in the
                     settings bundle, not in the view state. "Bereich
                     zeichnen" leads to the map since 23.08.2026 and comes
                     back here; the list is the same on both sides. */ ?>
                <div class="area-filter" id="organize-area-rows"></div>
                <?php /* Region, municipality, map sheet — the same controls as
                     in the sidebar, filled by filter.js from the stock. */ ?>
                <div id="organize-region-filter"></div>
                <div id="organize-municipality-filter"></div>
                <div id="organize-mtb-filter"></div>
            </div>

                    <?php /* HÖHE, and it sits with the place because it is a
                         question about where — just the vertical one. Two
                         handles on one track (drawElevation in filter.js);
                         the upper end means „and above", so a find in the
                         Alps does not fall out because the scale stops. */ ?>

                    <div class="filter-group" data-group="elevation">
                        <h4 class="filter-title"><?= h(t('Höhe')) ?></h4>
                        <div id="organize-elevation-build"></div>
                    </div>

            <div class="filter-group" data-group="identification">
                <h4 class="filter-title"><?= h(t('Bestimmung')) ?></h4>
                <?php /* Built by drawIdentification (filter.js), the same
                     drawing as in the sidebar — "the same filters in
                     both" means the fine hooks too: collective hook,
                     abbreviation families, the certainty steps. */ ?>
                <div id="organize-identification-build"></div>
            </div>

                    <div class="filter-group" data-group="taxonomy">
                        <h4 class="filter-title"><?= h(t('Systematik')) ?></h4>
                        <div id="organize-taxonomy-build"></div>
                    </div>

            <div class="filter-group" data-group="red-list">
                <h4 class="filter-title"><?= h(t('Gefährdung')) ?></h4>
                <?php /* The same checkmarks as in the sidebar, drawn by
                     assets/js/filter.js. The rating comes ready-made with
                     the point data ('rl'), looked up at the taxon. */ ?>
                <div class="rl-selection" id="organize-rl"></div>
                <div id="organize-rl-responsibility"></div>
            </div>

            <div class="filter-group" data-group="edibility">
                <h4 class="filter-title"><?= h(t('Speisewert')) ?></h4>
                <?php /* The DGfM's food-value marks — the same checkmarks as in
                     the sidebar (drawEdibility in filter.js); the marks come
                     with the point data ('dgfm'), looked up at the taxon. */ ?>
                <div class="rl-selection" id="organize-edibility"></div>
            </div>

        </div>
        <div class="table-frame organize-table">
            <table class="list">
                <?php /* Only the check column stands here — every other head
                     cell is built by organize.js, because which columns
                     exist is the user's choice (the „Spalten" chip above).
                     Each head cell carries the sort switch and, on the
                     desktop, its own search field. */ ?>
                <thead>
                <tr id="organize-head-row">
                    <th class="organize-check-column"><input type="checkbox" id="organize-header-check"
                        aria-label="<?= h(t('Alle sichtbaren Punkte wählen')) ?>"></th>
                </tr>
                </thead>
                <tbody id="organize-rows"></tbody>
            </table>
        </div>
    </div>
    <?php /* THE TOOLS UNDER BOTH COLUMNS, not under the right one alone
         (22.08.2026: „unten drunter die Buttons"). They act on
         what the two columns produce together — the selection on the
         right, made from the lists on the left — so they belong under the
         whole thing, like the concluding row of a form. As a grid item of
         its own across both tracks; inside the right column it used to
         start at its left edge and looked like a property of the table. */ ?>
    <div class="organize-actions">
            <?php /* ALL QUIET (26.09.2026: „die Knöpfe ruhig machen").
                 They are standing offers — none of them is the one action
                 due, so none wears the accent: eight lime buttons in a row
                 all shouted and said nothing (CLAUDE.md, „Loud only where
                 there is something to do"). What cannot act is still
                 HIDDEN, not quiet (12.09.2026); the trash keeps its red,
                 which is a meaning, not a volume. */ ?>
            <?php /* Left-aligned and apart from the group on the right
                 (22.08.2026): it creates something new and
                 needs no selection — the three on the right act on chosen
                 points, „Autoliste …" on the filter. */ ?>
            <button type="button" class="button subtle" id="organize-new-list"><?= t('Neue&nbsp;Liste&nbsp;…') ?></button>
            <span id="organize-selection"></span>
            <?php /* A button group of its own instead of single buttons in the
                 row: that keeps the three together and right-aligned even
                 when the selection display grows long enough to wrap —
                 single margin-left:auto buttons would otherwise tear
                 apart in the process. Right-aligned, because concluding
                 buttons below content are supposed to be (CLAUDE.md). */ ?>
            <div class="button-row right" style="margin:0">
                <?php /* THE AUTO LIST ARISES FROM THE FILTER, NOT FROM THE
                     SELECTION (14.08.2026) — which is why it is
                     the one button here that never sleeps: the three
                     others act on checked points and wake with the
                     selection. It still stands IN this group, same size,
                     same line (22.08.2026: no special area) —
                     one row of tools under the points. The ones that need
                     a selection are HIDDEN until there is one
                     (12.09.2026: „die ausgegrauten bitte komplett
                     ausblenden") — organize.js shows them with it. */ ?>
                <button type="button" class="button subtle" id="organize-auto-button"><?= t('Autoliste&nbsp;…') ?></button>
                <button type="button" class="button subtle" id="organize-lists-button" disabled hidden><?= t('Listen&nbsp;…') ?></button>
                <?php /* BOTH BUTTONS STAND ALWAYS, in both panes
                     (23.08.2026): that is what lets you sort crosswise —
                     pick points by tour and set their lists, or pick them
                     by list and set their tour. A button that appeared
                     only in its own pane would force a detour through the
                     other side for exactly the case this is for.

                     SINGULAR, AND THAT IS THE DIFFERENCE TO THE LISTS: a
                     point hangs in as many lists as one likes but is on
                     exactly ONE outing (migration 0071, one column). So
                     no tri-state dialog here — one choice, and it applies
                     to all chosen points. */ ?>
                <?php /* The excursions one leads stand in the same dialog
                     (12.09.2026: „keinen neuen Button, sondern
                     bei Touren mit auflisten") — the form for them is
                     organize-excursion-form below. */ ?>
                <button type="button" class="button subtle" id="organize-tour-button" disabled hidden><?= t('Tour&nbsp;…') ?></button>
                <button type="button" class="button subtle" id="organize-field-button" disabled hidden><?= t('Feld setzen&nbsp;…') ?></button>
                <?php /* ONLY WITH AN OWN FIND IN THE SELECTION (26.09.2026): a
                     find number belongs to a find in a species list, and a
                     guest's excursion find carries its finder's. */ ?>
                <button type="button" class="button subtle" id="organize-number-button" disabled hidden><?= t('Fundnummern&nbsp;…') ?></button>
                <?php /* NEVER SLEEPS, unlike the three beside it: without a
                     selection it takes what the table shows, and that is
                     the usual case — one filters and wants exactly that
                     out. The dialog says which of the two it will be. */ ?>
                <button type="button" class="button subtle" id="organize-export-button"><?= t('Export&nbsp;…') ?></button>
                <?php /* ITS OWN BUTTON, not an entry in the export dropdown
                     (23.08.2026 — the first version buried it
                     there and he did not find it). A report is not one
                     more file format: it is a document that gets
                     configured before it exists, and a thing one
                     configures deserves its own door. */ ?>
                <button type="button" class="button subtle" id="organize-report-button"><?= t('Fundbericht&nbsp;…') ?></button>
                <button type="button" class="button danger" id="organize-trash-button" disabled hidden><?= h(t('In den Papierkorb')) ?></button>
            </div>
        <?php /* THE DIVIDER IS A HANDLE (22.08.2026): the line
             between the columns can be dragged, so everyone sets for
             themselves how much room the list side gets. Invisible — the
             drawn line stays the border it always was, the handle is only
             the grab zone across it. Keyboard: arrow keys move it, double
             click resets.

             AT THE END OF THE GRID, NOT INSIDE A COLUMN. It first stood
             at the head of the right column and stole the header's
             :first-child — „Punkte" started 1.8rem lower than „Listen"
             (the maintainer saw the offset before I did). Now it hangs on the
             grid itself and is positioned by the same variable it drags:
             where the markup puts it costs nothing. */ ?>
        <div class="organize-splitter" id="organize-splitter" role="separator"
             aria-orientation="vertical" aria-label="<?= h(t('Spaltenbreite anpassen')) ?>"
             title="<?= h(t('Ziehen — Doppelklick setzt zurück')) ?>" tabindex="0"></div>
    </div>
</div>

<?php /* THE WINDOWS' SAVE AND APPLY BUTTONS stay quiet until something in
     them changed (25.09.2026: „mach die Fenster auch") —
     data-quiet-until-changed="open", measured each time organize.js has
     filled and opened a window (assets/js/quietsave.js). Report and
     target windows are actions, not saves, and keep their accent. */ ?>

<?php /* The three-state dialog. organize.js fills the rows from the selection;
     only leaves get a checkbox — lists with sublists are headings, not
     containers (see point_lists_set).

     tabindex="-1": the window itself gets the focus on opening
     (organize.js), not the first checkbox. showModal() would otherwise put
     it on the first checkbox, and its focus ring read like a mark — as
     if the list were already half selected. Whoever wants to continue by
     keyboard presses Tab and then gets the ring rightly. */ ?>
<dialog class="dialog organize-dialog" id="organize-dialog" tabindex="-1">
    <form method="dialog">
        <h2 class="dialog-title"><?= h(t('Listen der Auswahl')) ?></h2>
        <div class="organize-dialog-lists" id="organize-dialog-lists"></div>
        <p class="dialog-text" id="organize-dialog-summary"></p>
        <div class="dialog-footer">
            <button type="button" class="button subtle" value="nein"><?= h(t('Abbrechen')) ?></button>
            <button type="submit" class="button subtle" value="ja" id="organize-dialog-ok" data-quiet-until-changed="open"><?= h(t('Übernehmen')) ?></button>
        </div>
    </form>
</dialog>

<?php /* The row pencil's window (the sketch, 24.08.2026): ONE point,
     everything at once — organize.js fills title, note, find time and
     the fields that belong to this point (its roots' fields, the find
     set on species finds, the fixed set on excursion finds). The form is
     the truth: empty clears, and saving goes as one classic POST through
     the hidden form below. */ ?>
<dialog class="dialog organize-dialog" id="organize-point-dialog">
    <form method="dialog">
        <h2 class="dialog-title"><?= h(t('Punkt bearbeiten')) ?></h2>
        <div class="field">
            <label for="organize-point-title"><?= h(t('Titel')) ?></label>
            <input type="text" id="organize-point-title" required maxlength="200">
        </div>
        <div class="field">
            <label for="organize-point-time"><?= h(t('Fundzeit')) ?></label>
            <input type="datetime-local" id="organize-point-time">
        </div>
        <div class="field">
            <label for="organize-point-note"><?= h(t('Notiz')) ?></label>
            <textarea id="organize-point-note" rows="2"></textarea>
        </div>
        <div id="organize-point-fields"></div>
        <div class="dialog-footer">
            <button type="button" class="button subtle" value="nein"><?= h(t('Abbrechen')) ?></button>
            <button type="submit" class="button subtle" value="ja" data-quiet-until-changed="open"><?= icon('save') ?><?= h(t('Speichern')) ?></button>
        </div>
    </form>
</dialog>

<?php /* The set-field window: field choice from title, note, find fields and
     the own fields of the affected root lists; the value field switches
     with the field type (text, multiline, choice). Clearing is an
     explicit checkbox — an empty input field clears nothing, otherwise
     you would clear by accident. */ ?>
<dialog class="dialog organize-dialog" id="organize-field-dialog">
    <form method="dialog">
        <h2 class="dialog-title"><?= h(t('Feld setzen')) ?></h2>
        <div class="field">
            <label for="organize-field-selection"><?= h(t('Feld')) ?></label>
            <select id="organize-field-selection"></select>
        </div>
        <div class="field" id="organize-field-value-field">
            <label for="organize-field-value"><?= h(t('Wert für alle gewählten')) ?></label>
            <span id="organize-field-input"></span>
        </div>
        <label class="organize-field-clear"><input type="checkbox" id="organize-field-clear"> <?= h(t('Feld leeren')) ?></label>
        <p class="dialog-text" id="organize-field-summary"></p>
        <div class="dialog-footer">
            <button type="button" class="button subtle" value="nein"><?= h(t('Abbrechen')) ?></button>
            <button type="submit" class="button subtle" value="ja" id="organize-field-ok" data-quiet-until-changed="open"><?= h(t('Übernehmen')) ?></button>
        </div>
    </form>
</dialog>

<?php /* MANY FIND NUMBERS AT ONCE (26.09.2026: „das mehrfach
     umbenennen der Fundnummern"). numberdialog.js fills it: which questions
     stand depends on the choice and on the answers, and NONE IS PRESELECTED
     („vorwählen würde ich hier überhaupt nichts") — the user makes each
     decision. A question is as large as its answers and they stand indented
     beneath it (.field.question: „die Frage muss etwas markanter sein").
     The table shows every number before anything is saved; numbering.js
     computes it, find_number_plan() decides it on the server. An unanswered
     question turns red INSIDE the window: a toast would lie behind it.
     tabindex="-1" as at the lists window: the window takes the focus, not
     the first answer — its focus ring would read like a preselection. */ ?>
<dialog class="dialog organize-dialog organize-number-dialog" id="organize-number-dialog" tabindex="-1">
    <form method="dialog">
        <h2 class="dialog-title"><?= h(t('Fundnummern vergeben')) ?></h2>
        <p class="number-intro" id="organize-number-intro"></p>

        <fieldset class="field question" data-question="mode">
            <legend><span class="question-no"></span> <?= h(t('Welche Nummern sollen die Funde bekommen?')) ?></legend>
            <p class="question-missing" hidden><?= h(t('Hier fehlt noch deine Antwort.')) ?></p>
            <div id="organize-number-next">
                <label class="choice-row"><input type="radio" name="number-mode" value="next"> <span><?= h(t('Die nächsten freien Nummern deiner Reihe')) ?></span></label>
                <p class="choice-sub" id="organize-number-next-sub"></p>
            </div>
            <label class="choice-row number-from"><input type="radio" name="number-mode" value="from"> <span><?= h(t('Fortlaufend ab')) ?></span>
                <input type="text" id="organize-number-start" maxlength="80" autocomplete="off" spellcheck="false"
                       placeholder="neu-01" aria-label="<?= h(t('Erste Nummer oder Muster')) ?>"></label>
            <p class="choice-sub" id="organize-number-from-sub"></p>
            <label class="choice-row"><input type="radio" name="number-mode" value="remove"> <span><?= h(t('Eigene Nummern entfernen')) ?></span></label>
            <p class="choice-sub" id="organize-number-remove-sub"></p>
        </fieldset>

        <fieldset class="field question" data-question="restart">
            <legend><span class="question-no"></span> <span id="organize-number-restart-question"></span></legend>
            <p class="question-missing" hidden><?= h(t('Hier fehlt noch deine Antwort.')) ?></p>
            <label class="choice-row"><input type="radio" name="number-restart" value="yes"> <span id="organize-number-restart-yes"></span></label>
            <label class="choice-row"><input type="radio" name="number-restart" value="no"> <span id="organize-number-restart-no"></span></label>
        </fieldset>

        <fieldset class="field question" data-question="scope">
            <legend><span class="question-no"></span> <?= h(t('Welche Funde sollen eine neue Nummer bekommen?')) ?></legend>
            <p class="question-missing" hidden><?= h(t('Hier fehlt noch deine Antwort.')) ?></p>
            <label class="choice-row"><input type="radio" name="number-scope" value="missing"> <span id="organize-number-scope-missing"></span></label>
            <label class="choice-row"><input type="radio" name="number-scope" value="all"> <span id="organize-number-scope-all"></span></label>
        </fieldset>

        <fieldset class="field question" data-question="order">
            <legend><span class="question-no"></span> <?= h(t('In welcher Reihenfolge?')) ?></legend>
            <p class="question-missing" hidden><?= h(t('Hier fehlt noch deine Antwort.')) ?></p>
            <label class="choice-row"><input type="radio" name="number-order" value="time"> <span><?= h(t('Nach Fundzeit, die ältesten zuerst')) ?></span></label>
            <label class="choice-row"><input type="radio" name="number-order" value="table"> <span id="organize-number-order-table"></span></label>
        </fieldset>

        <fieldset class="field question" data-question="old">
            <legend><span class="question-no"></span> <?= h(t('Was passiert mit den bisherigen Nummern?')) ?></legend>
            <p class="question-missing" hidden><?= h(t('Hier fehlt noch deine Antwort.')) ?></p>
            <p class="question-note" id="organize-number-old-note"></p>
            <label class="choice-row"><input type="radio" name="number-old" value="note"> <span id="organize-number-old-note-answer"></span></label>
            <label class="choice-row"><input type="radio" name="number-old" value="drop"> <span><?= h(t('Nicht aufheben')) ?></span></label>
        </fieldset>

        <fieldset class="field question" data-question="adopt">
            <legend><span class="question-no"></span> <?= h(t('Sollen neue Funde danach auch so nummeriert werden?')) ?></legend>
            <p class="question-missing" hidden><?= h(t('Hier fehlt noch deine Antwort.')) ?></p>
            <label class="choice-row"><input type="radio" name="number-adopt" value="yes"> <span id="organize-number-adopt-yes"></span></label>
            <label class="choice-row"><input type="radio" name="number-adopt" value="no"> <span id="organize-number-adopt-no"></span></label>
        </fieldset>

        <table class="list number-preview">
            <thead><tr><th><?= h(t('Fundzeit')) ?></th><th><?= h(t('Fund')) ?></th><th><?= h(t('Bisher')) ?></th><th><?= h(t('Neu')) ?></th></tr></thead>
            <tbody id="organize-number-rows"></tbody>
        </table>

        <div class="number-summary" id="organize-number-summary"></div>

        <div class="dialog-footer">
            <button type="button" class="button subtle" value="nein"><?= h(t('Abbrechen')) ?></button>
            <button type="submit" class="button" value="ja" id="organize-number-ok"></button>
        </div>
    </form>
</dialog>

<?php /* The column window: one row per possible column — checkmark for
     visibility, arrows for the order. organize.js fills the rows from its
     catalogue (the fixed point fields plus every find and list field of
     the account); the choice lands in the settings bundle and survives
     the visit. „Titel" is not on offer: a row without its title would be
     unreadable, so that column is simply always there. */ ?>
<dialog class="dialog organize-dialog" id="organize-columns-dialog">
    <form method="dialog">
        <h2 class="dialog-title"><?= h(t('Spalten der Tabelle')) ?></h2>
        <div class="organize-columns" id="organize-columns-rows"></div>
        <div class="dialog-footer">
            <button type="button" class="button subtle" value="nein"><?= h(t('Abbrechen')) ?></button>
            <button type="submit" class="button subtle" value="ja" data-quiet-until-changed="open"><?= h(t('Übernehmen')) ?></button>
        </div>
    </form>
</dialog>

<?php /* The find report, configured before it is built (23.08.2026).
     The columns come from the same catalogue as the table's — whoever has
     already chosen what to see has chosen what to print, so the report
     starts with exactly those ticks. „Welche Angaben?" carries the same
     rows as the column window above, arrows and all: a report wants its
     own order (24.08.2026), and the order of the rows is the
     order of the columns on paper. Only „Titel" stays put at the top. */ ?>
<dialog class="dialog organize-dialog" id="organize-report-dialog">
    <form method="dialog">
        <h2 class="dialog-title"><?= h(t('Fundbericht')) ?></h2>
        <p class="dialog-text" id="organize-report-hint"></p>

        <?php /* DARSTELLUNG FIRST, THE COLUMNS AFTER IT (23.08.2026).
             The first version had it the other way round, and the four
             settings sat underneath a list of twenty-one checkmarks where
             one had to scroll past everything to reach them.

             SIDE BY SIDE ON THE DESKTOP (26.09.2026: „das
             Fenster ist sehr schmal und sehr lang … auf dem Desktop wäre
             schön, wenn welche Angaben quasi als zweite Spalte daneben
             stehen würde"): the width is there, the height is not — two
             columns keep the window within one screen on most monitors.
             Below the phone breakpoint they stack again, Darstellung
             first, as before. */ ?>
        <div class="report-columns">
            <div class="report-column">
                <div class="column-header"><h4><?= h(t('Darstellung')) ?></h4></div>
                <?php /* QUESTIONS WITH THEIR ANSWERS IN SIGHT, no dropdown
                     (the rule of 25.09.2026, and „die Fragen überall
                     so", 26.09.2026). The last choice stands chosen — a
                     report is made again and again in the same shape.

                     THE IDS STAY ON THE GROUPS on purpose: organize.js
                     reaches this page as a bare import (CLAUDE.md, trap
                     five), and for one visit after a deployment a device may
                     run the old script, which reads and writes .value on
                     these ids. On a fieldset that is a plain property — the
                     old script carries the remembered choice through and
                     never stops; the new one reads the ticked answer. */ ?>
                <fieldset class="field question" id="organize-report-layout">
                    <legend><?= h(t('In welcher Form?')) ?></legend>
                    <?php /* Two forms for two situations: few columns read as a
                         table, many as tiles — a table with twelve columns
                         squeezes every value into a thumb's width. */ ?>
                    <label class="choice-row"><input type="radio" name="report-layout" value="list"> <span><?= h(t('Liste — eine Zeile je Fund')) ?></span></label>
                    <label class="choice-row"><input type="radio" name="report-layout" value="tiles"> <span><?= h(t('Kacheln — Angaben untereinander')) ?></span></label>
                </fieldset>
                <?php /* From the thumbnail to the full page width
                     (23.08.2026: he decides how big, not the program). The
                     millimetres are printed millimetres — on paper that is
                     the only size that means anything. */ ?>
                <fieldset class="field question" id="organize-report-photos">
                    <legend><?= h(t('Fotos in welcher Größe?')) ?></legend>
                    <label class="choice-row"><input type="radio" name="report-photos" value="none"> <span><?= h(t('keine')) ?></span></label>
                    <label class="choice-row"><input type="radio" name="report-photos" value="small"> <span><?= h(t('Vorschaugröße (28 mm)')) ?></span></label>
                    <label class="choice-row"><input type="radio" name="report-photos" value="medium"> <span><?= h(t('mittel (45 mm)')) ?></span></label>
                    <label class="choice-row"><input type="radio" name="report-photos" value="large"> <span><?= h(t('groß (70 mm)')) ?></span></label>
                    <label class="choice-row"><input type="radio" name="report-photos" value="full"> <span><?= h(t('volle Breite (180 mm)')) ?></span></label>
                </fieldset>
                <label class="checkbox">
                    <input type="checkbox" id="organize-report-map">
                    <span><?= h(t('Übersichtskarte voranstellen')) ?></span>
                </label>
                <?php /* The find list becomes a species list
                     (23.08.2026): several people map the same species on an
                     excursion — listed three times, it says nothing three
                     times. Only the VIEW is merged; the points stay. */ ?>
                <label class="checkbox">
                    <input type="checkbox" id="organize-report-merge">
                    <span><?= h(t('Gleiche Arten zu einer Zeile zusammenfassen')) ?></span>
                </label>
                <?php /* The German name under the scientific one, where a list carries
                     one (12.09.2026). Garnish, hence a checkmark and
                     not the default.

                     THE CHECKBOX BELONGS TO THE REPORT FORM ITSELF (form="…"), not
                     to this dialog's: it travels with the submit natively, without
                     a hidden twin that a script has to fill. That matters on THIS
                     page — organize.js reaches it as a bare import (CLAUDE.md,
                     trap five), and after a deployment a device may run the old
                     script for one more visit. The maintainer ticked the box the
                     evening it went live and saw no names: the old script never
                     filled the twin. A field the browser submits itself cannot be
                     forgotten by a script. */ ?>
                <label class="checkbox">
                    <input type="checkbox" id="organize-report-german" name="german" value="1" form="organize-report-form">
                    <span><?= h(t('Deutsche Namen darunter zeigen')) ?></span>
                </label>
            </div>
            <div class="report-column">
                <div class="column-header"><h4><?= h(t('Welche Angaben?')) ?></h4></div>
                <div class="organize-columns" id="organize-report-columns"></div>
            </div>
        </div>

        <div class="dialog-footer">
            <button type="button" class="button subtle" value="nein"><?= h(t('Abbrechen')) ?></button>
            <button type="submit" class="button" value="ja"><?= h(t('Bericht öffnen')) ?></button>
        </div>
    </form>
</dialog>

<?php /* The target window for moving and merging — the same shell, organize.js
     sets heading, choice and hint. The questions below the hint are the
     export's for the MykIS sheet (how precisely, whose finds); every other
     use leaves them empty. */ ?>
<dialog class="dialog organize-dialog" id="organize-target-dialog">
    <form method="dialog">
        <h2 class="dialog-title" id="organize-target-title"></h2>
        <div class="field">
            <label for="organize-target-selection" id="organize-target-label"></label>
            <select id="organize-target-selection"></select>
        </div>
        <p class="dialog-text" id="organize-target-hint"></p>
        <div id="organize-target-explain"></div>
        <div id="organize-target-questions"></div>
        <div class="dialog-footer">
            <button type="button" class="button subtle" value="nein"><?= h(t('Abbrechen')) ?></button>
            <button type="submit" class="button" value="ja" id="organize-target-ok"></button>
        </div>
    </form>
</dialog>

<?php /* Forms for the actions: organize.js fills in ids and values and submits
     classically — the page comes back with a message, the anchor opens
     the tab again, 'zustand' the view. */ ?>
<form method="post" action="<?= h($target) ?>" id="organize-field-form" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="organize">
    <input type="hidden" name="action" value="field">
    <input type="hidden" name="points" value="">
    <input type="hidden" name="field" value="">
    <input type="hidden" name="value" value="">
    <input type="hidden" name="clear" value="">
    <?php organize_state_inputs() ?>
</form>
<?php /* The numbering's answers — the points in the order the window numbered
     them, and one field per question (organize_numbers_apply). */ ?>
<form method="post" action="<?= h($target) ?>" id="organize-number-form" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="organize">
    <input type="hidden" name="action" value="numbers">
    <input type="hidden" name="points" value="">
    <input type="hidden" name="mode" value="">
    <input type="hidden" name="start" value="">
    <input type="hidden" name="restart" value="">
    <input type="hidden" name="scope" value="">
    <input type="hidden" name="old" value="">
    <input type="hidden" name="adopt" value="">
    <?php organize_state_inputs() ?>
</form>
<form method="post" action="<?= h($target) ?>" id="organize-tree-form" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="organize">
    <input type="hidden" name="action" value="">
    <input type="hidden" name="list" value="">
    <input type="hidden" name="name" value="">
    <input type="hidden" name="target" value="">
    <input type="hidden" name="points" value="">
    <?php organize_state_inputs() ?>
</form>
<form method="post" action="<?= h($target) ?>" id="organize-lists-form" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="organize">
    <input type="hidden" name="action" value="lists">
    <input type="hidden" name="points" value="">
    <input type="hidden" name="add" value="">
    <input type="hidden" name="remove" value="">
    <input type="hidden" name="add_container" value="">
    <input type="hidden" name="add_container_original" value="">
    <input type="hidden" name="remove_container" value="">
    <?php organize_state_inputs() ?>
</form>
<?php /* The tour actions: rename, merge, delete (tour_set — one operation,
     see there) and the new tour out of the checked points (tour_new).
     Own fields with own names: 'list' means a list id everywhere else,
     and a tour name in it would be a lie the next reader falls for. */ ?>
<form method="post" action="<?= h($target) ?>" id="organize-excursion-form" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="organize">
    <input type="hidden" name="action" value="excursion_assign">
    <input type="hidden" name="points" value="">
    <input type="hidden" name="excursion_to" value="">
    <?php organize_state_inputs() ?>
</form>
<form method="post" action="<?= h($target) ?>" id="organize-tour-form" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="organize">
    <input type="hidden" name="action" value="">
    <input type="hidden" name="points" value="">
    <input type="hidden" name="tour_from" value="">
    <input type="hidden" name="tour_to" value="">
    <?php /* The identities beside the names (migration 0079): since two
         outings may share a name, the id says which one is meant. */ ?>
    <input type="hidden" name="tour_from_id" value="">
    <input type="hidden" name="tour_to_id" value="">
    <?php organize_state_inputs() ?>
</form>
<?php /* The export goes to its own page and comes back as a file, so this form
     carries no state fields: nothing here returns to the tab. target
     _blank keeps the tab where it is — a download navigation would
     otherwise leave the view behind (pages/export.php explains it). */ ?>
<form method="post" action="<?= h(url('pages/export.php')) ?>" id="organize-export-form"
      target="_blank" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="points" value="">
    <input type="hidden" name="format" value="">
    <?php /* The MykIS sheet's answers (pages/export.php reads them). */ ?>
    <input type="hidden" name="precision" value="">
    <input type="hidden" name="determined" value="">
    <input type="hidden" name="foreign" value="">
</form>
<?php /* The report opens in a tab of its own and prints itself from there —
     the chosen columns travel as a repeated field, everything else as
     one value each. organize.js fills the columns in before submitting. */ ?>
<form method="post" action="<?= h(url('pages/report.php')) ?>" id="organize-report-form"
      target="_blank" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="points" value="">
    <input type="hidden" name="layout" value="">
    <input type="hidden" name="photos" value="">
    <input type="hidden" name="map" value="">
    <input type="hidden" name="merge" value="">
    <span id="organize-report-fields"></span>
</form>
<form method="post" action="<?= h($target) ?>" id="organize-save-form" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="organize">
    <input type="hidden" name="action" value="point_save">
    <input type="hidden" name="point" value="">
    <?php /* Unused by point_save, but submitForm fills 'points' into every
         form it sends — a missing field is its loud stop. */ ?>
    <input type="hidden" name="points" value="">
    <input type="hidden" name="title" value="">
    <input type="hidden" name="note" value="">
    <input type="hidden" name="found_at" value="">
    <?php /* The fields[<name>] inputs are appended by organize.js per save —
         which fields exist depends on the point. */ ?>
    <?php organize_state_inputs() ?>
</form>
<form method="post" action="<?= h($target) ?>" id="organize-trash-form" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="organize">
    <input type="hidden" name="action" value="trash">
    <input type="hidden" name="points" value="">
    <?php organize_state_inputs() ?>
</form>

<script type="application/json" id="organize-data"><?=
    json_encode(
        [
            'lists'          => $lists,
            // The Mappen (0168) — they gather lists in the tree, as in the sidebar.
            'folders'        => folders_load($pdo, $userId),
            'points'         => $points,
            // The registered tour names — an empty tour has no points to
            // be recognized by, so it needs saying (migration 0073).
            'tour_names'     => organize_tour_names($pdo, $userId),
            'shared'         => $shared,
            'foreign_points' => array_values($foreignIndex),
            'excursions'     => $excursions,
            'preset'         => $result['state'],
            'find_fields' => array_map(static fn(array $f): array => [
                'name' => $f['name'], 'type' => $f['type'], 'options' => $f['options'],
                // The field a tick uncovers („Beleg aufbewahrt" → „Beleg-Nr.").
                'reveals' => $f['reveals'] ?? null,
            ], find_fields()),
            // For „Fundnummern …": the series with its counter, as the
            // point window gets it (find_number_series), and the numbers
            // the trash holds — a find put back keeps its number, so the
            // preview must not hand it out (find_number_highest counts
            // the trash too).
            'find_series'   => find_number_series($pdo, $userId),
            'trash_numbers' => find_number_trash($pdo, $userId),
            // What the export window starts from when it asks about the
            // MykIS sheet — the account's own choices (mykis.php).
            'mykis'         => mykis_export_choices($pdo, $userId),
        ],
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
    )
?></script>
    <?php
}
