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
 * The renderer below reads exactly what the text uses and nothing more:
 * „## " headings, paragraphs, lists with one nested level and wrapped
 * lines, **bold**, and the two blanks. Anything else would come out as
 * plain text — visible, never lost.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/page.php';

/** One line of text: escaped, then the bold spans. */
function privacy_inline(string $text): string
{
    return (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', h($text));
}

/** The list gathered so far, as nested <ul> — items are [level, text]. */
function privacy_list(array $items): string
{
    $html = '';
    $open = -1;
    foreach ($items as [$level, $text]) {
        if ($level > $open) {
            $html .= "<ul>\n<li>";
        } elseif ($level < $open) {
            $html .= "</li>\n</ul>\n</li>\n<li>";
        } else {
            $html .= "</li>\n<li>";
        }
        $open = $level;
        $html .= privacy_inline($text);
    }
    return $html . str_repeat("</li>\n</ul>\n", $open + 1);
}

function privacy_html(string $text): string
{
    $html = '';
    $paragraph = [];   // the lines of the paragraph being read
    $items = [];       // the list being read
    $flush = function () use (&$html, &$paragraph, &$items): void {
        if ($paragraph !== []) {
            $lines = [];
            foreach ($paragraph as $line) {
                // Two blanks at the end are a line break; any other end joins with a blank.
                $lines[] = privacy_inline(rtrim($line)) . (str_ends_with($line, '  ') ? "<br>\n" : ' ');
            }
            $body = rtrim(implode('', $lines));
            // The date of the text is a footnote, not a sentence of it (CLAUDE.md, „Meta is a footnote").
            $class = str_starts_with($paragraph[0], 'Stand:') ? ' class="hint-text small provider-currency"' : '';
            $html .= "<p$class>" . $body . "</p>\n";
            $paragraph = [];
        }
        if ($items !== []) {
            $html .= privacy_list($items);
            $items = [];
        }
    };
    foreach (preg_split('/\R/u', $text) as $line) {
        if (trim($line) === '') {
            $flush();
        } elseif (str_starts_with($line, '## ')) {
            $flush();
            $html .= '<h2>' . privacy_inline(substr($line, 3)) . "</h2>\n";
        } elseif (preg_match('/^( *)- (.*)$/u', $line, $m) === 1) {
            if ($paragraph !== []) {
                $flush();
            }
            $items[] = [intdiv(strlen($m[1]), 2), $m[2]];
        } elseif ($items !== [] && str_starts_with($line, ' ')) {
            $items[count($items) - 1][1] .= ' ' . trim($line);   // a wrapped line of the item above
        } else {
            if ($items !== []) {
                $flush();
            }
            $paragraph[] = $line;
        }
    }
    $flush();
    return $html;
}

$file = PROJECT_PATH . '/includes/privacy.de.txt';
$text = is_file($file) ? trim((string) file_get_contents($file)) : '';

page_header(t('Datenschutzerklärung'));
if ($text === '') {
    // A state, so it stays in the flow: it is the reason the page is empty.
    notice('error', t('Der Text der Datenschutzerklärung fehlt auf dem Server. Bitte gib uns Bescheid.'));
}
?>
<div class="legal-text" lang="de">
<?= privacy_html($text) ?>
</div>
<?php if (user_current() === null): ?>
<p class="hint-text"><a href="<?= h(url('login.php')) ?>"><?= h(t('Zur Anmeldung')) ?></a></p>
<?php endif; ?>
<?php page_footer(); ?>
