<?php
/**
 * Tests what a failure may tell the user (error_for_user() in helpers.php).
 *
 *   php tests/errors.php
 *
 * WHY THIS TEST EXISTS. On 25.11.2026, with the repository public, about
 * seventy places handed `$e->getMessage()` straight to the browser — and
 * because PDOException IS a RuntimeException, every `catch
 * (RuntimeException $e)` among them sent SQL along when the database
 * stumbled, every `catch (Throwable $e)` the server's file and line on a
 * TypeError. Since then they say error_for_user($e).
 *
 * TWO HALVES: what the function answers for each kind of failure,
 * measured on real exceptions (a real failing query, a real TypeError);
 * and a sweep over every file whose answer reaches a user, so a new
 * `json_error($e->getMessage())` is caught here and not in a stranger's
 * browser. The administration's own tools are left out on purpose — their
 * reader is the operator, and there the detail is the help.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';
require_once PROJECT_PATH . '/includes/cloud_moves.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

// The function logs what it hides; the log is not this test's business.
ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
$plain = t('Es ist ein Fehler aufgetreten.');

echo "\nWhat a failure tells the user\n\n";

check(error_for_user(new RuntimeException('Die Gruppe braucht einen Namen.')) === 'Die Gruppe braucht einen Namen.',
    'our own sentence goes through');
check(error_for_user(new InvalidArgumentException('Bitte einen Namen.')) === 'Bitte einen Namen.',
    'an InvalidArgumentException of ours too');

$pdoError = null;
try {
    $pdo->query('SELECT no_such_column FROM map_users');
} catch (PDOException $e) {
    $pdoError = $e;
}
check($pdoError instanceof RuntimeException, 'a PDOException IS a RuntimeException — the reason for all this');
check($pdoError !== null && error_for_user($pdoError) === $plain, 'SQL never reaches the user');

$typeError = null;
try {
    strlen([]);
} catch (Throwable $e) {
    $typeError = $e;
}
check($typeError !== null && error_for_user($typeError) === $plain, 'a TypeError (file and line) never does');

check(error_for_user(new RuntimeException('Fehlt: ' . PROJECT_PATH . '/data/users/1')) === $plain,
    'a message of ours with the server path in it is caught as well');
check(error_for_user(new RuntimeException('SQLSTATE[23000]: Duplicate entry')) === $plain,
    'and one that carries an SQLSTATE');

$iterator = null;
try {
    new RecursiveDirectoryIterator(PROJECT_PATH . '/no-such-folder');
} catch (Throwable $e) {
    $iterator = $e;
}
check($iterator !== null && error_for_user($iterator) === $plain, 'a directory iterator\'s complaint (it names the path)');

echo "\nA provider's refusal\n\n";

$refused = cloud_refused('Google Drive', '403', 'Insufficient Permission: Request had insufficient authentication scopes.');
check(error_for_user($refused) === t('%s lehnt ab (%s).', 'Google Drive', '403'), 'the user gets who and the code');
check(!str_contains(error_for_user($refused), 'Insufficient'), '— and none of the provider\'s free text');
check(str_contains($refused->getMessage(), 'Insufficient Permission'), 'the message keeps it, for the log');
check(str_contains(cloud_refused('OneDrive', 'itemNotFound', '')->getMessage(), '(itemNotFound)'),
    'the code stays in brackets, where onedrive.php and gdrive.php look for it');

$full = cloud_refused('Google Drive', '403', "The user's Drive storage quota has been exceeded.");
check(cloud_full_error($full->getMessage()), 'cloud_full_error() still recognises a full store');
check(error_for_user($full) === t('Der Speicher ist voll: %s nimmt nichts mehr an.', 'Google Drive'),
    'and the user is told in words');

check(error_for_user(new CloudMoveBlocked($refused->getMessage(), 0, $refused)) === error_for_user($refused),
    'a wrapper that repeats its cause is judged by the cause');

echo "\nNo raw message where a user reads it\n\n";

$root = dirname(__DIR__);
/* The administration's tools (pages/administration.php loads them only for
   an administrator) and the survey under admin/ are not on this list. */
$userSide = array_merge(
    glob("$root/api/v1/*.php"),
    glob("$root/pages/*.php"),
    ["$root/login.php", "$root/register.php", "$root/index.php", "$root/includes/backup.php"],
    array_map(static fn(string $n): string => "$root/includes/{$n}_content.php",
        ['archives', 'cloud', 'excursions', 'friends', 'given', 'groups', 'import', 'organize', 'settings', 'share', 'trash'])
);
$harmless = '~error_log|str_contains|str_starts_with|preg_match|fwrite|throw new|cloud_full_error~';
$leaks = [];
foreach ($userSide as $file) {
    if (!is_file($file)) {
        continue;
    }
    foreach (file($file) as $n => $line) {
        if (str_contains($line, '->getMessage()') && !preg_match($harmless, $line)) {
            $leaks[] = substr($file, strlen($root) + 1) . ':' . ($n + 1);
        }
    }
}
check($leaks === [], 'every user-side file says error_for_user($e)' . ($leaks === [] ? '' : ' — not: ' . implode(', ', $leaks)));

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
