<?php
/**
 * Excursions — processing and view (Gemeinsam → Exkursionen).
 *
 * Stage 1: the leader creates and manages his excursions — name, time
 * window, photo policy. The QR access for guests, the guest account and
 * the collected points come in later stages; what they will all hang on
 * is the row this tab manages (see includes/excursions.php for the
 * carrying thought).
 */

declare(strict_types=1);

require_once __DIR__ . '/excursions.php';

/** Must run BEFORE any output — the forms send back to this page. */
function excursions_process(PDO $pdo, int $userId): array
{
    $result = ['message' => null, 'error' => []];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'excursions') {
        return $result;
    }
    csrf_check();

    try {
        switch ((string) ($_POST['action'] ?? '')) {
            case 'create':
                /* THE CO-LEADERS RIDE ALONG (11.09.2026). They are
                   appointed AFTER the excursion exists, because an
                   appointment needs something to hang on — and through
                   the same function a single one goes through, so the
                   friendship check exists once. */
                $fresh = excursion_create($pdo, $userId, $_POST);
                $taken = excursion_leaders_take($pdo, $userId, $fresh, $_POST['leaders'] ?? []);
                $result['message'] = $taken === 0
                    ? t('Exkursion angelegt.')
                    : ($taken === 1
                        ? t('Exkursion angelegt — ein Mitleiter ist dabei.')
                        : t('Exkursion angelegt — %d Mitleiter sind dabei.', $taken));
                break;

            case 'save':
                excursion_save($pdo, $userId, (int) ($_POST['excursion'] ?? 0), $_POST);
                $result['message'] = t('Exkursion gespeichert.');
                break;

            case 'delete':
                excursion_delete($pdo, $userId, (int) ($_POST['excursion'] ?? 0));
                $result['message'] = t('Exkursion gelöscht.');
                break;

            /* The leader joins his own excursion — main or co, the check
               below decides. Like a registered guest he keeps his finds
               in his OWN stock; only the reference travels with them,
               and it is what makes them part of the excursion. */
            case 'map_along':
                // The same step the map's badge takes (excursion_map_along)
                // — a row of his own, tied to his account, and the mark in
                // the session.
                excursion_map_along($pdo, $userId, (int) ($_POST['excursion'] ?? 0));
                header('Location: ' . url('index.php'));
                exit;

            case 'leader_add':
                excursion_leader_add($pdo, $userId,
                    (int) ($_POST['excursion'] ?? 0), (int) ($_POST['who'] ?? 0));
                $result['message'] = t('Mitleiter aufgenommen.');
                break;

            case 'leader_remove':
                $who = (int) ($_POST['who'] ?? 0);
                excursion_leader_remove($pdo, $userId, (int) ($_POST['excursion'] ?? 0), $who);
                $result['message'] = $who === $userId
                    ? t('Als Mitleiter ausgetreten.')
                    : t('Mitleiter entfernt.');
                break;
        }
    } catch (InvalidArgumentException $e) {
        $result['error'][] = error_for_user($e);
    }

    return $result;
}

/** "2026-08-30 09:00:00" → "2026-08-30T09:00" for a datetime-local field. */
function excursion_field_time(string $t): string
{
    return str_replace(' ', 'T', substr($t, 0, 16));
}

/** The shared fields of the create and edit windows — one source, so the
 *  two can never ask for different things. $e is null when creating. */
