<?php
/**
 * Einstellungen → Arten → „Arbeitsnamen" (includes/working_names.php,
 * migration 0189). Under Arten because it is about the names one picks in
 * the title field — the place where the groups of the suggestions are
 * chosen (the answer, 05.11.2026).
 *
 * The forms belong to the settings tab (section „settings"), so a save
 * rebuilds that tab inline like every other setting; settings_process()
 * hands the two actions here.
 *
 * ONE WINDOW FOR NEW AND CHANGE: the pencil fills it (workingnames.js),
 * „Neu" opens it empty. The status is radios, not a dropdown — four
 * answers stand in sight („What there is to choose stands in sight").
 */

declare(strict_types=1);

require_once __DIR__ . '/working_names.php';

/** The two actions of the settings form — the toast's text, or null when the action is not ours. */
function working_names_process(PDO $pdo, int $userId, string $action): ?string
{
    if (!in_array($action, ['working_name_save', 'working_name_delete', 'working_name_publish'], true)) {
        return null;
    }
    if (!working_names_ready($pdo)) {
        throw new InvalidArgumentException(t('Arbeitsnamen brauchen die Migration 0189 — sie ist noch nicht eingespielt.'));
    }
    if ($action === 'working_name_publish') {
        $r = working_name_publish($pdo, $userId, (int) ($_POST['id'] ?? 0), (string) ($_POST['published'] ?? ''));
        return $r['finds'] === 1
            ? t('Veröffentlicht. 1 Fund heißt jetzt „%s".', $r['name'])
            : t('Veröffentlicht. %d Funde heißen jetzt „%s".', $r['finds'], $r['name']);
    }
    if ($action === 'working_name_delete') {
        working_name_delete($pdo, $userId, (int) ($_POST['id'] ?? 0));
        return t('Arbeitsname gelöscht. Deine Funde behalten ihren Titel.');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $id = working_name_save($pdo, $userId, $id > 0 ? $id : null, (string) ($_POST['genus'] ?? ''),
        (string) ($_POST['label'] ?? ''), (string) ($_POST['status'] ?? ''), (string) ($_POST['note'] ?? ''));
    working_name_share($pdo, $userId, $id, (array) ($_POST['share_users'] ?? []), (array) ($_POST['share_groups'] ?? []));
    return t('Gespeichert.');
}

function working_names_render(PDO $pdo, int $userId): void
{
    $target = url('pages/administration.php') . '#species-groups';
    ?>
    <hr class="section-rule">
    <div class="column-header">
        <h3><?= h(t('Arbeitsnamen')) ?></h3>
        <?php if (working_names_ready($pdo)): ?>
            <button type="button" class="button small" data-working-name-new><?= icon('plus') ?><?= h(t('Neu')) ?></button>
        <?php endif; ?>
    </div>
    <p class="hint-text"><?= h(t('Für Arten, die in keiner Namensliste stehen. Zum Beispiel eine Art, die du gerade beschreibst und die noch nicht veröffentlicht ist.')) ?></p>
    <p class="hint-text"><?= h(t('Du legst sie einmal an: die Gattung, deinen Namen für die Art und einen Zusatz wie „ad int.". Danach schlägt YASAIMAPP sie im Titelfeld vor wie jede andere Art. Ein Fund mit diesem Namen gilt als bestimmt und gehört zu seiner Gattung.')) ?></p>
    <?php if (!working_names_ready($pdo)): ?>
        <?php notice('warning', t('Arbeitsnamen brauchen die Migration 0189 — sie ist noch nicht eingespielt.')); ?>
        <?php return; ?>
    <?php endif; ?>
    <?php
    $all = working_names_visible($pdo, $userId);
    $own = array_values(array_filter($all, static fn($w) => $w['own']));
    $shared = array_values(array_filter($all, static fn($w) => !$w['own']));
    $counts = working_name_counts($pdo, array_column($all, 'id'));
    require_once __DIR__ . '/friends.php';
    $friends = friends_ready($pdo) ? friends_overview($pdo, $userId)['friends'] : [];
    $groups = friends_ready($pdo) ? groups_overview($pdo, $userId)['own'] : [];
    $names = [];
    foreach ($friends as $f) $names['u' . (int) $f['user_id']] = (string) $f['display_name'];
    foreach ($groups as $g) $names['g' . (int) $g['id']] = (string) $g['name'];
    ?>
    <?php if ($all === []): ?>
        <p class="hint-text"><?= h(t('Noch keine.')) ?></p>
    <?php else: ?>
        <div class="table-frame">
            <table class="list working-names">
                <thead><tr><th><?= h(t('Name')) ?></th><th><?= h(t('Funde')) ?></th><th></th></tr></thead>
                <?php if ($own !== []): ?>
                    <tbody>
                        <?php if ($shared !== []): ?><tr><th colspan="3" class="list-label"><?= h(t('Deine')) ?></th></tr><?php endif; ?>
                        <?php foreach ($own as $w): ?>
                            <tr>
                                <?php
                                $viewers = working_name_viewers($pdo, $w['id']);
                                $with = array_filter(array_merge(
                                    array_map(static fn($u) => $names['u' . $u] ?? null, $viewers['users']),
                                    array_map(static fn($g) => $names['g' . $g] ?? null, $viewers['groups'])));
                                ?>
                                <td><?= h($w['name']) ?>
                                    <?php if (($w['note'] ?? '') !== ''): ?><br><span class="hint-text small"><?= h((string) $w['note']) ?></span><?php endif; ?>
                                    <br><span class="hint-text small"><?= h($with === [] ? t('nur für dich') : t('geteilt mit %s', implode(', ', $with))) ?></span></td>
                                <td class="number"><?= (int) ($counts[$w['id']] ?? 0) ?></td>
                                <td class="button-cell">
                                    <button type="button" class="button subtle small" title="<?= h(t('Ändern')) ?>" aria-label="<?= h(t('„%s" ändern', $w['name'])) ?>"
                                            data-working-name-edit data-id="<?= $w['id'] ?>" data-genus="<?= h($w['genus']) ?>"
                                            data-label="<?= h($w['label']) ?>" data-status="<?= h($w['status']) ?>"
                                            data-note="<?= h((string) ($w['note'] ?? '')) ?>"
                                            data-users="<?= h(implode(',', $viewers['users'])) ?>"
                                            data-groups="<?= h(implode(',', $viewers['groups'])) ?>"><?= icon('pencil') ?></button>
                                    <button type="button" class="button subtle small" title="<?= h(t('Veröffentlicht als …')) ?>"
                                            aria-label="<?= h(t('„%s" ist veröffentlicht', $w['name'])) ?>"
                                            data-working-name-publish data-id="<?= $w['id'] ?>" data-name="<?= h($w['name']) ?>"
                                            data-genus="<?= h($w['genus']) ?>" data-finds="<?= (int) ($counts[$w['id']] ?? 0) ?>"><?= icon('check') ?></button>
                                    <form method="post" action="<?= h($target) ?>"
                                          data-confirm="<?= h(t('Deine Funde mit diesem Namen behalten ihren Titel. Sie gelten dann aber nicht mehr als bestimmte Art, und YASAIMAPP schlägt den Namen nicht mehr vor.')) ?>"
                                          data-confirm-title="<?= h(t('„%s" löschen?', $w['name'])) ?>"
                                          data-confirm-button="<?= h(t('Löschen')) ?>"
                                          data-confirm-danger>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="section" value="settings">
                                        <input type="hidden" name="action" value="working_name_delete">
                                        <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                        <button type="submit" class="button danger small" title="<?= h(t('Löschen')) ?>" aria-label="<?= h(t('„%s" löschen', $w['name'])) ?>"><?= icon('delete') ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endif; ?>
                <?php if ($shared !== []): ?>
                    <tbody>
                        <tr><th colspan="3" class="list-label"><?= h(t('Von Freunden geteilt')) ?></th></tr>
                        <?php foreach ($shared as $w): ?>
                            <tr>
                                <td><?= h($w['name']) ?><br><span class="hint-text small"><?= h(t('von %s', $w['owner'])) ?></span></td>
                                <td class="number"><?= (int) ($counts[$w['id']] ?? 0) ?></td>
                                <td></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>

    <?php $published = working_names_published($pdo, $userId); ?>
    <?php if ($published !== []): ?>
        <p class="sub-label"><?= h(t('Veröffentlicht')) ?></p>
        <ul class="working-names-published">
            <?php foreach ($published as $p): ?>
                <li><?= h(t('„%s" heißt seit %s „%s".', (string) $p['name'], date('d.m.Y', strtotime((string) $p['published_at'])), (string) $p['published_as'])) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php /* „Veröffentlicht als …": its own window, because what it does
             reaches the colleagues' finds — the window says how many and
             that their pictures follow, before anything moves. */ ?>
    <dialog class="dialog pinned-footer" id="working-name-publish-window">
        <form method="post" action="<?= h($target) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="settings">
            <input type="hidden" name="action" value="working_name_publish">
            <input type="hidden" name="id" value="">
            <h2 class="dialog-title"><?= h(t('Veröffentlicht als …')) ?></h2>
            <p class="dialog-text" data-publish-text
               data-one="<?= h(t('„%1$s" bekommt seinen richtigen Namen. Dein Fund und die Funde der Kollegen, die ihn benutzen, heißen danach so. Die Bilder in der Cloud ziehen mit um. Die Kollegen bekommen eine Nachricht.')) ?>"></p>
            <div class="field">
                <label for="working-name-published"><?= h(t('Veröffentlichter Name')) ?></label>
                <input type="text" id="working-name-published" name="published" required maxlength="200" autocomplete="off" spellcheck="false"
                       placeholder="<?= h(t('Gattung und Art, zum Beispiel „Inocybe violacea"')) ?>">
                <p class="hint-text small"><?= h(t('Steht der Name noch in keiner Namensliste, ist das in Ordnung. Neue Arten kommen dort oft erst Monate später an.')) ?></p>
            </div>
            <div class="dialog-footer">
                <button type="button" class="button subtle" data-close-window><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button"><?= h(t('Umbenennen')) ?></button>
            </div>
        </form>
    </dialog>

    <dialog class="dialog working-name-window pinned-footer" id="working-name-window">
        <form method="post" action="<?= h($target) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="settings">
            <input type="hidden" name="action" value="working_name_save">
            <input type="hidden" name="id" value="">
            <h2 class="dialog-title" data-new="<?= h(t('Neuer Arbeitsname')) ?>" data-edit="<?= h(t('Arbeitsname ändern')) ?>"></h2>
            <div class="field">
                <label for="working-name-genus"><?= h(t('Gattung')) ?></label>
                <input type="text" id="working-name-genus" name="genus" required maxlength="120" autocomplete="off" spellcheck="false"
                       placeholder="<?= h(t('zum Beispiel Inocybe')) ?>">
            </div>
            <div class="field">
                <label for="working-name-label"><?= h(t('Dein Name für die Art')) ?></label>
                <input type="text" id="working-name-label" name="label" required maxlength="160" autocomplete="off" spellcheck="false"
                       placeholder="<?= h(t('zum Beispiel nova, sp. 1 oder \'violacea\'')) ?>">
            </div>
            <fieldset class="field question">
                <legend><?= h(t('Welcher Zusatz?')) ?></legend>
                <label class="choice-row"><input type="radio" name="status" value="" checked> <?= h(t('keiner')) ?></label>
                <?php foreach (WORKING_NAME_STATUSES as $key => $latin): ?>
                    <label class="choice-row"><input type="radio" name="status" value="<?= h($key) ?>"> <?= h($latin) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <p class="hint-text"><?= h(t('So steht er dann im Titel:')) ?> <strong data-working-name-preview></strong></p>
            <div class="field">
                <label for="working-name-note"><?= h(t('Notiz')) ?></label>
                <textarea id="working-name-note" name="note" rows="2" placeholder="<?= h(t('nur für dich und die, mit denen du ihn teilst')) ?>"></textarea>
            </div>
            <?php /* WITH WHOM — friends and the working groups one leads,
                     the same ticks as everywhere one shares. Whoever is
                     ticked may title his own finds with it; nobody but its
                     maker changes it. */ ?>
            <fieldset class="field question">
                <legend><?= h(t('Mit wem teilst du ihn?')) ?></legend>
                <p class="hint-text"><?= h(t('Wer hier einen Haken hat, bekommt den Namen im Titelfeld vorgeschlagen und kann seine eigenen Funde so benennen. Ändern kannst nur du ihn.')) ?></p>
                <?php if ($friends === [] && $groups === []): ?>
                    <p class="hint-text"><?= h(t('Du hast noch keine Freunde.')) ?></p>
                <?php endif; ?>
                <?php foreach ($groups as $g): ?>
                    <label class="checkbox"><input type="checkbox" name="share_groups[]" value="<?= (int) $g['id'] ?>"> <?= icon('group') ?><?= h((string) $g['name']) ?></label>
                <?php endforeach; ?>
                <?php foreach ($friends as $f): ?>
                    <label class="checkbox"><input type="checkbox" name="share_users[]" value="<?= (int) $f['user_id'] ?>"> <?= h((string) $f['display_name']) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <div class="dialog-footer">
                <button type="button" class="button subtle" data-close-window><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button"><?= h(t('Speichern')) ?></button>
            </div>
        </form>
    </dialog>
    <?php
}
