<?php
/**
 * Containers — processing and view.
 *
 * The model lives in migration 0042. Here we administer, not display, what
 * is inside — the containers' points belong on the map, not in an
 * administration table.
 *
 * THE TAB WAS CALLED "GETEILTE LISTEN" and was a shapeless slat of tables
 * and select fields per container — with no recognizable boundary where one
 * ends and the next begins. Since 13.08.2026 it was called "Container",
 * since 25.08.2026 „Freigaben" on screen — the code keeps 'container'
 * throughout, because 'share' already names a different thing here (see
 * the note at the tab button in administration.php). (The
 * anchor 'teilen' stays, as always) and carries the visual language of
 * friends and workgroups, but SINGLE-COLUMN — the maintainer decided that
 * explicitly: each container is a block with a header surface, below it
 * members and hung-in lists as named sections, each with its own plus.
 * Creating, admitting and hanging in happen through windows instead of
 * open forms.
 *
 * TWO SUB-TABS since 14.08.2026, "Eigene" and "Von anderen": in your own
 * container you decide who is in, in a foreign one only what you yourself
 * hang in — those are two different jobs, and in a shared slat the
 * difference stood only in small print at the block header. The block
 * itself is drawn by share_block() for both.
 */

declare(strict_types=1);

require_once __DIR__ . '/container.php';
require_once __DIR__ . '/shapes.php';

/**
 * Must run BEFORE any output. 'section' tells the administration's forms
 * apart from one another — see invitations_content.php.
 *
 * @return array{message: ?string, error: string[]}
 */
function share_process(PDO $pdo, int $userId): array
{
    $result = ['message' => null, 'error' => []];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'share') {
        return $result;
    }
    csrf_check();

    if (!container_ready($pdo)) {
        $result['error'][] = t('Die Migration 0042 ist noch nicht eingespielt.');
        return $result;
    }

    try {
        switch ((string) ($_POST['action'] ?? '')) {
            case 'create':
                container_create($pdo, $userId, (string) ($_POST['name'] ?? ''),
                    share_filter_read($pdo, $userId));
                break;
            case 'delete':
                container_delete($pdo, $userId, (int) ($_POST['container'] ?? 0));
                break;
            case 'add':
                // One window for both — "f:12" is a friend, "g:3" a group.
                // Two lists side by side would be the question "which one
                // was where again?".
                $who = (string) ($_POST['who'] ?? '');
                if (!preg_match('/^([fg]):(\d+)$/', $who, $t)) {
                    throw new InvalidArgumentException(t('Bitte Freund oder Gruppe auswählen.'));
                }
                container_add($pdo, $userId, (int) ($_POST['container'] ?? 0),
                    $t[1] === 'f' ? 'friend' : 'group', (int) $t[2]);
                break;
            case 'remove':
                container_remove($pdo, $userId, (int) ($_POST['container'] ?? 0),
                    (string) ($_POST['type'] ?? ''), (int) ($_POST['target'] ?? 0));
                break;
            /* LEAVING IS ITS OWN DEED, not a remove aimed at oneself. It
               works for every kind of access — see container_leave() — and
               it is the only one of the two a non-owner may perform. */
            case 'leave':
                container_leave($pdo, $userId, (int) ($_POST['container'] ?? 0));
                $result['message'] = t('Freigabe verlassen.');
                break;
            case 'share_add':
                // Several at once, and EACH attempted on its own: an
                // overlap (say, parent and sublist checked at the same
                // time) must not drag the rest down with it. The error
                // names the list — the message from share_create alone
                // did not say who it is about.
                $lists = array_filter(array_map('intval',
                    explode(',', (string) ($_POST['lists'] ?? ''))));
                if ($lists === []) {
                    throw new InvalidArgumentException(t('Keine Liste gewählt.'));
                }
                foreach ($lists as $listId) {
                    try {
                        share_create($pdo, $userId, (int) ($_POST['container'] ?? 0), $listId);
                    } catch (InvalidArgumentException $e) {
                        $stmt = $pdo->prepare('SELECT name FROM map_lists WHERE id = ? AND user_id = ?');
                        $stmt->execute([$listId, $userId]);
                        $name = $stmt->fetchColumn();
                        // One key with both pieces instead of a glued-on
                        // prefix — a translation may need the name elsewhere.
                        $result['error'][] = $name !== false
                            ? t('„%s“: %s', $name, $e->getMessage())
                            : $e->getMessage();
                    }
                }
                break;
            case 'share_remove':
                share_remove($pdo, $userId, (int) ($_POST['share'] ?? 0));
                break;
            case 'additions_seen':
                additions_seen($pdo, $userId, (int) ($_POST['container'] ?? 0));
                break;
            case 'rights':
                // Unchecked boxes never arrive in the POST at all — so an
                // empty set means: everything revoked, read only.
                // The joint switch travels in the same form (migration 0110).
                if (share_joint_ready($pdo)) {
                    share_joint_set($pdo, $userId, (int) ($_POST['share'] ?? 0), !empty($_POST['joint']));
                }
                share_rights_set($pdo, $userId, (int) ($_POST['share'] ?? 0),
                    array_map('strval', (array) ($_POST['right'] ?? [])));
                $result['message'] = t('Rechte gespeichert.');
                break;
            case 'original_restore':
                original_restore($pdo, $userId,
                    (int) ($_POST['point'] ?? 0), (string) ($_POST['field'] ?? ''));
                $result['message'] = t('Original wiederhergestellt — auf allen Geräten beim nächsten Abgleich.');
                break;
            case 'original_keep':
                original_forget($pdo, $userId,
                    (int) ($_POST['point'] ?? 0), (string) ($_POST['field'] ?? ''));
                $result['message'] = t('Übernommen — der jetzige Stand ist das neue Original.');
                break;
        }
    } catch (InvalidArgumentException $e) {
        $result['error'][] = $e->getMessage();
    }

    return $result;
}

