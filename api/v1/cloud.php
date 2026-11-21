<?php
/**
 * Uploading into one's own cloud — the two steps the browser needs.
 *
 *   POST cloud.php?id=5&action=target      where does the recording go?
 *   POST cloud.php?id=5&action=record      it arrived, here is its id
 *   POST cloud.php?id=5&action=remove      away with it, here and in the cloud
 *
 * WHY THE FILE DOES NOT COME THROUGH HERE. 'upload_max_filesize' on this
 * hosting is 2 MB, and the pictures this exists for — stacked shots, raw
 * files, micrographs — are many times that. Routing them through the server
 * would defeat the very purpose of one's own storage. So the browser hands
 * them straight to pCloud; this endpoint only says where to and writes down
 * what came back.
 *
 * THE TOKEN GOES TO THE BROWSER, AND THAT IS A DELIBERATE WEAKENING.
 * Everywhere else in this application it never leaves cloud.php. Here it must:
 * without it the browser cannot talk to pCloud. Three things make it bearable,
 * and all three have to hold — whoever removes one of them should know what
 * they are removing:
 *
 *   IT IS FENCED IN. The app was registered as "Private", so the token opens
 *   /Applications/Karte and nothing else. Even in the wrong hands it does not
 *   reach the user's archive. With an "All folders" app this endpoint would
 *   be irresponsible.
 *
 *   IT ONLY GOES OUT WITH A TARGET. One asks for a folder for a specific find
 *   of one's own; there is no "give me the token" call. That keeps it out of
 *   pages that have nothing to do with uploading.
 *
 *   IT ONLY GOES TO ITS OWNER. The connection is looked up for the logged-in
 *   user, never by id from the request.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';

/* NOT FOR DEVICE TOKENS (security review, 23.09.2026). The recorder app's
   bearer token never expires and only ever needs api/v1/tracks.php — but
   here it would receive the account's pCloud access token, which covers
   the whole cloud folder and does not expire either. A lost phone must
   not be a lost cloud. The browser, with its session, is unaffected. */
if (token_from_request() !== null) {
    json_error(t('Ein Geräte-Zugang reicht für die Cloud nicht — bitte im Browser anmelden.'), 403);
}
require_once __DIR__ . '/../../includes/cloud.php';
// The scale gate lives with the photos — the 'scale' action below
// speaks it.
require_once __DIR__ . '/../../includes/photos.php';

$userId = (int) $me['id'];

if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
if (!cloud_ready($pdo)) {
    json_error(t('Die Cloud ist auf diesem Server noch nicht eingerichtet.'), 503);
}

$id = request_id();
if ($id <= 0) {
    json_error(t('Keine Kennung angegeben.'));
}

// THE RAW ROW, NOT point_load().
//
// point_load() returns what the browser gets, and that shape drops
// 'extra_species' on purpose. cloud_find_from_point() needs exactly that
// field — handing the prepared array in made 'filable' false on every point
// there is, however well determined the find was. The WHERE clause carries
// the same permission check point_load() does, no less.
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

/* MINE, OR ONE I MAY ATTACH TO (co-ownership, 11.09.2026). The holder's
   id comes back for the bookkeeping below; a stranger gets the same 404
   a missing point gets. The recordings themselves stay keyed to THIS user
   in every action — one uploads into one's own cloud, whoever's find.

   OR ONE I MAY MERELY LOOK AT — FOR THE LISTING ALONE (14.09.2026). A
   share member sees the holder's voucher photos since 23.08.2026 and
   never saw his recordings: this gate turned him away before 'files'
   was even reached, and the browser did not ask for a find of somebody
   else's unless it had a cloud of its own. Whoever may see the find may
   see its pictures, all of them; point_view_scope() is the seeing gate.
   Every writing action keeps the attaching one. */
require_once __DIR__ . '/../../includes/point_owners.php';
$holderId = point_attach_scope($pdo, $userId, $id);
/* STORING MEASURES ON A RECORDING AND REMOVING ONE need no attaching
   right either — only seeing the find; whose recording it is and what
   the share grants is asked per file below (picture_actor). */
if ($holderId === null && in_array($action, ['files', 'scale', 'remove'], true)) {
    $holderId = point_view_scope($pdo, $userId, $id);
}

/**
 * The recording's row on this point and in whose name to touch it — the
 * photographer's, when it is somebody else's and the share grants $right.
 * 404 otherwise, the answer a missing recording gets.
 */
