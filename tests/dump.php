<?php
/**
 * Tests the database dump — and does so by actually restoring it.
 *
 *   php tests/dump.php
 *
 * WHY LOOKING AT THE FILE IS NOT ENOUGH. A dump that looks plausible and
 * aborts at line 4000 when restored is worthless — and you only notice
 * exactly that in the moment you need it. That is why this test creates a
 * throwaway database, restores the dump there and compares row by row with
 * the original.
 *
 * IT SKIPS ITSELF when the mysql binary is missing or the account may not
 * create a database. On the live server both are the case, and a test that
 * glows red there without anything being broken is one nobody believes
 * afterwards.
 *
 * THE PASSWORD GOES THROUGH A DEFAULTS FILE, not through the command line.
 * Arguments sit in the process list and are thereby readable for every
 * other user of the same machine.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/dump.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "\nDatabase dump\n";

// ---------------------------------------------------------------------------
echo "\nValues become SQL literals\n";

check(dump_value($pdo, null) === 'NULL', 'null becomes NULL, not the empty string');
check(dump_value($pdo, 42) === '42', 'integer stays bare');
check(dump_value($pdo, '42') === '42', 'integer as string too');
check(dump_value($pdo, '-7') === '-7', 'negative integer');

// The three cases in which "looks like a number" would have destroyed the value.
check(dump_value($pdo, '007') === "'007'", 'leading zero stays a string');
check(dump_value($pdo, '1e5') === "'1e5'", '1e5 is not 100000');
check(dump_value($pdo, '47.441900') === "'47.441900'",
    'decimal stays a string — otherwise the decimal places would be lost');

check(dump_value($pdo, "O'Brien") === "'O\\'Brien'", 'apostrophe is escaped');
check(dump_value($pdo, 'Krün') === "'Krün'", 'umlaut remains');
check(str_starts_with(dump_value($pdo, "\xFF\xFE\x00"), '0x'),
    'invalid UTF-8 becomes a hex number instead of garbage data');

// ---------------------------------------------------------------------------
echo "\nThe right tables are included\n";

$tables = dump_tables($pdo);
check($tables !== [], 'tables are found at all');

foreach (['map_points', 'map_lists', 'map_users', 'map_migrations'] as $must) {
    check(($tables[$must]['included'] ?? false) === true, "$must is included");
}
foreach (['map_tax_col', 'map_tax_gbif', 'map_tokens', 'map_login_attempts'] as $not) {
    if (!isset($tables[$not])) {
        echo "  --    $not does not exist here\n";
        continue;
    }
    check($tables[$not]['included'] === false, "$not stays out");
    check(($tables[$not]['reason'] ?? '') !== '', "$not names a reason");
}

// Every omitted table must also exist. Otherwise DUMP_EXCLUDED holds a
// name that was once correct — and the table that replaced it would be in
// the dump unnoticed.
foreach (array_keys(DUMP_EXCLUDED) as $name) {
    check(isset($tables[$name]), "omitted table $name really exists");
}

// ---------------------------------------------------------------------------
echo "\nAnd now actually restored\n";

$mysql = null;
foreach (['/Applications/MAMP/Library/bin/mysql80/bin/mysql', 'mysql'] as $candidate) {
    $path = str_contains($candidate, '/') ? $candidate : trim((string) @shell_exec('command -v mysql'));
    if ($path !== '' && is_executable($path)) { $mysql = $path; break; }
}

if ($mysql === null) {
    echo "  (skipped — no mysql invocation found)\n";
} else {
    $db      = $config['db'];
    $probe   = $db['name'] . '_dumpcheck';
    $dir     = sys_get_temp_dir() . '/karte-abzug-' . getmypid();
    @mkdir($dir, 0700, true);
    $cnf     = $dir . '/my.cnf';
    $sql     = $dir . '/abzug.sql';

    // 0600 before anything is in it.
    touch($cnf);
    chmod($cnf, 0600);
    file_put_contents($cnf, "[client]\n"
        . 'user=' . $db['user'] . "\n"
        . 'password=' . $db['pass'] . "\n"
        . (empty($db['socket']) ? 'host=' . $db['host'] . "\nport=" . $db['port'] . "\n"
                                : 'socket=' . $db['socket'] . "\n"));

    $call = static function (string $arguments) use ($mysql, $cnf): array {
        $out = [];
        $rc  = 0;
        exec(escapeshellarg($mysql) . ' --defaults-extra-file=' . escapeshellarg($cnf)
            . ' ' . $arguments . ' 2>&1', $out, $rc);
        return [$rc, implode("\n", $out)];
    };

    [$rc, $message] = $call('-e ' . escapeshellarg(
        "DROP DATABASE IF EXISTS `$probe`; CREATE DATABASE `$probe` "
        . 'DEFAULT CHARACTER SET utf8mb4'));

    if ($rc !== 0) {
        echo "  (skipped — the throwaway database cannot be created)\n";
        echo '        ' . trim($message) . "\n";
    } else {
        $handle = fopen($sql, 'wb');
        $tally  = dump_write($pdo, static function (string $s) use ($handle): void {
            fwrite($handle, $s);
        });
        fclose($handle);

        check(filesize($sql) > 0, 'the dump is not empty (' . filesize($sql) . ' bytes)');

        [$rc, $message] = $call(escapeshellarg($probe) . ' < ' . escapeshellarg($sql));
        check($rc === 0, 'the dump can be restored'
            . ($rc === 0 ? '' : ' — ' . trim($message)));

        if ($rc === 0) {
            // Compare row counts — not the estimated ones from
            // information_schema, but counted ones.
            $probePdo = new PDO(
                'mysql:' . (empty($db['socket']) ? 'host=' . $db['host'] . ';port=' . $db['port']
                                                 : 'unix_socket=' . $db['socket'])
                . ';dbname=' . $probe . ';charset=utf8mb4',
                $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            foreach ($tally as $table => $expected) {
                $found = (int) $probePdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                check($found === $expected, "$table: $expected rows arrived"
                    . ($found === $expected ? '' : " — there are $found"));
            }

            // And the content, not just the count: a coordinate that drifts
            // a bit during the dump would be exactly the bug this
            // application is meant to fix.
            //
            // ROW BY ROW IN PHP, NOT via MD5(GROUP_CONCAT(...)). That is how
            // it stood here, and it was false safety: GROUP_CONCAT cuts off
            // at group_concat_max_len, 1024 bytes by default. Measured, the
            // hash thereby covered 35 of 156 points — the assurance "digit
            // for digit" in truth checked the first thirty and stayed
            // silent about the rest. No warning, no error, and with a
            // growing inventory the checked share keeps shrinking.
            $all = static fn(PDO $p): array => $p->query(
                'SELECT * FROM map_points ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

            $rowsHere  = $all($pdo);
            $rowsThere = $all($probePdo);

            check($rowsHere == $rowsThere,
                'the points are the same digit for digit (all '
                . count($rowsHere) . ', not just the first ones)');

            // The tables that were meant to stay out must not show up
            // there either.
            $there = $probePdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach (array_keys(DUMP_EXCLUDED) as $name) {
                check(!in_array($name, $there, true), "$name did not come along");
            }
        }

        $call('-e ' . escapeshellarg("DROP DATABASE IF EXISTS `$probe`"));
    }

    @unlink($cnf);
    @unlink($sql);
    @rmdir($dir);
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
