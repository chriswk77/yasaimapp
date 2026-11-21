<?php
/**
 * Aufsammlungen (migration 0174, 15.10.2026): what is collected
 * in the field — dung, bark, wood — and cultured at home, and the species
 * that grow from it.
 *
 * AN AUFSAMMLUNG IS A POINT OF ITS OWN KIND (is_collection). It has what a
 * point has — place, collecting day (found_at), finder and substrate in
 * its fields, pictures of the dung, lists, sharing, the backfills of
 * municipality, elevation and map sheet — plus the culture's two days
 * (cultured_at, culture_ended_at) and its own number for the box
 * (collection_number, CW-A-YYYY-NN). It is NOT a find: it carries no species
 * and draws no find number.
 *
 * WHAT GROWS FROM IT IS AN ORDINARY FIND with collection_id set. Its own
 * species, find number, pictures, microscopy, voucher; the same species may
 * come several times, one find per fruiting — „die Fotodaten passen dann
 * zum Fund … zum Mikroskopierzeitpunkt". It gets identified_at, the day it
 * was determined.
 *
 * PLACE, DAY AND FINDER COME FROM THE AUFSAMMLUNG, ALWAYS: the find date of
 * a culture find is the collecting day (researched 15.10.2026 — iNaturalist
 * forum, herbarium practice, Darwin Core's eventDate against
 * dateIdentified), the finder is the collector. A species created from an
 * Aufsammlung takes them over, a species saved later cannot change them,
 * and an Aufsammlung that moves or is redated takes its species along
 * (collection_sync).
 */

declare(strict_types=1);

function collections_ready(PDO $pdo): bool
{
    static $known = null;
    return $known ??= column_exists($pdo, 'map_points', 'is_collection');
}

/** What a culture find takes from its Aufsammlung — the columns of place and day. */
const COLLECTION_INHERITED = [
    'lat', 'lon', 'accuracy_m', 'found_at',
    'elevation_m', 'elevation_ellipsoid_m', 'elevation_accuracy_m', 'elevation_source',
    'mtb', 'mtb_quadrant', 'municipality', 'region', 'country', 'municipality_source',
    'protected_area', 'tour', 'tour_id',
];

/** The finder's field — the collector is the finder of everything that grows. */
const COLLECTION_FINDER_FIELD = 'Finder';
/** The substrate's field — what a species from the Aufsammlung starts with. */
const COLLECTION_SUBSTRATE_FIELD = 'Substrat / Wirt';
/**
 * STRICT COPIES OF THE AUFSAMMLUNG (18.10.2026: the finder
 * corrected on the Aufsammlung did not reach its species — „eigentlich
 * sollte er bei den Funden sogar gar nicht editierbar sein, sondern strikt
 * übernommen werden … ebenso Begleitvegetation"). A species carries them as
 * the Aufsammlung has them, now and after every change of it
 * (collection_sync); a species' own word for them is never kept
 * (collection_before_update), and the window shows them as plain text
 * (pointwindow.js, the same two names). The Finder field stood here alone
 * since step 1 — and only took over a finder that was not empty.
 */
const COLLECTION_COPIED_FIELDS = [COLLECTION_FINDER_FIELD, 'Begleitvegetation'];
/**
 * WHAT A SPECIES STARTS WITH and may change: the substrate. Asked to make it
 * a strict copy too, the maintainer took it back the same minute: „Substrat
 * könnte natürlich sein, wenn man einen Pilz auf einem Pilz findet, der auf
 * der Aufsammlung gewachsen ist" — the host is the species' own word.
 */
const COLLECTION_START_FIELDS = [COLLECTION_SUBSTRATE_FIELD];

/**
 * An Aufsammlung this user may hang a species into, alive — or an exception.
 *
 * ONE'S OWN, AND ONE ONE WORKS IN (15.10.2026: „Ich teile die
 * Losung und jemand anders bestimmt"). An Aufsammlung in a list shared
 * with the tick „eigene Punkte setzen" (joint_list_ids) takes the other's
 * species too — the same right that lets him file his points into that
 * list. The species stays HIS find (shared is sight and work, never
 * ownership); place, day and finder are still the Aufsammlung's.
 */
