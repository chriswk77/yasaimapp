<?php
/**
 * The Fundbrowser — every find one may see, as tiles where it carries a
 * picture (10.10.2026: „so ähnlich wie Adobe Lightroom … eine
 * Bilderansicht in Kacheln, der Funde natürlich, mit Label und Namen, wo
 * man ein bisschen browsen kann, nach Jahren, nach eigenen, nach fremden
 * Funden. Nur die Funde, die auch Bilder haben — zumindest vorerst").
 *
 * THE FINDS WITHOUT A PICTURE COME ALONG SINCE 11.10.2026, because the
 * Fundbrowser is becoming the cockpit one works on finds from (his words:
 * „Funde ohne Bild kommen momentan nicht vor … ich würde das ganz einfach
 * nur über die Suche regeln"). The browsing shows tiles of the finds with
 * pictures only; a SEARCH lists every find that matches, newest find first,
 * with day, place and tour so a find can be told apart by its text.
 * Which is why a find carries place and tour here — and why the stock is
 * no longer „the ones with pictures": a few hundred bytes more per find,
 * for the pictures' addresses are the weight.
 *
 * READ ONLY, AND THE ROADS ARE THE MAP'S. What counts as a foreign find
 * one may see is answered where the map answers it — the shares with
 * their per-recipient filters (container_points), the co-owned finds,
 * the finds a friend showed, the excursions one leads. A second, lighter
 * query beside them would be the copy that forgets the next filter, and
 * a point one may not see must never reach the page. Only one's OWN finds
 * are read with a query of their own: they are p.user_id = me and
 * nothing else, and points_load() would prepare taxonomy, Red List and
 * sequences for two thousand finds the tiles never show.
 *
 * BOTH KINDS OF PICTURE, from the start. A find's voucher photos
 * (map_photos, „Aufnehmen") are one or two; what makes it worked are the
 * cloud recordings (map_cloud_files, „Auswählen") — micrographs, stacks,
 * detail shots (30.09.2026). A find with only the latter is a
 * find with pictures, and its tile shows the first of them. The order is
 * the gallery's: one sort_order number space per find across both tables
 * since migration 0148, ties by id, the voucher first before that.
 */

declare(strict_types=1);

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/photos.php';
require_once __DIR__ . '/find_number.php';
require_once __DIR__ . '/cloud_names.php';

/**
 * Every find the user may see, newest first — with its pictures, none for
 * a find without.
 *
 * A SECOND READER: the identification module's tab „Funde" per species
 * (since 28.10.2026) takes id,
 * title, number, found_at, own, owner and pictures[0].thumb from here, and
 * jumps into a find by pages/finds.php#fund=<id>. Whoever renames one of
 * them tells whoever keeps that module.
 *
 * @return list<array{id:int, title:string, number:string, found_at:?string,
 *                    year:?int, lat:float, lon:float, place:string, tour:string,
 *                    own:bool, owner:?string, owner_id:int, pictures:list<array>}>
 */
/**
 * The points among $ids that stand in lists but in no SPECIES list — the
 * ones that are no finds. The rule of cloud_list_parts (cloud.php): a
 * species list wins, whatever else a point stands in; a point in no list at
 * all stays a find, as it stays in the cloud's species tree; deleted lists
 * and lists that fill themselves by a filter count for nothing. Empty where
 * the schema has no „Artenliste" yet.
 *
 * @param int[] $ids
 * @return int[]
 */
function finds_outside_species(PDO $pdo, array $ids): array
{
    if ($ids === [] || !column_exists($pdo, 'map_lists', 'is_species')) {
        return [];
    }
    $auto = column_exists($pdo, 'map_lists', 'filter') ? ' AND l.filter IS NULL' : '';
    $out = [];
    foreach (array_chunk(array_values($ids), 500) as $chunk) {
        $marks = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("SELECT pl.point_id FROM map_point_lists pl
                                 JOIN map_lists l ON l.id = pl.list_id
                                WHERE pl.point_id IN ($marks) AND l.deleted_at IS NULL$auto
                                GROUP BY pl.point_id
                               HAVING MAX(l.is_species) = 0");
        $stmt->execute(array_map('intval', $chunk));
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $out[] = (int) $id;
        }
    }
    return $out;
}

