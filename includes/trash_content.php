<?php
/**
 * Trash — processing and view.
 *
 * A file of its own because both were needed in two places: there used to be
 * a dedicated page for it, now it sits as a tab in the administration. Two
 * versions of the same form would be the surest way to let them drift apart —
 * and of all places, this is where the application's only action lives that
 * cannot be taken back.
 */

declare(strict_types=1);

require_once __DIR__ . '/excursions.php';

/**
 * Restore or remove for good. Must run BEFORE any output.
 *
 * @return array|null  Message as [kind, text], or null
 */
function trash_process(PDO $pdo, int $userId): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'trash') {
        return null;
    }
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    /* A FILING BACK INTO ITS JOINT LIST (step 2, 22.09.2026): point and
       list, any member; the record revives with it if it lay in the trash
       as a whole. Before the id gathering below — this form carries no
       ids[]. */
    if ($action === 'joint_restore') {
        require_once __DIR__ . '/joint_lists.php';
        // „<point>:<list>" on the button — the row lives inside the page's one
        // big form (HTML knows no nested forms), so the two ids travel in
        // one value, as the excursion rows travel their id.
        [$point, $list] = array_map('intval', explode(':', (string) ($_POST['joint'] ?? '0:0')) + [0, 0]);
        try {
            $ok = joint_filing_restore($pdo, $userId, $point, $list);
        } catch (InvalidArgumentException $e) {
            return ['error', error_for_user($e)];
        }
        return $ok
            ? ['success', t('Zurückgeholt — der Punkt liegt wieder in der Liste, bei allen beim nächsten Abgleich.')]
            : ['error', t('Nichts zurückgeholt — die Ablage liegt nicht mehr im Papierkorb.')];
    }

    // Single or several — the same path. A button in the row sends one id,
    // the bulk action sends many.
    $ids = array_values(array_unique(array_filter(array_map(
        'intval',
        isset($_POST['ids']) ? (array) $_POST['ids'] : [$_POST['id'] ?? 0]
    ))));

    if ($ids === []) {
        return ['hint', t('Nichts ausgewählt.')];
    }

    /* EACH ID RESOLVES FOR ITSELF: own first, else the excursion scope
       (leader's right, then the operator's — excursion_point_trash_scope).
       That way the bulk selection may mix own points and excursion finds
       and both answers stay honest; an id that resolves to nothing is
       skipped like any unknown one. */
    $me = user_current() ?? ['id' => $userId, 'role' => ''];
    $as = static function (int $id) use ($pdo, $userId, $me): int {
        $stmt = $pdo->prepare('SELECT 1 FROM map_points WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        if ($stmt->fetchColumn() !== false) {
            return $userId;
        }
        return excursion_point_trash_scope($pdo, $me, $id) ?? 0;
    };

    // A former co-owner's entries answer to the same two buttons: back in
    // as a co-owner, or out for good with his copies (point_owners.php).
    require_once __DIR__ . '/point_owners.php';
    /* MY OWN TRASH ENTRY COMES FIRST. An excursion leader who co-owned a
       guest's find and stepped out has TWO ways to this id — her leader
       scope and her former-co-owner row — and the scope won the first
       time: „Endgültig" then tried to bucket a living find as its leader
       and answered „gibt es nicht mehr". What lies in my trash as mine
       is handled as mine; the scopes only for what does not. */
    $left = static function (int $id) use ($pdo, $userId): bool {
        $stmt = $pdo->prepare('SELECT 1 FROM map_point_owners WHERE point_id = ? AND user_id = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$id, $userId]);
        return $stmt->fetchColumn() !== false;
    };
    // Two counts, two sentences: a find brought back is not the same
    // deed as coming back to one, and a find removed is not the same as
    // being forgotten at one — a message must say which happened.
    if ($action === 'restore') {
        $n = 0; $back = 0;
        foreach ($ids as $id) {
            if ($left($id)) { if (point_owner_restore($pdo, $id, $userId)) $back++; continue; }
            $owner = $as($id);
            if ($owner > 0 && point_restore($pdo, $owner, $id)) $n++;
        }
        $parts = [];
        if ($n)    $parts[] = $n === 1 ? t('Punkt zurückgeholt.') : t('%d Punkte zurückgeholt.', $n);
        if ($back) $parts[] = $back === 1 ? t('Bei einem Fund wieder Mitbesitzer.') : t('Bei %d Funden wieder Mitbesitzer.', $back);
        return $parts !== []
            ? ['success', implode(' ', $parts)]
            : ['error', t('Nichts zurückgeholt — die Punkte gibt es nicht mehr.')];
    }

    if ($action === 'purge') {
        require_once __DIR__ . '/joint_lists.php';
        $n = 0; $gone = 0; $held = 0;
        foreach ($ids as $id) {
            if ($left($id)) { if (point_owner_forget($pdo, $id, $userId)) $gone++; continue; }
            // A list trash keeps the point — say so instead of „gibt es nicht mehr".
            if (point_in_joint_trash($pdo, $id)) { $held++; continue; }
            $owner = $as($id);
            if ($owner > 0 && point_delete_permanently($pdo, $owner, $id)) $n++;
        }
        $parts = [];
        if ($n)    $parts[] = $n === 1 ? t('Punkt endgültig entfernt.') : t('%d Punkte endgültig entfernt.', $n);
        if ($held) $parts[] = $held === 1
            ? t('Ein Punkt liegt noch im Papierkorb einer gemeinsamen Liste und bleibt — dort holt ihn jedes Mitglied zurück.')
            : t('%d Punkte liegen noch im Papierkorb einer gemeinsamen Liste und bleiben — dort holt sie jedes Mitglied zurück.', $held);
        if ($gone) $parts[] = $gone === 1
            ? t('Bei einem Fund für immer ausgestiegen — die Kopien werden aus deiner Cloud entfernt.')
            : t('Bei %d Funden für immer ausgestiegen — die Kopien werden aus deiner Cloud entfernt.', $gone);
        return $parts !== []
            ? ['success', implode(' ', $parts)]
            : ['error', t('Nichts entfernt — die Punkte gibt es nicht mehr.')];
    }

    return ['error', t('Unbekannte Aktion.')];
}

