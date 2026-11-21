<?php
/**
 * Points.
 *
 *   GET    points.php                  all active points
 *   GET    points.php?trash=1     deleted points
 *   GET    points.php?since=…          only what changed, deletions included (sync)
 *   GET    points.php?id=5             one point
 *   POST   points.php                  create
 *   PATCH  points.php?id=5             change
 *   DELETE points.php?id=5             into the trash
 *   POST   points.php?id=5&what=restore fetch back from the trash
 *   DELETE points.php?id=5&permanent=1 remove for good
 */

declare(strict_types=1);

// Excursion guests may act here — on their own finds of their running
// excursion, nothing else. The slice lives in excursion_guest_api();
// everything below this branch is the logged-in world.
const EXCURSION_GUESTS_ALLOWED = true;

require __DIR__ . '/_start.php';

if ($actor !== null) {
    try {
        excursion_guest_api($pdo, $actor, $method, $input);
    } catch (InvalidArgumentException $e) {
        json_error($e->getMessage(), 422);
    }
}

$userId = (int) $me['id'];

try {
    switch ($method) {
        case 'GET':
            $id = request_id();
            if ($id > 0) {
                $point = point_load($pdo, $userId, $id);
                $point === null ? json_error(t('Punkt nicht gefunden.'), 404) : json_response($point);
            }

            $options = [];
            if (!empty($_GET['trash'])) {
                $options['trash'] = true;
            }
            if (!empty($_GET['since'])) {
                // During the sync the deletions have to come along too,
                // otherwise removed points stay on the phone forever.
                $options['since'] = point_timestamp((string) $_GET['since']);
                $options['with_deleted'] = true;
            }

            json_response([
                'points' => points_load($pdo, $userId, $options),
                'touched_at'  => now(),
            ]);
            // no break needed, json_response terminates

        case 'POST':
            if (($_GET['what'] ?? '') === 'restore') {
                $id = request_id();
                if (!point_restore($pdo, $userId, $id)) {
                    json_error(t('Punkt nicht im Papierkorb.'), 404);
                }
                json_response(point_load($pdo, $userId, $id));
            }

            /* MAPPING FOR AN EXCURSION (24.08.2026): a
               logged-in participant stays himself — the find lands in HIS
               stock, and only the excursion's reference travels with it.
               That reference is what the leader's right hangs on, so
               this one line is what makes a registered participant's
               finds part of the excursion at all.

               DECIDED BY THE SESSION, not by the request: a browser must
               not be able to pin any find onto any excursion. And the
               window is asked again here — over is over, whatever a page
               opened this morning still believes. */
            // _start.php loads excursions.php only for the guest branch —
            // for a logged-in person it is not there, and the call below
            // would be a fatal error. (Caught before it ever ran.)
            require_once __DIR__ . '/../../includes/excursions.php';
            /* THE REQUEST NEVER NAMES AN EXCURSION (security review,
               23.09.2026). point_create() stores excursion_id and
               excursion_guest_id as given, and this handler only ever SET
               them from the session — a value the browser sent went
               through untouched. A made-up excursion number put a find
               into any excursion, and with it came the right to read that
               excursion's tracks (every participant's GPS). Both keys are
               dropped here; the session below is the only way in. */
            unset($input['excursion_id'], $input['excursion_guest_id']);
            $forExcursion = (int) ($_SESSION['excursion_mapping'] ?? 0);
            if ($forExcursion > 0 && excursions_ready($pdo)) {
                $stmt = $pdo->prepare('SELECT * FROM map_excursions WHERE id = ?');
                $stmt->execute([$forExcursion]);
                $e = $stmt->fetch();
                $phase = $e === false ? 'beendet' : excursion_phase($e);
                if ($phase === 'läuft') {
                    $input['excursion_id'] = $forExcursion;
                } elseif ($phase === 'beendet') {
                    unset($_SESSION['excursion_mapping']);
                }
                // 'geplant': whoever joined ahead (13.09.2026) keeps the
                // key for the day, and a find made before the start is
                // an ordinary own find — no stamp, no clearing.
            }
            $id = point_create($pdo, $userId, $input);
            // Set into a joint list? Then the share reads it (step 2, 22.09.2026).
            require_once __DIR__ . '/../../includes/joint_lists.php';
            joint_created_note($pdo, $userId, $id);
            json_response(point_load($pdo, $userId, $id), 201);

        case 'PATCH':
        case 'PUT':
            $id = request_id();
            if ($id <= 0) {
                json_error(t('Keine Kennung angegeben.'));
            }
            // One's own write releases the anchors of the touched fields:
            // what the owner enters themselves is the new original (stage 4
            // of sharing). BEFORE point_update, afterwards the comparison
            // state is gone — for foreign and unknown points the call does
            // nothing.
            require_once __DIR__ . '/../../includes/container.php';
            /* THE STATE BEFORE, for the chat of a joint list (step 2,
               22.09.2026): whatever path writes below — own, joint, sifted
               or the leader's — the share reads afterwards what changed.
               Taken here, before any of them, and compared once at the end
               of each. */
            require_once __DIR__ . '/../../includes/joint_lists.php';
            $snapshot = joint_snapshot($pdo, $id);
            original_maintain($pdo, $userId, $id, $input);

            if (!point_update($pdo, $userId, $id, $input)) {
                // Either the point does not exist, or it belongs to someone
                // else. Foreign with rights (stage 3 of sharing) is the one
                // case where things continue — otherwise both look the same
                // from the outside.
                if (point_load($pdo, $userId, $id) === null) {
                    /* THE FIND NUMBER IS THE HOLDER'S OWN (26.09.2026): it
                       belongs to their series, so no path below writes it —
                       not the leader's, not a joint list's, not a share's.
                       The window offers the field on one's own finds only;
                       this is for whatever sends it anyway, and it keeps the
                       sift from refusing the whole change over one field. */
                    unset($input['find_number']);
                    $foreign = point_foreign_rights($pdo, $userId, $id);
                    if ($foreign === null) {
                        /* THE LEADER'S PATH (27.08.2026). A
                           guest find hangs in no list, so the container
                           route above finds nothing — yet whoever leads
                           the excursion may edit every find in it. The
                           right hangs on the excursion reference, asked
                           fresh per request; 'editable' in the payload
                           only ever repeated this answer.

                           WRITTEN AS THE OWNER, without sift and without
                           anchor: the sift and the originals belong to
                           the shares, where an owner defends his wording
                           against collaborators. A guest is no such
                           party — the leader IS the editor, exactly as
                           Organisieren has written since stage 5. */
                        require_once __DIR__ . '/../../includes/excursions.php';
                        $scope = excursion_point_leader_scope($pdo, $userId, $id);
                        if ($scope === null) {
                            json_error(t('Punkt nicht gefunden.'), 404);
                        }
                        point_update($pdo, $scope, $id, $input);
                        joint_change_note($pdo, $userId, $id, $snapshot, joint_snapshot($pdo, $id));
                        json_response(excursion_points($pdo, $userId)[$id]
                            ?? point_load($pdo, $scope, $id));
                    }
                    /* A JOINT LIST (22.09.2026): every member is the holder's
                       equal in it — written AS THE HOLDER, like the leader's
                       path above, without sift and without anchor. The
                       sift and the originals are for shares where an owner
                       defends his wording against collaborators; in a joint
                       list there is no such party, only the chat that will
                       carry every change (step 2). */
                    if (!empty($foreign['joint'])) {
                        point_update($pdo, $foreign['owner'], $id, $input);
                        joint_change_note($pdo, $userId, $id, $snapshot, joint_snapshot($pdo, $id));
                        json_response(foreign_point_load($pdo, $userId, $id));
                    }
                    $sifted = foreign_input_sift($pdo, $id, $foreign['rights'], $input);
                    // Anchor first, then write — the anchor needs the state
                    // BEFORE the first foreign change.
                    original_remember($pdo, $id, $sifted, user_display_name($me));
                    point_update($pdo, $foreign['owner'], $id, $sifted);
                    joint_change_note($pdo, $userId, $id, $snapshot, joint_snapshot($pdo, $id));
                    json_response(foreign_point_load($pdo, $userId, $id));
                }
            }
            joint_change_note($pdo, $userId, $id, $snapshot, joint_snapshot($pdo, $id));
            json_response(point_load($pdo, $userId, $id));

        case 'DELETE':
            $id = request_id();
            if ($id <= 0) {
                json_error(t('Keine Kennung angegeben.'));
            }

            if (!empty($_GET['permanent'])) {
                // For good NEVER works through a container's rights — only
                // the owner empties their trash.
                require_once __DIR__ . '/../../includes/joint_lists.php';
                if (point_in_joint_trash($pdo, $id)) {
                    json_error(t('Der Punkt liegt noch im Papierkorb einer gemeinsamen Liste — dort holt ihn jedes Mitglied zurück.'), 409);
                }
                if (!point_delete_permanently($pdo, $userId, $id)) {
                    json_error(t('Punkt liegt nicht im Papierkorb.'), 404);
                }
                json_response(['deleted' => $id, 'permanent' => true]);
            }

            /* IN A JOINT LIST THE BUCKET IS THE LIST'S (step 2, 22.09.2026,
               option A): the point's FILING goes out of the list into the
               list's trash — `list=` names which, `all=1` every joint list
               the caller is in; the holder's bare delete or `whole=1` takes
               the filings out AND lays the record into his own trash. A
               member's bare delete means every joint list he sees. The
               record is in the trash as a whole only when no filing is
               left — joint_filing_remove decides that. */
            require_once __DIR__ . '/../../includes/joint_lists.php';
            $jointShares = point_joint_shares($pdo, $id, $userId);
            $stmt = $pdo->prepare('SELECT user_id FROM map_points WHERE id = ?');
            $stmt->execute([$id]);
            $holder = (int) $stmt->fetchColumn() === $userId;
            /* IN A LIST SHARE (0160) ONLY WITH THE TICK: somebody else's
               find comes out of the list for whoever may „Punkte in deinen
               Papierkorb legen" — one's own always. Without it the list is
               not his to tidy, however much he works in it. */
            if (!$holder) {
                foreach (array_keys($jointShares) as $cId) {
                    if (list_share_list($pdo, (int) $cId) !== null
                        && !in_array('delete', list_share_viewer($pdo, $userId, (int) $cId)['rights'] ?? [], true)) {
                        unset($jointShares[$cId]);
                    }
                }
            }
            if ($jointShares !== []) {
                $jointLists = array_values(array_unique(array_merge(...array_column(array_values($jointShares), 'lists'))));
                $chosen = isset($_GET['list'])
                    ? array_values(array_filter(array_map('intval', explode(',', (string) $_GET['list']))))
                    : null;
                $whole = $holder && ($chosen === null && empty($_GET['all']) || !empty($_GET['whole']));
                $removed = joint_filing_remove($pdo, $userId, $id, $chosen ?? $jointLists);
                if ($whole) {
                    point_delete($pdo, $userId, $id);
                }
                json_response(['deleted' => $id, 'removed_from' => $removed['removed'], 'whole' => $whole || $removed['whole']]);
            }

            if (!point_delete($pdo, $userId, $id)) {
                // Foreign with delete right: into the OWNER's trash — the
                // lifeline against a collaborator tapping the wrong thing.
                require_once __DIR__ . '/../../includes/container.php';
                $foreign = point_foreign_rights($pdo, $userId, $id);
                if ($foreign === null || !in_array('delete', $foreign['rights'], true)) {
                    // The leader's path, like in PUT above: into the guest
                    // account's trash, where Organisieren can bring it back.
                    require_once __DIR__ . '/../../includes/excursions.php';
                    $scope = excursion_point_leader_scope($pdo, $userId, $id);
                    if ($scope === null) {
                        json_error(t('Punkt nicht gefunden.'), 404);
                    }
                    point_delete($pdo, $scope, $id);
                    json_response(['deleted' => $id]);
                }
                point_delete($pdo, $foreign['owner'], $id);
            }
            json_response(['deleted' => $id]);

        default:
            json_error(t('Methode nicht unterstützt.'), 405);
    }
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
}

/** Bring the sync's time value into the database format. */
function point_timestamp(string $value): string
{
    $time = strtotime($value);
    return date('Y-m-d H:i:s', $time === false ? 0 : $time);
}