function finds_overview(PDO $pdo, int $userId): array
{
    $finds = [];   // point id => the tile's data, pictures still empty
    $photoLists = [];   // point id => the voucher photos the road delivered

    // ONE'S OWN — holder only; the co-owned ones arrive below as foreign,
    // the way the map files them. Municipality and tour only where the
    // column is there (the code is live before the migration).
    $stmt = $pdo->prepare(
        'SELECT id, title, find_number, found_at, created_at, lat, lon'
        . (column_exists($pdo, 'map_points', 'municipality') ? ', municipality, region, country' : '')
        . (column_exists($pdo, 'map_points', 'tour') ? ', tour' : '')
        // Aufsammlungen (0174): the sample and the species grown from one.
        . (column_exists($pdo, 'map_points', 'is_collection')
            ? ', is_collection, collection_id, collection_number, cultured_at, culture_ended_at, identified_at' : '') . '
           FROM map_points
          WHERE user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$userId]);
    $own = $stmt->fetchAll();
    foreach ($own as $p) {
        $finds[(int) $p['id']] = finds_tile($p, true, null, $userId);
    }
    if ($own !== []) {
        $photoLists = photos_for_points($pdo, $userId, array_keys($finds));
    }

    // THE OTHERS' — through the map's own roads (see the head). The first
    // road to name a point wins; a find reachable twice is one tile.
    foreach (finds_foreign($pdo, $userId) as $p) {
        $id = (int) $p['id'];
        if (isset($finds[$id])) {
            continue;
        }
        $finds[$id] = finds_tile($p, (int) ($p['owner_id'] ?? 0) === $userId,
                                 $p['owner'] ?? null, (int) ($p['owner_id'] ?? 0));
        $photoLists[$id] = $p['photos'] ?? [];
    }
    /* ONLY FINDS (18.11.2026: „wir trennen jetzt etwas schärfer
       zwischen Funden und Nicht-Fundlisten. Und der Fund-Browser sollte
       deshalb auch nur Funde anzeigen aus Artenlisten und keine Bilder
       mehr aus Nicht-Artenlisten"). A burn site, an area, a spot in a list
       that holds no species is no find — its pictures lie under „Listen/"
       in the cloud since the same day, outside the species tree. The rule
       is the cloud's, word for word (cloud.php, cloud_list_parts), so the
       browser and the folders never disagree about what a find is. */
    foreach (finds_outside_species($pdo, array_keys($finds)) as $id) {
        unset($finds[$id], $photoLists[$id]);
    }
    if ($finds === []) {
        return [];
    }

    // The pictures of every find, both tables, in the gallery's order.
    $pictures = [];
    foreach ($photoLists as $pointId => $list) {
        foreach ($list as $ph) {
            $pictures[$pointId][] = [
                'kind'  => 'photo',
                'id'    => (int) $ph['id'],
                'title' => $ph['title'] ?? null,
                // Whose it is, where it is not one's own — „von Ben" under the picture.
                'by'    => (int) ($ph['photographer_id'] ?? $userId) === $userId ? null : ($ph['photographer'] ?? null),
                'order' => (int) ($ph['sort_order'] ?? 0),
                'thumb' => url('pages/photo.php') . '?id=' . (int) $ph['id'] . '&k=1',
                'large' => url('pages/photo.php') . '?id=' . (int) $ph['id'],
                'develop' => $ph['develop'] ?? null,
            ];
        }
    }
    foreach (finds_recordings($pdo, $userId, array_keys($finds)) as $pointId => $list) {
        foreach ($list as $r) {
            $pictures[$pointId][] = $r;
        }
    }

    $out = [];
    foreach ($finds as $id => $find) {
        $list = $pictures[$id] ?? [];
        // THE HAUPTMOTIV BEFORE ALL, because the tile shows the first
        // picture (12.10.2026: „als Vorschaubild für den Fund.
        // Momentan kommt Belegfoto vor") — the rule of the gallery, which
        // has it on top as well (cloud_names_gallery_order). Then the
        // dragged place, and the voucher before the recording on a tie —
        // the order the gallery had before 0148 gave both one number space.
        $rank = static fn(array $x): array => [
            mb_strtolower(trim((string) ($x['title'] ?? ''))) === CLOUD_NAMES_LEAD ? 0 : 1,
            $x['order'], $x['kind'] === 'photo' ? 0 : 1, $x['id'],
        ];
        usort($list, static fn(array $a, array $b): int => $rank($a) <=> $rank($b));
        foreach ($list as &$picture) {
            unset($picture['order']);
        }
        unset($picture);
        $find['pictures'] = $list;
        $out[] = $find;
    }

    // Newest find first — a find without a day by when it was set.
    usort($out, static fn(array $a, array $b): int =>
        [$b['sort_day'], $b['id']] <=> [$a['sort_day'], $a['id']]);
    foreach ($out as &$find) {
        unset($find['sort_day']);
    }
    unset($find);
    return $out;
}