function cloud_file_actor(PDO $pdo, int $userId, int $pointId, int $fileRow, string $right): int
{
    $stmt = $pdo->prepare('SELECT user_id FROM map_cloud_files WHERE id = ? AND point_id = ? LIMIT 1');
    $stmt->execute([$fileRow, $pointId]);
    $photographer = $stmt->fetchColumn();
    $actor = $photographer === false ? null : picture_actor($pdo, $userId, $pointId, (int) $photographer, $right);
    if ($actor === null) {
        json_error(t('Aufnahme nicht gefunden.'), 404);
    }
    return $actor;
}
$stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ? LIMIT 1');
$stmt->execute([$holderId === null ? 0 : $id]);
$point = $stmt->fetch();
if ($point === false) {
    json_error(t('Punkt nicht gefunden.'), 404);
}

/* THE SCALE NEEDS NO PROVIDER — it is our own note about the recording,
   pure database. Demanding a live connection here would mean: whoever
   disconnects their cloud loses the ability to calibrate recordings whose
   rows and previews they still see. NEITHER DOES THE LISTING (14.09.2026):
   the rows are ours, the previews come through cloud_thumb.php with
   whichever token the picture needs — a viewer without a cloud of his
   own still sees the holder's recordings. The one thing the listing does
   with the caller's token, the folder look-up, is skipped without one.
   NOR DOES THE SIZE, which is our note too.

   EVERY RECORDING KNOWS ITS CLOUD (26.09.2026): a new one goes into the
   cloud the BROWSER may upload to (cloud_uploads_storage — each provider
   once it is released on the upload list); removing one knocks at the
   cloud it LIES in, by the provider on its row. */
$s = in_array($action, ['target', 'record'], true) ? cloud_uploads_storage($pdo, $userId) : null;
if (in_array($action, ['target', 'record'], true) && $s === null) {
    json_error(t('Keine Cloud verbunden.'), 409);
}

