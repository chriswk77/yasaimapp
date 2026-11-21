<?php
/**
 * „Passwort vergessen" (migration 0118): only a confirmed address gets a
 * link, the link sets the password once and throws the other devices
 * out, the request cannot be turned into a mail cannon. Locally no mail
 * leaves the machine — mail_send writes data/mail.log — and the token
 * comes back from password_reset_request for the test.
 *
 *   php tests/password_reset.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email_confirmation.php';
require_once __DIR__ . '/../includes/password_reset.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo '  ', $b ? 'ok    ' : 'FAIL  ', $what, "\n";
    if (!$b) $failed++;
}

if (!password_reset_ready($pdo)) {
    echo "Migration 0118 is not applied — nothing to test.\n";
    exit(1);
}

// A documentation address (TEST-NET-3), so the throttle rows are ours alone.
$_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(1, 254);
$ip = $_SERVER['REMOTE_ADDR'];
$pdo->exec("DELETE FROM map_users WHERE email LIKE 'pruef-reset%@pruefung.local'");
$pw = bin2hex(random_bytes(8)) . 'Aa1!';
$u = account_create('pruef-reset@pruefung.local', $pw, ['first_name' => 'PRUEF', 'last_name' => 'Reset'], null);
$open = account_create('pruef-reset-offen@pruefung.local', $pw, ['first_name' => 'PRUEF', 'last_name' => 'Offen'], null);
$pdo->exec("UPDATE map_users SET email_confirmed_at = NOW() WHERE id = $u");
$row = static fn(int $id): array => $pdo->query("SELECT password_hash, password_token, password_token_expires FROM map_users WHERE id = $id")->fetch();

try {
    echo "Asking for a link:\n";
    check(password_reset_request($pdo, 'pruef-reset-offen@pruefung.local') === null && $row($open)['password_token'] === null,
        'an unconfirmed address gets no link');
    check(password_reset_request($pdo, 'pruef-reset-niemand@pruefung.local') === null, 'an unknown address gets none');
    $token = password_reset_request($pdo, ' PRUEF-Reset@pruefung.local ');
    check($token !== null, 'a confirmed address gets one — case and blanks as the login forgives them');
    check($row($u)['password_token'] === hash('sha256', (string) $token), 'only the hash is stored, never the secret');
    $log = (string) @file_get_contents(PROJECT_PATH . '/data/mail.log');
    check(str_contains($log, 'pages/password_reset.php?t=' . $token), 'locally the mail lands in data/mail.log, link and all');
    check(password_reset_request($pdo, 'pruef-reset@pruefung.local') === null
        && $row($u)['password_token'] === hash('sha256', (string) $token),
        'a second request within two minutes sends nothing and keeps the first link');
    $pdo->exec("UPDATE map_users SET locked_at = NOW(), password_token_expires = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = $u");
    check(password_reset_request($pdo, 'pruef-reset@pruefung.local') === null, 'a locked account gets none');
    $pdo->exec("UPDATE map_users SET locked_at = NULL WHERE id = $u");
    check(password_reset_account($pdo, (string) $token) === null, 'an expired link belongs to nobody');
    $pdo->exec("UPDATE map_users SET password_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = $u");

    echo "Following it:\n";
    check(password_reset_account($pdo, str_repeat('0', 64)) === null && password_reset_account($pdo, 'x') === null,
        'an unknown or malformed link belongs to nobody');
    check((int) (password_reset_account($pdo, (string) $token)['id'] ?? 0) === $u, 'the link names its account');
    $refused = static function (string $new, string $repeat) use ($pdo, $token): bool {
        try { password_reset_finish($pdo, (string) $token, $new, $repeat); } catch (InvalidArgumentException) { return true; }
        return false;
    };
    check($refused('kurz', 'kurz'), 'a password under ten characters is refused');
    check($refused('lang-genug-1', 'lang-genug-2'), 'a repetition that differs is refused');
    check(password_reset_account($pdo, (string) $token) !== null, 'a refused form leaves the link alive');

    $pdo->prepare('INSERT INTO map_tokens (user_id, token_hash, device, created_at, valid_until) VALUES (?, ?, ?, NOW(), NULL)')
        ->execute([$u, hash('sha256', bin2hex(random_bytes(32))), 'PRUEF-Gerät']);
    $user = password_reset_finish($pdo, (string) $token, 'neues-passwort-1', 'neues-passwort-1');
    check((int) ($user['id'] ?? 0) === $u, 'the link sets the password and hands back the account');
    check(password_verify('neues-passwort-1', $row($u)['password_hash']), 'the new password holds');
    check($row($u)['password_token'] === null, 'the link is spent');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_tokens WHERE user_id = $u")->fetchColumn() === 0,
        'every other device of the account is thrown out');
    check(password_reset_finish($pdo, (string) $token, 'noch-ein-passwort', 'noch-ein-passwort') === null
        && password_verify('neues-passwort-1', $row($u)['password_hash']), 'the link works once');

    echo "Logged in on the spot:\n";
    $pdo->prepare('INSERT INTO map_login_attempts (identifier, ip, attempted_at) VALUES (?, ?, NOW())')
        ->execute(['pruef-reset@pruefung.local', $ip]);
    login_session($user);
    check((int) ($_SESSION['user_id'] ?? 0) === $u, 'the session carries the account');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_login_attempts WHERE identifier = 'pruef-reset@pruefung.local'")->fetchColumn() === 0,
        'the failed attempts of the forgotten password are forgiven');

    echo "A new address:\n";
    $pdo->exec("UPDATE map_users SET password_token_expires = NULL WHERE id = $u");
    $late = password_reset_request($pdo, 'pruef-reset@pruefung.local');
    account_person_email_update($pdo, $u, 'PRUEF', 'Reset', 'pruef-reset-neu@pruefung.local', 'neues-passwort-1');
    $pdo->exec("UPDATE map_users SET email_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = $u");
    $confirm = email_confirmation_send($pdo, $u);
    email_confirmation_finish($pdo, $confirm['token']);
    check($late !== null && password_reset_account($pdo, (string) $late) === null,
        'a reset link that went to the old address dies when the new one is confirmed');

    echo "The mail cannon:\n";
    $pdo->prepare('DELETE FROM map_login_attempts WHERE ip = ?')->execute([$ip]);
    for ($i = 0; $i < PASSWORD_RESET_PER_IP; $i++) {
        password_reset_request($pdo, "pruef-reset-niemand-$i@pruefung.local");
    }
    $stopped = false;
    try { password_reset_request($pdo, 'pruef-reset-neu@pruefung.local'); } catch (InvalidArgumentException) { $stopped = true; }
    check($stopped, 'after ten requests from one address the eleventh is refused — known account or not');

    // THE TABLE FORGETS AFTER A WEEK (login_attempts_purge, 20.11.2026): a
    // failed attempt and a reset request alike, whoever they named.
    $pdo->prepare('DELETE FROM map_login_attempts WHERE ip = ?')->execute([$ip]);
    $old = $pdo->prepare('INSERT INTO map_login_attempts (identifier, ip, attempted_at) VALUES (?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))');
    $old->execute(['pruef-reset-alt@pruefung.local', $ip, LOGIN_KEEP_DAYS + 1]);
    $old->execute(['reset:pruef-reset-alt@pruefung.local', $ip, LOGIN_KEEP_DAYS + 30]);
    $old->execute(['pruef-reset-frisch@pruefung.local', $ip, LOGIN_KEEP_DAYS - 1]);
    $gone = login_attempts_purge($pdo);
    $left = $pdo->prepare('SELECT identifier FROM map_login_attempts WHERE ip = ?');
    $left->execute([$ip]);
    check($gone >= 2 && $left->fetchAll(PDO::FETCH_COLUMN) === ['pruef-reset-frisch@pruefung.local'],
        'attempts older than ' . LOGIN_KEEP_DAYS . ' days are thrown away, a younger one stays');
} finally {
    $pdo->prepare('DELETE FROM map_login_attempts WHERE ip = ?')->execute([$ip]);
    $pdo->exec("DELETE FROM map_login_attempts WHERE identifier LIKE 'pruef-reset%' OR identifier LIKE 'reset:pruef-reset%'");
    $pdo->exec("DELETE FROM map_tokens WHERE user_id IN ($u, $open)");
    $pdo->exec("DELETE FROM map_users WHERE id IN ($u, $open)");
}

echo $failed === 0 ? "All good.\n" : "$failed failed.\n";
exit($failed === 0 ? 0 : 1);
