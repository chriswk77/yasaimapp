<?php
/**
 * Friends — processing and view for the tab in "Mein Bereich".
 *
 * The logic lives in friends.php; here is only what accepts forms and
 * outputs HTML. Friendship arises through request and acceptance — nobody
 * ends up unasked in other people's friends lists.
 *
 * THE VIEW IS A COLUMN PAIR: on the left the open requests (both
 * directions — what is on its way from me and who is waiting on me), on the
 * right the friends, i.e. the result. Requesting happens via the plus in
 * the left column header, which opens a search window with name suggestions
 * (user_search in friends.php explains why search instead of type).
 */

declare(strict_types=1);

require_once __DIR__ . '/friends.php';

/**
 * Must run BEFORE any output. 'section' tells the administration's forms
 * apart from one another — see invitations_content.php.
 *
 * @return array{message: ?string, error: string[]}
 */
function friends_process(PDO $pdo, int $userId): array
{
    $state = ['message' => null, 'error' => []];

    // The name suggestions for the request window: ?friend_search=tho
    // answers with JSON instead of the page. The branch lives here because
    // processing runs BEFORE any output — only here can the page still be
    // aborted. Reading without side effects, hence GET and without CSRF.
    if (isset($_GET['friend_search'])) {
        header('Content-Type: application/json; charset=utf-8');
        /* WITHOUT THE FRIENDS ONE ALREADY HAS (12.09.2026: „das
           Fenster zeigt mir auch Personen an, mit denen ich schon
           befreundet bin — das ist Schwachsinn"). The window asks whom to
           request; a friend is no answer to that. A PENDING request stays,
           greyed with its reason: otherwise one would ask a second time
           and wonder why nothing happens.
           FIFTEEN AT A TIME (02.10.2026), ?offset= for the next page as the
           window scrolls down (shared.js). One row more than shown is
           asked for — whether it comes says whether there is a next page,
           without counting the whole table. */
        $page   = 15;
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        $hits   = friends_ready($pdo)
            ? user_search($pdo, $userId, (string) $_GET['friend_search'], $offset, $page + 1, false)
            : [];
        echo json_encode([
            'hits' => array_slice($hits, 0, $page),
            'more' => count($hits) > $page,
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'friends') {
        return $state;
    }
    csrf_check();

    if (!friends_ready($pdo)) {
        $state['error'][] = t('Die Migration 0041 ist noch nicht eingespielt.');
        return $state;
    }

    try {
        switch ((string) ($_POST['action'] ?? '')) {
            case 'request':
                $person = friendship_request($pdo, $userId, (int) ($_POST['who'] ?? 0));
                $state['message'] = t('Anfrage an %s geschickt.', user_display_name($person));
                break;
            case 'accept':
                friendship_accept($pdo, $userId, (int) ($_POST['id'] ?? 0));
                $state['message'] = t('Angenommen. Ihr seid jetzt Freunde.');
                break;
            // One action for three deeds, and since 02.10.2026 each says
            // what it did — before, all three answered with silence.
            case 'remove':
                $id = (int) ($_POST['id'] ?? 0);
                $name = '';
                foreach (friends_overview($pdo, $userId)['friends'] as $row) {
                    if ((int) $row['id'] === $id) {
                        $name = (string) $row['display_name'];
                    }
                }
                $state['message'] = match (friendship_remove($pdo, $userId, $id)) {
                    'declined'  => t('Abgelehnt.'),
                    'withdrawn' => t('Zurückgezogen.'),
                    default     => t('Die Freundschaft mit %s ist beendet.', $name),
                };
                break;
            /* NOT ONE OF THE TAB'S OWN: a module's section under the friends
               may have sent it (includes/modules.php, friends_process). The
               first module that knows the action answers with its message. */
            default:
                $state['message'] = module_first('friends_process', $pdo, $userId, (string) ($_POST['action'] ?? ''), $_POST);
                break;
        }
    } catch (InvalidArgumentException $e) {
        $state['error'][] = $e->getMessage();
    }

    return $state;
}

/** The view. $state comes from friends_process(). */
function friends_render(PDO $pdo, int $userId, array $state): void
{
    if (!friends_ready($pdo)) {
        notice('hint', t('Freunde und Gruppen brauchen die Migration 0041 — sie ist noch nicht eingespielt.'));
        return;
    }

    $overview    = friends_overview($pdo, $userId);
    $target = url('pages/administration.php') . '#friends';

    notice('error', $state['error'], true);
    if ($state['message'] !== null) {
        notice('success', $state['message'], true);
    }
    ?>

<?php /* PLAIN WORDS (02.10.2026). What friends are for names all
         three — groups and the chat are friends-only too (friends.php,
         chat.php), and that stood nowhere; then how one becomes one. */ ?>
<p class="hint-text"><?= h(t('Mit Freunden kannst du Listen teilen, Arbeitsgruppen bilden und auf der Karte miteinander schreiben.')) ?></p>
<p class="hint-text"><?= h(t('Ihr seid erst befreundet, wenn der andere deine Anfrage annimmt. Eine Anfrage schickst du mit „Neu".')) ?></p>

<?php /* FRIENDS LEFT, REQUESTS RIGHT — on the desk (02.10.2026:
         „irgendwann ist die rechte Spalte mit den Freunden recht voll", the
         requests then nearly always empty). The tab is opened for the
         friends, and a request still stands in sight at the top right,
         announced by the count at Kontakte besides. STACKED ON THE PHONE
         THE REQUESTS COME FIRST (.friend-requests, order in app.css): there
         a request would sink below a long list — the invitations' reason of
         25.08.2026, the short list that wants something comes first.
         THE DOM CARRIES THE DESK'S ORDER, not the phone's: the melting
         header bars and the inverted divider of .column-pair go by
         :first-child, and a visual swap on the desk would put them on the
         wrong sides. Stacked, they are switched off anyway. */ ?>
<div class="column-pair friend-columns">
    <div>
        <div class="column-header">
            <h3><?= h(t('Deine Freunde')) ?></h3>
        </div>

        <?php if ($overview['friends'] === []): ?>
            <p class="hint-text"><?= h(t('Du hast noch keine Freunde.')) ?></p>
        <?php else: ?>
            <ul class="person-list">
                <?php foreach ($overview['friends'] as $row): ?>
                    <li class="person-row">
                        <span class="person-name"><?= h($row['display_name']) ?></span>
                        <span class="person-buttons">
                            <?php /* A SPEECH BUBBLE STOOD HERE that linked to
                                 the map (09.09.2026:
                                 „bei Deine Freunde kann der Chat Button
                                 weg, wir haben das an anderer Stelle
                                 schon viel besser implementiert"). The
                                 way in is the flyout in the map's header:
                                 the whole list stands there with its
                                 unread marks, instead of one first going
                                 into the administration only to be sent
                                 back from there. */ ?>
                            <?php /* WHAT IS LOST, ALL OF IT (CLAUDE.md, dangerous
                                     buttons): until 02.10.2026 the question named only
                                     the groups — and the chat goes too, and the
                                     other's Freigaben, and since 02.10.2026 the
                                     pair's direct shares (friendship_remove). */ ?>
                            <form method="post" action="<?= h($target) ?>"
                                  data-confirm="<?= h(t('%s und du seid danach keine Freunde mehr. Euer Chat wird gelöscht. Ihr kommt aus den Arbeitsgruppen und Freigaben des anderen heraus. Was ihr euch direkt geteilt habt, seht ihr nicht mehr.', $row['display_name'])
                                      . "\n\n" . t('Eure Funde bleiben, wo sie sind. Befreunden könnt ihr euch jederzeit neu.')) ?>"
                                  data-confirm-title="<?= h(t('Freundschaft beenden?')) ?>"
                                  data-confirm-button="<?= h(t('Beenden')) ?>"
                                  data-confirm-danger>
                                <?= csrf_field() ?>
                                <input type="hidden" name="section" value="friends">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="symbol-button delete" type="submit" title="<?= h(t('Freundschaft beenden')) ?>"
                                        aria-label="<?= h(t('Freundschaft mit %s beenden', $row['display_name'])) ?>"><?= icon('delete') ?></button>
                            </form>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="friend-requests">
        <div class="column-header">
            <h3><?= h(t('Anfragen')) ?></h3>
            <button type="button" class="button small" id="friend-request-plus"
                    title="<?= h(t('Freundschaft anfragen')) ?>" aria-label="<?= h(t('Freundschaft anfragen')) ?>"><?= icon('plus') ?><?= h(t('Neu')) ?></button>
        </div>

        <?php if ($overview['incoming'] === [] && $overview['outgoing'] === []): ?>
            <p class="hint-text"><?= h(t('Gerade keine offenen Anfragen.')) ?></p>
        <?php else: ?>
            <ul class="person-list">
                <?php foreach ($overview['incoming'] as $row): ?>
                    <li class="person-row">
                        <span class="person-name">
                            <?= h($row['display_name']) ?>
                            <span class="item-data"><?= h(t('möchte mit dir befreundet sein')) ?></span>
                        </span>
                        <span class="person-buttons">
                            <form method="post" action="<?= h($target) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="section" value="friends">
                                <input type="hidden" name="action" value="accept">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="symbol-button" type="submit" title="<?= h(t('Annehmen')) ?>"
                                        aria-label="<?= h(t('Anfrage von %s annehmen', $row['display_name'])) ?>"><?= icon('check') ?></button>
                            </form>
                            <?php /* Declining without confirmation: nothing is lost,
                                 the other person may request again at any time. */ ?>
                            <form method="post" action="<?= h($target) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="section" value="friends">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <?php /* The cross, not the bucket: a request is a
                                     pending question, and one dismisses it —
                                     the person stays, and a new request is
                                     possible any time (22.08.2026). */ ?>
                                <button class="symbol-button delete" type="submit" title="<?= h(t('Ablehnen')) ?>"
                                        aria-label="<?= h(t('Anfrage von %s ablehnen', $row['display_name'])) ?>"><?= icon('close') ?></button>
                            </form>
                        </span>
                    </li>
                <?php endforeach; ?>
                <?php foreach ($overview['outgoing'] as $row): ?>
                    <li class="person-row">
                        <span class="person-name">
                            <?= h($row['display_name']) ?>
                            <span class="item-data"><?= h(t('deine Anfrage, noch ohne Antwort')) ?></span>
                        </span>
                        <span class="person-buttons">
                            <form method="post" action="<?= h($target) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="section" value="friends">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="symbol-button delete" type="submit" title="<?= h(t('Zurückziehen')) ?>"
                                        aria-label="<?= h(t('Anfrage an %s zurückziehen', $row['display_name'])) ?>"><?= icon('close') ?></button>
                            </form>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php /* WHAT THE MODULES ADD UNDER THE FRIENDS (includes/modules.php, hook
     friends_render): a section each, written out here — below the two
     columns, before the request window. The tab knows none of them. */
module_each('friends_render', $pdo, $userId, $overview, $target);
?>

<?php /* The request window. The search field deliberately carries NO name: it
     is not part of the submission, it only feeds the suggestions
     (shared.js). Submitting happens only once a suggestion has filled
     the who field. */ ?>
<dialog id="friend-search-window" class="form-window pinned-footer">
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="friends">
        <input type="hidden" name="action" value="request">
        <input type="hidden" name="who" id="friend-who" value="">

        <h2><?= h(t('Freundschaft anfragen')) ?></h2>

        <?php /* What the list is and what a tap does — the tap IS the
                 request, with no question in between, and that stood
                 nowhere (02.10.2026). */ ?>
        <p class="hint-text"><?= h(t('Hier stehen alle bei YASAIMAPP, mit denen du noch nicht befreundet bist. Tipp ein paar Buchstaben vom Namen ein, zum Beispiel „Ben". Dann bleiben nur die passenden übrig.')) ?></p>
        <p class="hint-text"><?= h(t('Tippst du auf eine Person, geht die Anfrage sofort los.')) ?></p>

        <div class="field">
            <label for="friend-search"><?= h(t('Name oder E-Mail-Adresse')) ?></label>
            <input type="search" id="friend-search" autocomplete="off">
        </div>

        <div class="suggestion-list" id="friend-suggestions" aria-live="polite"></div>

        <div class="dialog-footer">
            <button type="button" class="button subtle" id="friend-search-cancel"><?= h(t('Schließen')) ?></button>
        </div>
    </form>
</dialog>

    <?php
}
