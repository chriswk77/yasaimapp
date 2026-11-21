<?php
/**
 * Initial setup.
 *
 * Applies the migrations and creates the first account as administrator.
 * As soon as an account exists, the page locks itself — later migrations
 * then run via admin/migrations.php.
 *
 * Prerequisite: the database from config.php already exists. It can be
 * created with
 *   CREATE DATABASE karte CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/migrations.php';
require_once __DIR__ . '/../includes/page.php';

/** Is there an account yet? As long as there is none, this page is open. */
function accounts_exist(PDO $pdo): bool
{
    try {
        return (int) $pdo->query('SELECT COUNT(*) FROM map_users')->fetchColumn() > 0;
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') {
            return false; // table not there yet
        }
        throw $e;
    }
}

if (accounts_exist($pdo)) {
    page_header(t('Einrichtung'));
    notice('hint', t('Die Einrichtung ist bereits abgeschlossen. Diese Seite kann gelöscht werden.'));
    echo '<p><a class="button" href="' . h(url('login.php')) . '">' . h(t('Zur Anmeldung')) . '</a></p>';
    page_footer();
    exit;
}

$errors   = [];
$report  = [];
$pending    = migrations_pending($pdo);
$action   = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($action === 'migrations') {
        $report = migrations_apply($pdo, $pending);
        $pending   = migrations_pending($pdo);
    }

    if ($action === 'account') {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $firstName  = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $repeat      = (string) ($_POST['password2'] ?? '');

        if ($pending !== []) {
            $errors[] = t('Erst die Migrationen einspielen.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('Bitte eine gültige E-Mail-Adresse angeben.');
        }
        if ($firstName === '' || $lastName === '') {
            $errors[] = t('Bitte Vorname und Nachname angeben.');
        }
        if (mb_strlen($password) < 10) {
            $errors[] = t('Das Passwort muss mindestens 10 Zeichen haben.');
        }
        if ($password !== $repeat) {
            $errors[] = t('Die beiden Passwörter stimmen nicht überein.');
        }

        if ($errors === []) {
            $userId = account_create($email, $password, [
                'first_name' => $firstName, 'last_name' => $lastName,
            ], null);
            $pdo->prepare('UPDATE map_users SET role = "admin" WHERE id = ?')->execute([$userId]);

            session_renew();
            $_SESSION['user_id'] = $userId;
            redirect_to('index.php');
        }
    }
}

page_header(t('Einrichtung'));
?>
<h1><?= h(t('YASAIMAPP einrichten')) ?></h1>
<p class="hint-text">
    <?= h(t('Zwei Schritte: Datenbank aufbauen, dann das erste Konto anlegen. Dieses Konto wird Admin und kann später Einladungscodes ausgeben.')) ?>
</p>

<?php notice('error', $errors); ?>

<?php if ($report !== []): ?>
    <?php
    $failed = array_filter($report, static fn(array $z) => !$z['ok']);
    $texts = array_map(
        static fn(array $z) => $z['file'] . ' — ' . $z['message'],
        $report
    );
    notice($failed ? 'error' : 'success', $texts);
    ?>
<?php endif; ?>

<h2><?= h(t('1. Datenbank')) ?></h2>

<?php if ($pending === []): ?>
    <div class="notice notice-success"><p><?= h(t('Alle Migrationen sind eingespielt.')) ?></p></div>
<?php else: ?>
    <p><?= h(t('Diese Migrationen sind noch offen und werden der Reihe nach ausgeführt:')) ?></p>
    <ul>
        <?php foreach ($pending as $file): ?>
            <li><code><?= h($file) ?></code></li>
        <?php endforeach; ?>
    </ul>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="migrations">
        <button class="button" type="submit"><?= h(t('Migrationen einspielen')) ?></button>
    </form>
<?php endif; ?>

<h2><?= h(t('2. Erstes Konto')) ?></h2>

<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="account">

    <div class="field">
        <label for="first-name"><?= h(t('Vorname')) ?></label>
        <input type="text" id="first-name" name="first_name" required
               value="<?= h((string) ($_POST['first_name'] ?? '')) ?>">
    </div>

    <div class="field">
        <label for="last-name"><?= h(t('Nachname')) ?></label>
        <input type="text" id="last-name" name="last_name" required
               value="<?= h((string) ($_POST['last_name'] ?? '')) ?>">
    </div>

    <div class="field">
        <label for="email"><?= h(t('E-Mail-Adresse')) ?></label>
        <input type="email" id="email" name="email" required
               value="<?= h((string) ($_POST['email'] ?? '')) ?>">
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

    <div class="button-row">
        <button class="button" type="submit" <?= $pending === [] ? '' : 'disabled' ?>><?= h(t('Konto anlegen')) ?></button>
        <?php if ($pending !== []): ?>
            <span class="hint-text"><?= h(t('Erst die Migrationen einspielen.')) ?></span>
        <?php endif; ?>
    </div>
</form>

<?php page_footer(); ?>
