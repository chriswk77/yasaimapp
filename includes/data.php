<?php
/**
 * Access to points and lists.
 *
 * Every function takes the user id and filters by it. There is deliberately
 * no way to load a point without an ownership check — with private location
 * data, this is the place where you cannot afford a mistake.
 */

declare(strict_types=1);

require_once __DIR__ . '/taxonomy.php';

// Elevations come in as ellipsoid heights and must be converted to NHN
// before saving.
require_once __DIR__ . '/geoid.php';
// And right afterwards the better elevation is fetched from the terrain model.
require_once __DIR__ . '/elevation.php';
// Municipality, region, country — the filing scheme of the record photos.
require_once __DIR__ . '/municipality.php';
// The time buckets for the sync. Only because of EIMER_AUSDRUCK: the
// expression that assigns a point to its bucket must exist exactly once.
require_once __DIR__ . '/bucket.php';
// Field times: when two devices touch the same point, the younger change
// wins per field — and the losing one is preserved.
require_once __DIR__ . '/field_times.php';
// Record photos hang on the point and travel with it.
require_once __DIR__ . '/photos.php';
// The map sheet follows directly from the coordinate.
require_once __DIR__ . '/mtb.php';
// A point's species are additionally derived into a table of their own.
require_once __DIR__ . '/point_species.php';
// The endangerment rating is looked up on the taxon, not on the point.
require_once __DIR__ . '/species.php';
// The DGfM's four lists as marks on the title species — looked up on load
// beside the endangerment, asked by the auto lists (includes/dgfm_marks.php).
require_once __DIR__ . '/dgfm_marks.php';
// Mappen (0168): the name that gathers lists in the sidebar.
require_once __DIR__ . '/folders.php';
// Aufsammlungen (0174): what is collected in the field and cultured at home.
require_once __DIR__ . '/collections.php';

/**
 * Check the client id.
 *
 * It comes from the browser and makes creating repeatable. Only the form is
 * checked — its purpose is uniqueness, not trust: whoever forges it can at
 * most prevent their own creating.
 */
function point_client_key(mixed $value): ?string
{
    $w = (string) ($value ?? '');
    return preg_match('/^[0-9a-f-]{8,36}$/i', $w) === 1 ? $w : null;
}

// ---------------------------------------------------------------------------
// Lists
// ---------------------------------------------------------------------------

/**
 * WHAT A LIST HOLDS (12.11.2026, migration 0195): the species
 * groups its title suggests from — fungi, lichens, plants, the animal
 * groups (species_groups_all). Cleaned to known keys, each once; null for
 * anything that is no list of them. An empty array is a choice too: none.
 *
 * @return ?list<string>
 */
function list_species_groups_clean(mixed $groups): ?array
{
    if (is_string($groups)) {
        $groups = json_decode($groups, true);
    }
    if (!is_array($groups)) {
        return null;
    }
    require_once __DIR__ . '/species.php';
    return array_values(array_intersect(species_groups_all(), array_map('strval', $groups)));
}

/** The stored groups as JSON for the column, or NULL. */
function list_species_groups_json(mixed $groups): ?string
{
    $clean = list_species_groups_clean($groups);
    return $clean === null ? null : json_encode($clean);
}

function lists_load(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT l.*, (
                SELECT COUNT(*)
                  FROM map_point_lists pl
                  JOIN map_points p ON p.id = pl.point_id AND p.deleted_at IS NULL
                 WHERE pl.list_id = l.id
             ) AS total
           FROM map_lists l
          WHERE l.user_id = ? AND l.deleted_at IS NULL
          ORDER BY l.sort_order, l.name'
    );
    $stmt->execute([$userId]);

    $lists = array_map(static function (array $z): array {
        $z['id']             = (int) $z['id'];
        $z['sort_order']     = (int) $z['sort_order'];
        // THE COUNT IS THE LIST'S OWN. The browser keeps it current itself
        // when a point comes into being or vanishes without network
        // (computeListCounts); two places computing the same number by
        // different routes drift apart sooner or later.
        $z['total']         = (int) $z['total'];
        $z['is_species']      = (bool) ($z['is_species'] ?? false);
        // What it holds (0195) — null before the migration or before a choice; the browser reads null as fungi.
        $z['species_groups'] = list_species_groups_clean($z['species_groups'] ?? null);
        // Before migration 0051 the column does not exist — then simply no
        // list is a container list, and all stand in their own tree.
        $z['is_container_list'] = (bool) ($z['is_container_list'] ?? false);
        // Auto lists (migration 0052): only the fact THAT the list follows
        // a filter goes out — the filter itself stays with the server. The
        // interface needs the flag alone (lock the checkbox, no target,
        // label "automatisch"), and the PWA would hold the rest only as
        // dead weight in the cache.
        $z['is_autolist'] = ($z['filter'] ?? null) !== null;
        unset($z['filter']);
        // The list's own fields. What is inherited from above, the browser
        // assembles — it has the tree in full.
        $z['fields']         = [];
        $z['parent_id']      = array_key_exists('parent_id', $z) && $z['parent_id'] !== null
            ? (int) $z['parent_id']
            : null;
        // The Mappe it lies in (0168) — before the migration, none.
        $z['folder_id'] = isset($z['folder_id']) ? (int) $z['folder_id'] : null;
        unset($z['user_id']);
        return $z;
    }, $stmt->fetchAll());

    // The own fields added in one go, instead of one query per list.
    //
    // If the table is missing because the migration has not yet been
    // applied on this server, the lists simply stay without own fields.
    // Locking out the whole map over it would be the worse answer: the rest
    // of the application does not depend on it.
    try {
    if ($lists !== []) {
        $byId = [];
        foreach ($lists as $i => $l) {
            $byId[$l['id']] = $i;
        }
        $space = implode(',', array_fill(0, count($byId), '?'));
        $withOptions = column_exists($pdo, 'map_list_fields', 'options');
        $f = $pdo->prepare(
            'SELECT list_id, name, type, is_species' . ($withOptions ? ', options' : '') . "
               FROM map_list_fields
              WHERE list_id IN ($space) ORDER BY sort_order, id"
        );
        $f->execute(array_keys($byId));
        foreach ($f as $z) {
            $lists[$byId[(int) $z['list_id']]]['fields'][] = [
                'name'      => $z['name'],
                'type'       => $z['type'],
                'is_species' => (bool) $z['is_species'],
                'options'  => isset($z['options']) && is_string($z['options'])
                    ? (json_decode($z['options'], true) ?: null) : null,
            ];
        }
    }
    } catch (PDOException $e) {
        if (($e->errorInfo[0] ?? '') !== '42S02') {
            throw $e;
        }
    }

    return $lists;
}

/**
 * Permitted marker shapes.
 *
 * The map draws them as SVG; they stand here so the API lets nothing
 * through that the map cannot render afterwards.
 *
 * THE COUNTERPART IS SHAPE_NAMES IN map.js — both lists must carry the same
 * names, and tests/symbols.php checks that. The reconciliation is not
 * pedantry: when tree, insect, stag and lichen were added only over there,
 * list_symbol() silently threw every choice back to the circle — the
 * picker showed the new shapes, and on saving they vanished without
 * comment. The fallback is meant as protection against broken values;
 * against a forgotten list only the check protects.
 */
const LIST_SYMBOLS = ['circle', 'flag', 'cross',
                       'banned', 'check', 'fire', 'parking', 'campsite', 'guesthouse', 'hotel', 'lodging', 'rest_area',
                       'mushroom', 'single_mushroom', 'bolete', 'fly_agaric',
                       'plant', 'conifer', 'broadleaf', 'flower', 'rushes', 'cattail',
                       'poor_grassland', 'calcareous_grassland', 'sphagnum_bog',
                       'insect', 'beetle', 'ladybird', 'dragonfly',
                       'deer', 'roe_deer', 'wild_boar', 'cow', 'sheep', 'lichen', 'foliose_lichen',
                       'spider', 'hanging_spider', 'harvestman', 'tick', 'scorpion',
                       'snail', 'slug', 'door_snail', 'mussel',
                       'bird', 'waterbird', 'bird_of_prey', 'owl', 'woodpecker', 'wader',
                       'frog', 'lizard', 'snake', 'fish', 'trout', 'predatory_fish', 'worm', 'crayfish'];

/**
 * Shapes that went, and what took their place.
 *
 * 'tree' → 'plant' (24.09.2026: „ich will, dass die Tanne gegen
 * den Keimling getauscht wird"). Migration 0122 moves every stored list;
 * the alias is for what the migration cannot reach — a phone that was
 * offline and still holds the conifer in its lists or in a queued save.
 * Without it list_symbol() would turn that save into a circle, and the
 * map would draw such a list as one until it refreshes. A tree of its own
 * may come back later, under a NEW name (conifer, broadleaf): 'tree' now
 * means „the plant sign before the seedling" and nothing else.
 *
 * 'harvestman_side' → 'harvestman' (the same day): the harvestman seen from
 * the side stood for an hour beside the one from above, so the maintainer could
 * compare them live; the side view won and took the key. Migration 0123
 * moves what was stored meanwhile.
 *
 * 'amphibian' → 'lizard' (the same day): the old sign of the group
 * „Reptilien und Lurche" was a four-legged animal from above, and the
 * lizard replaced it; a frog and a snake came beside it. The key is not
 * reused for the frog — lists that wore the old animal get the lizard, as
 * the maintainer decided („Eidechse ersetzt das heutige Tier"). Migration 0124.
 *
 * 'square' → 'circle' and 'diamond' → 'check' (the same day): of the six
 * geometric signs, the square was worn by no list at all and the diamond
 * by one of the maintainer's own, „Gebiet vielversprechend"; „dann Quadrat und
 * Raute raus". That list goes to the check mark, the sign he has in mind
 * for it; the square, worn by nobody, to the circle. Migration 0125.
 *
 * 'triangle' → 'circle' (the same day): worn only by Finn's forest fires,
 * which took the flame, and by the maintainer's campsites, which got a sign of
 * their own ('campsite'). Migration 0126 does both by name; the alias is
 * only for a phone that still holds the old value, and until it syncs the
 * circle is the one honest guess.
 */
const LIST_SYMBOL_ALIAS = ['tree' => 'plant', 'harvestman_side' => 'harvestman', 'amphibian' => 'lizard',
                           'square' => 'circle', 'diamond' => 'check', 'triangle' => 'circle'];


function list_symbol(mixed $value): string
{
    $value = mb_strtolower(trim((string) $value));
    $value = LIST_SYMBOL_ALIAS[$value] ?? $value;
    return in_array($value, LIST_SYMBOLS, true) ? $value : 'circle';
}

/**
 * The top of the height band — and it means „and above", not „at most".
 *
 * The twin of ELEVATION_MAX in assets/js/filter.js. The two halves cannot
 * import from each other, so the number stands twice; tests/autolists.php
 * holds this side against tests/filter.mjs, the browser's, so a change
 * here without one there does not go unnoticed.
 */
const ELEVATION_MAX = 2500;

/**
 * NO LIST LIES UNDER A LIST ANY MORE (11.10.2026, migration
 * 0168): lists are flat, and a Mappe gathers them (includes/folders.php).
 * The column parent_id stays only because dropping it buys nothing; this
 * is the one door that would write it, and it refuses — a device that
 * still knows the old window, an old queue entry, a stray import. Live
 * converted on 11.10.2026, so it refuses on every server.
 */
function list_parent_check(PDO $pdo, int $userId, int $listId, mixed $value): ?int
{
    if ((int) ($value ?? 0) <= 0) {
        return null;
    }
    throw new InvalidArgumentException(t('Listen liegen nicht mehr unter Listen. Lege sie stattdessen in eine Mappe.'));
}

function list_create(PDO $pdo, int $userId, array $data): int
{
    // As with the points: if the same client id comes a second time, the
    // first attempt succeeded and only the response got lost.
    $clientId = point_client_key($data['client_id'] ?? null);
    if ($clientId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM map_lists WHERE user_id = ? AND client_id = ?');
        $stmt->execute([$userId, $clientId]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }
    }

    // A parent is refused (list_parent_check); the call stays so that an
    // old client's parent_id fails loudly instead of being dropped.
    list_parent_check($pdo, $userId, 0, $data['parent_id'] ?? null);
    $withFolder = folders_exist($pdo);
    $folderId = $withFolder ? folder_check($pdo, $userId, $data['folder_id'] ?? null) : null;
    $sortOrder = array_key_exists('sort_order', $data) || !$withFolder
        ? (int) ($data['sort_order'] ?? 0)
        : folders_next_sort($pdo, $userId, $folderId);

    $stmt = $pdo->prepare(
        'INSERT INTO map_lists (user_id, client_id, name, color, symbol, sort_order'
        . ($withFolder ? ', folder_id' : '') . ', created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?' . ($withFolder ? ', ?' : '') . ', NOW(), NOW())'
    );
    $values = [
        $userId,
        $clientId,
        list_name($data['name'] ?? ''),
        list_color($data['color'] ?? ''),
        list_symbol($data['symbol'] ?? 'circle'),
        $sortOrder,
    ];
    if ($withFolder) {
        $values[] = $folderId;
    }
    $stmt->execute($values);

    $listId = (int) $pdo->lastInsertId();

    if (!empty($data['is_species'])) {
        $pdo->prepare('UPDATE map_lists SET is_species = 1 WHERE id = ? AND user_id = ?')
            ->execute([$listId, $userId]);
    }
    // What it holds (0195) — before the migration the choice is simply not kept.
    if (array_key_exists('species_groups', $data) && column_exists($pdo, 'map_lists', 'species_groups')) {
        $pdo->prepare('UPDATE map_lists SET species_groups = ? WHERE id = ? AND user_id = ?')
            ->execute([list_species_groups_json($data['species_groups']), $listId, $userId]);
    }
    if (isset($data['fields'])) {
        list_fields_set($pdo, $listId, (array) $data['fields']);
    }
    return $listId;
}