/**
 * Read the birth filter from the creation form and normalize it — or null
 * if all fields stayed empty. Throws on the unusable, instead of quietly
 * saving a different filter than the one meant: the filter cannot be
 * corrected later.
 */
function share_filter_read(PDO $pdo, int $userId): ?array
{
    if (!filter_ready($pdo)) {
        return null;
    }
    $filter = [];

    $day = static function (string $raw): ?string {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $d = DateTime::createFromFormat('Y-m-d', $raw);
        if ($d === false || $d->format('Y-m-d') !== $raw) {
            throw new InvalidArgumentException(t('Der Zeitraum braucht Datumsangaben.'));
        }
        return $raw;
    };
    $from = $day((string) ($_POST['filter_from'] ?? ''));
    $to = $day((string) ($_POST['filter_to'] ?? ''));
    if ($from !== null && $to !== null && $from > $to) {
        throw new InvalidArgumentException(t('Der Zeitraum endet, bevor er beginnt.'));
    }
    if ($from !== null || $to !== null) {
        $filter['time'] = ['from' => $from, 'until' => $to];
    }

    // The area comes as a self-drawn polygon from the small map in the
    // creation window (shared.js), no longer from the offline areas —
    // the maintainer wanted a fresh outline of its own per container. Without a
    // name: there is nothing it would refer to.
    $cornersRaw = trim((string) ($_POST['filter_corners'] ?? ''));
    if ($cornersRaw !== '') {
        $corners = json_decode($cornersRaw, true);
        if (!is_array($corners) || count($corners) < 3 || count($corners) > 500) {
            throw new InvalidArgumentException(t('Das Gebiet braucht mindestens drei Ecken.'));
        }
        $clean = [];
        foreach ($corners as $e) {
            if (!is_array($e) || !isset($e[0], $e[1]) || !is_numeric($e[0]) || !is_numeric($e[1])
                || abs((float) $e[0]) > 90 || abs((float) $e[1]) > 180) {
                throw new InvalidArgumentException(t('Das Gebiet ist nicht lesbar.'));
            }
            // Six decimal places like the point columns — around eleven centimeters.
            $clean[] = [round((float) $e[0], 6), round((float) $e[1], 6)];
        }
        $filter['area'] = ['corners' => $clean];
    }

    $kinds  = (array) ($_POST['term_type'] ?? []);
    $texts  = (array) ($_POST['term_text'] ?? []);
    foreach ($texts as $i => $text) {
        $text = trim((string) $text);
        if ($text === '') {
            continue; // Empty rule rows do not count — that is how you get rid of them.
        }
        $kind = (string) ($kinds[$i] ?? '');
        if (!in_array($kind, ['contains', 'contains_not'], true)) {
            throw new InvalidArgumentException(t('Unbekannte Regelart.'));
        }
        $filter['terms'][] = ['type' => $kind, 'text' => mb_substr($text, 0, 100)];
    }

    return $filter === [] ? null : $filter;
}

/**
 * One's own lists as choices for the hang-in window, in tree order.
 *
 * The depth travels along as a number instead of a "— " prefix in the name:
 * since the entries carry their list's symbol (the call,
 * 14.08.2026), the indent moves in as padding BEFORE the symbol — a prefix
 * would stand between symbol and name and tear the two apart.
 *
 * Container lists are absent: they are vehicles of a particular container
 * (migration 0051), not lists one keeps — hanging container A's vehicle
 * into container B would yield a share bearing the wrong container's name.
 */
function share_list_selection(PDO $pdo, int $userId): array
{
    $lists = lists_load($pdo, $userId);
    $children = [];
    foreach ($lists as $l) {
        if ($l['is_container_list'] ?? false) {
            continue;
        }
        $children[$l['parent_id'] ?? 0][] = $l;
    }

    $out  = [];
    $depth = function (int $parent, int $t) use (&$depth, &$out, $children): void {
        foreach ($children[$parent] ?? [] as $l) {
            // Auto lists are EXPLICITLY up for choice — hanging them in is
            // half their purpose: a filter whose result the members follow
            // along. The flag travels with, so the window announces that
            // such a share grows by itself.
            $out[] = ['id' => $l['id'], 'name' => $l['name'], 'depth' => $t,
                       'color' => $l['color'], 'symbol' => $l['symbol'],
                       'auto' => (bool) ($l['is_autolist'] ?? false)];
            $depth($l['id'], $t + 1);
        }
    };
    $depth(0, 0);
    return $out;
}

/** What the others may do, as a sentence fragment for the share row. */
function share_rights_readable(array $rights): string
{
    if ($rights === []) {
        return t('die anderen: nur ansehen');
    }
    // The KEYS are the stored right values and stay as they are — only
    // their labels are display and go through t().
    $names = [
        'move'     => t('verschieben'),
        'delete'        => t('löschen'),
        'title'           => t('Titel'),
        'note'           => t('Notizen'),
        'delete_photos' => t('Bilder löschen'),
    ];
    $parts  = [];
    $fieldCount = 0;
    foreach ($rights as $r) {
        if (isset($names[$r])) {
            $parts[] = $names[$r];
        } elseif (str_starts_with($r, 'field:')) {
            $fieldCount++;
        }
    }
    if ($fieldCount > 0) {
        $parts[] = $fieldCount === 1 ? t('1 Feld') : t('%d Felder', $fieldCount);
    }
    return t('die anderen: %s', implode(', ', $parts));
}

/**
 * The switches of one's own share, folded behind a <details> — open, they
 * would stand there as a wall of boxes at every share.
 *
 * THE THREE QUICK PICKS only set boxes (administration.js); what is saved is
 * always the visible state. That way there is no "level" that secretly
 * means more or less than what is checked.
 */
