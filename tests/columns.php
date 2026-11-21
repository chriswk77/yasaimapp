<?php
/**
 * Every column name in an SQL string really exists.
 *
 *   php tests/columns.php
 *
 * WHY THIS HAS TO EXIST. PHP sees a string, and the database only speaks
 * up when the statement actually runs. For two days the border between
 * code and schema ran straight through these strings — German columns,
 * English code — and this test was born from that. The schema is English
 * since 0063, but the failure mode outlives the border: a renamed or
 * mistyped column in an SQL string stays invisible until the one request
 * that runs it.
 *
 * AND IT HAS HAPPENED. In includes/auth.php once stood
 *
 *     SELECT COUNT(*) AS total, MAX(timestamp) AS latest
 *       FROM map_login_versuche
 *
 * while the column was called 'zeitpunkt' (today: attempted_at in
 * map_login_attempts). The statement is the login throttle, so LOGGING IN
 * was broken — everywhere, live included — and nobody noticed, because
 * whoever is already logged in never runs it.
 *
 * HOW IT TESTS. The vocabulary comes from the database itself: every
 * column of every map_* table, plus the table names, plus the aliases the
 * statement introduces with AS. In this project's SQL, keywords and
 * functions are written UPPERCASE and identifiers lowercase — that
 * separates the two without a parser.
 *
 * THE UNION OF ALL TABLES IS ALLOWED, not the columns of the one table
 * addressed. Deliberately coarser: binding a column to its table would
 * mean resolving joins and aliases properly, and every gap in that
 * produces a false alarm. It catches what it is for — a name that exists
 * nowhere at all, which is exactly what a renamed column is.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) {
        echo "  ok    $what\n";
    } else {
        $failed++;
        echo "  FAILED: $what\n";
    }
}

echo "\nColumn names in SQL strings\n\n";

// --- The vocabulary, straight from the database ---------------------------

$known = [];
$tables = $pdo->query("SHOW TABLES LIKE 'map\\_%'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $known[$table] = true;
    foreach ($pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll() as $c) {
        $known[$c['Field']] = true;
    }
}
check($tables !== [], count($tables) . ' tables read out of the database');

/* Lowercase words that are not columns: SQL that this project writes small,
   and the handful of words that appear inside SQL strings without being an
   identifier. */
$vocabulary = [
    // written lowercase in places despite being SQL
    'as', 'on', 'and', 'or', 'not', 'null', 'is', 'in', 'by', 'set', 'asc', 'desc',
    // values that stand as bare words in the statements
    'pcloud', 'utf8mb4', 'unicode', 'ci', 'innodb',
    // the legacy ledger, which migrations.php deliberately still reads:
    // the takeover from map_migrationen names a table and columns that no
    // longer exist in a migrated database — that is its purpose.
    'map_migrationen', 'datei', 'eingespielt_am',
];
foreach ($vocabulary as $w) {
    $known[$w] = true;
}

// --- Collect the SQL strings ----------------------------------------------

$files = [];
foreach ([__DIR__ . '/../includes', __DIR__ . '/../api/v1', __DIR__ . '/../admin',
          __DIR__ . '/../pages'] as $folder) {
    foreach (glob($folder . '/*.php') ?: [] as $f) {
        $files[] = $f;
    }
}
// A module's SQL is held to the same columns (modules/<name>/…, 21.11.2026).
foreach (glob(__DIR__ . '/../modules/*/{,includes/,api/,admin/,pages/}*.php', GLOB_BRACE) ?: [] as $f) {
    $files[] = $f;
}
foreach ([__DIR__ . '/../index.php', __DIR__ . '/../login.php'] as $f) {
    if (is_file($f)) {
        $files[] = $f;
    }
}

