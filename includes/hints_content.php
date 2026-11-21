<?php
/**
 * Verwaltung → Gemeinsam → „Direkt geteilt": the finds I show single
 * friends, and the finds friends show me (migration 0149,
 * includes/point_hints.php).
 *
 * 02.10.2026: „eine Verwaltung dieser einzeln freigegebenen
 * Punkte würde ich dann einen eigenen Reiter bei Gemeinsam erstellen, dass
 * man hier sieht, welche einzelnen Punkte man mit wem teilt, und diese
 * dort dann übersichtlich hat und dort auch wieder rausnehmen kann. Die
 * andere Seite kann dieses Teilen auch jederzeit wieder aufheben."
 *
 * TWO LISTS SIDE BY SIDE, the friends tab's shape: mine on the left, the
 * others' on the right — „Von dir direkt geteilte Punkte" and „Dir direkt
 * geteilte Punkte" (the wording, 02.10.2026), so the heads say which
 * road they are about: a friend may see the same find through a Freigabe
 * as well.
 *
 * ON THE LEFT, THE FRIENDS OF A FIND ARE A FOLD (his second message the
 * same evening: „ein Aufklappmenü Freunde. Es werden normal nur die
 * Angehakten angezeigt. Aber man kann es aufklappen und kann dann Haken
 * setzen. Und rausnehmen. Wenn der letzte Haken raus ist, ist die Freigabe
 * damit gelöscht. Mit Nachfrage natürlich"). Every friend's box is in the
 * markup; the shut fold hides the unticked ones. A tick saves at once
 * through api/v1/hints.php — the same door the point window's tile uses, so
 * the two can never behave differently (assets/js/hints.js).
 *
 * NO FORMS AND NO PROCESSING: nothing here posts the page. hints_process()
 * exists for the tab's frame and answers nothing.
 */

declare(strict_types=1);

require_once __DIR__ . '/point_hints.php';
require_once __DIR__ . '/friends.php';

function hints_process(PDO $pdo, int $userId): array
{
    return [];
}

/** „14.08.2026 · Arenshausen" — the find's day and place, what tells finds apart. */
function hints_find_line(array $row): string
{
    $day = date('d.m.Y', strtotime((string) ($row['found_at'] ?? $row['created_at'])));
    return $row['municipality'] ? $day . ' · ' . $row['municipality'] : $day;
}

