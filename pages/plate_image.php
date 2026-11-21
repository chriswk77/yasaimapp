<?php
/**
 * A DRAWN PAGE OF A PLATE (13.10.2026, includes/plate_images.php).
 *
 *   plate_image.php?id=<plate>&page=<n>[&v=<hash>]          the kept preview
 *   plate_image.php?id=<plate>&page=<n>&large=1[&v=<hash>]  the page from the maker's cloud
 *
 * Whoever may see the find sees its plates (plates_for_point), so whoever
 * may see the find gets their pages — from the MAKER's directory and the
 * MAKER's cloud, the way a co-owner's picture comes from its photographer.
 * Not present and not allowed answer alike, with a 404. The hash in v only
 * makes a new drawing a new address; the browser keeps each for good.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/plate_images.php';
require_once __DIR__ . '/../includes/point_owners.php';

$me = login_required();
// Nothing below writes to the session — let the visitor's next request through (cloud_thumb.php says why).
session_write_close();

$plateId = (int) ($_GET['id'] ?? 0);
$page    = (int) ($_GET['page'] ?? 0);
if ($plateId <= 0 || $page <= 0 || !plate_images_ready($pdo)) {
    http_response_code(404);
    exit;
}
$stmt = $pdo->prepare('SELECT p.user_id, p.point_id, i.provider, i.fileid
                         FROM map_plate_images i JOIN map_plates p ON p.id = i.plate_id
                        WHERE i.plate_id = ? AND i.page = ? LIMIT 1');
$stmt->execute([$plateId, $page]);
$row = $stmt->fetch();
if ($row === false || point_view_scope($pdo, (int) $me['id'], (int) $row['point_id']) === null) {
    http_response_code(404);
    exit;
}
$maker = (int) $row['user_id'];

if (empty($_GET['large'])) {
    $kept = plate_image_preview_path($maker, $plateId, $page);
    if (is_file($kept)) {
        require_once __DIR__ . '/../includes/thumbs.php';
        thumb_serve($kept);
        exit;
    }
    // No preview kept (this server could not draw one): the page itself, below.
}

$s = cloud_storage_for($pdo, $maker, (string) $row['provider']);
if ($s === null) {
    http_response_code(404);
    exit;
}
try {
    $candidates = storage_file_fetch($s, (string) $row['fileid']);
} catch (RuntimeException $e) {
    error_log('plate_image ' . $plateId . '/' . $page . ': ' . $e->getMessage());
    $candidates = [];
}
if ($candidates === [] || !cloud_deliver($candidates, 'plate_image ' . $plateId . '/' . $page)) {
    http_response_code($candidates === [] ? 404 : 502);
}
