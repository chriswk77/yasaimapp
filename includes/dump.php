<?php
/**
 * Database dump: our own tables as SQL.
 *
 * WHAT FOR. Having the same state locally as on the server — to reproduce a
 * bug that only shows up with real data, or to have something in hand before
 * a risky change.
 *
 * NO mysqldump. The hoster provides no shell, and exec() is routinely
 * disabled on shared servers. So it has to work from within PHP, using
 * nothing but the connection that is open anyway.
 *
 * THE TAXONOMY STAYS OUT, and that is the whole trick of this file.
 * Measured, it is 481 of the 482 MB — the actual data is under one
 * megabyte. A dump "of everything" would be 99.9% a copy of what was
 * CREATED LOCALLY anyway and uploaded from there. It would therefore crawl
 * through a shared server's pipe for hours, only to hand back at the end
 * what you already have. Whoever really needs the taxonomy fresh takes
 * admin/tax_export.php and "Taxonomie einspielen" — that path exists for this.
 *
 * WHAT IS LEFT OUT AND WHY is listed in DUMP_EXCLUDED, each line with
 * its reason. Everything else comes along: the tables are QUERIED from the
 * database, not enumerated here. A fixed list would have silently lost
 * every table that gets added next month — and it would only have been
 * noticed once something is missing locally that nobody had missed.
 *
 * THE DUMP IS THE MOST SENSITIVE THING THIS APPLICATION PRODUCES: all find
 * locations at once, plus the password hashes. It is therefore NEVER
 * written to disk, but streamed directly to the browser — a file under the
 * web directory would only stay secret as long as nobody guesses its name.
 */

declare(strict_types=1);

/**
 * What does not belong in the dump, and why.
 *
 * The text is not decoration: it stands in the admin area next to the table,
 * and it is the answer to the question you will ask yourself in half a year.
 */
const DUMP_EXCLUDED = [
    'map_tax_col'          => 'Taxonomie — örtlich erzeugt, von dort hochgeladen',
    'map_tax_gbif'         => 'Taxonomie — örtlich erzeugt, von dort hochgeladen',
    'map_tax_dgfm'         => 'Taxonomie — örtlich erzeugt, von dort hochgeladen',
    'map_tax_links' => 'Taxonomie — örtlich erzeugt, von dort hochgeladen',
    'map_tax_plants'     => 'Taxonomie — örtlich erzeugt, von dort hochgeladen',
    'map_tax_lichens'     => 'Taxonomie — örtlich erzeugt, von dort hochgeladen',

    // LOGIN TOKENS DO NOT BELONG IN A FILE. They are valid keys: whoever
    // holds them is logged in without knowing a password. Locally they are
    // useless anyway — the remember-me cookie belongs to a different address.
    // Both together means: they cost nothing and would be pure risk.
    'map_tokens'           => 'gültige Anmeldemarken — gehören in keine Datei',

    // FOR THE SAME REASON AS THE LOGIN TOKENS, only worse: a cloud token
    // opens not this account but the user's ENTIRE cloud storage — including
    // everything that has nothing to do with this application. A dump
    // containing a dozen of them is a key ring. Whoever works locally
    // reconnects; that costs two clicks and is the whole price.
    'map_cloud_accounts'   => 'Cloud-Zugänge — Token öffnen fremden Speicher',

    // Throttling state, bound to this server and meaningless anywhere else.
    'map_login_attempts'   => 'Drosselungszähler, nur hier gültig',
];

/** How many bytes an INSERT statement gets at most. */
const DUMP_CHUNK_BYTES = 262144;

/** Unbuffered queries — the constant's name changed with PHP 8.4. */
function dump_unbuffered(): int
{
    return defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
        ? constant('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
        : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
}

/**
 * All tables of the database, with row count and size.
 *
 * @return array<string, array{rows:int, bytes:int, included:bool, reason:?string}>
 */
function dump_tables(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT table_name, table_rows, data_length + index_length AS bytes
           FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_type = "BASE TABLE"
          ORDER BY table_name'
    );

    $out = [];
    foreach ($stmt as $row) {
        // information_schema writes the column name in upper or lower case
        // depending on the version. Catching both is cheaper than relying on it.
        $name = (string) ($row['table_name'] ?? $row['TABLE_NAME']);
        $out[$name] = [
            'rows' => (int) ($row['table_rows'] ?? 0),
            'bytes'  => (int) ($row['bytes'] ?? 0),
            'included'  => !isset(DUMP_EXCLUDED[$name]),
            'reason'  => DUMP_EXCLUDED[$name] ?? null,
        ];
    }
    return $out;
}

