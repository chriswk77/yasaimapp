<?php
/**
 * ONE SPELLING OF AN UMLAUT IN A pCLOUD — the names written the other way
 * are rewritten once, and the folders that stand twice because of it are
 * laid together (22.11.2026).
 *
 * WHAT WAS FOUND. The survey of the maintainer's pCloud that day showed
 * names with „ü", „ö", „ä" in two spellings: as one sign (NFC — what the
 * database holds and this code sends) and as the letter with two dots
 * after it (NFD). 266 file names and 25 folder names up there were NFD:
 * everything named before about 20.11.2026. A write test the same day
 * showed that pCloud keeps a name exactly as it is sent and holds the two
 * spellings for DIFFERENT names — a folder „Prüfung" asked for in the one
 * was created beside the folder „Prüfung" in the other. Since this code
 * sent the same bytes all along, pCloud must have turned names into NFD
 * until then and stopped; that is a conclusion from what lies there, not
 * a word from pCloud.
 *
 * WHAT IT DID. Every find folder with an umlaut in its municipality —
 * Krün, Üdersdorf, Glashütten — got a twin the first time a plate or a
 * picture was filed into it afterwards: six pairs within a day, and five
 * plate pages twice in one folder. The two look alike in every listing.
 *
 * THE CODE NOW FINDS BY A COMPARISON KEY (cloud_name_key in cloud.php), so
 * no new twin arises. This job mends what is there:
 *
 *   - A folder or file whose name is not in the spelling we send is
 *     renamed where it lies. Nothing changes that an eye could see.
 *   - Two folders of one name become one: what lies in the others moves
 *     into the one spelt right (else the older), sub-folders are laid
 *     together the same way, the rows that named the emptied folder name
 *     the other, and the emptied folder goes — deletefolder, which refuses
 *     a folder that is not empty.
 *   - Two FILES of one name in one folder: of two sidecars the newer
 *     stays (a sidecar is a copy of the row; one picture, one sidecar); of
 *     two plate pages the one its row points at stays (a page is ours and
 *     is replaced on every save — plate_image_store says „REPLACE,
 *     ALWAYS"); the other goes into the provider's trash. ANYTHING ELSE IS
 *     NEVER DELETED: the second picture becomes „… (2)", as beside any
 *     namesake.
 *
 * NO RENAME ONTO ANOTHER FILE. pCloud's renamefile replaces a namesake
 * without a word (cloud_names.php). The plan is made over ONE listing of
 * the whole tree, and it frees a name before it gives it away; a name is
 * taken when its KEY is taken.
 *
 * THE PLAN IS PURE, THE WORK IS SEPARATE. cloud_spelling_plan() turns a
 * listing into steps and touches nothing — it is what the tests hold, and
 * what was shown to the maintainer as a list before anything ran („vorher
 * bekommst du die Liste"). cloud_spelling_account() applies the steps
 * until its deadline; a run that stops is continued by the next one,
 * which lists again and plans what is still to do — the progress is in
 * the cloud itself, no cursor is kept.
 *
 * ONLY pCLOUD. That is where it was measured. Drive and OneDrive never
 * showed a second spelling (every name there is one this code sent), and
 * S3 has no folders to lay together.
 *
 * ITS DOOR is admin/cloud_survey.php (`what=spelling`), for the one
 * account the survey is for. It is no cron job: a cloud connected from now
 * on gets one spelling from the first name. Should another pCloud turn out
 * to hold names from before, the door is here.
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';

/** The listing is decoded in memory — beyond this the job says so instead of trying. */
const CLOUD_SPELLING_TREE_MAX = 48 * 1024 * 1024;

/**
 * Everything in the app's folder, as pCloud lists it — one recursive call.
 *
 * @return array the folder's metadata, `contents` nested
 * @throws RuntimeException the provider refused, or the tree is too large to hold
 */
