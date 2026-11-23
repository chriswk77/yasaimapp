<?php
/**
 * The privacy statement — one text for the website and the Android app
 * (20.11.2026). The Play Store asks for a public address, and the
 * app shows this very site, so one statement covers both.
 *
 * NO LOGIN, ON PURPOSE: the store checks the address from outside, and
 * whoever is asked for an invitation code or a guest's name must be able to
 * read first what happens to it. It is the second page beside the login
 * that anybody may open.
 *
 * THE TEXT IS A FILE, NOT MARKUP (includes/privacy.de.txt). It is written
 * and kept in the app's repository (store/datenschutz.md there), because
 * it speaks for both halves; a new version replaces the file here word for
 * word, and nobody has to find its sentences again between tags. The file
 * begins at „Stand:" — the source's title and working note stay over
 * there. Only one thing differs: the address lines end in two blanks, the
 * mark for a line break.
 *
 * .txt AND NOT .md, though Markdown is what it holds: the deployment
 * leaves every .md at home (deploy.yml, AUSSCHLUSS — nobody reads Markdown
 * at runtime, it says). As privacy.de.md the page went live with its frame
 * and no text; local, where every file lies, nothing showed. So a missing
 * or empty text is now SAID on the page instead of shown as a blank one.
 *
 * GERMAN ONLY. A statement like this binds in the wording that was
 * approved, and that is the German one; a translation would be a second
 * text to keep true. So its sentences do not go through t().
 *
 * WHOEVER CHANGES WHAT IT DESCRIBES CHANGES IT TOO: a new outside service,
 * a file that moves between server and cloud, a period of keeping. The
 * source over there first, then this copy.
 *
 * The renderer lives in includes/legal_text.php since 23.11.2026, when the
 * rules (pages/terms.php) became the second text it reads.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/legal_text.php';

$file = PROJECT_PATH . '/includes/privacy.de.txt';
$text = is_file($file) ? trim((string) file_get_contents($file)) : '';

page_header(t('Datenschutzerklärung'));
if ($text === '') {
    // A state, so it stays in the flow: it is the reason the page is empty.
    notice('error', t('Der Text der Datenschutzerklärung fehlt auf dem Server. Bitte gib uns Bescheid.'));
}
?>
<div class="legal-text" lang="de">
<?= legal_html($text) ?>
</div>
<?php if (user_current() === null): ?>
<p class="hint-text"><a href="<?= h(url('login.php')) ?>"><?= h(t('Zur Anmeldung')) ?></a></p>
<?php endif; ?>
<?php page_footer(); ?>
