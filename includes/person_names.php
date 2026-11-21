<?php
/**
 * Whether a person field names the account — ONE RULE for the import and
 * the DGfM delivery (25.09.2026).
 *
 * The import asks it of every find in a file („wenn der Finder nicht der
 * eigene Name ist, ist es nicht ein eigener Fund", import_screen.php), the
 * delivery of every find it would send: one with somebody else as finder
 * goes only when the account says so (migration 0133, mykis.php). Two
 * readings would let a find come in as one's own and then stay home as
 * somebody else's — so both read the same words the same way, here.
 *
 * ONE'S OWN NAME IN ANY USUAL SPELLING counts: case, the order of first
 * and last name, a comma between them, umlauts written out („Schroeder"),
 * and among several finders („Volker Maier und Sebastian Schröder"). An
 * initial does not — „S. Schröder" may be somebody else of the family.
 */

declare(strict_types=1);

/**
 * The words of a name, as the matching compares them: lower case, umlauts
 * written out, everything that is not a letter a gap, and single letters —
 * initials — left out.
 *
 * @return string[]
 */
function person_name_tokens(string $text): array
{
    $text = mb_strtolower($text);
    $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
                          'é' => 'e', 'è' => 'e', 'á' => 'a', 'à' => 'a', 'ó' => 'o', 'ç' => 'c']);
    return array_values(array_filter(preg_split('/[^a-z]+/', $text) ?: [],
        static fn(string $w): bool => mb_strlen($w) > 1));
}

/**
 * Does the text name me — every word of my name in it, in any order?
 *
 * @param string[] $self  person_name_tokens() of the account's name
 */
function person_names_self(string $text, array $self): bool
{
    $words = array_flip(person_name_tokens($text));
    foreach ($self as $w) {
        if (!isset($words[$w])) {
            return false;
        }
    }
    return true;
}