function collection_parent(PDO $pdo, int $userId, mixed $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ? AND is_collection = 1 AND deleted_at IS NULL');
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();
    // A co-owner (who hung a species in before) may go on doing so, even
    // when the share has ended since.
    require_once __DIR__ . '/point_owners.php';
    if ($row !== false && (int) $row['user_id'] !== $userId && !point_owner_is($pdo, (int) $row['id'], $userId)) {
        require_once __DIR__ . '/container.php';
        $joint = joint_list_ids($pdo, $userId);
        $in = $joint === [] ? false : $pdo->query('SELECT 1 FROM map_point_lists WHERE point_id = ' . (int) $row['id']
            . ' AND list_id IN (' . implode(',', array_map('intval', $joint)) . ') LIMIT 1')->fetchColumn();
        if ($in === false) {
            $row = false;
        }
    }
    if ($row === false) {
        throw new InvalidArgumentException(t('Diese Aufsammlung gibt es nicht.'));
    }
    return $row;
}

/** A day as YYYY-MM-DD, or null — anything else is no day. */
function collection_day(mixed $value): ?string
{
    $value = substr(trim((string) ($value ?? '')), 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
        ? $value : null;
}

/**
 * The next number for the box: CW-A-YYYY-NN, counted per user and
 * collecting year. Two digits at least; the hundredth Aufsammlung of a
 * year simply gets three.
 *
 * THE PERSON'S INITIALS IN FRONT (18.10.2026). Counted per
 * person, every collector has his own run without gaps — and so Anna's
 * „A-2026-01" and Ben's stood side by side as the same number wherever
 * one sees the other's Aufsammlung (a co-owner, a shared list, an export).
 * A single run for everybody would have punched holes into each person's
 * own count. The two letters — first name, last name — come from the
 * account, where every signed-in person has a name; asked whether his find
 * number's prefix (letters of his surname) should serve, he chose these:
 * „da dies automatisch zu ermitteln ist". Two people with the same
 * initials still share numbers; that is rare, and the name stands beside
 * a foreign Aufsammlung anyway. Without a name there is no prefix.
 *
 * The count reads every number of the year whatever stands in front of
 * it, so a changed name continues the run instead of starting it anew.
 */
function collection_number_mint(PDO $pdo, int $userId, ?string $foundAt): string
{
    $year = substr((string) ($foundAt ?: date('Y-m-d')), 0, 4);
    /* NOT ONLY THE LIVING AUFSAMMLUNGEN: one turned into a find keeps its
       number in collection_number (collection_to_find), and counting it
       keeps that number burnt — „die Aufsammlungsnummer ist natürlich
       verbrannt, die würde ich nicht nochmal verwenden"
       (14.11.2026). Had the count asked for is_collection = 1, the
       converted last box of a year would have handed its number to the
       next. */
    $stmt = $pdo->prepare("SELECT collection_number FROM map_points
                            WHERE user_id = ? AND collection_number LIKE ?");
    $stmt->execute([$userId, '%A-' . $year . '-%']);
    $highest = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $number) {
        if (preg_match('/A-\d{4}-(\d+)$/', (string) $number, $m)) {
            $highest = max($highest, (int) $m[1]);
        }
    }
    $initials = collection_initials($pdo, $userId);
    return sprintf('%sA-%s-%02d', $initials === '' ? '' : $initials . '-', $year, $highest + 1);
}

/** First letter of the first name and of the last name, as capitals — '' where either is missing. */
function collection_initials(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare('SELECT first_name, last_name FROM map_users WHERE id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch() ?: [];
    $first = mb_strtoupper(mb_substr(trim((string) ($u['first_name'] ?? '')), 0, 1));
    $last  = mb_strtoupper(mb_substr(trim((string) ($u['last_name'] ?? '')), 0, 1));
    return $first !== '' && $last !== '' ? $first . $last : '';
}

/**
 * Before a point is created: a species from an Aufsammlung takes place,
 * day, tour and finder from it; an Aufsammlung is no species of its own.
 */
function collection_before_create(PDO $pdo, int $userId, array $data): array
{
    if (!collections_ready($pdo)) {
        return $data;
    }
    if (!empty($data['is_collection'])) {
        unset($data['collection_id'], $data['identified_at']);
        return $data;
    }
    if (empty($data['collection_id'])) {
        return $data;
    }
    $parent = collection_parent($pdo, $userId, $data['collection_id']);
    $data['lat'] = (float) $parent['lat'];
    $data['lon'] = (float) $parent['lon'];
    $data['accuracy_m'] = $parent['accuracy_m'];
    $data['found_at'] = $parent['found_at'];
    $data['tour'] = $parent['tour'] ?? null;
    $data['tour_id'] = $parent['tour_id'] ?? null;
    $parentExtra = json_decode((string) ($parent['extra'] ?? ''), true) ?: [];
    $refs = json_decode((string) ($parent['extra_species'] ?? ''), true) ?: [];
    // The strict copies: as the Aufsammlung has them, whatever was sent.
    $data = collection_copy_into($data, $parentExtra, $refs);
    // The substrate it grew on is the Aufsammlung's — a start, not a lock:
    // the species may name its host more exactly.
    foreach (COLLECTION_START_FIELDS as $field) {
        $value = $parentExtra[$field] ?? '';
        if ($value === '' || trim((string) (((array) ($data['extra'] ?? []))[$field] ?? '')) !== '') {
            continue;
        }
        $data['extra'] = array_merge((array) ($data['extra'] ?? []), [$field => $value]);
        // Its reference into the name list travels with it.
        if (is_array($refs[$field] ?? null) && empty($data['extra_sources'][$field])) {
            $data['extra_sources'] = (array) ($data['extra_sources'] ?? []);
            $data['extra_sources'][$field] = $refs[$field];
        }
    }
    return $data;
}

/** Right after the insert, inside its transaction: the columns of 0174. */
function collection_after_create(PDO $pdo, int $userId, int $pointId, array $data): void
{
    if (!collections_ready($pdo)) {
        return;
    }
    if (!empty($data['is_collection'])) {
        $row = $pdo->query('SELECT found_at, title FROM map_points WHERE id = ' . $pointId)->fetch();
        $number = collection_number_mint($pdo, $userId, $row['found_at'] ?: null);
        // Without a name of its own it is called by its number — „Ohne
        // Titel" would stand in every list.
        $title = trim((string) $row['title']) === '' ? t('Aufsammlung %s', $number) : $row['title'];
        $pdo->prepare('UPDATE map_points SET is_collection = 1, collection_number = ?, title = ?, cultured_at = ?, culture_ended_at = ? WHERE id = ?')
            ->execute([$number, $title,
                       collection_day($data['cultured_at'] ?? null), collection_day($data['culture_ended_at'] ?? null), $pointId]);
        return;
    }
    $sets = [];
    $values = [];
    if (!empty($data['collection_id'])) {
        $sets[] = 'collection_id = ?';
        $values[] = (int) $data['collection_id'];
        /* WHO HANGS A SPECIES INTO SOMEBODY ELSE'S AUFSAMMLUNG BECOMES ITS
           CO-OWNER (15.10.2026: „wie mit gemeinsamen Punkten …
           die Punkte gehen in den Besitz beider oder aller Bearbeiter
           über"). From then on the find-sharing rules hold: nobody deletes
           it for the others, each only steps out (collection_species_leave). */
        $holder = (int) $pdo->query('SELECT user_id FROM map_points WHERE id = ' . (int) $data['collection_id'])->fetchColumn();
        if ($holder > 0 && $holder !== $userId) {
            require_once __DIR__ . '/point_owners.php';
            $ownList = null;
            foreach ((array) ($data['lists'] ?? []) as $l) {
                $stmt = $pdo->prepare('SELECT id FROM map_lists WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
                $stmt->execute([(int) $l, $userId]);
                if (($found = $stmt->fetchColumn()) !== false) {
                    $ownList = (int) $found;
                    break;
                }
            }
            point_owner_add($pdo, (int) $data['collection_id'], $userId, $ownList);
        }
    }
    if (array_key_exists('identified_at', $data)) {
        $sets[] = 'identified_at = ?';
        $values[] = collection_day($data['identified_at']);
    }
    if ($sets !== []) {
        $values[] = $pointId;
        $pdo->prepare('UPDATE map_points SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
    }
}

/**
 * Before a point is saved: a culture find cannot be moved or redated — its
 * place and day are the Aufsammlung's — and its finder stays the collector.
 */
function collection_before_update(PDO $pdo, int $userId, int $pointId, array $data): array
{
    if (!collections_ready($pdo)) {
        return $data;
    }
    $stmt = $pdo->prepare('SELECT collection_id, is_collection, collection_number FROM map_points WHERE id = ? AND user_id = ?');
    $stmt->execute([$pointId, $userId]);
    $self = $stmt->fetch();
    // An Aufsammlung saved without a name is called by its number again.
    if ($self !== false && (int) $self['is_collection'] === 1 && array_key_exists('title', $data)
        && trim((string) $data['title']) === '' && $self['collection_number'] !== null) {
        $data['title'] = t('Aufsammlung %s', $self['collection_number']);
    }
    $parentId = $self === false ? false : $self['collection_id'];
    if ($parentId === false || $parentId === null) {
        return $data;
    }
    // The Aufsammlung may be somebody else's (collection_parent) — read by
    // id alone, its finder holds for every species.
    foreach (['lat', 'lon', 'accuracy_m', 'found_at', 'tour', 'tour_id', 'outline'] as $key) {
        unset($data[$key]);
    }
    // The strict copies stay the Aufsammlung's, whatever the window sent.
    if (isset($data['extra']) && is_array($data['extra'])) {
        $parent = $pdo->query('SELECT extra, extra_species FROM map_points WHERE id = ' . (int) $parentId)->fetch();
        $data = collection_copy_into($data,
            json_decode((string) ($parent['extra'] ?? ''), true) ?: [],
            json_decode((string) ($parent['extra_species'] ?? ''), true) ?: []);
    }
    return $data;
}

/** The strict copies (COLLECTION_COPIED_FIELDS) laid into a point's input — value and name-list reference, or emptied. */
function collection_copy_into(array $data, array $parentExtra, array $parentRefs): array
{
    $extra = (array) ($data['extra'] ?? []);
    foreach (COLLECTION_COPIED_FIELDS as $field) {
        $extra[$field] = (string) ($parentExtra[$field] ?? '');
        $data['extra_sources'] = (array) ($data['extra_sources'] ?? []);
        if (is_array($parentRefs[$field] ?? null)) {
            $data['extra_sources'][$field] = $parentRefs[$field];
        } else {
            unset($data['extra_sources'][$field]);
        }
    }
    $data['extra'] = $extra;
    return $data;
}

/**
 * After a point was saved: the culture days and the identification day,
 * then the species of an Aufsammlung follow it.
 *
 * @return bool whether something of 0174 changed
 */
function collection_after_update(PDO $pdo, int $userId, int $pointId, array $data): bool
{
    if (!collections_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT is_collection FROM map_points WHERE id = ? AND user_id = ?');
    $stmt->execute([$pointId, $userId]);
    $isCollection = $stmt->fetchColumn();
    if ($isCollection === false) {
        return false;
    }
    $sets = [];
    $values = [];
    $keys = (int) $isCollection === 1 ? ['cultured_at', 'culture_ended_at'] : ['identified_at'];
    foreach ($keys as $key) {
        if (array_key_exists($key, $data)) {
            $sets[] = "$key = ?";
            $values[] = collection_day($data[$key]);
        }
    }
    $changed = false;
    if ($sets !== []) {
        $values[] = $pointId;
        $stmt = $pdo->prepare('UPDATE map_points SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?');
        $stmt->execute($values);
        $changed = $stmt->rowCount() > 0;
    }
    if ((int) $isCollection === 1) {
        collection_sync($pdo, $pointId);
    }
    return $changed;
}

/**
 * The species of an Aufsammlung take its place, day, tour and finder — the
 * one road that keeps them in step, after every save of the Aufsammlung
 * and after its backfills (municipality, elevation). Only rows that really
 * differ are touched, so the devices fetch nothing for nothing.
 *
 * @return int how many species changed
 */
function collection_sync(PDO $pdo, int $collectionId): int
{
    if (!collections_ready($pdo)) {
        return 0;
    }
    $parent = $pdo->query('SELECT * FROM map_points WHERE id = ' . $collectionId . ' AND is_collection = 1')->fetch();
    if ($parent === false) {
        return 0;
    }
    $columns = array_values(array_filter(COLLECTION_INHERITED, static fn(string $c): bool => array_key_exists($c, $parent)));
    $parentExtra = json_decode((string) ($parent['extra'] ?? ''), true) ?: [];
    $parentRefs = json_decode((string) ($parent['extra_species'] ?? ''), true) ?: [];
    // Without touched_at the devices would not hear of the change (bucket.php).
    $touch = column_exists($pdo, 'map_points', 'touched_at') ? ', touched_at = CURRENT_TIMESTAMP(6)' : '';

    $children = $pdo->prepare('SELECT * FROM map_points WHERE collection_id = ? AND deleted_at IS NULL');
    $children->execute([$collectionId]);
    $count = 0;
    foreach ($children->fetchAll() as $child) {
        $sets = [];
        $values = [];
        foreach ($columns as $c) {
            if ((string) ($child[$c] ?? '') !== (string) ($parent[$c] ?? '')) {
                $sets[] = "$c = ?";
                $values[] = $parent[$c];
            }
        }
        /* THE STRICT COPIES follow every change — an emptied one empties
           too, and the name-list reference goes along with the value. */
        $extra = json_decode((string) ($child['extra'] ?? ''), true) ?: [];
        $refs = json_decode((string) ($child['extra_species'] ?? ''), true) ?: [];
        $extraChanged = $refsChanged = false;
        foreach (COLLECTION_COPIED_FIELDS as $field) {
            $want = (string) ($parentExtra[$field] ?? '');
            if ((string) ($extra[$field] ?? '') !== $want) {
                if ($want === '') {
                    unset($extra[$field]);
                } else {
                    $extra[$field] = $want;
                }
                $extraChanged = true;
            }
            $wantRefs = is_array($parentRefs[$field] ?? null) ? $parentRefs[$field] : null;
            if (($refs[$field] ?? null) !== $wantRefs) {
                if ($wantRefs === null) {
                    unset($refs[$field]);
                } else {
                    $refs[$field] = $wantRefs;
                }
                $refsChanged = true;
            }
        }
        if ($extraChanged) {
            $sets[] = 'extra = ?';
            $values[] = point_extra_write($extra);
        }
        if ($refsChanged && array_key_exists('extra_species', $child)) {
            $sets[] = 'extra_species = ?';
            $values[] = $refs === [] ? null : json_encode($refs, JSON_UNESCAPED_UNICODE);
        }
        if ($sets === []) {
            continue;
        }
        $values[] = (int) $child['id'];
        $pdo->prepare('UPDATE map_points SET ' . implode(', ', $sets) . ', updated_at = NOW()' . $touch . ' WHERE id = ?')->execute($values);
        $count++;
        // A new day renames the species' pictures in the cloud.
        if (in_array('found_at = ?', $sets, true)) {
            require_once __DIR__ . '/cloud_names.php';
            cloud_names_touch($pdo, (int) $child['id']);
        }
    }
    return $count;
}

/** The fields of 0174 for the browser — added to point_prepare's answer. */
function collection_prepare(array $p): array
{
    return [
        'is_collection'     => (bool) ($p['is_collection'] ?? false),
        'collection_id'     => isset($p['collection_id']) ? (int) $p['collection_id'] : null,
        'collection_number' => $p['collection_number'] ?? null,
        'cultured_at'       => $p['cultured_at'] ?? null,
        'culture_ended_at'  => $p['culture_ended_at'] ?? null,
        'identified_at'     => $p['identified_at'] ?? null,
    ];
}

/**
 * STEPPING OUT OF AN AUFSAMMLUNG TAKES ONE'S OWN SPECIES ALONG
 * (15.10.2026: „meine Arbeit, meine bestimmten Arten gehen natürlich nicht
 * als Kopie an die Gegenseite … nur das, was der andere als Art
 * eingetragen hat, behält er und den Aufsammlungspunkt. So ist das
 * gerecht."). Called where somebody leaves an Aufsammlung — the holder in
 * point_delete (which then hands it to the senior co-owner, or trashes it
 * when nobody else works in it), a co-owner in point_owner_leave. The
 * species go into his trash, through point_delete, so a species with its
 * own co-owners follows their rules too.
 *
 * @return int how many species went
 */
function collection_species_leave(PDO $pdo, int $collectionId, int $userId): int
{
    if (!collections_ready($pdo)) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT id FROM map_points WHERE collection_id = ? AND user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$collectionId, $userId]);
    $n = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        if (point_delete($pdo, $userId, (int) $id)) {
            $n++;
        }
    }
    return $n;
}

/**
 * AN AUFSAMMLUNG THAT WAS NONE BECOMES A FIND (14.11.2026: a
 * friend mapped a mushroom as an Aufsammlung by mistake — „können wir die
 * Aufsammlung irgendwo in einen Fund umwandeln?"). The point stays the
 * same row — place, day, fields, pictures, lists, co-owners, the chat —
 * and only stops being a sample:
 *
 * - THE BOX NUMBER IS BURNT, NOT FREED. It stays in collection_number as a
 *   record nobody reads (every display branches on is_collection), and the
 *   mint counts it (collection_number_mint), so the next Aufsammlung of the
 *   year does not get it again.
 * - The culture's days go; the default title „Aufsammlung CW-A-…" goes too,
 *   because the title of a find is its species — a name the user typed
 *   stays and now counts as one (point_species_set).
 * - It draws a find number as a find born into its lists would
 *   (find_number_mint) — none where the user has no pattern or the point
 *   lies in no species list, exactly like a new point.
 * - Its cloud folder is named after the find number from now on
 *   (cloud_names_touch; the 5-minute bundle renames it).
 *
 * ONLY THE HOLDER, AND ONLY WITHOUT SPECIES. A sample from which species
 * were determined WAS a sample; turning it into a find would orphan them
 * (their place and day are locked to it). Species in the trash are let
 * go instead — detached, so a restored one is an ordinary find and not
 * locked to a parent that is no Aufsammlung any more.
 *
 * @throws InvalidArgumentException with a message for the user
 */
function collection_to_find(PDO $pdo, int $userId, int $pointId): void
{
    if (!collections_ready($pdo)) {
        throw new InvalidArgumentException(t('Diese Aufsammlung gibt es nicht.'));
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ? AND user_id = ? AND is_collection = 1 AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute([$pointId, $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new InvalidArgumentException(t('Nur deine eigene Aufsammlung kannst du in einen Fund umwandeln.'));
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM map_points WHERE collection_id = ? AND deleted_at IS NULL');
        $stmt->execute([$pointId]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException(t('Aus dieser Aufsammlung sind schon Arten eingetragen. Sie bleibt deshalb eine Aufsammlung.'));
        }
        $pdo->prepare('UPDATE map_points SET collection_id = NULL WHERE collection_id = ? AND deleted_at IS NOT NULL')
            ->execute([$pointId]);

        $number = (string) ($row['collection_number'] ?? '');
        $title = (string) $row['title'];
        if ($number !== '' && trim($title) === t('Aufsammlung %s', $number)) {
            $title = '';
        }

        $findNumber = $row['find_number'] ?? null;
        if ((string) $findNumber === '') {
            require_once __DIR__ . '/find_number.php';
            $lists = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
            $lists->execute([$pointId]);
            $findNumber = find_number_mint($pdo, $userId, $lists->fetchAll(PDO::FETCH_COLUMN),
                $row['found_at'] ?: date('Y-m-d H:i:s'));
        }

        // Without touched_at the devices would not hear of it (bucket.php).
        $touch = column_exists($pdo, 'map_points', 'touched_at') ? ', touched_at = CURRENT_TIMESTAMP(6)' : '';
        $pdo->prepare('UPDATE map_points SET is_collection = 0, cultured_at = NULL, culture_ended_at = NULL,
                              title = ?, find_number = ?, updated_at = NOW()' . $touch . ' WHERE id = ?')
            ->execute([$title, $findNumber === '' ? null : $findNumber, $pointId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    require_once __DIR__ . '/point_species.php';
    point_species_set($pdo, $userId, $pointId);
    require_once __DIR__ . '/cloud_names.php';
    cloud_names_touch($pdo, $pointId);
}

/**
 * The species of an Aufsammlung, for its window — everyone's, with the
 * maker's name, newest determination last. Only for whoever may see the
 * Aufsammlung (point_view_scope: holder, co-owner, share, hint).
 *
 * @return array<array{id:int, title:string, find_number:?string, identified_at:?string, owner:string, own:bool}>|null
 */
function collection_species(PDO $pdo, int $userId, int $collectionId): ?array
{
    if (!collections_ready($pdo)) {
        return null;
    }
    require_once __DIR__ . '/point_owners.php';
    if (point_view_scope($pdo, $userId, $collectionId) === null) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.find_number, p.identified_at, p.user_id, ' . sql_display_name() . ' AS owner
           FROM map_points p JOIN map_users u ON u.id = p.user_id
          WHERE p.collection_id = ? AND p.deleted_at IS NULL
          ORDER BY p.identified_at IS NULL, p.identified_at, p.id'
    );
    $stmt->execute([$collectionId]);
    return array_map(static fn(array $r): array => [
        'id'            => (int) $r['id'],
        'title'         => (string) $r['title'],
        'find_number'   => $r['find_number'],
        'identified_at' => $r['identified_at'],
        'owner'         => (string) $r['owner'],
        'own'           => (int) $r['user_id'] === $userId,
    ], $stmt->fetchAll());
}

/**
 * The Aufsammlung numbers of these ids — for whoever writes a species out
 * and wants to name the box it grew in (export_points).
 *
 * @param int[] $ids
 * @return array<int, string> id => CW-A-YYYY-NN
 */
function collection_numbers(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if ($ids === [] || !collections_ready($pdo)) {
        return [];
    }
    $space = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, collection_number FROM map_points WHERE id IN ($space) AND is_collection = 1");
    $stmt->execute($ids);
    $numbers = [];
    foreach ($stmt as $row) {
        $numbers[(int) $row['id']] = (string) $row['collection_number'];
    }
    return $numbers;
}

/** 2026-09-14 → 14.09.2026, or '' for no day. */
function collection_date(mixed $value): string
{
    $day = collection_day($value);
    return $day === null ? '' : implode('.', array_reverse(explode('-', $day)));
}

/** Whole days from the collecting day to the determination, or null. */
function collection_days(array $p): ?int
{
    $from = collection_day($p['found_at'] ?? null);
    $to   = collection_day($p['identified_at'] ?? null);
    if ($from === null || $to === null) {
        return null;
    }
    return (int) round((strtotime($to . ' 00:00:00 UTC') - strtotime($from . ' 00:00:00 UTC')) / 86400);
}

/**
 * ONE SENTENCE FOR EVERY FILE THAT LEAVES (15.10.2026: „ein
 * Label, dass es ein Kulturwuchs ist"). A species from a culture says „ex
 * cultura" — the herbaria's word, read the same by a German coordinator
 * and a foreign one — with the day it was determined and how long after
 * the collecting; an Aufsammlung says its number and the culture's days.
 * The find date of both is the collecting day already (the head of this
 * file), so this sentence is what tells a reader that the fungus did NOT
 * fruit on that day in the field. Written into the note of KML, GPX and
 * GeoJSON (export_note), the find report, and MykIS's art_bemerkung
 * (mykis_row). The CSV has columns of its own (export_csv).
 *
 * '' for an ordinary find. The box's number only where the caller knows it
 * ('collection_parent_number', export_points) — the automatic MykIS
 * delivery does not ask, and a coordinator has no use for it anyway.
 */
function collection_note(array $p): string
{
    if (!empty($p['is_collection'])) {
        $parts = ['Aufsammlung ' . trim((string) ($p['collection_number'] ?? ''))];
        $since = collection_date($p['cultured_at'] ?? null);
        $ended = collection_date($p['culture_ended_at'] ?? null);
        if ($since !== '') {
            $parts[] = 'in Kultur seit ' . $since;
        }
        if ($ended !== '') {
            $parts[] = 'Kultur beendet ' . $ended;
        }
        return implode(', ', $parts);
    }
    if (($p['collection_id'] ?? null) === null) {
        return '';
    }
    $number = trim((string) ($p['collection_parent_number'] ?? ''));
    $text = 'ex cultura' . ($number !== '' ? ' (Aufsammlung ' . $number . ')' : '');
    $identified = collection_date($p['identified_at'] ?? null);
    if ($identified !== '') {
        $days = collection_days($p);
        $text .= ', bestimmt am ' . $identified
            . ($days === null ? '' : ($days === 1 ? ', 1 Tag nach dem Sammeln' : ", $days Tage nach dem Sammeln"));
    }
    return $text;
}