function list_update(PDO $pdo, int $userId, int $listId, array $data): bool
{
    $fields = [];
    $values  = [];

    if (array_key_exists('name', $data)) {
        $fields[] = 'name = ?';
        $values[]  = list_name((string) $data['name']);
    }
    if (array_key_exists('color', $data)) {
        $fields[] = 'color = ?';
        $values[]  = list_color((string) $data['color']);
    }
    if (array_key_exists('symbol', $data)) {
        $fields[] = 'symbol = ?';
        $values[]  = list_symbol($data['symbol']);
    }
    if (array_key_exists('sort_order', $data)) {
        $fields[] = 'sort_order = ?';
        $values[]  = (int) $data['sort_order'];
    }
    if (array_key_exists('is_species', $data)) {
        $fields[] = 'is_species = ?';
        $values[]  = !empty($data['is_species']) ? 1 : 0;
    }
    if (array_key_exists('species_groups', $data) && column_exists($pdo, 'map_lists', 'species_groups')) {
        $fields[] = 'species_groups = ?';
        $values[]  = list_species_groups_json($data['species_groups']);
    }
    // Under another list: refused (list_parent_check). Null is no change.
    if (array_key_exists('parent_id', $data)) {
        list_parent_check($pdo, $userId, $listId, $data['parent_id']);
    }
    /* INTO A MAPPE, OUT OF IT, INTO ANOTHER — the list goes to the end of
       where it arrives. Only when the Mappe really changes: the window
       sends folder_id with every save, and a list renamed in its Mappe
       must keep its place. */
    if (array_key_exists('folder_id', $data) && folders_exist($pdo)) {
        $folderId = folder_check($pdo, $userId, $data['folder_id']);
        $stmt = $pdo->prepare('SELECT folder_id FROM map_lists WHERE id = ? AND user_id = ?');
        $stmt->execute([$listId, $userId]);
        $now = $stmt->fetchColumn();
        if ($now !== false && ($now === null ? null : (int) $now) !== $folderId) {
            $fields[] = 'folder_id = ?';
            $values[]  = $folderId;
            if (!array_key_exists('sort_order', $data)) {
                $fields[] = 'sort_order = ?';
                $values[]  = folders_next_sort($pdo, $userId, $folderId);
            }
        }
    }

    if ($fields === []) {
        return false;
    }

    $fields[] = 'updated_at = NOW()';
    $values[]  = $listId;
    $values[]  = $userId;

    $was = $pdo->prepare('SELECT name, is_species FROM map_lists WHERE id = ? AND user_id = ?');
    $was->execute([$listId, $userId]);
    $was = $was->fetch() ?: null;

    $stmt = $pdo->prepare('UPDATE map_lists SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?');
    $stmt->execute($values);
    $changed = $stmt->rowCount() > 0;

    /* THE LIST'S NAME IS A FOLDER for points outside every species list
       („Listen/<list>/", cloud_list_parts), and switching „Artenliste"
       moves its points between that branch and the species tree. Either
       way the pictures of its points follow. A species list renamed
       touches nothing — its name stands in no folder, and the window sends
       the name with every save. */
    if ($changed && $was !== null) {
        $now = $pdo->prepare('SELECT name, is_species FROM map_lists WHERE id = ?');
        $now->execute([$listId]);
        $now = $now->fetch() ?: $was;
        if ((int) $now['is_species'] !== (int) $was['is_species']
            || ((int) $now['is_species'] === 0 && (string) $now['name'] !== (string) $was['name'])) {
            require_once __DIR__ . '/cloud_names.php';
            $points = $pdo->prepare('SELECT pl.point_id FROM map_point_lists pl
                                       JOIN map_points p ON p.id = pl.point_id
                                      WHERE pl.list_id = ? AND p.deleted_at IS NULL');
            $points->execute([$listId]);
            foreach ($points->fetchAll(PDO::FETCH_COLUMN) as $pointId) {
                cloud_names_touch($pdo, (int) $pointId);
            }
        }
    }

    if (array_key_exists('fields', $data)) {
        list_fields_set($pdo, $listId, (array) $data['fields']);
        $changed = true;
    }

    // The main list is set separately, because the previous one has to be
    // withdrawn in the process.
    return $changed;
}

/**
 * Do living points still lie in this list? The yardstick of the
 * species-list lock in list_delete: points in the trash do not count,
 * otherwise a withdrawn import could never be cleared away.
 */
function list_has_points(PDO $pdo, int $listId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM map_point_lists pl
           JOIN map_points p ON p.id = pl.point_id AND p.deleted_at IS NULL
          WHERE pl.list_id = ? LIMIT 1'
    );
    $stmt->execute([$listId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Delete a list. The points stay — whoever had no list besides this one
 * stands under "Ohne Liste" afterwards.
 *
 * A CATCH-ALL LIST "<Name> (gelöscht <Datum>)" USED TO ARISE HERE
 * AUTOMATICALLY. That dates from the time when listless points stranded
 * invisibly — today "Ohne Liste" is a visible place (sidebar, organizer),
 * and the automatism was mere clutter: a new list on every deletion, and
 * whoever deleted THAT one got the next.
 *
 * WHOEVER STILL WANTS A REAL CATCH-ALL LIST CREATES IT BY HAND and names
 * it literally "Ohne Liste": then the orphans move in there instead of
 * becoming listless. The name is the opt-in — the application itself never
 * creates such a list. For the interface both cases are the same sentence:
 * "Die Punkte stehen jetzt unter ‚Ohne Liste'."
 *
 * Returns: ['points' => how many were caught or became listless,
 * 'catch_all' => id of the hand-made catch-all list or null], or null if
 * the list does not exist (anymore).
 */
function list_delete(PDO $pdo, int $userId, int $listId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT name, color, symbol, is_species
           FROM map_lists WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$listId, $userId]);
    $list = $stmt->fetch();

    if (!$list) {
        return null;
    }
    // A species list is the collection the suggestions hang on. Whoever
    // really wants rid of it first takes out the checkmark — then it is a
    // list like any other. A slip thus costs no collection.
    //
    // BUT: the lock protects the COLLECTION, not the empty shell. A
    // species list in which no point lies any more may go —
    // import lists become species lists, and whoever has shoved everything
    // into their own lists after the import should get rid of the empty
    // remainder without the detour via the flag.
    /* A JOINT LIST IS HANDED OVER, NOT DELETED (joint lists, step 3,
       22.09.2026): it belongs to the group in everything but the column,
       and whoever hung it in may not take it from the others. It goes to
       the most senior other member of its share — fields and share row
       with it —, and only when nobody is left does the deletion below
       run. */
    require_once __DIR__ . '/joint_lists.php';
    $handed = joint_list_hand_over($pdo, $userId, $listId);
    if ($handed !== null) {
        return ['points' => 0, 'catch_all' => null, 'handed_over' => $handed];
    }

    /* A SPECIES LIST THAT STILL HOLDS FINDS IS PROTECTED
       (23.09.2026: „es ging ja darum, die Punkte zu schützen — ich kann ja
       eine Unterliste mit zweitausend Punkten löschen, und die fallen
       sofort zurück zu Ohne Liste"). Then the lock reached the whole
       branch below a species root; since the Mappen (0168) every list
       carries the flag itself, so the list decides alone. Empty it first,
       or take the flag off. After the handover above: a joint list that
       passes to another member loses nothing. */
    if ((int) $list['is_species'] === 1 && list_has_points($pdo, $listId)) {
        throw new InvalidArgumentException(
            'Diese Artenliste trägt noch Funde. '
            . 'Erst die Funde verschieben oder den Haken „Artenliste" herausnehmen, dann lässt sie sich löschen.'
        );
    }
    joint_list_deleted_note($pdo, $userId, $listId);

    $pdo->beginTransaction();
    try {
        /* THE OTHERS' POINTS IN A JOINT BRANCH COME HOME FIRST (joint
           lists, 22.09.2026): into a list of their own, named after the
           joint root. Before the orphan count below — otherwise their
           points counted as orphans and were filed into THIS user's
           „Ohne Liste", a list their holders never see. */
        $jointShares = joint_shares_of_lists($pdo, [$listId]);
        if ($jointShares !== []) {
            $stmt = $pdo->prepare(
                'SELECT pl.point_id, pl.list_id, p.user_id
                   FROM map_point_lists pl
                   JOIN map_points p ON p.id = pl.point_id
                  WHERE pl.list_id = ? AND p.user_id <> ?'
            );
            $stmt->execute([$listId, $userId]);
            joint_points_home($pdo, (int) reset($jointShares)['root'], $stmt->fetchAll());
        }

        // Points that have no list other than this one.
        $stmt = $pdo->prepare(
            'SELECT pl.point_id
               FROM map_point_lists pl
               JOIN map_points p ON p.id = pl.point_id AND p.deleted_at IS NULL
              WHERE pl.list_id = ?
                AND (SELECT COUNT(*) FROM map_point_lists x WHERE x.point_id = pl.point_id) = 1'
        );
        $stmt->execute([$listId]);
        $orphaned = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        // ALL affected points, not just the orphaned: in a moment they
        // lose their assignment to this list, and the devices must learn
        // of that — see the refresh below.
        $stmt = $pdo->prepare('SELECT point_id FROM map_point_lists WHERE list_id = ?');
        $stmt->execute([$listId]);
        $affected = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $pdo->prepare('UPDATE map_lists SET deleted_at = NOW() WHERE id = ? AND user_id = ?')
            ->execute([$listId, $userId]);
        $pdo->prepare('DELETE FROM map_point_lists WHERE list_id = ?')->execute([$listId]);

        // If this list was shared, the share falls: its anchor is gone.
        if (table_exists($pdo, 'map_container_lists')) {
            $pdo->prepare('DELETE FROM map_container_lists WHERE list_id = ?')->execute([$listId]);
        }

        // The orphans: listless under "Ohne Liste" — unless the hand-made
        // list of that name exists (header comment).
        $catchAll = null;
        if ($orphaned !== []) {
            $search = $pdo->prepare(
                "SELECT l.id FROM map_lists l
                  WHERE l.user_id = ? AND l.deleted_at IS NULL AND l.id <> ?
                    AND LOWER(TRIM(l.name)) = 'ohne liste'
                  LIMIT 1"
            );
            $search->execute([$userId, $listId]);
            $catchAllId = $search->fetchColumn();
            if ($catchAllId !== false) {
                $catchAll = (int) $catchAllId;
                $insert = $pdo->prepare('INSERT IGNORE INTO map_point_lists (point_id, list_id) VALUES (?, ?)');
                foreach ($orphaned as $pointId) {
                    $insert->execute([$pointId, $catchAll]);
                }
            }
        }

        // TOUCH THE POINTS, ALTHOUGH NOTHING ABOUT THEM HAS CHANGED.
        //
        // Their list assignment stands in map_point_lists, but their
        // fingerprint hangs solely on map_points.stand (see
        // includes/bucket.php). Without this line the print stays put
        // although the content is different — and then NO device fetches
        // the bucket again. On the phone the points would stand forever in
        // a list that no longer exists; on the same computer it would come
        // back at the next opening, because the display then comes from
        // the bucket again and not from the response one has just seen.
        //
        // This is the trap the header of bucket.php warns about, only from
        // the other side: the print can only capture what stands in its
        // own table. Whoever keeps point data elsewhere must pull it along
        // here. point_update() does the same when the lists change there
        // — it was merely forgotten when deleting a list.
        // ONLY stand, NOT updated_at. The point itself has not changed —
        // a list it lay in has vanished. Whoever looks at the "geändert
        // am" column wants to know when they last worked on THIS point; a
        // list deletion must not date a hundred points to today for them.
        // The same separation as with municipality_record().
        //
        // SET EXPLICITLY, instead of relying on ON UPDATE. MySQL only
        // touches stand when a value really changes — an UPDATE that
        // writes updated_at to the same second value is, to the
        // database, none at all. Exactly that happened while writing this
        // check: print unchanged although the row was touched.
        // CURRENT_TIMESTAMP(6) is guaranteed to advance.
        //
        // Without the column there are no buckets either (bucket_ready()),
        // and then the device fetches everything in one piece anyway —
        // nothing to pull along.
        if ($affected !== [] && column_exists($pdo, 'map_points', 'touched_at')) {
            $space = implode(',', array_fill(0, count($affected), '?'));
            $pdo->prepare("UPDATE map_points SET touched_at = CURRENT_TIMESTAMP(6)
                            WHERE user_id = ? AND id IN ($space)")
                ->execute(array_merge([$userId], $affected));
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // AFTER THE COMMIT, outside the try: if the deleted list was the source
    // of an auto list, its points now lose their basis — computing once in
    // full clears them out there. (And if the deleted one was itself the
    // auto list, after sieving on deleted_at there is nothing left to
    // maintain.)
    autolists_compute($pdo, $userId);

    return ['points' => count($orphaned), 'catch_all' => $catchAll];
}

// ---------------------------------------------------------------------------
// Auto lists — lists that follow a saved organize filter
// ---------------------------------------------------------------------------
//
// After the container list (0051) the second special list (migration 0052,
// map_lists.filter): its content is never set by hand but computed here —
// in full when created (autolists_compute), afterwards per changed
// point (autolists_refresh, called from point_create, point_update
// and point_restore; thus also from every import and every bulk
// change, because those all run through these three). Filling by hand
// bounces off the sieve in point_lists_set, which at the same time
// preserves their rows. MOUNTING into containers, by contrast, is expressly
// allowed — that is what they are for: a filter whose result the members
// can follow along.

function autolists_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_lists', 'filter');
}

/**
 * Does the point lie in the polygon? Ray casting along the parallel — for
 * excursion areas (kilometers, not continents) the earth is flat enough,
 * and the drawing of the areas on the map makes the same assumption.
 * Points ON the edge fall sometimes one way, sometimes the other — with a
 * hand-drawn area the edge is no measured quantity anyway.
 *
 * Lived in container.php (birth filter) until the place filter of the
 * auto lists needed it too — here both reach it. The browser twin is
 * inOutline in assets/js/polygon.js.
 */
function point_in_polygon(float $lat, float $lon, array $corners): bool
{
    $n = count($corners);
    if ($n < 3) {
        return false;
    }
    $inside = false;
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        [$latI, $lonI] = [(float) $corners[$i][0], (float) $corners[$i][1]];
        [$latJ, $lonJ] = [(float) $corners[$j][0], (float) $corners[$j][1]];
        if (($latI > $lat) !== ($latJ > $lat)
            && $lon < ($lonJ - $lonI) * ($lat - $latI) / ($latJ - $latI) + $lonI) {
            $inside = !$inside;
        }
    }
    return $inside;
}

/**
 * The saved filter, in fixed form. The unknown falls out silently — as
 * with shape_geometry: data can carry old things, and an auto list with a
 * half-readable filter should at least apply the readable part.
 *
 * @return array{sources: array<int|string>, terms: array, from: string,
 *               until: string, undated: bool}
 */
function autolist_filter_read(mixed $raw): array
{
    $f = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);

    $sources = [];
    foreach ((array) ($f['sources'] ?? []) as $q) {
        if ($q === 'none') {
            $sources['none'] = 'none';
        } elseif (is_numeric($q) && (int) $q > 0) {
            $sources[(int) $q] = (int) $q;
        }
    }
    $date = static fn(mixed $w): string =>
        preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $w) === 1 ? (string) $w : '';

    // THE TERM ROWS (since 14.08.2026): per row one text and the question
    // whether it should occur. The same form as in the containers' birth
    // filter (container_filter_matches) and over in assets/js/filter.js.
    // Empty ones fall out — a condition without text is none.
    $terms = [];
    foreach ((array) ($f['terms'] ?? []) as $z) {
        $text = trim((string) ($z['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $terms[] = [
            'type'  => ($z['type'] ?? '') === 'contains_not' ? 'contains_not' : 'contains',
            'text' => mb_substr($text, 0, 100),
        ];
    }

    // THE ENDANGERMENT CATEGORIES (since 15.08.2026): what should apply
    // gets checked; empty means all. Only known values from
    // RED_LIST_CATEGORIES, in their order — the counterpart is readRl()
    // in filter.js.
    $rl = [];
    foreach (RED_LIST_CATEGORIES as $category) {
        if (in_array($category, array_map('strval', (array) ($f['rl'] ?? [])), true)) {
            $rl[] = $category;
        }
    }

    // THE DGfM MARKS (17.09.2026): the food-value marks chosen, OR-linked
    // like the categories above, and the responsibility hook beside them.
    // Only known marks, in DGFM_EDIBILITY's order — readEdibility() in
    // filter.js is the counterpart; dgfm_marks_match() reads the result.
    $edibility = [];
    foreach (DGFM_EDIBILITY as $mark) {
        if (in_array($mark, array_map('strval', (array) ($f['edibility'] ?? [])), true)) {
            $edibility[] = $mark;
        }
    }

    // THE DRAWN AREAS (since 22.08.2026): lists of corners, each corner
    // two finite numbers. Rounded to six decimals like every stored
    // coordinate; capped generously — 25 areas of 200 corners is far
    // beyond anything drawn by hand, and a filter JSON should not become
    // a dumping ground.
    $areas = [];
    foreach ((array) ($f['areas'] ?? []) as $rawCorners) {
        if (count($areas) >= 25) {
            break;
        }
        $corners = [];
        foreach ((array) $rawCorners as $corner) {
            if (is_array($corner) && isset($corner[0], $corner[1])
                && is_numeric($corner[0]) && is_numeric($corner[1])) {
                $corners[] = [round((float) $corner[0], 6), round((float) $corner[1], 6)];
            }
        }
        if (count($corners) >= 3 && count($corners) <= 200) {
            $areas[] = $corners;
        }
    }

    // REGION CODES, MUNICIPALITIES, MAP SHEET (22.08.2026) — the place's
    // other three answers. Codes and names are exact-match values, the
    // sheet is a list of prefixes: "4625" means the sheet with all its
    // quarters, "4625/24" one sixteenth (mtbMatches in filter.js is the
    // counterpart). Whitespace inside a sheet entry is meaningless and
    // goes ("4625 / 2" = "4625/2").
    $shortText = static fn(mixed $w, int $max): string => mb_substr(trim((string) $w), 0, $max);
    $regions = [];
    foreach ((array) ($f['regions'] ?? []) as $code) {
        $code = $shortText($code, 10);
        if ($code !== '' && count($regions) < 50) {
            $regions[] = $code;
        }
    }
    $municipalities = [];
    foreach ((array) ($f['municipalities'] ?? []) as $name) {
        $name = $shortText($name, 100);
        if ($name !== '' && count($municipalities) < 50) {
            $municipalities[] = $name;
        }
    }
    // TWICE THROUGH HERE, IN TWO SHAPES: on creation the sheet arrives as
    // the raw comma text from the field, from a STORED filter as the list
    // this very function made of it. The string cast alone turned the
    // stored list into the word "Array" — a prefix no sheet ever starts
    // with, and every recomputation quietly emptied the list.
    $rawMtb = $f['mtb'] ?? [];
    $mtb = [];
    foreach (is_array($rawMtb) ? $rawMtb : explode(',', (string) $rawMtb) as $part) {
        $part = preg_replace('/\s+/', '', (string) $part);
        if ($part !== '' && mb_strlen($part) <= 12 && count($mtb) < 20) {
            $mtb[] = $part;
        }
    }

    /* THE HEIGHT BAND (26.08.2026) — the twin of
       elevationMatches in assets/js/filter.js. Read defensively, because a
       stored filter may come from before this field existed: absent means
       „no condition", and the neutral band is 0 to ELEVATION_MAX.

       The ceiling is written out here rather than shared with the browser:
       the two halves cannot import from each other, and a constant that
       lives in two files is the pair that drifts. What holds them together
       is the test — tests/autolists.php holds this reading against
       tests/filter.mjs, the browser's. */
    $taxonomyFilter = [];
    foreach (array_keys(TAXONOMY_RANKS) as $rank) {
        $value = trim((string) ($f['taxonomy'][$rank] ?? $f['taxonomy_' . $rank] ?? ''));
        if ($value !== '') {
            $taxonomyFilter[$rank] = mb_substr($value, 0, 120);
        }
    }

    $elevationFrom = isset($f['elevation_from']) && is_numeric($f['elevation_from'])
        ? max(0, min(ELEVATION_MAX, (int) $f['elevation_from'])) : 0;
    $elevationTo = isset($f['elevation_to']) && is_numeric($f['elevation_to'])
        ? max(0, min(ELEVATION_MAX, (int) $f['elevation_to'])) : ELEVATION_MAX;

    return [
        'sources'  => array_values($sources),
        'terms' => $terms,
        'from'      => $date($f['from'] ?? ''),
        'until'      => $date($f['until'] ?? ''),
        'undated' => !empty($f['undated']),
        'rl'       => $rl,
        'edibility'      => $edibility,
        'responsibility' => !empty($f['responsibility']),
        'areas'    => $areas,
        'regions'        => $regions,
        'municipalities' => $municipalities,
        'mtb'            => $mtb,
        'elevation_from' => $elevationFrom,
        'elevation_to'   => $elevationTo,
        // The systematic ranks — one field per rank, exactly the names
        // TAXONOMY_RANKS carries (includes/taxonomy.php). Read
        // defensively: a filter stored before these existed simply has
        // none, and that means „no condition".
        'taxonomy'       => $taxonomyFilter,
    ];
}

/**
 * Does the filter hit this point?
 *
 * THE YARDSTICK IS matches() IN organize.js — both must speak the same
 * language, otherwise the table in Organisieren shows a different set than the
 * auto list keeps afterwards. Whoever changes a rule here changes it over
 * there too: term rows over the HAYSTACK (not just the title, since
 * 14.08.2026 — matchTerms in assets/js/filter.js is the counterpart),
 * time range as string comparison on found_at, "undated" displaces the
 * time range.
 *
 * $normal are the point's lists WITHOUT auto lists, and that decides two
 * things. "none" means "in no ordinary list" — if auto lists counted, an
 * auto list with an ohne source would take its own points back out at the
 * next computation and pump forever. And an auto list as the source of
 * another would give rings — organize_autofilter does not even let them into
 * the sources, and if one stood there anyway, it would grasp at nothing
 * here, because $normal never contains it.
 *
 * @param array<int|string, array<int, true>> $branchOf per source list the
 *        lists it stands for (autolists_context) — itself, since lists are
 *        flat.
 */
function autolist_matches(array $filter, string $haystack, ?string $foundAt, ?string $day,
                          array $normal, array $branchOf, array $redList = [],
                          array $place = []): bool
{
    // $place carries the point's whereabouts: lat, lon, region,
    // municipality, mtb, mtb_quadrant. One bundle instead of six more
    // parameters — the two callers fetch all of it anyway.
    // The endangerment first, because it sieves cheapest: empty means all,
    // otherwise one of the point's categories must be checked — the same
    // reading as rlMatches() in assets/js/filter.js.
    if (($filter['rl'] ?? []) !== []) {
        $hit = false;
        foreach ($redList as $category) {
            if (in_array($category, $filter['rl'], true)) { $hit = true; break; }
        }
        if (!$hit) {
            return false;
        }
    }
    // The DGfM marks travel in the bundle like the ranks — one more
    // parameter for a line that every caller has to fill anyway.
    if (!dgfm_marks_match($filter['edibility'] ?? [], !empty($filter['responsibility']), $place['dgfm'] ?? [])) {
        return false;
    }

    // The drawn areas — inside ONE of them suffices, the same reading as
    // areaMatches() in assets/js/filter.js. A point without coordinates
    // (never the case for real rows, but the bundle allows it) stays
    // out: an area filter that cannot ask "where?" must not answer "in".
    if (($filter['areas'] ?? []) !== []) {
        $inside = false;
        if (isset($place['lat'], $place['lon'])) {
            foreach ($filter['areas'] as $corners) {
                if (point_in_polygon((float) $place['lat'], (float) $place['lon'], $corners)) {
                    $inside = true;
                    break;
                }
            }
        }
        if (!$inside) {
            return false;
        }
    }

    // Region, municipality, map sheet — exact for the first two, prefix
    // on "mtb/quadrant" for the sheet: the twins are regionMatches,
    // municipalityMatches and mtbMatches in assets/js/filter.js. A point
    // without the datum falls out of a set condition, as with the areas.
    if (($filter['regions'] ?? []) !== []
        && !in_array((string) ($place['region'] ?? ''), $filter['regions'], true)) {
        return false;
    }
    if (($filter['municipalities'] ?? []) !== []
        && !in_array((string) ($place['municipality'] ?? ''), $filter['municipalities'], true)) {
        return false;
    }
    /* THE HEIGHT BAND — the twin of elevationMatches in filter.js, and it
       has to answer identically: a point WITHOUT an elevation drops out as
       soon as the band applies, exactly as a point without a find time
       drops out of the time filter. „Unknown" is in no band. */
    $elevationFrom = (int) ($filter['elevation_from'] ?? 0);
    $elevationTo = (int) ($filter['elevation_to'] ?? ELEVATION_MAX);
    $openTop = $elevationTo >= ELEVATION_MAX;
    if ($elevationFrom > 0 || !$openTop) {
        // OUT OF $place, not out of a $point — this function has no such
        // variable, and the undefined name would have quietly made every
        // point elevationless and thus dropped the lot.
        $metres = $place['elevation_m'] ?? null;
        if ($metres === null || $metres === '' || !is_numeric($metres)) {
            return false;
        }
        $metres = (float) $metres;
        if ($metres < $elevationFrom || (!$openTop && $metres > $elevationTo)) {
            return false;
        }
    }

    /* THE SYSTEMATIC RANKS — the twin of taxonomyMatches in filter.js, and
       it must answer identically. The point's ranks come in the place
       bundle, resolved once per batch over its genus; a point whose genus
       is in no backbone drops out as soon as a rank is demanded, the same
       rule the height band follows for a missing elevation. */
    if (($filter['taxonomy'] ?? []) !== []
        && !taxonomy_matches($filter['taxonomy'], $place['taxonomy'] ?? null)) {
        return false;
    }

    if (($filter['mtb'] ?? []) !== []) {
        $sheet = (string) ($place['mtb'] ?? '');
        $full  = $sheet === '' ? '' : $sheet . '/' . (string) ($place['mtb_quadrant'] ?? '');
        $hit = false;
        foreach ($filter['mtb'] as $prefix) {
            if ($full !== '' && str_starts_with($full, $prefix)) { $hit = true; break; }
        }
        if (!$hit) {
            return false;
        }
    }

    if ($filter['sources'] !== []) {
        $inside = false;
        foreach ($filter['sources'] as $q) {
            if ($q === 'none') {
                if ($normal === []) { $inside = true; break; }
                continue;
            }
            foreach ($normal as $l) {
                if (isset($branchOf[$q][$l])) { $inside = true; break 2; }
            }
        }
        if (!$inside) {
            return false;
        }
    }
    // Every row must apply (AND) — the same rule as in matchTerms().
    // The haystack is already lowercased.
    foreach ($filter['terms'] as $z) {
        // COMMA MEANS OR within the row — the twin of the same split in
        // matchTerms (assets/js/filter.js): one of the alternatives must
        // (or, for contains_not, none may) appear. A row of bare commas
        // has no alternatives and does not count, like the empty row over
        // there.
        $alternatives = array_filter(
            array_map('trim', explode(',', mb_strtolower($z['text']))),
            static fn(string $t): bool => $t !== ''
        );
        if ($alternatives === []) {
            continue;
        }
        $inside = false;
        foreach ($alternatives as $t) {
            if (mb_strpos($haystack, $t) !== false) {
                $inside = true;
                break;
            }
        }
        if ($inside !== ($z['type'] === 'contains')) {
            return false;
        }
    }
    if ($filter['undated']) {
        // The ONLY filter that means the missing find time — and therefore
        // looks strictly at found_at, not at the day: a point whose day
        // stems from the creation time is exactly one of those sought
        // here.
        return $foundAt === null;
    }
    // ON DAYS, like inTimeRange() in assets/js/timerange.js: find time,
    // falling back to creation time. Previously the comparison on
    // found_at alone stood here — with that, a point without find time
    // fell out of every time range, while Organisieren showed it under its
    // creation day.
    if ($filter['from'] !== '' && ($day === null || $day < $filter['from'])) {
        return false;
    }
    if ($filter['until'] !== '' && ($day === null || $day > $filter['until'])) {
        return false;
    }
    return true;
}

/**
 * Everything the computation needs in one go: the auto lists with parsed
 * filter, the ids of all auto lists (for sieving out of "normal") and per
 * source list the set of lists it stands for — itself alone since lists
 * are flat (0168); the set stays, so the matchers keep one shape. null if
 * there is (yet) nothing to maintain — the common case, and it costs
 * exactly one query.
 */
function autolists_context(PDO $pdo, int $userId): ?array
{
    if (!autolists_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id, filter FROM map_lists WHERE user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$userId]);

    $autolists = [];
    $autoIds    = [];
    foreach ($stmt->fetchAll() as $z) {
        $id = (int) $z['id'];
        if ($z['filter'] !== null) {
            $autolists[$id] = autolist_filter_read($z['filter']);
            $autoIds[$id]    = true;
        }
    }
    if ($autolists === []) {
        return null;
    }

    $branchOf = [];
    foreach ($autolists as $filter) {
        foreach ($filter['sources'] as $q) {
            if ($q === 'none' || isset($branchOf[$q])) {
                continue;
            }
            $branchOf[$q] = [$q => true];
        }
    }
    return ['autolists' => $autolists, 'autoIds' => $autoIds, 'branchOf' => $branchOf];
}

/**
 * Pull along the auto lists of ONE point — the path for everyday use.
 *
 * Per point instead of per inventory, on purpose: point_create runs
 * hundreds of times in a row during an import, and a full pass over all
 * points per creation would be quadratic. Here it is three small queries,
 * and the first (autolists_context) answers the most common case — no
 * auto lists — alone.
 *
 * Points in the trash are computed too: nobody sees their rows anyway
 * (every display sieves deleted_at), but this way restoring finds the
 * correct state in place instead of having to catch it up.
 */
function autolists_refresh(PDO $pdo, int $userId, int $pointId): void
{
    $context = autolists_context($pdo, $userId);
    if ($context === null) {
        return;
    }

    // All fields that go into the haystack — since 14.08.2026 the filter no
    // longer searches only the title.
    // extra_species is included since the filter can also ask about the
    // endangerment (15.08.2026): it hangs on the taxon, not on the point,
    // and is therefore looked up here instead of read along.
    /* id AND find_number SINCE 26.09.2026. point_haystack() has taken the
       find number in since 0070 — the stored one, or the „Y-" mark built
       from the id — but this list named neither, so the haystack here had
       no number at all: an auto list on „CW-2026" matched in the browser
       and stayed empty on the server, without a word from either side.

       NO find_number_ready() GUARD, on purpose. The column has stood since
       migration 0070 (23.08.2026), long past the window in which code runs
       ahead of its migration, and a guard would bring back exactly this
       bug's shape — a number that is silently missing. Written plainly in
       the one string, tests/columns.php keeps its eye on the name. */
    $stmt = $pdo->prepare(
        'SELECT id, title, note, extra, extra_species, found_at, created_at, lat, lon,
                elevation_m, mtb, mtb_quadrant, municipality, region, country, protected_area,
                find_number
           FROM map_points WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$pointId, $userId]);
    $point = $stmt->fetch();
    if ($point === false) {
        return;
    }
    /* THE RANKS OF THE TITLE SPECIES — the genus sits in
       map_point_species, not on the point. Without this the systematic
       filter of an auto list would hold back every find. */
    $pointTaxonomy = null;
    if (column_exists($pdo, 'map_point_species', 'genus')) {
        $g = $pdo->prepare("SELECT genus, source FROM map_point_species
                             WHERE point_id = ? AND field = '' AND genus <> '' LIMIT 1");
        $g->execute([$pointId]);
        $row = $g->fetch();
        if ($row) {
            $entry = ['genus' => (string) $row['genus'], 'source' => (string) ($row['source'] ?? '')];
            $pointTaxonomy = taxonomy_of_genera($pdo, [$entry])
                [taxonomy_key($entry['genus'], $entry['source'])] ?? null;
        }
    }

    $haystack = point_haystack($point);
    $day = substr((string) ($point['found_at'] ?? $point['created_at'] ?? ''), 0, 10) ?: null;
    $identifiers = point_species_keys($point['extra_species'] ?? null);
    $rl = point_endangerment(
        $point['extra_species'] ?? null,
        $identifiers === [] ? [] : species_endangerment($pdo, $identifiers)
    );
    $dgfm = point_dgfm_marks(
        $point['extra_species'] ?? null,
        species_dgfm_marks($pdo, $identifiers),
        $point['title'] ?? null
    );

    $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
    $stmt->execute([$pointId]);
    $rows  = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $normal = array_values(array_filter(
        $rows, static fn(int $id): bool => !isset($context['autoIds'][$id])));

    $moved = false;
    foreach ($context['autolists'] as $listId => $filter) {
        $should = autolist_matches($filter, $haystack, $point['found_at'], $day,
            $normal, $context['branchOf'], $rl, [
                'lat' => $point['lat'], 'lon' => $point['lon'],
                'region' => $point['region'], 'municipality' => $point['municipality'],
                'mtb' => $point['mtb'], 'mtb_quadrant' => $point['mtb_quadrant'],
                'elevation_m' => $point['elevation_m'] ?? null,
                'taxonomy' => $pointTaxonomy,
                'dgfm' => $dgfm,
            ]);
        $is  = in_array($listId, $rows, true);
        if ($should === $is) {
            continue;
        }
        if ($should) {
            $pdo->prepare('INSERT IGNORE INTO map_point_lists (point_id, list_id) VALUES (?, ?)')
                ->execute([$pointId, $listId]);
        } else {
            $pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ? AND list_id = ?')
                ->execute([$pointId, $listId]);
        }
        $moved = true;
    }

    // Pull stand along as in list_delete: the assignment stands in
    // map_point_lists, the buckets' fingerprint hangs solely on
    // map_points.stand — without this line no other device would ever
    // learn of the new membership. Mostly the point has just been written
    // anyway, but "mostly" is too little for a fingerprint.
    if ($moved && column_exists($pdo, 'map_points', 'touched_at')) {
        $pdo->prepare('UPDATE map_points SET touched_at = CURRENT_TIMESTAMP(6) WHERE id = ?')
            ->execute([$pointId]);
    }
}

/**
 * Compute ALL of the user's auto lists in full — for the interventions
 * that hit many points at once: a list deleted (source gone), an auto
 * list freshly created.
 *
 * @return array<int, int> per auto list, how many LIVING points it keeps —
 *         for the message after creating; trash rows do not count, you
 *         would not see them anywhere anyway.
 */
function autolists_compute(PDO $pdo, int $userId): array
{
    $context = autolists_context($pdo, $userId);
    if ($context === null) {
        return [];
    }

    // As above: all fields of the haystack — find_number included, without
    // which every find here wore its „Y-" mark even where a number is
    // stored —, plus extra_species for the endangerment.
    $stmt = $pdo->prepare(
        'SELECT id, title, note, extra, extra_species, found_at, created_at, lat, lon,
                deleted_at, elevation_m, mtb, mtb_quadrant, municipality, region, country,
                protected_area, find_number
           FROM map_points WHERE user_id = ?'
    );
    $stmt->execute([$userId]);
    $points = $stmt->fetchAll();

    // One lookup for the whole inventory, not one per point — as in
    // points_load().
    $identifiers = [];
    foreach ($points as $p) {
        foreach (point_species_keys($p['extra_species'] ?? null) as $k) {
            $identifiers[$k] = true;
        }
    }
    $endangerment = $identifiers === []
        ? []
        : species_endangerment($pdo, array_keys($identifiers));
    $marksOf = species_dgfm_marks($pdo, array_keys($identifiers));

    $mapping = [];
    if ($points !== []) {
        $stmt = $pdo->prepare(
            'SELECT pl.point_id, pl.list_id
               FROM map_point_lists pl
               JOIN map_points p ON p.id = pl.point_id
              WHERE p.user_id = ?'
        );
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $z) {
            $mapping[(int) $z['point_id']][] = (int) $z['list_id'];
        }
    }

    $insert = $pdo->prepare('INSERT IGNORE INTO map_point_lists (point_id, list_id) VALUES (?, ?)');
    $remove = $pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ? AND list_id = ?');

    /* THE RANKS FOR THE WHOLE BATCH, in one sweep — this runs over the
       entire stock, and per point it would be one query per find. The
       SOURCE comes along because a genus name alone is not unique across
       the kingdoms (TAXONOMY_KINGDOM_OF_SOURCE). */
    $taxonomyOf = [];
    if ($points !== [] && column_exists($pdo, 'map_point_species', 'genus')) {
        $batchIds = array_map(static fn(array $z): int => (int) $z['id'], $points);
        $marks = implode(',', array_fill(0, count($batchIds), '?'));
        $g = $pdo->prepare("SELECT point_id, genus, source FROM map_point_species
                             WHERE point_id IN ($marks) AND field = '' AND genus <> ''");
        $g->execute($batchIds);
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

    $counts  = array_fill_keys(array_keys($context['autolists']), 0);
    $moved = [];
    foreach ($points as $p) {
        $pointId = (int) $p['id'];
        $rows  = $mapping[$pointId] ?? [];
        $normal = array_values(array_filter(
            $rows, static fn(int $id): bool => !isset($context['autoIds'][$id])));

        foreach ($context['autolists'] as $listId => $filter) {
            $should = autolist_matches($filter, point_haystack($p), $p['found_at'],
                substr((string) ($p['found_at'] ?? $p['created_at'] ?? ''), 0, 10) ?: null,
                $normal, $context['branchOf'],
                point_endangerment($p['extra_species'] ?? null, $endangerment), [
                    'lat' => $p['lat'], 'lon' => $p['lon'],
                    'region' => $p['region'], 'municipality' => $p['municipality'],
                    'mtb' => $p['mtb'], 'mtb_quadrant' => $p['mtb_quadrant'],
                    'elevation_m' => $p['elevation_m'] ?? null,
                    'taxonomy' => $taxonomyOf[(int) $p['id']] ?? null,
                    'dgfm' => point_dgfm_marks($p['extra_species'] ?? null, $marksOf, $p['title'] ?? null),
                ]);
            if ($should && $p['deleted_at'] === null) {
                $counts[$listId]++;
            }
            if ($should === in_array($listId, $rows, true)) {
                continue;
            }
            ($should ? $insert : $remove)->execute([$pointId, $listId]);
            $moved[$pointId] = true;
        }
    }

    // The same stand pull-along as when deleting a list — see the long
    // comment there: without it no device would ever fetch the new
    // assignment.
    if ($moved !== [] && column_exists($pdo, 'map_points', 'touched_at')) {
        $ids   = array_keys($moved);
        $space = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE map_points SET touched_at = CURRENT_TIMESTAMP(6)
                        WHERE user_id = ? AND id IN ($space)")
            ->execute(array_merge([$userId], $ids));
    }

    return $counts;
}

/**
 * Create the auto list: an ordinary list plus filter, then computed once
 * in full. The filter goes into the row AFTER list_create —
 * list_create deliberately does not know it, so no other caller can
 * ever set one without coming past here.
 *
 * @return array{0: int, 1: int} [id, points the filter currently hits]
 */
function autolist_create(PDO $pdo, int $userId, string $name, array $filter): array
{
    if (!autolists_ready($pdo)) {
        throw new InvalidArgumentException(
            'Autolisten brauchen Migration 0052 — die ist hier noch nicht eingespielt.');
    }
    $listId = list_create($pdo, $userId, ['name' => $name]);
    $pdo->prepare('UPDATE map_lists SET filter = ? WHERE id = ? AND user_id = ?')
        ->execute([json_encode(autolist_filter_read($filter), JSON_UNESCAPED_UNICODE),
                   $listId, $userId]);

    $counts = autolists_compute($pdo, $userId);
    return [$listId, $counts[$listId] ?? 0];
}

/**
 * A tour identity as it may be stored: twelve characters, hex, or null.
 *
 * MINTED BY THE DEVICE and therefore CHECKED here — anything a browser
 * sends is a claim, not a fact. The shape is the whole check: a value
 * that is not twelve hex characters cannot have come from mintTourId()
 * and would only make an outing nobody can find again.
 */
function point_tour_id(mixed $value): ?string
{
    $id = strtolower(trim((string) ($value ?? '')));
    return preg_match('/^[0-9a-f]{12}$/', $id) === 1 ? $id : null;
}

/**
 * The searchable text of a point, lowercased.
 *
 * THE TWIN OF pointHaystack() IN map.js — both enumerate the same
 * fields, and they MUST: the sidebar has the full points and builds its
 * haystack itself, Organisieren gets lean points and therefore this one
 * delivered ready-made. Whoever adds a field there and not here gets two
 * search results in two views — exactly the state the shared filter
 * (assets/js/filter.js) set out against. tests/haystack.php keeps
 * the two in step.
 *
 * Takes a point from point_prepare() OR a raw database row: there
 * extra stands as JSON text instead of a field — both occur (the auto
 * lists compute over raw rows, Organisieren over prepared ones), and two
 * versions of this function would be the opportunity to forget one of
 * them.
 *
 * A NEW FIELD HERE IS A NEW COLUMN IN TWO SELECTS: autolists_refresh()
 * and autolists_compute() fetch their raw rows with a written-out column
 * list, and a field they do not name is not an error but simply absent.
 * The find number was, for a month (tests/autolists.php now asks for it).
 */
function point_haystack(array $p): string
{
    $parts = [(string) ($p['title'] ?? ''), (string) ($p['note'] ?? '')];

    if (!empty($p['mtb'])) {
        $parts[] = (string) $p['mtb'];
        $parts[] = $p['mtb'] . '/' . (string) ($p['mtb_quadrant'] ?? '');
    }
    if (!empty($p['municipality'])) {
        $parts[] = (string) $p['municipality'];
        $parts[] = (string) ($p['region'] ?? '');
        $parts[] = (string) ($p['country'] ?? '');
    }
    if (!empty($p['protected_area'])) {
        $parts[] = (string) $p['protected_area'];
    }
    // The find number: "CW-2026-0042" typed into the search should find
    // the find — that is what a collection number is FOR. Without a
    // pattern it is the „Y-" mark the find shows (find_number_fallback);
    // pointHaystack() in haystack.js does the same.
    // An Aufsammlung is found by its box number, never by a „Y-" mark.
    if (!empty($p['is_collection'])) {
        if (!empty($p['collection_number'])) {
            $parts[] = (string) $p['collection_number'];
        }
    } elseif (!empty($p['find_number'])) {
        $parts[] = (string) $p['find_number'];
    } elseif ((int) ($p['id'] ?? 0) > 0) {
        require_once __DIR__ . '/find_number.php';
        $parts[] = find_number_fallback((int) $p['id']);
    }
    $extra = $p['extra'] ?? [];
    if (is_string($extra)) {
        $extra = json_decode($extra, true) ?: [];
    }
    foreach ((array) $extra as $value) {
        // Multi-value fields come as a list — over there String(value)
        // makes "a,b" of it, here implode does the same.
        $parts[] = is_array($value) ? implode(',', $value) : (string) $value;
    }

    // Trimmed because title and note always come along: a point without
    // both would otherwise yield a single space instead of the empty text.
    // Over in map.js the same .trim() stands — the twins must deliver
    // the same even in the edge case.
    return trim(mb_strtolower(implode(' ', $parts)));
}

function list_name(string $name): string
{
    $name = trim($name);
    return $name === '' ? 'Ohne Namen' : mb_substr($name, 0, 100);
}

/** Normalize a color to #rrggbb, otherwise the default. */
function list_color(string $color): string
{
    $color = trim($color);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : '#c0392b';
}

// ---------------------------------------------------------------------------
// Points
// ---------------------------------------------------------------------------

/**
 * Load points.
 *
 * Options:
 *   'since'       => timestamp — only what changed, for syncing with the PWA
 *   'trash' => true to get the deleted instead of the active
 *   'with_deleted' => true to get both (the sync needs that, because
 *                        the PWA must learn of deletions too)
 *   'buckets'      => list of bucket keys ("2026-08") — see bucket.php
 */
function points_load(PDO $pdo, int $userId, array $options = []): array
{
    $where    = ['p.user_id = ?'];
    $values = [$userId];

    if (!empty($options['trash'])) {
        $where[] = 'p.deleted_at IS NOT NULL';
    } elseif (empty($options['with_deleted'])) {
        $where[] = 'p.deleted_at IS NULL';
    }

    if (!empty($options['since'])) {
        $where[]    = 'p.updated_at > ?';
        $values[] = $options['since'];
    }

    // HERE AND NOT IN bucket.php. The gathering of lists and photos stands
    // further down in one sweep for all points; a second load function
    // beside it would mean writing that again — and forgetting it at one
    // of the two places with the next new field.
    if (!empty($options['buckets'])) {
        $buckets = array_values((array) $options['buckets']);
        $space = implode(',', array_fill(0, count($buckets), '?'));
        // The same expression as in bucket.php, only bound to the table
        // alias — copying it out here would mean maintaining it twice.
        $where[]  = bucket_expression('p') . ' IN (' . $space . ')';
        $values = array_merge($values, $buckets);
    }

    $stmt = $pdo->prepare(
        'SELECT p.* FROM map_points p WHERE ' . implode(' AND ', $where) . ' ORDER BY p.updated_at DESC'
    );
    $stmt->execute($values);
    $points = $stmt->fetchAll();

    if ($points === []) {
        return [];
    }

    // Load the list assignment in one sweep instead of per point.
    $ids   = array_column($points, 'id');
    $space = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = $pdo->prepare("SELECT point_id, list_id FROM map_point_lists WHERE point_id IN ($space)");
    $stmt->execute($ids);

    $mapping = [];
    foreach ($stmt as $z) {
        $mapping[(int) $z['point_id']][] = (int) $z['list_id'];
    }

    // The photos likewise: one query for all points, not one per point.
    $photos = photos_for_points($pdo, $userId, $ids);

    // And the cloud recordings — their count is the „bearbeitet" mark on
    // the sidebar row (cloud_recordings_for_points explains the why).
    require_once __DIR__ . '/cloud.php';
    $recordings = cloud_recordings_for_points($pdo, $ids);

    /* THE GENUS OF THE TITLE SPECIES, in one sweep like everything above.
       It is the hook the systematic filter hangs on (includes/taxonomy.php):
       everything above the genus is the same for every species in it, so
       ONE lookup answers for all of them.

       AND THE RANKS RIDE ON THE POINT, not in a map beside it. A map would
       be smaller on the wire — 195 entries for 618 finds — but it can be
       INCOMPLETE, and that was not a theoretical worry: with the buckets
       on, foreign points arrive long after the start data, and a map built
       at page load would not know their genera. Every such point would
       then drop out of an active filter without a visible reason. Attached
       here, every path inherits it, including the ones written later.

       field = '' is the TITLE species; a point may carry further species
       in its find fields, and those do not decide what the point is. */
    $taxonomyOf = [];
    if (column_exists($pdo, 'map_point_species', 'genus')) {
        $stmt = $pdo->prepare(
            "SELECT point_id, genus, source FROM map_point_species
              WHERE point_id IN ($space) AND field = '' AND genus <> ''");
        $stmt->execute($ids);
        // THE SOURCE COMES ALONG, because a genus name alone is not
        // unique across the kingdoms — see TAXONOMY_KINGDOM_OF_SOURCE.
        $keyOf = [];
        $entries = [];
        foreach ($stmt as $z) {
            $key = taxonomy_key((string) $z['genus'], (string) ($z['source'] ?? ''));
            $keyOf[(int) $z['point_id']] = $key;
            $entries[$key] = ['genus' => $z['genus'], 'source' => $z['source'] ?? ''];
        }
        $ranks = taxonomy_of_genera($pdo, array_values($entries));
        foreach ($keyOf as $pointId => $key) {
            if (isset($ranks[$key])) {
                $taxonomyOf[$pointId] = $ranks[$key];
            }
        }
    }

    // And the endangerment likewise: first collect all ids, then ONE
    // lookup for the whole inventory. Per point individually that would
    // be, at four hundred finds, four hundred queries for a datum that
    // hangs only on the species anyway.
    $identifiers = [];
    foreach ($points as $p) {
        foreach (point_species_keys($p['extra_species'] ?? null) as $k) {
            $identifiers[$k] = true;
        }
    }
    $endangerment = $identifiers === []
        ? []
        : species_endangerment($pdo, array_keys($identifiers));
    $marksOf = species_dgfm_marks($pdo, array_keys($identifiers));

    $out = array_map(
        static fn(array $p): array => point_prepare(
            $p,
            $mapping[(int) $p['id']] ?? [],
            $photos[(int) $p['id']] ?? [],
            point_endangerment($p['extra_species'] ?? null, $endangerment),
            $taxonomyOf[(int) $p['id']] ?? null,
            $recordings[(int) $p['id']] ?? 0,
            point_dgfm_marks($p['extra_species'] ?? null, $marksOf, $p['title'] ?? null)
        ),
        $points
    );
    // The sequences (migration 0112), one query for all — see includes/sequences.php.
    require_once __DIR__ . '/sequences.php';
    $sequences = sequences_for_points($pdo, $ids);
    foreach ($out as &$prepared) {
        $prepared['sequences'] = $sequences[(int) $prepared['id']] ?? [];
    }
    unset($prepared);
    /* WHO ELSE OWNS IT (co-ownership, 11.09.2026): the names of a find's
       present co-owners, by seniority. The window reads the first — it
       is the one the find goes to when the holder steps out — and shows
       the door instead of the bucket. Batched like the photos: one
       query for the whole load. */
    require_once __DIR__ . '/point_owners.php';
    $coOwners = co_owners_of_points($pdo, $ids);
    foreach ($out as &$p) {
        $p['co_owners'] = $coOwners[$p['id']] ?? [];
    }
    unset($p);
    return $out;
}

/**
 * The categories of a point's title species, from a ready-made lookup
 * table. Without a hit an empty list — a species without a rating is the
 * normal case, not a special one.
 *
 * @param array<string,string> $endangerment id => category
 * @return string[]
 */
function point_endangerment(mixed $extraSpecies, array $endangerment): array
{
    $out = [];
    foreach (point_species_keys($extraSpecies) as $identifier) {
        $category = $endangerment[$identifier] ?? null;
        if ($category !== null && !in_array($category, $out, true)) {
            $out[] = $category;
        }
    }
    return $out;
}

function point_load(PDO $pdo, int $userId, int $pointId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$pointId, $userId]);
    $point = $stmt->fetch();

    if (!$point) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
    $stmt->execute([$pointId]);

    $identifiers = point_species_keys($point['extra_species'] ?? null);

    require_once __DIR__ . '/cloud.php';
    $prepared = point_prepare(
        $point,
        array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)),
        photos_of_point($pdo, $userId, $pointId),
        point_endangerment(
            $point['extra_species'] ?? null,
            $identifiers === [] ? [] : species_endangerment($pdo, $identifiers)
        ),
        null,
        cloud_recordings_for_points($pdo, [$pointId])[$pointId] ?? 0,
        point_dgfm_marks(
            $point['extra_species'] ?? null,
            species_dgfm_marks($pdo, $identifiers),
            $point['title'] ?? null
        )
    );
    // The co-owners' names, like points_load() carries them (11.09.2026).
    require_once __DIR__ . '/point_owners.php';
    $prepared['co_owners'] = co_owners_of_points($pdo, [$pointId])[$pointId] ?? [];
    require_once __DIR__ . '/sequences.php';
    $prepared['sequences'] = sequences_for_points($pdo, [$pointId])[$pointId] ?? [];
    return $prepared;
}

