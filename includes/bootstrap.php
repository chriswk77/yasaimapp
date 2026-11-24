<?php
/**
 * Common entry point. Every page and every API endpoint includes this file
 * first. Afterwards these are available:
 *
 *   $pdo          database connection (PDO, exceptions, no emulation)
 *   $config       the loaded configuration as an array
 *   PROJECT_PATH  absolute path to the project directory
 *   BASE_URL      URL prefix of the application, always with a trailing slash
 */

declare(strict_types=1);

define('PROJECT_PATH', dirname(__DIR__));

// ---------------------------------------------------------------------------
// Load the configuration
// ---------------------------------------------------------------------------

$configLocations = array_filter([
    getenv('MAP_CONFIG') ?: null,
    dirname(PROJECT_PATH) . '/map-config.php',
    PROJECT_PATH . '/config.php',
]);

$config = null;
foreach ($configLocations as $location) {
    if (is_file($location)) {
        $config = require $location;
        break;
    }
}

if (!is_array($config)) {
    http_response_code(500);
    exit('Keine Konfiguration gefunden. Bitte config.sample.php nach config.php kopieren und ausfüllen.');
}

/* THE OLD KEYS KEEP WORKING. config.php is not part of the deployment —
   it lies on each server, edited by hand, and the code always goes live
   BEFORE anyone touches it (the same law as with the migrations). A
   config that still says 'umgebung' and 'basis_url' must therefore keep
   carrying, indefinitely; the sample teaches the new names. The old VALUE
   'lokal' works through the comparison against 'live' without any help. */
$config += ['environment' => $config['umgebung'] ?? 'live',
            'base_url'    => $config['basis_url'] ?? '/'];

define('IS_LOCAL', $config['environment'] !== 'live');
define('BASE_URL', rtrim((string) $config['base_url'], '/') . '/');

/* TRANSITION ALIASES for the German constant names. The deployment copies
   file by file; while it runs, an already-copied consumer can still say
   the old name against this new bootstrap. Deletable after the next
   deployment has settled. */
define('IST_LOKAL', IS_LOCAL);
define('BASIS_URL', BASE_URL);
define('PROJEKT_PFAD', PROJECT_PATH);

/* THE DEFAULT APPEARANCE, and it stands here because three places need it
   and none may guess it. The stylesheet knows the default only as "no
   data-theme on <html>" — the values simply sit in :root. The JavaScript
   knows it only by name, and it needs the name three times: the preamble
   of index.php and of page.php has to decide before the first paint
   whether to set the attribute at all, and the settings page has to know
   which tile to tick when nothing is stored.

   Before, the names stood written out at all three sites. That is the same
   shape as every silent breakage of the past days — one site moves, the
   others do not, and nothing complains. */
define('THEME_DEFAULT',  'petrol');
define('ACCENT_DEFAULT', 'lime');

// FOTO_PFAD NO LONGER EXISTS. The storage location once sat in the
// configuration and was thus adjustable — before there were user directories
// and quotas. Since then photos live under data/users/<id>/photos/, and
// mandatorily so: the quota counts this directory, and a second, adjustable
// location beside it would mean the accounting no longer adds up.
// Where things go is now stated in a single place, in
// includes/user_store.php. An existing 'foto_pfad' in an old
// config.php is simply ignored from now on.

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------

// Locally everything visible, live only into the log. An error message with
// file paths and queries does not belong on a public page.
error_reporting(E_ALL);
ini_set('display_errors', IS_LOCAL ? '1' : '0');
ini_set('log_errors', '1');

date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------

$db = $config['db'] ?? [];

// Via the socket, if one is given, otherwise via host and port.
// The socket matters locally: MAMP puts it somewhere other than where the
// command-line PHP expects it — without this setting maintenance scripts do
// not run, although the application works in the browser.
if (!empty($db['socket'])) {
    $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $db['socket'], $db['name'] ?? '');
} else {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $db['host'] ?? 'localhost',
        (int) ($db['port'] ?? 3306),
        $db['name'] ?? ''
    );
}