function share_rights_form(array $f, string $target, int $points = 0): void
{
    $rights = $f['rights'];
    // Keys are the stored right values (SHARE_RIGHTS) — labels only.
    $fixed   = [
        'title'           => t('Titel bearbeiten'),
        'note'           => t('Notizen bearbeiten'),
        'move'     => t('Punkte verschieben'),
        'delete'        => t('Punkte löschen — in DEINEN Papierkorb, nur du holst sie zurück'),
        'delete_photos' => t('Belegbilder löschen'),
    ];
    ?>
    <details class="share-permissions">
        <summary><?= icon('fold-triangle', ['class' => 'collapse-triangle fg-pfeil']) ?><?= h(t('Rechte der anderen')) ?></summary>
        <form method="post" action="<?= h($target) ?>" class="share-permissions-form">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="share">
            <input type="hidden" name="action" value="rights">
            <input type="hidden" name="share" value="<?= (int) $f['id'] ?>">

            <?php if (array_key_exists('joint', $f)): ?>
                <?php /* GEMEINSAM BEARBEITEN (21./22.09.2026): the members
                     fill and tend the list together — every right below at
                     once, and setting points into it. Migration 0110.

                     IT STANDS APART AND FIRST, WITH THE WARNING IN RED
                     (22.09.2026, after step 3): among boxes that
                     each grant one right it is the one that changes whom the
                     list belongs to — „meine Hauptpilzliste mit 1.800
                     Punkten" ticked by mistake would be the group's from
                     then on. So it says what happens before the box, and
                     administration.js asks once more when the box is ticked.
                     The point count travels along for that question. */ ?>
                <div class="share-joint">
                    <label class="share-permission">
                        <input type="checkbox" name="joint" value="1" data-points="<?= (int) $points ?>" <?= $f['joint'] ? 'checked' : '' ?>>
                        <?= h(t('Gemeinsam bearbeiten — jedes Mitglied hat alle Rechte und setzt selbst Punkte in die Liste; die Rechte unten gelten dann von selbst.')) ?>
                    </label>
                    <p class="share-joint-warning"><?= icon('warning') ?><span><?= h(t('Das ist ein großer Schritt: Die Liste gehört dann allen Mitgliedern — jeder bearbeitet, verschiebt und löscht jeden Punkt darin. Löschst du die Liste oder verlässt du die Freigabe, geht sie mit allen Punkten an ein anderes Mitglied über und ist nicht mehr deine. Zurück auf privat führt nur dieser Schalter, und was die anderen bis dahin geändert haben, bleibt.')) ?></span></p>
                </div>
                <hr class="section-rule">
            <?php endif; ?>

            <div class="button-row share-quick-selection">
                <?php /* type=button: the three only set boxes, saving happens
                     below. data-* instead of a script here, administration.js
                     wires it up. */ ?>
                <button type="button" class="button subtle small" data-quick-select="none"><?= h(t('Nur ansehen')) ?></button>
                <button type="button" class="button subtle small" data-quick-select="content"><?= h(t('Mitarbeiten')) ?></button>
                <button type="button" class="button subtle small" data-quick-select="all"><?= h(t('Alles')) ?></button>
            </div>

            <?php foreach ($fixed as $key => $label): ?>
                <label class="share-permission">
                    <input type="checkbox" name="right[]" value="<?= h($key) ?>"
                           data-group="<?= in_array($key, ['title', 'note'], true) ? 'content' : 'intervention' ?>"
                           <?= in_array($key, $rights, true) ? 'checked' : '' ?>>
                    <?= h($label) ?>
                </label>
            <?php endforeach; ?>

            <?php foreach ($f['fields'] as $field): ?>
                <label class="share-permission">
                    <input type="checkbox" name="right[]" value="field:<?= h($field['name']) ?>"
                           data-group="content"
                           <?= in_array('field:' . $field['name'], $rights, true) ? 'checked' : '' ?>>
                    <?= h(t('Feld „%s“ ausfüllen', $field['name'])) ?>
                </label>
            <?php endforeach; ?>

            <div class="button-row">
                <button class="button small subtle" type="submit" data-quiet-until-changed><?= h(t('Rechte speichern')) ?></button>
            </div>
        </form>
    </details>
    <?php
}

/** An anchor field with its readable name. */
function share_field_name(string $field): string
{
    return match (true) {
        $field === 'title'              => t('Titel'),
        $field === 'note'              => t('Notiz'),
        $field === 'position'                => t('Position'),
        $field === 'outline'             => t('Fläche'),
        str_starts_with($field, 'extra.') => substr($field, 7),
        default                        => $field,
    };
}

/** An anchor value as text — places as coordinates, areas counted, the empty named. */
function share_value_readable(mixed $value): string
{
    if (is_array($value) && isset($value['lat'], $value['lon'])) {
        return number_format((float) $value['lat'], 6, '.', '') . ', '
             . number_format((float) $value['lon'], 6, '.', '');
    }
    // The outline anchor: a list of corners. The coordinates themselves
    // would tell nobody anything here — the number of corners tells the
    // states apart, and what the area looks like, the map shows.
    if (is_array($value) && array_is_list($value)) {
        return $value === [] ? t('(keine Fläche)') : t('Fläche mit %d Ecken', count($value));
    }
    $text = trim((string) ($value ?? ''));
    return $text === '' ? t('(leer)') : $text;
}

/**
 * The deviations from the original — the lifeline (migration 0044).
 *
 * One row per field with both states side by side: restoring overwrites the
 * foreign change (hence with confirmation), "So lassen" accepts it and
 * makes the current state the new baseline.
 */
