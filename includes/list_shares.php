<?php
/**
 * Listen teilen — one share per list, its rights and filter per recipient
 * (08./09.10.2026, migration 0160).
 *
 * THE RULE IN ONE SENTENCE: whoever shares, shares his OWN list — with the
 * friends and own working groups he ticks, and for each of them he alone
 * decides what that one sees and may do. Nobody hangs anything into
 * somebody else's share, and nobody but the list's owner takes people in.
 * Until then a Freigabe was a box anybody inside could hang lists into,
 * with one birth filter its owner set for all — „Geteilt" had become a
 * chaos category, and one could not tell who decided what.
 *
 * THE ENGINE STAYS (container.php): a list's share is ONE container whose
 * owner is the list's owner and which holds exactly that list
 * (map_containers.list_id). What used to sit once per share now sits on
 * every recipient row (map_container_members): its rights and its filter.
 * Somebody reached through a friend row AND a group row gets the union of
 * both — the rights added up, and every point that passes either filter.
 * The sync to the device, the chat, the list's trash and the handover run
 * on as they were tested.
 *
 * POINTS AND PICTURES ALWAYS STAY THEIR MAKER'S. A list is a filing.
 * Removing somebody, his leaving and the end of a friendship change what
 * he SEES, never what he OWNS: whoever set points into the list takes them
 * home with him (joint_points_home). „Geteilt wird Sicht und Arbeit, nie
 * Besitz" — only whoever attaches a picture becomes a co-owner
 * (point_owners.php), and that stands apart from any share.
 *
 * „GEMEINSAM BEARBEITEN" IS NO SWITCH ANY MORE but the right `own_points`
 * on a recipient row (08.10.2026: „das wäre im Prinzip bloß die
 * Abkürzung überall Haken rein"). The share row's `joint` flag follows it
 * (list_share_joint_sync), so everything that reads joint lists — the
 * list's trash, the chat lines about every change, the filing of points —
 * keeps working without knowing about recipients. The difference to the
 * old joint list: the others' rights are exactly the ticks, no longer
 * everything at once, and the filter applies to every viewer.
 */

declare(strict_types=1);

require_once __DIR__ . '/container.php';

/**
 * The keys a recipient row can carry; per extra field of the list
 * „field:<name>" is added, and „field:*" means every field — also those
 * the owner adds later („Alles erlauben" for the spouse means tomorrow's
 * field too).
 *
 * WHAT EACH ONE OPENS:
 *   title, note      the two text fields
 *   move             position and outline — both move the same object
 *   attach_photos    pictures to the finds; whoever attaches becomes a
 *                    co-owner (point_owners.php)
 *   delete_photos    the OTHERS' pictures too — one's own one may always
 *   measure          store measures and the scale on the pictures; without
 *                    it one measures for oneself, nothing is saved
 *   delete           into the owner's trash, only he brings it back
 *   own_points       set one's own points into the list — „gemeinsam
 *                    bearbeiten", with its chat and the list's trash
 *   edit_photos      lay edits of one's own beside the sharer's on HIS
 *                    pictures of the list's finds — „Bilder bearbeiten"
 *                    (11.11.2026, includes/picture_edits.php); his own
 *                    edits stay untouched, only he makes one valid
 */
const LIST_SHARE_RIGHTS = ['title', 'note', 'move', 'attach_photos', 'delete_photos', 'measure', 'edit_photos', 'delete', 'own_points'];

/** Migration 0160 — the code goes live before it. */
function list_shares_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = container_ready($pdo)
            && column_exists($pdo, 'map_containers', 'list_id')
            && column_exists($pdo, 'map_container_members', 'rights');
    }
    return $ready;
}

/**
 * Which list a container shares — null for an old box (or before 0160).
 * Asked often and per container, so remembered for the request.
 */
function list_share_list(PDO $pdo, int $containerId): ?int
{
    static $known = [];
    if (!list_shares_ready($pdo)) {
        return null;
    }
    if (!array_key_exists($containerId, $known)) {
        $stmt = $pdo->prepare('SELECT list_id FROM map_containers WHERE id = ?');
        $stmt->execute([$containerId]);
        $id = $stmt->fetchColumn();
        $known[$containerId] = ($id === false || $id === null) ? null : (int) $id;
    }
    return $known[$containerId];
}

