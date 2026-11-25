<?php
/**
 * Verwaltung → Bildarchive: attach a shared folder of older pictures, see
 * what was read from it, name what was not recognised
 * (includes/picture_archives.php, migration 0181).
 *
 * A TAB OF ITS OWN, NOT A BLOCK UNDER CLOUD (02.11.2026). It
 * started under Cloud because an archive lives in a cloud — but the Cloud
 * tab connects one's OWN cloud to store finds in, and an archive is a READ
 * share hung in, maybe somebody else's, maybe from the same provider. Two
 * different things in one tab mixed up, and the names to assign, skip and
 * hide made this half long enough to stand alone.
 *
 * THE ORDER (02.11.2026): what it does, then the field for a
 * new link — a general thing, so above the folders, not after them —, then
 * a sub-tab „Freunde" (who sees what, for all folders) and one sub-tab per
 * folder with its state and the names read in it.
 *
 * Every scan runs inside the request: pCloud hands the whole tree in one
 * answer (2–6 s for the maintainer's 11,000 pictures), and a toast „gelesen"
 * that arrives before the reading would be a lie.
 */

declare(strict_types=1);

require_once __DIR__ . '/picture_archives.php';

function archives_process(PDO $pdo, int $userId): array
{
    $state = ['message' => null, 'error' => []];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'archives') {
        return $state;
    }
    csrf_check();
    if (!archives_ready($pdo)) {
        $state['error'][] = t('Bildarchive brauchen die Migration 0181 — sie ist noch nicht eingespielt.');
        return $state;
    }
    @set_time_limit(120);
    $id = (int) ($_POST['id'] ?? 0);
    try {
        switch ((string) ($_POST['action'] ?? '')) {
            case 'add':
                $r = archive_add($pdo, $userId, (string) ($_POST['link'] ?? ''));
                $state['message'] = archives_read_text($r);
                break;
            case 'rescan':
                $archive = archive_own($pdo, $userId, $id);
                $state['message'] = archives_read_text(archive_scan($pdo, $archive));
                break;
            case 'remove':
                $state['message'] = archive_remove($pdo, $userId, $id)
                    ? t('Das Archiv ist entfernt. Dein Ordner in der Cloud ist unverändert.')
                    : t('Dieses Archiv gibt es nicht mehr.');
                break;
            case 'names':
                $words = [];
                foreach ((array) ($_POST['written'] ?? []) as $i => $written) {
                    $species = trim((string) ($_POST['species'][$i] ?? ''));
                    $skip = !empty($_POST['skip'][$i]);
                    $words[(string) $written] = $skip ? '' : ($species === '' ? null : $species);
                }
                archive_names_save($pdo, $userId, $words);
                $read = ['pictures' => [], 'species' => 0, 'unplaced' => 0];
                foreach (archives_of($pdo, $userId) as $archive) {
                    $r = archive_scan($pdo, $archive);
                    $read['pictures'] = [...$read['pictures'], ...$r['pictures']];
                    $read['unplaced'] += $r['unplaced'];
                }
                $read['species'] = count(array_unique(array_column($read['pictures'], 'name')));
                $state['message'] = t('Gespeichert.') . ' ' . archives_read_text($read);
                break;
        }
    } catch (InvalidArgumentException $e) {
        $state['error'][] = error_for_user($e);
    } catch (RuntimeException $e) {
        $state['error'][] = t('Der Ordner liess sich nicht lesen: %s', error_for_user($e));
    }
    return $state;
}

/** The owner's archive by id — or the refusal a foreign id deserves. */
function archive_own(PDO $pdo, int $userId, int $id): array
{
    foreach (archives_of($pdo, $userId) as $a) {
        if ((int) $a['id'] === $id) return $a;
    }
    throw new InvalidArgumentException(t('Dieses Archiv gibt es nicht mehr.'));
}