/**
 * A value as an SQL literal.
 *
 * NOT EVERYTHING IN QUOTES. It would work for numbers — MySQL accepts '42'
 * for an INT column too — but the dump should stay readable, and comparing
 * two versions should not fail over quotation marks.
 *
 * BINARY AS A HEX NUMBER. Today no column holds binary content — the record
 * photos live as files, the table only holds their name. Should one ever be
 * added, a blind quote() would turn the dump into silent data garbage:
 * invalid UTF-8 inside a string does not survive the round trip. The check
 * costs one call per value and spares a bug you only notice on the broken
 * image.
 */
function dump_value(PDO $pdo, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    $text = (string) $value;

    // Whole numbers without a leading zero may stand bare. Everything else —
    // "007", "1e5", "0.10" — stays a string, otherwise the value changes.
    if (preg_match('/^-?(0|[1-9][0-9]{0,17})$/', $text) === 1) {
        return $text;
    }

    if (preg_match('//u', $text) !== 1) {
        return '0x' . bin2hex($text);
    }

    return $pdo->quote($text);
}

/**
 * Produce the dump and emit it piece by piece.
 *
 * PIECE BY PIECE VIA A CALLBACK, not returned as a string: a table with a
 * hundred thousand rows would otherwise sit in memory twice — once as raw
 * data and once as SQL. The callback writes to where it belongs, and memory
 * stays at the size of one piece.
 *
 * @param callable(string):void $out
 * @return array<string,int>  rows per table
 */
function dump_write(PDO $pdo, callable $out, ?array $tables = null): array
{
    $all = dump_tables($pdo);
    $names = $tables ?? array_keys(array_filter($all, static fn($t) => $t['included']));

    $out("-- Abzug der Karte, " . date('Y-m-d H:i:s') . "\n");
    $out("-- Nur die eigenen Tabellen. Die Taxonomie bleibt draussen —\n");
    $out("-- sie entsteht oertlich und wird von dort hochgeladen.\n");
    $out("--\n-- Einspielen:  mysql -u BENUTZER -p DATENBANK < dieseDatei.sql\n");
    $out("-- ACHTUNG: Die aufgefuehrten Tabellen werden dabei GELEERT.\n\n");

    // Set the framing conditions and take them back at the end. Without
    // FOREIGN_KEY_CHECKS=0 the import fails on the order of the tables —
    // a point references a user who only comes later.
    $out("SET NAMES utf8mb4;\n");
    $out("SET @alte_fk = @@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS = 0;\n");
    $out("SET @alter_modus = @@SQL_MODE, SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

    $tally = [];

    foreach ($names as $table) {
        // The name comes from information_schema, not from outside. Checked
        // anyway: this function is public, and a table name cannot be bound
        // as a placeholder.
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            throw new InvalidArgumentException('Unbrauchbarer Tabellenname.');
        }

        $created = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);

        $out("\n--\n-- $table\n--\n\n");
        $out("DROP TABLE IF EXISTS `$table`;\n");
        $out(($created[1] ?? '') . ";\n\n");

        $pdo->setAttribute(dump_unbuffered(), false);
        $read = $pdo->query('SELECT * FROM `' . $table . '`');

        $columns = null;
        $header  = '';
        $chunk   = [];
        $length  = 0;
        $n       = 0;

        // $header BY REFERENCE. It is only settled once the first row has
        // revealed the column names — a copy taken when creating the
        // callback would have stayed empty forever, and the dump would
        // consist of value parentheses with no INSERT in front.
        $flush = static function () use (&$chunk, &$length, &$header, $out): void {
            if ($chunk === []) {
                return;
            }
            $out($header . implode(",\n", $chunk) . ";\n");
            $chunk = [];
            $length = 0;
        };

        foreach ($read as $row) {
            if ($columns === null) {
                $columns = array_keys($row);
                $header = 'INSERT INTO `' . $table . '` (`'
                      . implode('`, `', $columns) . "`) VALUES\n";
            }

            $values = [];
            foreach ($columns as $s) {
                $values[] = dump_value($pdo, $row[$s]);
            }
            $record = '(' . implode(',', $values) . ')';

            $chunk[] = $record;
            $length += strlen($record) + 2;
            $n++;

            // Not one statement per row (slow to import) and not one for
            // everything (blows max_allowed_packet). A quarter megabyte is
            // neither.
            if ($length >= DUMP_CHUNK_BYTES) {
                $flush();
            }
        }
        $flush();

        $pdo->setAttribute(dump_unbuffered(), true);
        $tally[$table] = $n;
    }

    $out("\nSET FOREIGN_KEY_CHECKS = @alte_fk;\n");
    $out("SET SQL_MODE = @alter_modus;\n");
    $out("\n-- Ende.\n");

    return $tally;
}

/** What the file should be called. */
function dump_filename(): string
{
    return 'karte-' . date('Y-m-d-Hi') . '.sql';
}
