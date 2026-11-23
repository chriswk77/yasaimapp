<?php
/**
 * The two legal texts as HTML — the privacy statement (pages/privacy.php)
 * and the rules (pages/terms.php). Both are Markdown kept in a .txt file
 * (privacy.php says why not .md), and both are read by these functions.
 *
 * The renderer reads exactly what the text uses and nothing more:
 * „## " headings, paragraphs, lists with one nested level and wrapped
 * lines, **bold**, and the two blanks. Anything else would come out as
 * plain text — visible, never lost.
 */

declare(strict_types=1);

/** One line of text: escaped, then the bold spans. */
function legal_inline(string $text): string
{
    return (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', h($text));
}

/** The list gathered so far, as nested <ul> — items are [level, text]. */
function legal_list(array $items): string
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
        $html .= legal_inline($text);
    }
    return $html . str_repeat("</li>\n</ul>\n", $open + 1);
}

function legal_html(string $text): string
{
    $html = '';
    $paragraph = [];   // the lines of the paragraph being read
    $items = [];       // the list being read
    $flush = function () use (&$html, &$paragraph, &$items): void {
        if ($paragraph !== []) {
            $lines = [];
            foreach ($paragraph as $line) {
                // Two blanks at the end are a line break; any other end joins with a blank.
                $lines[] = legal_inline(rtrim($line)) . (str_ends_with($line, '  ') ? "<br>\n" : ' ');
            }
            $body = rtrim(implode('', $lines));
            // The date of the text is a footnote, not a sentence of it (CLAUDE.md, „Meta is a footnote").
            $class = str_starts_with($paragraph[0], 'Stand:') ? ' class="hint-text small provider-currency"' : '';
            $html .= "<p$class>" . $body . "</p>\n";
            $paragraph = [];
        }
        if ($items !== []) {
            $html .= legal_list($items);
            $items = [];
        }
    };
    foreach (preg_split('/\R/u', $text) as $line) {
        if (trim($line) === '') {
            $flush();
        } elseif (str_starts_with($line, '## ')) {
            $flush();
            $html .= '<h2>' . legal_inline(substr($line, 3)) . "</h2>\n";
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