/**
 * WHICH TILES STAND ON THE FIND BOOK — the green dot in the Fundbrowser's
 * tiles (finds.js, publishMark; 25.11.2026: „einen Punkt setzen bei den
 * Funden, die veröffentlicht wurden"). Each own find with a released plate
 * gets `publish`: 'shown' when the site shows it, 'waiting' while a
 * photographer's consent is missing — green and orange in the tile.
 *
 * ONLY FOR AN ACCOUNT WITH A FIND BOOK (publication_site_label): since
 * migration 0201 every old plate counts as released, so without the book
 * nearly every tile would carry a dot that means nothing. Asked here and
 * not in finds_overview(), which the species' finds read as well
 * (species_finds.php) and which knows the account only by its id.
 *
 * @param list<array> $finds finds_overview()'s tiles
 * @return list<array>
 */
function finds_publish_marks(PDO $pdo, array $me, array $finds): array
{
    require_once __DIR__ . '/plate_publish.php';
    require_once __DIR__ . '/publication.php';
    if ($finds === [] || publication_site_label($pdo, $me) === null) {
        return $finds;
    }
    $own = array_map(static fn(array $f): int => $f['id'], array_filter($finds, static fn(array $f): bool => $f['own']));
    $state = plate_publish_points($pdo, (int) $me['id'], $own);
    foreach ($finds as &$f) {
        if (isset($state[$f['id']])) {
            $f['publish'] = $state[$f['id']];
        }
    }
    unset($f);
    return $finds;
}

/** One tile's data out of a point row (own query or a road's prepared point). */
function finds_tile(array $p, bool $own, ?string $owner, int $ownerId): array
{
    $day = $p['found_at'] ?? null;
    $day = $day === null || $day === '' ? null : (string) $day;
    $number = trim((string) ($p['find_number'] ?? ''));
    return [
        'id'       => (int) $p['id'],
        'title'    => trim((string) ($p['title'] ?? '')),
        // Without a minted number the id is the number, with the mark the
        // point window and the cloud's folders write before it („Y-9584").
        'number'   => !empty($p['is_collection']) ? (string) ($p['collection_number'] ?? '')
            : ($number !== '' ? $number : find_number_fallback((int) $p['id'])),
        'found_at' => $day,
        'year'     => $day === null ? null : (int) substr($day, 0, 4),
        'lat'      => (float) $p['lat'],
        'lon'      => (float) $p['lon'],
        // WHERE, in the words one tells a find apart by: the municipality,
        // failing that the region or the country — and the tour it was
        // found on. Empty, never null: the page joins and searches them.
        'place'    => trim((string) ($p['municipality'] ?? '')) !== '' ? trim((string) $p['municipality'])
            : (trim((string) ($p['region'] ?? '')) !== '' ? trim((string) $p['region']) : trim((string) ($p['country'] ?? ''))),
        'tour'     => trim((string) ($p['tour'] ?? '')),
        'own'      => $own,
        'owner'    => $own ? null : ($owner ?? ''),
        'owner_id' => $ownerId,
        // AUFSAMMLUNGEN (0174): a sample shows its own number instead of the
        // find mark, a species from one says „aus Kultur" (finds.js).
        'is_collection'    => (bool) ($p['is_collection'] ?? false),
        'collection_id'    => isset($p['collection_id']) ? (int) $p['collection_id'] : null,
        'culture'          => !empty($p['is_collection'])
            ? (!empty($p['culture_ended_at']) ? 'ended' : (!empty($p['cultured_at']) ? 'running' : 'waiting'))
            : null,
        'identified_at'    => isset($p['identified_at']) ? substr((string) $p['identified_at'], 0, 10) : null,
        'pictures' => [],
        'sort_day' => $day ?? (string) ($p['created_at'] ?? ''),
    ];
}

/**
 * The foreign finds one may see — the same roads that fill the map's
 * foreign store (index.php: shared, excursionPoints, coOwnedPoints; and
 * the hints, map.js syncHints). Prepared points, each with owner and
 * owner_id and its voucher photos as the map gets them.
 *
 * @return list<array>
 */
