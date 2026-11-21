<?php
/**
 * One layer's mask of „Ebenen", out of its picture's cloud — through this
 * server.
 *
 *   layer_mask.php?set=5&key=cloud:12
 *
 * What was painted of one picture where it lies as a layer over others
 * (includes/layer_sets.php): a PNG in „Bearbeitung" beside the picture.
 * ONLY ITS MAKER asks for it — a set is his work in progress, nobody else
 * is shown it — and only for a layer his set names with a mask. Everything
 * else is the same 404.
 *
 * THROUGH THIS SERVER, as the pictures and the retouching's patches: a
 * provider's link is a credential and never reaches a browser.
 *
 * NEVER KEPT BY ANYONE ALONG THE WAY: the mask changes under this address
 * with every save. sw.js leaves the address alone for the same reason.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layer_sets.php';
require_once __DIR__ . '/../includes/picture_fence.php';

$me = login_required();
// Nothing below writes to the session, and the cloud may take a moment (cloud_thumb.php).
session_write_close();

$setId = (int) ($_GET['set'] ?? 0);
$key = (string) ($_GET['key'] ?? '');
if ($setId <= 0 || preg_match('/^(photo|cloud):[1-9][0-9]{0,9}$/', $key) !== 1) {
    http_response_code(404);
    exit;
}
try {
    $bytes = layer_mask_read($pdo, (int) $me['id'], $setId, $key);
} catch (RuntimeException $e) {
    error_log('layer_mask ' . $setId . ' ' . $key . ': ' . $e->getMessage());
    http_response_code(502);
    exit;
}
if ($bytes === null) {
    http_response_code(404);
    exit;
}
picture_headers('image/png', strlen($bytes), 'no-store');
echo $bytes;