try {
    $pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('Karte: Datenbankverbindung fehlgeschlagen — ' . $e->getMessage());
    http_response_code(500);
    exit(IS_LOCAL
        ? 'Datenbankverbindung fehlgeschlagen: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES)
        : 'Datenbankverbindung fehlgeschlagen.');
}

/**
 * When the database limps behind the program, say so too.
 *
 * This deployment ships code on every push, but migrations by hand. Between
 * the two inevitably lies a window in which the code expects a column or
 * table that does not exist yet. Without this catch, that ends in a blank
 * 500: no page, no message, no hint what to do — you hunt for the bug in the
 * program, although there is only a button to press.
 *
 * 42S02 is "table missing", 42S22 "column missing". Both mean the same thing
 * here: a migration is missing.
 */
set_exception_handler(static function (Throwable $e): void {
    $state  = $e instanceof PDOException ? ($e->errorInfo[0] ?? '') : '';
    $schema = in_array($state, ['42S02', '42S22'], true);

    error_log('Karte: ' . $e->getMessage());
    http_response_code(500);
    /* The visitor's language — i18n.php comes in further down this file,
       and an error before that line still has to say something. */
    $say = static fn(string $text): string => function_exists('t') ? t($text) : $text;

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e . "\n");
        return;
    }

    /*
     * The API is answered with JSON, not with an HTML page.
     *
     * Before, it got this very page — and the application stumbled over a
     * response that was not JSON, with an error that no longer had anything
     * to do with the cause. In the area overview, "wird geholt …" then stood
     * there silently: no hint, no message, and the only useful piece of
     * information — "a migration is missing" — was stuck in an HTML page
     * nobody got to see.
     */
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => $schema
                ? $say('Die Datenbank ist nicht auf dem Stand des Programms — es fehlt eine Migration.')
                : $say('Es ist ein Serverfehler aufgetreten.'),
            'migration_missing' => $schema,
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    header('Content-Type: text/html; charset=utf-8');

    if ($schema) {
        $target = htmlspecialchars(BASE_URL . 'admin/migrations.php', ENT_QUOTES);
        echo '<!DOCTYPE html><meta charset="utf-8">'
           . '<title>Migration ausstehend</title>'
           . '<body style="font:16px/1.6 system-ui,sans-serif;background:#14181c;color:#e6ebf0;padding:2rem">'
           . '<h1 style="font-size:1.3rem">Die Datenbank ist nicht auf dem Stand des Programms</h1>'
           . '<p>Es fehlt eine Migration. Unter <a style="color:#9cd67a" href="' . $target . '">'
           . 'Verwaltung → Migrationen</a> einspielen, dann geht es weiter.</p>';
        if (IS_LOCAL) {
            echo '<pre style="color:#92a0ae;white-space:pre-wrap">'
               . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</pre>';
        }
        echo '</body>';
        return;
    }

    echo IS_LOCAL
        ? '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES) . '</pre>'
        : htmlspecialchars($say('Es ist ein Fehler aufgetreten.'), ENT_QUOTES);
});

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    /* STRICT MODE (security review, 23.09.2026): PHP adopts only session
       ids it issued itself, so a planted id cannot be ridden into a login.
       The regeneration on login already closed that door; this bolts the
       frame too. */
    ini_set('session.use_strict_mode', '1');
    /* HTTPS ONLY, REMEMBERED BY THE BROWSER — live only: the local MAMP
       speaks plain http, and the header there would lock this machine out
       of its own server for a year. */
    if (!IS_LOCAL && !headers_sent()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL,
        'httponly' => true,
        'secure'   => !IS_LOCAL,
        'samesite' => 'Lax',
    ]);
    session_name('kartenapp');
    session_start();
}

require_once __DIR__ . '/helpers.php';
// The icon library belongs to the base stock like h() and url(): every page
// that has a header needs the map sheet, and every list with a delete
// button the trash can.
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/auth.php';
// What is not part of the core hangs itself in through here — every page and
// every API call may ask a module something (modules.php names the hooks).
require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/i18n.php';
