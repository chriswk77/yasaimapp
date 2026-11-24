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
<?php /* THE LOGIN PAGE, TIDIED (24.11.2026: „sehr unaufgeräumt, sehr
     chaotisch"). The card holds the one thing the page is opened for —
     address, password, go — and the way to a new account beneath a
     hairline. „Passwort vergessen?" stands small under the field it is
     about, where the eye already is when the password fails. Language and
     privacy and rules are no part of the form: they sit as one quiet line UNDER the
     card. The chosen language is the one in the text's colour, the others
     quiet — the accent once marked the language one did NOT have, and the
     accent is the loudest thing on the page. The sentence on how long
     one stays signed in went: grown-ups know not to stay signed in on a
     stranger's device. */ ?>
<div class="login narrow">
    <div class="card-block">
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
                <?php if (password_reset_ready($pdo)): ?>
                    <a class="login-forgot" href="<?= h(url('pages/password_reset.php')) ?>"><?= h(t('Passwort vergessen?')) ?></a>
                <?php endif; ?>
            </div>

            <div class="login-go">
                <label class="checkbox">
                    <input type="checkbox" name="stay" value="1" checked>
                    <span><?= h(t('Angemeldet bleiben')) ?></span>
                </label>
                <button class="button" type="submit"><?= h(t('Anmelden')) ?></button>
            </div>
        </form>

        <div class="login-new">
            <a class="button subtle" href="<?= h(url('register.php')) ?>"><?= h(t('Konto anlegen')) ?></a>
        </div>
    </div>

    <div class="login-foot">
        <?php /* The guest's language switch: whoever is not yet anyone has
             no settings page — ?lang= lands in the session
             (includes/i18n.php) and holds until the account's own choice
             takes over. The names stand in THEIR OWN language on purpose:
             whoever needs the other one cannot read this page's. */ ?>
        <span class="login-languages">
            <?php foreach (LANGUAGES as $code => $name): ?>
                <?php if ($code === lang_current()): ?><strong aria-current="true"><?= h($name) ?></strong><?php
                else: ?><a class="lang-switch" href="?<?= h(http_build_query(array_filter(['lang' => $code, 'next' => $next]))) ?>" lang="<?= h($code) ?>"><?= h($name) ?></a><?php endif; ?>
            <?php endforeach; ?>
        </span>
        <?php /* What happens to an address typed in here — to be read
             BEFORE it is typed, so the link stands on the one page
             everybody sees first (pages/privacy.php, 20.11.2026). */ ?>
        <span class="login-legal">
            <a href="<?= h(url('pages/privacy.php')) ?>"><?= h(t('Datenschutz')) ?></a>
            <a href="<?= h(url('pages/terms.php')) ?>"><?= h(t('Regeln')) ?></a>
        </span>
    </div>
    <?php /* WHAT WAS TYPED SURVIVES THE SWITCH (24.11.2026: switching the
         language below an already filled form emptied address and
         password). The switch has to reload — every sentence comes from
         the server — so the typed values ride along in sessionStorage:
         this tab only, written on the click and removed the moment the
         page is back. Turning the links into a POST of the form was the
         discarded alternative: the server would have to write the password
         back into the page's markup, and a page holding a password is
         worse than a value that lives for one reload inside the tab. */ ?>
    <script type="module">
    const KEY = 'loginCarry';
    const form = document.querySelector('form[method="post"]');
    const field = name => form.elements[name];
    try {
        const kept = JSON.parse(sessionStorage.getItem(KEY) || 'null');
        sessionStorage.removeItem(KEY);
        if (kept) {
            if (kept.email && !field('email').value) field('email').value = kept.email;
            if (kept.password) field('password').value = kept.password;
            field('stay').checked = !!kept.stay;
            (kept.email ? field('password') : field('email')).focus();
        }
    } catch { /* no storage, no carrying — the page works as before */ }
    document.querySelectorAll('.lang-switch').forEach(link => link.addEventListener('click', () => {
        if (!field('email').value && !field('password').value) return;
        try {
            sessionStorage.setItem(KEY, JSON.stringify({
                email: field('email').value,
                password: field('password').value,
                stay: field('stay').checked,
            }));
        } catch { /* see above */ }
    }));
    </script>
</div>
<?php page_footer(); ?>
