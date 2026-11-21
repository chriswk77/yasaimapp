<?php
/**
 * The link from the confirmation mail (migration 0116,
 * includes/email_confirmation.php). No login needed: the mail may be
 * opened on another device than the one that asked; the 256-bit secret in
 * the link is the proof, and it works once.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/email_confirmation.php';

$ok = null;
$error = null;
if (email_confirmation_ready($pdo)) {
    try {
        $ok = email_confirmation_finish($pdo, (string) ($_GET['t'] ?? '')) !== null;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

page_header(t('E-Mail-Adresse bestätigen'));
?>
<div class="card-block narrow">
    <h1><?= h(t('E-Mail-Adresse bestätigen')) ?></h1>
    <?php if ($ok): ?>
        <p><?= h(t('Danke — deine E-Mail-Adresse ist bestätigt.')) ?></p>
    <?php elseif ($error !== null): ?>
        <p><?= h($error) ?></p>
    <?php else: ?>
        <p><?= h(t('Dieser Link ist abgelaufen oder wurde schon benutzt. In den Einstellungen unter „Name und E-Mail-Adresse" lässt sich ein neuer anfordern.')) ?></p>
    <?php endif; ?>
    <div class="button-row right">
        <a class="button" href="<?= h(url('index.php')) ?>"><?= h(t('Zur Karte')) ?></a>
    </div>
</div>
<?php page_footer();
