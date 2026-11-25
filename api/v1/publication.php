<?php
/**
 * WHAT A FIND BOOK FETCHES — the released finds of one account, and each
 * plate page once (includes/publication.php says what a find book is, what
 * is public, and why the pictures pass through here instead of lying here).
 *
 *   GET publication.php                    the released finds
 *     → {v, site, finds: [{id, name, german, genus, date, place, state,
 *        country, certainty, mtb, quarter, details, keyings, rights,
 *        plates: [{id, k, w, h}]}], notes: [string]}
 *   GET publication.php?file=<plate id>    one plate page, both sizes in
 *                                          one answer: the small file's
 *                                          length as ten digits and a line
 *                                          feed, the small file, the large
 *                                          file. The header X-Fundbuch-File
 *                                          repeats the page's `k`.
 *
 * THE KEY COMES IN A HEADER — „X-Fundbuch-Key: …" — or as „Authorization:
 * Bearer …"; ?key= stays for a host whose PHP can send neither. A header
 * of its own first, because many hosts take „Authorization" away before
 * PHP sees it. In the address the key would land in every access log.
 *
 * NOT A LOGIN. This file does not go through _start.php: the key opens the
 * released finds and nothing else, there is no $me, and no other endpoint
 * knows this key. A wrong key and a missing find book answer alike.
 *
 * BOTH SIZES IN ONE ANSWER, because both are made from the one original:
 * asked for one at a time, every page would be fetched from the cloud
 * twice. Nothing is written to this server on the way — the bytes pass
 * through.
 *
 * A CONTRACT (the package cannot be redeployed with a push): fields are
 * added, never renamed.
 */

declare(strict_types=1);

/* NO SESSION FOR A MACHINE. bootstrap.php starts one for every request;
   a find book asks every hour and for every page, and each call would
   leave a session file behind. A handler that keeps nothing. */
session_set_save_handler(new class implements SessionHandlerInterface {
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false { return ''; }
    public function write(string $id, string $data): bool { return true; }
    public function destroy(string $id): bool { return true; }
    public function gc(int $max_lifetime): int|false { return 0; }
}, true);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/publication.php';

header('Cache-Control: no-store');
header_remove('Set-Cookie');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_error('Methode nicht unterstützt.', 405);
}
if (!publication_ready($pdo)) {
    json_error('Das Fundbuch ist auf diesem Server noch nicht eingerichtet.', 503);
}

$key = (string) ($_SERVER['HTTP_X_FUNDBUCH_KEY'] ?? token_from_request() ?? $_GET['key'] ?? '');
// hash_equals is not needed: the key is looked up by its hash, and a wrong one finds no row.
$book = publication_by_key($pdo, trim($key));
if ($book === null) {
    json_error('Kein oder falscher Schlüssel.', 403);
}
$userId = (int) $book['user_id'];
$site = (string) $book['site_url'];

$file = (string) ($_GET['file'] ?? '');
if ($file !== '') {
    try {
        $picture = publication_picture($pdo, $userId, $site, $file);
    } catch (Throwable $e) {
        error_log('publication ' . $file . ': ' . $e->getMessage());
        json_error('Die Tafel ließ sich nicht aus der Cloud holen.', 502);
    }
    if ($picture === null) {
        json_error('Diese Tafel ist nicht freigegeben — oder es gibt sie nicht mehr.', 404);
    }
    header('Content-Type: application/octet-stream');
    header('X-Fundbuch-File: ' . $picture['k']);
    header('Content-Length: ' . (11 + strlen($picture['small']) + strlen($picture['large'])));
    echo sprintf('%010d', strlen($picture['small'])), "\n", $picture['small'], $picture['large'];
    exit;
}

$pdo->prepare('UPDATE map_publications SET last_fetch_at = NOW() WHERE user_id = ?')->execute([$userId]);
$plan = publication_plan($pdo, $userId, $site);
json_response([
    'v'     => PUBLICATION_VERSION,
    'site'  => $site,
    'finds' => $plan['finds'],
    'notes' => $plan['notes'],
]);
