<?php
/**
 * Verwaltung → Nachtragen → Vorschauen: the preview cache's state and its
 * chunk button (includes/thumbs.php says what the cache is). The same
 * thumbs_run() as the cron job in cronjobs/every_5_minutes.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/thumbs.php';

/** One chunk from the button — short, the button goes on by itself. */
const THUMBS_SECONDS = 20;

function thumbs_process(PDO $pdo): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'thumbs') {
        return null;
    }
    csrf_check();

    ['made' => $made, 'failed' => $failed, 'swept' => $swept, 'profiled' => $profiled] = thumbs_run($pdo, microtime(true) + THUMBS_SECONDS);

    $parts = [];
    if ($made)   $parts[] = t('%d Vorschauen angelegt', $made);
    if ($failed) $parts[] = t('%d ließen sich nicht anlegen (nächster Versuch in einer Woche)', $failed);
    if ($swept)  $parts[] = t('%d verwaiste Vorschauen entfernt', $swept);
    if ($profiled) $parts[] = t('%d Vorschauen mit Farbprofil ergänzt', $profiled);

    $openCount = thumbs_pending($pdo);
    return [
        $openCount === 0 ? 'success' : 'hint',
        ($parts === [] ? t('Nichts zu tun.') : implode(' · ', $parts))
            . ($openCount === 0 ? ' — ' . t('fertig.') : ' · ' . t('noch %d offen', $openCount)),
    ];
}

function thumbs_render(PDO $pdo, ?array $message): void
{
    $state = thumbs_state($pdo);
    $target = url('pages/administration.php') . '#thumbs';
    ?>

<p class="hint-text">
    <?= h(t('Die kleinen Vorschaubilder liegen hier auf dem Server, damit Fundbrowser und Galerie schnell laden. Die Bilder selbst bleiben in der Cloud. Jedes Bild bekommt seine Vorschau beim ersten Anschauen, die übrigen legt der Server alle fünf Minuten nach.')) ?>
    <?= t('Unbeaufsichtigt erledigt das %s — hier geht es sofort, in Häppchen von %d Sekunden.', '<code>cronjobs/every_5_minutes.php</code>', THUMBS_SECONDS) ?>
</p>

<?php if ($message !== null) { notice($message[0], $message[1], true); } ?>

<div class="table-frame">
    <table class="list">
        <tr><th><?= h(t('mit Vorschau')) ?></th><td><?= $state['kept'] ?></td></tr>
        <tr><th><?= h(t('noch ohne')) ?></th><td><?= $state['open'] ?></td></tr>
        <tr><th><?= h(t('ließen sich nicht anlegen')) ?></th><td><?= $state['failed'] ?></td></tr>
    </table>
</div>

<form method="post" id="thumbs-form" action="<?= h($target) ?>" style="margin-top:1.5rem">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="thumbs">
    <button class="button<?= $state['open'] > 0 ? '' : ' subtle' ?>" type="submit"><?= h($state['open'] > 0 ? t('Anlegen (%d offen)', $state['open']) : t('Noch einmal prüfen')) ?></button>
    <?php if ($state['open'] > 0): ?>
        <label class="checkbox" style="margin-top:.8rem">
            <input type="checkbox" id="thumbs-auto" checked>
            <span><?= h(t('Von selbst weitermachen, bis alle erfasst sind')) ?></span>
        </label>
    <?php endif; ?>
</form>

<?php if ($message !== null && $state['open'] > 0): ?>
<script>
/* Continue only mid-run — the same reasoning as at the heights
   (elevation_content.php): a visit to the administration must not start
   asking the clouds unasked. */
if (document.getElementById('thumbs-auto')?.checked) {
    setTimeout(() => document.getElementById('thumbs-form').submit(), 800);
}
</script>
<?php endif; ?>

    <?php
}
