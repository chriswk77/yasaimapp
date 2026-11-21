<?php
/**
 * Migration mechanics.
 *
 * Every schema change is a file in migrations/. Applied files are recorded
 * in map_migrations and get skipped — the migrations themselves therefore
 * need not be repeatable.
 *
 * The convention comes from the neighboring project psv and prevented
 * exactly the mistake this is about there: an ALTER TABLE that runs
 * locally and never reaches the live server.
 *
 * NAMING SCHEME: NNNN_beschreibung.sql — a sequential, unique number up
 * front and nothing else. The next one is the highest assigned plus one.
 *
 * A date is no good as an identifier: several migrations come into being
 * on one day, and which of them runs first is then decided by the
 * description — alphabetically. Exactly that is what the live server's
 * initial setup failed on: taxonomie_jahre required a column that
 * verknuepfung_col creates, but ran first because of "t" before "v" and
 * aborted. Locally none of this was visible, because there they had been
 * applied in the order of writing; only an empty database demonstrates
 * the alphabetical order.
 *
 * The number is four digits, padded with zeros. Only then does the
 * alphabetical order match the numerical one — "10" would otherwise come
 * before "9".
 *
 * When renaming an already applied file, its name must move along in
 * map_migrations too. The name is the key there; if it stays put, the
 * migration starts running again.
 */

declare(strict_types=1);

function migrations_directory(): string
{
    return PROJECT_PATH . '/migrations';
}

/**
 * Every folder migrations lie in: the core's, then one per module that
 * brings its own (modules/<name>/migrations, 21.11.2026).
 *
 * A MODULE'S TABLES ARE THE MODULE'S — an installation without the folder
 * never creates them, and the core's files never mention them. ONE LEDGER
 * AND ONE NUMBERING ALL THE SAME: the run order is the file name's, across
 * all folders, and the ledger knows a file by its NAME, not by where it
 * lies. So a migration that moved from migrations/ into a module's folder
 * is the same migration and never runs again — and a module mints its
 * numbers from the one row everybody counts in.
 *
 * @return list<string>
 */
function migrations_directories(): array
{
    return array_merge([migrations_directory()], glob(PROJECT_PATH . '/modules/*/migrations', GLOB_ONLYDIR) ?: []);
}

/**
 * Where a migration lies — null where no folder holds a file of that name.
 * The core's folder is asked first: a name belongs to one file, and should
 * two ever carry it, the core's is the one that runs.
 */
