<?php
/**
 * Tests the names of the DGfM precision rules (mykis_rule_name in
 * includes/mykis.php, Einstellungen → DGfM-Kartierung → Genauigkeit).
 *
 *   php tests/mykis_rule_names.php
 *
 * WHY THIS IS TESTED. A rule compares its name with the first words of a
 * find's title (mykis_taxon), and a name that is not quite right matches
 * nothing — while the rule sits in the list and looks as if it worked.
 * „Boletus edulys" would leave every Steinpilz at the finer step its finder
 * meant to blur. So a typed name must be in the DGfM's list, is kept in the
 * list's own spelling, and a species is exactly the two words the rules
 * compare.
 *
 * NEEDS the fungus reference (map_tax_dgfm) and only reads it. Separate
 * from tests/export.php on purpose: that one promises to need no database.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mykis.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

if (!table_exists($pdo, 'map_tax_dgfm')
    || (int) $pdo->query('SELECT COUNT(*) FROM map_tax_dgfm')->fetchColumn() === 0) {
    echo "The fungus reference (map_tax_dgfm) is empty — nothing to look names up in.\n";
    exit(1);
}

echo "\nThe names of the DGfM precision rules\n\n";

check(mykis_rule_name($pdo, 'boletus', false) === 'Boletus' && mykis_rule_name($pdo, '  MORCHELLA ', false) === 'Morchella',
    'a genus comes back in the list\'s spelling, whatever the case it was typed in');
check(mykis_rule_name($pdo, 'Boletsu', false) === null,
    'a genus the list does not know is refused — a rule for it would match nothing');
check(mykis_rule_name($pdo, 'Boletus edulis', false) === null,
    'a genus is one word: „Boletus edulis" in the genus field is refused, not quietly cut to all Boletus');
check(mykis_rule_name($pdo, 'boletus EDULIS', true) === 'Boletus edulis',
    'a species comes back in the list\'s spelling');
check(mykis_rule_name($pdo, 'Boletus edulis var. arenarius', true) === 'Boletus edulis'
    && mykis_rule_name($pdo, 'Boletus edulis agg.', true) === 'Boletus edulis',
    'a species is the two words the rules compare — a variety or „agg." typed along falls away');
check(mykis_rule_name($pdo, 'Boletus', true) === null && mykis_rule_name($pdo, 'Boletus edulys', true) === null,
    'a single word or a misspelt epithet is no species');
check(mykis_rule_name($pdo, 'Boletus e%', true) === null && mykis_rule_name($pdo, 'Boletus _dulis', true) === null,
    'the wildcards of LIKE are taken as letters, not as „anything"');
check(mykis_rule_name($pdo, '', false) === null && mykis_rule_name($pdo, '   ', true) === null,
    'nothing typed is no name');

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