function share_deviations_render(array $deviations, string $target): void
{
    if ($deviations === []) {
        return;
    }
    ?>
    <h4><?= h(t('Abweichungen vom Original')) ?></h4>
    <p class="hint-text">
        <?= h(t('Diese Felder hat jemand anderes verändert. Dein zuletzt selbst eingegebener Stand bleibt als Anker liegen, bis du dich entscheidest — zurückholen oder übernehmen.')) ?>
    </p>
    <div class="table-frame">
        <table class="list">
            <tbody>
            <?php foreach ($deviations as $a): ?>
                <tr>
                    <td>
                        <strong><?= h($a['point_title'] === '' ? t('Ohne Titel') : $a['point_title']) ?></strong>
                        <span class="item-data">· <?= h(share_field_name($a['field'])) ?>
                            · <?= h(t('geändert von %s', $a['by'])) ?></span>
                        <div class="deviation-values">
                            <span class="deviation-original"><?= h(share_value_readable($a['original'])) ?></span>
                            <span class="deviation-arrow">→</span>
                            <span><?= h(share_value_readable($a['now'])) ?></span>
                        </div>
                    </td>
                    <td class="button-cell">
                        <form method="post" action="<?= h($target) ?>"
                              data-confirm="<?= h(t('Die Änderung von %s wird durch dein Original ersetzt — auch auf deren Geräten.', $a['by'])) ?>"
                              data-confirm-title="<?= h(t('Original wiederherstellen?')) ?>"
                              data-confirm-button="<?= h(t('Wiederherstellen')) ?>"
                              data-confirm-danger>
                            <?= csrf_field() ?>
                            <input type="hidden" name="section" value="share">
                            <input type="hidden" name="action" value="original_restore">
                            <input type="hidden" name="point" value="<?= (int) $a['point_id'] ?>">
                            <input type="hidden" name="field" value="<?= h($a['field']) ?>">
                            <button class="button subtle small" type="submit"><?= h(t('Wiederherstellen')) ?></button>
                        </form>
                        <form method="post" action="<?= h($target) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="section" value="share">
                            <input type="hidden" name="action" value="original_keep">
                            <input type="hidden" name="point" value="<?= (int) $a['point_id'] ?>">
                            <input type="hidden" name="field" value="<?= h($a['field']) ?>">
                            <button class="button subtle small" type="submit"><?= h(t('So lassen')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * ONE container as a block — header, members, hung-in lists.
 *
 * A function of its own since the containers stand separated into "Eigene"
 * and "Von anderen": the same block is needed in both tabs, and two copies
 * of two hundred lines of markup would drift apart at the first rework that
 * only one of the two gets.
 *
 * $details, $additions and $target are passed through by share_render() —
 * they live there because they are loaded for ALL containers at once.
 */
function share_block(PDO $pdo, int $userId, array $c, array $details, array $additions, string $target): void
{
    $isOwner = (int) $c['owner_user_id'] === $userId;
    $entries   = $details[$c['id']]['entries'];
    $shares   = $details[$c['id']]['shares'];
    $filter      = $details[$c['id']]['filter'];
    /* EVERY MEMBER MAY LEAVE since migration 0067 — it used to be only
       those admitted as a friend, because there was nothing to delete for
       the others. Now the departure is a record of its own, so the group
       member gets the same door (container_leave). */
    $mayLeave = !$isOwner;
    /* HOW MANY OF MY OWN LISTS HANG IN HERE — the question the departure
       has to answer before it happens. $shares carries them all; mine are
       the ones the exit unhooks. */
    $myShares = count(array_filter($shares, fn($sh) => (int) $sh['user_id'] === $userId));

    $memberCount = 1 + count($entries);
    $memberText   = $memberCount === 1 ? t('1 Mitglied') : t('%d Mitglieder', $memberCount);
    $listCount     = count($shares);
    $listText       = $listCount === 1 ? t('1 Liste') : t('%d Listen', $listCount);
    ?>
    <div class="ct-block is-collapsed" data-container="<?= (int) $c['id'] ?>">
        <div class="column-header">
            <h3>
                <?= icon('fold-triangle', ['class' => 'collapse-triangle ct-arrow']) ?>
                <?= h($c['name']) ?>
                <?php if (!$isOwner): ?>
                    <span class="item-data"><?= h(t('von %s', $c['owner_name'])) ?></span>
                <?php endif; ?>
                <span class="item-data ct-summary">· <?= $memberText ?> · <?= $listText ?></span>
            </h3>
            <?php if ($isOwner): ?>
                <form method="post" action="<?= h($target) ?>"
                      data-confirm="<?= h(t('Die Freigabe %s verschwindet für alle Mitglieder. Die eingehängten Listen und ihre Punkte bleiben bei ihren Besitzern unverändert.', $c['name'])) ?>"
                      data-confirm-title="<?= h(t('Freigabe löschen?')) ?>"
                      data-confirm-button="<?= h(t('Löschen')) ?>"
                      data-confirm-danger>
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="share">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="container" value="<?= (int) $c['id'] ?>">
                    <button class="symbol-button delete" type="submit" title="<?= h(t('Freigabe löschen')) ?>"
                            aria-label="<?= h(t('Freigabe %s löschen', $c['name'])) ?>"><?= icon('delete') ?></button>
                </form>
            <?php elseif ($mayLeave): ?>
                <?php
                /* THE SAME SENTENCE AS ON THE MAP, and it names all three
                   things: which container, what it costs, and how one
                   comes back (the maintainer asked what the question actually
                   says, 22.08.2026 — it said none of the three). The list
                   count is spelled out rather than left as "deine
                   Listen": whoever has hung in seven wants to read seven
                   before pressing, not afterwards.
                   BUILT RAW AND GUARDED ONCE AT THE END: escaping the
                   pieces and then putting them into an attribute would
                   guard them twice over, and a name with an ampersand
                   would arrive as &amp;amp; in the question. h() belongs
                   where the text meets the markup, nowhere earlier. */
                /* THREE WHOLE SENTENCES INSTEAD OF GLUED PIECES: a
                   translation cannot reorder fragments, so each plural
                   case carries the full text (i18n house rule). */
                $leaveText = match (true) {
                    $myShares === 0 => t('Die Freigabe „%s" verschwindet aus deiner Übersicht. Zurück kommst du nur, wenn %s dich wieder aufnimmt.', $c['name'], $c['owner_name']),
                    $myShares === 1 => t('Die Freigabe „%s" verschwindet aus deiner Übersicht, und deine eingehängte Liste wird ausgehängt. Zurück kommst du nur, wenn %s dich wieder aufnimmt.', $c['name'], $c['owner_name']),
                    default => t('Die Freigabe „%s" verschwindet aus deiner Übersicht, und deine %d eingehängten Listen werden ausgehängt. Zurück kommst du nur, wenn %s dich wieder aufnimmt.', $c['name'], $myShares, $c['owner_name']),
                };
                ?>
                <form method="post" action="<?= h($target) ?>"
                      data-confirm="<?= h($leaveText) ?>"
                      data-confirm-title="<?= h(t('Freigabe verlassen?')) ?>"
                      data-confirm-button="<?= h(t('Verlassen')) ?>"
                      data-confirm-danger>
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="share">
                    <input type="hidden" name="action" value="leave">
                    <input type="hidden" name="container" value="<?= (int) $c['id'] ?>">
                    <?php /* The door, not the bucket: the container carries on
                         without you, and the lists you hung in stay with
                         their owners. Same distinction as with the groups
                         (22.08.2026). */ ?>
                    <button class="symbol-button delete" type="submit" title="<?= h(t('Freigabe verlassen')) ?>"
                            aria-label="<?= h(t('Freigabe %s verlassen', $c['name'])) ?>"><?= icon('sign-out') ?></button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($filter !== null): ?>
            <?php /* The birth filter, read-only: it cannot be changed after
                 creation anymore (container_create explains why), so there
                 is nothing to operate here either. */ ?>
            <p class="hint-text ct-filter">
                <?= h(t('Geteilt wird nur, was passt: %s', container_filter_readable($filter))) ?>
            </p>
        <?php endif; ?>

        <?php if (isset($additions[$c['id']])): ?>
            <?php /* The room has grown since this user last looked — and they
                 have lists hung in here. The notice stays put until they
                 confirm it explicitly: a box that vanished on mere
                 rendering would never be reliably read. */ ?>
            <div class="notice notice-hint additions-hint">
                <p>
                    <strong><?= h(t('Neu dabei:')) ?></strong>
                    <?= h(implode(', ', $additions[$c['id']]['added'])) ?>.
                    <?= h(t('Sie sehen jetzt auch, was du hier eingehängt hast — prüfe, ob deine Listen für diesen Kreis noch die richtigen sind.')) ?>
                </p>
                <form method="post" action="<?= h($target) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="share">
                    <input type="hidden" name="action" value="additions_seen">
                    <input type="hidden" name="container" value="<?= (int) $c['id'] ?>">
                    <button class="button small" type="submit"><?= h(t('Verstanden')) ?></button>
                </form>
            </div>
        <?php endif; ?>

        <div class="person-section">
            <span><?= icon('people', ['class' => 'ct-abschnitt-icon']) ?><?= h(t('Mitglieder')) ?></span>
            <?php if ($isOwner): ?>
                <button type="button" class="symbol-button ct-aufnehmen"
                        data-container="<?= (int) $c['id'] ?>" data-name="<?= h($c['name']) ?>"
                        title="<?= h(t('Mitglied aufnehmen')) ?>"
                        aria-label="<?= h(t('Mitglied in %s aufnehmen', $c['name'])) ?>"><?= icon('plus') ?></button>
            <?php endif; ?>
        </div>
        <ul class="person-list">
            <?php /* The owner carries "Besitzer" on the same line as the name
                 (gl-inline) and WITHOUT a divider to the actual members
                 below — they are not a row "like the others", and the line
                 should not claim so. */ ?>
            <li class="person-row ct-owner">
                <span class="person-name">
                    <?= icon('person', ['class' => 'ct-row-icon']) ?>
                    <?= h($c['owner_name']) ?>
                    <span class="item-data item-inline">· <?= h(t('Besitzer')) ?></span>
                </span>
            </li>
            <?php foreach ($entries as $e): ?>
                <li class="person-row">
                    <span class="person-name">
                        <?= icon($e['type'] === 'group' ? 'group' : 'person', ['class' => 'ct-row-icon']) ?>
                        <?= h($e['name']) ?>
                        <?php if ($e['type'] === 'group'): ?>
                            <span class="item-data"><?= h(t('Arbeitsgruppe')) ?></span>
                        <?php endif; ?>
                    </span>
                    <?php // One's own entry already has its leave bin up in
                          // the header — the same action twice confuses. ?>
                    <?php if ($isOwner): ?>
                        <span class="person-buttons">
                            <form method="post" action="<?= h($target) ?>"
                                  data-confirm="<?= h($e['type'] === 'group'
                                      ? t('Alle, die nur über diese Gruppe Mitglied waren, verlieren die Freigabe — und ihre eingehängten Listen werden ausgehängt.')
                                      : t('Die eingehängten Listen dieses Mitglieds werden ausgehängt.')) ?>"
                                  data-confirm-title="<?= h(t('Herausnehmen?')) ?>"
                                  data-confirm-button="<?= h(t('Herausnehmen')) ?>"
                                  data-confirm-danger>
                                <?= csrf_field() ?>
                                <input type="hidden" name="section" value="share">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="container" value="<?= (int) $c['id'] ?>">
                                <input type="hidden" name="type" value="<?= h($e['type']) ?>">
                                <input type="hidden" name="target" value="<?= (int) $e['target_id'] ?>">
                                <button class="symbol-button delete" type="submit" title="<?= h(t('Herausnehmen')) ?>"
                                        aria-label="<?= h(t('%s aus %s herausnehmen', $e['name'], $c['name'])) ?>"><?= icon('delete') ?></button>
                            </form>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="person-section">
            <span><?= icon('list', ['class' => 'ct-abschnitt-icon']) ?><?= h(t('Eingehängte Listen')) ?></span>
            <button type="button" class="symbol-button ct-einhaengen"
                    data-container="<?= (int) $c['id'] ?>" data-name="<?= h($c['name']) ?>"
                    title="<?= h(t('Liste einhängen')) ?>"
                    aria-label="<?= h(t('Liste in %s einhängen', $c['name'])) ?>"><?= icon('plus') ?></button>
        </div>
        <?php if ($shares === []): ?>
            <p class="hint-text"><?= h(t('Noch keine. Über das Plus hängst du eigene Listen ein.')) ?></p>
        <?php else: ?>
            <ul class="person-list">
                <?php foreach ($shares as $f): ?>
                    <?php
                    // The shared branch's name stands in its first row; if
                    // the list has since been deleted, the share stays
                    // visible empty until someone takes it out.
                    // With a birth filter, the row counts what the others
                    // SEE — not what the list contains at home.
                    $root = $f['lists'][0]['name'] ?? t('(Liste gelöscht)');
                    // THE LIST'S SYMBOL, NOT ONE FOR "LIST". Here stood the
                    // same line drawing three times, no matter whether a
                    // mushroom or a stag was hung in — and that is exactly
                    // how you recognize your list on the map again. Color
                    // and shape come from the ROOT of the branch: only it
                    // carries them, the sublists take them over (migration
                    // 0040).
                    $rootColor  = $f['lists'][0]['color']  ?? null;
                    $rootSymbol = $f['lists'][0]['symbol'] ?? null;
                    $points = $filter === null
                        ? array_sum(array_column($f['lists'], 'total'))
                        : container_filtered_count($pdo, array_column($f['lists'], 'id'), $filter);
                    ?>
                    <li class="person-row">
                        <span class="person-name">
                            <?= shape_point($rootSymbol, $rootColor) ?>
                            <?= h($root) ?>
                            <span class="item-data"><?= h(t('von %s', $f['display_name'])) ?> ·
                                <?= h($points === 1 ? t('1 Punkt') : t('%d Punkte', $points)) ?> ·
                                <?= h(!empty($f['joint']) ? t('gemeinsam: alle bearbeiten alles, jeder setzt Punkte') : share_rights_readable($f['rights'])) ?></span>
                            <?php if ($f['user_id'] === $userId && share_rights_ready($pdo)): ?>
                                <?php share_rights_form($f, $target, $points); ?>
                            <?php endif; ?>
                        </span>
                        <?php if ($f['user_id'] === $userId || $isOwner): ?>
                            <span class="person-buttons">
                                <form method="post" action="<?= h($target) ?>"
                                      data-confirm="<?= h(!empty($f['joint'])
                                          ? t('Die Liste verschwindet aus der Freigabe. Sie ist gemeinsam: die Punkte der anderen liegen danach bei ihnen in einer eigenen Liste „… (ehemals gemeinsam)", und der Chat meldet es.')
                                          : t('Die Liste verschwindet aus der Freigabe — an ihr selbst und ihren Punkten ändert sich nichts.')) ?>"
                                      data-confirm-title="<?= h(t('Liste aushängen?')) ?>"
                                      data-confirm-button="<?= h(t('Aushängen')) ?>"
                                      data-confirm-danger>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="section" value="share">
                                    <input type="hidden" name="action" value="share_remove">
                                    <input type="hidden" name="share" value="<?= (int) $f['id'] ?>">
                                    <button class="symbol-button delete" type="submit" title="<?= h(t('Aushängen')) ?>"
                                            aria-label="<?= h(t('%s aushängen', $root)) ?>"><?= icon('delete') ?></button>
                                </form>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
}