/** The container sharing this list, or null. */
function list_share_container(PDO $pdo, int $listId): ?int
{
    if (!list_shares_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM map_containers WHERE list_id = ?');
    $stmt->execute([$listId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

// ---------------------------------------------------------------------------
// Rights
// ---------------------------------------------------------------------------

/**
 * A recipient row's rights, checked against the list. Unknown keys fail
 * loudly — a right one believes to be granting that never arrives is the
 * most unpleasant sort of error (the same stance as share_rights_set).
 *
 * @return string[]
 */
function list_share_rights_clean(PDO $pdo, int $listId, array $rights): array
{
    $fields = array_column(list_fields_of_root($pdo, $listId), 'name');
    $clean  = [];
    foreach ($rights as $r) {
        $r = (string) $r;
        $valid = in_array($r, LIST_SHARE_RIGHTS, true)
            || $r === 'field:*'
            || (str_starts_with($r, 'field:') && in_array(substr($r, 6), $fields, true));
        if (!$valid) {
            throw new InvalidArgumentException(t('Unbekanntes Recht: %s', $r));
        }
        $clean[] = $r;
    }
    return array_values(array_unique($clean));
}

/**
 * The stored keys as the gates read them: „field:*" spelled out into one
 * key per field the list has TODAY, so every existing check
 * (foreign_input_sift, the browser's sharedRights) needs no second kind of
 * answer.
 */
function list_share_rights_expand(PDO $pdo, int $listId, array $rights): array
{
    if (!in_array('field:*', $rights, true)) {
        return $rights;
    }
    $out = array_values(array_filter($rights, static fn(string $r): bool => $r !== 'field:*'));
    foreach (array_column(list_fields_of_root($pdo, $listId), 'name') as $name) {
        $out[] = 'field:' . $name;
    }
    return array_values(array_unique($out));
}

// ---------------------------------------------------------------------------
// The filter — the sidebar's points filter, on the server
// ---------------------------------------------------------------------------

/**
 * A recipient's filter in fixed form, or null for „sees every point".
 *
 * THE SAME FILTER AS „PUNKTE" IN THE SIDEBAR (08.10.2026: „den
 * gleichen Filter wie unser Filter bei Punkten … viel komplexer, viel
 * besser aufgebaut"). Its server half already exists for the auto lists —
 * autolist_filter_read() and autolist_matches() in data.php, held to
 * filter.js by tests/autolists.php — and is used as it is. What the auto
 * lists never needed is the identification group; it is added here.
 *
 * EVALUATED ON THE SERVER, NEVER IN THE BROWSER: a point the recipient may
 * not see must not reach his device at all, hidden or not.
 */
function list_share_filter_read(mixed $raw): ?array
{
    $f = is_array($raw) ? $raw : (is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : []);
    if ($f === []) {
        return null;
    }
    $filter = autolist_filter_read($f);
    // Sources are lists — a share is one list, there is nothing to choose.
    $filter['sources'] = [];

    $id = is_array($f['identification'] ?? null) ? $f['identification'] : [];
    $marks = [];
    foreach (['sp', 'cf', 'aff', 'sl', 'agg', 'sstr'] as $family) {
        if (in_array($family, array_map('strval', (array) ($id['marks'] ?? [])), true)) {
            $marks[] = $family;
        }
    }
    $certainty = [];
    foreach ((array) ($id['certainty'] ?? []) as $value) {
        $value = mb_substr(trim((string) $value), 0, 60);
        if ($value !== '' && !in_array($value, $certainty, true)) {
            $certainty[] = $value;
        }
    }
    $worked = in_array($id['worked'] ?? '', ['yes', 'no'], true) ? (string) $id['worked'] : '';
    $filter['identification'] = [
        'open'      => !empty($id['open']),
        'marks'     => $marks,
        'certainty' => $certainty,
        'worked'    => $worked,
    ];

    return list_share_filter_empty($filter) ? null : $filter;
}

/** Whether a read filter holds no condition at all. */
function list_share_filter_empty(array $f): bool
{
    $id = $f['identification'] ?? [];
    return $f['terms'] === [] && $f['from'] === '' && $f['until'] === '' && !$f['undated']
        && $f['rl'] === [] && $f['edibility'] === [] && !$f['responsibility']
        && $f['areas'] === [] && $f['regions'] === [] && $f['municipalities'] === [] && $f['mtb'] === []
        && $f['elevation_from'] === 0 && $f['elevation_to'] === ELEVATION_MAX
        && $f['taxonomy'] === []
        && empty($id['open']) && ($id['marks'] ?? []) === [] && ($id['certainty'] ?? []) === []
        && ($id['worked'] ?? '') === '';
}

/** The certainty field's words before migration 0069 wrote them out —
 *  CERTAINTY_LEGACY in identification.js. */
const LIST_SHARE_CERTAINTY_LEGACY = [
    'mak. sicher' => 'makroskopisch sicher',
    'mik. sicher' => 'mikroskopisch sicher',
];

/**
 * The identification group — the twin of identificationMatches() and
 * workedMatches() in assets/js/identification.js. The three degrees are
 * OR-linked (open marks in the title, an open certainty, a chosen
 * certainty), „bearbeitet" is an AND beside them. The marks come from
 * mykis_open_marks(), itself the twin of openMarksIn(); s. str. is no open
 * mark (familyOpen).
 */
function list_share_identification_matches(array $sel, string $title, mixed $certainty, int $recordings): bool
{
    $worked = $sel['worked'] ?? '';
    if ($worked !== '' && ($worked === 'yes') !== ($recordings > 0)) {
        return false;
    }
    $open   = !empty($sel['open']);
    $marks  = $sel['marks'] ?? [];
    $chosen = $sel['certainty'] ?? [];
    if (!$open && $marks === [] && $chosen === []) {
        return true;
    }
    require_once __DIR__ . '/mykis.php';
    $inTitle = mykis_open_marks($title);
    $value   = is_string($certainty) ? trim($certainty) : '';
    $value   = LIST_SHARE_CERTAINTY_LEGACY[$value] ?? $value;
    if ($open && (array_diff($inTitle, ['sstr']) !== [] || $value === 'unbestimmt')) {
        return true;
    }
    if (array_intersect($marks, $inTitle) !== []) {
        return true;
    }
    return $value !== '' && in_array($value, $chosen, true);
}

/**
 * Which of these points pass at least one of the filters.
 *
 * A BATCH, because the derived values cost lookups: the haystack, the
 * threat status, the DGfM marks, the ranks of the genus, the cloud
 * recordings — once for all rows, the way autolists_compute() fetches
 * them for a whole stock.
 *
 * @param array[]      $points   rows of map_points (p.* is enough)
 * @param array[]|null $filters  read filters; null means no condition
 * @return array<int,true>       point id → passes
 */
function list_share_filter_pass(PDO $pdo, array $points, ?array $filters): array
{
    if ($points === []) {
        return [];
    }
    if ($filters === null) {
        return array_fill_keys(array_map(static fn(array $p): int => (int) $p['id'], $points), true);
    }

    $identifiers = [];
    foreach ($points as $p) {
        foreach (point_species_keys($p['extra_species'] ?? null) as $k) {
            $identifiers[$k] = true;
        }
    }
    $endangerment = $identifiers === [] ? [] : species_endangerment($pdo, array_keys($identifiers));
    $marksOf      = species_dgfm_marks($pdo, array_keys($identifiers));

    $ids   = array_map(static fn(array $p): int => (int) $p['id'], $points);
    $space = implode(',', array_fill(0, count($ids), '?'));

    $taxonomyOf = [];
    if (column_exists($pdo, 'map_point_species', 'genus')) {
        $g = $pdo->prepare("SELECT point_id, genus, source FROM map_point_species
                             WHERE point_id IN ($space) AND field = '' AND genus <> ''");
        $g->execute($ids);
        $keyOf = [];
        $entries = [];
        foreach ($g as $z) {
            $key = taxonomy_key((string) $z['genus'], (string) ($z['source'] ?? ''));
            $keyOf[(int) $z['point_id']] = $key;
            $entries[$key] = ['genus' => $z['genus'], 'source' => $z['source'] ?? ''];
        }
        $ranks = taxonomy_of_genera($pdo, array_values($entries));
        foreach ($keyOf as $id => $key) {
            if (isset($ranks[$key])) {
                $taxonomyOf[$id] = $ranks[$key];
            }
        }
    }

    // „Bearbeitet" counts the cloud recordings at the find — whoever made them.
    $recordings = [];
    $needsWorked = false;
    foreach ($filters as $f) {
        $needsWorked = $needsWorked || (($f['identification']['worked'] ?? '') !== '');
    }
    if ($needsWorked && table_exists($pdo, 'map_cloud_files')) {
        $r = $pdo->prepare("SELECT point_id, COUNT(*) AS n FROM map_cloud_files WHERE point_id IN ($space) GROUP BY point_id");
        $r->execute($ids);
        foreach ($r as $z) {
            $recordings[(int) $z['point_id']] = (int) $z['n'];
        }
    }

    $pass = [];
    foreach ($points as $p) {
        $id    = (int) $p['id'];
        $extra = is_string($p['extra'] ?? null) ? (json_decode($p['extra'], true) ?: []) : (array) ($p['extra'] ?? []);
        $place = [
            'lat' => $p['lat'], 'lon' => $p['lon'],
            'region' => $p['region'] ?? null, 'municipality' => $p['municipality'] ?? null,
            'mtb' => $p['mtb'] ?? null, 'mtb_quadrant' => $p['mtb_quadrant'] ?? null,
            'elevation_m' => $p['elevation_m'] ?? null,
            'taxonomy' => $taxonomyOf[$id] ?? null,
            'dgfm' => point_dgfm_marks($p['extra_species'] ?? null, $marksOf, $p['title'] ?? null),
        ];
        $day = substr((string) ($p['found_at'] ?? $p['created_at'] ?? ''), 0, 10) ?: null;
        $rl  = point_endangerment($p['extra_species'] ?? null, $endangerment);
        foreach ($filters as $f) {
            if (!autolist_matches($f, point_haystack($p), $p['found_at'] ?? null, $day, [], [], $rl, $place)) {
                continue;
            }
            if (!list_share_identification_matches($f['identification'] ?? [], (string) ($p['title'] ?? ''),
                    $extra['Bestimmungssicherheit'] ?? null, $recordings[$id] ?? 0)) {
                continue;
            }
            $pass[$id] = true;
            break;
        }
    }
    return $pass;
}

/** The filter as one readable phrase — for the row under a ticked recipient. */
function list_share_filter_readable(?array $f): ?string
{
    if ($f === null) {
        return null;
    }
    $parts = [];
    foreach ($f['terms'] as $z) {
        $parts[] = $z['type'] === 'contains'
            ? t('Suchwort „%s“', $z['text'])
            : t('ohne Suchwort „%s“', $z['text']);
    }
    $d = static fn(string $day): string => date('d.m.Y', strtotime($day));
    if ($f['undated']) {
        $parts[] = t('ohne Fundzeit');
    } elseif ($f['from'] !== '' && $f['until'] !== '') {
        $parts[] = t('%s bis %s', $d($f['from']), $d($f['until']));
    } elseif ($f['from'] !== '') {
        $parts[] = t('ab %s', $d($f['from']));
    } elseif ($f['until'] !== '') {
        $parts[] = t('bis %s', $d($f['until']));
    }
    if ($f['areas'] !== []) {
        $parts[] = count($f['areas']) === 1 ? t('ein Gebiet') : t('%d Gebiete', count($f['areas']));
    }
    foreach ($f['municipalities'] as $m) {
        $parts[] = $m;
    }
    foreach ($f['regions'] as $r) {
        $parts[] = $r;
    }
    if ($f['mtb'] !== []) {
        $parts[] = t('MTB %s', implode(', ', $f['mtb']));
    }
    if ($f['elevation_from'] > 0 || $f['elevation_to'] < ELEVATION_MAX) {
        $parts[] = t('%d bis %d m', $f['elevation_from'], $f['elevation_to']);
    }
    foreach ($f['taxonomy'] as $value) {
        $parts[] = $value;
    }
    if ($f['rl'] !== []) {
        $parts[] = t('Rote Liste %s', implode(', ', $f['rl']));
    }
    if ($f['edibility'] !== [] || $f['responsibility']) {
        $parts[] = t('Speisewert');
    }
    $id = $f['identification'] ?? [];
    if (!empty($id['open']) || ($id['marks'] ?? []) !== [] || ($id['certainty'] ?? []) !== [] || ($id['worked'] ?? '') !== '') {
        $parts[] = t('Bestimmung');
    }
    return implode(' · ', $parts);
}

// ---------------------------------------------------------------------------
// Who sees what
// ---------------------------------------------------------------------------

/**
 * The recipient rows of a share that reach this user — his friend row and
 * every group row whose group he is in. Empty when none (or he left).
 *
 * @return array<array{type:string, target_id:int, rights:string[], filter:?array, added_at:string}>
 */
function list_share_rows_of(PDO $pdo, int $userId, int $containerId): array
{
    if (container_left_ready($pdo)) {
        $stmt = $pdo->prepare('SELECT 1 FROM map_container_left WHERE container_id = ? AND user_id = ?');
        $stmt->execute([$containerId, $userId]);
        if ($stmt->fetchColumn() !== false) {
            return [];
        }
    }
    $stmt = $pdo->prepare(
        "SELECT cm.type, cm.target_id, cm.rights, cm.filter, cm.added_at
           FROM map_container_members cm
          WHERE cm.container_id = ?
            AND ((cm.type = 'friend' AND cm.target_id = ?)
              OR (cm.type = 'group' AND EXISTS (SELECT 1 FROM map_group_members gm
                                                 WHERE gm.group_id = cm.target_id AND gm.user_id = ?)))"
    );
    $stmt->execute([$containerId, $userId, $userId]);
    return array_map(static fn(array $z): array => [
        'type'      => (string) $z['type'],
        'target_id' => (int) $z['target_id'],
        'rights'    => rights_read($z['rights']),
        'filter'    => list_share_filter_read($z['filter']),
        'added_at'  => (string) $z['added_at'],
    ], $stmt->fetchAll());
}

/**
 * What this user gets through the share: the union of his rows' rights
 * (spelled out per field) and their filters — null filters when any row
 * lets everything through. The owner gets everything; whoever is not
 * reached gets null.
 *
 * @return ?array{rights:string[], filters:?array, own_points:bool, owner:bool}
 */
function list_share_viewer(PDO $pdo, int $userId, int $containerId): ?array
{
    /* NOT REMEMBERED across calls, unlike list_share_list(): a request
       that saves recipients and then delivers (or a group change before a
       read) must see the new rows. The cost is two small queries per
       container, and callers ask once per container, not per point. */
    $listId = list_share_list($pdo, $containerId);
    if ($listId === null) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT owner_user_id FROM map_containers WHERE id = ?');
    $stmt->execute([$containerId]);
    if ((int) $stmt->fetchColumn() === $userId) {
        return [
            'rights' => list_share_rights_expand($pdo, $listId, array_merge(LIST_SHARE_RIGHTS, ['field:*'])),
            'filters' => null, 'own_points' => true, 'owner' => true,
        ];
    }
    $rows = list_share_rows_of($pdo, $userId, $containerId);
    if ($rows === []) {
        return null;
    }
    $rights  = [];
    $filters = [];
    $all     = false;
    foreach ($rows as $r) {
        $rights = array_merge($rights, $r['rights']);
        if ($r['filter'] === null) {
            $all = true;
        } else {
            $filters[] = $r['filter'];
        }
    }
    $rights = list_share_rights_expand($pdo, $listId, array_values(array_unique($rights)));
    return [
        'rights'     => $rights,
        'filters'    => $all ? null : $filters,
        'own_points' => in_array('own_points', $rights, true),
        'owner'      => false,
    ];
}

/**
 * Who works IN the list: the owner and every user with `own_points` —
 * oldest first, by when they were taken in. The heir is the first of them
 * after the owner (09.10.2026: „an den Nächsten, der am
 * längsten an dieser Liste schon mitarbeitet").
 *
 * THE DATE A GROUP MEMBER JOINED is the later of the two: when the group
 * was ticked and when he came into the group — the same reading as
 * container_members_since().
 *
 * @return array<int,string> user id → since (owner first)
 */
function list_share_participants(PDO $pdo, int $containerId): array
{
    $stmt = $pdo->prepare('SELECT owner_user_id, created_at FROM map_containers WHERE id = ?');
    $stmt->execute([$containerId]);
    $c = $stmt->fetch();
    if ($c === false) {
        return [];
    }
    $since = [];
    $stmt = $pdo->prepare('SELECT type, target_id, rights, added_at FROM map_container_members WHERE container_id = ?');
    $stmt->execute([$containerId]);
    foreach ($stmt->fetchAll() as $row) {
        if (!in_array('own_points', rights_read($row['rights']), true)) {
            continue;
        }
        if ($row['type'] === 'friend') {
            $users = [[(int) $row['target_id'], (string) $row['added_at']]];
        } else {
            $g = $pdo->prepare('SELECT user_id, added_at FROM map_group_members WHERE group_id = ?');
            $g->execute([(int) $row['target_id']]);
            $users = array_map(static fn(array $z): array =>
                [(int) $z['user_id'], max((string) $row['added_at'], (string) $z['added_at'])], $g->fetchAll());
        }
        foreach ($users as [$id, $at]) {
            if (!isset($since[$id]) || $at < $since[$id]) {
                $since[$id] = $at;
            }
        }
    }
    $owner = (int) $c['owner_user_id'];
    unset($since[$owner]);
    // Whoever walked out is no participant, whatever his row says.
    if (container_left_ready($pdo) && $since !== []) {
        $q = $pdo->prepare('SELECT user_id FROM map_container_left WHERE container_id = ?');
        $q->execute([$containerId]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $gone) {
            unset($since[(int) $gone]);
        }
    }
    uksort($since, static fn(int $a, int $b): int => strcmp($since[$a], $since[$b]) ?: $a <=> $b);
    return [$owner => (string) $c['created_at']] + $since;
}

/**
 * Who reads and writes the share's chat (08.10.2026): the
 * owner, everybody who works in the list, and everybody reached through a
 * GROUP — a list shared with a working group gets its own chat. A friend
 * who only looks talks to the owner in their friend chat.
 */
function list_share_chat_member(PDO $pdo, int $userId, int $containerId): bool
{
    if (array_key_exists($userId, list_share_participants($pdo, $containerId))) {
        return true;
    }
    foreach (list_share_rows_of($pdo, $userId, $containerId) as $r) {
        if ($r['type'] === 'group') {
            return true;
        }
    }
    return false;
}

// ---------------------------------------------------------------------------
// The owner's side: who gets the list, with which rights and filter
// ---------------------------------------------------------------------------

/**
 * The list's recipients, for the window „Liste bearbeiten" — with the
 * candidates that are not ticked: every friend and every own group, so the
 * window can show them all with their boxes (the same picture as „Direkt
 * teilen" in the point window).
 *
 * @return array{container:?int, recipients: array[], friends: array[], groups: array[], points:int}
 */
function list_share_overview(PDO $pdo, int $ownerId, int $listId): array
{
    list_share_own_root($pdo, $ownerId, $listId);
    $containerId = list_share_container($pdo, $listId);

    $stmt = $pdo->prepare(
        "SELECT u.id, " . sql_display_name() . " AS name FROM map_friends f
           JOIN map_users u ON u.id = IF(f.from_user_id = ?, f.to_user_id, f.from_user_id)
          WHERE (f.from_user_id = ? OR f.to_user_id = ?) AND f.status = 'accepted'
          ORDER BY name"
    );
    $stmt->execute([$ownerId, $ownerId, $ownerId]);
    $friends = array_map(static fn(array $z): array => ['id' => (int) $z['id'], 'name' => (string) $z['name']], $stmt->fetchAll());

    $stmt = $pdo->prepare(
        'SELECT g.id, g.name, (SELECT COUNT(*) FROM map_group_members gm WHERE gm.group_id = g.id) AS members
           FROM map_groups g WHERE g.owner_user_id = ? ORDER BY g.name'
    );
    $stmt->execute([$ownerId]);
    $groups = array_map(static fn(array $z): array =>
        ['id' => (int) $z['id'], 'name' => (string) $z['name'], 'members' => (int) $z['members']], $stmt->fetchAll());

    $recipients = [];
    if ($containerId !== null) {
        $stmt = $pdo->prepare(
            "SELECT cm.type, cm.target_id, cm.rights, cm.filter, cm.added_at,
                    CASE cm.type WHEN 'friend' THEN " . sql_display_name() . " ELSE g.name END AS name
               FROM map_container_members cm
               LEFT JOIN map_users u  ON cm.type = 'friend' AND u.id = cm.target_id
               LEFT JOIN map_groups g ON cm.type = 'group' AND g.id = cm.target_id
              WHERE cm.container_id = ?
              ORDER BY cm.type, name"
        );
        $stmt->execute([$containerId]);
        foreach ($stmt->fetchAll() as $z) {
            $filter = list_share_filter_read($z['filter']);
            $recipients[] = [
                'type'        => (string) $z['type'],
                'id'          => (int) $z['target_id'],
                'name'        => (string) ($z['name'] ?? t('(nicht mehr vorhanden)')),
                'rights'      => rights_read($z['rights']),
                'filter'      => is_string($z['filter']) ? (json_decode($z['filter'], true) ?: null) : null,
                'filter_text' => list_share_filter_readable($filter),
                'since'       => (string) $z['added_at'],
            ];
        }
    }

    $stmt   = $pdo->prepare('SELECT COUNT(DISTINCT pl.point_id) FROM map_point_lists pl
                               JOIN map_points p ON p.id = pl.point_id AND p.deleted_at IS NULL
                              WHERE pl.list_id = ?');
    $stmt->execute([$listId]);

    return [
        'container'  => $containerId,
        'list'       => $listId,
        'recipients' => $recipients,
        'friends'    => $friends,
        'groups'     => $groups,
        'fields'     => array_column(list_fields_of_root($pdo, $listId), 'name'),
        'points'     => (int) $stmt->fetchColumn(),
    ];
}

/**
 * The list must be the caller's own and alive. („root" is the tree's name:
 * a share used to be of a whole branch, from its root; since the Mappen,
 * 0168, every list is shared on its own.)
 */
function list_share_own_root(PDO $pdo, int $ownerId, int $listId): array
{
    $stmt = $pdo->prepare('SELECT id, name FROM map_lists WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$listId, $ownerId]);
    $list = $stmt->fetch();
    if ($list === false) {
        throw new InvalidArgumentException(t('Diese Liste gehört dir nicht (mehr).'));
    }
    return $list;
}

/**
 * Save who gets the list — the whole set at once, as the window holds it
 * when „Speichern" is pressed (everything in that window applies only with
 * Speichern, the ticks too).
 *
 * @param array<array{type:string, id:int, rights?:array, filter?:mixed}> $recipients
 * @return array{container:?int, added:int, removed:int, home:int}
 */
function list_share_save(PDO $pdo, int $ownerId, int $listId, array $recipients): array
{
    if (!list_shares_ready($pdo)) {
        throw new InvalidArgumentException(t('Listen teilen braucht die Migration 0160 — sie ist noch nicht eingespielt.'));
    }
    $list = list_share_own_root($pdo, $ownerId, $listId);

    $friends = friend_ids($pdo, $ownerId);
    $stmt = $pdo->prepare('SELECT id FROM map_groups WHERE owner_user_id = ?');
    $stmt->execute([$ownerId]);
    $groups = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $wanted = [];
    foreach ($recipients as $r) {
        $type = (string) ($r['type'] ?? '');
        $id   = (int) ($r['id'] ?? 0);
        if ($type === 'friend') {
            if (!in_array($id, $friends, true)) {
                throw new InvalidArgumentException(t('Teilen kannst du nur mit Freunden.'));
            }
        } elseif ($type === 'group') {
            if (!in_array($id, $groups, true)) {
                throw new InvalidArgumentException(t('Teilen kannst du nur mit eigenen Arbeitsgruppen.'));
            }
        } else {
            throw new InvalidArgumentException(t('Unbekannte Art.'));
        }
        $filter = list_share_filter_read($r['filter'] ?? null);
        $wanted[$type . ':' . $id] = [
            'type'   => $type,
            'id'     => $id,
            'rights' => list_share_rights_clean($pdo, $listId, (array) ($r['rights'] ?? [])),
            // Stored as it came, minus what the reader dropped — the window
            // gets back exactly the state it can draw.
            'filter' => $filter === null ? null : json_encode($r['filter'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
        ];
    }

    $containerId = list_share_container($pdo, $listId);
    if ($containerId === null && $wanted === []) {
        return ['container' => null, 'added' => 0, 'removed' => 0, 'home' => 0];
    }
    if ($containerId === null) {
        $containerId = list_share_container_create($pdo, $ownerId, $listId, (string) $list['name']);
    }

    $before = container_member_ids($pdo, $containerId);
    $participantsBefore = list_share_participants($pdo, $containerId);

    $stmt = $pdo->prepare('SELECT type, target_id FROM map_container_members WHERE container_id = ?');
    $stmt->execute([$containerId]);
    $existing = [];
    foreach ($stmt->fetchAll() as $z) {
        $existing[$z['type'] . ':' . (int) $z['target_id']] = true;
    }

    $added = 0;
    $removed = 0;
    $upsert = $pdo->prepare(
        'INSERT INTO map_container_members (container_id, type, target_id, rights, filter) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE rights = VALUES(rights), filter = VALUES(filter)'
    );
    foreach ($wanted as $key => $w) {
        $upsert->execute([$containerId, $w['type'], $w['id'],
            $w['rights'] === [] ? null : json_encode($w['rights'], JSON_UNESCAPED_UNICODE), $w['filter']]);
        if (!isset($existing[$key])) {
            $added++;
            // Ticking somebody again is aimed at him: his old departure is
            // lifted (the same reading as container_add()).
            if ($w['type'] === 'friend' && container_left_ready($pdo)) {
                $pdo->prepare('DELETE FROM map_container_left WHERE container_id = ? AND user_id = ?')
                    ->execute([$containerId, $w['id']]);
            }
        }
    }
    $drop = $pdo->prepare('DELETE FROM map_container_members WHERE container_id = ? AND type = ? AND target_id = ?');
    foreach (array_keys($existing) as $key) {
        if (!isset($wanted[$key])) {
            [$type, $id] = explode(':', $key);
            $drop->execute([$containerId, $type, (int) $id]);
            $removed++;
        }
    }

    list_share_joint_sync($pdo, $containerId);

    /* WHOEVER NO LONGER WORKS IN THE LIST TAKES HIS POINTS HOME — removed
       altogether, or only the tick „eigene Punkte setzen" taken away. His
       points are his, and a list he cannot file into is no place for them
       („Seine Punkte gehen zu ihm zurück"). */
    $participantsAfter = list_share_participants($pdo, $containerId);
    $gone = array_diff(array_keys($participantsBefore), array_keys($participantsAfter));
    $home = $gone === [] ? 0 : list_share_send_home($pdo, $containerId, $listId, $ownerId, array_values($gone), (string) $list['name']);

    // The cleaner settles what the removal leaves standing (snapshots,
    // the departure of whoever is out now).
    $after = container_member_ids($pdo, $containerId);
    if (array_diff($before, $after) !== []) {
        container_cleanup($pdo);
    }

    // Nobody left: the share is over, the container goes.
    if ($wanted === []) {
        list_share_end($pdo, $containerId);
        $containerId = null;
    }

    return ['container' => $containerId, 'added' => $added, 'removed' => $removed, 'home' => $home];
}

/** A list's container comes into being with the first recipient. */
function list_share_container_create(PDO $pdo, int $ownerId, int $listId, string $listName): int
{
    /* THE NAME IS THE LIST'S, and nobody reads it as a box's name any more
       — the sidebar says „Von Finn" and the list. It still has to be one
       per owner (UNIQUE owner, name): an old box may carry the same, so
       the list's id goes behind it when it collides. */
    $name = mb_substr($listName, 0, 90);
    $stmt = $pdo->prepare('SELECT 1 FROM map_containers WHERE owner_user_id = ? AND name = ?');
    $stmt->execute([$ownerId, $name]);
    if ($stmt->fetchColumn() !== false || container_name_reserved($name)) {
        $name = mb_substr($listName, 0, 80) . ' · ' . $listId;
    }
    $pdo->prepare('INSERT INTO map_containers (owner_user_id, name, list_id) VALUES (?, ?, ?)')
        ->execute([$ownerId, $name, $listId]);
    $containerId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO map_container_lists (container_id, list_id, user_id) VALUES (?, ?, ?)')
        ->execute([$containerId, $listId, $ownerId]);
    return $containerId;
}

/** The share row's `joint` follows the recipients: on while one of them works in the list. */
function list_share_joint_sync(PDO $pdo, int $containerId): void
{
    if (!share_joint_ready($pdo)) {
        return;
    }
    $joint = count(list_share_participants($pdo, $containerId)) > 1;
    $pdo->prepare('UPDATE map_container_lists SET joint = ? WHERE container_id = ?')
        ->execute([$joint ? 1 : 0, $containerId]);
}

/**
 * The points of users who no longer work in the list go home with them,
 * into their own list „… (ehemals gemeinsam)" — joint_points_home(), the
 * same move as leaving a joint list. One chat line per person.
 *
 * @return int how many filings came home
 */
function list_share_send_home(PDO $pdo, int $containerId, int $listId, int $ownerId, array $users, string $name): int
{
    $branch = container_branches($pdo, [['list_id' => $listId, 'user_id' => $ownerId]])[$listId] ?? [$listId];
    $lists  = implode(',', array_fill(0, count($branch), '?'));
    $people = implode(',', array_fill(0, count($users), '?'));
    $stmt   = $pdo->prepare(
        "SELECT pl.point_id, pl.list_id, p.user_id FROM map_point_lists pl
           JOIN map_points p ON p.id = pl.point_id
          WHERE pl.list_id IN ($lists) AND p.user_id IN ($people)"
    );
    $stmt->execute(array_merge($branch, array_map('intval', $users)));
    $gone = $stmt->fetchAll();
    if ($gone === []) {
        return 0;
    }
    require_once __DIR__ . '/joint_lists.php';
    $total = 0;
    foreach (joint_points_home($pdo, $listId, $gone) as $userId => $n) {
        $total += $n;
        // In HIS name, like every chat line about a deed: the chat writes
        // the person in front („Ben arbeitet nicht mehr …").
        joint_note($pdo, (int) $userId,
            $n === 1
                ? t('arbeitet nicht mehr in „%s" mit — der eigene Punkt liegt jetzt in einer eigenen Liste „%s"', $name, joint_home_name($name))
                : t('arbeitet nicht mehr in „%s" mit — die eigenen %d Punkte liegen jetzt in einer eigenen Liste „%s"', $name, $n, joint_home_name($name)),
            [$containerId]);
    }
    return $total;
}

/** The last recipient gone: the container and everything hanging on it goes. */
function list_share_end(PDO $pdo, int $containerId): void
{
    $pdo->prepare('DELETE FROM map_container_lists WHERE container_id = ?')->execute([$containerId]);
    $pdo->prepare('DELETE FROM map_container_members WHERE container_id = ?')->execute([$containerId]);
    if (container_left_ready($pdo)) {
        $pdo->prepare('DELETE FROM map_container_left WHERE container_id = ?')->execute([$containerId]);
    }
    if (table_exists($pdo, 'map_container_state')) {
        $pdo->prepare('DELETE FROM map_container_state WHERE container_id = ?')->execute([$containerId]);
    }
    if (table_exists($pdo, 'map_container_messages')) {
        $pdo->prepare('DELETE FROM map_container_messages WHERE container_id = ?')->execute([$containerId]);
    }
    $pdo->prepare('DELETE FROM map_containers WHERE id = ?')->execute([$containerId]);
}

/**
 * The list shares through which this user reaches this point — for the
 * chat lines about what he did to it.
 *
 * @return int[] container ids
 */
function list_share_point_containers(PDO $pdo, int $userId, int $pointId): array
{
    if (!list_shares_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
    $stmt->execute([$pointId]);
    $filings = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if ($filings === []) {
        return [];
    }
    $shares = $pdo->query('SELECT cl.container_id, cl.list_id, cl.user_id FROM map_container_lists cl
                             JOIN map_containers c ON c.id = cl.container_id
                            WHERE c.list_id IS NOT NULL')->fetchAll();
    $branches = container_branches($pdo, $shares);
    $out = [];
    foreach ($shares as $f) {
        $cId = (int) $f['container_id'];
        if (array_intersect($branches[(int) $f['list_id']] ?? [], $filings) !== []
            && list_share_viewer($pdo, $userId, $cId) !== null) {
            $out[] = $cId;
        }
    }
    return $out;
}

/**
 * SOMEBODY ELSE'S PICTURE REMOVED — the chat says so
 * (08.10.2026: „wie weit wird der Besitzer der Bilder darüber
 * benachrichtigt?" — until then not at all). A line in the share's chat,
 * in the remover's name, naming whose picture at which find.
 */
function list_share_picture_removed_note(PDO $pdo, int $userId, int $pointId, int $photographer): void
{
    if ($photographer === $userId) {
        return;
    }
    $containers = list_share_point_containers($pdo, $userId, $pointId);
    if ($containers === []) {
        return;
    }
    require_once __DIR__ . '/joint_lists.php';
    joint_note($pdo, $userId,
        t('hat ein Bild von %s an „%s" gelöscht', joint_user_name($pdo, $photographer), joint_point_name($pdo, $pointId)),
        $containers);
}

// ---------------------------------------------------------------------------
// The overview in the administration (Gemeinsam → Geteilt)
// ---------------------------------------------------------------------------

/**
 * A recipient's rights as one plain phrase, from the owner's side — the
 * PHP twin of summary() in assets/js/recipients.js: every tick by name, no
 * levels.
 */
function list_share_rights_readable(array $rights, array $fields = []): string
{
    $all = array_diff(LIST_SHARE_RIGHTS, $rights) === []
        && (in_array('field:*', $rights, true)
            || array_diff(array_map(static fn(string $f): string => 'field:' . $f, $fields), $rights) === []);
    if ($all) {
        return in_array('own_points', $rights, true) ? t('setzt eigene Punkte, darf alles') : t('darf alles');
    }
    $say = [
        'title' => t('Titel'), 'note' => t('Notiz'), 'move' => t('verschieben'),
        'attach_photos' => t('Bilder anhängen'), 'delete_photos' => t('Bilder löschen'),
        // „Bilder bearbeiten" is the edits' since 11.11.2026; measuring says what it does.
        'measure' => t('Bilder vermessen'), 'edit_photos' => t('Bilder bearbeiten'), 'delete' => t('Papierkorb'),
    ];
    $words = [];
    foreach ($rights as $r) {
        if (isset($say[$r])) {
            $words[] = $say[$r];
        } elseif ($r === 'field:*') {
            $words[] = t('alle Felder');
        } elseif (str_starts_with($r, 'field:')) {
            $words[] = t('Feld „%s"', substr($r, 6));
        }
    }
    $parts = [];
    if (in_array('own_points', $rights, true)) {
        $parts[] = t('setzt eigene Punkte');
    }
    $parts[] = $words === [] ? t('darf nichts ändern') : t('darf: %s', implode(', ', $words));
    return implode(' · ', $parts);
}

/** The list's name — once with its main list in front for a shared
 *  sublist („Meine Funde › Pilze"); lists are flat since the Mappen. */
function list_share_path(PDO $pdo, int $listId): string
{
    $stmt = $pdo->prepare('SELECT name FROM map_lists WHERE id = ?');
    $stmt->execute([$listId]);
    return (string) ($stmt->fetchColumn() ?: '');
}

/**
 * What I share: per list its recipients, each with what applies — for
 * „Von dir geteilt" in the administration.
 *
 * @return array<array{list:int, root:int, name:string, recipients:array}>
 */
function list_shares_given(PDO $pdo, int $ownerId): array
{
    if (!list_shares_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT c.id, c.list_id FROM map_containers c
                             JOIN map_lists l ON l.id = c.list_id AND l.deleted_at IS NULL
                            WHERE c.owner_user_id = ? AND c.list_id IS NOT NULL');
    $stmt->execute([$ownerId]);
    $out = [];
    foreach ($stmt->fetchAll() as $c) {
        $listId = (int) $c['list_id'];
        $fields = array_column(list_fields_of_root($pdo, $listId), 'name');
        $data   = list_share_overview($pdo, $ownerId, $listId);
        $out[] = [
            'list'       => $listId,
            'root'       => list_branch_root($pdo, $listId)['root'],
            'name'       => list_share_path($pdo, $listId),
            'recipients' => array_map(static fn(array $r): array => $r + [
                'what' => ($r['filter_text'] !== null ? t('Filter: %s', $r['filter_text']) : t('sieht alle Punkte'))
                    . ' · ' . list_share_rights_readable($r['rights'], $fields),
            ], $data['recipients']),
        ];
    }
    usort($out, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
    return $out;
}

/**
 * What others share with me: per list whose it is and what I may — for
 * „Mit dir geteilt" in the administration.
 *
 * @return array<array{container:int, list:int, name:string, owner:string, what:string, joint:bool}>
 */
function list_shares_received(PDO $pdo, int $userId): array
{
    if (!list_shares_ready($pdo)) {
        return [];
    }
    $out = [];
    foreach (container_mine($pdo, $userId) as $c) {
        $listId = list_share_list($pdo, (int) $c['id']);
        if ($listId === null || (int) $c['owner_user_id'] === $userId) {
            continue;
        }
        $viewer = list_share_viewer($pdo, $userId, (int) $c['id']);
        if ($viewer === null) {
            continue;
        }
        $fields = array_column(list_fields_of_root($pdo, $listId), 'name');
        $filters = $viewer['filters'];
        $out[] = [
            'container' => (int) $c['id'],
            'list'      => $listId,
            'name'      => list_share_path($pdo, $listId),
            'owner'     => (string) $c['owner_name'],
            'what'      => ($filters === null ? t('du siehst alle Punkte')
                    : t('du siehst: %s', implode(' ' . t('oder') . ' ', array_map('list_share_filter_readable', $filters))))
                . ' · ' . list_share_rights_readable($viewer['rights'], $fields),
            'joint'     => $viewer['own_points'],
        ];
    }
    usort($out, static fn(array $a, array $b): int => [$a['owner'], $a['name']] <=> [$b['owner'], $b['name']]);
    return $out;
}

// ---------------------------------------------------------------------------
// The handover — the Listenadministrator
// ---------------------------------------------------------------------------

/**
 * Who takes the list when its owner goes (deletes the list or his
 * account): whoever has worked in it longest — null when nobody works in
 * it but the owner, then the list ends and everyone has his points.
 */
function list_share_heir(PDO $pdo, int $containerId, int $ownerId): ?int
{
    $participants = list_share_participants($pdo, $containerId);
    unset($participants[$ownerId]);
    return $participants === [] ? null : (int) array_key_first($participants);
}

/**
 * The share follows the list to its heir: he owns the container now, his
 * own recipient row goes (an owner is no recipient), everybody else stays
 * with his rights — also whoever is no friend of the heir
 * (09.10.2026: „man bleibt erstmal in der Liste … da man ja an der Liste
 * mitgearbeitet hat"). He gets the window at his next map opening, and
 * the chat says what happened and what it means.
 */
function list_share_hand_over(PDO $pdo, int $containerId, int $oldOwner, int $heir, string $listName): void
{
    $pdo->prepare('UPDATE map_containers SET owner_user_id = ? WHERE id = ?')->execute([$heir, $containerId]);
    $pdo->prepare("DELETE FROM map_container_members WHERE container_id = ? AND type = 'friend' AND target_id = ?")
        ->execute([$containerId, $heir]);
    // A group of the old owner has no owner here any more — its members
    // stay, each as a friend row with the group's rights.
    $stmt = $pdo->prepare("SELECT cm.target_id, cm.rights, cm.filter, cm.added_at FROM map_container_members cm
                             JOIN map_groups g ON g.id = cm.target_id
                            WHERE cm.container_id = ? AND cm.type = 'group' AND g.owner_user_id = ?");
    $stmt->execute([$containerId, $oldOwner]);
    foreach ($stmt->fetchAll() as $g) {
        $m = $pdo->prepare('SELECT user_id FROM map_group_members WHERE group_id = ?');
        $m->execute([(int) $g['target_id']]);
        foreach ($m->fetchAll(PDO::FETCH_COLUMN) as $userId) {
            if ((int) $userId === $heir) {
                continue;
            }
            $pdo->prepare("INSERT IGNORE INTO map_container_members (container_id, type, target_id, rights, filter, added_at)
                           VALUES (?, 'friend', ?, ?, ?, ?)")
                ->execute([$containerId, (int) $userId, $g['rights'], $g['filter'], $g['added_at']]);
        }
        $pdo->prepare("DELETE FROM map_container_members WHERE container_id = ? AND type = 'group' AND target_id = ?")
            ->execute([$containerId, (int) $g['target_id']]);
    }
    // The old owner's departure row would make no sense on an owner.
    if (container_left_ready($pdo)) {
        $pdo->prepare('DELETE FROM map_container_left WHERE container_id = ? AND user_id = ?')->execute([$containerId, $heir]);
    }
    $pdo->prepare(
        "INSERT INTO map_container_state (user_id, container_id, members, admin_notice, updated_at)
              VALUES (?, ?, '', 1, NOW())
         ON DUPLICATE KEY UPDATE admin_notice = 1"
    )->execute([$heir, $containerId]);

    require_once __DIR__ . '/joint_lists.php';
    $old = joint_user_name($pdo, $oldOwner);
    $new = joint_user_name($pdo, $heir);
    // His wording (09.10.2026), in the old owner's name — the chat writes
    // the name in front: „Finn hat die gemeinsame Liste … verlassen."
    joint_note($pdo, $oldOwner,
        t('hat die gemeinsame Liste „%1$s" verlassen. Da %2$s der Listenadministrator war, gehen diese Rechte nun an %3$s über. %3$s kann jetzt Freunde dazunehmen und herausnehmen und bestimmen, was jeder in der Liste darf.', $listName, $old, $new),
        [$containerId]);
}

/** Lists the user has just become administrator of — for the window at the map's start. */
function list_share_admin_notices(PDO $pdo, int $userId): array
{
    if (!list_shares_ready($pdo) || !column_exists($pdo, 'map_container_state', 'admin_notice')) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT c.id AS container, c.list_id, l.name FROM map_container_state s
           JOIN map_containers c ON c.id = s.container_id AND c.owner_user_id = s.user_id
           JOIN map_lists l ON l.id = c.list_id
          WHERE s.user_id = ? AND s.admin_notice = 1'
    );
    $stmt->execute([$userId]);
    return array_map(static fn(array $z): array =>
        ['container' => (int) $z['container'], 'list' => (int) $z['list_id'], 'name' => (string) $z['name']], $stmt->fetchAll());
}

/** The window was seen. */
function list_share_admin_notice_seen(PDO $pdo, int $userId, int $containerId): void
{
    $pdo->prepare('UPDATE map_container_state SET admin_notice = 0 WHERE user_id = ? AND container_id = ?')
        ->execute([$userId, $containerId]);
}

// ---------------------------------------------------------------------------
// The viewer's own look of a shared list
// ---------------------------------------------------------------------------

/** Colour and shape this user gave the shared lists — list id → {color, symbol}. */
function shared_looks(PDO $pdo, int $userId): array
{
    if (!table_exists($pdo, 'map_shared_looks')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT list_id, color, symbol FROM map_shared_looks WHERE user_id = ?');
    $stmt->execute([$userId]);
    $out = [];
    foreach ($stmt as $z) {
        $out[(int) $z['list_id']] = ['color' => $z['color'], 'symbol' => $z['symbol']];
    }
    return $out;
}

/** Set (or with nulls, reset) the look of a list one sees through a share. */
function shared_look_set(PDO $pdo, int $userId, int $listId, ?string $color, ?string $symbol): void
{
    if (!table_exists($pdo, 'map_shared_looks')) {
        throw new InvalidArgumentException(t('Das braucht die Migration 0160 — sie ist noch nicht eingespielt.'));
    }
    if ($color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
        throw new InvalidArgumentException(t('Unbekannte Farbe.'));
    }
    if ($symbol !== null && !in_array($symbol, LIST_SYMBOLS, true)) {
        throw new InvalidArgumentException(t('Unbekannte Form.'));
    }
    if ($color === null && $symbol === null) {
        $pdo->prepare('DELETE FROM map_shared_looks WHERE user_id = ? AND list_id = ?')->execute([$userId, $listId]);
        return;
    }
    $pdo->prepare('INSERT INTO map_shared_looks (user_id, list_id, color, symbol) VALUES (?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE color = VALUES(color), symbol = VALUES(symbol)')
        ->execute([$userId, $listId, $color, $symbol]);
}