function hints_render(PDO $pdo, int $userId, array $state = []): void
{
    if (!point_hints_ready($pdo)) {
        // A state, not a deed — it stands until somebody applies it.
        notice('hint', t('Direkt teilen braucht die Migration 0149 — sie ist noch nicht eingespielt.'));
        return;
    }
    $given    = point_hints_given($pdo, $userId);
    $received = point_hints_received($pdo, $userId);
    $friends  = friends_ready($pdo) ? friends_overview($pdo, $userId)['friends'] : [];
    ?>

<p class="hint-text">
    <?= h(t('Hier stehen die Punkte, die du direkt mit einzelnen Freunden teilst, und die Punkte, die Freunde direkt mit dir teilen. Direkt teilen kannst du einen Punkt im Punktefenster: im Reiter „Karte" unter „Direkt teilen".')) ?>
</p>

<div class="column-pair hint-columns">
    <div>
        <div class="column-header">
            <h3><?= h(t('Von dir direkt geteilte Punkte')) ?></h3>
        </div>
        <?php if ($given === []): ?>
            <p class="hint-text"><?= h(t('Du teilst noch keinen Punkt direkt mit einem Freund.')) ?></p>
        <?php else: ?>
            <ul class="person-list" data-hint-given>
                <?php foreach ($given as $row):
                    $shown = array_column($row['recipients'], 'id');
                    // Who shared it where that was not me — a co-owner of my
                    // find (06.10.2026); his share stands here so I can take it back.
                    $by = array_column($row['recipients'], 'by', 'id');
                    $friendIds = array_map(static fn(array $f): int => (int) $f['user_id'], $friends); ?>
                    <li class="person-row hint-row" data-hint-point="<?= (int) $row['point_id'] ?>">
                        <span class="person-name">
                            <?= h($row['title'] !== '' ? $row['title'] : t('Ohne Titel')) ?>
                            <span class="item-data"><?= h(hints_find_line($row)) ?></span>
                        </span>
                        <span class="person-buttons">
                            <button type="button" class="symbol-button hint-fold" aria-expanded="false"
                                    title="<?= h(t('Alle Freunde aufklappen')) ?>" aria-label="<?= h(t('Alle Freunde aufklappen')) ?>"><?= icon('fold-triangle', ['class' => 'collapse-triangle']) ?></button>
                        </span>
                        <div class="hint-friends">
                            <?php foreach ($friends as $f):
                                $on = in_array((int) $f['user_id'], $shown, true); ?>
                                <label class="checkbox hint-friend"<?= $on ? '' : ' hidden' ?>>
                                    <input type="checkbox" data-friend="<?= (int) $f['user_id'] ?>"
                                           data-name="<?= h($f['display_name']) ?>"<?= $on ? ' checked' : '' ?>>
                                    <span><?= h($f['display_name']) ?><?php if (!empty($by[(int) $f['user_id']])): ?>
                                        <span class="item-data"><?= h(t('geteilt von %s', $by[(int) $f['user_id']])) ?></span><?php endif; ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php /* A co-owner's friend who is none of mine: he
                                     stands here all the same, ticked, with the
                                     co-owner's name — or the holder could not
                                     take the share back. */ ?>
                            <?php foreach ($row['recipients'] as $r): ?>
                                <?php if (in_array($r['id'], $friendIds, true)) continue; ?>
                                <label class="checkbox hint-friend">
                                    <input type="checkbox" data-friend="<?= (int) $r['id'] ?>"
                                           data-name="<?= h($r['name']) ?>" checked>
                                    <span><?= h($r['name']) ?><?php if ($r['by'] !== null): ?>
                                        <span class="item-data"><?= h(t('geteilt von %s', $r['by'])) ?></span><?php endif; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="hint-text" data-hint-given-empty hidden><?= h(t('Du teilst noch keinen Punkt direkt mit einem Freund.')) ?></p>
        <?php endif; ?>
    </div>

    <div>
        <div class="column-header">
            <h3><?= h(t('Dir direkt geteilte Punkte')) ?></h3>
        </div>
        <?php if ($received === []): ?>
            <p class="hint-text"><?= h(t('Noch kein Freund teilt einen Punkt direkt mit dir.')) ?></p>
        <?php else: ?>
            <ul class="person-list" data-hint-received>
                <?php foreach ($received as $row): ?>
                    <li class="person-row" data-hint-point="<?= (int) $row['point_id'] ?>">
                        <span class="person-name">
                            <?= h($row['title'] !== '' ? $row['title'] : t('Ohne Titel')) ?>
                            <span class="item-data"><?= h(t('von %s', $row['giver']) . ' · ' . hints_find_line($row)) ?></span>
                        </span>
                        <span class="person-buttons">
                            <?php /* THE DOOR: the find carries on without me — at
                                 its holder, and in every list he shares with
                                 me otherwise. Red, because only he can show
                                 it again. */ ?>
                            <button type="button" class="symbol-button delete hint-leave"
                                    data-giver="<?= h($row['giver']) ?>"
                                    title="<?= h(t('Entfernen')) ?>"
                                    aria-label="<?= h(t('Punkt von %s aus „Direkt geteilt" entfernen', $row['giver'])) ?>"><?= icon('sign-out') ?></button>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="hint-text" data-hint-received-empty hidden><?= h(t('Noch kein Freund teilt einen Punkt direkt mit dir.')) ?></p>
        <?php endif; ?>
    </div>
</div>
    <?php
}
