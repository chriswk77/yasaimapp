<?php
/**
 * Shared preamble of all API endpoints.
 *
 * After including, these are available:
 *   $pdo, $me (logged-in user), $method, $input
 *
 * Two login routes, one endpoint: the web interface comes with a session
 * cookie and CSRF token, the PWA and a later app with a bearer token. Token
 * requests need no CSRF check, because the browser does not send them along
 * on its own — which is exactly what makes CSRF possible in the first place.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/data.php';

header('Content-Type: application/json; charset=utf-8');
// Responses contain the private location data — that has no business in
// any cache.
header('Cache-Control: no-store');

/* EXCURSION GUESTS ARE DENIED BY DEFAULT. An endpoint that can handle
   them says so EXPLICITLY (const EXCURSION_GUESTS_ALLOWED = true before
   the include) and branches on $actor itself — today that is points.php
   alone. Everything else answers 401 as if nobody were there: the guest
   account is the archive of every excursion, and any endpoint reached
   without this deliberate opt-in would hand a guest the whole of it.
   $me stays null for a guest — no endpoint may mistake him for a user. */
$actor = null;
$me = user_current();
if ($me === null) {
    if (defined('EXCURSION_GUESTS_ALLOWED') && EXCURSION_GUESTS_ALLOWED) {
        require_once __DIR__ . '/../../includes/excursions.php';
        $actor = excursion_actor($pdo);
    }
    if ($actor === null) {
        json_error(t('Nicht angemeldet.'), 401);
    }
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Some hosts do not let PATCH and DELETE through. Then a POST arrives with
// an envelope.
$input = request_data();
$overridden = strtoupper((string) ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $input['_methode'] ?? ''));
if ($method === 'POST' && in_array($overridden, ['PATCH', 'PUT', 'DELETE'], true)) {
    $method = $overridden;
}
// The envelope has been read and does not belong in the data. For a long
// time this went unnoticed, because every handler skips over the unknown —
// until the sharing permission check arrived, which deliberately rejects
// the unknown LOUDLY, and tripped over "_methode".
unset($input['_methode']);

// Writing access from the browser needs the CSRF token.
if ($method !== 'GET' && token_from_request() === null) {
    csrf_check();
}

/** ID from the query string or the body. */
function request_id(): int
{
    global $input;
    return (int) ($_GET['id'] ?? $input['id'] ?? 0);
}