function finds_foreign(PDO $pdo, int $userId): array
{
    $out = [];

    // Co-owned first: there one is in the circle and sees the whole set.
    require_once __DIR__ . '/point_owners.php';
    foreach (co_owned_points($pdo, $userId) as $p) {
        $out[] = $p;
    }

    require_once __DIR__ . '/container.php';
    if (container_ready($pdo)) {
        foreach (container_mine($pdo, $userId) as $c) {
            foreach (container_points($pdo, (int) $c['id'], [], $userId) as $p) {
                $out[] = $p;
            }
        }
    }

    require_once __DIR__ . '/point_hints.php';
    foreach (point_hints_points($pdo, $userId) as $p) {
        $out[] = $p;
    }

    require_once __DIR__ . '/excursions.php';
    foreach (excursion_points($pdo, $userId) as $p) {
        $out[] = $p;
    }

    return $out;
}

/**
 * The cloud recordings of these finds, grouped by find — ONE query for the
 * whole stock, where cloud_files_rows() asks per find. Every recording on
 * a find one may see is shown, whoever took it: the same rule as the
 * point window's list (cloud_files_rows, „for everyone who reached this
 * line"); the callers hand in only finds the user may see.
 *
 * @param int[] $pointIds
 * @return array<int, list<array>>
 */
function finds_recordings(PDO $pdo, int $userId, array $pointIds): array
{
    if ($pointIds === [] || !table_exists($pdo, 'map_cloud_files')) {
        return [];
    }
    // Live before the migrations, like cloud_files_rows: the title from
    // 0097, the gallery's order from 0148, the copy's photographer from 0161.
    $hasTitle   = column_exists($pdo, 'map_cloud_files', 'title');
    $hasOrder   = column_exists($pdo, 'map_cloud_files', 'sort_order');
    $hasTakenBy = column_exists($pdo, 'map_cloud_files', 'taken_by');
    // How each is edited (0190) — the tiles show the picture as „Bearbeiten" left it.
    $hasDevelop = column_exists($pdo, 'map_cloud_files', 'develop');
    $out = [];
    foreach (array_chunk(array_values($pointIds), 500) as $chunk) {
        $stmt = $pdo->prepare(
            'SELECT f.id, f.point_id, f.user_id, f.name'
            . ($hasTitle ? ', f.title' : '') . ($hasOrder ? ', f.sort_order' : '')
            . ($hasTakenBy ? ', f.taken_by' : '') . ($hasDevelop ? ', f.develop' : '')
            . ', ' . sql_display_name() . ' AS photographer
               FROM map_cloud_files f
               JOIN map_users u ON u.id = f.user_id
              WHERE f.point_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')'
        );
        $stmt->execute($chunk);
        foreach ($stmt as $r) {
            $id = (int) $r['id'];
            $out[(int) $r['point_id']][] = [
                'kind'  => 'cloud',
                'id'    => $id,
                'title' => ($r['title'] ?? null) ?: null,
                'name'  => (string) $r['name'],
                'by'    => ($r['taken_by'] ?? null)
                    ?: ((int) $r['user_id'] === $userId ? null : (string) $r['photographer']),
                'order' => (int) ($r['sort_order'] ?? 0),
                // „c=1": the previews with their colour profile (pages/cloud_thumb.php says why).
                'thumb' => url('pages/cloud_thumb.php') . '?id=' . $id . '&c=1',
                'large' => url('pages/cloud_thumb.php') . '?id=' . $id . '&c=1&large=1',
                'develop' => photo_develop_read($r['develop'] ?? null),
            ];
        }
    }
    return $out;
}

/**
 * ONE FIND FOR THE COCKPIT — everything the picture viewer and the plates
 * ask of a find, in one answer (11.10.2026): the find as the server
 * prepares it, the recordings, and what the asker may do to it.
 *
 * THE ROADS ARE THE SEEING GATE'S. point_view_scope() decides whether the
 * find may be looked at at all and whose set of pictures one gets — the
 * holder's, the same the map's payload carries; a stranger and a trashed
 * find read as „not there". The find itself is the HOLDER'S load, so a
 * foreign find arrives as complete as the shares, the circle and the
 * excursions already hand it out.
 *
 * RIGHTS RIDE ALONG, because the browser cannot work them out: what a
 * share grants hangs on lists this page does not load. `rights` is what
 * point_foreign_rights() says (the union over every share that reaches the
 * find), `co_owned` the circle's door, `editable` the excursion leader's —
 * the same three doors the map's mayEditPointPhotos() asks. The server
 * stays the judge of every write; these only decide what the window offers.
 *
 * @return ?array{point: array, files: list<array>, own: bool, rights: string[], may_make: bool}
 */
