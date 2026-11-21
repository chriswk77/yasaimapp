<?php
/**
 * User administration — processing and view.
 *
 * Who is here, what they may do, how much space they have. Nothing more:
 * no creating by hand — one only gets in via an invitation, and it shall
 * stay that way. A form for producing accounts would be a second path
 * past the same door.
 *
 * WHAT CAN BE CHANGED: the role and the quota. The administrator enters
 * both, both take effect immediately.
 *
 * BLOCKING OR DELETING — between them lies the whole difference:
 *
 *   BLOCKED   no longer gets in, but keeps everything. Whoever is
 *             unblocked finds their points as if nothing had happened.
 *   DELETED   is gone, along with points, lists, areas and files. The
 *             database clears most of it away itself; the photos on disk
 *             must be cleared away by this script, it does not know them.
 *
 * NONE OF THIS WORKS ON ONESELF, and no demotion either: whoever is the
 * last admin and takes the role from themselves locks everyone out of the
 * server administration — and cannot unlock it again themselves. That is
 * the one door that cannot be opened again from the inside.
 */

declare(strict_types=1);

require_once __DIR__ . '/user_store.php';

/**
 * Change role or quota. Must run BEFORE any output.
 *
 * 'section' tells the administration's forms apart: on a page with
 * several tabs every submit arrives at all of them.
 *
 * @return array{message: ?string, error: string[]}
 */
function members_process(PDO $pdo, int $adminId): array
{
    $state = ['message' => null, 'error' => []];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'members') {
        return $state;
    }
    if (!members_ready($pdo)) {
        $state['error'][] = t('Erst die Migrationen einspielen.');
        return $state;
    }
    csrf_check();

    $id     = (int) ($_POST['id'] ?? 0);
    /* THE DEFAULT VALUE HAS TO BE RENAMED ALONG. It sat here as
       'speichern' while every comparison below already said 'save' — a
       submit without an action field would have fallen through every case
       and done nothing, without a word. The form does always send the
       field, but the default must not rely on that; otherwise it would be
       no default at all. */
    $action = (string) ($_POST['action'] ?? 'save');

    if ($id <= 0) {
        $state['error'][] = t('Kein Benutzer angegeben.');
        return $state;
    }

    // None of these actions works on oneself. Locking yourself out is the
    // one mistake that cannot be taken back.
    if ($id === $adminId && $action !== 'save') {
        $state['error'][] = t('Das eigene Konto lässt sich hier nicht sperren oder löschen.');
        return $state;
    }

    // And not on the founder account either. It is the anchor of the
    // installation: whoever removes it takes away the last safe way back
    // into the administration — even when other admins still exist at
    // that moment, because their accounts can be lost as well.
    if (is_founder($pdo, $id) && $action !== 'save') {
        $state['error'][] = t('Das ist das Gründerkonto dieser Anlage — es lässt sich weder sperren noch löschen.');
        return $state;
    }

    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, role, locked_at FROM map_users WHERE id = ?');
    $stmt->execute([$id]);
    $member = $stmt->fetch();
    if ($member === false) {
        $state['error'][] = t('Diesen Benutzer gibt es nicht.');
        return $state;
    }

    if ($action === 'lock' || $action === 'unlock') {
        $pdo->prepare('UPDATE map_users SET locked_at = ? WHERE id = ?')
            ->execute([$action === 'lock' ? date('Y-m-d H:i:s') : null, $id]);

        // When blocking, take the device tokens along: otherwise the phone
        // that ticked "angemeldet bleiben" would keep getting in — the
        // session does check too, but an invalidated token is the cleaner
        // answer.
        if ($action === 'lock') {
            $pdo->prepare('DELETE FROM map_tokens WHERE user_id = ?')->execute([$id]);
        }

        // One whole sentence per case — a translation cannot reorder
        // glued fragments (i18n house rule).
        $state['message'] = $action === 'lock'
            ? t('%s ist jetzt gesperrt.', user_display_name($member))
            : t('%s ist jetzt wieder frei.', user_display_name($member));
        return $state;
    }

    if ($action === 'delete') {
        return members_delete($pdo, $member, $state);
    }

    $role      = role_check($_POST['role'] ?? $member['role']);
    $firstName = mb_substr(trim((string) ($_POST['first_name'] ?? '')), 0, 60);
    $lastName  = mb_substr(trim((string) ($_POST['last_name'] ?? '')), 0, 80);

    // The one door that cannot be opened again from the inside.
    if ($id === $adminId && $role !== 'admin') {
        $state['error'][] = t('Die eigene Admin-Rolle lässt sich hier nicht abgeben — sonst käme niemand mehr an die Serververwaltung.');
        return $state;
    }

    // An empty field means unlimited. Input is in megabytes, because
    // nobody has bytes in their head; stored is in bytes, because anything
    // else would have to be rounded later.
    $raw = trim((string) ($_POST['quota_mb'] ?? ''));
    if ($raw === '') {
        $quota = null;
    } elseif (!is_numeric($raw) || (float) $raw < 0) {
        $state['error'][] = t('Das Kontingent muss eine Zahl in Megabyte sein — oder leer für unbegrenzt.');
        return $state;
    } else {
        $quota = (int) round((float) $raw * 1024 * 1024);
    }

    $pdo->prepare(
        'UPDATE map_users SET first_name = ?, last_name = ?, role = ?, quota_bytes = ?
          WHERE id = ?'
    )->execute([$firstName, $lastName, $role, $quota, $id]);

    $state['message'] = t('%s: %s, %s', trim($firstName . ' ' . $lastName), t(role_readable($role)),
        $quota === null ? t('unbegrenzter Platz') : bytes_readable($quota));
    return $state;
}