function migration_path(string $file): ?string
{
    foreach (migrations_directories() as $directory) {
        $path = $directory . '/' . basename($file);
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

/**
 * All migration files in run order. It arises from the file name alone —
 * neither file date nor creation time play into it, and neither does the
 * folder (migrations_directories). Because all names begin with the same
 * four-digit numbering, an ordinary string comparison suffices for that.
 */
function migrations_all(): array
{
    $names = [];
    foreach (migrations_directories() as $directory) {
        foreach (glob($directory . '/*.sql') ?: [] as $file) {
            $names[basename($file)] = true;
        }
    }
    $names = array_keys($names);
    sort($names, SORT_STRING);
    return $names;
}

/**
 * The runner owns its ledger. It creates map_migrations itself and, on
 * first contact, takes the rows of the German predecessor over — INSERT
 * IGNORE, because 0063's rehearsals may have copied some already. The
 * predecessor stays standing for the OLD code until 0064 drops it: the
 * live cut-over applies 0063 through the old tool, which still records
 * into map_migrationen afterwards. Nothing here may fail on a fresh
 * database, where no German table ever existed.
 */
function migrations_ledger_ensure(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS map_migrations (
        file varchar(190) NOT NULL PRIMARY KEY,
        applied_at datetime NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    if ($pdo->query("SHOW TABLES LIKE 'map\\_migrationen'")->fetch() !== false) {
        $pdo->exec('INSERT IGNORE INTO map_migrations (file, applied_at)
                    SELECT datei, eingespielt_am FROM map_migrationen');
    }
}

/** Files already applied. Empty as long as the log does not yet exist. */
function migrations_applied(PDO $pdo): array
{
    migrations_ledger_ensure($pdo);
    try {
        $stmt = $pdo->query('SELECT file, applied_at FROM map_migrations');
        $result = [];
        foreach ($stmt as $row) {
            $result[$row['file']] = $row['applied_at'];
        }
        return $result;
    } catch (PDOException $e) {
        // 42S02 = table not present. That is the normal case before the
        // very first migration, not an error.
        if ($e->getCode() === '42S02') {
            return [];
        }
        throw $e;
    }
}

/** Files still open, in the order in which they must run. */
function migrations_pending(PDO $pdo): array
{
    $applied = migrations_applied($pdo);
    return array_values(array_filter(
        migrations_all(),
        static fn(string $file) => !isset($applied[$file])
    ));
}

/**
 * Split an SQL file into individual statements.
 *
 * A simple explode(';') is not enough: semicolons also occur in strings
 * and comments. Hence walk through the text once and keep track of where
 * we currently are.
 */
function sql_statements_split(string $sql): array
{
    $statements = [];
    $current     = '';
    $length      = strlen($sql);
    $i           = 0;

    while ($i < $length) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        // Line comments: -- to end of line, # to end of line
        if (($char === '-' && $next === '-') || $char === '#') {
            $end = strpos($sql, "\n", $i);
            $i = $end === false ? $length : $end + 1;
            continue;
        }

        // Block comment
        if ($char === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $length : $end + 2;
            continue;
        }

        // Take strings and quoted identifiers over in one piece
        if ($char === "'" || $char === '"' || $char === '`') {
            $closing = $char;
            $current .= $char;
            $i++;
            while ($i < $length) {
                $c = $sql[$i];
                if ($c === '\\' && $closing !== '`') {
                    $current .= $c . ($sql[$i + 1] ?? '');
                    $i += 2;
                    continue;
                }
                $current .= $c;
                $i++;
                if ($c === $closing) {
                    // A doubled delimiter is an escaped character, not the end.
                    if (($sql[$i] ?? '') === $closing) {
                        $current .= $closing;
                        $i++;
                        continue;
                    }
                    break;
                }
            }
            continue;
        }

        if ($char === ';') {
            if (trim($current) !== '') {
                $statements[] = trim($current);
            }
            $current = '';
            $i++;
            continue;
        }

        $current .= $char;
        $i++;
    }

    if (trim($current) !== '') {
        $statements[] = trim($current);
    }

    return $statements;
}

/**
 * Apply migrations one after another.
 *
 * Aborts at the first failed file and leaves it standing as open —
 * subsequent migrations usually build on it, and letting them run anyway
 * does more damage than the abort.
 *
 * No teardown on failure: MySQL cannot roll back DDL. Whoever writes a
 * migration must have tried it out locally beforehand.
 *
 * @return array<int, array{file: string, ok: bool, message: string, statements: int}>
 */
function migrations_apply(PDO $pdo, array $files): array
{
    $report = [];

    foreach ($files as $file) {
        $path = migration_path($file);

        if ($path === null) {
            $report[] = ['file' => $file, 'ok' => false, 'message' => 'Datei nicht gefunden.', 'statements' => 0];
            break;
        }

        $statements = sql_statements_split((string) file_get_contents($path));
        $executed = 0;

        try {
            foreach ($statements as $statement) {
                $pdo->exec($statement);
                $executed++;
            }

            $pdo->prepare('INSERT INTO map_migrations (file, applied_at) VALUES (?, NOW())')
                ->execute([$file]);

            $report[] = ['file' => $file, 'ok' => true, 'message' => 'Eingespielt.', 'statements' => $executed];
        } catch (PDOException $e) {
            $report[] = [
                'file'       => $file,
                'ok'          => false,
                'message'     => 'Abgebrochen bei Anweisung ' . ($executed + 1) . ': ' . $e->getMessage(),
                'statements' => $executed,
            ];
            break;
        }
    }

    return $report;
}
