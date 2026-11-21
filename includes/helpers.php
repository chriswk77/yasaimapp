<?php
/**
 * Small tools needed everywhere: escaping output, building URLs, CSRF
 * tokens, JSON responses.
 */

declare(strict_types=1);

/** Escape text for HTML output. Short, because it appears in templates all the time. */
function h(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * The version marker of ONE script or style: its own modification time.
 *
 * PER FILE SINCE 24.09.2026, and the reason is the phone. Until then one
 * number stood on every file — the newest modification time under assets/
 * — so every deployment that touched a single stylesheet renamed all forty
 * files at once, and the service worker, which caches a versioned address
 * for good (sw.js, getAppFile), fetched every one of them again: some
 * 2.4 MB, unpacked until the server learned gzip the same day. Now only
 * what changed gets a new address. The deployment uploads only changed
 * files, so an untouched file keeps its time on the server too.
 *
 * Locally as well — an edit changes the time, which is all the old
 * time()-per-request did, without busting forty files on every load.
 *
 * AND ITS SIZE (10.10.2026, when every page on the maintainer's phone and Mac
 * came without any style right after a deployment). The deployment's
 * sftp writes a file IN PLACE, and a 290 KB stylesheet is written within
 * one second — so a page asked for in that second already named the
 * final time, and the browser fetched a file still empty or half
 * written. The service worker keeps a versioned address for good
 * (sw.js, getAppFile): the broken copy stood under the right name and no
 * reload let go of it. A file only grows while it is written, so with
 * the size in the tag such a half copy lies under an address no later
 * page names again.
 */
function asset_version(string $path): string
{
    static $seen = [];
    $file = PROJECT_PATH . '/' . ltrim($path, '/');
    return $seen[$path] ??= ((int) @filemtime($file)) . '-' . ((int) @filesize($file));
}

/**
 * The address of a script or style with ITS version — for every tag that
 * loads one, and for the import map of index.php. ONE function for both,
 * because the entry script's address and the one the import map hands
 * out must be identical, or the module is loaded twice (CLAUDE.md, trap 5).
 */
function asset_url(string $path): string
{
    return url($path) . '?v=' . asset_version($path);
}

/**
 * The import map that hands every module in assets/js/ its version tag.
 *
 * A tag on a <script> reaches only that one file: the modules it imports
 * stand as fixed paths in the source ('./layers.js') and would come bare,
 * so a changed layers.js arrived only when the cache let go of the old one.
 * The map rewrites every such path onto itself plus ?v=.
 *
 * EVERY PAGE, since 30.09.2026. Until then only the map had one, and on
 * the administration 30 of 44 modules loaded bare — and worse, dialogs.js
 * and toast.js ran TWICE there: once as the footer's versioned entry,
 * once as the bare import of administration.js, two copies with two
 * states. With the map both addresses are the same, because both come
 * from asset_url() — the one rule this function exists to keep: the
 * address a <script> tag names and the one the map hands out must be
 * IDENTICAL, or the module loads twice (CLAUDE.md, trap five).
 *
 * The list is built from the directory, not by hand. Maintained by hand,
 * dialogs.js was once missing — map.js had fetched it since the custom
 * dialogs, in the enumeration it never appeared. It must stand before
 * the first module script of the page.
 */
function asset_import_map(): string
{
    $mapping = [];
    foreach (glob(PROJECT_PATH . '/assets/js/*.js') ?: [] as $file) {
        $path = 'assets/js/' . basename($file);
        $mapping[url($path)] = asset_url($path);
    }
    /* A MODULE'S SCRIPTS TOO (modules/<name>/assets/js, 21.11.2026): they
       import the core's by a relative path that ends at the addresses
       above, and one another by './…' — the same rule for both, so no
       script of a module ever comes bare or twice. */
    foreach (glob(PROJECT_PATH . '/modules/*/assets/js/*.js') ?: [] as $file) {
        $path = substr($file, strlen(PROJECT_PATH) + 1);
        $mapping[url($path)] = asset_url($path);
    }
    return "<script type=\"importmap\">\n"
        . json_encode(['imports' => $mapping], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        . "\n</script>\n";
}

/** Application-internal URL from a path without a leading slash. */
/**
 * Are the strong model's files on this server — the second model of the
 * photo window's „Entfernen" (assets/js/moebius.js)?
 *
 * THEY DO NOT COME WITH THE DEPLOYMENT (the choice of 14.11.2026):
 * the largest weighs 455 MB, more than a file may weigh on its way through
 * GitHub, so they are laid into assets/vendor/moebius by hand, once. Until
 * they are, and on any copy of the project that has none, the model is not
 * offered at all — a button that could only fail would be a lie. The exact
 * sizes are the browser's business (inpaint.js, STRONG_FILES): a file cut
 * short on its way up is caught there, by the download.
 */
function inpaint_strong_offered(): bool
{
    static $there = null;
    if ($there === null) {
        $there = true;
        foreach (['moebius_encoder.onnx', 'moebius_decoder.onnx', 'moebius_unet.onnx'] as $name) {
            $file = dirname(__DIR__) . '/assets/vendor/moebius/' . $name;
            if (!is_file($file) || filesize($file) < 1024 * 1024) {
                $there = false;
                break;
            }
        }
    }
    return $there;
}

function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/**
 * Redirect and terminate.
 *
 * Values with a leading slash are already complete paths and must not be
 * prefixed with BASE_URL a second time. This concerns above all the
 * login's 'next': it holds the REQUEST_URI, e.g. "/map/index.php" —
 * passed through url() that became "/map/map/…" and thus a 404 after every
 * login via a direct link.
 *
 * "//host/…" does not count as an own path here but as a foreign address,
 * and is treated as a relative value. Otherwise 'next' could be used to
 * redirect to a foreign page.
 */
function redirect_to(string $path): never
{
    $absolute = str_starts_with($path, 'http')
        || (str_starts_with($path, '/') && !str_starts_with($path, '//'));

    header('Location: ' . ($absolute ? $path : url($path)));
    exit;
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

/**
 * Token for the current session. Created on demand and then kept, so that
 * several open forms work side by side.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Hidden field for forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

/**
 * Check the token of an incoming request. Aborts on failure — an invalid
 * value means either an expired session or an attack attempt, and in both
 * cases nothing may be written.
 */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Sitzung abgelaufen. Bitte die Seite neu laden und noch einmal versuchen.');
    }
}

// ---------------------------------------------------------------------------
// JSON
// ---------------------------------------------------------------------------

/** Send a JSON response and terminate. */
function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Error response in the uniform format. */
function json_error(string $message, int $status = 400): never
{
    json_response(['error' => $message], $status);
}

/**
 * Read the JSON body of a request. The PWA sends JSON, the classic forms
 * send fields — both should be able to talk to the same endpoints.
 */
function request_data(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($type, 'application/json')) {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

// ---------------------------------------------------------------------------
// Checking values
// ---------------------------------------------------------------------------

/**
 * Read a coordinate pair from arbitrary inputs. Returns [lat, lon] or null
 * if something is wrong. Commas as decimal separators are allowed because
 * they constantly occur when copying from German sources.
 */
function coordinates_read(mixed $lat, mixed $lon): ?array
{
    $number = static function (mixed $value): ?float {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $value = str_replace(',', '.', trim($value));
        return is_numeric($value) ? (float) $value : null;
    };

    $latNum = $number($lat);
    $lonNum = $number($lon);

    if ($latNum === null || $lonNum === null) {
        return null;
    }

    // is_finite BEFORE the limits, and that is not belt and braces: NAN
    // compares FALSE against everything, so both bounds let it through —
    // measured. A point with NAN as its coordinate is not a point, it is a
    // corrupted row that no map can draw and no filter can find. INF the
    // limits catch by themselves; NAN they cannot, by the definition of the
    // comparison.
    //
    // Not reachable through the interface today: JSON knows no NAN, and as
    // a string it fails is_numeric. But this function is also asked by the
    // imports, and there a number is COMPUTED — one division by zero in a
    // conversion and it would be in the database.
    if (!is_finite($latNum) || !is_finite($lonNum)) {
        return null;
    }
    if ($latNum < -90 || $latNum > 90 || $lonNum < -180 || $lonNum > 180) {
        return null;
    }

    // Round to six decimal places — that is the precision of the database
    // column, around eleven centimeters.
    return [round($latNum, 6), round($lonNum, 6)];
}

/** Current time in MySQL format. */
function now(): string
{
    return date('Y-m-d H:i:s');
}

