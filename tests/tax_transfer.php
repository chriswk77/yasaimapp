<?php
/**
 * Tests the transfer: empty, restore from the file, compare.
 *
 *   php tests/tax_transfer.php        (from the project directory)
 *
 * Tested on map_tax_links, the smallest of the three tables.
 * First a checksum over the entire content is taken, then the table is
 * emptied and refilled from the gzip file; in the end row count and
 * checksum must match again.
 *
 * This is the only test that really says something: the escaping of
 * tabs, empty values and backslashes otherwise only shows up on the
 * server, where you can see it worst. The file itself is the safeguard
 * — it is only read.
 *
 * Tested on the side: that an abort in the middle of the run breaks
 * nothing. The chunk is called with one second of runtime, so it is
 * interrupted and resumed many times.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/tax_transfer.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

$table = 'map_tax_links';

/** Checksum over the entire table content, independent of the order. */
function checksum(PDO $pdo, string $table): string
{
    $sum = $pdo->query("CHECKSUM TABLE $table")->fetch(PDO::FETCH_NUM);
    return (string) ($sum[1] ?? '');
}

$rowsBefore = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
$sumBefore  = checksum($pdo, $table);

if ($rowsBefore === 0) {
    exit("$table is empty — nothing to check.\n");
}

printf("\nStarting point: %s rows, checksum %s\n",
    number_format($rowsBefore, 0, ',', '.'), $sumBefore);

$file = tax_transfer_file($table);
check(is_readable($file), "the file is there: " . basename($file));
if (!is_readable($file)) {
    exit("\nWithout the file there is no check — first php admin/tax_export.php --really\n");
}

echo "\nEmptying the table\n";
$pdo->exec("TRUNCATE TABLE $table");
check((int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() === 0, 'is empty');

echo "\nReloading in chunks of one second\n";
$rounds = 0;
do {
    [$kind, $text] = tax_transfer_chunk($pdo, 1);
    $rounds++;
    $loaded = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    if ($rounds > 200) { check(false, 'too many rounds — aborting'); break; }
} while ($loaded < $rowsBefore);

printf("  %d rounds, so %d interruptions survived\n", $rounds, $rounds - 1);

echo "\nResult\n";
$rowsAfter = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
$sumAfter  = checksum($pdo, $table);

check($rowsAfter === $rowsBefore,
    sprintf('row count matches: %s', number_format($rowsAfter, 0, ',', '.')));
check($sumAfter === $sumBefore,
    "checksum matches: $sumAfter");

echo "\nOne more call, although everything is in\n";
[$kind, $text] = tax_transfer_chunk($pdo, 1);
check((int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() === $rowsBefore,
    'creates nothing twice: ' . $text);

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
