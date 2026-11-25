<?php
/**
 * The guest page of an excursion — what the QR code opens.
 *
 * NO ACCOUNT, NO LOGIN (the concept of 23.08.2026): the QR code replaces
 * both. Whoever scans it gives a name (it fills the finder) and, if they
 * want the finds attributed to them later, an e-mail — withholding it is
 * allowed and has exactly that nameable consequence. The guest lives in
 * the PHP session; the finds land in the one guest account with the
 * excursion's reference on them (excursion_point_add).
 *
 * THE WINDOW'S END GATES THE DOOR, THE WINDOW ITSELF GATES THE FINDS.
 * Until 13.09.2026 the door opened only with the window; then an
 * excursion began at a car park where most phones had no reception, and
 * the maintainer asked: „den Link kann man doch auch vorher verschicken, und
 * man kann sich dann vorher die Karte runterladen?" Now the door opens
 * ahead of the start — whoever joins early walks the onboarding and
 * loads the leader's area at home — and the finds still wait for the
 * window: the server checks the time AGAIN at every save
 * (excursion_point_add), because a page can stay open past midnight and
 * a guest can arrive before nine. After the end the page says it is
 * over. No revocation step exists or is needed; the token simply stops
 * working along with the window.
 *
 * A STALE QR IS THE NORMAL CASE at this door, not a fault: a photo of
 * the code survives the excursion. Hence a friendly page instead of a
 * 404 for every unknown token.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/excursions.php';

$token = (string) ($_GET['code'] ?? $_POST['code'] ?? '');
$e = excursions_ready($pdo) ? excursion_by_token($pdo, $token) : null;

/** The guest of THIS excursion, from the session — null before joining. */
$guest = $e === null ? null : ($_SESSION['excursion_guest'][(int) $e['id']] ?? null);

/* WHOEVER IS LOGGED IN NEEDS NO FORM (24.08.2026): name and
   e-mail stand in his account, and on the map he stays himself — his
   finds go into his OWN stock and merely carry the excursion's
   reference (the concept: „als Verweis, nicht als Kopie"). What he
   needs is one tap of consent, not a second identity. */
$user = user_current();

$error = [];
if ($e !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        switch ((string) ($_POST['action'] ?? '')) {
            /* The registered participant: one tap, then the map — with
               the excursion's badge on it (assets/js/map.js reads the
               session key below). No guest actor: he acts as himself,
               and the API serves his account as ever. */
            case 'join_as_user':
                if ($user === null) {
                    throw new InvalidArgumentException(t('Dafür musst du angemeldet sein.'));
                }
                if (!excursion_joinable($e)) {
                    throw new InvalidArgumentException(t('Diese Exkursion ist beendet.'));
                }
                if (!excursion_guests_ready($pdo)) {
                    throw new InvalidArgumentException(t('Der Zugang braucht die Migration 0074.'));
                }
                excursion_join_as_user($pdo, $e, $user);
                $_SESSION['excursion_mapping'] = (int) $e['id'];
                /* AND THE MAP OFFER, where the leader laid one down
                   (09.09.2026). The guest passes it as the
                   onboarding's third step; the registered participant
                   went straight through to the map and never saw it —
                   although he stands at the same car park with the same
                   reception, and in an hour there will be none.
                   The TWO PERMISSION STEPS are not repeated for him:
                   he has an account and walked them when he made it, so
                   the offer stands alone (onboarding.php). */
                header('Location: ' . url(excursion_area($pdo, $e) !== null
                    ? 'pages/onboarding.php?step=maps'
                    : 'index.php'));
                exit;

            case 'join':
                if (!excursion_joinable($e)) {
                    throw new InvalidArgumentException(t('Diese Exkursion ist beendet.'));
                }
                if (!excursion_guests_ready($pdo)) {
                    throw new InvalidArgumentException(t('Der Gastzugang braucht die Migration 0074 — sie ist noch nicht eingespielt.'));
                }
                $firstName = mb_substr(trim((string) ($_POST['first_name'] ?? '')), 0, 100);
                $lastName  = mb_substr(trim((string) ($_POST['last_name'] ?? '')), 0, 100);
                // A returning guest continues only with this browser's proof.
                $guestId = excursion_guest_register($pdo, (int) $e['id'],
                    $firstName, $lastName, (string) ($_POST['email'] ?? ''),
                    excursion_guest_proof((int) $e['id']));
                excursion_guest_proof_set((int) $e['id'], $guestId);
                $_SESSION['excursion_guest'][(int) $e['id']] = [
                    'id' => $guestId, 'name' => trim($firstName . ' ' . $lastName),
                ];
                // And as the ACTOR for the main app's API (stage 3): who
                // joined here may act over there — on this excursion, as
                // long as its window is open (excursion_actor).
                $_SESSION['excursion_actor'] = [
                    'excursion_id' => (int) $e['id'], 'guest_id' => $guestId,
                ];
                // FIRST THE ONBOARDING (24.08.2026): location
                // and storage, each explained on its own page — the same
                // path a freshly registered person walks (onboarding.php).
                header('Location: ' . url('pages/onboarding.php'));
                exit;
        }
    } catch (InvalidArgumentException $ex) {
        $error[] = error_for_user($ex);
    }
}

