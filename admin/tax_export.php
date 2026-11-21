<?php
/**
 * Write out the taxonomy tables for the transfer to the server.
 * Command line only.
 *
 *   php admin/tax_export.php [--really] [--only=<prefix>]
 *
 * WHY NOT SIMPLY A MIGRATION
 * The three tables together are 223 MB and around 450,000 rows. As SQL with
 * INSERT statements that would be several hundred megabytes — neither the
 * repository nor the migration machinery, which reads a file and executes
 * it in one piece, carries that. And the host does not provide a shell with
 * which one could import a dump.
 *
 * Hence compressed tab-separated values: readable line by line, without
 * ever holding the file whole in memory, and about a fifth the size. The
 * import happens in the administration under "Serververwaltung → Taxonomie
 * einspielen", which works in bites and finds its own place again after an
 * abort.
 *
 * COL STAYS HERE. 843,000 rows and 303 MB, two thirds of the whole mass,
 * and for the answer "which name is valid today" GBIF and the linkage
 * suffice. The COL columns in map_tax_links stay filled and keep
 * their value — valid_name is written out there, even where it comes
 * from COL. Only looking things up in COL on the server is then not
 * possible.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/tax_transfer.php';

$really = in_array('--really', $argv, true) || in_array('--wirklich', $argv, true);

/* ONLY SOME TABLES: --only=<prefix> writes the tables whose name starts
 * with that, and inhalt.json names those alone (08.10.2026, for the
 * tables a module builds on this machine). The import skips every table the
 * list does not name — so a server whose GBIF or link table holds another
 * count than this machine is not topped up with rows from here. A full
 * inhalt.json would make exactly that attempt. */
$only = '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, 7);
    }
}
// A table this machine has no migration for yet is left out, not an error.
$tables = array_values(array_filter(tax_transfer_tables(), static fn($t) => str_starts_with($t, $only) && table_exists($pdo, $t)));
if ($tables === []) {
    fwrite(STDERR, "Keine Tabelle beginnt mit „{$only}“.\n");
    exit(1);
}

printf("%s\n\n", $really ? 'Ausschreiben' : 'PROBELAUF — es wird nichts geschrieben');

if (!$really) {
    foreach ($tables as $table) {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        printf("  %-24s %10s Zeilen\n", $table, number_format($n, 0, ',', '.'));
    }
    echo "\nZum Ausschreiben mit --really erneut aufrufen.\n";
    exit;
}

if (!is_dir(TAX_TRANSFER_PATH) && !mkdir(TAX_TRANSFER_PATH, 0775, true)) {
    fwrite(STDERR, 'Verzeichnis lässt sich nicht anlegen: ' . TAX_TRANSFER_PATH . "\n");
    exit(1);
}

$directory = [];

foreach ($tables as $table) {
    $target = tax_transfer_file($table);

    // Unbuffered: otherwise PDO pulls 375,000 rows into memory in one piece.
    $pdo->setAttribute(tax_transfer_unbuffered(), false);
    $reader = $pdo->query("SELECT * FROM $table");

    $handle = gzopen($target, 'wb6');
    $columns = null;
    $n = 0;

    foreach ($reader as $row) {
        if ($columns === null) {
            $columns = array_keys($row);
            gzwrite($handle, implode("\t", $columns) . "\n");
        }
        gzwrite($handle, tax_transfer_row($row) . "\n");
        $n++;
    }

    // An empty table gets its header row too, otherwise the import later
    // faces a file without structure.
    if ($columns === null) {
        $fields = $pdo->query("SELECT * FROM $table LIMIT 0");
        $names = [];
        for ($i = 0; $i < $fields->columnCount(); $i++) {
            $names[] = $fields->getColumnMeta($i)['name'];
        }
        gzwrite($handle, implode("\t", $names) . "\n");
    }

    gzclose($handle);
    $pdo->setAttribute(tax_transfer_unbuffered(), true);

    $directory[$table] = $n;

    printf("  %-24s %10s Zeilen  →  %6.1f MB\n",
        $table, number_format($n, 0, ',', '.'), filesize($target) / 1048576);
}

file_put_contents(
    TAX_TRANSFER_DIR,
    json_encode($directory, JSON_PRETTY_PRINT) . "\n"
);

printf("\n  Zusammen %.1f MB in %s\n",
    array_sum(array_map(
        static fn($t) => filesize(tax_transfer_file($t)),
        $tables
    )) / 1048576,
    TAX_TRANSFER_PATH);
