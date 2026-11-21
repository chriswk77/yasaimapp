<?php
/**
 * Voucher photos.
 *
 *   GET    photos.php?point=5    the photos of a point
 *   POST   photos.php            store one (multipart/form-data)
 *   PATCH  photos.php            name, scale, measures of one — or
 *                                {point_id, order} for the gallery's order
 *   DELETE photos.php?id=7       remove one
 *
 * Normally photos are not read here at all: they hang on their point and
 * come with it via points.php. The GET is for the follow-up look after an
 * upload and for the check scripts.
 */

declare(strict_types=1);

// Excursion guests may act here (stage 6a) — photographing their own
// finds, within the leader's photo policy. The slice lives in
// excursion_guest_photos_api(); everything below is the logged-in world.
const EXCURSION_GUESTS_ALLOWED = true;

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/photos.php';

if (!photos_ready($pdo)) {
    json_error(t('Die Fotoverwaltung ist auf diesem Server noch nicht eingerichtet.'), 503);
}

if ($actor !== null) {
    try {
        excursion_guest_photos_api($pdo, $actor, $method, $input);
    } catch (InvalidArgumentException $e) {
        json_error($e->getMessage(), 422);
    }
}

$userId = (int) $me['id'];

try {
    switch ($method) {
        case 'GET':
            $pointId = (int) ($_GET['point'] ?? 0);
            if ($pointId <= 0) {
                json_error(t('Kein Punkt angegeben.'));
            }
            /* THE HOLDER'S SET FOR A FOREIGN FIND (14.09.2026). The
               gallery fetches a foreign find's pictures fresh since the
               co-ownership step of 11.09 — and this line answered with
               the CALLER's pictures on it, which for a share member are
               none. The empty list overrode the snapshot the bucket had
               brought, and a friend saw no picture on any shared find.
               point_view_scope() names whose set the viewer gets: the
               holder's, the same one the point payload carries. A
               stranger, or a trashed point, falls back to the old answer
               — one's own pictures on it, which is nothing. */
            require_once __DIR__ . '/../../includes/point_owners.php';
            $eyes = point_view_scope($pdo, $userId, $pointId) ?? $userId;
            json_response(['photos' => photos_of_point($pdo, $eyes, $pointId)]);

        case 'POST':
            // THE SILENT FAILURE FIRST.
            //
            // If the call exceeds post_max_size, PHP throws the whole body
            // away: $_POST and $_FILES are empty, and there is no error
            // code by which one could tell — not in
            // $_FILES['image']['error'] either. Without this check the user
            // would get "Es ist kein Bild angekommen", while in truth the
            // server limit is set too low. That is the difference between
            // an error message and a debugging session.
            if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
                json_error(
                    t('Das Bild war zu gross für diesen Server (Grenze: %s). Bitte beim Hoster anheben lassen.', (string) ini_get('post_max_size')),
                    413
                );
            }

            $pointId = (int) ($input['point_id'] ?? 0);
            if ($pointId <= 0) {
                json_error(t('Kein Punkt angegeben.'));
            }

            $photo = photo_create(
                $pdo,
                $userId,
                $pointId,
                $_FILES['image'] ?? [],
                $_FILES['preview'] ?? null,
                $input
            );
            // STRAIGHT ON INTO THE USER'S OWN STORAGE, if there is one.
            // This server is a waiting room, not an archive — see
            // includes/photo_cloud.php. It costs this request a moment; it
            // runs in the phone's background transfer, where nobody is
            // watching a spinner, and it saves the picture a stay on a
            // shared host.
            //
            // Best effort: whatever does not work stays lying here and goes
            // on the next sweep. A voucher photo must never be lost because
            // a foreign service was slow.
            require_once __DIR__ . '/../../includes/photo_cloud.php';
            if (photo_cloud_ready($pdo, $userId)) {
                $stmt = $pdo->prepare('SELECT * FROM map_photos WHERE id = ? AND user_id = ?');
                $stmt->execute([(int) $photo['id'], $userId]);
                if ($row = $stmt->fetch()) {
                    photo_to_cloud($pdo, $userId, $row);
                }
            }

            json_response($photo, 201);

        /* The calibration and the measures (migrations 0088/0092). Body:
           {id, scale?, measures?} — ONLY THE KEYS THAT ARE PRESENT are
           touched (array_key_exists, not ??): the old client always sent
           'scale', and a measures-only save must not read as "clear the
           scale". scale as {line, um, corner} to set, null to clear;
           measures as the whole list, [] to clear.

           WHO MAY: the same circle that may delete the photo, and through
           the same three doors as DELETE below — own photo, foreign with
           the delete_photos right, excursion leader or participant scope.
           Deliberately no right of its own: whoever is trusted to REMOVE a
           picture is surely trusted to hang a scale bar on it, and a new
           entry in SHARE_RIGHTS would cost every share dialog a checkbox
           for a distinction nobody asked for. */
        case 'PATCH':
            /* THE GALLERY'S ORDER (30.09.2026): {point_id, order: [ids]}
               — a whole point, not one picture, hence its own branch
               before the id check. Through point_attach_scope, the door
               that lets one attach a picture here: whoever may add to a
               find's gallery may arrange it. photos_reorder() says why
               one order serves the whole circle. */
            if (array_key_exists('order', $input)) {
                $pointId = (int) ($input['point_id'] ?? 0);
                if ($pointId <= 0 || !is_array($input['order'])) {
                    json_error(t('Kein Punkt angegeben.'));
                }
                require_once __DIR__ . '/../../includes/point_owners.php';
                if (point_attach_scope($pdo, $userId, $pointId) === null) {
                    json_error(t('Punkt nicht gefunden.'), 404);
                }
                json_response([
                    'point_id' => $pointId,
                    'order'    => photos_reorder($pdo, $pointId, $input['order']),
                ]);
            }

            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                json_error(t('Keine Kennung angegeben.'));
            }
            $hasScale    = array_key_exists('scale', $input);
            $hasMeasures = array_key_exists('measures', $input);
            $hasTitle    = array_key_exists('title', $input);
            // The medium (0187) — quietly skipped before its migration, as at the cloud recordings.
            $hasMedia    = array_key_exists('media', $input);
            $hasDevelop  = array_key_exists('develop', $input);
            if (!$hasScale && !$hasMeasures && !$hasTitle && !$hasMedia && !$hasDevelop) {
                json_error(t('Weder Eichung noch Messungen noch Titel angegeben.'));
            }
            $hasMedia = $hasMedia && column_exists($pdo, 'map_photos', 'media');
            $media = $hasMedia ? photo_media_clean($input['media']) : null;
            // The development (0190) — the same quiet skip before its migration.
            $hasDevelop = $hasDevelop && column_exists($pdo, 'map_photos', 'develop');
            $develop = $hasDevelop ? photo_develop_clean($input['develop']) : null;
            /* THE OTHER EDITS (0192), sent beside the valid one and only
               with it — false leaves the stored ones as they are (a client
               that does not know them, or the column not yet there). */
            $versions = $hasDevelop && array_key_exists('develop_versions', $input)
                && column_exists($pdo, 'map_photos', 'develop_versions')
                ? photo_develop_versions_clean($input['develop_versions']) : false;
            if ($hasDevelop && !photo_develop_fits($pdo, 'map_photos', $develop)) {
                json_error(t('So viele ausgebesserte Stellen passen erst nach der Migration 0193.'), 422);
            }
            /* THE PATCHES OF „ENTFERNEN" ride with the develop that names
               them (includes/develop_patches.php) — checked here, written
               below before the develop is. */
            $patches = [];
            if ($hasDevelop && array_key_exists('patches', $input)) {
                require_once __DIR__ . '/../../includes/develop_patches.php';
                try {
                    $patches = develop_patches_decode($input['patches'], $develop, $versions ?: null);
                } catch (InvalidArgumentException $e) {
                    json_error($e->getMessage());
                }
            }
            if ($hasScale && !column_exists($pdo, 'map_photos', 'scale')) {
                json_error(t('Der Maßstab braucht die Migration 0088 — sie ist noch nicht eingespielt.'), 503);
            }
            if ($hasMeasures && !column_exists($pdo, 'map_photos', 'measures')) {
                json_error(t('Die Messungen brauchen die Migration 0092 — sie ist noch nicht eingespielt.'), 503);
            }
            if ($hasTitle && !column_exists($pdo, 'map_photos', 'title')) {
                json_error(t('Der Bildtitel braucht die Migration 0097 — sie ist noch nicht eingespielt.'), 503);
            }
            $title = $hasTitle ? photo_title_clean($input['title']) : null;

            $scale = null;
            if ($hasScale && $input['scale'] !== null) {
                if (!is_array($input['scale'])) {
                    json_error(t('Unbrauchbare Eichung.'));
                }
                try {
                    $scale = photo_scale_write($input['scale']);
                } catch (InvalidArgumentException $e) {
                    json_error($e->getMessage());
                }
            }
            $measures = null;
            if ($hasMeasures) {
                if (!is_array($input['measures'])) {
                    json_error(t('Unbrauchbare Messungen.'));
                }
                try {
                    $measures = photo_measures_write($input['measures']);
                } catch (InvalidArgumentException $e) {
                    json_error($e->getMessage());
                }
            }

            /* NO PRESET IS MINTED HERE any more (26.09.2026): a scale set
               in the photo window belongs to that photo alone — a macro
               photo's distance and lens change from shot to shot, so its
               calibration says nothing about the next one. Presets come
               from a calibration image in the administration
               (settings_content.php, 'scale_calibrate'). An old client
               still sending „remember_as" gets its scale stored and
               nothing more. */

            /* Resolve the OWNER once, then store what was sent — the three
               doors in order. Two store calls must not each walk the
               cascade: they could disagree, and a half-written pair would
               be worse than a refusal. */
            $owner = photo_load($pdo, $userId, $id) !== null ? $userId : null;
            if ($owner === null) {
                require_once __DIR__ . '/../../includes/container.php';
                // photo_foreign_rights() gates on the right itself — null
                // already means "no such right". Storing over somebody
                // else's picture is `measure` (an old box's „Belegbilder
                // löschen" carries it along, rights_compat).
                $foreign = photo_foreign_rights($pdo, $userId, $id, 'measure');
                if ($foreign !== null) {
                    $owner = (int) $foreign['owner'];
                    /* THE PHOTOGRAPHER'S EDITS ARE HIS ALONE
                       (11.11.2026, „Fremdbearbeitungen": „Alles beim
                       Besitzer"). `measure` is the scale, the measures and
                       the name — whoever may edit the picture lays his OWN
                       edits beside it (api/v1/picture_edits.php). */
                    if ($hasDevelop) {
                        json_error(t('Die Bearbeitungen eines Bildes ändert nur, wer es aufgenommen hat.'), 403);
                    }
                }
            }
            if ($owner === null) {
                require_once __DIR__ . '/../../includes/excursions.php';
                $stmt = $pdo->prepare('SELECT point_id FROM map_photos WHERE id = ?');
                $stmt->execute([$id]);
                $pointId = $stmt->fetchColumn();
                $owner = $pointId === false ? null
                    : (excursion_point_leader_scope($pdo, $userId, (int) $pointId)
                        ?? excursion_point_participant_scope($pdo, $userId, (int) $pointId));
            }
            if ($owner === null) {
                json_error(t('Foto nicht gefunden.'), 404);
            }
            /* NO EDITING WITHOUT A CLOUD (10.11.2026): a voucher
               photo that lies on this server — its owner has no cloud, or it
               still waits for its way there — takes no development. Taking
               one OFF stays allowed, so nothing is ever stuck. Asked before
               anything is stored, so a request is never half kept. */
            if ($hasDevelop && photo_develop_any($develop, $versions ?: null) && column_exists($pdo, 'map_photos', 'cloud_fileid')) {
                $where = $pdo->prepare('SELECT cloud_fileid FROM map_photos WHERE id = ?');
                $where->execute([$id]);
                if ((string) $where->fetchColumn() === '') {
                    json_error(t('Bearbeiten geht nur bei Bildern, die in einer Cloud liegen.'), 409);
                }
            }
            /* The patches into the picture's cloud FIRST: a stored develop
               must never name a patch the cloud does not hold. And what the
               develop named before is remembered, so the patches of places
               taken out can go after the save (develop_patches_gone). */
            $prior = null;
            if ($hasDevelop) {
                $stmt = $pdo->prepare('SELECT * FROM map_photos WHERE id = ?');
                $stmt->execute([$id]);
                $prior = $stmt->fetch() ?: null;
            }
            if ($patches !== [] && $prior !== null) {
                $patchCloud = develop_patches_cloud($pdo, 'photo', $prior);
                if ($patchCloud === null) {
                    json_error(t('Die Cloud des Bildes ist gerade nicht erreichbar.'), 502);
                }
                try {
                    develop_patches_store($patchCloud, (string) $prior['cloud_fileid'], $patches);
                } catch (RuntimeException $e) {
                    error_log('photos.php patches (' . $id . '): ' . $e->getMessage());
                    json_error(t('Die entfernten Stellen ließen sich nicht in der Cloud speichern.'), 502);
                }
            }
            if ($hasScale && !photo_scale_store($pdo, $owner, $id, $scale)) {
                json_error(t('Foto nicht gefunden.'), 404);
            }
            if ($hasMeasures && !photo_measures_store($pdo, $owner, $id, $measures)) {
                json_error(t('Foto nicht gefunden.'), 404);
            }
            if ($hasTitle && !photo_title_store($pdo, $owner, $id, $title)) {
                json_error(t('Foto nicht gefunden.'), 404);
            }
            if ($hasMedia && !photo_media_store($pdo, $owner, $id, $media)) {
                json_error(t('Foto nicht gefunden.'), 404);
            }
            if ($hasDevelop && !photo_develop_store($pdo, $owner, $id, $develop, $versions)) {
                json_error(t('Foto nicht gefunden.'), 404);
            }

            /* THE SAFETY COPY BESIDE THE PICTURE (28.08.2026):
               the scale as a sidecar JSON in the OWNER's cloud — theirs,
               because the picture lies in their storage, whoever just
               calibrated it. Best effort behind the response's back would
               be nicer, but PHP has no back; a provider hiccup only costs
               this request a moment and the copy nothing, it comes again
               with the next change. */
            $stmt = $pdo->prepare('SELECT * FROM map_photos WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                require_once __DIR__ . '/../../includes/photo_cloud.php';
                photo_scale_sidecar($pdo, (int) $row['user_id'], $row);
                if ($prior !== null) {
                    require_once __DIR__ . '/../../includes/develop_patches.php';
                    // Named by no edit any more — the valid one, the others (0192), nor anybody else's (0194).
                    $gone = array_values(array_diff(develop_row_patch_ids($prior), develop_row_patch_ids($row),
                        develop_foreign_patch_ids($pdo, 'photo', $id)));
                    if ($gone !== [] && (string) ($row['cloud_fileid'] ?? '') !== '') {
                        $patchCloud ??= develop_patches_cloud($pdo, 'photo', $row);
                        if ($patchCloud !== null) {
                            develop_patches_gone($patchCloud, (string) $row['cloud_fileid'], $gone);
                        }
                    }
                }
            }

            json_response([
                'id'       => $id,
                'scale'    => $row ? photo_scale_read($row['scale'] ?? null) : $scale,
                'measures' => $row ? photo_measures_read($row['measures'] ?? null) : ($measures ?? []),
                'title'    => $row ? ($row['title'] ?? null) : $title,
                'media'    => $row ? photo_media_read($row['media'] ?? null) : $media,
                'develop'  => $row ? photo_develop_read($row['develop'] ?? null) : $develop,
                'develop_versions' => $row ? photo_develop_versions_read($row['develop_versions'] ?? null) : ($versions ?: null),
            ]);

        case 'DELETE':
            $id = request_id();
            if ($id <= 0) {
                json_error(t('Keine Kennung angegeben.'));
            }
            if (!photo_delete($pdo, $userId, $id)) {
                // Someone else's photo with the bilder_loeschen right
                // (stage 3 of sharing): deletion happens in the owner's
                // name — the file sits in their directory, their point is
                // touched.
                require_once __DIR__ . '/../../includes/container.php';
                $foreign = photo_foreign_rights($pdo, $userId, $id);
                $stmt = $pdo->prepare('SELECT point_id FROM map_photos WHERE id = ?');
                $stmt->execute([$id]);
                $photoPoint = (int) $stmt->fetchColumn();
                $done = $foreign !== null && photo_delete($pdo, $foreign['owner'], $id);
                if ($done) {
                    list_share_picture_removed_note($pdo, $userId, $photoPoint, (int) $foreign['owner']);
                }
                if (!$done) {
                    // THE LEADER SORTS OUT (stage 6, and the concept's
                    // whole reason), THE PARTICIPANT his own old ones
                    // (stage 7) — both rights ride on the excursion
                    // reference, deleted is in the guest account's name.
                    require_once __DIR__ . '/../../includes/excursions.php';
                    $stmt = $pdo->prepare('SELECT point_id FROM map_photos WHERE id = ?');
                    $stmt->execute([$id]);
                    $pointId = $stmt->fetchColumn();
                    $owner = $pointId === false ? null
                        : (excursion_point_leader_scope($pdo, $userId, (int) $pointId)
                            ?? excursion_point_participant_scope($pdo, $userId, (int) $pointId));
                    $done = $owner !== null && photo_delete($pdo, $owner, $id);
                }
                if (!$done) {
                    json_error(t('Foto nicht gefunden.'), 404);
                }
            }
            json_response(['deleted' => $id]);

        default:
            json_error(t('Methode nicht unterstützt.'), 405);
    }
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    // Disk full, directory not writable — nothing the user could do
    // differently, but also nothing they should accept in silence.
    error_log('Foto-Upload gescheitert: ' . $e->getMessage());
    json_error($e->getMessage(), 500);
}
