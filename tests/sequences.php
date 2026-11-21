<?php
/**
 * Sequences at a find (migration 0112) — the parser, the doors, the
 * co-ownership. Runs against the demodata fixture (anna, ben, carla)
 * and cleans up after itself.
 *
 *   php tests/sequences.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/container.php';
require_once __DIR__ . '/../includes/sequences.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo '  ', $b ? 'ok    ' : 'FAIL  ', $what, "\n";
    if (!$b) $failed++;
}
function rejected(callable $fn, ?string &$reason = null): bool
{
    try { $fn(); return false; } catch (InvalidArgumentException $e) { $reason = $e->getMessage(); return true; }
}

echo "The parser:\n";
$one = sequence_parse("acgt acgt\nACGTN");
check(count($one) === 1 && $one[0]['bases'] === 'ACGTACGTACGTN' && $one[0]['label'] === '',
    'bare bases: upper case, whitespace gone, no label');
$fasta = sequence_parse(">ITS Probe 7\nACGTACGT\nTTGG\n>LSU Probe 7\n; a comment\nGGCC\n");
check(count($fasta) === 2 && $fasta[0]['label'] === 'ITS Probe 7' && $fasta[0]['bases'] === 'ACGTACGTTTGG'
    && $fasta[1]['label'] === 'LSU Probe 7' && $fasta[1]['bases'] === 'GGCC',
    'FASTA with two records gives two entries, headers as labels, comments skipped');
$numbered = sequence_parse("1 acgtacgtac gtacgtacgt\n21 acgt");
check($numbered[0]['bases'] === 'ACGTACGTACGTACGTACGTACGT', 'a GenBank-style numbered listing loses its numbers');
check(sequence_parse("AC-GT RYKM SWBD HVN")[0]['bases'] === 'AC-GTRYKMSWBDHVN', 'IUPAC letters and gaps pass');
check(rejected(fn() => sequence_parse('ACGT ist keine Sequenz'), $r) && str_contains((string) $r, '„I"'),
    'a letter outside IUPAC is refused, and named: ' . $r);
check(rejected(fn() => sequence_parse("  \n")), 'nothing is refused');
check(rejected(fn() => sequence_parse(">nur ein Kopf\n")), 'a header without bases is refused');

if (!sequences_ready($pdo)) {
    echo "Migration 0112 is not applied — the rest is skipped.\n";
    exit($failed === 0 ? 0 : 1);
}
$id = static fn(string $email): int => (int) $pdo->query(
    'SELECT id FROM map_users WHERE email = ' . $pdo->quote($email))->fetchColumn();
$anna = $id('anna@demo.local'); $ben = $id('ben@demo.local'); $carla = $id('carla@demo.local');
if (!$anna || !$ben || !$carla) {
    echo "The demodata fixture is missing — run php admin/demodata.php --new first.\n";
    exit(1);
}

echo "The doors and the ownership:\n";
/* A SEQUENCE ONLY ON A FIND (02.10.2026): the test's find goes into
   anna's species list AFTER it is created — born there, point_create would
   ask the BfN for protected areas, three requests per run for nothing. */
