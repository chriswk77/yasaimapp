<?php
/**
 * Einstellungen → Fundbuch: where one's find book stands, and its key
 * (includes/publication.php, migration 0202).
 *
 * SORTED BY WEIGHT (CLAUDE.md): what a find book is and what goes out
 * first, then where it stands today with the address to change beneath,
 * and last the two steps one takes rarely — a new key, and ending it.
 *
 * THE KEY IS SHOWN ONCE, in a window that opens by itself after the save
 * that minted it, as a paired device's is (settings_content.php): only its
 * hash is kept, so no page can show it again.
 *
 * FOR THE ADMINISTRATORS ONLY, FOR NOW (25.11.2026: „dann bau mal als
 * Paket um und wir testen es dann direkt bei WK Pilz"). The package that
 * goes onto the homepage is not offered for download yet; a tab that hands
 * out a key for something nobody can install would only puzzle. The
 * condition goes when the package is there (publication_offered).
 */

declare(strict_types=1);

require_once __DIR__ . '/publication.php';

/** Whether this account sees the Fundbuch tab — see the head. */
function publication_offered(PDO $pdo, ?array $user): bool
{
    return $user !== null && publication_ready($pdo) && is_admin($user);
}

/**
 * Must run BEFORE any output.
 *
 * ITS FORMS CARRY section=settings, NOT A NAME OF THEIR OWN. The panel is
 * drawn inside the settings' tab, and the administration builds a tab into
 * the answer of a post only where the post names THAT tab
 * (pages/administration.php, tab_content) — every other tab is fetched in
 * a second request, where the freshly minted key would be gone. Found on
 * the first try: the key was made and never shown. The actions' names say
 * whose they are; the settings' own processing knows none of them and
 * passes them by.
 *
 * @return array{message: ?string, error: string[], key: ?string}
 */