function cloud_spelling_tree(array $s): array
{
    $answer = @file_get_contents('https://' . $s['host'] . '/listfolder', false, stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => http_build_query([
                'access_token' => $s['token'],
                'folderid'     => cloud_root_id($s['connection']),
                'recursive'    => 1,
                'timeformat'   => 'timestamp',
            ]),
            'timeout'       => 90,
            'ignore_errors' => true,
        ],
    ]));
    if ($answer === false) {
        throw new RuntimeException(t('pCloud antwortet nicht. Später noch einmal versuchen.'));
    }
    if (strlen($answer) > CLOUD_SPELLING_TREE_MAX) {
        throw new RuntimeException('The folder tree is too large to be read in one piece (' . strlen($answer) . ' bytes).');
    }
    $d = json_decode($answer, true);
    if (!is_array($d) || (int) ($d['result'] ?? -1) !== 0 || !is_array($d['metadata'] ?? null)) {
        throw cloud_refused('pCloud', (string) (int) ($d['result'] ?? -1), (string) ($d['error'] ?? ''));
    }
    return $d['metadata'];
}

/**
 * The file ids some row of this account points at — a file of ours, as
 * opposed to one that merely lies there.
 *
 * @return array<string, true>
 */
function cloud_spelling_referenced(PDO $pdo, int $userId): array
{
    $ids = [];
    $sets = [
        ['map_cloud_files', "SELECT fileid FROM map_cloud_files WHERE user_id = ? AND provider = 'pcloud'"],
        ['map_photos', "SELECT cloud_fileid FROM map_photos WHERE user_id = ? AND cloud_fileid IS NOT NULL"],
        ['map_cloud_copies', "SELECT fileid FROM map_cloud_copies WHERE user_id = ? AND provider = 'pcloud'"],
        ['map_plate_images', "SELECT i.fileid FROM map_plate_images i JOIN map_plates p ON p.id = i.plate_id
                               WHERE p.user_id = ? AND i.provider = 'pcloud'"],
        ['map_point_files', "SELECT fileid FROM map_point_files WHERE user_id = ? AND provider = 'pcloud'"],
        ['map_photo_scales', "SELECT fileid FROM map_photo_scales WHERE user_id = ? AND fileid IS NOT NULL"],
    ];
    foreach ($sets as [$table, $sql]) {
        if (!table_exists($pdo, $table)) {
            continue;
        }
        try {
            $q = $pdo->prepare($sql);
            $q->execute([$userId]);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) {
                if ((string) $id !== '') {
                    $ids[(string) $id] = true;
                }
            }
        } catch (PDOException $e) {
            // A column this installation has not got yet: that table names nothing here.
        }
    }
    return $ids;
}

/** What kind of file a name says it is — the three the job treats differently. */
function cloud_spelling_kind(string $name): string
{
    require_once __DIR__ . '/photos.php';
    $suffix = measure_sidecar_name('');
    if (str_ends_with($name, $suffix) && strlen($name) > strlen($suffix)) {
        return 'sidecar';
    }
    // „<head> 00 Tafel.jpg", „<head> 00 Tafel 2.jpg" — plate_image_name().
    return preg_match('/ 00 Tafel( \d+)?\.jpe?g$/iu', $name) === 1 ? 'plate' : 'other';
}

/** The next name beside a namesake: „X.jpg" → „X (2).jpg", by keys taken. */
function cloud_spelling_free(string $name, array $taken): string
{
    $dot = strrpos($name, '.');
    $stem = $dot === false || $dot === 0 ? $name : substr($name, 0, $dot);
    $ext  = $dot === false || $dot === 0 ? '' : substr($name, $dot);
    for ($k = 2; isset($taken[cloud_name_key($stem . ' (' . $k . ')' . $ext)]); $k++) {
        // the next free number
    }
    return $stem . ' (' . $k . ')' . $ext;
}

/**
 * The steps that bring one tree to one spelling — nothing is touched.
 *
 * @param array $root the app folder's metadata, nested (cloud_spelling_tree)
 * @param array<string, true> $referenced cloud_spelling_referenced()
 * @return list<array{do: string, path: string}> in the order they must run
 */
function cloud_spelling_plan(array $root, array $referenced): array
{
    $steps = [];
    cloud_spelling_folder($root, '', $referenced, $steps);
    return $steps;
}