$speciesList = (int) $pdo->query(
    "SELECT l.id FROM map_lists l
      WHERE l.user_id = $anna AND l.is_species = 1 AND l.deleted_at IS NULL
      ORDER BY l.id LIMIT 1")->fetchColumn();
$mine = point_create($pdo, $anna, ['lat' => 51.5, 'lon' => 10.5, 'title' => 'PRUEF Sequenzfund']);
$waypoint = point_create($pdo, $anna, ['lat' => 51.5, 'lon' => 10.5, 'title' => 'PRUEF Parkplatz']);
$benList = null;
try {
    check(rejected(fn() => sequence_create($pdo, $anna, $mine, ['text' => 'ACGT']), $r),
        'a point in no species list takes no sequence: ' . $r);
    check(rejected(fn() => sequence_create($pdo, $anna, $waypoint, ['text' => 'ACGT'])),
        'nor does a waypoint');
    point_lists_set($pdo, $anna, $mine, [$speciesList]);

    $rows = sequence_create($pdo, $anna, $mine, [
        'text' => ">ITS\nACGTACGTAC\n", 'marker' => 'ITS', 'accession' => 'MN123456', 'lab' => 'PRUEF Labor',
    ]);
    check(count($rows) === 1 && $rows[0]['length'] === 10 && $rows[0]['marker'] === 'ITS'
        && $rows[0]['accession'] === 'MN123456' && $rows[0]['user_id'] === $anna,
        'anna hangs a sequence on her own find');
    check((point_load($pdo, $anna, $mine)['sequences'][0]['id'] ?? null) === $rows[0]['id'],
        'the loaded point carries it');
    check(str_starts_with(sequence_fasta($rows[0]), ">ITS\nACGTACGTAC\n"), 'FASTA comes back with the label as header');

    check(rejected(fn() => sequence_create($pdo, $carla, $mine, ['text' => 'ACGT'])),
        'carla, who sees nothing of the find, is refused');
    check(sequence_delete($pdo, $ben, $rows[0]['id']) === false, "ben cannot delete anna's sequence");
    check(sequence_update($pdo, $anna, $rows[0]['id'], ['marker' => 'LSU', 'accession' => 'MN999']) === true
        && sequence_load($pdo, $rows[0]['id'])['marker'] === 'LSU', 'the author edits the columns');
    check(sequence_delete($pdo, $anna, $rows[0]['id']) === true && sequence_load($pdo, $rows[0]['id']) === null,
        'and deletes it');

    // A foreign find through a share: ben's list is shared with anna (since
    // „Listen teilen", 09.10.2026, with the ticks title, note, move and
    // attach_photos — the old fixture's „joint list" of his is gone).
    $benPoint = (int) $pdo->query(
        "SELECT p.id FROM map_points p
           JOIN map_point_lists pl ON pl.point_id = p.id
           JOIN map_container_lists cl ON cl.list_id = pl.list_id AND cl.user_id = $ben
          WHERE p.user_id = $ben AND p.deleted_at IS NULL LIMIT 1")->fetchColumn();
    check($benPoint > 0, "the fixture has ben's point in a list he shares with anna");
    if ($benPoint > 0) {
        require_once __DIR__ . '/../includes/point_owners.php';
        check(!point_owner_is($pdo, $benPoint, $anna), 'anna is no co-owner of it yet');
        // „Bens Beobachtungen" is no species list in the fixture, so for
        // the co-owner path it becomes one for the length of the test.
        check(rejected(fn() => sequence_create($pdo, $anna, $benPoint, ['text' => 'ACGT'])),
            "ben's list is no species list — no sequence there");
        $benList = (int) $pdo->query(
            "SELECT pl.list_id FROM map_point_lists pl
               JOIN map_lists l ON l.id = pl.list_id AND l.user_id = $ben
              WHERE pl.point_id = $benPoint LIMIT 1")->fetchColumn();
        $pdo->prepare('UPDATE map_lists SET is_species = 1 WHERE id = ?')->execute([$benList]);
        $foreign = sequence_create($pdo, $anna, $benPoint, ['text' => "ACGTTGCA", 'marker' => 'ITS']);
        check(count($foreign) === 1 && $foreign[0]['user_id'] === $anna && $foreign[0]['author'] !== '',
            "anna hangs a sequence on ben's find — it is hers, with her name");
        check(point_owner_is($pdo, $benPoint, $anna), 'and that made her a co-owner, like a photographer');
        check(sequence_delete($pdo, $ben, $foreign[0]['id']) === false, 'ben, the holder, cannot delete her voucher');
        check(sequence_delete($pdo, $anna, $foreign[0]['id']) === true, 'she can');
        $pdo->prepare('DELETE FROM map_point_owners WHERE point_id = ? AND user_id = ?')->execute([$benPoint, $anna]);
    }
} finally {
    $pdo->prepare('DELETE FROM map_points WHERE id IN (?, ?)')->execute([$mine, $waypoint]);
    if ($benList) {
        $pdo->prepare('UPDATE map_lists SET is_species = 0 WHERE id = ?')->execute([$benList]);
    }
}

echo $failed === 0 ? "All good.\n" : "$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
