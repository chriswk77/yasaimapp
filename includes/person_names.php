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

/**
 * THE NAMES ONE HAS WRITTEN AS FINDER OR DETERMINER, for the suggestions
 * under those two fields (24.11.2026: „Oft sind es ja die gleichen
 * Personen, die im Verein sind … mit denen man zusammenarbeitet").
 *
 * Every value as it stands, with how often it stands so — the browser
 * splits „Volker Maier und Sebastian Schröder" into its two people and
 * merges the spellings (pointwindow.js, peopleOffers), so the rule lives in
 * ONE place and also covers what was typed since the page came. Only one's
 * OWN finds: a name a friend wrote is his vocabulary, and a foreign find's
 * finder is not somebody one has entered. The trash counts too — a name
 * does not stop being a club member's because one find of his was thrown
 * away.
 *
 * Read per page, not stored: a few thousand rows' JSON cost milliseconds,
 * and a table of its own would have to be kept in step with every save,
 * import and handover.
 *
 * @return array<int, array{0: string, 1: int}>  [value, count], most frequent first
 */
function person_names_written(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT extra FROM map_points
                            WHERE user_id = ? AND (extra LIKE '%\"Finder\"%' OR extra LIKE '%\"Bestimmer\"%')");
    $stmt->execute([$userId]);
    $count = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
        $extra = json_decode((string) $json, true);
        if (!is_array($extra)) {
            continue;
        }
        foreach (['Finder', 'Bestimmer'] as $field) {
            $value = trim((string) preg_replace('/\s+/u', ' ', (string) ($extra[$field] ?? '')));
            if ($value !== '' && mb_strlen($value) <= 200) {
                $count[$value] = ($count[$value] ?? 0) + 1;
            }
        }
    }
    arsort($count);
    $out = [];
    foreach (array_slice($count, 0, 500, true) as $value => $n) {
        $out[] = [(string) $value, $n];
    }
    return $out;
}