$statements = 0;
$found = [];
foreach ($files as $file) {
    $source = (string) file_get_contents($file);

    /* PHP NAMES ITS OWN STRINGS. A regular expression over the source
       cannot: it does not know whether a quote opens or closes, so half its
       matches run from the end of one string to the start of the next — and
       then it reports 'foreach' and 'printf' as missing columns. The
       tokenizer knows, and it costs one call. */
    $strings = [];
    foreach (token_get_all($source) as $token) {
        if (!is_array($token)) {
            continue;
        }
        if ($token[0] !== T_CONSTANT_ENCAPSED_STRING && $token[0] !== T_ENCAPSED_AND_WHITESPACE) {
            continue;
        }
        /* EXACTLY ONE CHARACTER OFF EACH END, not trim() with a character
           list: trim strips REPEATEDLY, so "… status = 'requested'" lost the
           closing quote of the SQL literal along with the PHP one — and the
           value inside then looked like a column name.
           AND ONLY WHERE THERE ARE QUOTES: a piece of an interpolated string
           ("… scope$url FROM map_books ORDER BY year, source") arrives
           without them, and cutting it anyway made 'sourc' a missing column. */
        $strings[] = [$token[0] === T_CONSTANT_ENCAPSED_STRING ? substr($token[1], 1, -1) : $token[1], $token[2]];
    }

    /* ALIASES ARE COLLECTED PER FILE, not per statement — because a
       statement is not always one string. container_shares() builds its
       SQL out of three pieces with a function call between them; the alias
       is introduced in the first and used in the third, and per string the
       third one knows nothing of it.

       Reading the pieces back together would mean following PHP's dot
       operator across function calls. The looser rule costs almost
       nothing: for a translated column name to slip through, it would have
       to be spelt exactly like an alias somewhere else in the same file. */
    $fileAliases = [];
    foreach ([
        '/\bAS\s+([a-z_][a-z0-9_]*)/i',
        '/\b(?:FROM|JOIN|UPDATE)\s+map_[a-z0-9_]+\s+(?!AS\b)([a-z][a-z0-9_]*)/i',
        '/\)\s+([a-z_][a-z0-9_]*)/',
    ] as $pattern) {
        if (preg_match_all($pattern, $source, $a)) {
            foreach ($a[1] as $alias) {
                $fileAliases[mb_strtolower($alias)] = true;
            }
        }
    }

    foreach ($strings as [$sql, $line]) {
        if (!preg_match('/\b(?:FROM|INTO|UPDATE|JOIN)\s+map_/i', $sql)) {
            continue;
        }
        $statements++;

        // Aliases the statement itself introduces, plus the file's.
        $local = $fileAliases;
        if (preg_match_all('/\bAS\s+([a-z_][a-z0-9_]*)/i', $sql, $a)) {
            foreach ($a[1] as $alias) {
                $local[mb_strtolower($alias)] = true;
            }
        }
        // "FROM map_users u" / "JOIN map_tokens t" — the short table alias.
        if (preg_match_all('/\b(?:FROM|JOIN|UPDATE)\s+map_[a-z0-9_]+\s+(?!AS\b)([a-z][a-z0-9_]*)/i', $sql, $a)) {
            foreach ($a[1] as $alias) {
                $local[mb_strtolower($alias)] = true;
            }
        }
        // "(SELECT …) listenzahl" — an alias without AS, which MySQL allows.
        if (preg_match_all('/\)\s+([a-z_][a-z0-9_]*)/', $sql, $a)) {
            foreach ($a[1] as $alias) {
                $local[mb_strtolower($alias)] = true;
            }
        }

        /* Strip everything that is not an identifier. THE SQL STRING
           LITERALS FIRST, and in both spellings: in a double-quoted PHP
           string they stand as 'osm', in a single-quoted one as \'osm\'.
           Without this the values inside a statement — 'species', 'gbif',
           'osm' — are reported as missing columns, and a test with a list
           of excuses that long is one nobody reads. */
        $bare = preg_replace([
            "/\\\\'[^']*\\\\'/",   // \'value\' inside a single-quoted PHP string
            "/'[^']*'/",                    // 'value' inside a double-quoted one
            '/\\\\"[^"]*\\\\"/',        // \"value\" — MySQL takes double quotes too
            '/"[^"]*"/',
            '/--[^\n]*/',                  // SQL line comment
            '/\/\*.*?\*\//s',           // SQL block comment
            '/:[a-z_]+/i',                  // named placeholder
        ], ' ', $sql) ?? $sql;

        foreach (preg_split('/[^a-zA-Z0-9_.]+/', $bare) ?: [] as $word) {
            // Behind the dot stands the column; in front of it the alias.
            if (str_contains($word, '.')) {
                $word = (string) substr(strrchr($word, '.') ?: '', 1);
            }
            if ($word === '' || strlen($word) < 2) {
                continue;
            }
            // UPPERCASE is a keyword or function in this project's SQL.
            if ($word === mb_strtoupper($word)) {
                continue;
            }
            if (is_numeric($word) || isset($known[$word]) || isset($local[$word])) {
                continue;
            }
            $where = str_replace(__DIR__ . '/../', '', $file) . ':' . $line;
            $found[$where . ' ' . $word] = "$where  '$word' is a column of no map_ table";
        }
    }
}

check($statements > 20, "$statements statements examined");
foreach ($found as $line) {
    echo "  $line\n";
}
check($found === [], 'every lowercase name in an SQL string is a column that exists');

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