function excursion_form_fields(?array $e, string $prefix): void
{
    ?>
    <div class="field">
        <label for="<?= $prefix ?>-name"><?= h(t('Name')) ?></label>
        <input type="text" id="<?= $prefix ?>-name" name="name" required maxlength="200"
               value="<?= h((string) ($e['name'] ?? '')) ?>"
               placeholder="<?= h(t('z. B. Herbstexkursion Nordhang')) ?>">
    </div>
    <div class="field">
        <label for="<?= $prefix ?>-starts"><?= h(t('Zeitfenster')) ?></label>
        <?php /* In this window guests will be able to collect points; with its
             end the QR access ends by itself. Said HERE because the window
             is set here — a tooltip at the QR code would come too late. */ ?>
        <div class="button-row">
            <input type="datetime-local" id="<?= $prefix ?>-starts" name="starts_at" required
                   value="<?= h($e !== null ? excursion_field_time((string) $e['starts_at']) : '') ?>"
                   aria-label="<?= h(t('von')) ?>">
            <span class="item-data"><?= h(t('bis')) ?></span>
            <input type="datetime-local" id="<?= $prefix ?>-ends" name="ends_at" required
                   value="<?= h($e !== null ? excursion_field_time((string) $e['ends_at']) : '') ?>"
                   aria-label="<?= h(t('bis')) ?>">
        </div>
    </div>
    <div class="field">
        <label for="<?= $prefix ?>-description"><?= h(t('Beschreibung')) ?></label>
        <?php /* The invitation (24.08.2026): where it goes, which
             forest, what to expect. It stands on the guest page behind QR
             and link, and the planned mail dispatch will carry the same
             words. Grows by itself — autoheight.js binds every textarea. */ ?>
        <textarea id="<?= $prefix ?>-description" name="description" rows="3" maxlength="5000"
                  placeholder="<?= h(t('Wohin es geht, was zu erwarten ist — der Text steht auf der Beitrittsseite.')) ?>"><?=
            h((string) ($e['description'] ?? '')) ?></textarea>
    </div>
    <label class="filter-check">
        <input type="checkbox" name="photos_allowed" value="1"
            <?= $e === null || !empty($e['photos_allowed']) ? 'checked' : '' ?>>
        <?= h(t('Fotos erlaubt')) ?>
    </label>
    <div class="field">
        <label for="<?= $prefix ?>-photos"><?= h(t('Fotos je Teilnehmer, höchstens')) ?></label>
        <?php /* PER PARTICIPANT, not per excursion (24.08.2026,
             against the concept's first reading): a shared pool would
             refuse a guest for pictures he cannot see and cannot delete.
             Per participant, the refusal is always actionable — and the
             excursion stays bounded at guests × measure. Empty means no
             limit. */ ?>
        <input type="number" id="<?= $prefix ?>-photos" name="photo_limit" min="1"
               value="<?= h((string) ($e['photo_limit'] ?? '')) ?>"
               placeholder="<?= h(t('leer = unbegrenzt')) ?>">
    </div>
    <?php
    /* THE OFFLINE MAP (24.08.2026): one of the leader's OWN
       areas — drawn, edited and measured in the Offline tab, and merely
       referenced here. A drawing surface of its own would be the twin
       that drifts. The guests are offered it in their onboarding, over
       the reception at the car park.

       THE SIZE IS SHOWN WHILE PICKING, not on the guest's phone: the
       leader chooses area and zoom, so he is the one who should see what
       he is asking of other people's data plans. administration.js
       computes it from the outline (areas.js) and writes the two hidden
       fields the server stores. */
    global $pdo;
    $areas = excursion_areas_of($pdo, (int) (user_current()['id'] ?? 0));
    if (!excursion_area_ready($pdo)) {
        return;
    }
    ?>
    <div class="field">
        <label for="<?= $prefix ?>-area"><?= h(t('Offline-Karte für die Gäste')) ?></label>
        <?php if ($areas === []): ?>
            <p class="hint-text small">
                <?= h(t('Du hast noch kein Gebiet. Eines legst du unter „Offline" an — dann kannst du es hier anbieten.')) ?>
            </p>
        <?php else: ?>
            <select id="<?= $prefix ?>-area" name="area_id" class="excursion-area-picker"
                    data-prefix="<?= h($prefix) ?>">
                <option value="0"><?= h(t('— keine —')) ?></option>
                <?php foreach ($areas as $a): ?>
                    <option value="<?= (int) $a['id'] ?>"
                            data-corners="<?= h((string) $a['corners']) ?>"
                        <?= (int) ($e['area_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>>
                        <?= h((string) $a['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>
    <?php if ($areas !== []): ?>
        <div class="field" id="<?= $prefix ?>-area-zoom-field"<?= ($e['area_id'] ?? null) === null ? ' hidden' : '' ?>>
            <label for="<?= $prefix ?>-area-zoom"><?= h(t('Bis Zoomstufe')) ?></label>
            <select id="<?= $prefix ?>-area-zoom" name="area_zoom" class="excursion-area-zoom"
                    data-prefix="<?= h($prefix) ?>">
                <?php
                /* 18 IS THE RECOMMENDATION FOR AN EXCURSION
                   (24.08.2026, after the measurement): each further level
                   quadruples, and the topmost one alone is three quarters
                   of the whole — measured on his area, 18 costs 4.802
                   tiles where 19 costs 18.608. Single trees are already
                   distinguishable at 18; the difference to 19 buys little
                   in the woods and much on other people's data plans.
                   One's own download in the Offline tab keeps 19: there
                   somebody decides for their own phone. */
                foreach ([15 => t('15 — Überblick'), 16 => '16', 17 => '17',
                          18 => t('18 — empfohlen'), 19 => t('19 — sehr fein')] as $z => $label): ?>
                    <option value="<?= $z ?>" <?= (int) ($e['area_zoom'] ?? 18) === $z ? 'selected' : '' ?>>
                        <?= h($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="hint-text small" id="<?= $prefix ?>-area-size">…</p>
        </div>
        <input type="hidden" name="area_tiles" id="<?= $prefix ?>-area-tiles"
               value="<?= (int) ($e['area_tiles'] ?? 0) ?>">
        <input type="hidden" name="area_bytes" id="<?= $prefix ?>-area-bytes"
               value="<?= (int) ($e['area_bytes'] ?? 0) ?>">
    <?php endif;
}

/**
 * The co-leader section of the edit window — main leader only. Its own
 * forms BESIDE the save form, not inside it: appointing and removing are
 * deeds of their own, and nested forms do not exist in HTML anyway.
 */
function excursion_leaders_section(PDO $pdo, int $userId, array $e, string $target): void
{
    ?>
    <div class="column-header"><h3><?= h(t('Weitere Leiter')) ?></h3></div>
    <?php if (!excursion_leaders_ready($pdo)): ?>
        <p class="hint-text"><?= h(t('Mitleiter brauchen die Migration 0069 — sie ist noch nicht eingespielt.')) ?></p>
        <?php return; ?>
    <?php endif; ?>
    <p class="hint-text small">
        <?= h(t('Mitleiter dürfen die Exkursion bearbeiten und den QR-Code zeigen. Löschen und Leiter verwalten bleibt bei dir.')) ?>
    </p>
    <?php
    $leaders = excursion_leaders($pdo, (int) $e['id']);
    $taken   = array_map(static fn(array $l): int => (int) $l['id'], $leaders);
    foreach ($leaders as $l):
    ?>
        <div class="excursion-row">
            <span><?= h($l['display_name']) ?></span>
            <form method="post" action="<?= h($target) ?>"
                  data-confirm="<?= h(t('%s ist dann kein Mitleiter mehr — bearbeiten und den QR-Code zeigen kann nur noch, wer Leiter ist. Aufnehmen geht jederzeit wieder.', $l['display_name'])) ?>"
                  data-confirm-title="<?= h(t('Mitleiter entfernen?')) ?>"
                  data-confirm-button="<?= h(t('Entfernen')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="excursions">
                <input type="hidden" name="action" value="leader_remove">
                <input type="hidden" name="excursion" value="<?= (int) $e['id'] ?>">
                <input type="hidden" name="who" value="<?= (int) $l['id'] ?>">
                <button class="symbol-button delete" type="submit" title="<?= h(t('Mitleiter entfernen')) ?>"
                        aria-label="<?= h(t('%s als Mitleiter entfernen', $l['display_name'])) ?>"><?= icon('delete') ?></button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php
    // Candidates: my friends who are not co-leaders yet — the same door
    // through which containers admit people. The query sits in
    // excursions.php since the creation window asks the same question.
    $candidates = excursion_leader_candidates($pdo, $userId, $taken);
    $friendIds  = friend_ids($pdo, $userId);
    if ($candidates === []): ?>
        <?php if ($leaders === []): ?>
            <p class="hint-text small"><?= h($friendIds === []
                ? t('Mitleiter nimmst du aus deinen Freunden auf — du hast noch keine.')
                : t('Mitleiter nimmst du aus deinen Freunden auf — alle sind schon dabei.')) ?></p>
        <?php endif; ?>
    <?php else: ?>
        <form method="post" action="<?= h($target) ?>" class="button-row excursion-leader-add">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="excursions">
            <input type="hidden" name="action" value="leader_add">
            <input type="hidden" name="excursion" value="<?= (int) $e['id'] ?>">
            <select name="who" aria-label="<?= h(t('Mitleiter wählen')) ?>">
                <?php foreach ($candidates as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"><?= h($u['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button small"><?= h(t('Hinzufügen')) ?></button>
        </form>
    <?php endif;
}

/**
 * The co-leader offer in the CREATION window.
 *
 * 11.09.2026: „Wenn man eine Exkursion anlegt, soll man gleich
 * die Möglichkeit bekommen, weitere Leiter hinzuzufügen, so wie es dann
 * das Bearbeiten-Fenster macht." Whoever plans an outing knows at that
 * moment who is leading it with him — and had to save, find the
 * excursion again and open the pencil to say so.
 *
 * CHECKMARKS AND NOT THE CHOOSER FROM THE EDIT WINDOW, and the difference
 * is not carelessness: over there each appointment is its own deed on an
 * excursion that EXISTS — one chooser, one button, one row added. Here
 * nothing exists yet, so the names cannot be their own forms; they ride
 * along in the one form that creates the excursion. Checkmarks also
 * answer the case that brings this about — two or three friends at once
 * — without three round trips.
 *
 * NOTHING WITHOUT THE TABLE. Without migration 0069 the section stays
 * away entirely instead of explaining itself: the creation window is not
 * the place to learn what is missing on the server, and the edit window
 * says it where it belongs (excursion_leaders_section).
 */
function excursion_leaders_new_section(PDO $pdo, int $userId): void
{
    if (!excursion_leaders_ready($pdo)) {
        return;
    }
    $candidates = excursion_leader_candidates($pdo, $userId);
    ?>
    <div class="column-header"><h3><?= h(t('Weitere Leiter')) ?></h3></div>
    <p class="hint-text small">
        <?= h(t('Mitleiter dürfen die Exkursion bearbeiten und den QR-Code zeigen. Löschen und Leiter verwalten bleibt bei dir.')) ?>
    </p>
    <?php if ($candidates === []): ?>
        <p class="hint-text small">
            <?= h(t('Mitleiter nimmst du aus deinen Freunden auf — du hast noch keine.')) ?>
        </p>
    <?php else: ?>
        <?php foreach ($candidates as $u): ?>
            <label class="checkbox">
                <input type="checkbox" name="leaders[]" value="<?= (int) $u['id'] ?>">
                <span><?= h($u['display_name']) ?></span>
            </label>
        <?php endforeach; ?>
        <p class="hint-text small">
            <?= h(t('Später kommt jeder Weitere über den Stift dazu.')) ?>
        </p>
    <?php endif;
}

/** The view. $result comes from excursions_process(). */
function excursions_render(PDO $pdo, int $userId, array $result): void
{
    $target = url('pages/administration.php') . '#excursions';

    notice('error', $result['error'], true);
    if ($result['message'] !== null) {
        notice('success', $result['message'], true);
    }

    if (!excursions_ready($pdo)) {
        // A state, not a deed — it stays in the flow until somebody acts.
        notice('hint', t('Exkursionen brauchen die Migration 0068 — sie ist noch nicht eingespielt.'));
        return;
    }

    $mine = excursions_mine($pdo, $userId);
    ?>

<p class="hint-text">
    <?= h(t('Eine Exkursion sammelt Funde vieler Leute in einem Zeitfenster — auch von Gästen ohne Konto: Sie scannen den QR-Code, geben ihren Namen an und setzen los. Du bist Leiter und darfst hinterher alles bearbeiten, was auf ihr entstanden ist.')) ?>
</p>

<div class="column-header">
    <h3><?= h(t('Eigene Exkursionen')) ?></h3>
    <button type="button" class="button small" id="excursion-plus"
            title="<?= h(t('Neue Exkursion')) ?>" aria-label="<?= h(t('Neue Exkursion')) ?>"><?= icon('plus') ?><?= h(t('Neu')) ?></button>
</div>

<?php if ($mine === []): ?>
    <p class="hint-text"><?= h(t('Noch keine angelegt. Über den Knopf „Neu“ legst du eine an.')) ?></p>
<?php endif; ?>

<?php foreach ($mine as $e): ?>
    <?php
        $phase  = excursion_phase($e);
        $isMain = !empty($e['is_main']);
    ?>
    <div class="excursion-row">
        <div class="excursion-summary">
            <strong><?= h($e['name']) ?></strong>
            <span class="item-data">
                <?= icon('calendar') ?> <?= h(excursion_readable_time($e['starts_at'])) ?>
                <?= h(t('bis')) ?> <?= h(excursion_readable_time($e['ends_at'])) ?>
                · <?= $phase === 'läuft' ? '<strong>' . h(t('läuft')) . '</strong>' : h(t($phase)) ?>
                · <?= empty($e['photos_allowed'])
                        ? h(t('keine Fotos'))
                        : ($e['photo_limit'] !== null
                            ? h(t('Fotos, je Teilnehmer höchstens %d', (int) $e['photo_limit']))
                            : h(t('Fotos'))) ?>
                <?php if (!$isMain): ?>
                    · <?= h(t('Leiter: %s', (string) $e['leader_name'])) ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="excursion-tools">
            <?php if ($phase === 'läuft'): ?>
                <?php /* THE LEADER MAPS ALONG (24.08.2026), and so
                     does every co-leader: he is on the same outing, and
                     his finds — with their photos — belong to it like
                     everyone else's. It worked before only by opening
                     one's OWN join link, which is a detour nobody finds.
                     Only while the excursion runs: outside the window
                     the reference would be refused anyway, and a button
                     that cannot work is worse than none. */ ?>
                <form method="post" action="<?= h($target) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="excursions">
                    <input type="hidden" name="action" value="map_along">
                    <input type="hidden" name="excursion" value="<?= (int) $e['id'] ?>">
                    <button class="symbol-button" type="submit" title="<?= h(t('Selbst für diese Exkursion kartieren')) ?>"
                            aria-label="<?= h(t('Selbst für %s kartieren', $e['name'])) ?>"><?= icon('crosshair') ?></button>
                </form>
            <?php endif; ?>
            <?php /* Every leader's phone shows the same code — the token
                 belongs to the excursion, not to a device. Text instead
                 of a drawing: no icon says "QR" better than the word. */ ?>
            <button type="button" class="symbol-button excursion-qr"
                    data-window="excursion-qr-<?= (int) $e['id'] ?>"
                    title="<?= h(t('QR-Code für Gäste zeigen')) ?>"
                    aria-label="<?= h(t('QR-Code von %s zeigen', $e['name'])) ?>">QR</button>
            <button type="button" class="symbol-button excursion-edit"
                    data-window="excursion-edit-<?= (int) $e['id'] ?>"
                    title="<?= h(t('Exkursion bearbeiten')) ?>"
                    aria-label="<?= h(t('Exkursion %s bearbeiten', $e['name'])) ?>"><?= icon('pencil') ?></button>
            <?php if ($isMain): ?>
                <form method="post" action="<?= h($target) ?>"
                      data-confirm="<?= h(t('Die Funde der Gäste werden endgültig gelöscht, samt Fotos. Funde von angemeldeten Teilnehmern und Leitern bleiben bei ihren Besitzern und verlieren nur den Bezug zur Exkursion „%s“.', $e['name'])) ?>"
                      data-confirm-title="<?= h(t('Exkursion löschen?')) ?>"
                      data-confirm-button="<?= h(t('Löschen')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="excursions">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="excursion" value="<?= (int) $e['id'] ?>">
                    <button class="symbol-button delete" type="submit" title="<?= h(t('Exkursion löschen')) ?>"
                            aria-label="<?= h(t('Exkursion %s löschen', $e['name'])) ?>"><?= icon('delete') ?></button>
                </form>
            <?php else: ?>
                <?php /* The DOOR, not the bucket: whoever steps out leaves an
                     excursion that carries on without him. Red anyway —
                     only the main leader could ask him back in. */ ?>
                <form method="post" action="<?= h($target) ?>"
                      data-confirm="<?= h(t('Du trittst als Mitleiter von „%s“ aus — bearbeiten und den QR-Code zeigen kann dann nur noch der Leiter. Zurück holt dich nur er.', $e['name'])) ?>"
                      data-confirm-title="<?= h(t('Als Mitleiter austreten?')) ?>"
                      data-confirm-button="<?= h(t('Austreten')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="excursions">
                    <input type="hidden" name="action" value="leader_remove">
                    <input type="hidden" name="excursion" value="<?= (int) $e['id'] ?>">
                    <input type="hidden" name="who" value="<?= $userId ?>">
                    <button class="symbol-button delete" type="submit" title="<?= h(t('Als Mitleiter austreten')) ?>"
                            aria-label="<?= h(t('Als Mitleiter von %s austreten', $e['name'])) ?>"><?= icon('sign-out') ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php /* SAVING IS THE LAST THING IN THE WINDOW (24.08.2026:
         „unten wird gespeichert, nicht mittendrin"). The co-leader
         section used to stand BELOW the save button, so one saved in the
         middle and went on arranging afterwards — exactly the confusion
         the rule forbids. The button now lives in the footer and reaches
         its form from outside via form="…", because the section carries
         forms of its own and HTML knows no nested ones. */ ?>
    <dialog id="excursion-edit-<?= (int) $e['id'] ?>" class="form-window pinned-footer">
        <div class="window-body">
            <h2><?= h(t('Exkursion bearbeiten')) ?></h2>
            <form method="post" action="<?= h($target) ?>" id="excursion-form-<?= (int) $e['id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="excursions">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="excursion" value="<?= (int) $e['id'] ?>">
                <?php excursion_form_fields($e, 'excursion-' . (int) $e['id']) ?>
            </form>
            <?php if ($isMain) excursion_leaders_section($pdo, $userId, $e, $target); ?>
            <div class="dialog-footer">
                <button type="button" class="button subtle excursion-cancel"><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button subtle" data-quiet-until-changed
                        form="excursion-form-<?= (int) $e['id'] ?>"><?= icon('save') ?><?= h(t('Speichern')) ?></button>
            </div>
        </div>
    </dialog>

    <?php
        // The join address, absolute: a QR code travels onto other
        // people's phones, a path without a host would lead nowhere.
        $joinUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . url('pages/excursion.php') . '?code=' . $e['join_token'];
    ?>
    <dialog id="excursion-qr-<?= (int) $e['id'] ?>" class="form-window is-wide">
        <?php /* .window-body instead of a form: nothing here submits, but the
             window styles hang on the form — see app.css. On the phone it
             carries the scrolling and pins the footer to the bottom. */ ?>
        <div class="window-body">
        <h2><?= h($e['name']) ?></h2>
        <p class="hint-text small">
            <?php /* Since the door opens ahead of the window (13.09.2026)
                     the sentence says so: the link may go out by mail
                     before the day, the map can be loaded then, the finds
                     wait for the window. */ ?>
            <?= h(t('Wer diesen Code scannt oder den Link öffnet, kann ohne Konto mitmachen — schon vorher, um sich die Karte aufs Handy zu laden. Funde gehen im Zeitfenster vom %s bis %s; danach führt der Link nur noch auf eine Abschlussseite.',
                excursion_readable_time($e['starts_at']), excursion_readable_time($e['ends_at']))) ?>
        </p>
        <?php /* Drawn on opening (administration.js): the library is fetched
             lazily and only once — and if it is missing on the server,
             the link below does the same job in words. */ ?>
        <div class="excursion-qr-code" data-link="<?= h($joinUrl) ?>"
             data-lib="<?= h(url('assets/vendor/qrcode/qrcode.js')) ?>"></div>
        <?php /* THE LINK WITH A COPY BUTTON (13.09.2026: „mach mir
             bei der QR-Anzeige hinter dem Link einen Kopierknopf"), since
             the door opens ahead of the window and the link goes out by
             mail before the day. The same row as the invitation's
             (invitations_content.php): the field takes the width and can
             be selected by hand where the clipboard is out of reach, the
             button stands beside it at the field's height. */ ?>
        <div class="field">
            <label for="excursion-link-<?= (int) $e['id'] ?>"><?= h(t('Link zum Mitmachen')) ?></label>
            <div class="copy-row">
                <input type="text" id="excursion-link-<?= (int) $e['id'] ?>" readonly value="<?= h($joinUrl) ?>">
                <button type="button" class="button" data-copy="#excursion-link-<?= (int) $e['id'] ?>"><?= icon('copy') ?><?= h(t('Link kopieren')) ?></button>
            </div>
        </div>
        <div class="dialog-footer">
            <button type="button" class="button subtle excursion-cancel"><?= h(t('Schließen')) ?></button>
        </div>
        </div>
    </dialog>
<?php endforeach; ?>

<dialog id="excursion-new-window" class="form-window pinned-footer">
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="excursions">
        <input type="hidden" name="action" value="create">
        <h2><?= h(t('Neue Exkursion')) ?></h2>
        <?php excursion_form_fields(null, 'excursion-new') ?>
        <?php /* BEFORE THE FOOTER, because saving is the last thing in a
             window (CLAUDE.md). Unlike in the edit window this section
             carries no forms of its own — the checkmarks belong to THIS
             form and travel with the creation. */ ?>
        <?php excursion_leaders_new_section($pdo, $userId) ?>
        <div class="dialog-footer">
            <button type="button" class="button subtle excursion-cancel"><?= h(t('Abbrechen')) ?></button>
            <button type="submit" class="button"><?= h(t('Anlegen')) ?></button>
        </div>
    </form>
</dialog>
    <?php
}
