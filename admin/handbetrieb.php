<?php
/**
 * The manual deployment — for when GitHub Actions is down.
 *
 *   php admin/handbetrieb.php
 *   php admin/handbetrieb.php <sha>     with a base of one's own
 *
 * WHAT IT DOES. It works out which files the server is missing and copies
 * exactly those into _upload/, keeping their folders. What one then drags
 * into the FTP client is a finished parcel — no searching for which file
 * belongs where, and above all no forgetting one.
 *
 * WHY IT EXISTS. On 26.08.2026 GitHub Actions had a major outage: a push
 * created no run at all, a hand-started one sat in the queue for hours
 * without ever being assigned a runner, and it could be neither cancelled
 * nor force-cancelled. Two finished commits stood on origin and reached
 * nobody. The maintainer uploaded by hand — and had to work out for himself
 * which files those were.
 *
 * IT DOES NOT ASK THE MEMO FILE, and it cannot: .ht-deployed-sha lives on
 * the server under a name Apache refuses on principle (measured: 403), and
 * that is the point of the name. What it asks instead is GitHub — for the
 * last SUCCESSFUL deployment run. That is the same commit the memo holds,
 * as long as nobody has uploaded by hand in between; and after a manual
 * upload the two drift apart in the harmless direction, see below.
 *
 * THE EXCLUSION LIST IS READ, NOT COPIED. Which files stay home is decided
 * in .github/workflows/deploy.yml and nowhere else — a second copy of that
 * expression here would be the pair that drifts apart, and the day it did,
 * this script would quietly hand over a parcel with a test file or a
 * CLAUDE.md in it. If the key is not found, it stops rather than guessing.
 *
 * AFTER A MANUAL UPLOAD THE MEMO ON THE SERVER STAYS PUT. The next
 * automatic run therefore uploads the same files once more — same content,
 * no harm — and only then does the memo advance. That is deliberate:
 * pushing it forward by hand would make the next run see no difference at
 * all, and then something really would be missing.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
chdir($root);

/** Run a command and return its trimmed output, or null if it failed. */
function ask(string $command): ?string
{
    $output = [];
    $status = 0;
    exec($command . ' 2>/dev/null', $output, $status);
    return $status === 0 ? trim(implode("\n", $output)) : null;
}

function fail(string $text): never
{
    fwrite(STDERR, "\n  $text\n\n");
    exit(1);
}

// --- The exclusion list, out of the workflow ------------------------------
$workflow = @file_get_contents($root . '/.github/workflows/deploy.yml');
if ($workflow === false) {
    fail('deploy.yml ist nicht lesbar — ohne sie weiß ich nicht, was zu Hause bleibt.');
}
if (!preg_match("~^\s*AUSSCHLUSS:\s*'(.+)'\s*$~m", $workflow, $found)) {
    fail('In deploy.yml steht kein AUSSCHLUSS mehr. Wurde der Schlüssel umbenannt? '
       . 'Ich rate nicht — sonst lädst du am Ende Testdateien hoch.');
}
$exclude = $found[1];

$target = preg_match('~^\s*ZIEL:\s*(\S+)~m', $workflow, $z) ? $z[1] : '(unbekannt)';

// --- Where the server stands ----------------------------------------------
$base = $argv[1] ?? null;
$since = 'von Hand angegeben';
if ($base === null) {
    $base = ask('gh run list --workflow=deploy.yml --status=success --limit 1'
              . ' --json headSha --jq \'.[0].headSha\'');
    if ($base === null || $base === '') {
        fail('GitHub sagt nicht, wann zuletzt erfolgreich ausgeliefert wurde. '
           . 'Ist gh angemeldet? Sonst den Stand selbst angeben: '
           . 'php admin/handbetrieb.php <sha>');
    }
    $when = ask('gh run list --workflow=deploy.yml --status=success --limit 1'
              . ' --json createdAt --jq \'.[0].createdAt\'');
    $since = 'letzter erfolgreicher Lauf'
           . ($when ? ', ' . date('d.m.Y H:i', strtotime($when)) : '');
}
if (ask('git cat-file -t ' . escapeshellarg($base)) !== 'commit') {
    fail('„' . $base . '" ist hier kein Commit. Fehlt ein git fetch?');
}

$head = ask('git rev-parse HEAD') ?? fail('git antwortet nicht.');

// --- What is missing up there ---------------------------------------------
$raw = ask('git diff --name-only --diff-filter=d '
         . escapeshellarg($base) . ' ' . escapeshellarg($head));
$files = array_values(array_filter(
    explode("\n", (string) $raw),
    static fn(string $f): bool => $f !== '' && !preg_match('~' . $exclude . '~', $f)
));

printf("\n  Surfer steht auf %s (%s)\n", substr($base, 0, 7), $since);
printf("  Hier steht %s\n\n", substr($head, 0, 7));

if ($files === []) {
    echo "  Nichts zu tun — der Surfer ist auf dem Stand.\n\n";
    exit(0);
}

// --- The parcel -----------------------------------------------------------
//
// EMPTIED FIRST, and that matters: a leftover from the last time would
// travel along and overwrite something up there with an older version.
$parcel = $root . '/_upload';
if (is_dir($parcel)) {
    $old = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($parcel, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($old as $entry) {
        $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
    }
}

foreach ($files as $file) {
    $to = $parcel . '/' . $file;
    if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0777, true) && !is_dir(dirname($to))) {
        fail('Kann ' . dirname($to) . ' nicht anlegen.');
    }
    if (!copy($root . '/' . $file, $to)) {
        fail('Kann ' . $file . ' nicht kopieren.');
    }
    echo '  ' . $file . "\n";
}

$n = count($files);
printf("\n  %d Datei%s nach _upload/ kopiert.\n", $n, $n === 1 ? '' : 'en');
printf("  Deren INHALT nach %s ziehen — die Ordner darin sind schon richtig.\n\n", $target);
echo "  Danach bleibt die Merkdatei auf dem Surfer stehen, wo sie steht.\n"
   . "  Der nächste automatische Lauf lädt dieselben Dateien noch einmal\n"
   . "  hoch — gleicher Inhalt, und danach stimmt der Merkzettel wieder.\n\n";
