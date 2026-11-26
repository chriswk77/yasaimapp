<?php
/**
 * Invitations — processing and view.
 *
 * A separate file for the same reason as the trash: the content now lives
 * as a tab in the admin area, the old path merely redirects there. Two
 * versions of the same form would be the surest way to let them drift
 * apart.
 */

declare(strict_types=1);

/**
 * Issue or withdraw a code. Must run BEFORE any output.
 *
 * 'section' tells the admin area's forms apart: on a page with several
 * tabs, every submit arrives at all of them, and without this field every
 * processing step would have to guess whether it was meant.
 *
 * @return array{code: ?string, error: string[]}
 */
function invitations_process(PDO $pdo, int $adminId): array
{
    $state = ['code' => null, 'error' => []];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'invitations') {
        return $state;
    }
    csrf_check();

    if (($_POST['action'] ?? '') === 'new') {
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 190);
        // UNLIMITED IS THE RULE, not the exception. Whoever you invite, you
        // want on board — and you do not want to explain in four weeks why
        // the link no longer works. A deadline exists anyway, for the case
        // that someone is only meant to have a look around.
        //
        // This concerns ONLY the code, not the membership: once redeemed,
        // the account has nothing more to do with it. It never expires, and
        // nothing attached to it ever disappears from disk.
        $raw  = trim((string) ($_POST['days'] ?? ''));
        $tage = $raw === '' ? null : max(1, min(365, (int) $raw));

        /* HOW MANY PEOPLE (26.11.2026, for the DGfM's state coordinators:
           one link in one letter, „einen universellen Einladungscode").
           One is the code as it always was and stores NULL; more counts
           down with each registration (migration 0204, account_create).
           Capped at 500 — a code for a crowd is the public registration,
           and that is another decision. */
        $withUses = column_exists($pdo, 'map_invites', 'uses_left');
        $uses = max(1, min(500, (int) ($_POST['uses'] ?? 1)));

        // The role hangs on the code, not on the registration form —
        // otherwise anyone could declare themselves an administrator when
        // joining. Whatever is not in the list becomes an ordinary user;
        // nothing is guessed here.
        $mitRolle = column_exists($pdo, 'map_invites', 'role');
        $role = (string) ($_POST['role'] ?? 'user');
        if (!in_array($role, ['admin', 'user', 'guest'], true)) {
            $role = 'user';
        }

        // On a collision, simply roll the dice again.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = invitation_code_generate();
            try {
                // Before migration 0026 the role column does not exist yet.
                $pdo->prepare($mitRolle
                    ? 'INSERT INTO map_invites (code, created_by, role, created_at, valid_until, note'
                      . ($withUses ? ', uses_left' : '') . ')
                       VALUES (?, ?, ?, NOW(),
                               CASE WHEN ? IS NULL THEN NULL ELSE DATE_ADD(NOW(), INTERVAL ? DAY) END, ?'
                      . ($withUses ? ', ?' : '') . ')'
                    : 'INSERT INTO map_invites (code, created_by, created_at, valid_until, note)
                       VALUES (?, ?, NOW(),
                               CASE WHEN ? IS NULL THEN NULL ELSE DATE_ADD(NOW(), INTERVAL ? DAY) END, ?)'
                )->execute($mitRolle
                    ? [$code, $adminId, $role, $tage, $tage, $note, ...($withUses ? [$uses > 1 ? $uses : null] : [])]
                    : [$code, $adminId, $tage, $tage, $note]);
                $state['code'] = $code;
                break;
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        if ($state['code'] === null) {
            $state['error'][] = t('Es ließ sich kein freier Code erzeugen. Bitte noch einmal versuchen.');
        }
    }

    if (($_POST['action'] ?? '') === 'delete') {
        // Only withdraw codes not yet redeemed — otherwise one would lose
        // the record of who invited whom.
        $pdo->prepare('DELETE FROM map_invites WHERE id = ? AND redeemed_by IS NULL')
            ->execute([(int) ($_POST['id'] ?? 0)]);
    }

    return $state;
}

