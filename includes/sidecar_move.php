<?php
/**
 * THE OLD SIDECARS DOWN INTO „BEARBEITUNG", once (10.11.2026).
 *
 * Until that day every measured or edited picture had its sidecar beside
 * it — „… 01 Hauptmotiv.jpg.messwerte.json" between the pictures of the
 * find. Since then they are written one storey down, into the folder's
 * „Bearbeitung" (SIDECAR_FOLDER, cloud.php). This job carries the ones
 * written before: every folder that holds a picture of ours is listed once,
 * and each sidecar found beside its picture goes down.
 *
 *   - Its picture is there and „Bearbeitung" has no sidecar of that name:
 *     it MOVES down, unchanged.
 *   - „Bearbeitung" has one already: that one was written after
 *     10.11.2026 and is newer — the old one is DELETED, not moved (a move
 *     would replace the newer at pCloud, whose renamefile overwrites a
 *     namesake without a word).
 *   - Its picture is not in the folder any more: a stray, deleted — the
 *     same rule storage_prune_empty has followed since 04.10.2026.
 *
 * ONCE, WITH A CURSOR. The folders are walked in a fixed order (user,
 * cloud, folder id) and the last one done is kept in a small file
 * (SIDECAR_MOVE_STATE); when the walk has passed the last folder it says
 * „done" there and every later call is a file read. New sidecars never
 * need it — they go into „Bearbeitung" from the start. A folder that fails
 * three times is passed by and logged, so one broken folder cannot hold up
 * everybody behind it; the paths that delete or move a sidecar still look
 * in both places (storage_sidecars_of), so a left-over is never lost track
 * of. A cloud that is not connected is passed by the same way.
 *
 * NO BUTTON IN THE ADMINISTRATION, unlike the other jobs: it runs once
 * after the deployment and has nothing to do afterwards. The cron
 * (every_5_minutes, cron_job_sidecar_move) is its only door; the CLI call
 * of that bundle shows its line while it works.
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';
require_once __DIR__ . '/photos.php';

/** Where the walk keeps its place — rebuilt harmlessly if it is ever lost. */
const SIDECAR_MOVE_STATE = PROJECT_PATH . '/data/sidecar_move.json';
/** How often one folder may fail before the walk passes it by. */
const SIDECAR_MOVE_TRIES = 3;

/** @return array{done: bool, after: string, failures: array<string, int>} */
function sidecar_move_state(): array
{
    $raw = is_file(SIDECAR_MOVE_STATE) ? json_decode((string) file_get_contents(SIDECAR_MOVE_STATE), true) : null;
    return [
        'done'     => (bool) ($raw['done'] ?? false),
        'after'    => (string) ($raw['after'] ?? ''),
        'failures' => is_array($raw['failures'] ?? null) ? $raw['failures'] : [],
    ];
}

