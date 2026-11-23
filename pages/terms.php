<?php
/**
 * The rules of YASAIMAPP (23.11.2026) — what is not allowed, how one
 * blocks and reports, what the operator does about it. The Play review
 * wants them accepted before anybody shares anything; the registration
 * asks for the tick (register.php) and stores the date
 * (map_users.terms_accepted_at, migration 0200).
 *
 * KEPT APART FROM THE PRIVACY STATEMENT, as Google asks: that one says what
 * happens to one's data, this one what one may do. Same frame, same
 * renderer (includes/legal_text.php), and like that one open WITHOUT A
 * LOGIN — whoever registers must read it before ticking it.
 *
 * GERMAN ONLY, for the privacy page's reason: rules bind in the wording
 * they were written in. The text is includes/terms.de.txt; the date at its
 * top is the version — whoever changes a rule changes the date with it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/legal_text.php';

$file = PROJECT_PATH . '/includes/terms.de.txt';
$text = is_file($file) ? trim((string) file_get_contents($file)) : '';

page_header(t('Regeln'));
if ($text === '') {
    // A state, so it stays in the flow: it is the reason the page is empty.
    notice('error', t('Der Text der Regeln fehlt auf dem Server. Bitte gib uns Bescheid.'));
}
?>
<div class="legal-text" lang="de">
<?= legal_html($text) ?>
</div>
<?php if (user_current() === null): ?>
<p class="hint-text"><a href="<?= h(url('login.php')) ?>"><?= h(t('Zur Anmeldung')) ?></a></p>
<?php endif; ?>
<?php page_footer(); ?>
