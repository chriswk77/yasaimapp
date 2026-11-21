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
 * THIS FILE IS THE SECOND DOOR TO THAT REFRESH (20.11.2026: „als
 * Seitenprojekt … Modul von YASAIMAPP gestalten, so dass ich aus
 * YASAIMAPP selbst aktualisieren kann"). The first is the site's own secret
 * address (wkpilz.de/refresh/?key=…). Here the login of YASAIMAPP is the
 * key: only the account whose finds the site shows (the site's OWNER_ID)
 * sees the button in the Fundbrowser and may call api/v1/public_site.php,
 * which the button calls in the background (assets/js/publicsite.js).
 *
 * WHAT IS PUBLIC IS DECIDED THERE, NOT HERE. This file only finds the
 * site, asks it whose it is and hands it the connection; the rules — plates
 * only, sure identifications only, what the site itself adds of the place
 * no finer than the Messtischblatt — live in the site's inc/sync.php, once.
 *
 * NO SITE, NO BUTTON: on a server without the site (a fresh install, a
 * test machine) public_site_root() finds nothing and every caller stays
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

/** Whether this user may refresh the site — the one whose finds it shows. */
function public_site_may(?array $user): bool
{
    return $user !== null && public_site_load() && (int) OWNER_ID === (int) ($user['id'] ?? 0);
}

/**
 * One slice of the refresh (the site's sync_run: some seconds of work, then
 * an answer). The caller asks again while `done` is false.
 *
 * @return array{done: bool, held: bool, made: int, left: int, total: int, finds: int, notes: list<string>, failed: list<string>}
 */
function public_site_refresh(PDO $pdo): array
{
    if (!public_site_load()) {
        throw new RuntimeException(t('Die öffentliche Seite ist auf diesem Server nicht eingerichtet.'));
    }
    require_once public_site_root() . '/inc/sync.php';
    sync_require_map();
    return sync_run($pdo);
}
