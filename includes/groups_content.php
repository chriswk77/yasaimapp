<?php
/**
 * Working groups — processing and view, a tab of its own next to the
 * friends. (They USED TO stand in one: whoever fills a group reaches into
 * their friends list. That argument did not survive the switch to column
 * pairs — two pairs stacked were a wall again, and adding members shows
 * the friends in its own window anyway. Separated at the request,
 * 13.08.2026.)
 *
 * ONE COLUMN PAIR as with the friends: on the left one's own groups, on
 * the right the members of the clicked one. On the right always stands
 * exactly ONE group — all side by side (the old state) turned into
 * wallpaper with the third group, on which you had to hunt for your row.
 *
 * Which group stands on the right, the script decides (shared.js)
 * without reloading; after a form submission the server preselects the
 * group that was just concerned — otherwise the view would jump back to
 * the first one after every member added.
 */

declare(strict_types=1);

require_once __DIR__ . '/friends.php';
/* For container_using_group(): the tab says where a group opens doors, and
   that is a question about containers. friends.php pulls container.php in
   itself further down, but only inside a function — a file that uses a
   function names its own source. */
require_once __DIR__ . '/container.php';

/**
 * Must run BEFORE any output.
 *
 * @return array{message: ?string, error: string[], group: int}
 */
function groups_process(PDO $pdo, int $userId): array
{
    $state = ['message' => null, 'error' => [], 'group' => 0];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'groups') {
        return $state;
    }
    csrf_check();

    if (!friends_ready($pdo)) {
        $state['error'][] = t('Die Migration 0041 ist noch nicht eingespielt.');
        return $state;
    }

    try {
        switch ((string) ($_POST['action'] ?? '')) {
            case 'create':
                $state['group'] = group_create($pdo, $userId, (string) ($_POST['name'] ?? ''));
                break;
            case 'delete':
                group_delete($pdo, $userId, (int) ($_POST['group'] ?? 0));
                break;
            case 'add':
                group_add($pdo, $userId, (int) ($_POST['group'] ?? 0), (int) ($_POST['who'] ?? 0));
                $state['group'] = (int) ($_POST['group'] ?? 0);
                break;
            case 'remove':
                group_remove($pdo, $userId, (int) ($_POST['group'] ?? 0), (int) ($_POST['who'] ?? 0));
                $state['group'] = (int) ($_POST['group'] ?? 0);
                break;
        }
    } catch (InvalidArgumentException $e) {
        $state['error'][] = error_for_user($e);
    }

    return $state;
}