/** The view. $message comes from trash_process(). */
function trash_render(PDO $pdo, int $userId, ?array $message): void
{
    $points = points_load($pdo, $userId, ['trash' => true]);

    /*
     * List names, including those of deleted lists.
     *
     * lists_load() leaves deleted ones out — here they are needed: a point
     * in the trash may well have sat in a list that no longer exists either.
     * Keeping quiet about that would be exactly the answer that's missing.
     */
    $listNames = [];
    $stmt = $pdo->prepare('SELECT id, name, deleted_at FROM map_lists WHERE user_id = ?');
    $stmt->execute([$userId]);
    foreach ($stmt as $row) {
        $listNames[(int) $row['id']] = $row['deleted_at']
            ? t('%s (gelöscht)', $row['name'])
            : $row['name'];
    }
    ?>

<?php
    // The per-excursion trashes (stage 6b) — the leader's, and for the
    // operator every one. Loaded here so "empty" can speak the truth
    // about both.
    $me = user_current() ?? ['id' => $userId, 'role' => ''];
    $excursionTrash = excursion_trash($pdo, $me);
    $showLeader = is_admin($me);
    // And what I stepped out of as a co-owner (step 4, 11.09.2026).
    require_once __DIR__ . '/point_owners.php';
    $left = point_owner_trash_entries($pdo, $userId);
    // The joint lists' trashes I may look into (step 2 of the joint lists,
    // 22.09.2026) — and which of my own trashed points a list trash holds.
    require_once __DIR__ . '/joint_lists.php';
    $jointTrash = joint_trash_lists($pdo, $userId);
    $held = joint_trash_held($pdo, array_column($points, 'id'));
?>

<?php if ($message !== null) { notice($message[0], $message[1]); } ?>

<?php if ($points === [] && $excursionTrash === [] && $left === [] && $jointTrash === []): ?>
    <p><?= h(t('Der Papierkorb ist leer.')) ?></p>
<?php else: ?>

<?php /* The anchor in the target: without it, submitting lands you on the first
     tab and you go looking for the trash all over again. It never reaches the
     server; the browser merely appends it to the response's address. */ ?>
<form method="post" id="bulk-form" action="<?= h(url('pages/administration.php')) ?>#trash">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="trash">

    <?php if ($points !== []): ?>
    <div class="table-frame">
        <table class="list trash">
            <thead>
            <tr>
                <th style="width:2.2rem">
                    <input type="checkbox" id="all" aria-label="<?= h(t('Alle auswählen')) ?>">
                </th>
                <th><?= h(t('Punkt')) ?></th>
                <th><?= h(t('Listen')) ?></th>
                <th><?= h(t('Gelöscht')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($points as $p): ?>
                <?php
                $lat = number_format($p['lat'], 6, '.', '');
                $lon = number_format($p['lon'], 6, '.', '');
                $names = array_values(array_filter(array_map(
                    static fn(int $id): ?string => $listNames[$id] ?? null,
                    $p['lists']
                )));
                ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= $p['id'] ?>" class="selection" aria-label="<?= h(t('Punkt auswählen')) ?>"></td>

                    <td>
                        <strong><?= h($p['title'] !== '' ? $p['title'] : t('Ohne Titel')) ?></strong>
                        <?php if (($p['note'] ?? '') !== ''): ?>
                            <div class="trash-note"><?= nl2br(h($p['note'])) ?></div>
                        <?php endif; ?>
                        <div class="trash-coord">
                            <code><?= h($lat) ?>, <?= h($lon) ?></code>
                            <?php if ($p['elevation_m'] !== null): ?>
                                · <?= h(t('%d m ü. NHN', (int) $p['elevation_m'])) ?>
                            <?php endif; ?>
                        </div>
                    </td>

                    <td>
                        <?php if ($names === []): ?>
                            <span class="trash-empty"><?= h(t('in keiner Liste')) ?></span>
                        <?php else: ?>
                            <?= h(implode(', ', $names)) ?>
                        <?php endif; ?>
                        <?php if (isset($held[(int) $p['id']])): ?>
                            <?php /* A list trash holds a filing: the point cannot be purged
                                 from here, and the section below is where it comes back. */ ?>
                            <div class="hint-text small"><?= h(t('Liegt auch im Papierkorb von „%s" — jedes Mitglied holt ihn dort zurück; endgültig geht hier nicht.', implode('“, „', $held[(int) $p['id']]))) ?></div>
                        <?php endif; ?>
                    </td>

                    <td class="trash-date"><?= h(date('d.m.Y H:i', strtotime((string) $p['deleted_at']))) ?></td>

                    <td>
                        <div class="trash-buttons">
                            <?php /* Viewing on the map: the point is deleted and
                                 not drawn there, so the link passes the
                                 coordinate along and the map places a
                                 temporary marker. */ ?>
                            <a class="button subtle small"
                               href="<?= h(url()) ?>#zeige=<?= h($lat) ?>,<?= h($lon) ?>"
                               target="_blank" rel="noopener"><?= h(t('Auf der Karte')) ?></a>

                            <button class="button subtle small" type="submit"
                                    name="id" value="<?= $p['id'] ?>"
                                    formnovalidate
                                    onclick="this.form.elements.action.value='restore'"><?= h(t('Zurückholen')) ?></button>

                            <button class="button danger small" type="submit"
                                    name="id" value="<?= $p['id'] ?>"
                                    onclick="this.form.elements.action.value='purge'"
                                    data-purge <?= isset($held[(int) $p['id']]) ? 'disabled title="' . h(t('Liegt im Papierkorb einer gemeinsamen Liste')) . '"' : '' ?>><?= h(t('Endgültig')) ?></button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($left !== []): ?>
        <?php /* WHAT I STEPPED OUT OF (co-ownership, step 4, 11.09.2026): the
             finds I co-owned and left, or held and handed over. The find
             itself is alive with the others — „Zurückholen" makes me a
             co-owner again, „Endgültig" removes my copies of their
             pictures from my cloud and forgets me there. Same form, same
             two buttons; the processing tells the kinds apart. */ ?>
        <div class="column-header"><h3><?= h(t('Ausgestiegen')) ?></h3></div>
        <p class="hint-text"><?= h(t('Diese Funde leben bei den anderen weiter. Zurückholen macht dich wieder zum Mitbesitzer; Endgültig entfernt deine Kopien ihrer Bilder aus deiner Cloud.')) ?></p>
        <div class="table-frame">
            <table class="list trash">
                <thead>
                <tr>
                    <th style="width:2.2rem"></th>
                    <th><?= h(t('Fund')) ?></th>
                    <th><?= h(t('Bei')) ?></th>
                    <th><?= h(t('Ausgestiegen')) ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($left as $p): ?>
                    <?php $lat = number_format((float) $p['lat'], 6, '.', ''); $lon = number_format((float) $p['lon'], 6, '.', ''); ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" class="selection" aria-label="<?= h(t('Punkt auswählen')) ?>"></td>
                        <td>
                            <strong><?= h($p['title'] !== '' ? (string) $p['title'] : t('Ohne Titel')) ?></strong>
                            <?php if ($p['point_gone'] !== null): ?>
                                <div class="trash-note"><?= h(t('Liegt auch beim Halter im Papierkorb.')) ?></div>
                            <?php endif; ?>
                            <div class="trash-coord"><code><?= h($lat) ?>, <?= h($lon) ?></code></div>
                        </td>
                        <td><?= h((string) $p['holder']) ?></td>
                        <td class="trash-date"><?= h(date('d.m.Y H:i', strtotime((string) $p['deleted_at']))) ?></td>
                        <td>
                            <div class="trash-buttons">
                                <a class="button subtle small" href="<?= h(url()) ?>#zeige=<?= h($lat) ?>,<?= h($lon) ?>"
                                   target="_blank" rel="noopener"><?= h(t('Auf der Karte')) ?></a>
                                <button class="button subtle small" type="submit" name="id" value="<?= (int) $p['id'] ?>"
                                        formnovalidate onclick="this.form.elements.action.value='restore'"><?= h(t('Zurückholen')) ?></button>
                                <button class="button danger small" type="submit" name="id" value="<?= (int) $p['id'] ?>"
                                        onclick="this.form.elements.action.value='purge'" data-purge data-forget><?= h(t('Endgültig')) ?></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php foreach ($jointTrash as $block): ?>
        <?php /* THE TRASH OF A JOINT LIST (step 2, 22.09.2026): filings, not
             points — who took the point out of this list and when. Any
             member puts it back; there is no „Endgültig", because nobody
             purges alone what the group can still restore. Its own small
             form per row: the big form above carries ids[] and a shared
             action, and this action needs point AND list. */ ?>
        <div class="column-header"><h3><?= h(t('Gemeinsame Liste „%s“ — Freigabe „%s“ von %s', $block['list']['name'], $block['container'], $block['sharer'])) ?></h3></div>
        <div class="table-frame">
            <table class="list trash">
                <thead>
                <tr>
                    <th><?= h(t('Punkt')) ?></th>
                    <th><?= h(t('Von')) ?></th>
                    <th><?= h(t('Herausgenommen')) ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($block['entries'] as $p): ?>
                    <?php
                    $lat = number_format($p['lat'], 6, '.', '');
                    $lon = number_format($p['lon'], 6, '.', '');
                    ?>
                    <tr>
                        <td>
                            <strong><?= h($p['title'] !== '' ? $p['title'] : t('Ohne Titel')) ?></strong>
                            <div class="trash-coord"><code><?= h($lat) ?>, <?= h($lon) ?></code></div>
                            <?php if ($p['whole']): ?>
                                <div class="hint-text small"><?= h(t('Liegt in keiner Liste mehr — Zurückholen belebt den Punkt wieder.')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= h($p['holder']) ?></td>
                        <?php /* „von %s" after the date, NOT t('%s von %s'): that
                                 sentence means „3 von 5" everywhere else, and one
                                 German key cannot say „by" here and „of" there —
                                 the catalog kept the later one, and English read
                                 „12 MB by 40 MB" (tests/lang.php, 26.09.2026). */ ?>
                        <td class="trash-date"><?= h(date('d.m.Y H:i', strtotime($p['removed_at'])) . ' ' . t('von %s', $p['remover'])) ?></td>
                        <td>
                            <?php /* The page's one form (no nested forms in HTML): the
                                 button names point and list in its value and sets
                                 the action, as the excursion rows do with their id. */ ?>
                            <div class="trash-buttons">
                                <a class="button subtle small"
                                   href="<?= h(url()) ?>#zeige=<?= h($lat) ?>,<?= h($lon) ?>"
                                   target="_blank" rel="noopener"><?= h(t('Auf der Karte')) ?></a>
                                <button class="button small" type="submit"
                                        name="joint" value="<?= (int) $p['point_id'] ?>:<?= (int) $block['list']['id'] ?>"
                                        formnovalidate
                                        onclick="this.form.elements.action.value='joint_restore'"><?= h(t('Zurückholen')) ?></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <?php foreach ($excursionTrash as $block): ?>
        <?php $e = $block['excursion']; ?>
        <?php /* ONE SECTION PER EXCURSION, inside the SAME form: the bulk
             selection reaches across own points and excursion finds, and
             the processing resolves each id for itself. The right rides
             on the excursion reference — restoring puts the find back
             into the guest account, removing for good takes its photos
             along (point_delete_permanently). */ ?>
        <div class="column-header"><h3><?=
            $showLeader && $block['leader_name'] !== null && (int) $e['leader_user_id'] !== (int) $me['id']
                ? h(t('Exkursion „%s“ — Leiter: %s', $e['name'], (string) $block['leader_name']))
                : h(t('Exkursion „%s“', $e['name'])) ?></h3></div>
        <div class="table-frame">
            <table class="list trash">
                <thead>
                <tr>
                    <th style="width:2.2rem"></th>
                    <th><?= h(t('Fund')) ?></th>
                    <th><?= h(t('Von')) ?></th>
                    <th><?= h(t('Gelöscht')) ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($block['points'] as $p): ?>
                    <?php
                    $lat = number_format((float) $p['lat'], 6, '.', '');
                    $lon = number_format((float) $p['lon'], 6, '.', '');
                    ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>"
                                   class="selection" aria-label="<?= h(t('Fund auswählen')) ?>"></td>
                        <td>
                            <strong><?= h($p['title'] !== '' ? $p['title'] : t('Ohne Titel')) ?></strong>
                            <?php if (($p['note'] ?? '') !== ''): ?>
                                <div class="trash-note"><?= nl2br(h((string) $p['note'])) ?></div>
                            <?php endif; ?>
                            <div class="trash-coord"><code><?= h($lat) ?>, <?= h($lon) ?></code></div>
                        </td>
                        <td><?= h((string) ($p['guest_name'] ?? t('Gast'))) ?></td>
                        <td class="trash-date"><?= h(date('d.m.Y H:i', strtotime((string) $p['deleted_at']))) ?></td>
                        <td>
                            <div class="trash-buttons">
                                <a class="button subtle small"
                                   href="<?= h(url()) ?>#zeige=<?= h($lat) ?>,<?= h($lon) ?>"
                                   target="_blank" rel="noopener"><?= h(t('Auf der Karte')) ?></a>
                                <button class="button subtle small" type="submit"
                                        name="id" value="<?= (int) $p['id'] ?>"
                                        formnovalidate
                                        onclick="this.form.elements.action.value='restore'"><?= h(t('Zurückholen')) ?></button>
                                <button class="button danger small" type="submit"
                                        name="id" value="<?= (int) $p['id'] ?>"
                                        onclick="this.form.elements.action.value='purge'"
                                        data-purge><?= h(t('Endgültig')) ?></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <input type="hidden" name="action" value="">

    <div class="trash-collect-bar" id="bulk-bar" hidden>
        <span id="bulk-count"></span>
        <button class="button subtle" type="submit" onclick="this.form.elements.action.value='restore'">
            <?= h(t('Ausgewählte zurückholen')) ?>
        </button>
        <button class="button danger" type="submit" data-purge
                onclick="this.form.elements.action.value='purge'">
            <?= h(t('Ausgewählte endgültig entfernen')) ?>
        </button>
    </div>
</form>

<?php /* The script INSIDE the if: it wires up the bulk form — with an empty
     trash that form doesn't exist, and accessing it would throw an error
     into the console on every visit to the administration. */ ?>
<script type="module">
/* By the bare path, deliberately: administration.js imports './dialogs.js'
   by its bare name, and the import map (asset_import_map) versions both
   the same way — one address, one instance. A hand-written ?v= here would
   still match, but only as long as nobody forgets how it is built; see
   the settings tab, where two addresses once split the settings bundle. */
import { confirm } from '<?= h(url('assets/js/dialogs.js')) ?>';
import { t } from '<?= h(url('assets/js/i18n.js')) ?>';

/* THE HEAD CHECKBOX WAS CALLED 'alle' IN THE MARKUP AND 'all' HERE. The
   script therefore found null, and the very first line that does anything
   with it threw:

     Uncaught TypeError: can't access property "addEventListener", all is null

   A module that throws while being evaluated runs nothing at all — in the
   trash the whole multiple selection was dead because of it: the head box
   switched nothing, the bar reading „n Punkte ausgewählt" never came, and
   with it neither restoring nor permanently deleting several points.
   Reported by the maintainer on 20.08.2026 from the live console.

   The class name of the individual boxes was pulled along on the same
   occasion: it stood German on both sides, so it was sound — but leaving a
   German class beside a just-repaired English id is exactly the half state
   that landed us in this. It appears in no stylesheet, only twice here. */
const form   = document.getElementById('bulk-form');
const all   = document.getElementById('all');
const bar = document.getElementById('bulk-bar');
const countEl = document.getElementById('bulk-count');
const boxes = () => [...form.querySelectorAll('.selection')];

function updateBar() {
    const n = boxes().filter(k => k.checked).length;
    bar.hidden = n === 0;
    countEl.textContent = n === 1 ? t('1 Punkt ausgewählt') : t('%s Punkte ausgewählt', n);
    /* #all lives in the OWN table's head — with only excursion sections
       on the page it does not exist, and exactly the null-crash this
       comment block describes would return through the back door. */
    if (all) {
        all.checked = n > 0 && n === boxes().length;
        all.indeterminate = n > 0 && n < boxes().length;
    }
}

all?.addEventListener('change', () => {
    for (const k of boxes()) k.checked = all.checked;
    updateBar();
});
for (const k of boxes()) k.addEventListener('change', updateBar);

/**
 * Ask before removing for good — and say what is at stake while doing so.
 *
 * This is the only action here that cannot be taken back. That is why the
 * confirmation states the count and, for a single point, its title:
 * "Endgültig entfernen?" alone does not answer what you are about to
 * lose.
 */
form.addEventListener('submit', async (e) => {
    const button = e.submitter;
    if (!button?.hasAttribute('data-purge')) return;

    e.preventDefault();

    const single = button.name === 'id';
    const chosen = single ? 1 : boxes().filter(k => k.checked).length;
    if (chosen === 0) return;

    const title = single
        ? button.closest('tr').querySelector('strong').textContent.trim()
        : null;

    /* One whole sentence per case instead of a glued subject — a
       translation cannot reorder fragments (i18n house rule). */
    /* A FORMER CO-OWNER'S ROW says what really happens (11.09.2026): the
       find is not deleted — it lives on with the others — only I am
       forgotten there, and my copies of their pictures leave my cloud.
       The generic sentence would promise a deletion that does not take
       place. */
    const forget = single && button.hasAttribute('data-forget');
    const ok = await confirm({
        title: forget ? t('Für immer aussteigen?') : t('Endgültig entfernen?'),
        text: forget
            ? t('Du wirst bei „%s" vergessen, und deine Kopien der Bilder der anderen verschwinden aus deiner Cloud. Der Fund selbst bleibt bei den anderen; deine eigenen Bilder bleiben daran.', title)
            : (single
                ? t('„%s" wird unwiderruflich gelöscht. Das lässt sich nicht rückgängig machen.', title)
                : t('%s Punkte werden unwiderruflich gelöscht. Das lässt sich nicht rückgängig machen.', chosen)),
        button: forget ? t('Für immer aussteigen') : t('Endgültig entfernen'),
        danger: true,
    });
    if (!ok) return;

    // In the single-item action, the triggering button carries the id; a
    // programmatic submit() does not send it along.
    if (single) {
        const field = document.createElement('input');
        field.type = 'hidden';
        field.name = 'id';
        field.value = button.value;
        form.append(field);
    }
    form.elements.action.value = 'purge';
    form.submit();
});

updateBar();
</script>

<?php endif; ?>

    <?php
}
