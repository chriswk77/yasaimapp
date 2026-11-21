<?php
/**
 * Gemeinsam → Geteilt — the overview of shared lists
 * (09.10.2026: „In der Verwaltung brauchen wir das dann gar nicht mehr so
 * kompliziert … welche Listen habe ich geteilt mit wem, einmal nach Listen
 * sortiert, einmal nach Freunden sortiert … und dann was wurde mit mir
 * geteilt").
 *
 * ONLY AN OVERVIEW. Who gets a list and what he may is set at the list
 * itself, in the window „Liste bearbeiten" — the pencil here leads there
 * (index.php?edit_list=…). Two places to change the same thing would be two
 * places that can disagree.
 *
 * NOT „share" IN ANY ID OR CLASS: content blockers hide what is called so
 * (the note at the tab buttons in pages/administration.php). Hence the
 * name „given".
 */

declare(strict_types=1);

require_once __DIR__ . '/list_shares.php';

/** The one deed here: stepping out of a list somebody shares with me. */
function given_process(PDO $pdo, int $userId): array
{
    $state = ['message' => null, 'error' => []];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'given') {
        return $state;
    }
    csrf_check();
    try {
        container_leave($pdo, $userId, (int) ($_POST['container'] ?? 0));
        $state['message'] = t('Du siehst die Liste nicht mehr. Punkte, die du hineingesetzt hattest, liegen jetzt in einer eigenen Liste.');
    } catch (InvalidArgumentException $e) {
        $state['error'][] = $e->getMessage();
    }
    return $state;
}

function given_render(PDO $pdo, int $userId, array $state): void
{
    foreach ($state['error'] as $e) {
        notice('error', $e, float: true);
    }
    if ($state['message'] !== null) {
        notice('ok', $state['message'], float: true);
    }
    if (!list_shares_ready($pdo)) {
        notice('warning', t('Listen teilen braucht die Migration 0160 — sie ist noch nicht eingespielt.'));
        return;
    }
    $given    = list_shares_given($pdo, $userId);
    $received = list_shares_received($pdo, $userId);
    $edit = static fn(int $listId): string => url('index.php') . '?edit_list=' . $listId;
    ?>
    <p><?= h(t('Hier siehst du, welche Listen du teilst und welche andere mit dir teilen. Wer eine Liste sieht und was er darf, stellst du an der Liste selbst ein: auf der Karte im Fenster „Liste bearbeiten", unter „Teilen". Der Stift führt dorthin.')) ?></p>

    <h2><?= h(t('Von dir geteilt')) ?></h2>
    <?php if ($given === []): ?>
        <p class="hint-text"><?= h(t('Du teilst noch keine Liste.')) ?></p>
    <?php else: ?>
        <fieldset class="field given-order">
            <label class="choice-row"><input type="radio" name="given-order" value="lists" checked> <?= h(t('Nach Listen')) ?></label>
            <label class="choice-row"><input type="radio" name="given-order" value="people"> <?= h(t('Nach Freunden')) ?></label>
        </fieldset>

        <?php // GROUPS INSTEAD OF A TABLE (11.10.2026: on the phone the
              // stacked table showed „Liste:" empty in front of every further
              // name). One head per list with the pencil at its end, the
              // people beneath it — the shape groups and members already
              // have (.person-section + .person-list). ?>
        <div class="given-groups" data-given="lists">
            <?php foreach ($given as $g): ?>
                <div class="person-section given-head">
                    <span><?= h($g['name']) ?></span>
                    <a class="symbol-button small" href="<?= h($edit($g['root'])) ?>" title="<?= h(t('In der Liste ändern')) ?>" aria-label="<?= h(t('„%s" in der Liste ändern', $g['name'])) ?>"><?= icon('pencil') ?></a>
                </div>
                <ul class="person-list">
                    <?php foreach ($g['recipients'] as $r): ?>
                        <li class="person-row">
                            <span class="person-name">
                                <?= icon($r['type'] === 'group' ? 'group' : 'person', ['class' => 'ct-row-icon']) ?>
                                <?= h($r['type'] === 'group' ? t('Gruppe „%s"', $r['name']) : $r['name']) ?>
                                <span class="item-data"><?= h($r['what']) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </div>

        <?php
        // The same rows turned round: per person or group, the lists they see.
        $people = [];
        foreach ($given as $g) {
            foreach ($g['recipients'] as $r) {
                $key = $r['type'] . ':' . $r['id'];
                $people[$key] ??= ['name' => $r['type'] === 'group' ? t('Gruppe „%s"', $r['name']) : $r['name'], 'lists' => []];
                $people[$key]['lists'][] = ['name' => $g['name'], 'root' => $g['root'], 'what' => $r['what']];
            }
        }
        uasort($people, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        ?>
        <div class="given-groups" data-given="people" hidden>
            <?php foreach ($people as $p): ?>
                <div class="person-section given-head"><span><?= h($p['name']) ?></span></div>
                <ul class="person-list">
                    <?php foreach ($p['lists'] as $l): ?>
                        <li class="person-row">
                            <span class="person-name">
                                <?= h($l['name']) ?>
                                <span class="item-data"><?= h($l['what']) ?></span>
                            </span>
                            <span class="person-buttons"><a class="symbol-button small" href="<?= h($edit($l['root'])) ?>" title="<?= h(t('In der Liste ändern')) ?>" aria-label="<?= h(t('„%s" in der Liste ändern', $l['name'])) ?>"><?= icon('pencil') ?></a></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 style="margin-top:2.4rem"><?= h(t('Mit dir geteilt')) ?></h2>
    <?php if ($received === []): ?>
        <p class="hint-text"><?= h(t('Noch teilt niemand eine Liste mit dir.')) ?></p>
    <?php else: ?>
        <div class="given-groups">
            <?php $last = null; foreach ($received as $r): ?>
                <?php if ($r['owner'] !== $last): ?>
                    <?php if ($last !== null): ?></ul><?php endif; ?>
                    <div class="person-section given-head"><span><?= h($r['owner']) ?></span></div>
                    <ul class="person-list">
                <?php endif; ?>
                <li class="person-row">
                    <span class="person-name">
                        <?= h($r['name']) ?><?= $r['joint'] ? ' · ' . h(t('gemeinsam')) : '' ?>
                        <span class="item-data"><?= h($r['what']) ?></span>
                    </span>
                    <span class="person-buttons">
                        <form method="post" action="<?= h(url('pages/administration.php') . '#given') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="section" value="given">
                            <input type="hidden" name="container" value="<?= (int) $r['container'] ?>">
                            <button type="submit" class="symbol-button small delete" title="<?= h(t('Nicht mehr sehen')) ?>"
                                    aria-label="<?= h(t('„%s" nicht mehr sehen', $r['name'])) ?>"
                                    data-confirm="<?= h(t('„%s" verschwindet bei dir. Bei %s läuft die Liste weiter. Punkte, die du hineingesetzt hast, bekommst du in eine eigene Liste zurück.', $r['name'], $r['owner'])) ?>"><?= icon('sign-out') ?></button>
                        </form>
                    </span>
                </li>
            <?php $last = $r['owner']; endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php
}
