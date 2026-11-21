<?php
/**
 * „Ordner in der Cloud" — the folder a recording lies in, opened at its
 * provider (26.09.2026, step four of „alle Clouds richtig nutzen").
 *
 *   cloud_folder.php?id=12   → on to the provider's own page of that folder
 *
 * A PAGE AND NOT AN ADDRESS BUILT IN THE BROWSER, because one of the
 * providers cannot be written out: OneDrive's folder address differs
 * between a personal account and a work account, and only Microsoft can
 * name it (cloud_folder_web says more). pCloud's and Drive's could be
 * built in map.js — but two ways to one button drift apart, and the
 * redirect costs a blink.
 *
 * OWN RECORDINGS ONLY, like the button: a co-owner's recording lies in the
 * PHOTOGRAPHER's cloud, and its folder in mine does not exist
 * (14.09.2026). Somebody else's id gets the same answer as a missing one.
 *
 * WHERE THERE IS NO ADDRESS, the page says so in one sentence instead of
 * a bare 404 — it opens in a tab of its own, and an empty tab explains
 * nothing.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud.php';

$me = login_required();
$userId = (int) $me['id'];

$row = null;
$id  = (int) ($_GET['id'] ?? 0);
if ($id > 0 && cloud_ready($pdo) && table_exists($pdo, 'map_cloud_files')) {
    $stmt = $pdo->prepare(
        'SELECT provider, folderid FROM map_cloud_files WHERE id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch() ?: null;
}

$web = $row === null ? null
    : cloud_folder_web($pdo, $userId, (string) $row['provider'] ?: 'pcloud', (string) ($row['folderid'] ?? ''));
if ($web !== null) {
    redirect_to($web);
}

require_once __DIR__ . '/../includes/page.php';
http_response_code(404);
page_header(t('Ordner in der Cloud'));
?>
<p><?= h(t('Diesen Ordner kann ich gerade nicht öffnen. Die Aufnahme selbst ist davon nicht betroffen — du findest sie weiter bei deinem Fund.')) ?></p>
<?php
page_footer();