/**
 * An account along with everything attached to it.
 *
 * THE DATABASE CLEARS MOST OF IT AWAY ITSELF — points, lists, areas,
 * device tokens and shares hang on the account with ON DELETE CASCADE.
 * Who invited whom is set to NULL instead of deleted along: that there
 * was an invitation stays true even when the inviter is gone.
 *
 * ONE TABLE FALLS THROUGH: map_point_overwritten deliberately has no
 * foreign key (migration 0039) — "your change was overwritten and the
 * point is gone by now" is supposed to survive the deletion of a point.
 * On deletion of the ACCOUNT, though, the same decision means: the
 * database does not clear these rows away, and nobody could ever read
 * them again. So here, by hand — otherwise they would lie forever under
 * an id that belongs to no one anymore.
 *
 * WHAT IT DOES NOT KNOW ARE THE FILES. They sit on disk, and if they do
 * not go away here, they stay lying around forever — under an id that
 * someone else gets later.
 *
 * THE LAST ADMIN STAYS. Without them there would be no way back into the
 * server administration, and thus none to appoint a new one either.
 */
function members_delete(PDO $pdo, array $member, array $state): array
{
    $id = (int) $member['id'];

    if ($member['role'] === 'admin') {
        $others = (int) $pdo->query(
            'SELECT COUNT(*) FROM map_users WHERE role = "admin" AND id <> ' . $id
        )->fetchColumn();
        if ($others === 0) {
            $state['error'][] = t('Das ist der letzte Admin — ohne ihn käme niemand mehr an die Serververwaltung. Erst jemand anderen zum Admin machen.');
            return $state;
        }
    }

    // The one table without a foreign key (see above) by hand, before the
    // account falls. table_exists because the code goes live before
    // the migration — on a server without 0039 there is simply nothing to
    // clear.
    if (table_exists($pdo, 'map_point_overwritten')) {
        $pdo->prepare('DELETE FROM map_point_overwritten WHERE user_id = ?')->execute([$id]);
    }

    /* THE JOINT LISTS ARE HANDED OVER FIRST (09.10.2026: „an
       den Nächsten, der am längsten an dieser Liste schon mitarbeitet —
       Finn geht oder löscht das Konto"). The cascade below would take
       the list from everybody who works in it; handed over, it lives on
       with its heir, and only the account's own points go. */
    require_once __DIR__ . '/joint_lists.php';
    if (share_joint_ready($pdo)) {
        $joint = $pdo->prepare('SELECT DISTINCT list_id FROM map_container_lists WHERE user_id = ? AND joint = 1');
        $joint->execute([$id]);
        foreach ($joint->fetchAll(PDO::FETCH_COLUMN) as $listId) {
            joint_list_hand_over($pdo, $id, (int) $listId);
        }
    }

    $pdo->prepare('DELETE FROM map_users WHERE id = ?')->execute([$id]);

    // The account's friendships and groups go with it — the tables have
    // no foreign keys (see migration 0041), this is the one place that
    // answers for it. Otherwise friend_ids() would keep the deleted user
    // alive and pull them into containers and shares later.
    if (table_exists($pdo, 'map_friends')) {
        $pdo->prepare('DELETE FROM map_friends WHERE from_user_id = ? OR to_user_id = ?')
            ->execute([$id, $id]);
        $pdo->prepare('DELETE FROM map_group_members WHERE user_id = ?
                          OR group_id IN (SELECT id FROM map_groups WHERE owner_user_id = ?)')
            ->execute([$id, $id]);
        $pdo->prepare('DELETE FROM map_groups WHERE owner_user_id = ?')->execute([$id]);
    }

    // And the containers (migration 0042): own ones including their
    // entries, plus every own membership and share elsewhere. The rest —
    // third parties' shares left standing without a membership — is put
    // right by container_cleanup().
    require_once __DIR__ . '/container.php';
    if (container_ready($pdo)) {
        $pdo->prepare('DELETE FROM map_container_members
                        WHERE container_id IN (SELECT id FROM map_containers WHERE owner_user_id = ?)
                           OR (type = \'friend\' AND target_id = ?)')->execute([$id, $id]);
        $pdo->prepare('DELETE FROM map_container_lists
                        WHERE user_id = ?
                           OR container_id IN (SELECT id FROM map_containers WHERE owner_user_id = ?)')
            ->execute([$id, $id]);
        $pdo->prepare('DELETE FROM map_containers WHERE owner_user_id = ?')->execute([$id]);
        container_cleanup($pdo);
    }

    // Only after the database delete: if the row stays put because
    // something intervenes, it at least still has its files.
    directory_remove(user_directory($id));

    $state['message'] = t('%s ist gelöscht, samt Punkten, Listen und Dateien.', user_display_name($member));
    return $state;
}

/** A directory with everything in it. If it is missing, nothing to do. */
function directory_remove(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
    }
    @rmdir($path);
}

/**
 * Are the columns there that this view works with?
 *
 * THE QUESTION HAS TO BE ASKED because administration.php builds all tabs in
 * one pass. If one of them aborts, the page does not come into being at
 * all — not even the tab with the migrations. And that is exactly the
 * one you would then need.
 */
function members_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_users', 'role')
        && column_exists($pdo, 'map_users', 'locked_at');
}

function members_render(PDO $pdo, int $adminId, array $state): void
{
    if (!members_ready($pdo)) {
        notice('hint',
            t('Die Benutzerverwaltung braucht Migrationen, die auf diesem Server noch nicht eingespielt sind. Der Reiter „Migrationen" nebenan erledigt das.'));
        return;
    }

    $members = $pdo->query(
        'SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.quota_bytes,
                u.locked_at, u.created_at, u.last_login_at,
                ' . sql_display_name('e') . ' AS inviter,
                (SELECT COUNT(*) FROM map_points p WHERE p.user_id = u.id AND p.deleted_at IS NULL) AS points,
                (SELECT COUNT(*) FROM map_lists  l WHERE l.user_id = u.id AND l.deleted_at IS NULL) AS lists
           FROM map_users u
           LEFT JOIN map_users e ON e.id = u.invited_by
          ORDER BY u.created_at'
    )->fetchAll();

    $target = url('pages/administration.php') . '#members';
    ?>

<p class="hint-text">
    <?= h(t('Wer hier ist und was er darf. Herein kommt man nur über eine Einladung — die stellst du weiter oben aus, und dort wird auch schon festgelegt, mit welcher Rolle jemand ankommt.')) ?>
</p>

<?php notice('error', $state['error'], true); ?>
<?php if ($state['message'] !== null): ?>
    <?php notice('success', t('Geändert — %s', $state['message']), true); ?>
<?php endif; ?>

<div class="table-frame">
    <table class="list">
        <thead>
        <tr>
            <th><?= h(t('Wer')) ?></th><th><?= h(t('Platz')) ?></th><th><?= h(t('Inhalt')) ?></th><th><?= h(t('Dabei seit')) ?></th><th><?= h(t('Ändern')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($members as $m): ?>
            <?php
            $used = user_used((int) $m['id']);
            $limit = $m['quota_bytes'] === null ? null : (int) $m['quota_bytes'];
            ?>
            <tr>
                <td>
                    <strong><?= h(user_display_name($m)) ?></strong>
                    <span class="item-data">· <?= h(t(role_readable($m['role']))) ?></span>
                    <?php if (is_founder($pdo, (int) $m['id'])): ?>
                        <span class="badge-founder" title="<?= h(t('Das erste Konto dieser Anlage')) ?>"><?= h(t('Gründer')) ?></span>
                    <?php endif; ?>
                    <?php if ($m['locked_at'] !== null): ?>
                        <span class="badge-locked"><?= h(t('gesperrt seit %s',
                            date('d.m.Y', strtotime((string) $m['locked_at'])))) ?></span>
                    <?php endif; ?><br>
                    <span class="item-data"><?= h($m['email']) ?></span>
                    <?php if ($m['inviter'] !== null): ?>
                        <br><span class="item-data"><?= h(t('eingeladen von %s', $m['inviter'])) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php /* One key for the pair: a bare 'von %s' would
                           collide with the owner attribution elsewhere
                           ("von Tobias" = by), while this one means "of". */ ?>
                    <?= $limit !== null
                        ? h(t('%s von %s', bytes_readable($used), bytes_readable($limit)))
                        : h(bytes_readable($used)) ?>
                </td>
                <td class="item-data">
                    <?= h(t('%d Punkte, %d Listen', (int) $m['points'], (int) $m['lists'])) ?>
                </td>
                <td class="item-data">
                    <?= h(date('d.m.Y', strtotime((string) $m['created_at']))) ?><br>
                    <?= $m['last_login_at']
                        ? h(t('zuletzt %s', date('d.m.Y', strtotime((string) $m['last_login_at']))))
                        : h(t('noch nie da')) ?>
                </td>
                <td>
                    <button type="button" class="button subtle small user-edit"
                            data-id="<?= (int) $m['id'] ?>"
                            data-name="<?= h(user_display_name($m)) ?>"
                            data-first_name="<?= h($m['first_name']) ?>"
                            data-last_name="<?= h($m['last_name']) ?>"
                            data-role="<?= h($m['role']) ?>"
                            data-quota="<?= $limit === null ? '' : (int) round($limit / 1024 / 1024) ?>"
                            data-locked="<?= $m['locked_at'] === null ? '' : '1' ?>"
                            data-self="<?= (int) $m['id'] === $adminId || is_founder($pdo, (int) $m['id']) ? '1' : '' ?>"
                            data-points="<?= (int) $m['points'] ?>"
                            data-lists="<?= (int) $m['lists'] ?>">
                        <?= h(t('Bearbeiten')) ?>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p class="hint-text small">
    <?= h(t('Der belegte Platz wird bei jedem Aufruf auf der Platte nachgezählt, nicht mitgeschrieben — ein Zähler in einer Spalte läuft irgendwann auseinander, und dann stimmt er nie wieder.')) ?>
</p>
<p class="hint-text small">
    <?= h(t('Am eigenen Konto geht weder Sperren noch Löschen noch Herabstufen: Wer der letzte Admin ist und sich die Rolle nimmt, sperrt die Serververwaltung für alle aus — und kann sie selbst nicht wieder aufschliessen. Dasselbe gilt für das Gründerkonto, also das erste dieser Anlage: Es ist der Anker, an dem man sich zurückhangeln kann, wenn sonst nichts mehr geht.')) ?>
</p>

<?php /* ONE WINDOW FOR ALL ROWS, not one per user. With twenty members that
     would be twenty forms in the source, nineteen of which nobody ever
     sees. What belongs in it sits on the row's button; the script fills
     it in. */ ?>
<dialog id="user-window" class="form-window pinned-footer">
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="members">
        <input type="hidden" name="id" id="user-id">
        <input type="hidden" name="action" id="user-action" value="save">

        <h2 id="user-heading"><?= h(t('Benutzer bearbeiten')) ?></h2>

        <div class="field">
            <label for="user-first-name"><?= h(t('Vorname')) ?></label>
            <input type="text" id="user-first-name" name="first_name" maxlength="60">
        </div>

        <div class="field">
            <label for="user-last-name"><?= h(t('Nachname')) ?></label>
            <input type="text" id="user-last-name" name="last_name" maxlength="80">
        </div>

        <div class="field">
            <label for="user-role"><?= h(t('Rolle')) ?></label>
            <select id="user-role" name="role">
                <?php // The VALUE is the stored role key — only the label
                      // is display. The German labels live in ROLES
                      // (auth.php); t() looks them up like any sentence. ?>
                <?php foreach (ROLES as $value => $text): ?>
                    <option value="<?= h($value) ?>"><?= h(t($text)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="user-quota"><?= h(t('Speicherplatz in MB')) ?></label>
            <input type="number" id="user-quota" name="quota_mb" min="0" step="1"
                   placeholder="<?= h(t('leer = unbegrenzt')) ?>">
        </div>

        <?php /* The two grave actions set apart from the rest, with a
             confirmation prompt. data-confirm comes from dialogs.js. */ ?>
        <div class="field-danger" id="user-danger">
            <button type="submit" class="button subtle small" id="user-lock"
                    formnovalidate
                    data-confirm="<?= h(t('Er kommt dann nicht mehr herein, behält aber alles. Rückgängig zu machen.')) ?>"
                    data-confirm-title="<?= h(t('Konto sperren?')) ?>"
                    data-confirm-button="<?= h(t('Sperren')) ?>"><?= h(t('Sperren')) ?></button>

            <button type="submit" class="button danger small" id="user-delete"
                    formnovalidate
                    data-confirm-title="<?= h(t('Konto endgültig löschen?')) ?>"
                    data-confirm-button="<?= h(t('Endgültig löschen')) ?>"><?= h(t('Löschen')) ?></button>
        </div>

        <div class="dialog-footer">
            <button type="button" class="button subtle" id="user-cancel"><?= h(t('Abbrechen')) ?></button>
            <?php /* Quiet until something changed, measured each time the
                 window opens — user.js fills it right before (quietsave.js). */ ?>
            <button type="submit" class="button subtle" data-quiet-until-changed="open"><?= icon('save') ?><?= h(t('Speichern')) ?></button>
        </div>
    </form>
</dialog>
<?php
}
