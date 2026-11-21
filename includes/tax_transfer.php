<?php
/**
 * Shared pieces for exporting and importing the taxonomy tables.
 *
 * The format is deliberately plain: gzip-compressed tab-separated values,
 * first line the column names. Readable line by line — the import never has
 * to hold more than one line in memory, and that is the condition for it
 * getting through at all on a shared server with scarce memory.
 *
 * The escaping follows MySQL: \N for "no value", and backslash, tab and
 * newline are protected with a backslash. Without that, a species name
 * containing a tab tears the line apart — unlikely, but with 450,000 lines
 * "unlikely" is no foundation.
 */

declare(strict_types=1);

/**
 * Which tables get transferred, in this order.
 *
 * map_tax_col is missing on purpose: 843,000 rows and 303 MB, two thirds of
 * the mass, and for the answer "which name is valid today" GBIF and the
 * linkage suffice. Their COL columns stay filled and keep their value.
 *
 * THE ANIMALS WERE MISSING BY ACCIDENT (found 13.09.2026, when the species
 * tab began counting what each list holds and every animal group said
 * „noch keine Namen auf dem Server"): the two tables came in August, after
 * this list was written, and nobody added them — the switches stood in the
 * administration with nothing behind them for three weeks, on the live
 * server and on every phone. A table that is not named here does not
 * travel, and nothing tells anyone.
 *
 * A MODULE'S TABLES travel the same way (since 08.10.2026; named by the
 * module since 20.11.2026 — tax_transfer_tables() below): built on the
 * machine that holds their source, emptied on the server and imported anew
 * after every new build, like map_tax_links.
 */
const TAX_TRANSFER_TABLES = [
    'map_tax_gbif',
    'map_tax_dgfm',
    'map_tax_lichens',
    'map_tax_plants',
    'map_tax_links',
    'map_tax_animals',
    'map_tax_faeu',
];

/**
 * Every table that travels: the core's above, then what the modules add
 * (includes/modules.php, hook transfer_tables) — in that order, which is the
 * order they are written out and read in. The key tables stood in the list
 * above until 20.11.2026; they are their module's to name now.
 *
 * @return string[]
 */
function tax_transfer_tables(): array
{
    $tables = TAX_TRANSFER_TABLES;
    foreach (module_each('transfer_tables') as $added) {
        $tables = [...$tables, ...array_values((array) $added)];
    }
    return $tables;
}

const TAX_TRANSFER_PATH     = PROJECT_PATH . '/data/taxonomie/uebertrag';
const TAX_TRANSFER_DIR = TAX_TRANSFER_PATH . '/inhalt.json';

function tax_transfer_file(string $table): string
{
    return TAX_TRANSFER_PATH . '/' . $table . '.tsv.gz';
}

/**
 * Row counts per table, as counted at write-out.
 *
 * Without them the import would not know when it is done — a gzip file does
 * not tell you how many lines it contains without reading it in full. And
 * that is exactly what it is meant to avoid.
 */
function tax_transfer_directory(): array
{
    if (!is_readable(TAX_TRANSFER_DIR)) {
        return [];
    }
    $d = json_decode((string) file_get_contents(TAX_TRANSFER_DIR), true);
    return is_array($d) ? $d : [];
}

/**
 * The name of the attribute for unbuffered reading.
 *
 * PHP 8.5 marked the old constant as deprecated, older versions do not know
 * the new one — and the server may well be running an older version than
 * here.
 */