function sidecar_move_state_save(array $state): void
{
    @file_put_contents(SIDECAR_MOVE_STATE, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Every folder that holds a picture of ours, as sortable keys
 * „<user 10 digits>|<provider>|<folder id>" — voucher photos moved into a
 * cloud and recordings. Copies carry no sidecar (their measures live on the
 * origin), so their folders are not walked.
 *
 * @return list<string>
 */
function sidecar_move_folders(PDO $pdo): array
{
    $keys = [];
    $sets = [];
    if (table_exists($pdo, 'map_cloud_files')) {
        $sets[] = "SELECT DISTINCT user_id, provider, folderid AS folder FROM map_cloud_files
                    WHERE fileid <> '' AND folderid IS NOT NULL AND folderid <> ''";
    }
    if (photos_ready($pdo) && column_exists($pdo, 'map_photos', 'cloud_folderid')) {
        $provider = column_exists($pdo, 'map_photos', 'cloud_provider') ? "COALESCE(NULLIF(cloud_provider, ''), 'pcloud')" : "'pcloud'";
        $sets[] = "SELECT DISTINCT user_id, $provider AS provider, cloud_folderid AS folder FROM map_photos
                    WHERE cloud_fileid IS NOT NULL AND cloud_fileid <> ''
                      AND cloud_folderid IS NOT NULL AND cloud_folderid <> ''";
    }
    foreach ($sets as $sql) {
        foreach ($pdo->query($sql) as $r) {
            $keys[sprintf('%010d', (int) $r['user_id']) . '|' . $r['provider'] . '|' . $r['folder']] = true;
        }
    }
    $keys = array_keys($keys);
    sort($keys, SORT_STRING);
    return $keys;
}

/**
 * One folder: its old sidecars down into „Bearbeitung".
 *
 * @return int how many sidecars were moved or dropped
 */
function sidecar_move_folder(array $s, string $folder): int
{
    $files = storage_folder_files($s, $folder);
    $suffix = measure_sidecar_name('');
    $found = [];
    foreach ($files as $name => $f) {
        if (str_ends_with((string) $name, $suffix) && strlen((string) $name) > strlen($suffix)) {
            $found[(string) $name] = $f;
        }
    }
    if ($found === []) {
        return 0;
    }
    $into = null;
    $there = null;
    $done = 0;
    foreach ($found as $name => $f) {
        $picture = substr($name, 0, -strlen($suffix));
        if (!isset($files[$picture])) {
            storage_file_delete($s, $f['fileid']);   // a stray: its picture is gone
            $done++;
            continue;
        }
        $into ??= storage_sidecar_folder($s, $folder, true);
        $there ??= storage_folder_files($s, $into);
        if (isset($there[$name])) {
            storage_file_delete($s, $f['fileid']);   // a newer one is down there already
        } else {
            storage_file_move($s, $f['fileid'], $into);
        }
        $done++;
    }
    return $done;
}

/**
 * The walk, as far as the time allows.
 *
 * @param ?callable $open (int $user, string $provider): ?array — the tests' storage
 * @return array{moved: int, folders: int, failed: int, open: int}
 */
function sidecar_move_run(PDO $pdo, float $deadline, ?callable $open = null): array
{
    $sum = ['moved' => 0, 'folders' => 0, 'failed' => 0, 'open' => 0];
    $state = sidecar_move_state();
    if ($state['done']) {
        return $sum;
    }
    $keys = array_values(array_filter(sidecar_move_folders($pdo),
        static fn(string $k): bool => strcmp($k, $state['after']) > 0));
    $storages = [];
    foreach ($keys as $i => $key) {
        if (microtime(true) >= $deadline) {
            $sum['open'] = count($keys) - $i;
            sidecar_move_state_save($state);
            return $sum;
        }
        [$user, $provider, $folder] = explode('|', $key, 3);
        $storeKey = $user . '|' . $provider;
        try {
            if (!array_key_exists($storeKey, $storages)) {
                $storages[$storeKey] = $open !== null ? $open((int) $user, $provider)
                    : cloud_storage_for($pdo, (int) $user, $provider);
            }
            $s = $storages[$storeKey];
            if ($s !== null && $folder !== '0') {
                $sum['moved'] += sidecar_move_folder($s, $folder);
            }
            $sum['folders']++;
        } catch (Throwable $e) {
            $tries = (int) ($state['failures'][$key] ?? 0) + 1;
            error_log('sidecar_move (' . $key . ', try ' . $tries . '): ' . get_class($e) . ': ' . $e->getMessage());
            $sum['failed']++;
            if ($tries < SIDECAR_MOVE_TRIES) {
                $state['failures'][$key] = $tries;
                sidecar_move_state_save($state);
                $sum['open'] = count($keys) - $i;
                return $sum;   // stop here and try this folder again next call
            }
            unset($state['failures'][$key]);
        }
        $state['after'] = $key;
    }
    $state['done'] = true;
    $state['failures'] = [];
    sidecar_move_state_save($state);
    return $sum;
}