/** The view. $result comes from share_process(), $additions from
 *  additions_per_container() — computed by administration.php AFTER the
 *  processing, the same order as with the deviations. */
function share_render(PDO $pdo, int $userId, array $result, array $deviations = [], array $additions = []): void
{
    if (!container_ready($pdo)) {
        notice('hint', t('Freigaben brauchen die Migration 0042 — sie ist noch nicht eingespielt.'));
        return;
    }

    $container  = container_mine($pdo, $userId);
    $target       = url('pages/administration.php') . '#share';
    $listSelection = share_list_selection($pdo, $userId);
    $friends    = friends_ready($pdo) ? friends_overview($pdo, $userId)['friends'] : [];
    $groups    = friends_ready($pdo) ? groups_overview($pdo, $userId)['own'] : [];

    notice('error', $result['error'], true);
    if ($result['message'] !== null) {
        notice('success', $result['message'], true);
    }

    // At the very top, before everything else: a decision waits here, the
    // rest of the page is administration.
    share_deviations_render($deviations, $target);

    // What the windows need to know, per container: who is already in and
    // which of my lists already hang — shared.js filters the offerings
    // with that. Along the way the loop collects everything the blocks
    // below need, so nothing is loaded twice.
    $details = [];
    $forWindow = ['friends' => [], 'groups' => [], 'lists' => [], 'container' => (object) []];
    foreach ($friends as $f) {
        $forWindow['friends'][] = ['id' => (int) $f['user_id'], 'name' => $f['display_name']];
    }
    foreach ($groups as $g) {
        $forWindow['groups'][] = ['id' => (int) $g['id'], 'name' => $g['name']];
    }
    foreach ($listSelection as $l) {
        $forWindow['lists'][] = ['id' => (int) $l['id'], 'name' => $l['name'],
                                     'depth' => (int) $l['depth'],
                                     'color' => $l['color'], 'symbol' => $l['symbol'],
                                     // For an auto list, the hang-in window
                                     // announces that the share grows with
                                     // its filter — sharing without notice
                                     // does not exist here.
                                     'auto' => (bool) ($l['auto'] ?? false)];
    }
    foreach ($container as $c) {
        $entries = container_entries($pdo, $c['id']);
        $shares = container_shares($pdo, $c['id']);
        $details[$c['id']] = [
            'entries' => $entries,
            'shares' => $shares,
            'filter'    => container_filter($pdo, $c['id']),
        ];
        $forWindow['container']->{$c['id']} = [
            'name'  => $c['name'],
            'insideF' => array_values(array_map('intval', array_column(
                array_filter($entries, fn($e) => $e['type'] === 'friend'), 'target_id'))),
            'insideG' => array_values(array_map('intval', array_column(
                array_filter($entries, fn($e) => $e['type'] === 'group'), 'target_id'))),
            'myLists'     => array_values(array_map('intval', array_column(
                array_filter($shares, fn($f) => (int) $f['user_id'] === $userId), 'list_id'))),
        ];
    }

    // OWN AND FOREIGN SEPARATED. In one slat they stood jumbled, and the
    // difference stood in small print at each block's header ("von Tobias
    // Lenz") — yet it is the most important one: in your own container you
    // decide who is in, in a foreign one only what you yourself hang in.
    // Whoever looks for the delete button looks in "Eigene", and whoever
    // wants to know whom they are showing something sees it under "Von
    // anderen" at a glance.
    $own = array_values(array_filter($container,
        static fn(array $c): bool => (int) $c['owner_user_id'] === $userId));
    $foreign = array_values(array_filter($container,
        static fn(array $c): bool => (int) $c['owner_user_id'] !== $userId));
    ?>

<p class="hint-text">
    <?= h(t('Eine Freigabe sammelt Listen mehrerer Leute. Jeder hängt eigene Listen hinein und nimmt sie wieder heraus — Punkte gehören immer dem, der sie gesetzt hat. Was hier hängt, sehen alle Mitglieder auf ihrer Karte.')) ?>
</p>

<?php /* WITHOUT ICONS, as the administration's only tab bar. The library
     demands that tab icons differ FROM ONE ANOTHER, and here everything
     fitting is already taken two lines further up: the crate by
     "Freigaben" itself, the net by "Gemeinsam", the one, two and three
     people by "Mein Bereich", "Freunde" and "Arbeitsgruppen". An icon you
     confuse with the one above is worse than none — and "Eigene" and "Von
     anderen" cannot be confused even without a picture.

     The count sits on the tab because you cannot see inside from outside:
     "Von anderen (2)" says there is something there. Only where there is
     something — a zero would be noise. */ ?>
<?php /* THESE TWO STAY TABS, on the phone too (09.09.2026:
     „bei den Freigaben eigene und von anderen, diese beiden Tabs würd ich
     tatsächlich als Tabs auch anzeigen"). data-tabs="row" tells the
     chooser layer in tabs.js.

     There are two, they carry their counts in their names, and one jumps
     back and forth between them — a menu for two entries costs two taps
     instead of one and hides the very count one wants to compare. */ ?>
<div class="tab" role="tablist" data-tabs="row" aria-label="<?= h(t('Freigaben')) ?>">
    <button type="button" role="tab" id="tab-container-own" aria-controls="section-container-own"
            data-anchor="container-own"><?= h(t('Eigene')) ?><?= $own !== [] ? ' (' . count($own) . ')' : '' ?></button>
    <button type="button" role="tab" id="tab-container-others" aria-controls="section-container-others"
            data-anchor="container-others"><?= h(t('Von anderen')) ?><?= $foreign !== [] ? ' (' . count($foreign) . ')' : '' ?></button>
</div>

<?php /* The first panel stands there WITHOUT hidden, as with the offline tabs:
     otherwise the page is empty for a blink until tabs.js uncovers it. */ ?>
<section id="section-container-own" role="tabpanel" aria-labelledby="tab-container-own">
    <div class="column-header">
        <h3><?= h(t('Eigene Freigaben')) ?></h3>
        <button type="button" class="button small" id="container-plus"
                title="<?= h(t('Neue Freigabe')) ?>" aria-label="<?= h(t('Neue Freigabe')) ?>"><?= icon('plus') ?><?= h(t('Neu')) ?></button>
    </div>

    <?php if ($own === []): ?>
        <p class="hint-text"><?= h(t('Noch keinen angelegt. Über den Knopf „Neu“ legst du einen an.')) ?></p>
    <?php endif; ?>

    <?php foreach ($own as $c): ?>
        <?php share_block($pdo, $userId, $c, $details, $additions, $target); ?>
    <?php endforeach; ?>
</section>

<section id="section-container-others" role="tabpanel" aria-labelledby="tab-container-others" hidden>
    <div class="column-header">
        <h3><?= h(t('Freigaben von anderen')) ?></h3>
    </div>

    <?php if ($foreign === []): ?>
        <?php /* No plus and no "create one": you do not get into a foreign
             container from here, but by someone admitting you. A button
             promising that would lead nowhere. */ ?>
        <p class="hint-text">
            <?= h(t('Noch keine. Hier stehen die Freigaben, in die dich jemand aufgenommen hat — als Freund oder über eine Arbeitsgruppe.')) ?>
        </p>
    <?php endif; ?>

    <?php foreach ($foreign as $c): ?>
        <?php share_block($pdo, $userId, $c, $details, $additions, $target); ?>
    <?php endforeach; ?>
</section>

<?php /* The creation window: name and the birth filters. The filters exist
     ONLY here — set once, never changed (container_create explains why).
     That is why the warning stands alongside BEFORE you create, not after. */ ?>
<dialog id="container-new-window" class="form-window pinned-footer">
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="share">
        <input type="hidden" name="action" value="create">

        <h2><?= h(t('Neue Freigabe')) ?></h2>

        <div class="field">
            <label for="container-name"><?= h(t('Name')) ?></label>
            <input type="text" id="container-name" name="name" required maxlength="100"
                   placeholder="<?= h(t('z. B. Kartierung Nordhang 2026')) ?>">
        </div>

        <?php if (filter_ready($pdo)): ?>
            <p class="hint-text">
                <?= h(t('Filter halten zurück, was nicht gemeint ist: Von jeder eingehängten Liste teilen sie nur die Punkte, die passen. Sie gelten ab dem Anlegen und lassen sich nicht mehr ändern.')) ?>
            </p>

            <div class="field">
                <label for="container-filter-from"><?= h(t('Zeitraum der Funde')) ?></label>
                <div class="button-row">
                    <input type="date" id="container-filter-from" name="filter_from" aria-label="<?= h(t('von')) ?>">
                    <span class="item-data"><?= h(t('bis')) ?></span>
                    <input type="date" id="container-filter-to" name="filter_to" aria-label="<?= h(t('bis')) ?>">
                </div>
            </div>

            <?php /* Outlining happens on the BIG map, with the same drawing
                 toolbar as areas and offline regions — the decision
                 against a small map in the window. The button saves the
                 form state to sessionStorage and jumps over; the map adds
                 the corners and comes back, shared.js then reopens the
                 window filled in. */ ?>
            <div class="field">
                <label><?= h(t('Gebiet')) ?></label>
                <div class="button-row">
                    <button type="button" class="button subtle small" id="container-outline"><?= h(t('Auf der Karte umranden')) ?></button>
                    <?php /* Discard is a text button, not a bin: it deletes no
                         record, only a form input. */ ?>
                    <button type="button" class="button subtle small" id="container-area-discard" hidden><?= h(t('Verwerfen')) ?></button>
                    <span class="item-data" id="container-area-status"><?= h(t('Keins umrandet.')) ?></span>
                </div>
                <input type="hidden" name="filter_corners" id="container-filter-corners" value="">
            </div>

            <div class="field">
                <label><?= h(t('Titel-Regeln — alle müssen zutreffen')) ?></label>
                <?php /* To get rid of a rule you empty its text field: empty
                     rules do not count. A bin would stand for deleting a
                     record — none exists here yet. */ ?>
                <div id="container-rules"></div>
                <button type="button" class="button subtle small" id="container-rule-add"><?= h(t('Regel hinzufügen')) ?></button>
            </div>
        <?php endif; ?>

        <div class="dialog-footer">
            <button type="button" class="button subtle" id="container-new-cancel"><?= h(t('Abbrechen')) ?></button>
            <button type="submit" class="button"><?= h(t('Anlegen')) ?></button>
        </div>
    </form>
</dialog>

<?php /* ONE admission window for all containers. Friends and groups stand in
     ONE list ("f:12" / "g:3", the processing above separates) — two
     windows would be the question "which one was where again?". */ ?>
<dialog id="container-admit-window" class="form-window pinned-footer">
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="share">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="container" id="container-admit-container" value="">
        <input type="hidden" name="who" id="container-admit-who" value="">

        <?php /* Placeholder only — shared.js writes „In ‚Name' aufnehmen"
             on every opening. It still has to be German: half-translated
             fallback text is how „Mitglied capture" stood here. */ ?>
        <h2 id="container-admit-title"><?= h(t('Mitglied aufnehmen')) ?></h2>

        <div class="suggestion-list" id="container-admit-candidates"></div>

        <div class="dialog-footer">
            <button type="button" class="button subtle" id="container-admit-cancel"><?= h(t('Schließen')) ?></button>
        </div>
    </form>
</dialog>

<?php /* And ONE hang-in window: one's own lists in tree order, what already
     hangs blanked out. Whether a branch overlaps a shared one, the server
     checks — the error message explains it. */ ?>
<?php /* Checking instead of clicking: whoever stocks a survey often hangs in
     several lists at once — a separate trip through the window per list
     would be busywork. The hang-in button stays off until something is
     checked; shared.js fills the lists field on submit. */ ?>
<?php /* tabindex="-1": on opening, the window itself gets the focus
     (shared.js), not the first checkbox — its ring would read like a
     preselection. The same trick as on the ordnen-dialog. */ ?>
<dialog id="container-mount-window" class="form-window pinned-footer" tabindex="-1">
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="share">
        <input type="hidden" name="action" value="share_add">
        <input type="hidden" name="container" id="container-mount-container" value="">
        <input type="hidden" name="lists" id="container-mount-selected" value="">

        <h2 id="container-mount-title"><?= h(t('Listen einhängen')) ?></h2>

        <div class="suggestion-list" id="container-mount-lists"></div>

        <div class="dialog-footer">
            <button type="button" class="button subtle" id="container-mount-cancel"><?= h(t('Schließen')) ?></button>
            <button type="submit" class="button" id="container-mount-ok" disabled><?= h(t('Einhängen')) ?></button>
        </div>
    </form>
</dialog>

<script type="application/json" id="container-data">
<?= json_encode($forWindow, JSON_UNESCAPED_UNICODE) ?>
</script>

    <?php
}