function publication_process(PDO $pdo, int $userId): array
{
    $state = ['message' => null, 'error' => [], 'key' => null];
    $action = (string) ($_POST['action'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'settings' || !str_starts_with($action, 'fundbuch_')) {
        return publication_state($state);
    }
    csrf_check();
    if (!publication_offered($pdo, user_current())) {
        return publication_state($state);
    }
    try {
        switch ($action) {
            case 'fundbuch_save':
                $state['key'] = publication_save($pdo, $userId, (string) ($_POST['site_url'] ?? ''));
                $state['message'] = $state['key'] !== null ? t('Dein Fundbuch ist eingerichtet.') : t('Gespeichert.');
                break;
            case 'fundbuch_new_key':
                $book = publication_of($pdo, $userId);
                if ($book !== null) {
                    $state['key'] = publication_save($pdo, $userId, (string) $book['site_url'], true);
                    $state['message'] = t('Der neue Schlüssel ist da. Der alte gilt nicht mehr.');
                }
                break;
            case 'fundbuch_remove':
                publication_remove($pdo, $userId);
                $state['message'] = t('Das Fundbuch ist entfernt. Deine Homepage bekommt keine neuen Funde mehr.');
                break;
        }
    } catch (InvalidArgumentException $e) {
        $state['error'][] = $e->getMessage();
    }
    return publication_state($state);
}

/**
 * What the processing left for the render. The panel is drawn inside the
 * settings' render (settings_content.php), which is handed the settings'
 * own state and no other — so this one waits here.
 */
function publication_state(?array $set = null): array
{
    static $state = ['message' => null, 'error' => [], 'key' => null];
    if ($set !== null) {
        $state = $set;
    }
    return $state;
}

/** The tab's panel — inside the settings' own tab bar (settings_content.php). */
function publication_render(PDO $pdo, int $userId): void
{
    $state = publication_state();
    $target = url('pages/administration.php') . '#fundbuch';
    $book = publication_of($pdo, $userId);

    notice('error', $state['error'], true);
    if ($state['message'] !== null) {
        notice('success', $state['message'], true);
    }
    ?>
    <div class="settings-group">
        <h3 class="list-label"><?= h(t('Fundbuch')) ?></h3>
        <p><?= h(t('Mit dem Fundbuch zeigst du Funde auf deiner eigenen Homepage.')) ?>
           <?= h(t('Dort erscheint nur, was du freigibst: Öffne einen Fund im Fundbrowser und klicke oben auf den Knopf mit der Weltkugel.')) ?></p>
        <p><?= h(t('Deine Homepage holt sich jede Tafel einmal ab und zeigt sie dann selbst. Der genaue Fundort bleibt hier: Hinaus gehen nur die Gemeinde und das Viertel des Messtischblatts.')) ?></p>

        <?php if ($book !== null): ?>
        <p class="publication-state"><?= icon('check') ?><?= h(t('Eingerichtet für %s am %s.', publication_host((string) $book['site_url']),
                date('d.m.Y', strtotime((string) $book['created_at'])))) ?>
            <?= h($book['last_fetch_at'] !== null
                ? t('Deine Homepage hat zuletzt am %s nachgefragt.', date('d.m.Y, H:i', strtotime((string) $book['last_fetch_at'])))
                : t('Deine Homepage hat noch nie nachgefragt.')) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= h($target) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="settings">
            <input type="hidden" name="action" value="fundbuch_save">
            <div class="field">
                <label for="publication-url"><?= h(t('Adresse deiner Homepage')) ?></label>
                <input type="text" id="publication-url" name="site_url" required maxlength="250" inputmode="url"
                       placeholder="https://beispiel.de/" value="<?= h((string) ($book['site_url'] ?? ($_POST['site_url'] ?? ''))) ?>">
                <p class="hint-text small"><?= h(t('Die Adresse, unter der dein Fundbuch zu sehen ist. Sie steht auch in jeder Tafel, als Stelle für Nachfragen zur Lizenz.')) ?></p>
            </div>
            <div class="button-row right">
                <button type="submit" class="button subtle" data-quiet-until-changed><?= h($book !== null ? t('Speichern') : t('Fundbuch einrichten')) ?></button>
            </div>
        </form>
    </div>

    <?php if ($book !== null): ?>
    <div class="settings-group">
        <h3 class="list-label"><?= h(t('Schlüssel')) ?></h3>
        <p><?= h(t('Mit dem Schlüssel weist sich deine Homepage hier aus. Er steht dort in einer Datei. Wir kennen ihn nicht mehr: Er wurde dir nur einmal gezeigt.')) ?></p>
        <div class="button-row">
            <form method="post" action="<?= h($target) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="settings">
                <input type="hidden" name="action" value="fundbuch_new_key">
                <button type="submit" class="button subtle"
                        data-confirm="<?= h(t('Einen neuen Schlüssel erzeugen? Der alte gilt dann sofort nicht mehr. Deine Homepage bekommt erst wieder neue Funde, wenn du dort den neuen Schlüssel einträgst.')) ?>"><?= icon('key') ?><?= h(t('Neuen Schlüssel erzeugen')) ?></button>
            </form>
            <form method="post" action="<?= h($target) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="settings">
                <input type="hidden" name="action" value="fundbuch_remove">
                <button type="submit" class="button danger"
                        data-confirm="<?= h(t('Das Fundbuch entfernen? Der Schlüssel gilt dann nicht mehr, und deine Homepage bekommt keine neuen Funde. Was schon auf deiner Homepage liegt, bleibt dort, bis du es dort löschst.')) ?>"><?= icon('delete') ?><?= h(t('Fundbuch entfernen')) ?></button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($state['key'] !== null): ?>
    <dialog class="dialog pinned-footer" id="publication-key-window" data-open-at-load>
        <?php /* method="dialog" only for the window's frame and spacing; nothing in it is sent. */ ?>
        <form method="dialog">
            <h2 class="dialog-title"><?= h(t('Der Schlüssel für dein Fundbuch')) ?></h2>
            <p class="hint-text"><strong><?= h(t('Kopiere den Schlüssel jetzt.')) ?></strong>
                <?= h(t('Er erscheint nur dieses eine Mal. Geht er verloren, erzeugst du hier einen neuen.')) ?></p>
            <p class="hint-text"><?= h(t('Er gehört auf deine Homepage, in die Datei „fundbuch-key.php" im Ordner „inc".')) ?></p>
            <div class="field">
                <label for="publication-key"><?= h(t('Schlüssel')) ?></label>
                <input type="text" id="publication-key" class="pair-value" readonly value="<?= h($state['key']) ?>">
            </div>
            <div class="button-row">
                <button type="button" class="button subtle small" data-copy="#publication-key"><?= icon('copy') ?><?= h(t('Schlüssel kopieren')) ?></button>
            </div>
            <?php /* A DOOR, NOT A CLOSE: the page under the window answers a
                 post, and a reload would mint the next key. */ ?>
            <div class="dialog-footer">
                <a class="button" href="<?= h(url('pages/administration.php')) ?>?fundbuch=1#fundbuch"><?= h(t('Fertig')) ?></a>
            </div>
        </form>
    </dialog>
    <?php endif; ?>
    <?php
}
