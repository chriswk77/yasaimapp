<?php
/**
 * Administration → Artlinks: Pilz-Link's export into map_web_links
 * (migration 0166, includes/web_links.php). One file at a time, uploaded
 * here; each replaces the sites it contains and leaves the others.
 *
 * AN UPLOAD AND NOT A PATH: the export lies on the maintainer's computer, and
 * the live server only ever sees what the browser hands it. gzip is
 * welcome — the plain CSV of some three hundred thousand links may be
 * larger than the host lets one upload.
 */

declare(strict_types=1);

require_once __DIR__ . '/web_links.php';

function web_links_process(PDO $pdo): array
{
    $state = ['message' => null, 'error' => []];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'web_links') {
        return $state;
    }
    csrf_check();
    try {
        $file = $_FILES['links'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE
                ? t('Die Datei ist größer, als der Server annimmt (%s). Pack sie als .gz und versuch es noch einmal.', (string) ini_get('upload_max_filesize'))
                : t('Bitte wähle die Datei aus.'));
        }
        @set_time_limit(300);
        $done = web_links_import($pdo, (string) $file['tmp_name']);
        $state['message'] = t('Eingespielt: %d Links aus %d Quellen, %d Zeilen übersprungen.', $done['rows'], count($done['sources']), $done['skipped']);
    } catch (Throwable $e) {
        $state['error'][] = t('Das Einspielen ist gescheitert, es wurde nichts geändert: %s', $e->getMessage());
    }
    return $state;
}

function web_links_render(PDO $pdo, array $state): void
{
    foreach ($state['error'] as $e) {
        notice('error', $e, float: true);
    }
    if ($state['message'] !== null) {
        notice('ok', $state['message'], float: true);
    }
    if (!web_links_ready($pdo)) {
        notice('warning', t('Die Artlinks brauchen die Migration 0166 — sie ist noch nicht eingespielt.'));
        return;
    }
    $stock = web_links_stock($pdo);
    ?>
    <p><?= h(t('Hier kommen die Links zu den Artporträts und Verbreitungskarten hinein, die Pilz-Link auf rund dreißig Seiten gesammelt hat. Dazu gehören zum Beispiel Artsdatabanken, Mushroom Observer, 123pilze und Taunuspilz. Sie erscheinen in der Bestimmung bei einer Art hinter dem Knopf „Internet-Links".')) ?></p>
    <p><?= h(t('Eine Datei ersetzt die Links der Seiten, die in ihr stehen. Alle anderen Seiten behalten ihre Links. So lässt sich auch ein Nachtrag mit einzelnen Seiten einspielen.')) ?></p>

    <?php if ($stock['rows'] > 0): ?>
        <div class="table-frame">
            <table class="list">
                <thead><tr><th><?= h(t('Seite')) ?></th><th><?= h(t('Links')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($stock['sources'] as $s): ?>
                    <tr><td><?= h((string) $s['label']) ?></td><td><?= number_format((int) $s['n'], 0, ',', '.') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="hint-text"><?= h(t('Noch sind keine Links eingespielt.')) ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="<?= h(url('pages/administration.php') . '#web_links') ?>" class="button-row">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="web_links">
        <input type="file" name="links" accept=".csv,.gz,text/csv,application/gzip" required>
        <button type="submit" class="button"><?= h(t('Einspielen')) ?></button>
    </form>
    <p class="provider-currency"><?= h(t('Erwartet wird die Exportdatei von Pilz-Link (CSV, auch als .gz). Der Server nimmt Dateien bis %s an.', (string) ini_get('upload_max_filesize'))) ?></p>
    <?php
}