/** The view. $state comes from invitations_process(). */
function invitations_render(PDO $pdo, array $state): void
{
    /* ONLY WHAT IS STILL OPEN (25.08.2026: „entferne
       automatisch die Benutzer, die eingelöst haben, dass dort nur noch
       die offenen stehen"). A redeemed code has nothing left to say
       here: the person it brought in stands in the user table below,
       with their name, their role and their find count — the code row
       repeated that fact in worse words and grew by one with every new
       account, until the two or three OPEN codes, the only ones anybody
       can act on, sat at the bottom of a page of history.

       „Offen" means not redeemed — the same reading the statistics row
       on the administration page has always used
       (COUNT(*) WHERE redeemed_by IS NULL). An EXPIRED code stays in the
       list on purpose: it is un-redeemed, it is why somebody cannot
       register, and it still has a button to withdraw it.

       HIDDEN, NOT DELETED. The row is the record of how an account came
       into being — who invited whom and when. auth.php recognises a
       spent code by exactly this row; deleting it would destroy the only
       trace, and nothing here would be better for it. */
    $invitations = $pdo->query(
        'SELECT i.* FROM map_invites i
          WHERE i.redeemed_by IS NULL
          ORDER BY i.created_at DESC'
    )->fetchAll();

    $registrierUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('register.php');

    // The anchor in the target: without it you land on the first tab after
    // submitting and hunt for the invitations from scratch. It does not go
    // to the server; the browser merely puts it on the response's address.
    $target = url('pages/administration.php') . '#members';
    ?>

<p class="hint-text">
    <?= h(t('Ohne Code kommt niemand herein. Ein Code gilt für eine Person, oder für so viele, wie du beim Erzeugen einträgst.')) ?>
</p>

<?php notice('error', $state['error'], true); ?>

<?php if ($state['code'] !== null): ?>
    <?php /* THE COPY BUTTONS RIGHT WHERE THE NEW CODE APPEARS (26.11.2026:
         „Was mir da fehlt, ist ein Button für Kopiere Code und kopiere
         direkt einen Link. Ich muss dann immer unten … über den Button"):
         the same rows as the window „Code und Link zeigen" further down —
         field and button in one line, the same data-copy handler
         (administration.js) — so the code is handed on from where it was
         made. */ ?>
    <div class="notice notice-success">
        <div class="field">
            <label for="invitation-new-code"><?= h(t('Neuer Code:')) ?></label>
            <div class="copy-row">
                <input type="text" id="invitation-new-code" readonly value="<?= h($state['code']) ?>">
                <button type="button" class="button" data-copy="#invitation-new-code"><?= icon('copy') ?><?= h(t('Code kopieren')) ?></button>
            </div>
        </div>
        <div class="field">
            <label for="invitation-new-link"><?= h(t('Direkter Link:')) ?></label>
            <div class="copy-row">
                <input type="text" id="invitation-new-link" readonly value="<?= h($registrierUrl . '?code=' . urlencode($state['code'])) ?>">
                <button type="button" class="button" data-copy="#invitation-new-link"><?= icon('copy') ?><?= h(t('Einladungslink kopieren')) ?></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="button-row" style="margin-top:1rem">
    <button type="button" class="button" id="invitation-new"><?= h(t('Neue Einladung')) ?></button>
</div>

<dialog id="invitation-window" class="form-window pinned-footer">
<form method="post" action="<?= h($target) ?>">
    <h2><?= h(t('Neue Einladung')) ?></h2>
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="invitations">
    <input type="hidden" name="action" value="new">

    <div class="field">
        <label for="invitation-note"><?= h(t('Für wen? (nur als Gedächtnisstütze)')) ?></label>
        <input type="text" id="invitation-note" name="note" placeholder="<?= h(t('z. B. Ben aus dem Wanderverein')) ?>">
    </div>

    <div class="field"<?= column_exists($pdo, 'map_invites', 'role') ? '' : ' hidden' ?>>
        <label for="invitation-role"><?= h(t('Als was?')) ?></label>
        <select id="invitation-role" name="role">
            <?php // The option VALUES are stored role keys and stay —
                  // only the visible labels go through t(). ?>
            <option value="user" selected><?= h(t('Benutzer — eigene Punkte, Listen und Fotos')) ?></option>
            <option value="admin"><?= h(t('Admin — dazu die Serververwaltung')) ?></option>
            <option value="guest"><?= h(t('Gast — noch ohne eigene Bedeutung')) ?></option>
        </select>
    </div>

    <?php if (column_exists($pdo, 'map_invites', 'uses_left')): ?>
    <div class="field">
        <label for="invitation-uses"><?= h(t('Für wie viele Personen?')) ?></label>
        <input type="number" id="invitation-uses" name="uses" value="1" min="1" max="500">
        <p class="hint-text small">
            <?= h(t('Mehr als 1 heißt: Alle bekommen denselben Link, zum Beispiel in einem Rundbrief. Jede Anmeldung zählt einen Platz ab.')) ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="field">
        <label for="invitation-days"><?= h(t('Gültig für … Tage (leer = unbegrenzt)')) ?></label>
        <input type="number" id="invitation-days" name="days" value="" min="1" max="365"
               placeholder="<?= h(t('unbegrenzt')) ?>">
        <p class="hint-text small">
            <?= h(t('Betrifft nur den Code, nicht die Mitgliedschaft: Wie lange er sich einlösen lässt. Ist er eingelöst, läuft nichts mehr ab.')) ?>
        </p>
    </div>

    <div class="dialog-footer">
        <button type="button" class="button subtle" id="invitation-cancel"><?= h(t('Abbrechen')) ?></button>
        <button class="button" type="submit"><?= h(t('Code erzeugen')) ?></button>
    </div>
</form>

</dialog>

<h3><?= h(t('Offene Codes')) ?></h3>

<?php if ($invitations === []): ?>
    <p class="hint-text"><?= h(t('Keine offenen Einladungen — eingelöste stehen als Benutzer unten.')) ?></p>
<?php else: ?>
    <div class="table-frame">
        <table class="list">
            <thead>
            <tr>
                <th><?= h(t('Code')) ?></th><th><?= h(t('Notiz')) ?></th><?php if (isset($invitations[0]['role'])): ?><th><?= h(t('Rolle')) ?></th><?php endif; ?><th><?= h(t('Gültig bis')) ?></th><th><?= h(t('Status')) ?></th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($invitations as $e): ?>
                <?php
                // Two states left, since redeemed ones no longer reach
                // this list: a code either still works or its day has
                // passed.
                $expired = $e['valid_until'] !== null && strtotime($e['valid_until']) < time();
                $status = $expired ? t('Abgelaufen')
                    // A code for several says how many it still lets in.
                    : (($e['uses_left'] ?? null) === null ? t('Offen')
                        : ((int) $e['uses_left'] === 1 ? t('Offen, noch ein Platz') : t('Offen, noch %d Plätze', (int) $e['uses_left'])));
                ?>
                <tr>
                    <td><code><?= h($e['code']) ?></code></td>
                    <td><?= h($e['note']) ?></td>
                    <?php if (isset($e['role'])): ?>
                        <td><?= h(t(role_readable($e['role']))) ?></td>
                    <?php endif; ?>
                    <td><?= $e['valid_until'] ? h(date('d.m.Y', strtotime($e['valid_until']))) : h(t('unbegrenzt')) ?></td>
                    <td><?= h($status) ?></td>
                    <td class="button-cell">
                        <?php /* SHOW CODE AND LINK AGAIN (12.09.2026:
                             „einen Button, bei dem noch mal der Code und
                             der Einladungslink angezeigt wird, und einen,
                             um den Link zu kopieren"). The green notice
                             shows both once, at minting; whoever comes
                             back a day later to hand the link on had to
                             build it by hand. The window below carries
                             both, each with a copy button — the same
                             fields and the same copy handler as the
                             pairing key (administration.js). Only while
                             the code still works: an expired one has no
                             link worth copying. */ ?>
                        <?php if (!$expired): ?>
                            <button type="button" class="symbol-button excursion-edit"
                                    data-window="invitation-show-<?= (int) $e['id'] ?>"
                                    title="<?= h(t('Code und Link zeigen')) ?>"
                                    aria-label="<?= h(t('Code und Link von %s zeigen', $e['code'])) ?>"><?= icon('link') ?></button>
                        <?php endif; ?>
                        <?php /* No condition around it any more: every row in
                             this list is un-redeemed, so every one can be
                             withdrawn. */ ?>
                            <form method="post" action="<?= h($target) ?>"
                                  data-confirm="<?= h(t('Der Code lässt sich danach nicht mehr einlösen.')) ?>"
                                  data-confirm-title="<?= h(t('Code zurückziehen?')) ?>"
                                  data-confirm-button="<?= h(t('Zurückziehen')) ?>"
                                  data-confirm-danger>
                                <?= csrf_field() ?>
                                <input type="hidden" name="section" value="invitations">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                                <button class="button subtle" type="submit"><?= h(t('Zurückziehen')) ?></button>
                            </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php foreach ($invitations as $e): ?>
        <?php if ($e['valid_until'] !== null && strtotime($e['valid_until']) < time()) continue; ?>
        <?php $link = $registrierUrl . '?code=' . urlencode($e['code']); ?>
        <dialog id="invitation-show-<?= (int) $e['id'] ?>" class="form-window is-wide">
            <div class="window-body">
                <h2><?= h(t('Einladung')) ?><?= $e['note'] !== '' ? ' · ' . h($e['note']) : '' ?></h2>
                <?php /* FIELD AND BUTTON IN ONE LINE (12.09.2026:
                     „hinter der Code-Zeile den Button Code kopieren,
                     hinter dem Einladungslink den Button — in einer
                     Zeile"). The field takes the width, the button
                     stands beside it at the field's height, like the
                     coordinate field and its copy button on the map. */ ?>
                <div class="field">
                    <label for="invitation-code-<?= (int) $e['id'] ?>"><?= h(t('Code')) ?></label>
                    <div class="copy-row">
                        <input type="text" id="invitation-code-<?= (int) $e['id'] ?>" readonly value="<?= h($e['code']) ?>">
                        <button type="button" class="button" data-copy="#invitation-code-<?= (int) $e['id'] ?>"><?= icon('copy') ?><?= h(t('Code kopieren')) ?></button>
                    </div>
                </div>
                <div class="field">
                    <label for="invitation-link-<?= (int) $e['id'] ?>"><?= h(t('Einladungslink')) ?></label>
                    <div class="copy-row">
                        <input type="text" id="invitation-link-<?= (int) $e['id'] ?>" readonly value="<?= h($link) ?>">
                        <button type="button" class="button" data-copy="#invitation-link-<?= (int) $e['id'] ?>"><?= icon('copy') ?><?= h(t('Einladungslink kopieren')) ?></button>
                    </div>
                </div>
                <div class="dialog-footer">
                    <button type="button" class="button subtle excursion-cancel"><?= h(t('Schließen')) ?></button>
                </div>
            </div>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>

    <?php
}