switch ($action) {

    // Step one: name the folder, create it, rearrange if the letter has
    // tipped — and only then hand out what the browser needs.
    //
    // THE FILE ARRIVES DESCRIBED since the other clouds came (26.09.2026):
    // name, size, type and its own time — Drive and OneDrive open their
    // session for exactly that file, S3 signs exactly its key. pCloud needs
    // none of it and an older page sends none.
    case 'target':
        $wanted = cloud_file_name_clean((string) ($_POST['name'] ?? ''));
        $type   = (string) ($_POST['type'] ?? '');
        $type   = preg_match('~^[a-z]+/[a-z0-9.+-]+$~i', $type) === 1 ? $type : 'application/octet-stream';
        $size   = max(0, (int) ($_POST['size'] ?? 0));
        $mtime  = (int) ($_POST['mtime'] ?? 0) ?: null;
        if ($wanted === '' && $s['provider'] !== 'pcloud') {
            json_error(t('Der Dateiname fehlt.'));
        }
        try {
            // EVERY SAVED POINT CAN RECEIVE SOMETHING, since the call
            // of 19.08.2026. A find without a name used to be refused here —
            // and that was backwards: a micrograph of something one cannot
            // name is exactly the picture worth keeping. It goes to
            // "Unbestimmt" and moves on by itself once the name is there.
            $home   = cloud_home_resolve($pdo, $userId, $s);
            $target = cloud_target_folder($s, $home, $pdo, $userId, $point);
            // The way in, the provider's own (cloud_upload_target) — for S3
            // with the two preview addresses, S3 draws none itself.
            $way = cloud_upload_target($s, $target['folderid'], $wanted, $type, $size, $mtime,
                (string) $home, true);
        } catch (RuntimeException $e) {
            // 502: not our mistake, the provider could not deliver.
            json_error($e->getMessage(), 502);
        }

        json_response($way + [
            'folderid'   => (string) $target['folderid'],
            'path'       => implode('/', $target['path']),
            'moved' => $target['moved'],
        ]);
        break;

    // Step two: it is up there. Write down which recording belongs to which
    // find — that is the only thing this server keeps of it.
    case 'record':
        /* The provider's id, as the provider named it — a string since
           0138 (Drive, OneDrive and S3 name their files; pCloud's numbers
           ride as digits). It comes from the client, so it is held to the
           shape of the cloud it claims to be in. */
        $fileid = trim((string) ($_POST['fileid'] ?? ''));
        $name   = trim((string) ($_POST['name'] ?? ''));
        if ($fileid === '' || $name === '' || mb_strlen($fileid) > 512
            || ($s['provider'] === 'pcloud' && !ctype_digit($fileid))) {
            json_error(t('fileid und name werden gebraucht.'));
        }
        $folderid = trim((string) ($_POST['folderid'] ?? ''));

        /* ASKED BACK AT THE CLOUD, beyond pCloud: whether the file really
           arrived, what it is called there, how big it is — the browser's
           word is not taken for it where the provider can be asked in one
           call. At S3 the key must also lie inside the app's folder. */
        $size = (int) ($_POST['size'] ?? 0) ?: null;
        if ($s['provider'] !== 'pcloud') {
            if ($s['provider'] === 's3' && !str_starts_with($fileid, (string) cloud_home_resolve($pdo, $userId, $s))) {
                json_error(t('fileid und name werden gebraucht.'));
            }
            try {
                $there = storage_file_meta($s, $fileid);
            } catch (RuntimeException $e) {
                json_error($e->getMessage(), 502);
            }
            if ($there === null) {
                json_error(t('Die Aufnahme ist in der Cloud nicht angekommen.'), 409);
            }
            $name = $there['name'] !== '' ? $there['name'] : $name;
            $size = $there['size'] > 0 ? $there['size'] : $size;
        }

        /* The ORIGINAL's pixel dimensions ride along since migration
           0095 — cloudupload.js reads them off the picture before it
           leaves the device. They are what lets a preset stamp onto the
           PREVIEW later (µm per original pixel needs to know the
           original). Guarded: the code is live before the migration,
           and a RAW file the browser cannot decode sends nothing. */
        $hasDims = column_exists($pdo, 'map_cloud_files', 'width');
        /* THE END OF THE GALLERY'S ROW (0148), behind photos and
           recordings alike. A recording filed a second time keeps its
           place — unless it moved to another find, where it is new. The
           IF stands BEFORE point_id's own assignment: the database reads
           the updated value in every assignment after it. */
        $hasOrder = cloud_files_sortable($pdo);
        /* WHAT THE CAMERA CALLED IT (0151), kept on the row when the name
           in the cloud becomes the find's (cloud_names.php) — set on the
           first recording only, never overwritten by a later one. */
        $hasOriginal = column_exists($pdo, 'map_cloud_files', 'original_name');
        $pdo->prepare(
            'INSERT INTO map_cloud_files
                    (user_id, point_id, provider, fileid, folderid, name, ' . ($hasOriginal ? 'original_name, ' : '') . 'path,
                     size, ' . ($hasDims ? 'width, height, ' : '') . ($hasOrder ? 'sort_order, ' : '') . 'uploaded_at)
             VALUES (?, ?, ?, ?, ?, ?, ' . ($hasOriginal ? '?, ' : '') . '?, ?, ' . ($hasDims ? '?, ?, ' : '') . ($hasOrder ? '?, ' : '') . 'NOW())
             ON DUPLICATE KEY UPDATE
                    ' . ($hasOrder ? 'sort_order = IF(point_id = VALUES(point_id), sort_order, VALUES(sort_order)),
                    ' : '') . 'point_id = VALUES(point_id), folderid = VALUES(folderid),
                    name = VALUES(name), path = VALUES(path),
                    size = VALUES(size), '
                    . ($hasDims ? 'width = VALUES(width), height = VALUES(height), ' : '')
                    . 'uploaded_at = NOW()'
        )->execute(array_merge(
            [
                $userId, $id, $s['provider'], $fileid,
                $folderid === '' || $folderid === '0' ? null : mb_substr($folderid, 0, 512),
                mb_substr($name, 0, 255),
            ],
            $hasOriginal ? [mb_substr($name, 0, 255)] : [],
            [
                mb_substr(trim((string) ($_POST['path'] ?? '')), 0, 512),
                $size,
            ],
            $hasDims ? [
                (int) ($_POST['width'] ?? 0) ?: null,
                (int) ($_POST['height'] ?? 0) ?: null,
            ] : [],
            $hasOrder ? [photo_next_sort_order($pdo, $id)] : []
        ));

        /* The point's bucket must learn about the new recording: its
           count is the „bearbeitet" mark in the sidebar, and without the
           nudge every OTHER device would keep the old row until the
           point changes for some unrelated reason. THE HOLDER's bucket —
           on a foreign find that is not this user's; photo_point_touched
           finds it by the point itself. */
        photo_point_touched($pdo, $id);

        // Co-ownership and the copies for the others (point_owners.php).
        $stmt = $pdo->prepare('SELECT id FROM map_cloud_files WHERE user_id = ? AND provider = ? AND fileid = ? LIMIT 1');
        $stmt->execute([$userId, $s['provider'], $fileid]);
        $rowId = $stmt->fetchColumn();
        if ($rowId !== false) {
            point_photo_attached($pdo, $id, $userId, 'cloud', (int) $rowId);
        }

        /* WHO TOOK IT, where it was not the uploader (08.11.2026:
           „wenn ich ein fremdes Foto hochlade … würde fälschlich ich als
           Urheber drin stehen"). The upload asks („Wer hat die Fotos
           gemacht?", photogallery.js) and sends a name only for „jemand
           anderes" — „ich" sends nothing, and nothing is the row's user, as
           taken_by has meant since 0161. Only ever SET here: a recording
           filed again without the answer keeps the name it had. */
        $takenBy = mb_substr(trim((string) ($_POST['taken_by'] ?? '')), 0, 120);
        if ($takenBy !== '' && $rowId !== false && column_exists($pdo, 'map_cloud_files', 'taken_by')) {
            $pdo->prepare('UPDATE map_cloud_files SET taken_by = ? WHERE id = ? AND user_id = ?')
                ->execute([$takenBy, (int) $rowId, $userId]);
        }

        /* THE WHOLE ONE REPLACES ITS SHRUNK COPY — replaces_photo names the
           2048 px copy that went up while the original waited in the
           phone's gallery (photo_replace_with_recording says when it stays
           instead). The answer says which, so the page drops the copy from
           its gallery without waiting for the next sync. */
        $replaced = false;
        $replaces = (int) ($_POST['replaces_photo'] ?? 0);
        if ($replaces > 0 && $rowId !== false) {
            $replaced = photo_replace_with_recording($pdo, $userId, $id, $replaces, (int) $rowId);
        }

        // The new picture takes its name and its number (cloud_names.php).
        require_once __DIR__ . '/../../includes/cloud_names.php';
        cloud_names_touch($pdo, $id);

        json_response(['files' => cloud_files_for_point($pdo, $userId, $id),
                       'replaced' => $replaced ? $replaces : null]);
        break;

    /* The original's dimensions, filed AFTER the fact (migration 0095):
       the Volle-Größe button just loaded the original of an older
       recording, and what the browser now knows should not have to be
       measured again on every opening. Own rows only, numbers only —
       and only ever FILLED, never cleared: a dimension, once true, stays
       true. */
    case 'size':
        if (!column_exists($pdo, 'map_cloud_files', 'width')) {
            json_error(t('Die Bildmaße brauchen die Migration 0095 — sie ist noch nicht eingespielt.'), 503);
        }
        $fileRow = (int) ($_POST['file'] ?? 0);
        $width   = (int) ($_POST['width'] ?? 0);
        $height  = (int) ($_POST['height'] ?? 0);
        if ($fileRow <= 0 || $width <= 0 || $width > 100000
            || $height <= 0 || $height > 100000) {
            json_error(t('Aufnahme und Maße werden gebraucht.'));
        }
        $pdo->prepare(
            'UPDATE map_cloud_files SET width = ?, height = ?
              WHERE id = ? AND user_id = ?'
        )->execute([$width, $height, $fileRow, $userId]);
        json_response(['file' => $fileRow, 'width' => $width, 'height' => $height]);
        break;

    // The recordings of a find, with a preview address each.
    //
    // THE PREVIEW LINKS ARE FETCHED HERE, not in the browser: they carry no
    // token, so they may go into an <img src> — and the token stays where it
    // belongs. One call per file, and only for those that have a preview at
    // all; a raw file the provider cannot read simply gets none.
    case 'files':
        // A GOOD MOMENT FOR THE STRAGGLERS. Whatever is still lying on this
        // server, and whatever went to "Unbestimmt" before the find had a
        // name, belongs over there — and the person is looking at exactly
        // this find right now. Bounded to two, because this answer must not
        // become slow; the rest goes on the next opening.
        require_once __DIR__ . '/../../includes/photo_cloud.php';
        photos_to_cloud($pdo, $userId, $id, 2);
        // And the recordings of this find, if its name gave it another
        // folder since (28.09.2026) — before they are listed below, so the
        // list shows where they lie now.
        recordings_refile($pdo, $userId, $id, 2);

        $files = cloud_files_for_point($pdo, $userId, $id);
        // THE PREVIEW ADDRESS IS OUR OWN, and it has to be — pCloud makes its
        // links out to the IP that asked for them, so a link fetched here
        // is useless to the browser. pages/cloud_thumb.php fetches the
        // picture where the link is valid and passes the bytes on.
        //
        // It also takes the provider out of this list entirely. One link per
        // file used to be fetched on every opening of a point, before
        // anything appeared on screen; now the picture is fetched when the
        // browser asks for it, and a browser that already has it asks not
        // at all.
        foreach ($files as &$file) {
            // „c=1": the previews with their colour profile (pages/cloud_thumb.php says why).
            $file['preview'] = url('pages/cloud_thumb.php') . '?id=' . (int) $file['id'] . '&c=1';

            // THE FOLDER, ASKED FOR ONCE AND THEN KEPT. The browser makes a
            // link out of it that opens the recording where it lies, among
            // the other shots of the same find. It is written down at upload
            // time; a row from before that, or one whose file was moved in
            // the storage itself, has none — and then a single question to
            // the provider settles it for good.
            // Own rows only: the folder is looked up in the CALLER's cloud —
            // the one the recording lies in, by its row — and a foreign row
            // lies in somebody else's.
            $known = (string) ($file['folderid'] ?? '');
            if (($known === '' || $known === '0') && $file['mine'] === true) {
                $at = cloud_storage_for($pdo, $userId, (string) ($file['provider'] ?? '') ?: 'pcloud');
                try {
                    $parent = $at === null ? '' : (string) (storage_file_meta($at, (string) $file['fileid'])['parent'] ?? '');
                } catch (RuntimeException $e) {
                    $parent = '';
                }
                if ($parent !== '' && $parent !== '0') {
                    $pdo->prepare('UPDATE map_cloud_files SET folderid = ? WHERE id = ? AND user_id = ?')
                        ->execute([$parent, (int) $file['id'], $userId]);
                    $file['folderid'] = $parent;
                }
            }
        }
        unset($file);

        // WHETHER ANYTHING CAN BE FILED AT ALL IS DECIDED HERE, not in the
        // browser. What counts as a determined species is a rule with several
        // sources — a second copy of it in JavaScript would drift apart, and
        // the first attempt did: it looked at 'extra', which carries the
        // record fields, while the species sit in 'extra_species', which the
        // point does not even send to the browser. The button then never
        // appeared.
        /* THE FIND'S OTHER FILES ride along (05.10.2026, point_files.php) —
           the caller's own on his own find, the sub-tab „Dateien" draws
           them; one request opens both. Absent while migration 0153 is
           not applied, so the page knows not to offer the tab. */
        require_once __DIR__ . '/../../includes/point_files.php';
        $documents = point_files_ready($pdo) && (int) $point['user_id'] === $userId
            ? point_files_list($pdo, $userId, $id) : null;
        json_response([
            'files'  => $files,
            // Nothing to work out any more: a point that is on the server can
            // take recordings. Where they go is the server's business, and
            // for a nameless find that is the catch-all folder.
            'filable' => true,
        ] + ($documents === null ? [] : ['documents' => $documents]));
        break;

    // Away with it — in the cloud and here.
    //
    // BOTH, AND IN THIS ORDER. Only our row would leave the file lying in
    // the storage where nobody looks for it any more; only the file would
    // leave a tile pointing at nothing. And should the second step fail,
    // the row can be removed again later, whereas a file deleted without a
    // record of it is simply gone.
    //
    // MEASURED (19.08.2026): pCloud answers deletefile with
    // isdeleted:true and keeps the file in its trash — it is still there
    // with showdeleted. That is what the question in the browser says, and
    // it is the difference between "recoverable" and "gone".
    /* The calibration and measures of a cloud recording (migrations
       0090/0092). Body: file (the map_cloud_files row) plus scale and/or
       measures — the same shapes photos.php PATCH takes, through the same
       gates, and like there ONLY THE KEYS THAT ARE PRESENT are touched.
       NO provider call for the picture itself: scale and measures are OUR
       notes about it, the picture stays untouched — which is the whole
       architecture of the measuring tool. */
    case 'scale':
        $fileRow = (int) ($input['file'] ?? 0);
        if ($fileRow <= 0) {
            json_error(t('Keine Aufnahme angegeben.'));
        }
        $hasScale    = array_key_exists('scale', $input);
        $hasMeasures = array_key_exists('measures', $input);
        $hasTitle    = array_key_exists('title', $input);
        $hasMedia    = array_key_exists('media', $input);
        $hasDevelop  = array_key_exists('develop', $input);
        /* THE AUTHOR, changed later (08.11.2026) — by the one who uploaded
           it and nobody else: a picture is its maker's, and a share's
           measure right is no right to say who made it. Empty gives the
           uploader back. Answered on its own; it touches nothing else. */
        if (array_key_exists('taken_by', $input)) {
            if (!column_exists($pdo, 'map_cloud_files', 'taken_by')) {
                json_error(t('Der Urheber braucht die Migration 0161 — sie ist noch nicht eingespielt.'), 503);
            }
            $takenBy = mb_substr(trim((string) $input['taken_by']), 0, 120);
            $stmt = $pdo->prepare('UPDATE map_cloud_files SET taken_by = ? WHERE id = ? AND user_id = ? AND point_id = ?');
            $stmt->execute([$takenBy === '' ? null : $takenBy, $fileRow, $userId, $id]);
            $check = $pdo->prepare('SELECT 1 FROM map_cloud_files WHERE id = ? AND user_id = ? AND point_id = ?');
            $check->execute([$fileRow, $userId, $id]);
            if ($check->fetchColumn() === false) {
                json_error(t('Den Urheber kann nur ändern, wer die Aufnahme hochgeladen hat.'), 403);
            }
            json_response(['file' => $fileRow, 'taken_by' => $takenBy === '' ? null : $takenBy]);
        }
        if (!$hasScale && !$hasMeasures && !$hasTitle && !$hasMedia && !$hasDevelop) {
            json_error(t('Weder Eichung noch Messungen noch Titel angegeben.'));
        }
        /* THE MEDIUM, quietly skipped before its migration (0187): the
           microscope sends it with every still, and a picture must not be
           refused its name and scale because the column is not there yet. */
        $hasMedia = $hasMedia && column_exists($pdo, 'map_cloud_files', 'media');
        $media = $hasMedia ? photo_media_clean($input['media']) : null;
        // The development (0190), quietly skipped before its migration, as the medium.
        $hasDevelop = $hasDevelop && column_exists($pdo, 'map_cloud_files', 'develop');
        $develop = $hasDevelop ? photo_develop_clean($input['develop']) : null;
        // The other edits (0192), only beside the valid one — as at the voucher photos.
        $versions = $hasDevelop && array_key_exists('develop_versions', $input)
            && column_exists($pdo, 'map_cloud_files', 'develop_versions')
            ? photo_develop_versions_clean($input['develop_versions']) : false;
        if ($hasDevelop && !photo_develop_fits($pdo, 'map_cloud_files', $develop)) {
            json_error(t('So viele ausgebesserte Stellen passen erst nach der Migration 0193.'), 422);
        }
        // The patches of „Entfernen" ride with it, as at the voucher photos (api/v1/photos.php).
        $patches = [];
        if ($hasDevelop && array_key_exists('patches', $input)) {
            require_once __DIR__ . '/../../includes/develop_patches.php';
            try {
                $patches = develop_patches_decode($input['patches'], $develop, $versions ?: null);
            } catch (InvalidArgumentException $e) {
                json_error($e->getMessage());
            }
        }
        if ($hasTitle && !column_exists($pdo, 'map_cloud_files', 'title')) {
            json_error(t('Der Bildtitel braucht die Migration 0097 — sie ist noch nicht eingespielt.'), 503);
        }
        $title = $hasTitle ? photo_title_clean($input['title']) : null;
        if ($hasScale && !column_exists($pdo, 'map_cloud_files', 'scale')) {
            json_error(t('Der Maßstab für Aufnahmen braucht die Migration 0090 — sie ist noch nicht eingespielt.'), 503);
        }
        if ($hasMeasures && !column_exists($pdo, 'map_cloud_files', 'measures')) {
            json_error(t('Die Messungen brauchen die Migration 0092 — sie ist noch nicht eingespielt.'), 503);
        }

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

        /* No preset is minted here any more — see api/v1/photos.php,
           same reason (26.09.2026). In the PHOTOGRAPHER's name when it is
           somebody else's recording and the share grants `measure`. */
        $actor = cloud_file_actor($pdo, $userId, $id, $fileRow, 'measure');
        /* THE PHOTOGRAPHER'S EDITS ARE HIS ALONE (11.11.2026):
           `measure` through a share reaches the scale, the measures and
           the name — the edits of somebody else's recording are laid
           beside it (api/v1/picture_edits.php), never into it. */
        if ($hasDevelop && $actor !== $userId) {
            json_error(t('Die Bearbeitungen eines Bildes ändert nur, wer es aufgenommen hat.'), 403);
        }
        /* The patches into the recording's cloud FIRST, and what the
           develop named before remembered — the same two moves as at the
           voucher photos, for the same reasons. */
        $prior = null;
        if ($hasDevelop) {
            $stmt = $pdo->prepare('SELECT * FROM map_cloud_files WHERE id = ? AND user_id = ? AND point_id = ?');
            $stmt->execute([$fileRow, $actor, $id]);
            $prior = $stmt->fetch() ?: null;
        }
        if ($patches !== [] && $prior !== null) {
            $patchCloud = develop_patches_cloud($pdo, 'cloud', $prior);
            if ($patchCloud === null) {
                json_error(t('Die Cloud des Bildes ist gerade nicht erreichbar.'), 502);
            }
            try {
                develop_patches_store($patchCloud, (string) $prior['fileid'], $patches);
            } catch (RuntimeException $e) {
                error_log('cloud.php patches (' . $fileRow . '): ' . $e->getMessage());
                json_error(t('Die entfernten Stellen ließen sich nicht in der Cloud speichern.'), 502);
            }
        }
        if ($hasScale && !cloud_file_scale_store($pdo, $actor, $fileRow, $scale)) {
            json_error(t('Aufnahme nicht gefunden.'), 404);
        }
        if ($hasMeasures && !cloud_file_measures_store($pdo, $actor, $fileRow, $measures)) {
            json_error(t('Aufnahme nicht gefunden.'), 404);
        }
        if ($hasTitle && !cloud_file_title_store($pdo, $actor, $fileRow, $title)) {
            json_error(t('Aufnahme nicht gefunden.'), 404);
        }
        if ($hasMedia && !cloud_file_media_store($pdo, $actor, $fileRow, $media)) {
            json_error(t('Aufnahme nicht gefunden.'), 404);
        }
        if ($hasDevelop && !cloud_file_develop_store($pdo, $actor, $fileRow, $develop, $versions)) {
            json_error(t('Aufnahme nicht gefunden.'), 404);
        }

        /* The safety copy beside the recording — same move as at the
           voucher photos (api/v1/photos.php PATCH), same best effort. The
           row is read back so the sidecar always carries BOTH halves,
           whichever one this call happened to change. It lies in the
           photographer's cloud, beside his recording. */
        $stmt = $pdo->prepare('SELECT * FROM map_cloud_files WHERE id = ? AND user_id = ?');
        $stmt->execute([$fileRow, $actor]);
        $fresh = $stmt->fetch();
        $freshScale    = $fresh ? photo_scale_read($fresh['scale'] ?? null) : $scale;
        $freshMeasures = $fresh ? photo_measures_read($fresh['measures'] ?? null) : ($measures ?? []);
        if ($fresh && (string) $fresh['fileid'] !== '') {
            require_once __DIR__ . '/../../includes/photo_cloud.php';
            measure_sidecar_store($pdo, $actor, (string) $fresh['provider'], (string) $fresh['fileid'],
                $freshScale, $freshMeasures,
                // The original's dimensions, when the row knows them
                // (0095) — the sidecar's image block says so too.
                (int) ($fresh['width'] ?? 0) ?: null,
                (int) ($fresh['height'] ?? 0) ?: null,
                photo_title_clean($fresh['title'] ?? null),
                $fresh['original_name'] ?? null,
                photo_media_read($fresh['media'] ?? null),
                photo_develop_read($fresh['develop'] ?? null));
        }
        if ($prior !== null && $fresh) {
            require_once __DIR__ . '/../../includes/develop_patches.php';
            // Named by no edit any more — the valid one, the others (0192), nor anybody else's (0194).
            $gone = array_values(array_diff(develop_row_patch_ids($prior), develop_row_patch_ids($fresh),
                develop_foreign_patch_ids($pdo, 'cloud', $fileRow)));
            if ($gone !== [] && (string) $fresh['fileid'] !== '') {
                $patchCloud ??= develop_patches_cloud($pdo, 'cloud', $fresh);
                if ($patchCloud !== null) {
                    develop_patches_gone($patchCloud, (string) $fresh['fileid'], $gone);
                }
            }
        }

        json_response([
            'file'     => $fileRow,
            'scale'    => $freshScale,
            'measures' => $freshMeasures,
            'title'    => $fresh ? photo_title_clean($fresh['title'] ?? null) : $title,
            'media'    => $fresh ? photo_media_read($fresh['media'] ?? null) : $media,
            'develop'  => $fresh ? photo_develop_read($fresh['develop'] ?? null) : $develop,
            'develop_versions' => $fresh ? photo_develop_versions_read($fresh['develop_versions'] ?? null) : ($versions ?: null),
        ]);

    case 'remove':
        $fileRow = (int) ($_POST['file'] ?? 0);
        if ($fileRow <= 0) {
            json_error(t('Keine Aufnahme angegeben.'));
        }

        /* SOMEBODY ELSE'S RECORDING with the share's `delete_photos`
           goes in the PHOTOGRAPHER's name, from HIS cloud — into his
           provider's trash where it has one (pictures are pictures: the
           voucher photos have had this door since the shares). */
        $actor = cloud_file_actor($pdo, $userId, $id, $fileRow, 'delete_photos');
        $stmt = $pdo->prepare(
            'SELECT provider, fileid FROM map_cloud_files
              WHERE id = ? AND user_id = ? AND point_id = ? LIMIT 1'
        );
        $stmt->execute([$fileRow, $actor, $id]);
        $row = $stmt->fetch();
        if ($row === false || (string) $row['fileid'] === '') {
            json_error(t('Aufnahme nicht gefunden.'), 404);
        }
        // Deleted where it LIES, by the provider on its row.
        $at = cloud_storage_for($pdo, $actor, (string) $row['provider'] ?: 'pcloud');
        if ($at === null) {
            json_error(t('Keine Cloud verbunden.'), 409);
        }

        /* The sidecar goes first, like at the voucher photos
           (photo_cloud_forget): finding it needs the recording's name, and
           once the recording is gone nobody can ask for it — it used to
           stay behind alone. */
        require_once __DIR__ . '/../../includes/photo_cloud.php';
        measure_sidecar_store($pdo, $actor, (string) $row['provider'] ?: 'pcloud', (string) $row['fileid'],
            null, [], null, null);

        try {
            // ALREADY GONE IS NOT A FAILURE — the seam counts it as done.
            // Whoever removed the file in the cloud itself would otherwise
            // never get rid of the tile here. Anything else stops, so
            // nobody believes a deletion that did not happen.
            storage_picture_delete($pdo, $actor, $at, (string) $row['fileid']);
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 502);
        }

        /* „DAS WIRD DANN BEI ALLEN MIT AUS DER CLOUD GELÖSCHT"
           (11.09.2026). It cuts against his own rule that work, once done, is
           never lost — and he chose it anyway, for two reasons he named: the
           photographer is the picture's real owner and holds the rights to
           it; and on a shared find the TIDYING has to run in sync, or every
           collaborator of an excursion would have to weed out the guest's
           dozens of pictures himself, of which one or two are worth keeping.
           The worker removes the copies' files, then their rows. */
        cloud_copies_retire($pdo, 'cloud', $fileRow);
        $pdo->prepare('DELETE FROM map_cloud_files WHERE id = ? AND user_id = ?')
            ->execute([$fileRow, $actor]);
        // The friends' edits of it go with it (migration 0194).
        require_once __DIR__ . '/../../includes/picture_edits.php';
        picture_edits_forget($pdo, 'cloud', $fileRow);
        // And its kept preview (includes/thumbs.php).
        require_once __DIR__ . '/../../includes/thumbs.php';
        thumb_forget($actor, 'cloud', $fileRow);
        list_share_picture_removed_note($pdo, $userId, $id, $actor);
        // The pictures behind it move up a number (cloud_names.php).
        require_once __DIR__ . '/../../includes/cloud_names.php';
        cloud_names_touch($pdo, $id);

        // The mark may just have fallen — same nudge as at 'record'.
        photo_point_touched($pdo, $id);

        json_response(['files' => cloud_files_for_point($pdo, $userId, $id)]);
        break;

    default:
        json_error(t('Unbekannte Aktion.'));
}