/**
 * Bring a database row into the form interface and API expect.
 *
 * $redList comes FROM OUTSIDE, because it hangs on the taxon and not on
 * the row: the load functions look it up for all points at once
 * (species_endangerment in species.php) and pass through per point what
 * remains — exactly the same path as with the photos.
 *
 * @param string[] $redList categories of the title species, mostly none or one
 * @param list<array> $dgfm the DGfM marks of the title species (point_dgfm_marks) — food value, responsibility
 */
function point_prepare(array $p, array $lists, array $photos = [], array $redList = [],
                       ?array $taxonomy = null, int $recordings = 0, array $dgfm = []): array
{
    // The Aufsammlung's fields (0174) in front — keys of their own.
    return collection_prepare($p) + [
        'id'            => (int) $p['id'],
        // Output as a number, not as a string: DECIMAL comes back from PDO
        // as a string, and in JSON that would have tripped up the map.
        'lat'           => (float) $p['lat'],
        'lon'           => (float) $p['lon'],
        // For an area the corners; for an ordinary point null. The map
        // decides on this alone whether it draws a marker or an area — a
        // separate flag beside it could drift apart from it.
        'outline'        => point_outline_read($p['outline'] ?? null),
        'accuracy_m' => $p['accuracy_m'] === null ? null : (int) $p['accuracy_m'],
        'elevation_m'       => $p['elevation_m'] === null ? null : (int) $p['elevation_m'],
        'elevation_ellipsoid_m'   => ($p['elevation_ellipsoid_m']   ?? null) === null ? null : (int) $p['elevation_ellipsoid_m'],
        'elevation_accuracy_m' => ($p['elevation_accuracy_m'] ?? null) === null ? null : (int) $p['elevation_accuracy_m'],
        'elevation_source'        => $p['elevation_source'] ?? null,
        // Map sheet and quadrant, as the survey expects them.
        'mtb'                 => $p['mtb'] ?? null,
        'mtb_quadrant'        => $p['mtb_quadrant'] ?? null,
        // Municipality, region, country — separately, because one wants to
        // group and count by them. The finished path comes along as a
        // fourth value and is NOT stored: it is derivable from the other
        // three, and two versions of the same datum drift apart sooner or
        // later. Built here and not in the interface, so the rules for
        // permitted folder names stand in a single place.
        'municipality'            => $p['municipality'] ?? null,
        'region'          => $p['region'] ?? null,
        'country'               => $p['country'] ?? null,
        'folder'              => municipality_folder(
            $p['country'] ?? null, $p['region'] ?? null, $p['municipality'] ?? null),
        // Minted at creation, or null — then the id IS the number and the
        // display reads it off the point (fillDetailUneditable in map.js).
        'find_number'         => $p['find_number'] ?? null,
        // The named outing this point was created on (migration 0071), or
        // null — then the time filter shows it under its day, as always.
        'tour'                => $p['tour'] ?? null,
        'tour_id'             => $p['tour_id'] ?? null,
        'title'         => (string) $p['title'],
        'note'         => $p['note'],
        'extra'        => point_extra_read($p['extra'] ?? null),
        'found_at'   => $p['found_at'],
        'protected_area'  => $p['protected_area'],
        // The title species' endangerment — derived, not stored, and
        // therefore on every fetch as current as the Red List itself.
        'red_list'      => $redList,
        // The DGfM's marks — derived like the endangerment above, for the
        // same reason: they belong to the species and to this year's list.
        'dgfm'          => $dgfm,
        /* THE EXCURSION IT BELONGS TO, or null. A leader who mapped along
           has these finds in his OWN stock, so they never come through
           excursion_points(); the sidebar's excursion entry reads the
           reference here instead and shows them beside the guests' —
           which is the one comparison that entry exists for. */
        'excursion_id'  => isset($p['excursion_id']) && $p['excursion_id'] !== null
            ? (int) $p['excursion_id'] : null,
        // Kingdom, phylum, class, order and family of the title species —
        // derived from its genus (includes/taxonomy.php), like the
        // endangerment above derived and not stored. null where the genus
        // is in no backbone; the systematic filter then holds the point
        // back, exactly as the height band holds back a point without an
        // elevation.
        'taxonomy'      => $taxonomy,
        'lists'        => $lists,
        'photos'        => $photos,
        /* HOW MANY CLOUD RECORDINGS hang on the find — the „bearbeitet"
           mark (30.08.2026): micrographs, stacking shots,
           identification material. Derived from map_cloud_files, never
           stored. The foreign paths (container, excursions) pass no
           count and show no mark — the recordings are the owner's, and
           only the owner's own stock says „bearbeitet". */
        'recordings'    => $recordings,
        // The modification time per field — the device needs it as the
        // "seen" value when it later changes the same field itself
        // (field_times.php). Null for the archive and thus practically free
        // in the JSON; only a point changed after migration 0039 carries
        // any.
        'field_times'    => ($fz = json_decode((string) ($p['field_times'] ?? ''), true))
                            && is_array($fz) ? $fz : null,
        'created_at'   => $p['created_at'],
        'updated_at'  => $p['updated_at'],
        'deleted_at'  => $p['deleted_at'],
    ];
}

