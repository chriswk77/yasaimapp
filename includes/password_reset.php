<?php
/**
 * „Passwort vergessen" (23.09.2026). Migration 0118.
 *
 * ONLY FOR A CONFIRMED ADDRESS — that is what confirming it buys: a link
 * that sets the password may only go where the owner has proven to read.
 * An unconfirmed address may be a typo or somebody else's, and the reset
 * would hand the account to whoever reads that mailbox. Confirming stays
 * voluntary (the same day); the settings say what it is for.
 *
 * THE PAGE ANSWERS THE SAME WHATEVER HAPPENED: an unknown address, an
 * unconfirmed one, a locked account and a real one all hear „if there is
 * a confirmed account, the link is on its way" — the form must not become
 * a way to ask which addresses have an account.
 *
 * OPENING THE LINK DOES NOTHING. It shows a form; only sending the form
 * spends it. Mail programs and virus scanners open links to look at them,
 * and a link that acted on GET would be spent before its owner saw it.
 *
 * AFTER THE RESET every stay-logged-in and device token of the account is
 * gone, as after a change in the settings — whoever forgot a password may
 * also have lost a phone — and the person is logged in on the spot
 * through login_session(), the same path the login takes.
 */

declare(strict_types=1);

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/excursions.php';

/** A link that sets the password lives shorter than one that confirms an address. */
const PASSWORD_RESET_MINUTES = 60;
/** A second mail to the same account only after this many seconds. */
const PASSWORD_RESET_PAUSE_SECONDS = 120;
/** Requests from one network address within LOGIN_LOCK_MIN minutes. */
const PASSWORD_RESET_PER_IP = 10;

function password_reset_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_users', 'password_token')
        && column_exists($pdo, 'map_users', 'email_confirmed_at');
}

/**
 * Ask for a link. Returns the token when a mail went out — for the tests,
 * the page never shows it — and null when nothing was sent; the page
 * answers the same either way.
 *
 * @throws InvalidArgumentException  too many requests from this address
 */
function password_reset_request(PDO $pdo, string $email): ?string
{
    $email = mb_strtolower(trim($email));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    /* EVERY REQUEST IS COUNTED, whatever the address, in the login
       throttle's table under „reset:" — otherwise one machine could send
       a mail to every confirmed account every two minutes. The rows also
       count into login_locked()'s thirty per address, which is right:
       it is the same machine knocking. */
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM map_login_attempts
                            WHERE ip = ? AND identifier LIKE ?
                              AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)');
    $stmt->execute([$ip, 'reset:%', LOGIN_LOCK_MIN]);
    if ((int) $stmt->fetchColumn() >= PASSWORD_RESET_PER_IP) {
        throw new InvalidArgumentException(t('Zu viele Anfragen von hier. Bitte in %d Minuten noch einmal versuchen.', LOGIN_LOCK_MIN));
    }
    $pdo->prepare('INSERT INTO map_login_attempts (identifier, ip, attempted_at) VALUES (?, ?, NOW())')
        ->execute([mb_substr('reset:' . $email, 0, 190), $ip]);

    // „Just sent" is asked of the database, not of PHP's clock — the two
    // need not share a time zone.
    $stmt = $pdo->prepare('SELECT id, email, first_name, locked_at, email_confirmed_at,
                                  password_token_expires > DATE_ADD(NOW(), INTERVAL ? SECOND) AS just_sent
                             FROM map_users WHERE email = ? LIMIT 1');
    $stmt->execute([PASSWORD_RESET_MINUTES * 60 - PASSWORD_RESET_PAUSE_SECONDS, $email]);
    $user = $stmt->fetch();
    if ($user === false || $user['email_confirmed_at'] === null || $user['locked_at'] !== null
        || $email === EXCURSION_GUEST_EMAIL || (int) $user['just_sent'] === 1) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE map_users SET password_token = ?,
                          password_token_expires = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?')
        ->execute([hash('sha256', $token), PASSWORD_RESET_MINUTES, $user['id']]);

    $name = trim((string) $user['first_name']);
    $sent = mail_send((string) $user['email'], t('YASAIMAPP: Neues Passwort setzen'), implode("\n", [
        $name !== '' ? t('Hallo %s,', $name) : t('Hallo,'),
        '',
        t('für dein YASAIMAPP-Konto wurde ein neues Passwort angefordert. Mit diesem Link setzt du es:'),
        '',
        absolute_url('pages/password_reset.php?t=' . $token),
        '',
        t('Der Link gilt eine Stunde und nur einmal. Wenn du das nicht angestoßen hast, lass die Mail einfach liegen — dein Passwort bleibt, wie es ist.'),
    ]));
    if (!$sent) {
        error_log('password reset mail not handed on (user ' . $user['id'] . ')');
    }
    return $token;
}

/** The account a link belongs to — null for one that is unknown, spent or expired. */
function password_reset_account(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM map_users
                            WHERE password_token = ? AND password_token_expires > NOW()
                              AND locked_at IS NULL AND email_confirmed_at IS NOT NULL
                            LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

/**
 * Set the new password through the link. Returns the account — the caller
 * logs it in — or null for a link that is unknown, spent or expired.
 *
 * @throws InvalidArgumentException  the new password breaks the rules
 */
function password_reset_finish(PDO $pdo, string $token, string $newPassword, string $repeat): ?array
{
    $user = password_reset_account($pdo, $token);
    if ($user === null) {
        return null;
    }
    password_new_check($newPassword, $repeat);

    // The link is spent in the very statement that uses it: a form sent
    // twice finds the token gone the second time.
    $stmt = $pdo->prepare('UPDATE map_users SET password_hash = ?, password_token = NULL, password_token_expires = NULL
                            WHERE id = ? AND password_token = ?');
    $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id'], hash('sha256', $token)]);
    if ($stmt->rowCount() === 0) {
        return null;
    }
    $pdo->prepare('DELETE FROM map_tokens WHERE user_id = ?')->execute([$user['id']]);
    return $user;
}
