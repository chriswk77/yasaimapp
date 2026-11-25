<?php
/**
 * DNA sequences at a find (migration 0112) — see includes/sequences.php.
 *
 *   POST sequences.php {point_id, text, marker, label, lab, sample_no,
 *                       accession, primers, note}          hang on (FASTA or bases)
 *   POST sequences.php {action: update, id, marker, …}    the author edits the columns
 *   POST sequences.php {action: delete, id}               the author takes it down
 *
 * Everything is POST with an action, like owners.php: the text can be a
 * thousand letters, and one door is easier to keep than three.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/sequences.php';

if ($me === null) {
    json_error(t('Dafür musst du angemeldet sein.'), 403);
}
$userId = (int) $me['id'];

if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
if (!sequences_ready($pdo)) {
    json_error(t('Sequenzen brauchen die Migration 0112 — sie ist noch nicht eingespielt.'), 503);
}

$action = (string) ($input['action'] ?? 'create');
switch ($action) {
    case 'delete':
        $id = (int) ($input['id'] ?? 0);
        if (!sequence_delete($pdo, $userId, $id)) {
            json_error(t('Diese Sequenz ist nicht deine — oder es gibt sie nicht mehr.'), 404);
        }
        json_response(['deleted' => $id]);

    case 'update':
        $id = (int) ($input['id'] ?? 0);
        if (!sequence_update($pdo, $userId, $id, $input)) {
            json_error(t('Diese Sequenz ist nicht deine — oder es gibt sie nicht mehr.'), 404);
        }
        json_response(['sequence' => sequence_load($pdo, $id)]);

    default:
        $pointId = (int) ($input['point_id'] ?? 0);
        if ($pointId <= 0) {
            json_error(t('Kein Punkt angegeben.'));
        }
        try {
            $rows = sequence_create($pdo, $userId, $pointId, $input);
        } catch (InvalidArgumentException $e) {
            json_error(error_for_user($e), 422);
        } catch (RuntimeException $e) {
            json_error(error_for_user($e), 503);
        }
        /* A JOINT LIST READS IT IN ITS CHAT (joint lists, step 2): a
           voucher hung on is a change like any other. */
        require_once __DIR__ . '/../../includes/joint_lists.php';
        $shares = point_joint_shares($pdo, $pointId);
        if ($shares !== []) {
            $first  = $rows[0] ?? null;
            $marker = $first !== null && $first['marker'] !== '' ? $first['marker'] : t('Sequenz');
            // Per share, with the joint lists of that share the find lies in.
            foreach ($shares as $cId => $share) {
                $lists = joint_list_names($pdo, $share['lists']);
                joint_note($pdo, $userId,
                    count($rows) === 1
                        ? t('hat eine Sequenz an „%s" in „%s" gehängt (%s)', joint_point_name($pdo, $pointId), $lists, $marker)
                        : t('hat %d Sequenzen an „%s" in „%s" gehängt (%s)', count($rows), joint_point_name($pdo, $pointId), $lists, $marker),
                    [$cId]);
            }
        }
        json_response(['sequences' => $rows], 201);
}