/**
 * Replace a list's extra fields.
 *
 * Always as a whole: what does not come along falls away. That is easier
 * to understand than individual creating and deleting — the list editor
 * sends the state the user sees in front of them anyway.
 *
 * Values on points remain untouched by this. They hang on the field name,
 * not on the row here: whoever accidentally deletes a field and creates it
 * again finds their entries in place.
 */
function list_fields_set(PDO $pdo, int $listId, array $fields): void
{
    $pdo->prepare('DELETE FROM map_list_fields WHERE list_id = ?')->execute([$listId]);

    $withOptions = column_exists($pdo, 'map_list_fields', 'options');
    $stmt = $pdo->prepare(
        'INSERT INTO map_list_fields (list_id, name, type, is_species' . ($withOptions ? ', options' : '') . ', sort_order)
         VALUES (?, ?, ?, ?' . ($withOptions ? ', ?' : '') . ', ?)'
    );

    $seen = [];
    $idx = 0;
    foreach ($fields as $f) {
        $name = mb_substr(trim((string) ($f['name'] ?? '')), 0, 60);
        if ($name === '') {
            continue;
        }
        // The same name twice would point at the same value — that
        // confuses more than it helps. The database index would forbid it
        // anyway.
        $key = mb_strtolower($name);
        if (isset($seen[$key])) {
            continue;
        }
        // Names of the find fields are taken: the fixed set of every
        // species list (includes/find_fields.php) wins — an own field
        // "Finder" with a different type beside it would be two things
        // under one name.
        require_once __DIR__ . '/find_fields.php';
        if (in_array($key, find_field_names(), true)) {
            continue;
        }
        $seen[$key] = true;

        $type = (string) ($f['type'] ?? 'text');
        if (!in_array($type, ['text', 'multiline', 'choice'], true)) {
            $type = 'text';
        }

        $options = null;
        if ($type === 'choice') {
            $options = field_options_read($f['options'] ?? null);
            // A selection without values is none — and before migration
            // 0046 there is no place for the values. Both honestly become
            // a text field, instead of offering an empty selection in
            // which nothing can be chosen.
            if ($options === null || !$withOptions) {
                $type      = 'text';
                $options = null;
            }
        }

        $values = [$listId, $name, $type, !empty($f['is_species']) ? 1 : 0];
        if ($withOptions) {
            $values[] = $options === null ? null : json_encode($options, JSON_UNESCAPED_UNICODE);
        }
        $values[] = $idx++;
        $stmt->execute($values);
    }
}

