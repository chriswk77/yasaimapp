<?php
/**
 * The preview of a picture among a find's other files (includes/point_files.php).
 *
 *   pages/point_file.php?id=17           320 px, for the row in the list
 *   pages/point_file.php?id=17&large=1   2048 px, for the preview window
 *
 * THROUGH HERE AND NOT BY A LINK for the reason pages/cloud_thumb.php gives:
 * pCloud makes its links out to the IP that asked, so the bytes are fetched
 * where the link is valid and handed on (cloud_deliver, pictures only). A
 * preview, never the file: a downloaded file goes straight from the cloud
 * (point_file_way).
 *
 * ONLY ONE'S OWN FILES — nobody else sees them (05.10.2026). Not
 * present and not mine answer alike, so no id gives away that it exists.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/point_files.php';

$me = login_required();
$userId = (int) $me['id'];
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0 || !cloud_ready($pdo) || !point_files_ready($pdo)) {
    http_response_code(404);
    exit;
}
$stmt = $pdo->prepare('SELECT * FROM map_point_files WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$id, $userId]);
$file = $stmt->fetch();
if ($file === false || point_file_kind((string) $file['name']) !== 'image') {
    http_response_code(404);
    exit;
}
$s = cloud_storage_for($pdo, $userId, (string) $file['provider']);
if ($s === null) {
    http_response_code(404);
    exit;
}
try {
    $home = $s['provider'] === 's3' ? (string) cloud_home_resolve($pdo, $userId, $s) : null;
    $candidates = storage_file_fetch($s, (string) $file['fileid'], empty($_GET['large']) ? 320 : 2048, $home);
} catch (RuntimeException $e) {
    error_log('point_file ' . $id . ': ' . $e->getMessage());
    $candidates = [];
}
if ($candidates === [] || !cloud_deliver($candidates, 'point_file ' . $id)) {
    http_response_code($candidates === [] ? 404 : 502);
}