/** One folder: its sub-folders of one name laid together, its files brought to one spelling, then down. */
function cloud_spelling_folder(array $node, string $path, array $referenced, array &$steps): void
{
    $folders = [];
    $files = [];
    foreach ((array) ($node['contents'] ?? []) as $e) {
        if (!empty($e['isfolder'])) {
            $folders[cloud_name_key((string) $e['name'])][] = $e;
        } else {
            $files[] = $e;
        }
    }

    $kept = [];
    foreach ($folders as $key => $group) {
        $key = (string) $key;
        // The one spelt as we send it leads; among equals the older, which pCloud numbers lower.
        usort($group, static fn(array $a, array $b): int =>
            [$a['name'] !== $key, (int) $a['folderid']] <=> [$b['name'] !== $key, (int) $b['folderid']]);
        $target = array_shift($group);
        foreach ($group as $source) {
            $target = cloud_spelling_merge($source, $target, $path . '/' . $key, $referenced, $steps);
        }
        if ((string) $target['name'] !== $key) {
            $steps[] = ['do' => 'rename_folder', 'path' => $path, 'id' => (string) $target['folderid'], 'to' => $key];
            $target['name'] = $key;
        }
        $kept[] = $target;
    }

    cloud_spelling_files($files, (string) $node['folderid'], $path, $referenced, $steps);

    foreach ($kept as $folder) {
        cloud_spelling_folder($folder, $path . '/' . $folder['name'], $referenced, $steps);
    }
}

/**
 * Lay $source into $target, a folder of the same name beside it. Returns
 * the target as it will be — so the walk that follows sees what arrived.
 */
function cloud_spelling_merge(array $source, array $target, string $path, array $referenced, array &$steps): array
{
    $from = (string) $source['folderid'];
    $to   = (string) $target['folderid'];
    $target['contents'] = (array) ($target['contents'] ?? []);

    foreach ((array) ($source['contents'] ?? []) as $e) {
        $key = cloud_name_key((string) $e['name']);
        // Who holds that name in the target already?
        $at = null;
        foreach ($target['contents'] as $i => $has) {
            if (!empty($has['isfolder']) === !empty($e['isfolder']) && cloud_name_key((string) $has['name']) === $key) {
                $at = $i;
                break;
            }
        }
        if (!empty($e['isfolder'])) {
            if ($at !== null) {
                $target['contents'][$at] = cloud_spelling_merge($e, $target['contents'][$at], $path . '/' . $key, $referenced, $steps);
            } else {
                $steps[] = ['do' => 'move_folder', 'path' => $path, 'id' => (string) $e['folderid'], 'into' => $to, 'name' => (string) $e['name']];
                $target['contents'][] = $e;   // still in its own spelling — the walk renames it where it now lies
            }
            continue;
        }
        if ($at === null) {
            $steps[] = ['do' => 'move_file', 'path' => $path, 'id' => (string) $e['fileid'], 'into' => $to, 'to' => $key, 'from' => (string) $e['name']];
            $target['contents'][] = ['name' => $key] + $e;
            continue;
        }
        // A file of that name on both sides.
        $has  = $target['contents'][$at];
        $kind = cloud_spelling_kind($key);
        $mine = isset($referenced[(string) $e['fileid']]);
        $its  = isset($referenced[(string) $has['fileid']]);
        $newer = (int) ($e['modified'] ?? 0) > (int) ($has['modified'] ?? 0);
        $wins = $kind === 'sidecar' ? $newer : ($kind === 'plate' ? ($mine !== $its ? $mine : $newer) : null);
        if ($wins === true) {
            $steps[] = ['do' => 'delete_file', 'path' => $path, 'id' => (string) $has['fileid'], 'name' => (string) $has['name'], 'why' => $kind];
            $steps[] = ['do' => 'move_file', 'path' => $path, 'id' => (string) $e['fileid'], 'into' => $to, 'to' => $key, 'from' => (string) $e['name']];
            $target['contents'][$at] = ['name' => $key] + $e;
        } elseif ($wins === false) {
            $steps[] = ['do' => 'delete_file', 'path' => $path, 'id' => (string) $e['fileid'], 'name' => (string) $e['name'], 'why' => $kind];
        } else {
            $taken = [];
            foreach ($target['contents'] as $x) {
                if (empty($x['isfolder'])) {
                    $taken[cloud_name_key((string) $x['name'])] = true;
                }
            }
            $free = cloud_spelling_free($key, $taken);
            $steps[] = ['do' => 'move_file', 'path' => $path, 'id' => (string) $e['fileid'], 'into' => $to, 'to' => $free, 'from' => (string) $e['name']];
            $target['contents'][] = ['name' => $free] + $e;
        }
    }

    $steps[] = ['do' => 'rows_folder', 'path' => $path, 'from' => $from, 'into' => $to];
    $steps[] = ['do' => 'delete_folder', 'path' => $path, 'id' => $from, 'name' => (string) $source['name']];
    return $target;
}