/**
 * A field's selection values, cleaned — or null if nothing usable
 * remains. Order stays that of the input: it is the order in the select
 * field, and somebody thought that through.
 *
 * @return ?string[]
 */
function field_options_read(mixed $raw): ?array
{
    if (!is_array($raw)) {
        return null;
    }
    $clean  = [];
    $seen = [];
    foreach ($raw as $o) {
        $o = mb_substr(trim((string) $o), 0, 100);
        if ($o === '' || isset($seen[mb_strtolower($o)])) {
            continue;
        }
        $seen[mb_strtolower($o)] = true;
        $clean[] = $o;
        if (count($clean) >= 30) {
            break;
        }
    }
    return $clean === [] ? null : $clean;
}


/**
 * Make the stable reference from name and source.
 *
 * WHY THE SERVER DOES THIS and not the browser: it has the tables. The
 * browser would otherwise have to lug the id along in the offline packages
 * — for the two BfN lists a UUID per species, 36 characters, more than the
 * name itself. The plant package would thereby grow from 89 kB to a
 * multiple, and that for a datum never needed on the device.
 *
 * Resolution goes via the VALID name. Whoever chose a synonym sees the
 * valid one in the field anyway — the search suggests nothing else.
 *
 * @return string|null "source:identifier", or null if the name is not there
 */
/**
 * The spellings under which ONE infraspecific name may stand.
 *
 * THE TWO REFERENCE WORKS WRITE THEM DIFFERENTLY, and that is not a
 * detail: the DGfM keeps the rank marker in its binomial
 * („Rhodocollybia butyracea f. asema"), GBIF drops it from its canonical
 * („Rhodocollybia butyracea asema"). Measured on 25.08.2026: 2452 DGfM
 * names carry a marker — var. 2035 times, f. 373, subsp. 44 — and of
 * 397511 GBIF rows NOT ONE does. So whoever writes the name the way one
 * work spells it found the other never, and the find silently lost its
 * identifier — the very thing that later makes synonyms find each other.
 * The maintainer brought „Rhodocollybia butyracea f. butyracea", which stands
 * in GBIF as key 3321186 and was not found.
 *
 * Which is why the search asks for ALL spellings at once instead of
 * normalizing one of them: normalizing would need a column, a migration
 * and a re-import of both works, and would still leave whatever the user
 * typed. A handful of candidates costs one indexed IN().
 *
 * The marker must carry its dot (or be the written-out „forma"): without
 * that guard a two-letter species epithet would be swallowed as a rank.
 */
function species_name_spellings(string $name): array
{
    /* THE SENSU QUALIFIER IN ITS LIST FORM (13.09.2026): the DGfM list
       writes „s. str.", „s. l." and „agg."; a hand writes „s.str.", „s. str",
       „sstr" or „agg" as readily. Brought onto the list's spelling first,
       so the exact match below has a chance — and the marker step further
       down never sees it: a name that ends in a qualifier is not
       genus-epithet-epithet. */
    $name = trim((string) preg_replace('~\s+~u', ' ', $name));
    $name = (string) preg_replace('~\s+s\.?\s*str\.?$~ui', ' s. str.', $name);
    $name = (string) preg_replace('~\s+s\.?\s*l\.?$~ui', ' s. l.', $name);
    $name = (string) preg_replace('~\s+agg\.?$~ui', ' agg.', $name);
    $bare = (string) preg_replace(
        '~\s+(?:subsp\.|ssp\.|var\.|subvar\.|fo\.|f\.|cv\.|forma)\s+~ui', ' ', $name);
    $bare = trim((string) preg_replace('~\s+~u', ' ', $bare));

    $spellings = [$name];
    if ($bare !== $name && $bare !== '') {
        $spellings[] = $bare;
    }

    // Genus, epithet, infraspecific epithet — only then is there a place
    // for a marker at all. Not for „Genus epithet agg." — that third word
    // is the qualifier, and „Genus epithet var. agg." would be nonsense.
    $words = explode(' ', $bare);
    if (count($words) === 3 && !preg_match('~^(?:agg\.|s\.|str\.|l\.|Gruppe)$~u', $words[2])) {
        foreach (['subsp.', 'var.', 'f.'] as $marker) {
            $spellings[] = $words[0] . ' ' . $words[1] . ' ' . $marker . ' ' . $words[2];
        }
    }

    return array_values(array_unique($spellings));
}

function species_reference(PDO $pdo, string $name, string $source): ?string
{
    $tables = [
        'fungi'    => ['map_tax_dgfm',     'taxnr'],
        'lichens' => ['map_tax_lichens', 'uuid'],
        'plants' => ['map_tax_plants', 'uuid'],
    ];

    // SYNONYMS GET THEIR REFERENCE TOO. If the reference work carries the
    // exactly written name only as a synonym (Hymenopellis radicata stands
    // in the DGFM with taxnr ≠ sipnr), the earlier version silently
    // discarded the reference — when typing as when importing, and nobody
    // saw it. But the reference points at EXACTLY the written name; that
    // the reference work considers it a synonym is precisely the
    // information later needed for the consolidation (sipnr or the link
    // table hold the connection to the valid name). The accepted row
    // remains preferred, in case the same name is both.
    // Every spelling of the name at once — see species_name_spellings().
    // What was written stays preferred over a variant of it.
    $spellings = species_name_spellings($name);
    $holes     = implode(', ', array_fill(0, count($spellings), '?'));

    if ($source === 'gbif') {
        $stmt = $pdo->prepare(
            "SELECT gbif_key FROM map_tax_gbif
              WHERE canonical IN ($holes)
              ORDER BY (canonical = ?) DESC, is_synonym, gbif_key LIMIT 1"
        );
        $stmt->execute([...$spellings, $name]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : 'gbif:' . $value;
    }

    if (!isset($tables[$source])) {
        return null;
    }
    [$table, $column] = $tables[$source];

    // The UUID only arrives with migration 0030 and a re-import of the
    // lists. If it is missing, there simply is no reference — the name is
    // there after all.
    if (!column_exists($pdo, $table, $column)) {
        return null;
    }

    if ($table === 'map_tax_dgfm') {
        /* THE DGfM LIST NAMES A BINOMIAL TWICE (13.09.2026, species_qualifier_sql
           in species.php says it all): „Xerocomellus chrysenteron agg." and
           „… s. str." share the binomial. Whoever writes the qualifier gets
           that row exactly; whoever writes the bare name gets the species,
           not the aggregate — the aggregate says „agg." of itself, a bare
           name does not, and this very ORDER BY used to hand the bare name
           to the aggregate because its taxnr was the lower one. */
        require_once __DIR__ . '/species.php';
        $qualified = species_qualified_name_sql($table);
        $qualifier = species_qualifier_sql($table);
        /* AND GBIF'S NAME OF A DGfM ROW (0106): „Imperator torosus" is
           what the list now offers for the DGfM's Boletus torosus, and a
           title written that way must find its way back to that row —
           the German name and the category hang on it. Third in the
           order, behind the DGfM's own spellings, so a name the DGfM
           itself carries (as a synonym row, say) keeps winning: the
           reference points at the written name where one exists. */
        $viaGbif = species_links_ready($pdo)
            ? "OR $table.taxnr IN (SELECT lk.taxnr FROM map_tax_links lk WHERE lk.gbif_name IN ($holes))"
            : '';
        $gbifFirst = species_links_ready($pdo)
            ? "((SELECT lk.gbif_name FROM map_tax_links lk WHERE lk.taxnr = $table.taxnr) = ?) DESC, "
            : '';
        $stmt = $pdo->prepare(
            "SELECT $column FROM $table WHERE binomial IN ($holes) OR $qualified IN ($holes) $viaGbif
              ORDER BY ($qualified = ?) DESC, (binomial = ?) DESC, $gbifFirst
                       (COALESCE($qualifier, '') IN ('agg.', 'Gruppe')) ASC,
                       (taxnr = sipnr) DESC, taxnr LIMIT 1"
        );
        $params = [...$spellings, ...$spellings];
        if ($viaGbif !== '') {
            $params = [...$params, ...$spellings];
        }
        $params[] = $name;
        $params[] = $name;
        if ($gbifFirst !== '') {
            $params[] = $name;
        }
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return ($value === false || $value === null) ? null : $source . ':' . $value;
    }

    $stmt = $pdo->prepare(
        "SELECT $column FROM $table WHERE binomial IN ($holes)
          ORDER BY (binomial = ?) DESC, (taxnr = sipnr) DESC, taxnr LIMIT 1"
    );
    $stmt->execute([...$spellings, $name]);
    $value = $stmt->fetchColumn();

    return ($value === false || $value === null) ? null : $source . ':' . $value;
}

/**
 * Split a field value into its individual names.
 *
 * THE SAME SEPARATORS AS IN THE BROWSER — line break, comma, semicolon.
 * The counterpart is ARTEN_TRENNER in assets/js/map.js; if the two
 * drift apart, the browser sends names the server does not find again in
 * the field, and silently no reference comes into being.
 *
 * @return list<string>
 */
function species_entries(string $value): array
{
    $parts = preg_split('/[\n,;]/', $value) ?: [];
    $out  = [];
    foreach ($parts as $t) {
        $t = trim($t);
        if ($t !== '' && !in_array($t, $out, true)) {
            $out[] = $t;
        }
    }
    return $out;
}

/**
 * The taxon ids of the SPECIES OF THE FIND — that is, the title's.
 *
 * ONLY THE TITLE (key ""), NOT ACCOMPANYING VEGETATION AND HOST. The point
 * IS its title; what stands in the extra fields is surroundings. If the
 * endangerment took them along, a checkmark at "3" would also mean: show
 * me finds whose accompanying tree is endangered — and in the list would
 * then stand a find whose reason you cannot see, because the title stands
 * there and not the field that hit. A hit you cannot see reads like a
 * bug.
 *
 * @return string[] ids of the form "fungi:49097"
 */
function point_species_keys(mixed $raw): array
{
    $all = json_decode((string) ($raw ?? ''), true);
    if (!is_array($all) || !isset($all['']) || !is_array($all[''])) {
        return [];
    }

    $out = [];
    foreach ($all[''] as $identifier) {
        $identifier = trim((string) $identifier);
        if ($identifier !== '' && !in_array($identifier, $out, true)) {
            $out[] = $identifier;
        }
    }
    return $out;
}

/**
 * A point's references from what the browser sent.
 *
 * THE FORM IS "FIELD, THEN NAME, THEN REFERENCE":
 *
 *   {"Begleitvegetation": {"Fagus sylvatica": "plants:…",
 *                          "Quercus robur":   "plants:…"},
 *    "Wirt":              {"Fagus sylvatica": "plants:…"}}
 *
 * BY NAME, NOT BY ROW NUMBER. Whoever inserts a species at the top shifts
 * all below by one — and every reference would afterwards point at the
 * wrong plant. That would be the worst sort of bug: silent, plausible and
 * only noticeable years later. Fastened to the name, the assignment
 * survives every reordering, and what gets deleted loses its reference by
 * itself.
 *
 * Single-line fields carry the same form with exactly one entry. Having a
 * second form just for them would mean making every reading site
 * distinguish both.
 *
 * WHAT THE BROWSER SENDS IS VERIFIED: a reference is only created if the
 * name really stands in the field. Without this check, a doctored request
 * could attach a taxon whose name is nowhere to be seen — and a reference
 * claiming something other than the text beside it is worse than none.
 *
 * @param array $sources  per field: {name: source}. A string instead of
 *                        the object is the old form (one field, one taxon)
 *                        and remains accepted — a browser with an old
 *                        program in its cache should not run aground.
 * @param string|null $old  previously stored JSON, for preserving
 * @return string|null JSON for map_points.extra_species, or null if nothing
 */
function point_species_references(PDO $pdo, array $extra, array $sources, ?string $old = null): ?string
{
    // Before migration 0030 there is neither the column nor the UUIDs.
    // Without this check, every saving of a point would break as soon as
    // the code is deployed and the migration not yet applied — the same
    // bug as with the roles, only at a place used even more often.
    if (!column_exists($pdo, 'map_points', 'extra_species')) {
        return null;
    }

    $references = [];

    foreach ($sources as $field => $source) {
        $value = trim((string) ($extra[$field] ?? ''));
        if ($value === '') {
            continue;
        }
        $existing = species_entries($value);

        // Old form: the whole field is the name.
        if (is_string($source)) {
            $v = species_reference($pdo, $value, $source);
            if ($v !== null) {
                $references[$field] = [$value => $v];
            }
            continue;
        }
        if (!is_array($source)) {
            continue;
        }

        foreach ($source as $name => $q) {
            $name = trim((string) $name);
            if ($name === '' || !is_string($q) || !in_array($name, $existing, true)) {
                continue;
            }
            $v = species_reference($pdo, $name, $q);
            if ($v !== null) {
                $references[$field][$name] = $v;
            }
        }
    }

    $references = species_references_preserve($references, $old, $extra);

    return $references === [] ? null : json_encode($references, JSON_UNESCAPED_UNICODE);
}

/**
 * What was already there stays there.
 *
 * THE BUG THIS FIXES: the browser only remembers what was chosen from the
 * suggestion list in THIS session — on opening a point it starts at zero.
 * So whoever opens a point with references, changes the note and saves,
 * sent not a single source along. And because the interface ALWAYS sends
 * the field values along, the column was recomputed each time in the
 * process — from nothing. A reference thus survived exactly until the
 * next change to the point. Measured and confirmed before this came into
 * being.
 *
 * Only what still fits the text is preserved: if the name no longer
 * stands in the field, its reference goes with it. That is exactly why it
 * hangs on the name.
 */
function species_references_preserve(array $new, ?string $old, array $extra): array
{
    $previous = ($old === null || $old === '') ? [] : json_decode($old, true);
    if (!is_array($previous)) {
        $previous = [];
    }

    foreach ($previous as $field => $entries) {
        $value = trim((string) ($extra[$field] ?? ''));
        if ($value === '') {
            continue;                       // the field is empty, so away with it
        }
        $existing = species_entries($value);

        // The old form knew no name — there the whole field value WAS the
        // name. It can thus be carried over exactly when this one entry
        // still stands in the field.
        if (is_string($entries)) {
            $entries = ($existing === [$value]) ? [$value => $entries] : [];
        }
        if (!is_array($entries)) {
            continue;
        }

        foreach ($entries as $name => $reference) {
            $name = trim((string) $name);
            if ($name === '' || !is_string($reference)) {
                continue;
            }
            // Do not overwrite: what was just newly chosen takes precedence.
            if (isset($new[$field][$name]) || !in_array($name, $existing, true)) {
                continue;
            }
            $new[$field][$name] = $reference;
        }
    }

    // A SORT by the order in the text ONCE STOOD HERE. It was without
    // effect: the column has type JSON, and MySQL stores objects
    // normalized — the keys are sorted by length and then binary, what
    // you write in makes no difference. Measured: "Fagus, Quercus,
    // Betula" became on reading "Quercus, Betula, Fagus", that is 13, 14,
    // 15 characters.
    //
    // That is no loss, because the order stands in the field text anyway,
    // and that is the authoritative datum. Whoever displays the
    // references later walks through the text and looks them up here —
    // not the other way round.
    return array_filter($new, static fn($e): bool => is_array($e) && $e !== []);
}

/**
 * How many corners an outline may have.
 *
 * Typed by hand it is five to thirty. The limit is aimed at the import
 * still to come: a protected-area boundary taken over from a specialist
 * system can have thousands of vertices, and a single point should not
 * fill half the table. At a thousand corners it is around 22 kB — plenty
 * for everything somebody walks around in the field, and far below what
 * hurts the column.
 */
const OUTLINE_CORNERS_MAX = 1000;

/**
 * Prepare an area's outline for saving.
 *
 * Expects [[lat, lon], …] and returns JSON — or null, and null here
 * expressly means "not an area, but a point".
 *
 * TO SIX DECIMAL PLACES, like lat and lon in their DECIMAL columns. Thus
 * the outline exists at the same resolution as the spot it surrounds —
 * around eleven centimeters. Anything beyond would be feigned precision
 * and would not stay stable through writing back and forth anyway.
 *
 * What does not fit the form falls out instead of rejecting the request:
 * a single unusable corner should not prevent saving a point at which
 * somebody is standing in the forest right now. If fewer than three
 * remain, it is no area — then null.
 */
function point_outline_write(mixed $data): ?string
{
    if (!is_array($data)) {
        return null;
    }

    $corners = [];
    foreach ($data as $corner) {
        if (count($corners) >= OUTLINE_CORNERS_MAX) {
            break;
        }
        $corner = (array) $corner;
        if (!isset($corner[0], $corner[1]) || !is_numeric($corner[0]) || !is_numeric($corner[1])) {
            continue;
        }
        $lat = (float) $corner[0];
        $lon = (float) $corner[1];
        if (!is_finite($lat) || !is_finite($lon)
            || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            continue;
        }
        $corners[] = [round($lat, 6), round($lon, 6)];
    }

    return count($corners) < 3 ? null : json_encode($corners);
}

/**
 * A point's stored outline, as a list or null.
 *
 * Checked on reading too: the column can hold something from hand, from
 * an import or from an older version that the map cannot make sense of. A
 * half outline on the screen would be worse than none.
 */
function point_outline_read(mixed $raw): ?array
{
    if ($raw === null || $raw === '') {
        return null;
    }
    $d = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($d) || count($d) < 3) {
        return null;
    }

    $corners = [];
    foreach ($d as $corner) {
        if (!is_array($corner) || !isset($corner[0], $corner[1])
            || !is_numeric($corner[0]) || !is_numeric($corner[1])) {
            return null;
        }
        $corners[] = [(float) $corner[0], (float) $corner[1]];
    }
    return $corners;
}