// The flash from the redirect above — read once, then gone: a toast that
// reappeared on every reload would report a deed nobody just did.
$flash = $_SESSION['excursion_flash'] ?? null;
unset($_SESSION['excursion_flash']);

// WHO IS ALREADY IN goes straight to the map (stage 4): the main app is
// the guest's workplace — full screen, location, offline queue. This
// page remains only the door, and the door does not repeat itself.
// (The onboarding right after joining lives on its own page and is
// reached by redirect, so it does not need an exception here.)
if ($e !== null && $guest !== null && $error === []
    && excursion_joinable($e) && excursion_guests_ready($pdo)) {
    header('Location: ' . url('index.php'));
    exit;
}

page_header(t('Exkursion'));
notice('error', $error, true);
if ($flash !== null) {
    notice('success', $flash, true);
}

?>

<?php if ($e === null): ?>
    <h1><?= h(t('Exkursion')) ?></h1>
    <p class="hint-text">
        <?= h(t('Dieser Zugang führt zu keiner Exkursion. Vielleicht ist der QR-Code veraltet — frag die Person, die ihn dir gezeigt hat.')) ?>
    </p>
<?php else: ?>
    <?php $phase = excursion_phase($e); ?>
    <h1><?= h($e['name']) ?></h1>
    <?php if (($e['description'] ?? '') !== '' && $e['description'] !== null): ?>
        <?php /* The leader's invitation — the reason this page is worth
             opening before the day: where it goes, what to expect. */ ?>
        <p class="excursion-description"><?= nl2br(h((string) $e['description'])) ?></p>
    <?php endif; ?>

    <?php
    /* AHEAD OF THE WINDOW THE DOOR IS OPEN TOO (13.09.2026, see the head
       of this file): the same form and the same button, only the sentence
       above them says when the finds begin — and, where the leader laid
       an area down, that the map can be loaded now, at home, with
       reception. $when is that sentence; the running one names the end
       instead. */
    /* AND ON WHICH DEVICE (13.09.2026: „wenn ich das per Mail
       versende, wird man vermutlich zuerst auf dem Desktop-PC den Link
       öffnen — und die Offlinekarte muss ja aufs Handy"). The link
       travels by mail, mail is read at the desk, and a map loaded at
       the desk stays at the desk: it lives in the browser that fetched
       it. Said outright, before anyone loads eleven megabytes onto the
       wrong machine. */
    $when = $phase === 'geplant'
        ? (excursion_area($pdo, $e) !== null
            ? t('Beginnt am %s. Mach jetzt schon mit — dann kannst du dir die Karte des Gebiets aufs Handy laden, solange du Empfang hast. Funde eintragen geht ab dem Start.',
                excursion_readable_time($e['starts_at']))
              . ' ' . t('Öffne diesen Link dafür auf dem Handy, das du im Gelände dabeihast: Die Karte bleibt auf dem Gerät, auf dem du sie lädst.')
            : t('Beginnt am %s. Mach jetzt schon mit — Funde eintragen geht ab dem Start.',
                excursion_readable_time($e['starts_at'])))
        : t('Läuft bis %s.', excursion_readable_time($e['ends_at']));
    ?>
    <?php if ($phase === 'beendet'): ?>
        <p class="hint-text">
            <?= h(t('Diese Exkursion ist beendet — seit dem %s können keine Funde mehr dazukommen. Danke fürs Mitmachen!',
                excursion_readable_time($e['ends_at']))) ?>
        </p>
        <?php
        /* THE SPECIES LIST ON THE CLOSING PAGE (13.09.2026): what
           the day brought, for everyone who holds the link — the species,
           the German name where a list has one, the Red List category.
           Nobody's name, no place at all („Lass das MTB ganz weg"):
           excursion_species_list() selects only these columns, and the
           legend names only the categories that occur. */
        $species = excursion_species_list($pdo, $e);
        $finds   = array_sum(array_column($species, 'finds'));
        $legend  = [];
        foreach ($species as $row) {
            if ($row['rl'] !== '' && isset(RED_LIST_NAMES[$row['rl']])) {
                $legend[$row['rl']] = RED_LIST_NAMES[$row['rl']];
            }
        }
        uksort($legend, static fn(string $a, string $b): int =>
            array_search($a, RED_LIST_CATEGORIES, true) <=> array_search($b, RED_LIST_CATEGORIES, true));
        ?>
        <?php $spots = $species === [] ? [] : excursion_find_spots($pdo, $e); ?>
        <?php if ($spots !== []): ?>
        <?php /* THE PICTURE OF THE DAY ABOVE THE LIST (13.09.2026):
             the same painted map the find report carries — tiles and the
             finds as dots, framed once and never moved or zoomed
             (assets/js/staticmap.js). Twice the pixels of its box, so a
             phone's screen sees real detail and not an enlargement; the
             zoom cap is the map's own „no closer than 15" — a level
             coarser than the report, which prints at twice the density.
             The space is reserved by the canvas's own size, so the page
             does not jump when the tiles arrive. */ ?>
        <canvas class="excursion-map" width="1280" height="640"
                data-points="<?= h(json_encode($spots)) ?>"
                data-pad="48" data-max-zoom="15" data-radius="11" data-font="24"
                aria-label="<?= h(t('Karte der Funde dieser Exkursion')) ?>"></canvas>
        <?php endif; ?>
        <?php if ($species !== []): ?>
        <h2 class="excursion-species-heading">
            <?= h(count($species) === 1
                ? t('Artenliste — eine Art aus %d Funden', $finds)
                : t('Artenliste — %d Arten aus %d Funden', count($species), $finds)) ?>
        </h2>
        <?php /* A striped list, not a table: a table.list folds into labelled
             cards on the phone, and „RL:" with nothing behind it on forty
             cards is noise. One row per species — the name with its German
             one underneath, the sheet and the category at the row's end. */ ?>
        <ul class="excursion-species">
            <?php foreach ($species as $row): ?>
                <li class="excursion-species-row">
                    <span class="excursion-species-name">
                        <i><?= h($row['name']) ?></i>
                        <?php if ($row['german'] !== ''): ?>
                            <span class="item-data"><?= h($row['german']) ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if ($row['rl'] !== ''): ?>
                        <span class="excursion-species-facts item-data"
                              title="<?= h(t(RED_LIST_NAMES[$row['rl']] ?? '')) ?>"><?= h(t('RL %s', $row['rl'])) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($legend !== []): ?>
        <p class="hint-text small">
            <?= h(t('RL: Rote Liste Deutschlands — %s.', implode(', ', array_map(
                static fn(string $k, string $v): string => $k . ' = ' . t($v), array_keys($legend), $legend)))) ?>
        </p>
        <?php endif; ?>
        <?php endif; ?>
    <?php elseif (!excursion_guests_ready($pdo)): ?>
        <?php notice('hint', t('Der Gastzugang braucht die Migration 0074 — sie ist noch nicht eingespielt.')); ?>
    <?php elseif ($user !== null): ?>
        <?php /* SIGNED IN: no form, no second identity. One tap, then the
             map — the finds stay in one's own stock and carry only the
             link to the excursion. */ ?>
        <p class="hint-text">
            <?= h($when) ?>
            <?= t('Du bist angemeldet als %s — deine Funde bleiben in deinem eigenen Bestand, die Exkursionsleitung darf sie für den Fundbericht bearbeiten.',
                '<strong>' . h(user_display_name($user)) . '</strong>') ?>
        </p>
        <form method="post" action="<?= h(url('pages/excursion.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="code" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="join_as_user">
            <div class="button-row right">
                <button type="submit" class="button"><?= h(t('Mitmachen')) ?></button>
            </div>
        </form>
        <p class="excursion-fineprint">
            <?= h(t('Fotos, die du zu dieser Exkursion hochlädst, dürfen von der Exkursionsleitung frei verwendet und weitergegeben werden — etwa für Fundberichte und Belege.')) ?>
        </p>
    <?php elseif ($guest === null): ?>
        <p class="hint-text">
            <?= h($when) ?>
            <?= h(t('Gib deinen Namen an — er steht als Finder an deinen Funden.')) ?>
        </p>
        <form method="post" action="<?= h(url('pages/excursion.php')) ?>" class="excursion-join">
            <?= csrf_field() ?>
            <input type="hidden" name="code" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="join">
            <?php /* Separately, both required (24.08.2026): a
                 report sorts by last name, and one field can never be
                 split reliably again. The star is the whole legend —
                 with two of three fields required, marking the optional
                 one alone would be the riddle. */ ?>
            <div class="field">
                <label for="guest-first-name"><?= h(t('Vorname *')) ?></label>
                <input type="text" id="guest-first-name" name="first_name" required maxlength="100"
                       autocomplete="given-name">
            </div>
            <div class="field">
                <label for="guest-last-name"><?= h(t('Nachname *')) ?></label>
                <input type="text" id="guest-last-name" name="last_name" required maxlength="100"
                       autocomplete="family-name">
            </div>
            <div class="field">
                <label for="guest-email"><?= h(t('E-Mail (freiwillig)')) ?></label>
                <input type="email" id="guest-email" name="email" maxlength="255"
                       autocomplete="email">
                <?php /* The consequence stands BESIDE the choice, not in fine
                     print afterwards: nobody is forced, and the waiver
                     has a nameable price (the concept's wording). */ ?>
                <p class="hint-text small">
                    <?= h(t('Mit E-Mail können dir deine Funde später zugeschrieben werden, wenn du irgendwann ein Konto anlegst. Ohne geht es auch — dann bleibt es beim Namen.')) ?>
                </p>
            </div>
            <div class="button-row right">
                <button type="submit" class="button"><?= h(t('Mitmachen')) ?></button>
            </div>
        </form>

        <?php /* The fine print, at the very bottom in smaller type
             (24.08.2026): what joining stores and grants. Two
             sentences, no legalese — the storage (name, e-mail, tied to
             the finds as finder, permanently) and the photo clause (the
             leader may use and pass on guest photos without hunting
             permissions later). One consent for both, at the deed. */ ?>
        <p class="excursion-fineprint">
            <?= h(t('Vor- und Nachname — und, falls angegeben, die E-Mail-Adresse — werden bei uns gespeichert und dauerhaft als Finder mit deinen Funden verbunden. Fotos, die du hier hochlädst, dürfen von der Exkursionsleitung frei verwendet und weitergegeben werden, etwa für Fundberichte und Belege. Mit dem Mitmachen erklärst du dich mit beidem einverstanden.')) ?>
        </p>
        <?php /* And where the whole of it stands: a guest gives a name here
             and has no account whose settings could show the link
             (pages/privacy.php, 20.11.2026). */ ?>
        <p class="excursion-fineprint">
            <a href="<?= h(url('pages/privacy.php')) ?>"><?= h(t('Datenschutzerklärung')) ?></a>
        </p>
    <?php endif; ?>
<?php endif; ?>

<?php page_footer(['scripts' => ['assets/js/staticmap.js']]); ?>
