<?php
/**
 * WORKING NAMES — species no list knows yet (05.11.2026,
 * migration 0189 says what they are and why).
 *
 * „Inocybe 'violacea' ad int." is a genus the lists know, a free part and
 * a status. A find titled so carries „working:<id>" as its reference, the
 * way a listed one carries „fungi:<taxnr>" — title_references_sync()
 * looks here after the lists, so a name a list knows always wins, and
 * species_genus() takes the genus from here.
 *
 * WHO SEES ONE: its maker, and the friends and working groups he shares it
 * with (map_working_name_viewers) — a friend only while he is one, a group
 * only while its owner is the maker, so the end of a friendship or of a
 * membership ends the sight too, the same rule as everywhere. Seeing it
 * means it is offered as a title and a find may carry it; it never means
 * changing it.
 *
 * WHAT A WORKING NAME CANNOT HOLD: a comma, a semicolon or a line break —
 * they separate species in a title (species_entries), and „Inocybe nova,
 * ad int." would become two names.
 */

declare(strict_types=1);

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/point_species.php';

/** The statuses in the order the window offers them; the key is stored, the Latin is shown. */
const WORKING_NAME_STATUSES = [
    'ad_int'   => 'ad int.',
    'nom_prov' => 'nom. prov.',
    'sp_nov'   => 'sp. nov.',
    'ined'     => 'ined.',
];

function working_names_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_working_names');
}

/** „Inocybe" + „'violacea'" + ad_int → „Inocybe 'violacea' ad int." */
function working_name_compose(string $genus, string $label, string $status): string
{
    $parts = [trim($genus), trim(preg_replace('/\s+/u', ' ', $label))];
    if (isset(WORKING_NAME_STATUSES[$status])) {
        $parts[] = WORKING_NAME_STATUSES[$status];
    }
    return implode(' ', array_filter($parts, static fn($p) => $p !== ''));
}

/**
 * A typed genus in the spelling of the lists — or null when none knows it.
 * DGfM first (fungi), then the lichens, then GBIF, which knows every genus
 * of fungi, plants and animals; a working name in a genus nobody knows
 * would leave the genus filter and the cloud folder without a home.
 */
function working_name_genus(PDO $pdo, string $typed): ?string
{
    $typed = trim($typed);
    if (!preg_match('/^\p{L}[\p{L}-]+$/u', $typed)) {
        return null;
    }
    foreach ([
        'SELECT genus FROM map_tax_dgfm WHERE genus = ? LIMIT 1',
        'SELECT genus FROM map_tax_lichens WHERE genus = ? LIMIT 1',
        "SELECT canonical FROM map_tax_gbif WHERE canonical = ? AND taxon_rank = 'GENUS' LIMIT 1",
    ] as $sql) {
        try {
            $q = $pdo->prepare($sql);
            $q->execute([$typed]);
            $found = $q->fetchColumn();
        } catch (PDOException) {
            continue; // a list not loaded on this installation
        }
        if (is_string($found) && $found !== '') {
            return $found;
        }
    }
    return null;
}

/** Which list already knows this name — its source key, or null. */
function working_name_listed(PDO $pdo, string $name): ?string
{
    foreach (['fungi', 'lichens', 'plants', 'gbif'] as $source) {
        try {
            if (species_reference($pdo, $name, $source) !== null) {
                return $source;
            }
        } catch (PDOException) {
            continue;
        }
    }
    return null;
}

/**
 * The working names this account may use: its own and those shared with it
 * — not the published ones, whose finds carry the real name now.
 *
 * @return list<array{id: int, user_id: int, genus: string, label: string, status: string, name: string, note: ?string, owner: string, own: bool}>
 */