/** A point's stored field values. */
function point_extra_read(?string $raw): object
{
    if ($raw === null || $raw === '') {
        return new stdClass();
    }
    $d = json_decode($raw);
    return is_object($d) ? $d : new stdClass();
}

/**
 * Prepare field values for saving.
 *
 * Empty values fall out instead of standing there as empty strings —
 * otherwise the JSON would grow with every field somebody once saw and
 * did not fill in.
 */
function point_extra_write(mixed $data): ?string
{
    if (!is_array($data) && !is_object($data)) {
        return null;
    }

    $clean = [];
    foreach ((array) $data as $name => $value) {
        $name = mb_substr(trim((string) $name), 0, 60);
        $value = trim((string) $value);
        if ($name === '' || $value === '') {
            continue;
        }
        $clean[$name] = mb_substr($value, 0, 500);
    }

    return $clean === [] ? null : json_encode($clean, JSON_UNESCAPED_UNICODE);
}

/**
 * A moment in time from the device, checked.
 *
 * THE DEVICE'S CLOCK IS THE ONLY ONE THAT KNOWS — the server may learn of
 * the find hours later, when the queue drains. It is in return also the
 * only one that can run wrong: therefore it is checked whether a date
 * stands there at all, and everything beyond one day in the future falls
 * away. A device off by years would otherwise bend the whole timeline —
 * and of all things the sorting "newest first" would then hang on it.
 *
 * Backwards there is no limit: whoever records an old find later may.
 */
function point_find_datetime(mixed $value): ?string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return null;
    }

    try {
        $time = new DateTimeImmutable($raw);
    } catch (Throwable) {
        return null;
    }

    if ($time > new DateTimeImmutable('+1 day')) {
        return null;
    }
    return $time->format('Y-m-d H:i:s');
}

/** A point's stored coordinates, or null. */
function point_coordinates(PDO $pdo, int $userId, int $pointId): ?array
{
    $stmt = $pdo->prepare('SELECT lat, lon FROM map_points WHERE id = ? AND user_id = ?');
    $stmt->execute([$pointId, $userId]);
    $z = $stmt->fetch();
    return $z === false ? null : [(float) $z['lat'], (float) $z['lon']];
}

/**
 * Determine the three elevation values from the input.
 *
 * Two routes lead in, and they mean different things:
 *
 *   elevation_ellipsoid_m   Raw measurement of a satellite receiver, relative
 *                       to the WGS84 ellipsoid. Converted here via the
 *                       EGM96 grid to NHN — in Germany around 46 meters
 *                       of difference.
 *   elevation_m             Already a sea-level elevation, say from an import.
 *                       Taken over unchanged.
 *
 * If both come, the raw measurement wins: it is the measurement, the
 * other would be a second opinion about it.
 *
 * If the conversion fails, elevation_m stays empty. Writing the raw number in
 * anyway would be the worst outcome — you could not tell from the number
 * that it is 46 meters off.
 */
function point_elevations(array $data, float $lat, float $lon): array
{
    $raw = point_integer($data['elevation_ellipsoid_m'] ?? null, -32768, 32767);
    $nhn = point_integer($data['elevation_m'] ?? null, -32768, 32767);

    if ($raw !== null) {
        $converted = geoid_to_nhn((float) $raw, $lat, $lon);
        $nhn = $converted === null ? null : (int) round($converted);
    }

    return [
        'elevation_m'             => $nhn,
        'elevation_ellipsoid_m'   => $raw,
        'elevation_accuracy_m' => point_integer($data['elevation_accuracy_m'] ?? null, 0, 65535),
        // Where elevation_m stems from. When creating, always the receiver —
        // the terrain elevation is only looked up at the press of a button.
        'elevation_source'        => $nhn === null ? null : ($raw !== null ? 'gnss' : null),
    ];
}

/**
 * Create a point. Expects at least lat and lon.
 *
 * @throws InvalidArgumentException if the coordinates are no good
 */