function tax_transfer_unbuffered(): int
{
    return defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
        ? constant('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
        : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
}

/** Escape one value for the file. */
function tax_transfer_value(mixed $value): string
{
    if ($value === null) {
        return '\\N';
    }
    return str_replace(
        ['\\',   "\t",  "\n",  "\r"],
        ['\\\\', '\\t', '\\n', '\\r'],
        (string) $value
    );
}

/** A whole line. */
function tax_transfer_row(array $row): string
{
    return implode("\t", array_map('tax_transfer_value', $row));
}

/** Undo the escaping. */
function tax_transfer_decode(string $value): ?string
{
    if ($value === '\\N') {
        return null;
    }
    return str_replace(
        ['\\t', '\\n', '\\r', '\\\\'],
        ["\t",  "\n",  "\r",  '\\'],
        $value
    );
}

/**
 * Import one chunk: the first table that is not yet complete.
 *
 * HOW IT FINDS ITS PLACE AGAIN AFTER AN ABORT: it doesn't — it recounts.
 * How many rows already sit in the table, the database knows itself; that
 * many lines the file skips, then it continues. No progress record that
 * could drift apart from reality.
 *
 * Towards the end the skipping costs a moment, because the file is read
 * from the start every time — with 400,000 lines a few seconds without any
 * database access. The price of bookkeeping that cannot lie.
 *
 * @return array{0:string,1:string} Kind and text of the message
 */
function tax_transfer_chunk(PDO $pdo, int $seconds): array
{
    $directory = tax_transfer_directory();
    $deadline = microtime(true) + $seconds;

    foreach (tax_transfer_tables() as $table) {
        $expected = (int) ($directory[$table] ?? 0);
        if ($expected === 0) {
            continue;
        }
        // A file that arrived before its migration: say so, instead of a
        // bare „Table doesn't exist" — or of „vollständig", which would lie.
        if (!table_exists($pdo, $table)) {
            throw new RuntimeException(t('Die Tabelle %s gibt es noch nicht — erst die Migrationen einspielen.', $table));
        }
        $present = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();

        if ($expected === 0 || $present >= $expected) {
            continue;
        }

        $file = tax_transfer_file($table);
        if (!is_readable($file)) {
            throw new RuntimeException("Datei fehlt: $file");
        }

        $handle = gzopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Datei lässt sich nicht öffnen: $file");
        }

        $header = gzgets($handle);
        if ($header === false) {
            gzclose($handle);
            throw new RuntimeException("Datei ist leer: $file");
        }
        $columns = explode("\t", rtrim($header, "\r\n"));

        // Skip what has already been imported.
        for ($i = 0; $i < $present; $i++) {
            if (gzgets($handle) === false) {
                gzclose($handle);
                throw new RuntimeException("Datei endet früher, als die Tabelle voll ist ($table).");
            }
        }

        /* IS THE TABLE REALLY A PREFIX OF THIS FILE?
         *
         * The counting above rests on that assumption, and it broke twice
         * on 26.08.2026. First when the GBIF backbone was widened from
         * fungi to all kingdoms; then, more quietly, on map_tax_links,
         * which had grown from 36,243 to 36,971 rows: the server held the
         * old 36,243, the tool read „incomplete", skipped 36,243 lines of
         * the NEW file and inserted rows that had long been there. What
         * came back was „Duplicate entry '850316' for key 'PRIMARY'" —
         * true, and saying nothing about the cause.
         *
         * A FIRST GUARD CHECKED THE FIRST LINE OF THE FILE and was too
         * weak for exactly this case: a table can be a SUBSET of the file
         * without being its BEGINNING, and then the first row is present
         * while a later one collides.
         *
         * The question that really has to be asked is about the line at
         * the RESUME POSITION: the next row to be written must not be in
         * the table yet. That is one query per chunk, and it catches every
         * shape of mismatch — different order, grown content, foreign
         * origin. It records nothing and stays within the principle above:
         * it asks.
         *
         * The peeked line is kept and handed to the batch below, otherwise
         * it would be lost — the stream cannot be pushed back. */
        $pending = null;
        if ($present > 0) {
            $pending = gzgets($handle);
            if ($pending !== false) {
                $nextKey = explode("\t", rtrim($pending, "\r\n"))[0] ?? '';
                $check = $pdo->prepare("SELECT 1 FROM $table WHERE `{$columns[0]}` = ? LIMIT 1");
                $check->execute([$nextKey]);
                if ($check->fetchColumn() !== false) {
                    gzclose($handle);
                    throw new RuntimeException(
                        "$table enthält " . number_format($present, 0, ',', '.')
                        . ' Zeilen, die nicht der Anfang dieser Übertragung sind —'
                        . ' der nächste Eintrag (' . $columns[0] . ' ' . $nextKey
                        . ') steht schon darin. Bitte die Tabelle oben erst leeren,'
                        . ' dann neu einspielen.');
                }
            }
        }

        // Per statement as many rows as keep the number of placeholders
        // within bounds — MySQL does not take arbitrarily many.
        $perBatch = max(50, (int) floor(2000 / max(1, count($columns))));
        $tuple    = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $prefix   = 'INSERT INTO ' . $table . ' (`' . implode('`,`', $columns) . '`) VALUES ';

        $added    = 0;
        $done = false;

        while (!$done && microtime(true) < $deadline) {
            $values  = [];
            $rows = 0;

            while ($rows < $perBatch) {
                // The line peeked at above comes first — it has already
                // been read out of the stream and cannot be put back.
                $row = $pending ?? gzgets($handle);
                $pending = null;
                if ($row === false) { $done = true; break; }

                $fields = explode("\t", rtrim($row, "\r\n"));
                if (count($fields) !== count($columns)) {
                    gzclose($handle);
                    throw new RuntimeException(sprintf(
                        'Zeile mit %d statt %d Feldern in %s, Zeile %d.',
                        count($fields), count($columns), $table, $present + $added + $rows + 1
                    ));
                }

                foreach ($fields as $f) {
                    $values[] = tax_transfer_decode($f);
                }
                $rows++;
            }

            if ($rows === 0) break;

            $pdo->prepare($prefix . implode(',', array_fill(0, $rows, $tuple)))
                ->execute($values);
            $added += $rows;
        }

        gzclose($handle);

        $total = $present + $added;
        return [$total >= $expected ? 'success' : 'hint', sprintf(
            '%s: %s von %s Zeilen%s',
            $table,
            number_format($total, 0, ',', '.'),
            number_format($expected, 0, ',', '.'),
            $total >= $expected ? ' — vollständig.' : ' …'
        )];
    }

    return ['success', 'Alle Tabellen sind vollständig.'];
}

/** State of a table: how many rows are in and how many belong in. */
function tax_transfer_progress(PDO $pdo, string $table): array
{
    $file = tax_transfer_file($table);

    return [
        'table'   => $table,
        'file'     => $file,
        'present' => is_readable($file),
        'size'   => is_readable($file) ? (int) filesize($file) : 0,
        /* THE CODE GOES LIVE BEFORE THE MIGRATION. A table named in
           tax_transfer_tables() whose migration is still pending (the key
           tables of 0157, 08.10.2026) must not throw here: this runs on
           every load of the administration, and a throw breaks the page
           off before its scripts — every tab dead, the migrations tab too. */
        'loaded'      => table_exists($pdo, $table)
            ? (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() : 0,
    ];
}
