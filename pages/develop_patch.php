<?php
/**
 * One patch of „Entfernen", out of the picture's cloud — through this server.
 *
 *   develop_patch.php?kind=photo&id=7&patch=1a2b3c4d5e6f7a8b    a voucher photo's
 *   develop_patch.php?kind=cloud&id=12&patch=1a2b3c4d5e6f7a8b   a recording's
 *
 * The AI's pixels for one removed place (includes/develop_patches.php) lie
 * in „Bearbeitung" beside the picture, in the cloud of whoever's picture
 * it is. Everybody who may SEE the picture sees it with its places
 * removed, so the patches go to exactly the same people as the picture:
 * its owner, and whoever may look at the find (point_view_scope, the one
 * seeing gate — the same question pages/photo.php and pages/cloud_thumb.php
 * ask). A stranger, a missing picture and a missing patch all get the same
 * 404, so nothing is learned about which ids are taken.
 *
 * THROUGH THIS SERVER for the reason the pictures come through it: a
 * provider's link is made out to whoever asked and is a credential besides
 * (cloud_thumb.php says it at length) — it never reaches a browser.
 *
 * ONLY A PATCH AN EDIT OF THE PICTURE NAMES is fetched — the photographer's
 * or, since 12.11.2026, one somebody else laid on it (map_picture_edits), so the address
 * cannot be used to fish other files out of „Bearbeitung"; and only bytes
 * that are a PNG of the patch's size go out (develop_patch_png).
 *
 * KEPT BY THE BROWSER FOR GOOD: a patch's id is random and never written
 * twice with other pixels, so the address points at one content for ever.
 *
 * Logged-in people only. An excursion guest's world (pages/photo.php) has
 * no editing in it, and so no patches.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/photos.php';
require_once __DIR__ . '/../includes/develop_patches.php';
require_once __DIR__ . '/../includes/picture_fence.php';

$me = login_required();
$userId = (int) $me['id'];
// Nothing below writes to the session, and the cloud may take a moment (cloud_thumb.php).
session_write_close();

$kind  = (string) ($_GET['kind'] ?? '');
$id    = (int) ($_GET['id'] ?? 0);
$patch = (string) ($_GET['patch'] ?? '');
$table = match ($kind) {
    'photo' => 'map_photos',
    'cloud' => 'map_cloud_files',
    default => null,
};
if ($table === null || $id <= 0 || preg_match('/^[a-z0-9]{8,32}$/', $patch) !== 1
    || !table_exists($pdo, $table) || !column_exists($pdo, $table, 'develop')) {
    http_response_code(404);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
$seen = $row !== false && ((int) $row['user_id'] === $userId || (function () use ($pdo, $userId, $row): bool {
    require_once __DIR__ . '/../includes/point_owners.php';
    return point_view_scope($pdo, $userId, (int) $row['point_id']) !== null;
})());
// Named by any edit of the picture (0192) — the one open in the viewer need not be the valid one —
// or by an edit somebody else laid on it (0194): its patches lie in the same cloud.
if (!$seen || !in_array($patch, develop_picture_patch_ids($pdo, $kind, $row), true)
    || develop_patches_fileid($kind, $row) === '') {
    http_response_code(404);
    exit;
}

$s = develop_patches_cloud($pdo, $kind, $row);
if ($s === null) {
    http_response_code(502);
    exit;
}
try {
    $bytes = develop_patch_bytes($s, develop_patches_fileid($kind, $row), $patch);
} catch (RuntimeException $e) {
    error_log('develop_patch ' . $kind . ' ' . $id . ' ' . $patch . ': ' . $e->getMessage());
    http_response_code(502);
    exit;
}
if ($bytes === null) {
    http_response_code(404);
    exit;
}
picture_headers('image/png', strlen($bytes), 'private, max-age=31536000, immutable');
echo $bytes;