function working_names_visible(PDO $pdo, int $userId): array
{
    if (!working_names_ready($pdo)) {
        return [];
    }
    require_once __DIR__ . '/friends.php';
    $friends = friends_ready($pdo) ? friend_ids($pdo, $userId) : [];
    $owners = $friends === [] ? '0' : implode(',', array_map('intval', $friends));
    $q = $pdo->prepare("SELECT DISTINCT w.*, u.first_name, u.last_name, u.email
                          FROM map_working_names w
                          JOIN map_users u ON u.id = w.user_id
                          LEFT JOIN map_working_name_viewers v ON v.working_name_id = w.id
                          LEFT JOIN map_groups g ON g.id = v.group_id AND g.owner_user_id = w.user_id
                          LEFT JOIN map_group_members m ON m.group_id = g.id AND m.user_id = ?
                         WHERE w.published_as IS NULL
                           AND (w.user_id = ?
                                OR (v.user_id = ? AND w.user_id IN ($owners))
                                OR m.user_id IS NOT NULL)
                         ORDER BY w.name");
    $q->execute([$userId, $userId, $userId]);
    $out = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => (int) $r['id'], 'user_id' => (int) $r['user_id'], 'genus' => (string) $r['genus'],
                  'label' => (string) $r['label'], 'status' => (string) $r['status'], 'name' => (string) $r['name'],
                  'note' => $r['note'], 'owner' => user_display_name($r), 'own' => (int) $r['user_id'] === $userId];
    }
    return $out;
}

/**
 * The working names for the map's start data — what the title field offers
 * ahead of the lists (pointwindow.js, workingSpecies). In the page itself,
 * so a phone in the woods offers them as it offers its stored packages.
 *
 * @return list<array{name: string, genus: string, status: string, owner: ?string}>
 */
function working_names_for_start(PDO $pdo, int $userId): array
{
    try {
        return array_map(static fn($w) => [
            'name' => $w['name'], 'genus' => $w['genus'],
            'status' => WORKING_NAME_STATUSES[$w['status']] ?? '',
            'owner' => $w['own'] ? null : $w['owner'],
        ], working_names_visible($pdo, $userId));
    } catch (PDOException $e) {
        error_log('working names for the start data: ' . $e->getMessage());
        return [];
    }
}

/**
 * The reference a title name gets from the working names this account may
 * use — „working:<id>", or null. Its own first: two colleagues may both
 * have coined „Inocybe nova", and a find is its holder's.
 */
function working_name_reference(PDO $pdo, int $userId, string $name): ?string
{
    $wanted = mb_strtolower(trim($name));
    $found = null;
    foreach (working_names_visible($pdo, $userId) as $w) {
        if (mb_strtolower($w['name']) !== $wanted) continue;
        if ($w['own']) return 'working:' . $w['id'];
        $found ??= 'working:' . $w['id'];
    }
    return $found;
}

