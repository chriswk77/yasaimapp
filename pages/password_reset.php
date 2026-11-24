<?php
/**
 * „Passwort vergessen" (migration 0118, includes/password_reset.php).
 * Two faces on one address: without a link it asks for the e-mail
 * address, with one — the link from the mail — for the new password. No
 * login needed; that is the point of it.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/password_reset.php';

$ready  = password_reset_ready($pdo);
$token  = (string) ($_POST['t'] ?? $_GET['t'] ?? '');
$email  = trim((string) ($_POST['email'] ?? ''));
$errors = [];
$sent   = false;

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if ($token === '') {
            password_reset_request($pdo, $email);
            $sent = true;
        } elseif (($user = password_reset_finish($pdo, $token,
                (string) ($_POST['new'] ?? ''), (string) ($_POST['new2'] ?? ''))) !== null) {
            login_session($user);
            if (!empty($_POST['stay'])) {
                persistent_remember((int) $user['id']);
            }
            redirect_to('index.php');
        }
    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    }
}
$account = $ready && $token !== '' ? password_reset_account($pdo, $token) : null;

page_header($token !== '' ? t('Neues Passwort setzen') : t('Passwort vergessen'));
?>
<div class="card-block narrow">
<?php if (!$ready): ?>
    <h1><?= h(t('Passwort vergessen')) ?></h1>
    <p><?= h(t('Das Zurücksetzen ist hier noch nicht eingerichtet.')) ?></p>

<?php elseif ($token !== ''): ?>
    <h1><?= h(t('Neues Passwort setzen')) ?></h1>
    <?php if ($account === null): ?>
        <p><?= h(t('Dieser Link ist abgelaufen oder wurde schon benutzt.')) ?></p>
        <div class="button-row right">
            <a class="button" href="<?= h(url('pages/password_reset.php')) ?>"><?= h(t('Neuen Link anfordern')) ?></a>
        </div>
    <?php else: ?>
        <?php notice('error', $errors, float: true); ?>
        <p><?= h(t('Für das Konto %s.', (string) $account['email'])) ?></p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="t" value="<?= h($token) ?>">
            <?php /* For the password manager: it files the new password under
                 the address only if it sees one beside it. */ ?>
            <input type="email" name="username" value="<?= h((string) $account['email']) ?>"
                   autocomplete="username" readonly hidden>
            <div class="field">
                <label for="reset-new"><?= h(t('Neues Passwort (mindestens 10 Zeichen)')) ?></label>
                <input type="password" id="reset-new" name="new" required minlength="10" autofocus
                       autocomplete="new-password">
            </div>
            <div class="field">
                <label for="reset-new2"><?= h(t('Neues Passwort noch einmal')) ?></label>
                <input type="password" id="reset-new2" name="new2" required minlength="10"
                       autocomplete="new-password">
            </div>
            <label class="checkbox">
                <input type="checkbox" name="stay" value="1" checked>
                <span><?= h(t('Angemeldet bleiben')) ?></span>
            </label>
            <div class="button-row right">
                <button class="button" type="submit"><?= h(t('Passwort setzen')) ?></button>
            </div>
        </form>
    <?php endif; ?>

<?php elseif ($sent): ?>
    <h1><?= h(t('Passwort vergessen')) ?></h1>
    <p><?= h(t('Gehört zu %s ein Konto mit bestätigter Adresse, ist der Link jetzt unterwegs. Er gilt eine Stunde.', $email)) ?></p>
    <p class="hint-text"><?= h(t('Keine Mail? Dann schau im Spam-Ordner nach. Eine Adresse, die nie bestätigt wurde, bekommt keinen Link.')) ?></p>
    <div class="button-row right">
        <a class="button" href="<?= h(url('login.php')) ?>"><?= h(t('Zur Anmeldung')) ?></a>
    </div>

<?php else: ?>
    <h1><?= h(t('Passwort vergessen')) ?></h1>
    <?php notice('error', $errors, float: true); ?>
    <p><?= h(t('Gib die E-Mail-Adresse deines Kontos ein. Ist sie bestätigt, bekommst du einen Link, mit dem du ein neues Passwort setzt.')) ?></p>
    <form method="post">
        <?= csrf_field() ?>
        <div class="field">
            <label for="reset-email"><?= h(t('E-Mail-Adresse')) ?></label>
            <input type="email" id="reset-email" name="email" required autofocus
                   autocomplete="email" value="<?= h($email) ?>">
        </div>
        <div class="button-row right">
            <button class="button" type="submit"><?= h(t('Link anfordern')) ?></button>
        </div>
    </form>
    <p class="hint-text" style="margin-top:1.4rem">
        <a href="<?= h(url('login.php')) ?>"><?= h(t('Zurück zur Anmeldung')) ?></a>
    </p>
<?php endif; ?>
</div>
<?php page_footer();
