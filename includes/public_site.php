<?php
/**
 * THE PUBLIC FIND BOOK ON wkpilz.de, SEEN FROM HERE.
 *
 * The front page of the domain is a project of its own — repository
 * chriswk77/wkpilz, beside this one in htdocs, at the domain root on the
 * server with YASAIMAPP as its folder map/. It shows the maintainer's finds
 * that have a plate, and it never reads this database while a visitor
 * looks: its refresh copies what is public into its own media/ folder.
 *
 * THIS FILE IS THE SECOND DOOR TO THAT REFRESH, AND THE ONE THAT RUNS BY
 * ITSELF: the hourly cron (cron_job_public_site in cronjobs/_jobs.php)
 * calls it, so a plate drawn today stands on the site within the hour
 * (22.11.2026: „den Button für die Veröffentlichung brauche ich eigentlich
 * gar nicht … automatisch in einen Cron-Job"). From 20.11.2026 until then
 * it was a button in the Fundbrowser. The first door is the site's own
 * secret address (wkpilz.de/refresh/?key=…), for a plate wanted at once.
 *
 * WHAT IS PUBLIC IS DECIDED THERE, NOT HERE. This file only finds the
 * site, asks it whose it is and hands it the connection; the rules — plates
 * only, sure identifications only, what the site itself adds of the place
 * no finer than the Messtischblatt — live in the site's inc/sync.php, once.
 *
 * NO SITE, NO BUTTON: on a server without the site (a fresh install, a
 * test machine) public_site_root() finds nothing and the cron job stays
 * silent.
 */

declare(strict_types=1);

/**
 * Where the site lies: on the server the domain root above this project,
 * on the maintainer's machine its neighbour folder wkpilz. PUBLIC_SITE_ROOT
 * in the environment points elsewhere (a worktree under test).
 */
function public_site_root(): ?string
{
    static $root = false;
    if ($root === false) {
        $root = null;
        $candidates = array_filter([
            getenv('PUBLIC_SITE_ROOT') ?: null,
            dirname(PROJECT_PATH),
            dirname(PROJECT_PATH) . '/wkpilz',
        ]);
        foreach ($candidates as $dir) {
            if (is_file($dir . '/inc/config.php') && is_file($dir . '/inc/sync.php')) {
                $root = $dir;
                break;
            }
        }
    }
    return $root;
}

/**
 * The site's settings, loaded once. YASAIMAPP says first where IT lies
 * (MAP_ROOT), so both projects load the very same include files — the
 * site would otherwise guess its neighbour, and in a worktree that is a
 * different copy whose functions are declared a second time.
 */
function public_site_load(): bool
{
    $root = public_site_root();
    if ($root === null) {
        return false;
    }
    if (!defined('MAP_ROOT')) {
        define('MAP_ROOT', PROJECT_PATH);
    }
    require_once $root . '/inc/config.php';
    return defined('OWNER_ID');
}

/**
 * Whether this account owns the site — the one whose finds it shows. Only
 * he sees the plate window's „Veröffentlichen" (plate_publish.php).
 */
function public_site_may(?array $user): bool
{
    return $user !== null && public_site_load() && (int) OWNER_ID === (int) ($user['id'] ?? 0);
}

/** The site's address as the plate window names it („wkpilz.de"), or its name. */
function public_site_label(): string
{
    if (!public_site_load()) {
        return '';
    }
    return defined('SITE_DOMAIN') ? (string) SITE_DOMAIN : (defined('SITE_NAME') ? (string) SITE_NAME : '');
}

/**
 * One slice of the refresh (the site's sync_run): the missing plate pictures
 * as far as $seconds reach, then — when none is missing — the site's
 * finds.json. Null where there is no site.
 *
 * @return array{done: bool, held: bool, changed: bool, made: int, left: int, total: int, finds: int, notes: list<string>, failed: list<string>}|null
 */
function public_site_refresh(PDO $pdo, float $seconds): ?array
{
    if (!public_site_load()) {
        return null;
    }
    require_once public_site_root() . '/inc/sync.php';
    sync_require_map();
    return sync_run($pdo, $seconds);
}
