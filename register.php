<?php
/**
 * Registration — exclusively with an invitation code.
 *
 * The stored locations are the most sensitive thing about this application,
 * which is why nobody gets in without a code. Codes are issued by
 * admin/invitations.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/page.php';

if (user_current() !== null) {
    redirect_to('index.php');
}

$errors = [];
$code   = trim((string) ($_POST['code'] ?? $_GET['code'] ?? ''));
$email    = trim((string) ($_POST['email'] ?? ''));
$firstName  = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $password = (string) ($_POST['password'] ?? '');
    $repeat      = (string) ($_POST['password2'] ?? '');
    $invitation = invitation_check($code);

    if ($invitation === null) {
        $errors[] = t('Dieser Einladungscode ist unbekannt, abgelaufen oder schon eingelöst.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('Bitte eine gültige E-Mail-Adresse angeben.');
    }
    if ($firstName === '') {
        $errors[] = t('Bitte den Vornamen angeben.');
    }
    if ($lastName === '') {
        $errors[] = t('Bitte den Nachnamen angeben.');
    }
    if (mb_strlen($password) < 10) {
        $errors[] = t('Das Passwort muss mindestens 10 Zeichen haben.');
    }
    if ($password !== $repeat) {
        $errors[] = t('Die beiden Passwörter stimmen nicht überein.');
    }
    /* THE RULES ARE ACCEPTED BEFORE ANYTHING IS SHARED (23.11.2026, the Play
       review: the acceptance „cannot be skipped"). Checked here as well as
       by `required` on the box — the box is the browser's courtesy, this is
       the boundary. */
    if (($_POST['terms'] ?? '') !== '1') {
        $errors[] = t('Bitte lies die Regeln und hak an, dass du dich daran hältst.');
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT 1 FROM map_users WHERE email = ? LIMIT 1');
        $stmt->execute([mb_strtolower($email)]);
        if ($stmt->fetchColumn()) {
            $errors[] = t('Für diese E-Mail-Adresse gibt es bereits ein Konto.');
        }
    }

    if ($errors === []) {
        try {
            $userId = account_create($email, $password, [
                'first_name'  => $firstName,
                'last_name' => $lastName,
            ], $invitation);
            session_renew();
            $_SESSION['user_id'] = $userId;
            // When the rules were accepted (migration 0200; before it, the
            // tick above is asked all the same and nothing is stored).
            if (column_exists($pdo, 'map_users', 'terms_accepted_at')) {
                $pdo->prepare('UPDATE map_users SET terms_accepted_at = NOW() WHERE id = ?')->execute([$userId]);
            }
            // The confirmation link goes out at once (migration 0116); the
            // account is usable before it is followed. A mail that could
            // not be handed on must not cost the registration.
            // And the guest rows this browser proves are his at once — the
            // login() that would tie them does not run on registration.
            require_once __DIR__ . '/includes/excursions.php';
            try {
                excursion_claim($pdo, $userId);
            } catch (Throwable $claimError) {
                error_log('registration claim (' . $userId . '): ' . $claimError->getMessage());
            }
            require_once __DIR__ . '/includes/email_confirmation.php';
            if (email_confirmation_ready($pdo)) {
                try {
                    email_confirmation_send($pdo, $userId);
                } catch (Throwable $mailError) {
                    error_log('registration confirmation mail (' . $userId . '): ' . $mailError->getMessage());
                }
            }
            // THE ONBOARDING FIRST (24.08.2026: „sowas wäre
            // natürlich auch bei neu registrierten Benutzern sinnvoll"):
            // location and storage, explained, before the map. Only here
            // at the CREATION of the account — an ordinary login goes
            // straight to the map as ever.
            redirect_to('pages/onboarding.php');
        } catch (RuntimeException $e) {
            $errors[] = error_for_user($e);
        }
    }
}

page_header(t('Konto anlegen'));
?>
<?php /* IN THE STYLE OF THE LOGIN (24.11.2026: „nach dem gleichen Stil …
     etwas ruhiger, etwas übersichtlicher"): one short sentence on top,
     the two names side by side, the rules' tick on the line of the
     button, the way back to the login beneath a hairline, and language,
     privacy and rules under the card (auth_foot()). The rules link opens
     beside the form, so what was typed stays. */ ?>
<div class="auth narrow">
    <div class="card-block">
        <h1><?= h(t('Konto anlegen')) ?></h1>
        <p class="hint-text auth-lead">
            <?= h(t('Du brauchst einen Einladungscode. Deine Punkte siehst nur du, bis du eine Liste teilst.')) ?>
        </p>

        <?php notice('error', $errors); ?>

        <form method="post" autocomplete="off">
            <?= csrf_field() ?>

            <div class="field">
                <label for="code"><?= h(t('Einladungscode')) ?></label>
                <input type="text" id="code" name="code" required
                       value="<?= h($code) ?>" <?= $code === '' ? 'autofocus' : '' ?>>
            </div>

            <div class="auth-pair">
                <div class="field">
                    <label for="first-name"><?= h(t('Vorname')) ?></label>
                    <input type="text" id="first-name" name="first_name" required
                           autocomplete="given-name" value="<?= h($firstName) ?>">
                </div>
                <div class="field">
                    <label for="last-name"><?= h(t('Nachname')) ?></label>
                    <input type="text" id="last-name" name="last_name" required
                           autocomplete="family-name" value="<?= h($lastName) ?>">
                </div>
            </div>

            <div class="field">
                <label for="email"><?= h(t('E-Mail-Adresse')) ?></label>
                <input type="email" id="email" name="email" required
                       autocomplete="username" value="<?= h($email) ?>">
            </div>

            <div class="field">
                <label for="password"><?= h(t('Passwort (mindestens 10 Zeichen)')) ?></label>
                <input type="password" id="password" name="password" required minlength="10"
                       autocomplete="new-password">
            </div>

            <div class="field">
                <label for="password2"><?= h(t('Passwort wiederholen')) ?></label>
                <input type="password" id="password2" name="password2" required minlength="10"
                       autocomplete="new-password">
            </div>

            <div class="auth-go">
                <label class="checkbox">
                    <input type="checkbox" name="terms" value="1" required<?= ($_POST['terms'] ?? '') === '1' ? ' checked' : '' ?>>
                    <span><?= t('Ich halte mich an die <a href="%s" target="_blank" rel="noopener">Regeln</a>.', h(url('pages/terms.php'))) ?></span>
                </label>
                <button class="button" type="submit"><?= h(t('Konto anlegen')) ?></button>
            </div>
        </form>

        <div class="auth-alt">
            <a class="button subtle" href="<?= h(url('login.php')) ?>"><?= h(t('Anmelden')) ?></a>
        </div>
    </div>

    <?php auth_foot(['code' => $code]); ?>
</div>
<?php page_footer(); ?>