function point_create(PDO $pdo, int $userId, array $data): int
{
    // A species from an Aufsammlung takes its place, day and finder (collections.php).
    $data = collection_before_create($pdo, $userId, $data);
    $coords = coordinates_read($data['lat'] ?? null, $data['lon'] ?? null);
    if ($coords === null) {
        throw new InvalidArgumentException('Ungültige Koordinaten.');
    }

    // Id assigned by the device: if the same one comes a second time, the
    // first attempt succeeded and only the response got lost. Then return
    // the existing point instead of creating a double.
    $clientId = point_client_key($data['client_id'] ?? null);
    if ($clientId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM map_points WHERE user_id = ? AND client_id = ?');
        $stmt->execute([$userId, $clientId]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }
    }

    $elevations = point_elevations($data, (float) $coords[0], (float) $coords[1]);
    $withReferences = column_exists($pdo, 'map_points', 'extra_species');
    $withMtb       = column_exists($pdo, 'map_points', 'mtb');
    // The column arrives with migration 0034, the code is there first.
    // Without this check, every creating would break in the window in
    // between — not only that of areas.
    $withOutline    = column_exists($pdo, 'map_points', 'outline');
    // The import id (migration 0045) is written only by the import itself
    // — it makes a whole run removable again later.
    $withImport    = ($data['import_id'] ?? null) !== null
        && column_exists($pdo, 'map_points', 'import_id');
    // The tour (migration 0071) — the code is live before the migration,
    // and a client that already sends the name must not break the insert.
    $withTour      = column_exists($pdo, 'map_points', 'tour');
    // The tour's identity (migration 0079) — minted on the device like
    // client_id, so it is complete from birth even without reception.
    $withTourId    = column_exists($pdo, 'map_points', 'tour_id');
    // The excursion reference (migration 0074) — written only by the
    // guest page; it is what the leader's rights and the later guest
    // attribution hang on (excursions.php).
    $withExcursion = ($data['excursion_id'] ?? null) !== null
        && column_exists($pdo, 'map_points', 'excursion_id');
    $sheet        = $withMtb ? mtb_from_coordinates((float) $coords[0], (float) $coords[1]) : null;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO map_points
                (user_id, client_id, lat, lon, accuracy_m, elevation_m, elevation_ellipsoid_m,
                 elevation_accuracy_m, elevation_source, title, note, extra, found_at'
            . ($withReferences ? ', extra_species' : '')
            . ($withMtb ? ', mtb, mtb_quadrant' : '')
            . ($withOutline ? ', outline' : '')
            . ($withImport ? ', import_id' : '')
            . ($withTour ? ', tour' : '')
            . ($withTourId ? ', tour_id' : '')
            . ($withExcursion ? ', excursion_id, excursion_guest_id' : '') . ',
                 created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?'
            . ($withReferences ? ', ?' : '')
            . ($withMtb ? ', ?, ?' : '')
            . ($withOutline ? ', ?' : '')
            . ($withImport ? ', ?' : '')
            . ($withTour ? ', ?' : '')
            . ($withTourId ? ', ?' : '')
            . ($withExcursion ? ', ?, ?' : '') . ', NOW(), NOW())'
        );
        $values = [
            $userId,
            $clientId,
            $coords[0],
            $coords[1],
            point_integer($data['accuracy_m'] ?? null, 0, 65535),
            $elevations['elevation_m'],
            $elevations['elevation_ellipsoid_m'],
            $elevations['elevation_accuracy_m'],
            $elevations['elevation_source'],
            mb_substr(trim((string) ($data['title'] ?? '')), 0, 200),
            point_note($data['note'] ?? null),
            point_extra_write($data['extra'] ?? null),
            // If the KEY does not come along — old browser in the cache,
            // third-party access via the API —, then server time will do:
            // imprecise, but better than no timeline at all. If it
            // expressly stands at null, by contrast, the find time stays
            // empty — the Takeout import REALLY does not know when a place
            // was saved, and prefers to invent nothing. The map always
            // sends its device clock along when creating; it merely never
            // hits the first case.
            array_key_exists('found_at', $data)
                ? point_find_datetime($data['found_at'])
                : date('Y-m-d H:i:s'),
        ];
        if ($withReferences) {
            $values[] = point_species_references($pdo, (array) ($data['extra'] ?? []), (array) ($data['extra_sources'] ?? []));
        }
        if ($withMtb) {
            $values[] = $sheet['sheet'] ?? null;
            $values[] = $sheet === null ? null : $sheet['quadrant'] . $sheet['q16'] . $sheet['q64'];
        }
        if ($withOutline) {
            $values[] = point_outline_write($data['outline'] ?? null);
        }
        if ($withImport) {
            $values[] = (int) $data['import_id'];
        }
        if ($withTour) {
            $tour = trim((string) ($data['tour'] ?? ''));
            $values[] = $tour === '' ? null : mb_substr($tour, 0, 120);
        }
        if ($withTourId) {
            $values[] = point_tour_id($data['tour_id'] ?? null);
        }
        if ($withExcursion) {
            $values[] = (int) $data['excursion_id'];
            $values[] = ($data['excursion_guest_id'] ?? null) === null
                ? null : (int) $data['excursion_guest_id'];
        }
        $stmt->execute($values);

        $pointId = (int) $pdo->lastInsertId();

        if (isset($data['lists'])) {
            point_lists_set($pdo, $userId, $pointId, (array) $data['lists']);
        }

        /* THE FIND NUMBER, once and never again (includes/find_number.php).
           Only points born into a species branch draw one — a waypoint
           must not use up a number of the collection — and only while a
           pattern is configured; without one the id is the number and
           nothing needs storing. Inside the transaction: a point must not
           exist without the number its creation was owed.

           AN IMPORT NEVER MINTS. It brings the past, and the running
           number belongs to the present: five hundred old finds would
           stamp the counter through in file order, under find years the
           pattern was never meant for. Whoever has numbers for old finds
           usually has them IN the source — the CSV import carries them
           over as the 'Fundnummer' column (find_number below), stored
           without touching the counter — in the series' spelling where it
           has the series' shape, as the point window stores a typed one
           („wk-1" → wk-00001; review of 26.09.2026: verbatim, it sat beside
           a stored „wk-00001" as a second number), anything else as it
           comes. */
        require_once __DIR__ . '/find_number.php';
        $delivered = trim((string) ($data['find_number'] ?? ''));
        // A „Y-…" is YASAIMAPP's own mark for a find without a number — an
        // exported one coming back names the OLD find; the new one wears
        // its own (find_number_fallback).
        if (find_number_reserved($delivered)) {
            $delivered = '';
        }
        if ($delivered !== '' && find_number_ready($pdo) && ($spell = find_number_pattern($pdo, $userId)) !== null) {
            $delivered = find_number_normalize($spell, $delivered);
        }
        if ($delivered !== '' && find_number_ready($pdo)) {
            $pdo->prepare('UPDATE map_points SET find_number = ? WHERE id = ?')
                ->execute([mb_substr($delivered, 0, 80), $pointId]);
        } elseif (!empty($data['find_number_draw']) && find_number_ready($pdo)
                  && ($drawPattern = find_number_pattern($pdo, $userId)) !== null) {
            /* THE ONE IMPORT THAT DRAWS: the user chose „nächste freie
               Nummer" for this find in the preview (a number the file
               brought is taken already, or the voucher of a find they
               determined lies with them). Drawn here, inside this
               transaction, so the counter's lock holds until the number
               is stored. */
            $pdo->prepare('UPDATE map_points SET find_number = ? WHERE id = ?')
                ->execute([mb_substr(find_number_draw($pdo, $userId, $drawPattern,
                    array_key_exists('found_at', $data) ? point_find_datetime($data['found_at']) : null), 0, 80), $pointId]);
        } elseif (($data['import_id'] ?? null) === null && empty($data['is_collection'])) {
            // An Aufsammlung is no find — it draws no number of the series
            // and gets its own for the box (collection_after_create).
            $findNumber = find_number_mint($pdo, $userId,
                (array) ($data['lists'] ?? []),
                array_key_exists('found_at', $data) ? point_find_datetime($data['found_at']) : date('Y-m-d H:i:s'));
            if ($findNumber !== null) {
                $pdo->prepare('UPDATE map_points SET find_number = ? WHERE id = ?')
                    ->execute([$findNumber, $pointId]);
            }
        }

        collection_after_create($pdo, $userId, $pointId, $data);

        $pdo->commit();

        // A find born with a tour name names the tracks of that tour — the
        // same rule as in point_update, where it is explained.
        if ($withTour && $withTourId) {
            $bornTour = trim((string) ($data['tour'] ?? ''));
            $bornId   = point_tour_id($data['tour_id'] ?? null);
            if ($bornTour !== '' && $bornId !== null) {
                require_once __DIR__ . '/tracks.php';
                track_tour_name_follow($pdo, $userId, $bornId, mb_substr($bornTour, 0, 120));
            }
        }

        /* NEITHER RUNS DURING AN IMPORT (25.08.2026, after a
           Meine-Pilze upload sat there without a word: „Wir haben zig
           Grundjobs, die alles im Hintergrund erledigen können. Du musst
           nicht die ganzen Daten ermitteln — nicht Gemeinde, Höhe und
           Schutzgebiet"). He named exactly the two calls below, and
           protected_area_record() three lines further down already
           carried this exact guard and its own long-form reasoning — an
           import brings hundreds of finds, and each of these two is one
           request to a foreign service, with Nominatim's municipality
           lookup capped at ONE PER SECOND. A hundred finds would have
           held the request open for well over a minute with nothing on
           screen but the browser's own spinner — the elevation module's
           own docblock (includes/elevation.php) even says outright that
           this "NEVER happens on its own", which the unconditional call
           here quietly contradicted for every import all along.

           Nothing is lost by skipping: both are picked up afterward by
           their own tab in the administration (admin/elevation_backfill.php
           redirects there; municipalities_content.php the same way),
           exactly like protected_area_record()'s catch-up already works —
           three tools with one shared design, not three different ones. */
        if (($data['import_id'] ?? null) === null) {
            // Take the terrain elevation along right away: +/-0.3 m
            // instead of the +/-10 m a satellite receiver manages
            // vertically. After the commit, so a slow or failed service
            // never holds up the creating — the point is there, the
            // elevation joins it. If it fails,
            // admin/elevation_backfill.php adds it later.
            elevation_record($pdo, $userId, $pointId, true);

            // The municipality likewise — it is the filing scheme of the
            // record photos and should stand there when the images get
            // sorted in the evening.
            //
            // ON THE REQUEST RATE: Nominatim allows one request per
            // second. The queue sends ONE point per request and awaits
            // each; with the elevation query before it, a creating takes
            // a good second anyway. A batch of twenty points handed in
            // later thus paces itself — a dedicated rate limiter would be
            // machinery for a case that does not exist outside imports,
            // and imports are exactly what skips this block now.
            //
            // The columns arrive with migration 0037, the code is there
            // first.
            if (column_exists($pdo, 'map_points', 'municipality')) {
                municipality_record($pdo, $userId, $pointId, true);
            }
        }

        // The title's reference first, the derived table after it — the
        // table reads source and identifier out of exactly what this
        // writes (point_species.php, title_references_sync).
        title_references_refresh($pdo, $userId, $pointId);
        point_species_refresh($pdo, $userId, $pointId);

        // The protected area, but only for finds —
        // protected_area_record() asks itself whether the point hangs in
        // a species-list branch, and stays silent otherwise. After the
        // commit and silently like everything derived here: the BfN must
        // never hold up the creating.
        //
        // EXCEPT ON IMPORT, and that is no fine-tuning: "Meine Pilze"
        // creates its root as a SPECIES LIST (import_create with
        // rootIsSpecies, as the only source) and brings hundreds of finds
        // along. Per find, three requests would go to a foreign, public
        // service — minutes of waiting and a stampede nobody asked for.
        //
        // THE REMAINING SOURCES — Takeout, KML, GPX, GeoJSON, CSV — do NOT
        // mark their root as a species list; their points are no finds at
        // all and would never reach the first request anyway. For them the
        // switch is thus only cheap head start, not the rescue.
        //
        // Catching up happens via the admin area
        // (protected_areas_content.php), just as with elevation and
        // municipality.
        // Word for word the same as $withImport further up: both mean
        // "comes from an import", and two spellings of the same question
        // drift apart at the first rebuild.
        if (($data['import_id'] ?? null) === null) {
            require_once __DIR__ . '/protected_areas.php';
            protected_area_record($pdo, $userId, $pointId, true);
        }

        // And the auto lists: a new point can hit any filter. After the
        // commit like everything derived here — if it fails, an
        // assignment is missing that the next write access catches up on,
        // but the point itself is intact.
        autolists_refresh($pdo, $userId, $pointId);

        return $pointId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Change a point. Only the fields that were actually sent along get
 * touched — this way the map can update the coordinates alone when a
 * marker was moved, without overwriting the rest.
 */
function point_update(PDO $pdo, int $userId, int $pointId, array $data): bool
{
    // A culture find keeps the place, day and finder of its Aufsammlung (collections.php).
    $data = collection_before_update($pdo, $userId, $pointId, $data);

    // THE SCREENING FIRST, because it changes what gets processed: fields
    // that lose against a younger change from another device, and fields
    // that are not even different from what is stored, vanish from $data
    // here. A losing location thus also triggers no terrain elevation, no
    // map sheet and no municipality. See includes/field_times.php.
    $review = point_field_times_review($pdo, $userId, $pointId, $data);

    $fields = [];
    $values  = [];

    // Has the spot moved? That decides later whether the terrain elevation
    // must be fetched anew — the map sends lat/lon along with EVERY save,
    // even when only the title was changed. Mere presence is thus no good
    // as a marker.
    $moved = false;

    if (array_key_exists('lat', $data) || array_key_exists('lon', $data)) {
        $coords = coordinates_read($data['lat'] ?? null, $data['lon'] ?? null);
        if ($coords === null) {
            throw new InvalidArgumentException('Ungültige Koordinaten.');
        }

        $before = point_coordinates($pdo, $userId, $pointId);
        // A millionth of a degree is around eleven centimeters — exactly
        // the resolution the coordinate is stored at.
        $moved = $before !== null
            && (abs($before[0] - (float) $coords[0]) > 1e-6
             || abs($before[1] - (float) $coords[1]) > 1e-6);

        $fields[] = 'lat = ?';
        $values[]  = $coords[0];
        $fields[] = 'lon = ?';
        $values[]  = $coords[1];

        // THE MAP SHEET BELONGS TO THE COORDINATE, not to the point.
        // Whoever moves a marker can end up in a different sheet — not
        // pulling it along here would mean the datum silently goes stale
        // and is wrong precisely when reporting.
        //
        // Unlike the terrain elevation, this needs no service and no wire:
        // two divisions, done in the same call.
        if (column_exists($pdo, 'map_points', 'mtb')) {
            $sheet = mtb_from_coordinates((float) $coords[0], (float) $coords[1]);
            $fields[] = 'mtb = ?';
            $values[]  = $sheet['sheet'] ?? null;
            $fields[] = 'mtb_quadrant = ?';
            $values[]  = $sheet === null ? null : $sheet['quadrant'] . $sheet['q16'] . $sheet['q64'];
        }
    }

    $simple = [
        'title'         => static fn($w) => mb_substr(trim((string) $w), 0, 200),
        'note'         => static fn($w) => point_note($w),
        'extra'        => static fn($w) => point_extra_write($w),
        'found_at'         => static fn($w) => point_find_datetime($w),
        'accuracy_m'       => static fn($w) => point_integer($w, 0, 65535),
        'elevation_m'             => static fn($w) => point_integer($w, -32768, 32767),
        'elevation_accuracy_m' => static fn($w) => point_integer($w, 0, 65535),
        // 'protected_area' ONCE STOOD HERE and is gone on purpose: since the
        // server determines it itself (protected_areas.php), it belongs to
        // it — like municipality, mtb and the terrain elevation, which for the
        // same reason never stood in this list. A client sending it along
        // would overwrite a derivation that arises anew at the next move
        // anyway.
    ];

    /* THE FIND NUMBER BY HAND (25./26.09.2026, point 3 of the
       numbers): the point window's field on one's own finds — a number an
       import did not bring, a voucher label older than the app, a typo.
       Empty clears it, and the find wears its „Y-" mark again; the mark
       itself is refused, as for a pattern (find_number_settings_save).
       The counter needs no word: the next minted number goes on above
       whatever stands (find_number_mint). */
    require_once __DIR__ . '/find_number.php';
    if (array_key_exists('find_number', $data) && find_number_ready($pdo)) {
        if (find_number_reserved((string) ($data['find_number'] ?? ''))) {
            throw new InvalidArgumentException(
                t('Eine Fundnummer darf nicht mit „Y-" anfangen. So beginnen die Nummern, die YASAIMAPP selbst vergibt.'));
        }
        /* IN THE SERIES' OWN SPELLING (26.09.2026: „wk-01 darf
           zu wk-00001 werden"): a number of the pattern's shape takes the
           pattern's text and its padding, whatever device sent it — the
           window does the same while one types (normalizeFindNumber). */
        $ownPattern = find_number_pattern($pdo, $userId);
        $simple['find_number'] = static function ($w) use ($ownPattern): ?string {
            $w = trim((string) ($w ?? ''));
            if ($w !== '' && $ownPattern !== null) {
                $w = find_number_normalize($ownPattern, $w);
            }
            return $w === '' ? null : mb_substr($w, 0, 80);
        };
    }

    // The tour only once its column exists (migration 0071) — the code is
    // live first, and a PATCH carrying 'tour' must not break the update in
    // the window between. Null clears: leaving a tour is an ordinary edit.
    if (column_exists($pdo, 'map_points', 'tour')) {
        $simple['tour'] = static function ($w): ?string {
            $w = trim((string) ($w ?? ''));
            return $w === '' ? null : mb_substr($w, 0, 120);
        };
    }
    // And its identity beside it (migration 0079). The two travel
    // together — a point that changes its tour changes both, and a
    // rename changes only the name of everything carrying one id.
    if (column_exists($pdo, 'map_points', 'tour_id')) {
        $simple['tour_id'] = static fn($w): ?string => point_tour_id($w);
    }

    // The references hang on the field values, so only write along when
    // those come along. A PATCH changing only the title should not delete
    // them.
    if (array_key_exists('extra', $data) && column_exists($pdo, 'map_points', 'extra_species')) {
        // HAND OVER THE PREVIOUS STATE. The browser knows only the choices
        // from this session; without what came before, every later change
        // to the point would recompute the references from nothing — and
        // thereby delete them.
        $previous = $pdo->prepare('SELECT extra_species FROM map_points WHERE id = ? AND user_id = ?');
        $previous->execute([$pointId, $userId]);
        $oldJson = $previous->fetchColumn();

        $fields[] = 'extra_species = ?';
        $values[]  = point_species_references(
            $pdo,
            (array) $data['extra'],
            (array) ($data['extra_sources'] ?? []),
            $oldJson === false ? null : $oldJson
        );
    }

    // The outline only when it comes along. A PATCH that merely changes
    // the title must not turn an area into a point — and null here
    // expressly means "remove the outline", not "not sent along". Hence
    // array_key_exists and not isset.
    if (array_key_exists('outline', $data) && column_exists($pdo, 'map_points', 'outline')) {
        $fields[] = 'outline = ?';
        $values[]  = point_outline_write($data['outline']);
    }

    foreach ($simple as $field => $prepare) {
        if (array_key_exists($field, $data)) {
            $fields[] = "$field = ?";
            $values[]  = $prepare($data[$field]);
        }
    }

    // If a raw measurement comes, it gets converted — namely at the spot
    // where the point lies afterwards. Without coordinates sent along,
    // that is the stored one; over a few meters that amounts to
    // centimeters anyway, but the rule should be unambiguous.
    if (array_key_exists('elevation_ellipsoid_m', $data)) {
        $raw = point_integer($data['elevation_ellipsoid_m'], -32768, 32767);

        $at = isset($coords)
            ? [(float) $coords[0], (float) $coords[1]]
            : point_coordinates($pdo, $userId, $pointId);

        $nhn = null;
        if ($raw !== null && $at !== null) {
            $converted = geoid_to_nhn((float) $raw, $at[0], $at[1]);
            $nhn = $converted === null ? null : (int) round($converted);
        }

        $fields[] = 'elevation_ellipsoid_m = ?';
        $values[]  = $raw;
        $fields[] = 'elevation_m = ?';
        $values[]  = $nhn;
    }

    $pdo->beginTransaction();
    try {
        $changed = false;

        if ($fields !== []) {
            // The new field times travel with the same UPDATE as the
            // values they belong to — never apart, or they gape.
            if ($review['json'] !== null) {
                $fields[] = 'field_times = ?';
                $values[]  = $review['json'];
            }
            $fields[] = 'updated_at = NOW()';
            $values[]  = $pointId;
            $values[]  = $userId;

            // WITHOUT deleted_at IN THE WHERE, and on purpose. A change
            // handed in later from the phone can hit a point another
            // device has meanwhile deleted. Rejecting it would mean
            // throwing the input away; writing it into the trash means
            // restoring brings BOTH back — the point and the change. The
            // map tells the user what happened (map.js, "liegt
            // inzwischen im Papierkorb"). Whoever adds a deleted_at IS
            // NULL here turns a preserved input into a lost one.
            $stmt = $pdo->prepare('UPDATE map_points SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?');
            $stmt->execute($values);
            $changed = $stmt->rowCount() > 0;
        } elseif ($review['json'] !== null && !array_key_exists('lists', $data)) {
            // It can happen that ONLY the lists won or ONLY a time is to
            // advance (old browser wrote a field that stayed the same —
            // then not; but the lists below write themselves). What
            // remains is the rare case: times move, nothing else.
            $pdo->prepare('UPDATE map_points SET field_times = ? WHERE id = ? AND user_id = ?')
                ->execute([$review['json'], $pointId, $userId]);
        }

        // The losing versions — in the same envelope as the decision that
        // displaced them. If something breaks here, neither exists.
        overwritten_remember($pdo, $userId, $pointId, $review['losing']);

        if (array_key_exists('lists', $data)) {
            // First check whether the point belongs to the user at all.
            $stmt = $pdo->prepare('SELECT 1 FROM map_points WHERE id = ? AND user_id = ?');
            $stmt->execute([$pointId, $userId]);
            if (!$stmt->fetchColumn()) {
                $pdo->rollBack();
                return false;
            }

            point_lists_set($pdo, $userId, $pointId, (array) $data['lists']);
            // If the field times did not come along above (no other field
            // present), they travel along here — the list time belongs to
            // the list change.
            if ($fields === [] && $review['json'] !== null) {
                $pdo->prepare('UPDATE map_points SET updated_at = NOW(), field_times = ? WHERE id = ? AND user_id = ?')
                    ->execute([$review['json'], $pointId, $userId]);
            } else {
                $pdo->prepare('UPDATE map_points SET updated_at = NOW() WHERE id = ? AND user_id = ?')
                    ->execute([$pointId, $userId]);
            }
            $changed = true;
        }

        $pdo->commit();

        /* A NEW TOUR NAME REACHES THE TRACKS OF THAT TOUR (13.09.2026) —
           the walk recorded under „Tagestour #5" must say „Tagestour"
           once its finds do. The id is the point's stored one when none
           was sent along: a rename sends the name alone. tracks.php
           carries the rule; here only the call, after the commit, so a
           refused update names nothing. */
        if ($changed && array_key_exists('tour', $data) && array_key_exists('tour', $simple)
            && column_exists($pdo, 'map_points', 'tour_id')) {
            $tourId = array_key_exists('tour_id', $data)
                ? point_tour_id($data['tour_id'])
                : (function () use ($pdo, $userId, $pointId): ?string {
                    $q = $pdo->prepare('SELECT tour_id FROM map_points WHERE id = ? AND user_id = ?');
                    $q->execute([$pointId, $userId]);
                    $id = $q->fetchColumn();
                    return $id === false || $id === null ? null : (string) $id;
                })();
            require_once __DIR__ . '/tracks.php';
            track_tour_name_follow($pdo, $userId, $tourId, $simple['tour']($data['tour']));
        }

        // The title's reference before the derived table — the table reads
        // source and identifier out of exactly what this writes. It also
        // heals what species_references_preserve dropped above: the browser
        // never sends the title inside the extra block, so the '' entry
        // falls out of every recomputation and comes back here.
        /* ONLY WHERE THE REFERENCE CAN HAVE MOVED: the title, the lists
           (whether it is a species branch) or the extra block, whose
           recomputation above drops the '' entry this heals. A number or a
           note alone cannot move it — and the lookup is the dear part of a
           save: a title the name lists do not know (a typo, „Urlaub 2019")
           is searched in all four sources every time. Measured 26.09.2026:
           numbering 400 such finds at once took 44 s, 1.4 ms a find
           without this step. The map's window always sends the title, so
           an ordinary save still refreshes. */
        $referencesChanged = (array_key_exists('title', $data) || array_key_exists('lists', $data)
                              || array_key_exists('extra', $data))
            && title_references_refresh($pdo, $userId, $pointId);

        // Pull the derived species table along. After the commit and
        // silently, for the same reason as with the elevation: a second
        // view of existing data must never hold up the saving itself. If
        // it fails, admin/arten_nachtragen.php rebuilds it — nothing can
        // be lost in the process, because every row is derivable.
        point_species_refresh($pdo, $userId, $pointId);

        // The auto lists right along with it: title, find time or lists
        // can have changed — everything a filter hangs on. Only on a real
        // change; without one, no membership can change either. A freshly
        // arisen reference counts too: the endangerment a filter asks
        // about hangs on it.
        if ($changed || $referencesChanged) {
            autolists_refresh($pdo, $userId, $pointId);
        }

        // Moved means: the old elevation belongs to a different spot.
        // Leaving it standing would be worse than none at all — you could
        // not tell from it that it stems from elsewhere.
        //
        // After the commit and silently, for the same reason as when
        // creating: a slow service must not hold up the saving. If it
        // fails, admin/elevation_backfill.php adds it later — for that,
        // elevation_source is emptied beforehand so the point shows up there
        // at all.
        if ($moved) {
            $pdo->prepare('UPDATE map_points SET elevation_source = NULL WHERE id = ? AND user_id = ?')
                ->execute([$pointId, $userId]);
            elevation_record($pdo, $userId, $pointId, true);

            // The municipality belongs to the coordinate, just like the
            // sheet. Whoever drags a point across a municipal border would
            // otherwise permanently keep the old one's folder name — and
            // would sort the images into a place they never were. Emptied
            // beforehand so the point shows up for catching up, in case
            // the service is silent right now.
            if (column_exists($pdo, 'map_points', 'municipality')) {
                $pdo->prepare('UPDATE map_points SET municipality_source = NULL WHERE id = ? AND user_id = ?')
                    ->execute([$pointId, $userId]);
                municipality_record($pdo, $userId, $pointId, true);
            }

            // The protected area belongs to the coordinate likewise:
            // whoever drags a find out of the nature reserve must not keep
            // its name. First empty — NULL means "not asked" and is the
            // honest answer in case the BfN stays silent in a moment —,
            // then fetch anew. For points outside the species lists the
            // call does nothing (see protected_areas.php).
            if (column_exists($pdo, 'map_points', 'protected_area')) {
                $pdo->prepare('UPDATE map_points SET protected_area = NULL WHERE id = ? AND user_id = ?')
                    ->execute([$pointId, $userId]);
                require_once __DIR__ . '/protected_areas.php';
                protected_area_record($pdo, $userId, $pointId, true);
            }
        }

        /* THE PICTURES' NAMES are made of the species, the day and the
           find number (cloud_names.php) — one of them changed, and the
           find's pictures in the cloud are renamed. Renumbering all one's
           finds comes through here too, one find at a time. The place a
           move brings marks the find itself (municipality_record). */
        if ($changed && array_intersect_key($data, array_flip(['title', 'extra', 'found_at', 'find_number'])) !== []) {
            require_once __DIR__ . '/cloud_names.php';
            cloud_names_touch($pdo, $pointId);
        }

        // The culture's days and the identification day; an Aufsammlung
        // takes its species along (collections.php).
        if (collection_after_update($pdo, $userId, $pointId, $data)) {
            $changed = true;
        }

        return $changed;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Set a point's list assignment.
 *
 * Foreign lists are silently sorted out — nobody should be able to hang a
 * point into another account's list. Silently instead of with an error:
 * the interface does not even offer them; whatever arrives anyway is an
 * outdated or hand-crafted call, and the rest of the assignment should
 * not fail over it.
 */
function point_lists_set(PDO $pdo, int $userId, int $pointId, array $listIds): void
{
    $requested = array_values(array_unique(array_map('intval', $listIds)));
    $withFilter  = column_exists($pdo, 'map_lists', 'filter');
    // What the point stood in before — another list may mean another folder in the cloud (below).
    $before = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ? ORDER BY list_id');
    $before->execute([$pointId]);
    $before = array_map('intval', $before->fetchAll(PDO::FETCH_COLUMN));

    $own = [];
    if ($requested !== []) {
        $space = implode(',', array_fill(0, count($requested), '?'));
        $stmt  = $pdo->prepare(
            "SELECT l.id FROM map_lists l
              WHERE l.user_id = ? AND l.deleted_at IS NULL AND l.id IN ($space)"
            // AND AUTO LISTS LIKEWISE (migration 0052): their assignment
            // belongs to their filter (autolists_refresh), not to the
            // form. This here is the bolt behind the interface — there the
            // checkbox is merely locked, and locked stops no hand-crafted
            // call.
            . ($withFilter ? ' AND l.filter IS NULL' : '')
        );
        $stmt->execute(array_merge([$userId], $requested));
        $own = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        /* A JOINT LIST IS FILED INTO LIKE AN OWN ONE (22.09.2026, gemeinsame
           Listen, migration 0110): a list somebody else hung into a share
           with „gemeinsam bearbeiten" takes this user's points too, if he
           is a member of that share. Same auto-list condition. */
        require_once __DIR__ . '/container.php';
        $joint = array_values(array_intersect($requested, joint_list_ids($pdo, $userId)));
        if ($joint !== []) {
            $space = implode(',', array_fill(0, count($joint), '?'));
            $stmt  = $pdo->prepare(
                "SELECT l.id FROM map_lists l
                  WHERE l.deleted_at IS NULL AND l.id IN ($space)"
                . ($withFilter ? ' AND l.filter IS NULL' : '')
            );
            $stmt->execute($joint);
            $own = array_values(array_unique(array_merge($own, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)))));
        }
    }

    if ($withFilter) {
        // THE AUTO-LIST ROWS SURVIVE THE SETTING. The point window shows
        // them as locked checkmarks and sends them along sometimes, and
        // sometimes not — if everything were deleted here, every casual
        // save would strip off the filter membership,
        // autolists_refresh would write it back right after, and every
        // device would reload the point for a zero-sum game. LEFT JOIN, so
        // orphaned rows (list gone) keep being cleared along as with the
        // bare DELETE before.
        $pdo->prepare(
            'DELETE pl FROM map_point_lists pl
               LEFT JOIN map_lists l ON l.id = pl.list_id
              WHERE pl.point_id = ? AND (l.id IS NULL OR l.filter IS NULL)'
        )->execute([$pointId]);
    } else {
        $pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ?')->execute([$pointId]);
    }

    if ($own !== []) {
        $stmt = $pdo->prepare('INSERT INTO map_point_lists (point_id, list_id) VALUES (?, ?)');
        foreach ($own as $listId) {
            $stmt->execute([$pointId, $listId]);
        }
    }

    /* THE LISTS DECIDE THE FOLDER TOO since 18.11.2026: a point outside
       every species list lies under „Listen/<list>/" (cloud_list_parts), so
       moving it into a species list, out of one or into another list moves
       its pictures. Only when the assignment really changed. */
    $after = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ? ORDER BY list_id');
    $after->execute([$pointId]);
    if (array_map('intval', $after->fetchAll(PDO::FETCH_COLUMN)) !== $before) {
        require_once __DIR__ . '/cloud_names.php';
        cloud_names_touch($pdo, $pointId);
    }
}

/** Put into the trash. */
function point_delete(PDO $pdo, int $userId, int $pointId): bool
{
    /* THE DOOR BEFORE THE BUCKET (co-ownership, 11.09.2026): a find with
       present co-owners does not go to the trash when its holder deletes
       it — it goes to the most senior of them, and the holder steps out
       into his own trash. „Wenn er ihn löscht, wandert der Punkt nicht
       weg, sondern ist dann im Besitz eines Bearbeiters." Every trash
       path passes through here — the window, Organisieren, the API — so
       this is the one place the rule has to stand. */
    require_once __DIR__ . '/point_owners.php';
    /* AN AUFSAMMLUNG'S HOLDER who deletes it takes his own species from it
       along into his trash — they are his work, not the others'
       (collection_species_leave). Then the usual: handed over to the
       senior co-owner, or into the trash. */
    if (collections_ready($pdo)) {
        $stmt = $pdo->prepare('SELECT 1 FROM map_points WHERE id = ? AND user_id = ? AND is_collection = 1 AND deleted_at IS NULL');
        $stmt->execute([$pointId, $userId]);
        if ($stmt->fetchColumn() !== false) {
            collection_species_leave($pdo, $pointId, $userId);
        }
    }
    $senior = point_owner_senior($pdo, $pointId);
    if ($senior !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM map_points WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
        $stmt->execute([$pointId, $userId]);
        if ($stmt->fetchColumn() !== false) {
            return point_hand_over($pdo, $pointId, $userId, $senior);
        }
    }
    $stmt = $pdo->prepare(
        'UPDATE map_points SET deleted_at = NOW(), updated_at = NOW()
          WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$pointId, $userId]);
    return $stmt->rowCount() > 0;
}

