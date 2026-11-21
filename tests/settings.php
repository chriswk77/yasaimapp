<?php
/**
 * Tests the settings tab — name and password (auth.php).
 *
 *   php tests/settings.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE FIRST NAME IS MANDATORY as at registration — it feeds display
 *   name and finder field.
 *
 *   THE PASSWORD first demands the old one (an open session on someone
 *   else's machine is not enough to lock somebody out) — and afterwards
 *   throws out ALL OTHER devices, one's own stays.
 *
 * Everything created is removed again at the end.
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
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

function rejected(callable $do, ?string &$reason = null): bool
{
    try {
        $do();
        return false;
    } catch (InvalidArgumentException $e) {
        $reason = $e->getMessage();
        return true;
    }
}

$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-einst@pruefung.local', ?, 'PRUEF', 'Einst', 'user', NOW())"
)->execute([password_hash('altes-passwort', PASSWORD_DEFAULT)]);
$userId = (int) $pdo->lastInsertId();

$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-einst2@pruefung.local', '', 'PRUEF', 'Nachbar', 'user', NOW())"
)->execute();
$neighbourId = (int) $pdo->lastInsertId();

try {
    echo "Name and e-mail address, one form:\n";
    check(rejected(fn() => account_person_email_update(
        $pdo, $userId, 'Christine', 'Beispiel', 'pruef-einst@pruefung.local', 'falsch')),
        'without the correct password nothing changes — not even the name');

    account_person_email_update($pdo, $userId, ' Christine ', ' Beispiel ',
        'pruef-einst@pruefung.local', 'altes-passwort');
    $me = $pdo->query("SELECT first_name, last_name, email FROM map_users WHERE id = $userId")->fetch();
    check($me['first_name'] === 'Christine' && $me['last_name'] === 'Beispiel',
        'name stored, surrounding whitespace stripped');
    check(user_display_name($me) === 'Christine Beispiel',
        'the display name is now the full name — the nick no longer exists');
    check(user_display_name(['first_name' => '', 'last_name' => '', 'email' => 'wer@da.local']) === 'wer@da.local',
        'without a name the display name falls back to the e-mail address');

    check(rejected(fn() => account_person_email_update(
        $pdo, $userId, '', 'X', 'pruef-einst@pruefung.local', 'altes-passwort')),
        'without a first name it does not work — it feeds display name and finder');

    echo "E-mail address:\n";
    check(rejected(fn() => account_person_email_update(
        $pdo, $userId, 'Christine', 'Beispiel', 'kein-at-zeichen', 'altes-passwort')),
        'an invalid address is rejected');
    check(rejected(fn() => account_person_email_update(
        $pdo, $userId, 'Christine', 'Beispiel', 'PRUEF-EINST2@pruefung.local', 'altes-passwort')),
        'the address of another account is off limits — even spelt differently');
    account_person_email_update($pdo, $userId, 'Christine', 'Beispiel',
        ' PRUEF-Einst-Neu@Pruefung.local ', 'altes-passwort');
    // Since migration 0116 a new address waits for its link (tests/email_confirmation.php).
    $stored = column_exists($pdo, 'map_users', 'email_pending')
        ? $pdo->query("SELECT email_pending FROM map_users WHERE id = $userId")->fetchColumn()
        : $pdo->query("SELECT email FROM map_users WHERE id = $userId")->fetchColumn();
    check($stored === 'pruef-einst-neu@pruefung.local',
        'the new address is taken — lower-cased and stripped, the way the login checks it');

    echo "Password:\n";
    check(rejected(fn() => account_password_update($pdo, $userId, 'falsch', 'neues-passwort-1', 'neues-passwort-1')),
        'without the correct old password nothing happens');
    check(rejected(fn() => account_password_update($pdo, $userId, 'altes-passwort', 'kurz', 'kurz')),
        'under 10 characters is rejected — the same limit as at registration');
    check(rejected(fn() => account_password_update($pdo, $userId, 'altes-passwort', 'neues-passwort-1', 'anders')),
        'a differing repetition is rejected');

    // Two device tokens: the "own" one (cookie of this call) and a foreign one.
    $_COOKIE[PERSISTENT_COOKIE] = 'device-eigen';
    $insert = $pdo->prepare(
        'INSERT INTO map_tokens (user_id, token_hash, device, created_at, valid_until)
         VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))'
    );
    $insert->execute([$userId, hash('sha256', 'device-eigen'), 'PRUEF eigenes']);
    $insert->execute([$userId, hash('sha256', 'device-fremd'), 'PRUEF fremdes']);
    $insert->execute([$neighbourId, hash('sha256', 'device-nachbar'), 'PRUEF Nachbar']);

    account_password_update($pdo, $userId, 'altes-passwort', 'neues-passwort-1', 'neues-passwort-1');
    $hash = (string) $pdo->query("SELECT password_hash FROM map_users WHERE id = $userId")->fetchColumn();
    check(password_verify('neues-passwort-1', $hash) && !password_verify('altes-passwort', $hash),
        'the new password holds, the old one no longer does');

    $remaining = $pdo->query(
        "SELECT device FROM map_tokens WHERE user_id = $userId ORDER BY device"
    )->fetchAll(PDO::FETCH_COLUMN);
    check($remaining === ['PRUEF eigenes'],
        'all other devices are signed out — one\'s own stay-token survives');
    check((int) $pdo->query(
        "SELECT COUNT(*) FROM map_tokens WHERE user_id = $neighbourId"
    )->fetchColumn() === 1,
        'foreign accounts remain untouched');
} finally {
    unset($_COOKIE[PERSISTENT_COOKIE]);
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
    $pdo->exec("DELETE FROM map_users WHERE id = $neighbourId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
