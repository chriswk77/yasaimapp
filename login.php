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
        redirect_to($next !== '' ? $next : 'index.php');
    } else {
        // Deliberately without saying which part was wrong.
        $errors[] = t('Kennung oder Passwort stimmt nicht.');
    }
}

page_header(t('Anmelden'));
?>
<div class="card-block narrow">
    <h1><?= h(t('Anmelden')) ?></h1>

    <?php notice('error', $errors); ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= h($next) ?>">

        <div class="field">
            <label for="email"><?= h(t('E-Mail-Adresse')) ?></label>
            <input type="email" id="email" name="email" required autofocus
                   autocomplete="email" value="<?= h($email) ?>">
        </div>

        <div class="field">
            <label for="password"><?= h(t('Passwort')) ?></label>
            <input type="password" id="password" name="password" required
                   autocomplete="current-password">
        </div>

        <label class="checkbox">
            <input type="checkbox" name="stay" value="1" checked>
            <span><?= h(t('Angemeldet bleiben')) ?></span>
        </label>
        <p class="hint-text small">
            <?= h(t('Gilt drei Monate und verlängert sich bei jeder Nutzung. Auf einem fremden Gerät besser abwählen.')) ?>
        </p>

        <div class="button-row">
            <button class="button" type="submit"><?= h(t('Anmelden')) ?></button>
        </div>
    </form>

    <?php if (password_reset_ready($pdo)): ?>
        <p class="hint-text">
            <?= h(t('Passwort vergessen?')) ?>
            <a href="<?= h(url('pages/password_reset.php')) ?>"><?= h(t('Neues Passwort setzen')) ?></a>
        </p>
    <?php endif; ?>

    <p class="hint-text" style="margin-top:1.4rem">
        <?= h(t('Einladungscode bekommen?')) ?>
        <a href="<?= h(url('register.php')) ?>"><?= h(t('Konto anlegen')) ?></a>
    </p>

    <?php /* The guest's language switch: whoever is not yet anyone has no
         settings page — ?lang= lands in the session (includes/i18n.php)
         and holds until the account's own choice takes over. The names
         stand in THEIR OWN language on purpose: whoever needs the other
         one cannot read this page's. */ ?>
    <p class="hint-text small">
        <?php foreach (LANGUAGES as $code => $name): ?>
            <?php if ($code !== array_key_first(LANGUAGES)) echo ' · '; ?>
            <?php if ($code === lang_current()): ?><?= h($name) ?><?php
            else: ?><a href="?lang=<?= h($code) ?>"><?= h($name) ?></a><?php endif; ?>
        <?php endforeach; ?>
    </p>
    <?php /* What happens to an address typed in here — to be read BEFORE
         it is typed, so the link stands on the one page everybody sees
         first (pages/privacy.php, 20.11.2026). */ ?>
    <p class="hint-text small"><a href="<?= h(url('pages/privacy.php')) ?>"><?= h(t('Datenschutz')) ?></a></p>
</div>
<?php page_footer(); ?>