/** „10.880 Bilder in 1.382 Arten gefunden. 40 Bilder warten auf einen Namen." */
function archives_read_text(array $r): string
{
    $text = t('%s Bilder in %s Arten gefunden.', number_format(count($r['pictures']), 0, ',', '.'), number_format($r['species'], 0, ',', '.'));
    if ($r['unplaced'] > 0) {
        $text .= ' ' . ($r['unplaced'] === 1 ? t('1 Bild wartet auf einen Namen.') : t('%d Bilder warten auf einen Namen.', $r['unplaced']));
    }
    return $text;
}

function archives_render(PDO $pdo, int $userId, array $state): void
{
    $target = url('pages/administration.php') . '#archives';
    notice('error', $state['error'], true);
    if ($state['message'] !== null) {
        notice('success', $state['message'], true);
    }
    ?>
<div class="archives">
    <?php /* No head of its own: the tab says „Bildarchive" already, and a
             heading over the whole tab would name it twice. */ ?>
    <p><?= h(t('Hast du Pilzbilder aus der Zeit vor YASAIMAPP in einer Cloud? Dann häng den Ordner hier an. Du siehst die Bilder dann in der Bestimmung unter „Funde", und deine Freunde auch. Welche Freunde was sehen, legst du unter „Freunde" fest.')) ?></p>
    <details class="info-fold">
        <summary><?= icon('help') ?><?= h(t('Wie findet YASAIMAPP die Art?')) ?></summary>
        <p class="hint-text"><?= h(t('YASAIMAPP liest die Namen deiner Ordner und Dateien. Es versteht „Clitocybe odora", „C. odora" in einem Ordner „Clitocybe" und deutsche Namen wie „Steinpilz". Der Ordner zählt mehr als der Dateiname.')) ?></p>
        <p class="hint-text"><?= h(t('Ein Ordner, dessen Name mit einem Tag beginnt, ist ein Fund. Zum Beispiel „2021-07-17 Kirn".')) ?></p>
        <p class="hint-text"><?= h(t('Was YASAIMAPP nicht versteht, steht beim Ordner in einer Liste. Dort schreibst du einmal den richtigen Namen dazu. Nur eine Gattung, „spec." oder „cf." bleiben weg.')) ?></p>
        <p class="hint-text"><?= h(t('YASAIMAPP ändert nichts in deinem Ordner. Jede Nacht schaut es nach, was sich dort geändert hat.')) ?></p>
    </details>

    <?php if (!archives_ready($pdo)): ?>
        <?php notice('warning', t('Bildarchive brauchen die Migration 0181 — sie ist noch nicht eingespielt.')); ?>
</div>
        <?php return; ?>
    <?php endif; ?>
    <?php $archives = archives_of($pdo, $userId); ?>

    <form method="post" action="<?= h($target) ?>" class="archives-add">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="archives">
        <input type="hidden" name="action" value="add">
        <div class="field">
            <label for="archive-link"><?= h($archives === [] ? t('Freigabe-Link deines Ordners') : t('Noch einen Ordner anhängen')) ?></label>
            <div class="field-row">
                <input type="url" id="archive-link" name="link" required autocomplete="off" spellcheck="false"
                       placeholder="https://e.pcloud.link/publink/show?code=…">
                <button type="submit" class="button"><?= h(t('Anhängen')) ?></button>
            </div>
            <p class="hint-text small"><?= h(t('Im Moment geht das nur mit pCloud. Dort: Ordner anklicken, „Teilen", „Link teilen", Link kopieren.')) ?></p>
        </div>
    </form>

    <?php if ($archives !== []): ?>
        <?php /* ONE SUB-TAB FOR THE FRIENDS, ONE PER FOLDER
                 (02.11.2026). Who sees what holds for all folders at once,
                 so it stands first and once; a folder's tab holds what is
                 that folder's own — its state and the names read in it. */ ?>
        <div class="tab" role="tablist" aria-label="<?= h(t('Bildarchive')) ?>">
            <button type="button" role="tab" id="tab-archive-friends" aria-controls="section-archive-friends"
                    data-anchor="archive-friends"><?= icon('people') ?><?= h(t('Freunde')) ?></button>
            <?php foreach ($archives as $a): ?>
                <button type="button" role="tab" id="tab-archive-<?= (int) $a['id'] ?>" aria-controls="section-archive-<?= (int) $a['id'] ?>"
                        data-anchor="archive-<?= (int) $a['id'] ?>"><?= icon('folder') ?><?= h($a['title'] !== '' ? $a['title'] : t('noch nicht gelesen')) ?></button>
            <?php endforeach; ?>
        </div>

        <section id="section-archive-friends" role="tabpanel" aria-labelledby="tab-archive-friends">
            <?php archives_friends($pdo, $userId); ?>
        </section>

        <?php foreach ($archives as $a): ?>
            <section id="section-archive-<?= (int) $a['id'] ?>" role="tabpanel" aria-labelledby="tab-archive-<?= (int) $a['id'] ?>" hidden>
                <?php archives_folder($pdo, $userId, $a, $target); ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
    <?php
}