/** The files of ONE folder: each name in our spelling, and of two of one name only what the head of this file says. */
function cloud_spelling_files(array $files, string $folder, string $path, array $referenced, array &$steps): void
{
    $groups = [];
    foreach ($files as $f) {
        $groups[cloud_name_key((string) $f['name'])][] = $f;
    }
    $taken = array_fill_keys(array_map('strval', array_keys($groups)), true);

    foreach ($groups as $key => $group) {
        $key  = (string) $key;
        $kind = cloud_spelling_kind($key);
        if (count($group) > 1) {
            // Who stays: the row's own, then — for what is ours to replace — the newer; else the one spelt right, the older.
            usort($group, static function (array $a, array $b) use ($referenced, $kind, $key): int {
                $rank = static fn(array $f): array => [
                    $kind === 'sidecar' ? 0 : (int) !isset($referenced[(string) $f['fileid']]),
                    $kind === 'other' ? (int) ((string) $f['name'] !== $key) : -(int) ($f['modified'] ?? 0),
                    (int) $f['fileid'],
                ];
                return $rank($a) <=> $rank($b);
            });
            $keeper = array_shift($group);
            foreach ($group as $f) {
                if ($kind === 'other') {
                    $free = cloud_spelling_free($key, $taken);
                    $taken[cloud_name_key($free)] = true;
                    $steps[] = ['do' => 'rename_file', 'path' => $path, 'id' => (string) $f['fileid'], 'folder' => $folder, 'to' => $free, 'from' => (string) $f['name']];
                } else {
                    $steps[] = ['do' => 'delete_file', 'path' => $path, 'id' => (string) $f['fileid'], 'name' => (string) $f['name'], 'why' => $kind];
                }
            }
            $group = [$keeper];
        }
        $f = $group[0];
        if ((string) $f['name'] !== $key) {
            $steps[] = ['do' => 'rename_file', 'path' => $path, 'id' => (string) $f['fileid'], 'folder' => $folder, 'to' => $key, 'from' => (string) $f['name']];
        }
    }
}

/**
 * One step, done.
 *
 * @throws RuntimeException the provider refused
 */
function cloud_spelling_apply(PDO $pdo, int $userId, array $s, array $step): void
{
    switch ($step['do']) {
        case 'rename_file':
            storage_file_place($s, $step['id'], $step['folder'], $step['to']);
            cloud_spelling_rows_name($pdo, $userId, $step['id'], $step['to']);
            break;
        case 'move_file':
            storage_file_place($s, $step['id'], $step['into'], $step['to']);
            cloud_spelling_rows_name($pdo, $userId, $step['id'], $step['to']);
            break;
        case 'delete_file':
            storage_file_delete($s, $step['id']);
            break;
        case 'rename_folder':
            pcloud_call($s['host'], 'renamefolder', [
                'access_token' => $s['token'], 'folderid' => (int) $step['id'], 'toname' => $step['to'],
            ]);
            break;
        case 'move_folder':
            storage_folder_move($s, $step['id'], $step['into']);
            break;
        case 'delete_folder':
            // Refused while anything lies in it — then it stays, and the next plan looks at it again.
            storage_folder_delete_empty($s, $step['id']);
            break;
        case 'rows_folder':
            cloud_spelling_rows_folder($pdo, $userId, $step['from'], $step['into']);
            break;
        default:
            throw new LogicException('Unknown step: ' . $step['do']);
    }
}

