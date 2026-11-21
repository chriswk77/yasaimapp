<?php
/**
 * ONE SLICE OF THE PUBLIC FIND BOOK'S REFRESH — for the Fundbrowser's
 * button (assets/js/publicsite.js), which calls it again and again until
 * `done` and then says in one toast whether it worked.
 *
 * QUIET, NO PAGE (20.11.2026, after the first try opened a page of its
 * own with a progress bar, the result and a link: „dass du mir eine neue
 * Seite mit Statusmeldung aufmachst, finde ich nicht gut. Einfach
 * stillschweigend … mit einer Toast-Nachricht … aber mehr nicht"). So the
 * answer carries numbers only; which finds stay off the site and why can
 * still be read at the site's own refresh address.
 *
 * includes/public_site.php says who may (the account whose finds the site
 * shows) and hands the work to the site, where the rules of what is
 * public live.
 *
 * POST /api/v1/public_site.php
 *   → {done, held, finds, made, left, total}
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/public_site.php';

if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
if (!public_site_may($me)) {
    json_error(t('Diese Seite gehört zu einem anderen Konto.'), 403);
}

// A slice runs for seconds — the session is not held meanwhile, or every other request of this browser would wait for it.
session_write_close();

try {
    $result = public_site_refresh($pdo);
} catch (Throwable $e) {
    json_error($e->getMessage(), 500);
}

json_response([
    'done'  => $result['done'],
    'held'  => $result['held'],
    'finds' => $result['finds'],
    'made'  => $result['made'],
    'left'  => $result['left'],
    'total' => $result['total'],
]);