function find_cockpit(PDO $pdo, int $userId, int $pointId): ?array
{
    require_once __DIR__ . '/point_owners.php';
    require_once __DIR__ . '/container.php';
    require_once __DIR__ . '/excursions.php';
    require_once __DIR__ . '/cloud.php';

    $holder = point_view_scope($pdo, $userId, $pointId);
    if ($holder === null) {
        return null;
    }
    $point = point_load($pdo, $holder, $pointId);
    if ($point === null) {
        return null;
    }
    unset($point['field_times']);

    $own     = $holder === $userId;
    $coOwned = !$own && point_owner_is($pdo, $pointId, $userId);
    $leader  = !$own && excursion_point_leader_scope($pdo, $userId, $pointId) !== null;
    $foreign = $own || $coOwned ? null : point_foreign_rights($pdo, $userId, $pointId);

    if (!$own) {
        $q = $pdo->prepare('SELECT ' . sql_display_name() . ' FROM map_users u WHERE u.id = ?');
        $q->execute([$holder]);
        $point['owner']    = (string) $q->fetchColumn();
        $point['owner_id'] = $holder;
        $point['co_owned'] = $coOwned;
        // The excursion find one leads: every right, the server judging each write.
        $point['editable'] = $leader;
    }

    // The recordings the way the point window lists them (cloud.php, 'files'):
    // the preview comes through this server, the provider's links being bound to its address.
    $files = cloud_files_for_point($pdo, $userId, $pointId);
    foreach ($files as &$file) {
        $file['preview'] = url('pages/cloud_thumb.php') . '?id=' . (int) $file['id'] . '&c=1';
    }
    unset($file);

    /* THE CONFIRMED KEY WAYS, for the plates (point_keyings_plate): they
       draw from this object without asking again, in print as well. */
    require_once __DIR__ . '/point_keyings.php';
    $point['keyings'] = point_keyings_plate($pdo, $userId, $pointId);

    return [
        'point'    => $point,
        'files'    => $files,
        'own'      => $own,
        'rights'   => $foreign['rights'] ?? [],
        // The holder and the co-owners — the circle that works on a find (plates.php).
        'may_make' => $own || $coOwned,
    ];
}

/**
 * THE FIND'S FACE FOR THE MAP'S NAME TAG (12.10.2026: at the
 * desk a click on a point shows „nur noch den Namen und vielleicht eine
 * kleine Bildvorschau"). The first picture in the gallery's order — the
 * Hauptmotiv before all, then the dragged place, the voucher before the
 * recording on a tie, as finds_overview() sorts a tile's pictures —, as the
 * address of its small preview; null for a find without a picture or one
 * the caller may not see. Through find_cockpit(), so the same rules decide
 * what one sees: one find per click, not the whole stock.
 */
function find_cover(PDO $pdo, int $userId, int $pointId): ?string
{
    $find = find_cockpit($pdo, $userId, $pointId);
    if ($find === null) {
        return null;
    }
    $pictures = [];
    foreach ($find['point']['photos'] ?? [] as $ph) {
        $pictures[] = ['kind' => 0, 'id' => (int) $ph['id'], 'title' => $ph['title'] ?? null,
                       'order' => (int) ($ph['sort_order'] ?? 0),
                       'thumb' => url('pages/photo.php') . '?id=' . (int) $ph['id'] . '&k=1'];
    }
    foreach ($find['files'] as $f) {
        $pictures[] = ['kind' => 1, 'id' => (int) $f['id'], 'title' => $f['title'] ?? null,
                       'order' => (int) ($f['sort_order'] ?? 65536), 'thumb' => $f['preview']];
    }
    if ($pictures === []) {
        return null;
    }
    $rank = static fn(array $x): array => [
        mb_strtolower(trim((string) ($x['title'] ?? ''))) === CLOUD_NAMES_LEAD ? 0 : 1,
        $x['order'], $x['kind'], $x['id'],
    ];
    usort($pictures, static fn(array $a, array $b): int => $rank($a) <=> $rank($b));
    return $pictures[0]['thumb'];
}
