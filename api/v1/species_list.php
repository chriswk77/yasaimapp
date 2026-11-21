<?php
/**
 * A name package in one piece, for the search in the browser.
 *
 *   GET species_list.php?package=fungi
 *   GET species_list.php?package=lichens
 *
 * WHY IN ONE PIECE
 * The search once went through the server on every keystroke. That is fast,
 * but it presumes a network — and it is needed in the woods. The fungi list
 * is 560 kB packed, so about as much as 24 aerial-image tiles, the lichen
 * list 36 kB. Fetched once, the browser searches it itself: without waiting
 * and without reception.
 *
 * WHAT EXISTS is listed in includes/species_packages.php — here it is only served.
 *
 * WITHOUT A PARAMETER THE FUNGI COME. Not out of preference, but because of
 * the browsers that stored the list under the old address without a package
 * parameter: they revalidate with their ETag and should get the same answer
 * as before, instead of an empty one.
 */

declare(strict_types=1);

/* GUESTS TOO — the package itself, for the same reason as its directory
   (species_packages.php): a guest maps species and needs the names in
   the woods, without reception. Reference data, no user data. */
const EXCURSION_GUESTS_ALLOWED = true;

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/species_packages.php';

if ($method !== 'GET') {
    json_error(t('Methode nicht unterstützt.'), 405);
}

$key = (string) ($_GET['package'] ?? 'fungi');
if (species_package($key) === null) {
    json_error(t('Unbekanntes Paket.'), 404);
}

$rev = species_package_rev($pdo, $key);

// If the browser already has this version, it spares itself the transfer.
$etag = '"species-' . $key . '-' . $rev['version'] . '"';
header('ETag: ' . $etag);
header('Cache-Control: private, max-age=86400');

if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

// The GBIF package is 21 MB and takes a few seconds. The usual time limit
// for a request would cut it off in the middle — and with status 200 at
// that, because the header is long gone. The browser would get truncated
// JSON and report a format error where in truth the time ran out.
set_time_limit(300);

$gzip = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')), 'gzip');
species_package_deliver($pdo, $key, $rev['version'], $gzip);