/**
 * „Freunde": who sees one's archives, with or without the find spots, and
 * which genera and species stay hidden from whom (migration 0184,
 * archive_audience). THE TICKS SAVE AT ONCE (02.11.2026: „bei
 * solchen Haken würde ich ein Autosave machen") through
 * api/v1/archive_viewers.php — assets/js/archiveviewers.js; the filter
 * opens a window of its own per friend.
 *
 * „Neue Freunde" is the first row and the answer for whoever becomes a
 * friend later; changing it never reaches the friends one already has.
 */
function archives_friends(PDO $pdo, int $userId): void
{
    if (!archive_viewers_ready($pdo)) {
        notice('warning', t('Die Auswahl der Freunde braucht die Migration 0184 — sie ist noch nicht eingespielt. Bis dahin sehen alle deine Freunde die Bilder.'));
        return;
    }
    $audience = archive_audience($pdo, $userId);
    $row = static function (int $id, string $name, string $note, array $a): void {
        $hidden = [...$a['hidden']['genera'], ...$a['hidden']['species']];
        ?>
        <tr data-archive-friend="<?= $id ?>" data-name="<?= h($name) ?>"
            data-hidden="<?= h(json_encode($a['hidden'], JSON_UNESCAPED_UNICODE)) ?>">
            <td><?= h($name) ?><?php if ($note !== ''): ?><br><span class="hint-text small"><?= h($note) ?></span><?php endif; ?></td>
            <td><label class="checkbox"><input type="checkbox" data-what="sees"<?= $a['sees'] ? ' checked' : '' ?>
                       aria-label="<?= h(t('%s sieht die Bilder', $name)) ?>"></label></td>
            <td><label class="checkbox"><input type="checkbox" data-what="spots"<?= $a['spots'] ? ' checked' : '' ?><?= $a['sees'] ? '' : ' disabled' ?>
                       aria-label="<?= h(t('%s sieht die Fundorte', $name)) ?>"></label></td>
            <td class="archive-hidden-summary"><?= $hidden === [] ? '<span class="hint-text">' . h(t('nichts')) . '</span>' : h(implode(', ', $hidden)) ?></td>
            <td class="button-cell">
                <button type="button" class="button subtle small" data-archive-filter<?= $a['sees'] ? '' : ' disabled' ?>
                        title="<?= h(t('Gattungen und Arten, die %s nicht sieht', $name)) ?>"><?= icon('filter') ?><?= h(t('Filter')) ?></button>
            </td>
        </tr>
        <?php
    };
    ?>
    <p><?= h(t('Hier legst du fest, welche Freunde deine Bilder sehen. Das gilt für alle deine Ordner.')) ?></p>
    <p><?= h(t('Ohne Haken bei „Fundorte" sieht der Freund keine Koordinaten: nicht im Dateinamen und nicht in den Bildern. Den Ortsnamen sieht er weiter, zum Beispiel „Wiesbaden, Nerotal".')) ?></p>
    <p><?= h(t('Mit „Filter" nimmst du Gattungen oder Arten aus, zum Beispiel solche, an denen du gerade schreibst. Die sieht der Freund dann gar nicht.')) ?></p>
    <div class="table-frame">
        <table class="list archive-friends">
            <thead><tr><th><?= h(t('Freund')) ?></th><th><?= h(t('Sieht die Bilder')) ?></th><th><?= h(t('Fundorte')) ?></th><th><?= h(t('Nicht zu sehen')) ?></th><th></th></tr></thead>
            <tbody>
                <?php $row(0, t('Neue Freunde'), t('Wer später dein Freund wird'), $audience['new']); ?>
            </tbody>
            <?php if ($audience['friends'] !== []): ?>
                <tbody>
                    <tr><th colspan="5" class="list-label"><?= h(t('Deine Freunde')) ?></th></tr>
                    <?php foreach ($audience['friends'] as $f) $row($f['id'], $f['name'], '', $f); ?>
                </tbody>
            <?php endif; ?>
        </table>
    </div>

    <?php /* The filter's window, one for every row: archiveviewers.js fills
             in the friend and his lists when it opens. Every add and every
             cross saves at once, so the footer only closes. */ ?>
    <dialog class="dialog pinned-footer" id="archive-filter-window">
        <div class="archive-filter">
            <h2 class="dialog-title"></h2>
            <p class="hint-text"><?= h(t('Bilder dieser Gattungen und Arten sieht er nicht. Alles andere sieht er wie bisher.')) ?></p>
            <?php foreach (['genus' => [t('Gattungen'), t('Gattung, zum Beispiel „Inocybe"')], 'species' => [t('Arten'), t('Gattung und Art, zum Beispiel „Inocybe geophylla"')]] as $kind => [$label, $placeholder]): ?>
                <p class="sub-label"><?= h($label) ?></p>
                <div class="archive-filter-list" data-kind="<?= $kind ?>"></div>
                <div class="field-row rule-adder">
                    <input type="text" data-kind="<?= $kind ?>" maxlength="120" autocomplete="off" spellcheck="false"
                           aria-label="<?= h($placeholder) ?>" placeholder="<?= h($placeholder) ?>">
                    <button type="button" class="button subtle" data-archive-add="<?= $kind ?>"><?= icon('plus') ?><?= h(t('Hinzufügen')) ?></button>
                </div>
            <?php endforeach; ?>
            <p class="archive-filter-fault" role="alert" hidden></p>
            <div class="dialog-footer">
                <button type="button" class="button" data-close-window><?= h(t('Fertig')) ?></button>
            </div>
        </div>
    </dialog>
    <?php
}