/** The rows that name a file by its id carry the name it has now. */
function cloud_spelling_rows_name(PDO $pdo, int $userId, string $fileid, string $name): void
{
    $name = mb_substr($name, 0, 255);
    $sets = [
        ['map_cloud_files', "UPDATE map_cloud_files SET name = ? WHERE fileid = ? AND user_id = ? AND provider = 'pcloud'"],
        ['map_photos', "UPDATE map_photos SET cloud_name = ? WHERE cloud_fileid = ? AND user_id = ? AND cloud_name IS NOT NULL"],
        ['map_cloud_copies', "UPDATE map_cloud_copies SET name = ? WHERE fileid = ? AND user_id = ? AND provider = 'pcloud'"],
        ['map_point_files', "UPDATE map_point_files SET name = ? WHERE fileid = ? AND user_id = ? AND provider = 'pcloud'"],
        ['map_plate_images', "UPDATE map_plate_images i JOIN map_plates p ON p.id = i.plate_id SET i.name = ?
                               WHERE i.fileid = ? AND p.user_id = ? AND i.provider = 'pcloud'"],
    ];
    foreach ($sets as [$table, $sql]) {
        if (!table_exists($pdo, $table)) {
            continue;
        }
        try {
            $pdo->prepare($sql)->execute([$name, $fileid, $userId]);
        } catch (PDOException $e) {
            error_log('cloud_spelling rows (' . $table . '): ' . $e->getMessage());
        }
    }
}

/** The rows that named a folder laid into another name the other. */
function cloud_spelling_rows_folder(PDO $pdo, int $userId, string $from, string $into): void
{
    $sets = [
        ['map_cloud_files', "UPDATE map_cloud_files SET folderid = ? WHERE folderid = ? AND user_id = ? AND provider = 'pcloud'"],
        ['map_photos', "UPDATE map_photos SET cloud_folderid = ? WHERE cloud_folderid = ? AND user_id = ?"],
        ['map_cloud_copies', "UPDATE map_cloud_copies SET folderid = ? WHERE folderid = ? AND user_id = ? AND provider = 'pcloud'"],
        ['map_point_files', "UPDATE map_point_files SET folderid = ? WHERE folderid = ? AND user_id = ? AND provider = 'pcloud'"],
        ['map_point_files', "UPDATE map_point_files SET home_folderid = ? WHERE home_folderid = ? AND user_id = ? AND provider = 'pcloud'"],
        ['map_plate_images', "UPDATE map_plate_images i JOIN map_plates p ON p.id = i.plate_id SET i.folderid = ?
                               WHERE i.folderid = ? AND p.user_id = ? AND i.provider = 'pcloud'"],
    ];
    foreach ($sets as [$table, $sql]) {
        if (!table_exists($pdo, $table)) {
            continue;
        }
        try {
            $pdo->prepare($sql)->execute([$into, $from, $userId]);
        } catch (PDOException $e) {
            error_log('cloud_spelling rows (' . $table . '): ' . $e->getMessage());
        }
    }
}

/**
 * One account's pCloud: list, plan, and — unless $dry — work through the
 * plan until the deadline.
 *
 * @param array $s the account's pCloud, open (cloud_storage_for)
 * @return array{steps: list<array>, done: int, failed: list<array{step: array, why: string}>, open: int}
 *         the plan as it stood before this run, how many of its steps were
 *         done, which were refused and why, and how many were not reached
 * @throws RuntimeException the tree could not be read
 */
function cloud_spelling_account(PDO $pdo, int $userId, array $s, float $deadline, bool $dry): array
{
    if (($s['provider'] ?? '') !== 'pcloud') {
        throw new LogicException('Only pCloud has shown two spellings.');
    }
    $steps = cloud_spelling_plan(cloud_spelling_tree($s), cloud_spelling_referenced($pdo, $userId));
    $out = ['steps' => $steps, 'done' => 0, 'failed' => [], 'open' => count($steps)];
    if ($dry) {
        return $out;
    }
    foreach ($steps as $step) {
        if (microtime(true) >= $deadline) {
            break;
        }
        try {
            cloud_spelling_apply($pdo, $userId, $s, $step);
            $out['done']++;
        } catch (RuntimeException $e) {
            // One refusal does not stop the others: every step names its file or folder by id.
            $out['failed'][] = ['step' => $step, 'why' => $e->getMessage()];
            error_log('cloud_spelling (' . $step['do'] . ' ' . ($step['id'] ?? $step['from'] ?? '') . '): ' . $e->getMessage());
        }
        $out['open']--;
    }
    return $out;
}