/** The view. $state comes from groups_process(). */
function groups_render(PDO $pdo, int $userId, array $state): void
{
    if (!friends_ready($pdo)) {
        // Its own message, no longer the friends' one: since the split into
        // two tabs, their notice is not visible from here.
        notice('hint', t('Arbeitsgruppen brauchen die Migration 0041 — sie ist noch nicht eingespielt.'));
        return;
    }

    $overview       = groups_overview($pdo, $userId);
    $friends = friends_overview($pdo, $userId)['friends'];
    $target    = url('pages/administration.php') . '#groups';

    // Preselected is the group from the last action; if the page knows
    // none (fresh call, group just deleted or left), the first one — own
    // ones before foreign ones, as in the list.
    /* Only one's own — the foreign ones are not drawn any more, so a
       remembered selection pointing at one of them would open nothing. */
    $allIds = array_map(fn($g) => (int) $g['id'], $overview['own']);
    $selected    = in_array($state['group'], $allIds, true) ? $state['group'] : ($allIds[0] ?? 0);

    notice('error', $state['error'], true);
    if ($state['message'] !== null) {
        notice('success', $state['message'], true);
    }
    ?>

<p class="hint-text">
    <?= h(t('Eine Arbeitsgruppe fasst Freunde zusammen, damit du mit allen auf einmal teilen kannst statt mit jedem einzeln.')) ?>
</p>

<div class="column-pair">
    <div>
        <div class="column-header">
            <h3><?= h(t('Gruppen')) ?></h3>
            <button type="button" class="button small" id="group-plus"
                    title="<?= h(t('Neue Arbeitsgruppe')) ?>" aria-label="<?= h(t('Neue Arbeitsgruppe')) ?>"><?= icon('plus') ?><?= h(t('Neu')) ?></button>
        </div>

        <?php if ($overview['own'] === []): ?>
            <p class="hint-text"><?= h(t('Noch keine. Über den Knopf „Neu“ legst du eine an.')) ?></p>
        <?php else: ?>
            <ul class="person-list">
                <?php /* ONLY ONE'S OWN GROUPS STAND HERE, and the foreign ones
                     no longer at all (22.08.2026). They used to,
                     under a heading of their own — and the only thing one
                     could DO with such a row was to leave the group, which
                     drops one out of every container it hangs in at once.
                     Since migration 0067 one leaves containers one by one,
                     where one sees what one is leaving, so the row lost its
                     purpose: what remained was a list one cannot act on,
                     growing with every friend who founds a group.

                     A group one is only a member of shows itself where it
                     has an effect — in the container that admitted it, in
                     the "Mit dabei" line. Not in a register of memberships.

                     With them the heading went too: what does not stand
                     beside anything separates nothing. */ ?>
                <?php foreach ($overview['own'] as $g): ?>
                    <li class="person-row">
                        <button type="button"
                                class="group-selection<?= (int) $g['id'] === $selected ? ' is-active' : '' ?>"
                                data-group="<?= (int) $g['id'] ?>">
                            <?= h($g['name']) ?>
                            <span class="item-data"><?= h(count($g['members']) === 1
                                ? t('1 Mitglied') : t('%d Mitglieder', count($g['members']))) ?></span>
                        </button>
                        <span class="person-buttons">
                            <form method="post" action="<?= h($target) ?>"
                                  data-confirm="<?= h(t('Die Gruppe %s verschwindet für alle Mitglieder.', $g['name'])) ?>"
                                  data-confirm-title="<?= h(t('Gruppe löschen?')) ?>"
                                  data-confirm-button="<?= h(t('Löschen')) ?>"
                                  data-confirm-danger>
                                <?= csrf_field() ?>
                                <input type="hidden" name="section" value="groups">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="group" value="<?= (int) $g['id'] ?>">
                                <button class="symbol-button delete" type="submit" title="<?= h(t('Gruppe löschen')) ?>"
                                        aria-label="<?= h(t('Gruppe %s löschen', $g['name'])) ?>"><?= icon('delete') ?></button>
                            </form>
                        </span>
                    </li>
                <?php endforeach; ?>
                <?php /* "von X" says here too whose group it is.

                     THE DOOR AND NOT THE BUCKET (the maintainer asked what the
                     symbol does here, 22.08.2026 — and whoever has to ask
                     has already been told something wrong). In this
                     project the bucket means: a record ceases to exist.
                     Here nothing ceases: the group carries on with
                     everybody else, only without you. The door with the
                     arrow says exactly that, and it is the same drawing
                     as on the sign-out. Red it stays — you cannot undo it
                     alone, the owner has to let you back in. */ ?>
            </ul>
        <?php endif; ?>
    </div>

    <div id="group-detail">
        <?php /* On the right first the own blocks (with all controls), then the
             foreign ones: only owner and members, no plus and no bucket.
             Everyone inside may see who reads along — only the owner
             manages. Leaving sits on the left, at the row's bucket. */ ?>
        <?php foreach ($overview['own'] as $g): ?>
            <div class="gr-members" data-group="<?= (int) $g['id'] ?>"
                 <?= (int) $g['id'] === $selected ? '' : 'hidden' ?>>
                <div class="column-header">
                    <h3><?= h($g['name']) ?></h3>
                    <button type="button" class="symbol-button gr-admit"
                            data-group="<?= (int) $g['id'] ?>" data-name="<?= h($g['name']) ?>"
                            title="<?= h(t('Freund aufnehmen')) ?>"
                            aria-label="<?= h(t('Freund in %s aufnehmen', $g['name'])) ?>"><?= icon('plus') ?></button>
                </div>

                <?php
                /* WHERE THIS GROUP OPENS DOORS — above the members and not
                   below them (22.08.2026). Whoever stands here
                   is about to add or remove somebody, and the question at
                   that moment is not "who is in" — that is right below —
                   but "what does it get them". Reading it afterwards is
                   reading it too late.

                   A LIST AND NOT A SENTENCE (same day: „Nein,
                   ohne langen Text"). The names ARE the information; a
                   sentence around them explains what one already sees and
                   has to be read every time to get at the two words that
                   matter. It reads like the member list below it, because
                   it is the same kind of thing: what this group touches. */
                $usedIn = container_using_group($pdo, (int) $g['id']);
                ?>
                <?php /* Two lists under one heading want to say which is which
                     (22.08.2026: „noch mit einem kleinen Label
                     oben drüber"). .person-section is the label this
                     project already has for a part of a list — the same one
                     the group list used until this morning. */ ?>
                <ul class="person-list gr-containers">
                    <li class="person-section"><?= h(t('Enthalten in Freigaben')) ?></li>
                    <?php if ($usedIn === []): ?>
                        <li class="person-row"><span class="person-name"><?= h(t('in keinem')) ?></span></li>
                    <?php else: ?>
                        <?php foreach ($usedIn as $c): ?>
                            <li class="person-row">
                                <span class="person-name"><?= icon('container') ?><?= h($c['name']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <ul class="person-list">
                    <li class="person-section"><?= h(t('Mitglieder')) ?></li>
                    <?php if ($g['members'] === []): ?>
                        <li class="person-row">
                            <span class="person-name"><?= h(t('noch keine — über das Plus aufnehmen')) ?></span>
                        </li>
                    <?php else: ?>
                        <?php foreach ($g['members'] as $m): ?>
                            <li class="person-row">
                                <span class="person-name"><?= h($m['display_name']) ?></span>
                                <span class="person-buttons">
                                    <?php /* No confirmation: adding is one click
                                         away, nothing is lost. */ ?>
                                    <form method="post" action="<?= h($target) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="section" value="groups">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="group" value="<?= (int) $g['id'] ?>">
                                        <input type="hidden" name="who" value="<?= (int) $m['id'] ?>">
                                        <button class="symbol-button delete" type="submit" title="<?= h(t('Entfernen')) ?>"
                                                aria-label="<?= h(t('%s aus %s entfernen', $m['display_name'], $g['name'])) ?>"><?= icon('delete') ?></button>
                                    </form>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php /* The creation window: only the name. The group comes into being empty —
     filled afterwards via the plus in the members column, where that
     control lives anyway. (THE FRIENDS ONCE STOOD HERE as checkboxes for
     adding right away; removed at the request, 12.08.2026.) */ ?>
<dialog id="group-new-window" class="form-window pinned-footer">
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="groups">
        <input type="hidden" name="action" value="create">

        <h2><?= h(t('Neue Arbeitsgruppe')) ?></h2>

        <div class="field">
            <label for="group-name"><?= h(t('Name')) ?></label>
            <input type="text" id="group-name" name="name" required maxlength="100"
                   placeholder="<?= h(t('z. B. Kartierung Nordhang')) ?>">
        </div>

        <div class="dialog-footer">
            <button type="button" class="button subtle" id="group-new-cancel"><?= h(t('Abbrechen')) ?></button>
            <button type="submit" class="button"><?= h(t('Anlegen')) ?></button>
        </div>
    </form>
</dialog>

<?php /* ONE add window for all groups, like the user window of the member
     administration: one per group would be a heap of forms nobody ever
     sees. Whom it offers for selection, shared.js computes from the
     data below — friends minus those already in. */ ?>
<dialog id="group-admit-window" class="form-window pinned-footer">
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="groups">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="group" id="group-admit-group" value="">
        <input type="hidden" name="who" id="group-admit-who" value="">

        <h2 id="group-admit-title"><?= h(t('Freund aufnehmen')) ?></h2>

        <div class="suggestion-list" id="group-admit-candidates"></div>

        <div class="dialog-footer">
            <button type="button" class="button subtle" id="group-admit-cancel"><?= h(t('Schließen')) ?></button>
        </div>
    </form>
</dialog>

<script type="application/json" id="shared-data">
<?= json_encode([
    'friends' => array_map(fn($f) => [
        'id'   => (int) $f['user_id'],
        'name' => $f['display_name'],
    ], $friends),
    'groups' => (object) array_combine(
        array_map(fn($g) => (string) $g['id'], $overview['own']),
        array_map(fn($g) => array_map(fn($m) => (int) $m['id'], $g['members']), $overview['own'])
    ),
], JSON_UNESCAPED_UNICODE) ?>
</script>

    <?php
}