/** A folder's own tab: its state and its one action, then the names read in it. */
function archives_folder(PDO $pdo, int $userId, array $a, string $target): void
{
    ?>
    <div class="table-frame">
        <table class="list">
            <thead><tr><th><?= h(t('Gefunden')) ?></th><th><?= h(t('Zuletzt gelesen')) ?></th><th></th></tr></thead>
            <tbody>
                <tr>
                    <td><?= h(t('%s Bilder in %s Arten', number_format((int) $a['pictures'], 0, ',', '.'), number_format((int) $a['species'], 0, ',', '.'))) ?>
                        <br><span class="hint-text small">pCloud</span>
                        <?php if ($a['status'] === 'failed'): ?>
                            <br><span class="hint-text small is-failed"><?= h((string) $a['error']) ?></span>
                        <?php endif; ?></td>
                    <td><?= $a['scanned_at'] === null ? '<span class="hint-text">' . h(t('noch nie')) . '</span>' : h(date('d.m.Y H:i', strtotime((string) $a['scanned_at']))) ?></td>
                    <td class="button-cell">
                        <form method="post" action="<?= h($target) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="section" value="archives">
                            <input type="hidden" name="action" value="rescan">
                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                            <button type="submit" class="button subtle small"><?= h(t('Neu einlesen')) ?></button>
                        </form>
                        <form method="post" action="<?= h($target) ?>"
                              data-confirm="<?= h(t('YASAIMAPP vergisst dann, was es in diesem Ordner gelesen hat. Deine Freunde sehen die Bilder nicht mehr. Der Ordner in deiner Cloud bleibt, wie er ist.')) ?>"
                              data-confirm-title="<?= h(t('Archiv entfernen?')) ?>"
                              data-confirm-button="<?= h(t('Entfernen')) ?>"
                              data-confirm-danger>
                            <?= csrf_field() ?>
                            <input type="hidden" name="section" value="archives">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                            <button type="submit" class="button danger small" title="<?= h(t('Entfernen')) ?>" aria-label="<?= h(t('Entfernen')) ?>"><?= icon('delete') ?></button>
                        </form>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php archives_names_form($pdo, $userId, $a, $target); ?>
    <?php
}

