<?php
/**
 * E-mail confirmation (23.09.2026). Migration 0116.
 *
 * TWO CASES, ONE LINK. After registration the account's own address is
 * confirmed; after a change in the settings the NEW address is — it
 * waits in email_pending and becomes the login only when its link is
 * followed, so a typo cannot lock anybody out and nobody can take an
 * address that is not theirs. The link carries a 256-bit secret, only its
 * hash is stored, it lives 48 hours and works once.
 *
 * WHAT HANGS ON IT: an account is usable unconfirmed. A confirmed address
 * is what excursion_claim() may trust again — a guest's finds follow the
 * address to the account on any device, which the proof cookie alone
 * could not do.
 */

declare(strict_types=1);

require_once __DIR__ . '/mail.php';

const EMAIL_TOKEN_HOURS = 48;
/** A second mail only after this many seconds — a button pressed twice is not two requests. */
const EMAIL_RESEND_SECONDS = 120;

function email_confirmation_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_users', 'email_token');
}

/**
 * Mint a link and mail it. $newAddress null: confirm the account's own
 * address; otherwise the address becomes pending and the link goes there,
 * and the old address hears that a change was asked for.
 *
 * @return array{sent: bool, to: string, token: string}  the token for the tests only
 * @throws InvalidArgumentException  too soon after the last mail
 */
function email_confirmation_send(PDO $pdo, int $userId, ?string $newAddress = null): array
{
    $stmt = $pdo->prepare('SELECT email, first_name, email_pending, email_token_expires FROM map_users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user === false) {
        throw new InvalidArgumentException(t('Konto nicht gefunden.'));
    }
    if ($user['email_token_expires'] !== null) {
        $issued = strtotime((string) $user['email_token_expires']) - EMAIL_TOKEN_HOURS * 3600;
        if (time() - $issued < EMAIL_RESEND_SECONDS && $newAddress === null) {
            throw new InvalidArgumentException(t('Die Mail ist gerade erst verschickt worden. Bitte warte zwei Minuten, dann kannst du es noch einmal versuchen.'));
        }
    }

    $to = $newAddress ?? ($user['email_pending'] !== null ? (string) $user['email_pending'] : (string) $user['email']);
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE map_users SET email_pending = ?, email_token = ?,
                          email_token_expires = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id = ?')
        ->execute([
            $newAddress ?? $user['email_pending'],
            hash('sha256', $token),
            EMAIL_TOKEN_HOURS,
            $userId,
        ]);

    $link = absolute_url('pages/confirm_email.php?t=' . $token);
    $name = trim((string) $user['first_name']);
    $hello = $name !== '' ? t('Hallo %s,', $name) : t('Hallo,');
    $sent = mail_send($to, t('YASAIMAPP: E-Mail-Adresse bestätigen'), implode("\n", [
        $hello,
        '',
        t('bitte klicke auf diesen Link. Damit bestätigst du deine E-Mail-Adresse für YASAIMAPP:'),
        '',
        $link,
        '',
        t('Der Link funktioniert %d Stunden lang und nur ein einziges Mal. Hast du diese Mail nicht angefordert? Dann lösche sie einfach. Es passiert nichts.', EMAIL_TOKEN_HOURS),
    ]));

    // The old address learns of a change — the one warning a hijacked
    // session cannot swallow.
    if ($newAddress !== null && (string) $user['email'] !== $newAddress) {
        mail_send((string) $user['email'], t('YASAIMAPP: Änderung deiner E-Mail-Adresse'), implode("\n", [
            $hello,
            '',
            t('für dein YASAIMAPP-Konto wurde eine neue E-Mail-Adresse eingetragen: %s. Sie gilt erst, wenn der Link in der Mail an diese Adresse angeklickt wird. Bis dahin meldest du dich weiter mit dieser Adresse hier an.', $newAddress),
            '',
            t('Warst du das nicht? Dann ändere bitte sofort dein Passwort.'),
        ]));
    }
    return ['sent' => $sent, 'to' => $to, 'token' => $token];
}

/**
 * Follow a link. Returns the user id it confirmed, or null for a link
 * that is unknown, used or expired.
 *
 * @throws InvalidArgumentException  the pending address was taken in the meantime
 */
function email_confirmation_finish(PDO $pdo, string $token): ?int
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, email_pending FROM map_users
                            WHERE email_token = ? AND email_token_expires > NOW() LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch();
    if ($user === false) {
        return null;
    }
    $userId = (int) $user['id'];
    if ($user['email_pending'] !== null) {
        $new = (string) $user['email_pending'];
        $taken = $pdo->prepare('SELECT 1 FROM map_users WHERE email = ? AND id <> ? LIMIT 1');
        $taken->execute([$new, $userId]);
        if ($taken->fetchColumn()) {
            $pdo->prepare('UPDATE map_users SET email_pending = NULL, email_token = NULL, email_token_expires = NULL WHERE id = ?')
                ->execute([$userId]);
            throw new InvalidArgumentException(t('Für diese E-Mail-Adresse gibt es inzwischen ein anderes Konto.'));
        }
        // A „Passwort vergessen" link still out there went to the OLD
        // address — it dies with the swap (migration 0118).
        $pdo->prepare('UPDATE map_users SET email = ?, email_pending = NULL, email_confirmed_at = NOW(),
                              email_token = NULL, email_token_expires = NULL'
                      . (column_exists($pdo, 'map_users', 'password_token')
                          ? ', password_token = NULL, password_token_expires = NULL' : '')
                      . ' WHERE id = ?')
            ->execute([$new, $userId]);
    } else {
        $pdo->prepare('UPDATE map_users SET email_confirmed_at = NOW(),
                              email_token = NULL, email_token_expires = NULL WHERE id = ?')
            ->execute([$userId]);
    }
    /* THE GUEST FINDS COME AT ONCE (23.09.2026, playing the
       two-excursion guest through): a confirmed address ties every guest
       row under it — until now only at the next login, so whoever
       registered and then clicked the link waited a login for his finds. */
    require_once __DIR__ . '/excursions.php';
    try {
        excursion_claim($pdo, $userId);
    } catch (Throwable $e) {
        error_log('confirmation claim (' . $userId . '): ' . $e->getMessage());
    }
    return $userId;
}

/** The state for the settings: confirmed?, a pending address? */
function email_confirmation_state(PDO $pdo, int $userId): array
{
    if (!email_confirmation_ready($pdo)) {
        return ['ready' => false, 'confirmed' => true, 'confirmed_at' => null, 'pending' => null];
    }
    $stmt = $pdo->prepare('SELECT email_confirmed_at, email_pending FROM map_users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch() ?: [];
    return [
        'ready'        => true,
        'confirmed'    => ($row['email_confirmed_at'] ?? null) !== null,
        'confirmed_at' => $row['email_confirmed_at'] ?? null,
        'pending'      => $row['email_pending'] ?? null,
    ];
}
