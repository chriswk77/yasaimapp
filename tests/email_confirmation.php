<?php
/**
 * E-mail confirmation (migration 0116): the link after registration, a
 * new address that waits until its link is followed, the dead ends.
 * Locally no mail leaves the machine — mail_send writes data/mail.log —
 * and the token comes back from email_confirmation_send for the test.
 *
 *   php tests/email_confirmation.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email_confirmation.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo '  ', $b ? 'ok    ' : 'FAIL  ', $what, "\n";
    if (!$b) $failed++;
}

if (!email_confirmation_ready($pdo)) {
    echo "Migration 0116 is not applied — nothing to test.\n";
    exit(1);
}

$pdo->exec("DELETE FROM map_users WHERE email LIKE 'pruef-mail%@pruefung.local'");
$pdo->exec("DELETE FROM map_excursion_guests WHERE email = 'pruef-mail-gast@pruefung.local'");
$pw = bin2hex(random_bytes(8)) . 'Aa1!';
$u = account_create('pruef-mail@pruefung.local', $pw, ['first_name' => 'PRUEF', 'last_name' => 'Mail'], null);
$other = account_create('pruef-mail-andere@pruefung.local', $pw, ['first_name' => 'PRUEF', 'last_name' => 'Andere'], null);
$row = static fn(): array => $pdo->query("SELECT email, email_confirmed_at, email_pending, email_token FROM map_users WHERE id = $u")->fetch();

try {
    echo "After registration:\n";
    check($row()['email_confirmed_at'] === null, 'a fresh account is unconfirmed');
    $first = email_confirmation_send($pdo, $u);
    check($first['sent'] && $first['to'] === 'pruef-mail@pruefung.local', 'the link goes to the account\'s own address');
    check($row()['email_token'] === hash('sha256', $first['token']), 'only the hash is stored, never the secret');
    $log = (string) @file_get_contents(PROJECT_PATH . '/data/mail.log');
    check(str_contains($log, 'pages/confirm_email.php?t=' . $first['token']), 'locally the mail lands in data/mail.log, link and all');
    $tooSoon = false;
    try { email_confirmation_send($pdo, $u); } catch (InvalidArgumentException) { $tooSoon = true; }
    check($tooSoon, 'a second mail within two minutes is refused');
    check(email_confirmation_finish($pdo, str_repeat('0', 64)) === null, 'an unknown link confirms nothing');
    check(email_confirmation_finish($pdo, $first['token']) === $u && $row()['email_confirmed_at'] !== null,
        'following the link confirms the address');
    check(email_confirmation_finish($pdo, $first['token']) === null, 'the link works once');

    echo "A new address:\n";
    $pending = account_person_email_update($pdo, $u, 'PRUEF', 'Mail', 'pruef-mail-neu@pruefung.local', $pw);
    check($pending === 'pruef-mail-neu@pruefung.local', 'the change reports the pending address');
    check($row()['email'] === 'pruef-mail@pruefung.local' && $row()['email_pending'] === 'pruef-mail-neu@pruefung.local',
        'until the link is followed the old address stays the login');
    $pdo->exec("UPDATE map_users SET email_token_expires = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = $u");
    $expired = $pdo->query("SELECT email_token FROM map_users WHERE id = $u")->fetchColumn();
    check($expired !== null, 'a link was minted for the new address');
    $again = email_confirmation_send($pdo, $u);
    check($again['to'] === 'pruef-mail-neu@pruefung.local', 'sent again, the link goes to the PENDING address');
    check(email_confirmation_finish($pdo, $again['token']) === $u
        && $row()['email'] === 'pruef-mail-neu@pruefung.local' && $row()['email_pending'] === null,
        'following it makes the new address the login');

    echo "The guest who registers later:\n";
    require_once __DIR__ . '/../includes/excursions.php';
    $e1 = (int) $pdo->query('SELECT id FROM map_excursions ORDER BY id LIMIT 1')->fetchColumn();
    $e2 = (int) $pdo->query('SELECT id FROM map_excursions ORDER BY id DESC LIMIT 1')->fetchColumn();
    if ($e1 > 0 && $e2 > 0) {
        $g1 = excursion_guest_register($pdo, $e1, 'PRUEF', 'Gast', 'pruef-mail-gast@pruefung.local');
        $g2 = excursion_guest_register($pdo, $e2, 'PRUEF', 'Gast', 'pruef-mail-gast@pruefung.local');
        $guest = account_create('pruef-mail-gast@pruefung.local', $pw, ['first_name' => 'PRUEF', 'last_name' => 'Gast'], null);
        $tiedTo = static fn(int $g): ?int => (($v = $pdo->query("SELECT user_id FROM map_excursion_guests WHERE id = $g")->fetchColumn()) === null ? null : (int) $v);
        check(excursion_claim($pdo, $guest) === 0 && $tiedTo($g1) === null, 'unconfirmed, the address ties no guest row');
        $gt = email_confirmation_send($pdo, $guest)['token'];
        email_confirmation_finish($pdo, $gt);
        check($tiedTo($g1) === $guest && $tiedTo($g2) === $guest,
            'following the link ties the guest rows of BOTH excursions at once');
        $pdo->exec("DELETE FROM map_excursion_guests WHERE id IN ($g1, $g2)");
        $pdo->exec("DELETE FROM map_users WHERE id = $guest");
    }

    echo "Taken in the meantime:\n";
    account_person_email_update($pdo, $u, 'PRUEF', 'Mail', 'pruef-mail-frei@pruefung.local', $pw);
    // The change mailed a link a moment ago; age it past the pause, then fetch a fresh one.
    $pdo->exec("UPDATE map_users SET email_token_expires = DATE_SUB(email_token_expires, INTERVAL 3 MINUTE) WHERE id = $u");
    $token = email_confirmation_send($pdo, $u)['token'] ?? null;
    $pdo->exec("UPDATE map_users SET email = 'pruef-mail-frei@pruefung.local' WHERE id = $other");
    $refused = false;
    try { email_confirmation_finish($pdo, $token); } catch (InvalidArgumentException) { $refused = true; }
    check($refused && $row()['email'] === 'pruef-mail-neu@pruefung.local' && $row()['email_pending'] === null,
        'an address taken by another account meanwhile is refused, and the pending one is dropped');
} finally {
    $pdo->exec("DELETE FROM map_users WHERE id IN ($u, $other)");
}

echo $failed === 0 ? "All good.\n" : "$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