/**
 * What the reading of this folder could not place, and what the owner
 * already said — one table, because both are the same thing: a name in his
 * folders and the species it means. A row emptied and not ticked is
 * forgotten. What he said holds for ALL his folders (map_archive_names),
 * so „Schon zugeordnet" is the same list under every folder, and a
 * sentence says so.
 */
function archives_names_form(PDO $pdo, int $userId, array $archive, string $target): void
{
    $open = [];
    foreach (json_decode((string) ($archive['unresolved'] ?? ''), true) ?: [] as $u) {
        $key = mb_strtolower($u['written']);
        if (isset($open[$key])) {
            $open[$key]['pictures'] += $u['pictures'];
        } else {
            $open[$key] = $u;
        }
    }
    $said = archive_names($pdo, $userId);
    if ($open === [] && $said === []) {
        return;
    }
    $i = 0;
    $row = static function (string $written, string $where, ?int $pictures, string $species, bool $skip) use (&$i): void {
        ?>
        <tr>
            <td><?= h($written) ?><input type="hidden" name="written[<?= $i ?>]" value="<?= h($written) ?>">
                <?php if ($where !== ''): ?><br><span class="hint-text small"><?= h($where) ?></span><?php endif; ?></td>
            <td class="number"><?= $pictures === null ? '' : (int) $pictures ?></td>
            <td><input type="text" name="species[<?= $i ?>]" value="<?= h($species) ?>" autocomplete="off" spellcheck="false"
                       placeholder="<?= h(t('Gattung Art')) ?>" aria-label="<?= h(t('Art zu „%s"', $written)) ?>"></td>
            <td><label class="checkbox"><input type="checkbox" name="skip[<?= $i ?>]" value="1"<?= $skip ? ' checked' : '' ?>><?= h(t('weglassen')) ?></label></td>
        </tr>
        <?php
        $i++;
    };
    ?>
    <form method="post" action="<?= h($target) ?>" class="archives-names">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="archives">
        <input type="hidden" name="action" value="names">
        <?php if ($said !== []): ?>
            <p class="hint-text"><?= h(t('Ein Name, den du einmal zuordnest, gilt in allen deinen Ordnern.')) ?></p>
        <?php endif; ?>
        <div class="table-frame">
            <table class="list">
                <thead><tr><th><?= h(t('Name im Ordner')) ?></th><th><?= h(t('Bilder')) ?></th><th><?= h(t('Gemeinte Art')) ?></th><th></th></tr></thead>
                <?php if ($open !== []): ?>
                    <tbody>
                        <tr><th colspan="4" class="list-label"><?= h(t('Nicht erkannt')) ?></th></tr>
                        <?php foreach ($open as $u) $row($u['written'], $u['path'], (int) $u['pictures'], '', false); ?>
                    </tbody>
                <?php endif; ?>
                <?php if ($said !== []): ?>
                    <tbody>
                        <tr><th colspan="4" class="list-label"><?= h(t('Schon zugeordnet')) ?></th></tr>
                        <?php ksort($said, SORT_NATURAL); foreach ($said as $written => $species) $row(mb_strtoupper(mb_substr((string) $written, 0, 1)) . mb_substr((string) $written, 1), '', null, (string) $species, $species === ''); ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
        <div class="button-row right">
            <button type="submit" class="button subtle" data-quiet-until-changed><?= h(t('Speichern und neu einlesen')) ?></button>
        </div>
    </form>
    <?php
}