/** Bring back from the trash. */
function point_restore(PDO $pdo, int $userId, int $pointId): bool
{
    $stmt = $pdo->prepare(
        'UPDATE map_points SET deleted_at = NULL, updated_at = NOW()
          WHERE id = ? AND user_id = ? AND deleted_at IS NOT NULL'
    );
    $stmt->execute([$pointId, $userId]);
    if ($stmt->rowCount() === 0) {
        return false;
    }
    // In the trash the world can have changed — a filter that now grips
    // differently (auto list renamed admittedly not, but source deleted or
    // a time range the point now falls into): pull along once, then the
    // assignment agrees with the filter again.
    autolists_refresh($pdo, $userId, $pointId);
    return true;
}

/** Remove for good. Only from within the trash. */
function point_delete_permanently(PDO $pdo, int $userId, int $pointId): bool
{
    // FIRST ASK WHETHER IT HAS EVEN COME TO THAT. The condition stands
    // below in the WHERE clause — whoever rashly clears away the files
    // here and then finds the point is not in the trash at all has robbed
    // a living point of its images.
    $stmt = $pdo->prepare(
        'SELECT id FROM map_points WHERE id = ? AND user_id = ? AND deleted_at IS NOT NULL'
    );
    $stmt->execute([$pointId, $userId]);
    if ($stmt->fetchColumn() === false) {
        return false;
    }
    /* A LIST TRASH HOLDS THE POINT (joint lists, 22.09.2026): a filing of
       it lies where every member of that share may bring it back, and
       „Endgültig" of the holder would take that from them. Nothing here
       is ever purged by one hand; the trash page says why. */
    require_once __DIR__ . '/joint_lists.php';
    if (point_in_joint_trash($pdo, $pointId)) {
        return false;
    }

    // And now before, not after: the foreign key clears the photo rows
    // away with the point, and afterwards nobody knows anymore which
    // files belonged to it. Those would then lie on the disk forever and
    // against the quota — invisible, because no row points at them
    // anymore.
    photos_of_point_remove($pdo, $userId, $pointId);
    /* THE OTHERS' PICTURES TOO, and their copies everywhere (11.09.2026).
       A find can carry pictures of former co-owners — they left, their
       pictures stayed. The row cascade below would take their DATABASE
       rows and leave their FILES behind, unnamed on disk; and every
       copy of every picture of this find would go on lying in clouds
       that no longer know why. So: each photographer's files, each
       picture's copies, before the point row goes. */
    require_once __DIR__ . '/cloud_copies_content.php';
    $q = $pdo->prepare('SELECT DISTINCT user_id FROM map_photos WHERE point_id = ? AND user_id <> ?');
    $q->execute([$pointId, $userId]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $other) {
        photos_of_point_remove($pdo, (int) $other, $pointId);
    }
    foreach (['cloud' => 'map_cloud_files', 'photo' => 'map_photos'] as $kind => $table) {
        $q = $pdo->prepare("SELECT id FROM $table WHERE point_id = ?");
        $q->execute([$pointId]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $originId) {
            cloud_copies_retire($pdo, $kind, (int) $originId);
        }
    }

    $stmt = $pdo->prepare('DELETE FROM map_points WHERE id = ? AND user_id = ? AND deleted_at IS NOT NULL');
    $stmt->execute([$pointId, $userId]);
    return $stmt->rowCount() > 0;
}

// ---------------------------------------------------------------------------
// Preparing values
// ---------------------------------------------------------------------------

function point_integer(mixed $value, int $min, int $max): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    return max($min, min($max, (int) round((float) $value)));
}

function point_date(mixed $value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    $time = strtotime($value);
    return $time === false ? null : date('Y-m-d', $time);
}

function point_note(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    $text = trim((string) $value);
    return $text === '' ? null : mb_substr($text, 0, 20000);
}

// ---------------------------------------------------------------------------
// Offline areas
//
// Only the boundary — the tiles live in the browser of each device.
// See migrations/0022_gebiete.sql for the reasoning behind the separation.
// ---------------------------------------------------------------------------

function areas_load(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, corners, measurements, measured_on, created_at
           FROM map_areas
          WHERE user_id = ?
          ORDER BY name'
    );
    $stmt->execute([$userId]);

    return array_map(static function (array $z): array {
        $corners = json_decode((string) $z['corners'], true);
        $measurements  = json_decode((string) ($z['measurements'] ?? ''), true);
        return [
            'id'          => (int) $z['id'],
            'name'        => $z['name'],
            'corners'       => is_array($corners) ? $corners : [],
            // What a tile weighs here hangs on the terrain and not on the
            // device — hence measured once and remembered for all.
            'measurements'   => is_array($measurements) ? $measurements : null,
            'measured_on' => $z['measured_on'],
            'created_at' => $z['created_at'],
        ];
    }, $stmt->fetchAll());
}

/**
 * Check the boundary.
 *
 * A polygon with fewer than three corners encloses nothing, and
 * coordinates outside the possible would yield nonsense in the tile
 * arithmetic — in the worst case millions of tiles.
 */
function area_corners_read(mixed $raw): array
{
    if (!is_array($raw) || count($raw) < 3 || count($raw) > 200) {
        throw new InvalidArgumentException('Ein Gebiet braucht drei bis 200 Ecken.');
    }

    $corners = [];
    foreach ($raw as $e) {
        $coords = coordinates_read($e[0] ?? null, $e[1] ?? null);
        if ($coords === null) {
            throw new InvalidArgumentException('Ungültige Koordinate in der Umrandung.');
        }
        $corners[] = [(float) $coords[0], (float) $coords[1]];
    }
    return $corners;
}

function area_create(PDO $pdo, int $userId, array $data): int
{
    $clientId = point_client_key($data['client_id'] ?? null);
    if ($clientId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM map_areas WHERE user_id = ? AND client_id = ?');
        $stmt->execute([$userId, $clientId]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }
    }

    // Only name and boundary. Zoom level and deadline are decided by the
    // device that loads later; they therefore stand in its browser, not
    // here.
    $stmt = $pdo->prepare(
        'INSERT INTO map_areas (user_id, client_id, name, corners, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([
        $userId,
        $clientId,
        mb_substr(trim((string) ($data['name'] ?? '')), 0, 60) ?: 'Ohne Namen',
        json_encode(area_corners_read($data['corners'] ?? null)),
    ]);

    return (int) $pdo->lastInsertId();
}

function area_update(PDO $pdo, int $userId, int $id, array $data): bool
{
    $fields = [];
    $values  = [];

    if (array_key_exists('name', $data)) {
        $fields[] = 'name = ?';
        $values[]  = mb_substr(trim((string) $data['name']), 0, 60) ?: 'Ohne Namen';
    }
    if (array_key_exists('corners', $data)) {
        $fields[] = 'corners = ?';
        $values[]  = json_encode(area_corners_read($data['corners']));
        // THE MEASUREMENTS HANG ON THE CORNERS: they say what the tiles of
        // EXACTLY THIS area weigh. With a new boundary they would be
        // information about an area that no longer exists in that form —
        // the next device should measure anew, not guess wrongly. Only do
        // not null them when fresh measurements come along in the same
        // call (below).
        if (!array_key_exists('measurements', $data)) {
            $fields[] = 'measurements = NULL';
            $fields[] = 'measured_on = NULL';
        }
    }
    // Measurements are stored by the device that determined them — for all others.
    if (array_key_exists('measurements', $data)) {
        $fields[] = 'measurements = ?';
        $values[]  = is_array($data['measurements']) ? json_encode($data['measurements']) : null;
        $fields[] = 'measured_on = CURDATE()';
    }

    if ($fields === []) {
        return false;
    }

    $fields[] = 'updated_at = NOW()';
    $values[]  = $id;
    $values[]  = $userId;

    $stmt = $pdo->prepare('UPDATE map_areas SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?');
    $stmt->execute($values);
    return $stmt->rowCount() > 0;
}

function area_delete(PDO $pdo, int $userId, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM map_areas WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * A boundary no longer expires.
 *
 * A cleanup by expiry date used to stand here. That was thought the wrong
 * way round: a patch of forest does not cease to exist. What expires are
 * the TILES on a particular device — and that device looks after those
 * itself, because only it knows when it fetched them and how long it
 * wanted to keep them. See areas.js.
 */
