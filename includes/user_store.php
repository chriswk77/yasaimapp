<?php
/**
 * Each user's own space on the disk.
 *
 * WHAT FOR
 * Photos for find points. Those come later; here stands where they belong
 * and how much somebody may store — so that this is settled before the first
 * file exists. Changing a directory scheme afterwards means moving files
 * whose path already sits in the database.
 *
 * THE LAYOUT
 *
 *   data/users/<id>/photos/
 *
 * English on the disk, German in the code — that is no oversight. Paths are
 * read in error messages, in backups, in the hoster's file manager; there,
 * umlauts and German words are a source of trouble nobody needs. In the
 * code, on the other hand, everything stays as it was.
 *
 * THE ID, NOT THE NAME. Names change, names contain spaces, slashes and
 * umlauts, and two people are allowed to have the same name. The id is a
 * number and stays.
 *
 * NONE OF THIS IS EXPOSED TO THE WEB. data/ has always carried a "Deny from
 * all"; every created directory gets the same block once more, in case the
 * upper rule gets lost in a move. Photos are later delivered via PHP, which
 * first asks who they belong to.
 */

declare(strict_types=1);

/** Where everything lives. Below data/, so outside the web. */
function user_root(): string
{
    return PROJECT_PATH . '/data/users';
}

/** A user's directory. Creates nothing. */
function user_directory(int $userId): string
{
    return user_root() . '/' . $userId;
}

/** Where the photos live. Creates nothing. */
function user_photo_directory(int $userId): string
{
    return user_directory($userId) . '/photos';
}

/**
 * Create the photo directory if it is missing, and return it.
 *
 * Only at the first upload — an account that never stores a photo needs no
 * empty directory. And the block comes along: whoever moves data/ somewhere
 * else one day at least still has the rule right next to it.
 */
function user_photo_directory_create(int $userId): string
{
    $path = user_photo_directory($userId);
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        /* The path goes to the log, never to the user: it is the server's
           inside (error_for_user() in helpers.php). */
        error_log('Karte: Verzeichnis lässt sich nicht anlegen: ' . $path);
        throw new RuntimeException(t('Ein Ordner auf dem Server lässt sich nicht anlegen.'));
    }

    $lock = user_root() . '/.htaccess';
    if (!is_file($lock)) {
        file_put_contents($lock, "Deny from all\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n");
    }

    return $path;
}

/**
 * How many bytes this user occupies.
 *
 * The disk is counted, not the database. That is slower, but it is the
 * truth: a counter in a column drifts apart at some point — an aborted
 * upload, a file deleted by hand, a mistake while cleaning up, and from then
 * on it is never right again. With a few hundred files the recount costs
 * nothing.
 */
function user_used(int $userId): int
{
    $path = user_directory($userId);
    if (!is_dir($path)) {
        return 0;
    }

    $total = 0;
    $walker = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($walker as $file) {
        if ($file->isFile()) {
            $total += $file->getSize();
        }
    }
    return $total;
}

/**
 * What still fits in.
 *
 * NULL means unlimited — and that is the default. A limit nobody has set is
 * no limit; whoever needs one gets it individually.
 *
 * @return int|null Free bytes, or null for unlimited
 */
function user_free(PDO $pdo, int $userId): ?int
{
    $limit = $pdo->prepare('SELECT quota_bytes FROM map_users WHERE id = ?');
    $limit->execute([$userId]);
    $bytes = $limit->fetchColumn();

    if ($bytes === false || $bytes === null) {
        return null;
    }
    return max(0, (int) $bytes - user_used($userId));
}

/**
 * Does a file of this size still fit?
 *
 * The question is asked BEFORE storing. Afterwards it would be too late: a
 * file that bursts the quota and is therefore deleted again has still
 * occupied the space for the moment — and on a full disk that is exactly the
 * moment when something else breaks.
 */
function user_fits(PDO $pdo, int $userId, int $bytes): bool
{
    $free = user_free($pdo, $userId);
    return $free === null || $bytes <= $free;
}

/** For display: "12,3 MB". */
function bytes_readable(?int $bytes): string
{
    if ($bytes === null) {
        return 'unbegrenzt';
    }
    $units = ['B', 'kB', 'MB', 'GB', 'TB'];
    $i = 0;
    $value = (float) $bytes;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return number_format($value, $value < 10 && $i > 0 ? 1 : 0, ',', '.') . ' ' . $units[$i];
}