/** One working name by id, published or not — or null. */
function working_name(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM map_working_names WHERE id = ?');
    $q->execute([$id]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/**
 * Create or change one of one's working names. Returns its id.
 *
 * A CHANGED NAME CARRIES ITS FINDS ALONG — every find that carries it, the
 * colleagues' too, is retitled (working_name_retitle), or the next save of
 * each would find its title in no list and no working name and drop the
 * reference.
 */
function working_name_save(PDO $pdo, int $userId, ?int $id, string $genusTyped, string $label, string $status, string $note): int
{
    $genus = working_name_genus($pdo, $genusTyped);
    if ($genus === null) {
        throw new InvalidArgumentException(t('„%s" kenne ich nicht als Gattung. Schreib nur die Gattung, zum Beispiel „Inocybe".', trim($genusTyped)));
    }
    $label = trim(preg_replace('/\s+/u', ' ', $label));
    if ($label === '') {
        throw new InvalidArgumentException(t('Schreib hinter die Gattung, wie du die Art nennst, zum Beispiel „nova" oder „sp. 1".'));
    }
    if (preg_match('/[,;\n]/u', $label) || mb_strlen($label) > 160) {
        throw new InvalidArgumentException(t('Ein Arbeitsname darf kein Komma und kein Semikolon enthalten. Damit trennt YASAIMAPP zwei Arten in einem Titel.'));
    }
    $status = isset(WORKING_NAME_STATUSES[$status]) ? $status : '';
    $name = working_name_compose($genus, $label, $status);
    if (($source = working_name_listed($pdo, $name)) !== null) {
        throw new InvalidArgumentException(t('„%s" steht schon in einer Namensliste. Dafür brauchst du keinen Arbeitsnamen.', $name));
    }
    $note = trim($note);

    $before = null;
    if ($id !== null) {
        $before = working_name($pdo, $id);
        if ($before === null || (int) $before['user_id'] !== $userId || $before['published_as'] !== null) {
            throw new InvalidArgumentException(t('Diesen Arbeitsnamen gibt es nicht mehr.'));
        }
    }
    $clash = $pdo->prepare('SELECT id FROM map_working_names WHERE user_id = ? AND name = ?');
    $clash->execute([$userId, $name]);
    $other = $clash->fetchColumn();
    if ($other !== false && (int) $other !== $id) {
        throw new InvalidArgumentException(t('Den Arbeitsnamen „%s" hast du schon.', $name));
    }

    if ($before === null) {
        $pdo->prepare('INSERT INTO map_working_names (user_id, genus, label, status, name, note, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$userId, $genus, $label, $status, $name, $note === '' ? null : $note]);
        $id = (int) $pdo->lastInsertId();
        working_name_link($pdo, $userId, $name);
        return $id;
    }
    $pdo->prepare('UPDATE map_working_names SET genus = ?, label = ?, status = ?, name = ?, note = ? WHERE id = ?')
        ->execute([$genus, $label, $status, $name, $note === '' ? null : $note, $id]);
    if ($before['name'] !== $name) {
        working_name_retitle($pdo, $id, (string) $before['name'], $name);
        working_name_archives_follow($pdo, $id, $userId, (string) $before['name'], $name);
    }
    return $id;
}

/**
 * Remove one of one's working names. Its finds keep their titles — they
 * only lose the reference, as any title no list knows.
 */
function working_name_delete(PDO $pdo, int $userId, int $id): void
{
    $w = working_name($pdo, $id);
    if ($w === null || (int) $w['user_id'] !== $userId) {
        throw new InvalidArgumentException(t('Diesen Arbeitsnamen gibt es nicht mehr.'));
    }
    $points = working_name_points($pdo, $id);
    $pdo->prepare('DELETE FROM map_working_names WHERE id = ?')->execute([$id]);
    foreach ($points as [$pointId, $holder]) {
        working_name_resync($pdo, $holder, $pointId);
    }
}

/**
 * The finds that carry this working name, with their holders — by the
 * reference, so a title in another spelling counts too.
 *
 * @return list<array{0: int, 1: int}> point id, holder
 */
function working_name_points(PDO $pdo, int $id): array
{
    $q = $pdo->prepare("SELECT id, user_id FROM map_points
                         WHERE deleted_at IS NULL AND extra_species LIKE ?");
    $q->execute(['%"working:' . $id . '"%']);
    return array_map(static fn($r) => [(int) $r['id'], (int) $r['user_id']], $q->fetchAll(PDO::FETCH_ASSOC));
}

/** How many finds carry each working name — id → count, for the overview. */
function working_name_counts(PDO $pdo, array $ids): array
{
    $out = [];
    foreach ($ids as $id) {
        $out[(int) $id] = count(working_name_points($pdo, (int) $id));
    }
    return $out;
}

/** A find's references and species rows anew, after a working name came, changed or went. */
function working_name_resync(PDO $pdo, int $holder, int $pointId): void
{
    title_references_refresh($pdo, $holder, $pointId);
    point_species_refresh($pdo, $holder, $pointId);
}

/**
 * A new working name finds the finds that already wear it. Somebody who
 * titled twenty finds „Inocybe nova" before the name existed should not
 * have to open each one. Only this account's own finds: whoever gets the
 * name shared is linked when it is shared with him.
 */
function working_name_link(PDO $pdo, int $userId, string $name): int
{
    $q = $pdo->prepare('SELECT id, title FROM map_points WHERE user_id = ? AND deleted_at IS NULL AND title LIKE ?');
    $q->execute([$userId, '%' . addcslashes($name, '%_\\') . '%']);
    $n = 0;
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $entries = array_map('mb_strtolower', species_entries((string) $p['title']));
        if (in_array(mb_strtolower($name), $entries, true)) {
            working_name_resync($pdo, $userId, (int) $p['id']);
            $n++;
        }
    }
    return $n;
}

/**
 * Every find that carries this working name gets $new where $old stood in
 * its title — through point_update, so its cloud folder, picture names and
 * species rows follow as after any title change. The holders' finds, not
 * only the maker's: it is one taxon, and a colleague's find left with the
 * old name would lose its reference at its next save.
 */
function working_name_retitle(PDO $pdo, int $id, string $old, string $new): int
{
    $n = 0;
    foreach (working_name_points($pdo, $id) as [$pointId, $holder]) {
        $q = $pdo->prepare('SELECT title FROM map_points WHERE id = ?');
        $q->execute([$pointId]);
        $entries = species_entries((string) $q->fetchColumn());
        $changed = false;
        foreach ($entries as &$entry) {
            if (mb_strtolower($entry) === mb_strtolower($old)) {
                $entry = $new;
                $changed = true;
            }
        }
        unset($entry);
        if (!$changed) {
            continue;
        }
        point_update($pdo, $holder, $pointId, ['title' => implode(', ', $entries)]);
        working_name_resync($pdo, $holder, $pointId);
        $n++;
    }
    return $n;
}

// --- sharing --------------------------------------------------------------------

/**
 * With whom one of one's working names is shared — the friends' and the
 * groups' ids, for the window's ticks.
 *
 * @return array{users: list<int>, groups: list<int>}
 */
function working_name_viewers(PDO $pdo, int $id): array
{
    $q = $pdo->prepare('SELECT user_id, group_id FROM map_working_name_viewers WHERE working_name_id = ?');
    $q->execute([$id]);
    $out = ['users' => [], 'groups' => []];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['user_id'] !== null) $out['users'][] = (int) $r['user_id'];
        if ($r['group_id'] !== null) $out['groups'][] = (int) $r['group_id'];
    }
    return $out;
}

/**
 * Share one of one's working names with exactly these friends and working
 * groups — the ticks of the window, saved whole. Only one's own friends and
 * groups one leads count; anything else is dropped without a word, as an
 * id somebody typed deserves.
 *
 * WHOEVER NEWLY SEES IT HAS HIS FINDS LINKED: a colleague who titled his
 * finds „Inocybe nova" before it was shared with him should not have to
 * open each one (working_name_link). Whoever stops seeing it keeps the
 * reference on his finds — they are his, and so is the determination.
 */
function working_name_share(PDO $pdo, int $userId, int $id, array $users, array $groups): void
{
    $w = working_name($pdo, $id);
    if ($w === null || (int) $w['user_id'] !== $userId) {
        throw new InvalidArgumentException(t('Diesen Arbeitsnamen gibt es nicht mehr.'));
    }
    require_once __DIR__ . '/friends.php';
    $friends = friends_ready($pdo) ? friend_ids($pdo, $userId) : [];
    $users = array_values(array_intersect(array_map('intval', $users), $friends));
    $own = $pdo->prepare('SELECT id FROM map_groups WHERE owner_user_id = ?');
    $own->execute([$userId]);
    $groups = array_values(array_intersect(array_map('intval', $groups), array_map('intval', $own->fetchAll(PDO::FETCH_COLUMN))));

    $before = working_name_audience($pdo, $id);
    $pdo->prepare('DELETE FROM map_working_name_viewers WHERE working_name_id = ?')->execute([$id]);
    $add = $pdo->prepare('INSERT INTO map_working_name_viewers (working_name_id, user_id, group_id, created_at) VALUES (?, ?, ?, NOW())');
    foreach (array_unique($users) as $u) $add->execute([$id, $u, null]);
    foreach (array_unique($groups) as $g) $add->execute([$id, null, $g]);

    foreach (array_diff(working_name_audience($pdo, $id), $before) as $viewer) {
        working_name_link($pdo, $viewer, (string) $w['name']);
    }
}

/** Everybody besides its maker who sees a working name now — the friends and the members of its groups. */
function working_name_audience(PDO $pdo, int $id): array
{
    $q = $pdo->prepare('SELECT v.user_id FROM map_working_name_viewers v WHERE v.working_name_id = ? AND v.user_id IS NOT NULL
                        UNION
                        SELECT m.user_id FROM map_working_name_viewers v
                          JOIN map_group_members m ON m.group_id = v.group_id
                         WHERE v.working_name_id = ?');
    $q->execute([$id, $id]);
    return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}

// --- published ------------------------------------------------------------------

/**
 * „VERÖFFENTLICHT ALS …" — the working name became a real species. Every
 * find that carries it is retitled to the real name, the colleagues' too
 * (the call, 05.11.2026: it is one taxon, and a find left with
 * the old name would be a remnant), and each colleague hears it in his
 * chat with the maker. The picture archives' word on the name follows, so
 * an archive folder that was assigned the working name now means the real
 * species. The working name itself stays, marked published — it is no
 * longer offered, but it remembers what it became.
 *
 * THE REAL NAME SHOULD BE IN A LIST, and usually is not yet: a new species
 * reaches GBIF and the DGfM months after its publication. So any proper
 * „Gattung art" in a genus the lists know is taken; until a list knows it,
 * the finds carry it as a free title, as any name no list knows.
 *
 * @return array{finds: int, name: string}
 */
function working_name_publish(PDO $pdo, int $userId, int $id, string $typed): array
{
    $w = working_name($pdo, $id);
    if ($w === null || (int) $w['user_id'] !== $userId || $w['published_as'] !== null) {
        throw new InvalidArgumentException(t('Diesen Arbeitsnamen gibt es nicht mehr.'));
    }
    $typed = trim(preg_replace('/\s+/u', ' ', $typed));
    if (!preg_match('/^(\p{Lu}[\p{Ll}ë-]+) ([\p{Ll}ë-]+)(?: (?:var|f|subsp)\. [\p{Ll}ë-]+)?$/u', $typed, $m)
        || working_name_genus($pdo, $m[1]) === null) {
        throw new InvalidArgumentException(t('Schreib den veröffentlichten Namen als Gattung und Art, zum Beispiel „Inocybe violacea".'));
    }
    $published = $typed;
    $old = (string) $w['name'];

    // Who carries it, before the titles change: the colleagues hear of their own finds.
    $holders = [];
    foreach (working_name_points($pdo, $id) as [, $holder]) {
        $holders[$holder] = ($holders[$holder] ?? 0) + 1;
    }
    $finds = working_name_retitle($pdo, $id, $old, $published);

    working_name_archives_follow($pdo, $id, $userId, $old, $published);

    $pdo->prepare('UPDATE map_working_names SET published_as = ?, published_at = NOW() WHERE id = ?')
        ->execute([$published, $id]);

    require_once __DIR__ . '/chat.php';
    foreach ($holders as $holder => $count) {
        if ($holder === $userId) continue;
        friend_chat_note($pdo, $userId, $holder, $count === 1
            ? t('hat den Arbeitsnamen „%s" als „%s" veröffentlicht. Dein Fund heißt jetzt „%s".', $old, $published, $published)
            : t('hat den Arbeitsnamen „%s" als „%s" veröffentlicht. Deine %d Funde heißen jetzt „%s".', $old, $published, $count, $published));
    }
    return ['finds' => $finds, 'name' => $published];
}

/**
 * The picture archives follow a working name's new name — renamed or
 * published: an archive folder assigned „Inocybe 'violacea' ad int." means
 * the species under its new name, and the pictures already read carry it
 * at once instead of after the night's reading. Only the archives of those
 * who see the name: a colleague's own namesake is his.
 */
function working_name_archives_follow(PDO $pdo, int $id, int $ownerId, string $old, string $new): void
{
    if (!table_exists($pdo, 'map_archive_names')) {
        return;
    }
    $owners = array_values(array_unique([$ownerId, ...working_name_audience($pdo, $id)]));
    $in = implode(',', array_fill(0, count($owners), '?'));
    $pdo->prepare("UPDATE map_archive_names SET species = ? WHERE species = ? AND user_id IN ($in)")
        ->execute([$new, $old, ...$owners]);
    $pdo->prepare("UPDATE map_archive_pictures p JOIN map_picture_archives a ON a.id = p.archive_id
                      SET p.name = ? WHERE p.name = ? AND a.user_id IN ($in)")
        ->execute([mb_strtolower($new), mb_strtolower($old), ...$owners]);
}

/** One's published working names, newest first — what they became, for the overview. */
function working_names_published(PDO $pdo, int $userId): array
{
    $q = $pdo->prepare('SELECT * FROM map_working_names WHERE user_id = ? AND published_as IS NOT NULL ORDER BY published_at DESC');
    $q->execute([$userId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
