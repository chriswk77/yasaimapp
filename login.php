<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/password_reset.php';

if (user_current() !== null) {
    redirect_to('index.php');
}

$errors = [];
$email  = trim((string) ($_POST['email'] ?? ''));

// Destination after login. Only accept application-internal paths, otherwise
// the parameter would be an open redirect to foreign sites.
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
if ($next !== '' && (!str_starts_with($next, BASE_URL) || str_starts_with($next, '//'))) {
    $next = '';
}

/* AND AFTER A LOGIN ONE SEES THE MAP (24.08.2026: „Ich bin
   nach einer Anmeldung auch mal in die Verwaltung gekommen. Das darf
   natürlich auch nicht sein."). 'next' carries the address one was
   thrown out of — with an expired session in the administration that is
   the administration, and one lands in a tool instead of in the
   application. Only a destination ON THE MAP survives, so a shared deep
   link to a find still works (index.php#zeige=…); everything else falls
   back to the map itself. */
if ($next !== '') {
    $path = parse_url($next, PHP_URL_PATH) ?? '';
    if (!in_array(basename($path), ['', 'index.php'], true)) {
        $next = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $locked = login_locked($email);
    if ($locked > 0) {
        $errors[] = t('Zu viele Fehlversuche. Bitte in %d Minuten noch einmal versuchen.', $locked);
    } elseif (($account = login($email, (string) ($_POST['password'] ?? ''))) !== null) {
        if (!empty($_POST['stay'])) {
            persistent_remember((int) $account['id']);
        }
        /* A DESK OR NOT — the form said so (the hidden field below), and
           with it the server sends this session straight into the frame
           page instead of writing the map once for nothing first
           (includes/shell.php, way 2). Wrong or missing costs nothing: the
           pages put it right themselves. */
        $_SESSION['desk'] = ($_POST['desk'] ?? '') === '1';
        redirect_to($next !== '' ? $next : 'index.php');
    } else {
        // Deliberately without saying which part was wrong.
        $errors[] = t('Kennung oder Passwort stimmt nicht.');
    }
}

page_header(t('Anmelden'));
?>
<?php /* THE LOGIN PAGE, TIDIED (24.11.2026: „sehr unaufgeräumt, sehr
     chaotisch"). The card holds the one thing the page is opened for —
     address, password, go — and the way to a new account beneath a
     hairline. „Passwort vergessen?" stands small under the field it is
     about, where the eye already is when the password fails. Language,
     privacy and rules stand outside the card (auth_foot() in
     includes/page.php says why). The sentence on how long
     one stays signed in went: grown-ups know not to stay signed in on a
     stranger's device. */ ?>
<div class="auth narrow">
    <div class="card-block">
        <h1><?= h(t('Anmelden')) ?></h1>

        <?php notice('error', $errors); ?>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= h($next) ?>">
            <?php /* Filled by the line below with what only the browser knows:
                 a wide window and a mouse (DESK_QUERY), and not the recorder app. */ ?>
            <input type="hidden" name="desk" id="login-desk" value="">
            <script>document.getElementById('login-desk').value = !window.yasaimapp && matchMedia(<?= json_encode(DESK_QUERY, JSON_HEX_TAG | JSON_HEX_AMP) ?>).matches ? '1' : '';</script>

            <div class="field">
                <label for="email"><?= h(t('E-Mail-Adresse')) ?></label>
                <input type="email" id="email" name="email" required autofocus
                       autocomplete="email" value="<?= h($email) ?>">
            </div>

            <div class="field">
                <label for="password"><?= h(t('Passwort')) ?></label>
                <input type="password" id="password" name="password" required
                       autocomplete="current-password">
                <?php if (password_reset_ready($pdo)): ?>
                    <a class="auth-forgot" href="<?= h(url('pages/password_reset.php')) ?>"><?= h(t('Passwort vergessen?')) ?></a>
                <?php endif; ?>
            </div>

            <div class="auth-go">
                <label class="checkbox">
                    <input type="checkbox" name="stay" value="1" checked>
                    <span><?= h(t('Angemeldet bleiben')) ?></span>
                </label>
                <button class="button" type="submit"><?= h(t('Anmelden')) ?></button>
            </div>
        </form>

        <div class="auth-alt">
            <a class="button subtle" href="<?= h(url('register.php')) ?>"><?= h(t('Konto anlegen')) ?></a>
        </div>
    </div>

    <?php auth_foot(['next' => $next]); ?>
</div>
<?php page_footer(); ?>
