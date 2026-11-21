<?php
/**
 * Cloud — processing and view (Mein Bereich → Cloud).
 *
 * Each user's own storage space for everything belonging to a find, in full
 * resolution: voucher photos from the phone as originals, micrographs,
 * stacked camera shots, raw files. Once a storage is connected, even the
 * shrunken voucher photo moves on and the server keeps NOTHING of the
 * picture (photo_cloud.php) — why is laid out at length in
 * includes/cloud.php.
 *
 * AS OF 15.08.2026: THE SCAFFOLDING, NO PROVIDER. The call was
 * explicitly "only the scaffolding, providers later". So the tab is there,
 * the table too, the provider list honestly shows "noch nicht angebunden" —
 * and there is deliberately NO button that looks as if it did something.
 * A connect button that only throws an error message is worse than none at
 * all: you try it three times before you believe it is not your own fault.
 *
 * What already stands here nevertheless is the target folder. It is the
 * only piece of information independent of the provider — and the only one
 * you want to think about beforehand.
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';
require_once __DIR__ . '/backup.php';
require_once __DIR__ . '/cloud_moves.php';

/**
 * Off to the provider's consent page — for „Verbinden" and for the move's
 * „Weiter zu …" alike.
 *
 * THE BUTTON LEADS AWAY FROM HERE. It is a POST and not a link because it
 * changes the session; a link would be followed by every prefetching
 * browser.
 */
function cloud_consent_redirect(string $provider): never
{
    $_SESSION['cloud_state'] = bin2hex(random_bytes(16));
    /* WHO IS EXPECTED BACK. pCloud says its name on the return (the
       hostname parameter gives it away); Google's return is anonymous —
       code and state, nothing else. The session remembers whom we sent the
       user to, and the return page believes only that. */
    $_SESSION['cloud_provider'] = $provider;
    if ($provider === 'gdrive') {
        require_once __DIR__ . '/gdrive.php';
        redirect_to(gdrive_consent_url($_SESSION['cloud_state']));
    }
    if ($provider === 'onedrive') {
        require_once __DIR__ . '/onedrive.php';
        redirect_to(onedrive_consent_url($_SESSION['cloud_state']));
    }
    redirect_to(pcloud_consent_url($_SESSION['cloud_state']));
}

/**
 * Must run BEFORE any output. 'section' tells the management area's forms
 * apart from one another — see invitations_content.php.
 *
 * @return array{message: ?string, error: string[]}
 */
function cloud_process(PDO $pdo, int $userId): array
{
    $state = ['message' => null, 'error' => []];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'cloud') {
        return $state;
    }
    csrf_check();

    try {
        $provider = (string) ($_POST['provider'] ?? '');

        switch ((string) ($_POST['action'] ?? '')) {
            case 'disconnect':
                if (!cloud_provider_valid($provider)) {
                    throw new InvalidArgumentException(t('Unbekannter Anbieter.'));
                }
                /* NOT DURING A MOVE: cutting either cloud would leave
                   pictures stranded halfway. The tile hides „Trennen" then;
                   this says no to a replayed form. */
                if (cloud_move_open($pdo, $userId) !== null) {
                    throw new RuntimeException(t('Während eines Umzugs lässt sich keine Cloud trennen.'));
                }
                $state['message'] = cloud_disconnect($pdo, $userId, $provider)
                    ? t('Getrennt. Deine Dateien in der Cloud sind noch da.')
                    : t('Es ist keine Cloud verbunden.');
                break;

            // THE BUTTON LEADS AWAY FROM HERE. The consent happens at the
            // provider, so this branch does not render anything — it draws the
            // state, remembers it and leaves. It is a POST and not a link
            // because it changes the session; a link would be followed by
            // every prefetching browser.
            case 'connect':
                if (!cloud_provider_usable($provider)) {
                    throw new InvalidArgumentException(t('Dieser Anbieter ist hier nicht eingerichtet.'));
                }
                // This branch is the OAuth journey. A key provider (S3) has
                // its own form and its own action — a hand-crafted POST
                // naming it here would otherwise fall through to pCloud's
                // consent screen, of all places.
                if (CLOUD_PROVIDERS[$provider]['kind'] !== 'oauth') {
                    throw new InvalidArgumentException(t('Dieser Anbieter verbindet sich nicht über eine Zustimmungsseite.'));
                }
                if (!cloud_ready($pdo)) {
                    throw new RuntimeException(t('Die Tabelle für die Zugänge fehlt noch — Migration 0053 einspielen.'));
                }
                /* ONE STORAGE PER ACCOUNT (27.08.2026: „man kann
                   immer nur zu einem connected sein"). Enforced HERE and
                   not only hidden in the view: the view hides the other
                   cards, but a replayed form would connect past it — and
                   then the backup would face "into which?" every night. */
                foreach (cloud_connections($pdo, $userId) as $key => $c) {
                    if ($key !== $provider) {
                        throw new RuntimeException(t('%s ist schon verbunden. Willst du wechseln, nimm „In andere Cloud umziehen …".',
                            CLOUD_PROVIDERS[$key]['name']));
                    }
                }
                cloud_consent_redirect($provider);

            /* THE MOVE'S DOOR (26.09.2026) — the one way to another cloud
               while one is connected (includes/cloud_moves.php tells the
               switch). Nothing changes here yet: the session remembers
               where the pictures come from and where they go, and the
               switch happens only when the user comes back with the new
               cloud's consent (pages/cloud_return.php). Whoever turns
               back at the provider has changed nothing. */
            case 'move_start':
                if (!cloud_moves_ready($pdo)) {
                    throw new RuntimeException(t('Der Umzug braucht die Migration 0142 — sie ist noch nicht eingespielt.'));
                }
                $from = array_key_first(cloud_connections($pdo, $userId));
                if ($from === null) {
                    throw new RuntimeException(t('Es ist keine Cloud verbunden, aus der man umziehen könnte.'));
                }
                if (cloud_move_open($pdo, $userId) !== null) {
                    throw new RuntimeException(t('Es läuft schon ein Umzug.'));
                }
                if (!in_array($provider, cloud_move_targets((string) $from), true)) {
                    throw new InvalidArgumentException(t('In diese Cloud kann YASAIMAPP im Moment nicht umziehen.'));
                }
                $_SESSION['cloud_move'] = ['from' => (string) $from, 'to' => $provider];
                cloud_consent_redirect($provider);

            // „Nochmal versuchen" — the stuck ones start over, a waiting
            // move asks again at once.
            case 'move_retry':
                $n = cloud_move_retry($pdo, $userId);
                $state['message'] = match (true) {
                    $n === 0 => t('Der Umzug versucht es jetzt noch einmal.'),
                    $n === 1 => t('Ein Bild wird noch einmal versucht.'),
                    default  => t('%d Bilder werden noch einmal versucht.', $n),
                };
                break;

            // „Umzug so abschließen" — asked before (data-confirm), with
            // what stays behind.
            case 'move_close':
                $move = cloud_move_open($pdo, $userId);
                if ($move === null) {
                    throw new RuntimeException(t('Es läuft kein Umzug.'));
                }
                $files = cloud_move_progress($pdo, $move)['files'] > 0;
                $left = cloud_move_close($pdo, $userId);
                $state['message'] = $left === 0
                    ? ($files ? t('Umzug abgeschlossen. Alle Dateien liegen jetzt in %s.', cloud_move_name((string) $move['to_provider']))
                              : t('Umzug abgeschlossen. Alle Bilder liegen jetzt in %s.', cloud_move_name((string) $move['to_provider'])))
                    : ($files ? t('Umzug abgeschlossen. %d Dateien sind in %s geblieben.', $left, cloud_move_name((string) $move['from_provider']))
                              : t('Umzug abgeschlossen. %d Bilder sind in %s geblieben.', $left, cloud_move_name((string) $move['from_provider'])));
                break;

            // THE WHOLE SETUP THROUGH THE INTERFACE — no editing files over
            // SFTP. The maintainer asked for this expressly, and rightly: whoever
            // administers this installation sits in front of a browser, not
            // in front of a shell the hoster does not provide anyway.
            /* 'setup' — the installation's registration at a provider —
               lives in cloud_providers_content.php since 02.10.2026
               (Administration → Cloud-Anbieter). */

            // Does the connection still hold? Asked on demand, not on every
            // page view: it is a call to a foreign server, and the tab would
            // hang on it every time someone only wanted to change the folder.
            /* THE KEY PROVIDER'S "CONNECT" IS A FORM, NOT A JOURNEY: no
               consent screen exists, the user hands over the four values
               themselves. Everything is PROBED before it is stored —
               one listing exercises endpoint, region, bucket and both key
               halves at once, and a wrong value fails HERE, in front of
               the form, not at three in the morning in the backup. */
            case 's3_connect':
                if (!cloud_provider_usable('s3')) {
                    throw new InvalidArgumentException(t('Dieser Anbieter ist hier nicht eingerichtet.'));
                }
                if (!cloud_ready($pdo)) {
                    throw new RuntimeException(t('Die Tabelle für die Zugänge fehlt noch — Migration 0053 einspielen.'));
                }
                // One storage per account — the same rule, the same reason
                // as at the OAuth button above.
                foreach (cloud_connections($pdo, $userId) as $key => $c) {
                    if ($key !== 's3') {
                        throw new RuntimeException(t('%s ist schon verbunden. Willst du wechseln, nimm „In andere Cloud umziehen …".',
                            CLOUD_PROVIDERS[$key]['name']));
                    }
                }

                require_once __DIR__ . '/s3.php';
                $endpoint = s3_endpoint_clean((string) ($_POST['endpoint'] ?? ''));
                $region   = trim((string) ($_POST['region'] ?? ''));
                $bucket   = trim((string) ($_POST['bucket'] ?? ''), " \t/");
                $keyId    = trim((string) ($_POST['key_id'] ?? ''));
                $secret   = trim((string) ($_POST['key_secret'] ?? ''));
                if ($endpoint === '' || $region === '' || $bucket === ''
                    || $keyId === '' || $secret === '') {
                    throw new InvalidArgumentException(t('Bitte fülle alle fünf Felder aus.'));
                }

                $bundle = s3_bundle_parse(s3_bundle_pack($endpoint, $region, $bucket, $keyId, $secret));
                s3_check($bundle);

                cloud_connect($pdo, $userId, 's3', [
                    // What the card shows as "verbunden als": the bucket
                    // and where it lives — the keys stay invisible.
                    'account'    => mb_substr($bucket . ' · ' . $endpoint, 0, 190),
                    'region'     => $region,
                    'secret'     => s3_bundle_pack($endpoint, $region, $bucket, $keyId, $secret),
                    'expires_at' => null,
                    'folder'     => (string) (cloud_connections($pdo, $userId)['s3']['folder'] ?? ''),
                ]);

                $state['message'] = t('Verbunden. Der Eimer „%s" bei %s antwortet.',
                    $bucket, $endpoint);
                /* THE BROWSER MAY PUT INTO THE BUCKET only with a CORS rule
                   for our origin — set here when the key allows it, beside
                   any rules of the user's own (s3_cors_allow); otherwise
                   the message says what is missing (26.09.2026). */
                try {
                    s3_cors_allow($bundle, cloud_origin());
                } catch (RuntimeException $e) {
                    error_log('s3_cors_allow (' . $userId . '): ' . $e->getMessage());
                    $state['message'] .= ' ' . t('Für Bilder direkt aus dem Browser braucht der Eimer noch eine CORS-Regel für %s. Dein Schlüssel darf sie nicht anlegen. Lege sie bei deinem Anbieter an: Methoden PUT, GET und HEAD, alle Kopfzeilen.', cloud_origin());
                }
                break;

            case 'check':
                if ($provider === 's3') {
                    require_once __DIR__ . '/s3.php';
                    $bundle = s3_bundle_parse(cloud_secret($pdo, $userId, 's3'));
                    if ($bundle === null) {
                        throw new RuntimeException(t('Es ist keine Cloud verbunden.'));
                    }
                    s3_check($bundle);
                    $pdo->prepare('UPDATE map_cloud_accounts SET checked_at = NOW()
                                    WHERE user_id = ? AND provider = "s3"')->execute([$userId]);
                    $state['message'] = t('Die Verbindung klappt. Der Eimer „%s" bei %s antwortet.',
                        $bundle['bucket'], $bundle['endpoint']);
                    break;
                }
                if ($provider === 'gdrive' || $provider === 'onedrive') {
                    require_once __DIR__ . '/' . $provider . '.php';
                    /* Through the one gate: a check that also has to
                       refresh IS the check — a token nobody can renew is
                       a connection that does not hold. For OneDrive the
                       refresh is doubly the point: it rotates the stored
                       token and thereby restarts the ninety-day window. */
                    $access = $provider === 'gdrive'
                        ? gdrive_access($pdo, $userId)
                        : onedrive_access($pdo, $userId);
                    if ($access === null) {
                        throw new RuntimeException(t('Die Verbindung klappt nicht mehr. Trenne sie und verbinde dann neu.'));
                    }
                    $who = $provider === 'gdrive'
                        ? gdrive_userinfo($access)
                        : onedrive_userinfo($access);
                    if ($who['email'] !== '') {
                        $pdo->prepare('UPDATE map_cloud_accounts
                            SET account = ?, checked_at = NOW() WHERE user_id = ? AND provider = ?'
                        )->execute([mb_substr($who['email'], 0, 190), $userId, $provider]);
                    }
                    $state['message'] = t(
                        'Die Verbindung klappt. In deiner Cloud sind %s von %s belegt.',
                        cloud_size($who['used']),
                        $who['quota'] === null ? t('unbegrenzt') : cloud_size($who['quota'])
                    );
                    break;
                }

                $connection = cloud_connections($pdo, $userId)['pcloud'] ?? null;
                $token      = cloud_secret($pdo, $userId, 'pcloud');
                if ($connection === null || $token === null) {
                    throw new RuntimeException(t('Es ist keine Cloud verbunden.'));
                }
                $who = pcloud_userinfo(pcloud_host($connection['region'] ?? null), $token);
                cloud_connect($pdo, $userId, 'pcloud', [
                    'account'     => $who['email'],
                    'region'       => (string) ($connection['region'] ?? ''),
                    'secret' => $token,
                    'folder'    => (string) ($connection['folder'] ?? ''),
                ]);
                /* THE SAME SENTENCE AS AT DRIVE AND ONEDRIVE. Until
                   02.10.2026 the check also named the root the app sees,
                   its folder id and its first entries — a diagnosis from
                   setting the app up („All folders" versus „Private", see
                   cloud_root_id), and noise for everybody else.
                   „kann für alle weg, ich brauche das nicht". */
                $state['message'] = t('Die Verbindung klappt. In deiner Cloud sind %s von %s belegt.',
                    cloud_size($who['used']), cloud_size($who['quota']));
                break;

            /* The 'backup' switch case stood here for one day (0085…0087).
               28.08.2026: „Das ist ein Automatismus. Das heißt,
               habe ich eine Cloud, werden meine Daten dort auch gesichert.
               Ungefragt." A connected storage is the consent; the only
               off-switch is Trennen. */

            // The app's own folder at Drive and S3 gets a name of the user's
            // own, renamed where it lies (cloud_home_rename, 23.09.2026).
            case 'home_folder':
                $state['message'] = cloud_home_rename($pdo, $userId, (string) ($_POST['home_folder'] ?? ''));
                break;

            /* FULL SIZE OVER MOBILE DATA — the tick of the connected tile
               (below). Only this one key of the style bundle changes; the
               rest is read and written back as it stands. */
            case 'full_size_mobile':
                require_once __DIR__ . '/photos.php';
                // The tile shows no tick before the column (CLAUDE.md, code before migration).
                if (!column_exists($pdo, 'map_users', 'measure_style')) {
                    break;
                }
                $stmt = $pdo->prepare('SELECT measure_style FROM map_users WHERE id = ?');
                $stmt->execute([$userId]);
                $style = measure_style_read($stmt->fetchColumn() ?: null);
                $style['full_size_mobile'] = !empty($_POST['full_size_mobile']);
                $pdo->prepare('UPDATE map_users SET measure_style = ? WHERE id = ?')
                    ->execute([measure_style_write($style), $userId]);
                $state['message'] = t('Gespeichert. Auf der Karte gilt es, sobald du sie neu lädst.');
                break;

            // The same sweep the cron job runs, for this one account — so
            // whoever has just imported four hundred finds does not have to
            // wait for the night. See 'backup' in cronjobs/_jobs.php.
            case 'backup_now':
                backup_check($pdo, $userId);

                // TWENTY SECONDS, because a person is waiting. The cron job
                // gets a hundred; here the browser would sit on a white page
                // for as long as the budget lasts. Whatever does not fit
                // says so in the message and goes up in the night.
                $run = backup_run($pdo, $userId, microtime(true) + 20);

                if ($run['written'] === 0 && $run['open'] === 0 && $run['error'] === null) {
                    $state['message'] = t('Alles gesichert. Seit dem letzten Mal hat sich nichts geändert.');
                    break;
                }
                if ($run['error'] !== null) {
                    $state['error'][] = $run['error'];
                }
                if ($run['written'] > 0) {
                    // Both plural branches are whole sentences for t() —
                    // a glued-on 'e' could not be translated.
                    $tail = $run['open'] === 0 ? ''
                        : ' ' . ($run['open'] === 1
                            ? t('1 fehlt noch, der kommt heute Nacht von selbst.')
                            : t('%d fehlen noch, die kommen heute Nacht von selbst.', $run['open']));
                    $state['message'] = ($run['written'] === 1
                        ? t('%d Monat gesichert (%s).', $run['written'], bytes_readable($run['bytes']))
                        : t('%d Monate gesichert (%s).', $run['written'], bytes_readable($run['bytes'])))
                        . $tail;
                } elseif ($run['open'] > 0 && $run['error'] === null) {
                    $state['message'] = $run['open'] === 1
                        ? t('Ein Monat fehlt noch. Der kommt heute Nacht von selbst.')
                        : t('%d Monate fehlen noch. Die kommen heute Nacht von selbst.', $run['open']);
                }
                break;
        }
    } catch (InvalidArgumentException | RuntimeException $e) {
        $state['error'][] = $e->getMessage();
    }

    return $state;
}

/**
 * What of the user lies in a cloud, as one says it — „24 Belegfotos,
 * 17 Aufnahmen und 1 Eichbild". Null when nothing lies there.
 *
 * @param array $held from cloud_pictures_held()
 */
function cloud_pictures_list(array $held, bool $withCopies = false): ?string
{
    $parts = [];
    $n = (int) ($held['photo'] ?? 0);
    if ($n > 0) {
        $parts[] = $n === 1 ? t('1 Belegfoto') : t('%d Belegfotos', $n);
    }
    $n = (int) ($held['cloud'] ?? 0);
    if ($n > 0) {
        $parts[] = $n === 1 ? t('1 Aufnahme') : t('%d Aufnahmen', $n);
    }
    $n = (int) ($held['scale'] ?? 0);
    if ($n > 0) {
        $parts[] = $n === 1 ? t('1 Eichbild') : t('%d Eichbilder', $n);
    }
    $n = (int) ($held['copy'] ?? 0);
    if ($withCopies && $n > 0) {
        $parts[] = $n === 1 ? t('1 Kopie von gemeinsamen Funden') : t('%d Kopien von gemeinsamen Funden', $n);
    }
    // A find's other files (Belege → Dateien, 05.10.2026).
    $n = (int) ($held['file'] ?? 0);
    if ($n > 0) {
        $parts[] = $n === 1 ? t('1 weitere Datei') : t('%d weitere Dateien', $n);
    }
    if ($parts === []) {
        return null;
    }
    $last = array_pop($parts);
    return $parts === [] ? $last : t('%s und %s', implode(', ', $parts), $last);
}

/**
 * THE QUESTION BEFORE „TRENNEN", with numbers (26.09.2026): the old one
 * only said that nothing is deleted — true, and silent on what matters:
 * YASAIMAPP can no longer show what lies there. The copies of the others'
 * pictures are not counted: without them the pictures come from the
 * photographer's cloud (pages/cloud_thumb.php), so nothing of theirs goes
 * dark. Where a move is possible, the question points to it.
 */
function cloud_disconnect_question(string $name, array $held, bool $canMove): string
{
    $count = (int) ($held['photo'] ?? 0) + (int) ($held['cloud'] ?? 0) + (int) ($held['scale'] ?? 0)
           + (int) ($held['file'] ?? 0);
    if ($count === 0) {
        return t('Die Verbindung zu %s wird gelöst. Deine Dateien dort bleiben unberührt — es geht nur der Zugang verloren, und du kannst ihn jederzeit neu herstellen.', $name);
    }
    $text = $count === 1
        ? t('In %s liegt ein Bild von dir. Es bleibt dort liegen. Aber YASAIMAPP kann es nicht mehr zeigen, bis du %s wieder verbindest.', $name, $name)
        : t('In %s liegen %s von dir. Sie bleiben dort liegen. Aber YASAIMAPP kann sie nicht mehr zeigen, bis du %s wieder verbindest.',
            $name, (string) cloud_pictures_list($held), $name);
    if ($canMove) {
        $text .= "\n\n" . t('Willst du die Cloud wechseln? Dann nimm lieber „In andere Cloud umziehen …". So kommen deine Bilder mit.');
    }
    return $text;
}

/** The footnote on the new cloud's tile — where the old folder still lies. */
function cloud_move_footnote(array $last): string
{
    $when = date('d.m.Y', strtotime((string) ($last['finished_at'] ?? $last['started_at'])));
    $from = cloud_move_name((string) $last['from_provider']);
    $moved = (int) $last['moved'];
    $left  = (int) $last['waiting'] + (int) $last['stuck'];
    if ($moved === 0 && $left === 0) {
        return t('Am %s von %s hierher gewechselt. Was dort liegt, ist unberührt.', $when, $from);
    }
    // „Dateien" once a find's other files went along (cloud_move_count_text).
    $files = (int) ($last['files'] ?? 0) > 0;
    if ($left > 0) {
        return $files
            ? t('Am %s aus %s umgezogen: %d Dateien, %d sind dort geblieben. In %s liegt noch alles wie vorher. Den Ordner dort kannst du selbst löschen.',
                $when, $from, $moved, $left, $from)
            : t('Am %s aus %s umgezogen: %d Bilder, %d sind dort geblieben. In %s liegt noch alles wie vorher. Den Ordner dort kannst du selbst löschen.',
                $when, $from, $moved, $left, $from);
    }
    return $files
        ? t('Am %s aus %s umgezogen: %d Dateien. In %s liegt noch alles wie vorher. Den Ordner dort kannst du selbst löschen.',
            $when, $from, $moved, $from)
        : t('Am %s aus %s umgezogen: %d Bilder. In %s liegt noch alles wie vorher. Den Ordner dort kannst du selbst löschen.',
            $when, $from, $moved, $from);
}

/** The bar's line: pictures, and the weight where it is known. */
function cloud_move_count_text(array $state): string
{
    // „Dateien" once a find's other files are among them — they are no pictures.
    $line = ($state['files'] ?? 0) > 0
        ? ($state['total'] === 1
            ? t('%d von 1 Datei ist umgezogen', $state['moved'])
            : t('%d von %d Dateien sind umgezogen', $state['moved'], $state['total']))
        : ($state['total'] === 1
            ? t('%d von 1 Bild ist umgezogen', $state['moved'])
            : t('%d von %d Bildern sind umgezogen', $state['moved'], $state['total']));
    if ($state['bytes'] > 0) {
        $line .= ' · ' . t('%s von %s', cloud_size($state['bytes_moved']), cloud_size($state['bytes']));
    }
    return $line;
}

/**
 * THE MOVE IN THE TAB (26.09.2026) — under the one tile, which since the
 * switch is the new cloud's. A bar, the numbers, and whatever the move
 * waits for (includes/cloud_moves.php):
 *
 *   running  the open tab carries it step by step (assets/js/cloudmove.js
 *            reads data-cloud-move) — no buttons, nothing to decide
 *   full     the new cloud is full: one warning, „Nochmal versuchen"
 *   problem  a cloud does not answer: the reason, „Nochmal versuchen"
 *   stuck    everything else has arrived, these have not: the list,
 *            „Nochmal versuchen", and — asked first — closing the move
 *
 * The stuck list shows as soon as there is one, even while the rest still
 * moves: better seen early than discovered at the end.
 */
function cloud_move_render(PDO $pdo, array $move, string $target): void
{
    $state = cloud_move_state($pdo, $move);
    $from  = $state['from'];
    $to    = $state['to'];
    $mode  = $state['full'] ? 'full'
           : ($state['problem'] !== null ? 'problem'
           : ($state['waiting'] > 0 ? 'running' : 'stuck'));
    $share = $state['total'] > 0 ? (int) floor(100 * $state['moved'] / $state['total']) : 100;
    $stuck = $state['stuck'] > 0 ? cloud_move_stuck($pdo, $move) : [];
    $left  = $state['waiting'] + $state['stuck'];
    ?>
<div class="settings-group cloud-move" data-cloud-move="<?= h($mode) ?>">
    <h3 class="list-label"><?= h(t('Umzug aus %s', $from)) ?></h3>
    <div class="move-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $share ?>">
        <div class="area-bar"><div style="width: <?= $share ?>%"></div></div>
        <span class="move-count"><?= h(cloud_move_count_text($state)) ?></span>
    </div>

    <?php if ($mode === 'full'): ?>
        <?php notice('warning', t('%s ist voll. Der Umzug wartet. Mach dort Platz oder buch mehr Speicher dazu. Danach geht es von selbst weiter.', $to)); ?>
        <p class="hint-text"><?= h(t('Bis dahin behält YASAIMAPP den Schlüssel zu %s. Deine Bilder bleiben zu sehen, egal wo sie gerade liegen. Neue Fotos warten so lange auf dem Server.', $from)) ?></p>
    <?php elseif ($mode === 'problem'): ?>
        <?php notice('warning', (string) $state['problem']); ?>
        <p class="hint-text"><?= h(t('Bis dahin behält YASAIMAPP den Schlüssel zu %s. Deine Bilder bleiben zu sehen, egal wo sie gerade liegen.', $from)) ?></p>
    <?php else: ?>
        <p class="hint-text"><?= h(t('Der Umzug läuft von selbst weiter, auch wenn du YASAIMAPP schließt. Deine Bilder bleiben währenddessen zu sehen, egal wo sie gerade liegen.')) ?></p>
        <p class="hint-text"><?= h(t('Die Datensicherung legt in der ersten Nacht einmal alles in %s ab. Die alten Sicherungen bleiben in %s.', $to, $from)) ?></p>
    <?php endif; ?>

    <?php if ($stuck !== []): ?>
        <p class="hint-text"><?= h($state['stuck'] === 1
            ? t('Dieses Bild ist noch nicht angekommen:')
            : t('Diese %d Bilder sind noch nicht angekommen:', $state['stuck'])) ?></p>
        <div class="table-frame">
            <table class="list">
                <thead><tr><th><?= h(t('Bild')) ?></th><th><?= h(t('Warum')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($stuck as $row): ?>
                    <tr>
                        <td><?= h($row['name']) ?><br><span class="hint-text small"><?= h($row['what']) ?></span></td>
                        <td><?= h($row['error']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="hint-text"><?= h(t('YASAIMAPP versucht es mit ihnen einmal am Tag von selbst noch einmal.')) ?></p>
    <?php endif; ?>

    <?php if ($mode !== 'running'): ?>
        <div class="button-row right">
            <form method="post" action="<?= h($target) ?>"
                  data-confirm="<?= h($left === 1
                      ? t('Ein Bild bleibt dann in %s. YASAIMAPP löscht den Schlüssel zu %s und kann es danach nicht mehr zeigen.', $from, $from)
                      : t('%d Bilder bleiben dann in %s. YASAIMAPP löscht den Schlüssel zu %s und kann sie danach nicht mehr zeigen.', $left, $from, $from)) ?>"
                  data-confirm-title="<?= h(t('Umzug so abschließen?')) ?>"
                  data-confirm-button="<?= h(t('Abschließen')) ?>"
                  data-confirm-danger>
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="cloud">
                <input type="hidden" name="action" value="move_close">
                <button type="submit" class="button danger small"><?= h(t('Umzug so abschließen')) ?></button>
            </form>
            <form method="post" action="<?= h($target) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="cloud">
                <input type="hidden" name="action" value="move_retry">
                <button type="submit" class="button small"><?= h(t('Nochmal versuchen')) ?></button>
            </form>
        </div>
    <?php endif; ?>
</div>
    <?php
}

/**
 * THE MOVE WINDOW — the one door to another cloud while one is connected
 * (26.09.2026: the other tiles stay hidden, his rule of
 * 27.08.2026). It says what goes along and what happens, asks where to,
 * and sends the user to the new cloud's consent page; the switch itself
 * happens only when he comes back (pages/cloud_return.php).
 *
 * The choice stands in sight as radios where there is more than one
 * cloud to go to; with one, the button names it and nothing is asked.
 */
function cloud_move_window_render(PDO $pdo, int $userId, string $from, string $target): void
{
    $targets  = cloud_move_targets($from);
    $fromName = cloud_move_name($from);
    $held     = cloud_pictures_held($pdo, $userId, $from);
    $list     = cloud_pictures_list($held, true);
    ?>
<dialog class="form-window cloud-move-window pinned-footer" id="cloud-move-window">
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="cloud">
        <input type="hidden" name="action" value="move_start">
        <h2><?= h(t('In eine andere Cloud umziehen')) ?></h2>
        <?php if ($list === null): ?>
            <?php /* Nothing to carry: then it is only the switch, said in two
                     sentences — the key's loan and the running move do
                     not happen at all. */ ?>
            <p class="hint-text"><?= h(t('In %s liegen keine Bilder von YASAIMAPP. Du wechselst nur die Cloud.', $fromName)) ?></p>
            <p class="hint-text"><?= h(t('Sobald du bei der neuen Cloud zugestimmt hast, ist sie deine Cloud. Neue Fotos und die Datensicherung gehen dann dorthin. %s ist danach nicht mehr verbunden. Was dort liegt, bleibt unberührt.', $fromName)) ?></p>
        <?php else: ?>
            <?php /* „ALLES", once the finds' other files go along — they are
                     not pictures, and the sentence must not leave them out. */ ?>
            <p class="hint-text"><?= h(($held['file'] ?? 0) > 0
                ? ($held['bytes'] > 0
                    ? t('Alles, was YASAIMAPP in %s abgelegt hat, zieht in die neue Cloud um: %s, zusammen %s.', $fromName, $list, cloud_size($held['bytes']))
                    : t('Alles, was YASAIMAPP in %s abgelegt hat, zieht in die neue Cloud um: %s.', $fromName, $list))
                : ($held['bytes'] > 0
                    ? t('Alle Bilder, die YASAIMAPP in %s abgelegt hat, ziehen in die neue Cloud um: %s, zusammen %s.', $fromName, $list, cloud_size($held['bytes']))
                    : t('Alle Bilder, die YASAIMAPP in %s abgelegt hat, ziehen in die neue Cloud um: %s.', $fromName, $list))) ?></p>
            <p class="hint-text"><?= h(t('Du wechselst dabei die Cloud. Sobald du bei der neuen zugestimmt hast, ist sie deine Cloud. Neue Fotos und die Datensicherung gehen dann dorthin.')) ?></p>
            <p class="hint-text"><?= h(t('%s ist danach nicht mehr verbunden. YASAIMAPP behält nur den Schlüssel, bis das letzte Bild drüben ist, und löscht ihn dann. In %s selbst wird nichts gelöscht.', $fromName, $fromName)) ?></p>
            <p class="hint-text"><?= h(t('Der Umzug läuft von selbst. Du kannst YASAIMAPP dabei schließen. Deine Bilder bleiben die ganze Zeit zu sehen.')) ?></p>
        <?php endif; ?>
        <?php if (count($targets) === 1): ?>
            <input type="hidden" name="provider" value="<?= h($targets[0]) ?>">
        <?php else: ?>
            <fieldset class="field question">
                <legend><?= h(t('Wohin sollen deine Bilder?')) ?></legend>
                <?php foreach ($targets as $i => $key): ?>
                    <label class="choice-row"><input type="radio" name="provider" value="<?= h($key) ?>"
                           data-name="<?= h(cloud_move_name($key)) ?>"<?= $i === 0 ? ' checked' : '' ?>> <span><?= h(cloud_move_name($key)) ?></span></label>
                <?php endforeach; ?>
            </fieldset>
        <?php endif; ?>
        <div class="dialog-footer">
            <button type="button" class="button subtle" data-cloud-move-cancel><?= h(t('Abbrechen')) ?></button>
            <button type="submit" class="button" id="cloud-move-go"
                    data-label="<?= h(t('Weiter zu %s', '%s')) ?>"><?= h(t('Weiter zu %s', cloud_move_name($targets[0]))) ?></button>
        </div>
    </form>
</dialog>
    <?php
}

/** The view. $state comes from cloud_process(). */
function cloud_render(PDO $pdo, int $userId, array $state): void
{
    $target = url('pages/administration.php') . '#cloud';

    notice('error', $state['error'], true);
    if ($state['message'] !== null) {
        notice('success', $state['message'], true);
    }

    // The outcome of the round trip to the provider — laid down by
    // pages/cloud_return.php and picked up exactly once, so a reload does
    // not repeat it.
    // A deed, so a toast (CLAUDE.md) — it stood in the flow until the move
    // came and said its „ist jetzt deine Cloud" through here too.
    if (isset($_SESSION['cloud_message'])) {
        [$kind, $text] = $_SESSION['cloud_message'];
        unset($_SESSION['cloud_message']);
        notice((string) $kind, (string) $text, true);
    }

    $connections = cloud_connections($pdo, $userId);
    // A running move (includes/cloud_moves.php): the tile of the new cloud
    // loses „Trennen" and the move button, the panel below shows the move.
    $move = cloud_move_open($pdo, $userId);
    ?>

<?php /* WHAT THE CLOUD BRINGS, FIRST (03.10.2026, second round:
         „Hier geht viel zu viel um die Belegfotos. Die Cloud ist ja da,
         damit man seine großen gestackten Fotos von der echten Kamera
         hochladen kann und seine Mikrobilder" — and that the explanation
         said „freiwillig" but not the great advantages). So: the big
         pictures with the way to them, measuring, the order they are filed
         in (cloud_path_parts — kingdom, group and division stand above the
         genus there, left out for beginners), the nightly backup. No head
         over it: a heading over the one explanation names it twice.
         THE PROVIDER SENTENCE COMES FROM THE RELEASE LISTS — the clouds on
         both, the ones that take voucher photos AND „Auswählen" — and goes
         by itself once every provider is on them.
         THE PHONE'S PHOTOS ONLY BEHIND THE QUESTION MARK. Since 03.10.2026
 a shot with reception goes WHOLE into the cloud — on
         Wi-Fi always, over mobile data only with the tick in the
         connected tile below (map.js, fullSizeCapture). His
         reason for shrinking the rest stays: „Aufnehmen" must work offline
         on a tour, and the phone's own storage must not fill up. The
         numbers are EDGE_WITHOUT_CLOUD and EDGE in assets/js/photos.js —
         whoever changes those changes the fold too. „Auswählen" uploads
         every picture as it is and exists only with a connected cloud
         (cloudupload.js). */ ?>
<p class="hint-text"><?= h(t('Hier verbindest du YASAIMAPP mit deiner eigenen Cloud, also deinem Speicher im Internet. Das ist freiwillig, bringt dir aber viel:')) ?></p>
<ul class="hint-text">
    <li><?= t('<strong>Deine großen Bilder in voller Größe:</strong> Fotos aus der Kamera, auch gestackte, und Bilder durchs Mikroskop. Am Fund wählst du sie unter „Belege" mit „Auswählen" aus.') ?></li>
    <li><?= t('<strong>Messen auf den Bildern,</strong> zum Beispiel Sporen unter dem Mikroskop.') ?></li>
    <li><?= t('<strong>Alles ordentlich abgelegt:</strong> ein Ordner je Gattung und Art, darin einer je Fund mit Tag, Ort und Fundnummer. Jedes Bild trägt Art, Tag, Ort, seine Nummer und seinen Motivnamen im Dateinamen. So findest du deine Bilder auch ohne YASAIMAPP wieder.') ?></li>
    <li><?= t('<strong>Eine Sicherung deiner Funde,</strong> jede Nacht von selbst.') ?></li>
</ul>
<?php $pictureClouds = array_values(array_intersect(CLOUD_PICTURE_PROVIDERS, CLOUD_UPLOAD_PROVIDERS)); ?>
<?php if (count($pictureClouds) < count(CLOUD_PROVIDERS)): ?>
    <p class="hint-text"><?= h(t('Die Bilder gehen im Moment nur in %s. Die Sicherung geht bei jedem Anbieter unten.', cloud_names_or($pictureClouds))) ?></p>
<?php endif; ?>
<details class="info-fold">
    <summary><?= icon('help') ?><?= h(t('Und die Fotos, die ich am Fund mit „Aufnehmen" mache?')) ?></summary>
    <p class="hint-text"><?= h(t('Mit Netz gehen sie in voller Größe direkt in deine Cloud. Im WLAN immer, über mobile Daten nur, wenn du unten bei deiner Cloud den Haken setzt.')) ?></p>
    <p class="hint-text"><?= h(t('Sonst rechnet YASAIMAPP sie kleiner, damit dein Handy unterwegs nicht vollläuft: Ohne Empfang warten die Fotos auf dem Gerät, bis du wieder Netz hast. Ohne Cloud werden es immer 1000 Pixel an der langen Seite, mit Cloud 2048.')) ?></p>
    <p class="hint-text"><?= h(t('Willst du ein Bild in voller Größe, nimm „Auswählen". Das lädt jedes Bild so hoch, wie es ist, auch ein Foto aus der Galerie deines Handys. „Auswählen" gibt es, sobald eine Cloud verbunden ist.')) ?></p>
</details>

<?php if (!cloud_ready($pdo)): ?>
    <?php /* The code is live before the migration (see CLAUDE.md). As long as
         the table is missing, the tab says so instead of shattering on a
         query. */ ?>
    <?php
    /* THROUGH notice() AND NOT THROUGH A CLASS OF ITS OWN. What stood
       here was <p class="hint-text warnung">, and 'warnung' never had a
       rule in any stylesheet — the sentence looked like a passing remark
       although it says that nothing works here right now. The library has
       known the kind all along and translates it at the one place where it
       becomes a CSS class (page.php). */
    notice('warning', t('Die Tabelle für die Zugänge fehlt noch — Migration 0053 ist noch nicht eingespielt. Bis dahin lässt sich hier nichts verbinden.'));
    ?>
<?php endif; ?>

<?php
/* ONE CONNECTED MEANS ONLY THAT ONE (28.08.2026: „wenn man
   verbunden ist, werden die anderen drei ausgeblendet … Den Rest braucht
   man nicht sehen"). This supersedes the 27.08 exception that kept the
   other cards for administrators — an exception that rested on a
   MISREADING of this page, corrected the same day (see the TWO LEVELS
   paragraph in cloud.php): the connection is per user account, and an
   administrator can neither connect nor manage a cloud for anybody else,
   so the other cards bought them nothing of the sort. The cards used to
   carry the per-installation APP REGISTRATION for administrators, on the
   assumption that one sets an installation up before connecting
   oneself — wrong on 02.10.2026, when the maintainer, connected to pCloud,
   could not reach Google Drive's at all. The registrations live in
   Administration → Cloud-Anbieter since (cloud_providers_content.php).
   Said here so the next person reads the vanished cards as neither a
   regression nor a lost admin power that never existed.

   The status wording per tile:
     nicht verbunden            usable, one click away
     noch nicht freigeschaltet  the code can do it, no app is registered —
                                the administrator's job, not the user's
                                mistake (until 02.10.2026 „auf diesem
                                Server nicht eingerichtet")
     kommt später               honest: the code cannot do it yet */
$connectedKey = array_key_first($connections);
?>
<?php /* „Deine Cloud" once one is connected: then only its tile stands here,
     and „Anbieter" over a single one would announce a choice that is gone. */ ?>
<div class="column-header">
    <h3><?= h($connectedKey === null ? t('Anbieter') : t('Deine Cloud')) ?></h3>
</div>
<div class="cloud-provider">
    <?php foreach (CLOUD_PROVIDERS as $key => $a): ?>
        <?php if ($connectedKey !== null && $key !== $connectedKey) continue; ?>
        <?php $connected = $connections[$key] ?? null; ?>
        <?php if ($connected === null): ?>
        <?php
        /* A SMALL TILE THAT UNFOLDS (28.08.2026: „vier kleine
           Kacheln … Die Anleitung soll nur angezeigt werden, wenn man
           sich dafür interessiert"). The whole tile is a <details>; its
           summary is the tile face — name and status, nothing more — and
           everything below (hint, instructions, the connect button or the
           S3 form) waits inside. name="cloud-provider" makes the four an
           accordion NATIVELY: opening one closes the others, no script.
           The summary keeps the browser's fold triangle — the same
           affordance every other fold on this page shows. */
        ?>
        <details class="cloud-card cloud-tile" name="cloud-provider">
            <summary>
                <span class="cloud-header">
                    <span class="cloud-name"><?= h($a['name']) ?></span>
                    <?php if (cloud_provider_usable($key)): ?>
                        <span class="cloud-status"><?= h(t('nicht verbunden')) ?></span>
                    <?php elseif ($a['ready']): ?>
                        <span class="cloud-status"><?= h(t('noch nicht freigeschaltet')) ?></span>
                    <?php else: ?>
                        <span class="cloud-status"><?= h(t('kommt später')) ?></span>
                    <?php endif; ?>
                </span>
            </summary>
            <div class="cloud-tile-body">
                <p class="cloud-hint"><?= h(t($a['hint']) . ' ' . cloud_card_goods($key)) ?></p>

                <?php /* WHAT IS MISSING, said to the one who cannot fix it
                         (Finn, 23.09.2026, the first to try Google Drive:
                         „nicht eingerichtet", no steps, no button). The two
                         levels of cloud.php, in one sentence each: the MAP
                         registers itself with the provider — the
                         administrator's one act —, the CONNECTION is the user's
                         own. The first wording („richtet der Verwalter
                         einmal für alle ein") gave the registration an
                         addressee and read as if the administrator set up
                         everybody's cloud — the maintainer read it exactly so,
                         the same day. */ ?>
                <?php /* THE ADMINISTRATOR IS SENT TO THE REGISTRATION, not
                         shown it here (02.10.2026): it lives in
                         Administration → Cloud-Anbieter now, where it stands
                         whatever one is connected to oneself. */ ?>
                <?php if ($a['ready'] && !cloud_provider_usable($key)): ?>
                    <?php if (is_admin(user_current())): ?>
                        <p class="cloud-hint"><?= h(t('Das geht noch nicht. Zuerst muss YASAIMAPP bei %s angemeldet werden. Das machst du als Verwalter unter Administration → Cloud-Anbieter.', $a['name'])) ?></p>
                        <div class="button-row right">
                            <button type="button" class="button subtle small" data-goto-tab="cloud_providers"><?= h(t('Zu den Cloud-Anbietern')) ?></button>
                        </div>
                    <?php else: ?>
                        <p class="cloud-hint"><?= h(t('Das geht noch nicht. Zuerst muss der Verwalter dieser Seite YASAIMAPP bei %s anmelden. Dadurch sieht niemand etwas von deiner Cloud. Danach verbindest du hier dein eigenes %s.', $a['name'], $a['name'])) ?></p>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($key === 's3' && cloud_provider_usable($key)): ?>
                    <?php /* THE KEY PROVIDER CONNECTS THROUGH A FORM, not a
                         journey: no consent screen exists, the five values
                         come from the user. Directly in the tile body —
                         the tile itself is the fold now, and a second one
                         inside it would be a drawer in a drawer. */ ?>
                    <p class="hint-text small">
                        <?= t('So geht es: Lege bei deinem Anbieter einen Eimer (Bucket) an. Erzeuge dann einen Zugriffsschlüssel. Lass ihn nur für diesen Eimer gelten, wenn dein Anbieter das kann. <strong>Wichtig:</strong> Der Schlüssel darf alles, was du ihm beim Erzeugen erlaubst. Die fünf Angaben findest du in der Übersicht deines Anbieters.') ?>
                    </p>
                    <form method="post" action="<?= h($target) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="cloud">
                        <input type="hidden" name="action" value="s3_connect">

                        <div class="field">
                            <label for="s3-endpoint"><?= h(t('Endpunkt')) ?></label>
                            <input type="text" id="s3-endpoint" name="endpoint"
                                   placeholder="s3.example.com" autocomplete="off"
                                   spellcheck="false" required>
                        </div>
                        <div class="field">
                            <label for="s3-region"><?= h(t('Region')) ?></label>
                            <input type="text" id="s3-region" name="region"
                                   placeholder="eu-central-1" autocomplete="off"
                                   spellcheck="false" required>
                        </div>
                        <div class="field">
                            <label for="s3-bucket"><?= h(t('Eimer (Bucket)')) ?></label>
                            <input type="text" id="s3-bucket" name="bucket"
                                   autocomplete="off" spellcheck="false" required>
                        </div>
                        <div class="field">
                            <label for="s3-key-id"><?= h(t('Schlüsselkennung (Access Key ID)')) ?></label>
                            <input type="text" id="s3-key-id" name="key_id"
                                   autocomplete="off" spellcheck="false" required>
                        </div>
                        <div class="field">
                            <label for="s3-key-secret"><?= h(t('Schlüssel (Secret Key)')) ?></label>
                            <?php /* type=password as for the Client-Secret of
                                 the provider windows: not readable in passing,
                                 no password-manager suggestion. */ ?>
                            <input type="password" id="s3-key-secret" name="key_secret"
                                   autocomplete="new-password" spellcheck="false" required>
                        </div>

                        <div class="button-row right">
                            <button type="submit" class="button small"><?= h(t('Prüfen und verbinden')) ?></button>
                        </div>
                    </form>
                <?php elseif (cloud_provider_usable($key)): ?>
                    <form method="post" action="<?= h($target) ?>" class="button-row right">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="cloud">
                        <input type="hidden" name="action" value="connect">
                        <input type="hidden" name="provider" value="<?= h($key) ?>">
                        <button type="submit" class="button small"><?= h(t('Verbinden')) ?></button>
                    </form>
                <?php endif; ?>

            </div>
        </details>
        <?php continue; ?>
        <?php endif; ?>

        <div class="cloud-card is-connected">
            <div class="cloud-header">
                <span class="cloud-name"><?= h($a['name']) ?></span>
                <span class="cloud-status is-active"><?=
                    $connected['account'] !== null
                        ? t('verbunden als %s', h($connected['account']))
                        : h(t('verbunden')) ?></span>
            </div>

            <?php /* SINCE WHEN, NOT HOW (23.09.2026): the tile
                     of a connected storage said „Verbinden, zustimmen,
                     fertig" to someone long connected. Now the date, and
                     whatever of the provider's sentence stays true. */ ?>
            <p class="cloud-hint"><?= h(trim(
                t('Verbunden seit %s.', date('d.m.Y', strtotime((string) $connected['created_at'])))
                . ' ' . ($a['connected_hint'] !== '' ? t($a['connected_hint']) : '')
                . ' ' . cloud_card_goods($key))) ?></p>

            <?php /* WHERE THE OLD FOLDER IS (26.09.2026). A move deletes
                     nothing in the old cloud (the choice), so the
                     tile of the new one says once, quietly, where
                     everything still lies — meta, hence a footnote. */ ?>
            <?php $last = $move === null ? cloud_move_last($pdo, $userId, $key) : null; ?>
            <?php if ($last !== null): ?>
                <p class="hint-text small provider-currency"><?= h(cloud_move_footnote($last)) ?></p>
            <?php endif; ?>

            <?php /* THE APP'S OWN FOLDER, where the app makes one
                     (23.09.2026): Drive and S3 give no app folder, so the
                     app keeps everything in „YASAIMAPP" in the root — the
                     one name there that can meet the user's own, and so the
                     one the user may change. pCloud and OneDrive carry no
                     field: their app folder is the provider's. A setting of
                     the connection, so it stands in the connection's tile,
                     not in the backup below. */ ?>
            <?php if (cloud_has_own_home($key) && cloud_home_ready($pdo)): ?>
                <form method="post" action="<?= h($target) ?>" class="field" style="margin-top:.8rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="cloud">
                    <input type="hidden" name="action" value="home_folder">
                    <label for="home-folder"><?= h(t('Ordner von YASAIMAPP in deiner Cloud')) ?></label>
                    <div class="field-row">
                        <input type="text" id="home-folder" name="home_folder" maxlength="100" required
                               autocomplete="off" spellcheck="false"
                               value="<?= h(cloud_home_name($pdo, $userId)) ?>">
                        <?php /* „Ändern" at S3: a new name there is a new
                                 folder, nothing is renamed. */ ?>
                        <button type="submit" class="button subtle"><?= h($key === 's3' ? t('Ändern') : t('Umbenennen')) ?></button>
                    </div>
                    <p class="hint-text small">
                        <?= h($key === 's3'
                            ? t('Hier legt YASAIMAPP alles ab. Ein neuer Name ergibt einen neuen Ordner. YASAIMAPP legt dann alles noch einmal dort ab. Der alte Ordner bleibt, wie er ist.')
                            : t('Hier legt YASAIMAPP alles ab, in „Meine Ablage". Du kannst den Ordner auch in Google Drive umbenennen oder verschieben. YASAIMAPP findet ihn trotzdem.')) ?>
                    </p>
                </form>
            <?php endif; ?>

            <?php /* FULL SIZE OVER MOBILE DATA (14.11.2026: „das
                     würde ich sogar dann vielleicht bei der Cloud hinpacken")
                     — it stood at the head of Einstellungen → Fotos →
                     Motivnamen since 03.10.2026, and that tab went. Here,
                     because it means something only with a connected cloud:
                     without one a shot is always shrunk. A setting of the
                     connection, so in its tile, beside the folder. No witness
                     field beside the box as in the old form: this form
                     exists for the box alone, so a tick that does not come
                     means off. */ ?>
            <?php if (column_exists($pdo, 'map_users', 'measure_style')): ?>
                <?php
                require_once __DIR__ . '/photos.php';
                $styleStmt = $pdo->prepare('SELECT measure_style FROM map_users WHERE id = ?');
                $styleStmt->execute([$userId]);
                $fullSize = !empty(measure_style_read($styleStmt->fetchColumn() ?: null)['full_size_mobile']);
                ?>
                <form method="post" action="<?= h($target) ?>" class="field" style="margin-top:.8rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="cloud">
                    <input type="hidden" name="action" value="full_size_mobile">
                    <label class="checkbox-row">
                        <input type="checkbox" name="full_size_mobile" value="1"<?= $fullSize ? ' checked' : '' ?>>
                        <?= h(t('Fotos in voller Größe auch über mobile Daten hochladen')) ?>
                    </label>
                    <p class="hint-text small"><?= h(t('Im WLAN gehen deine Fotos immer in voller Größe in die Cloud. Über mobile Daten nur mit diesem Haken. Ein Handyfoto hat 5 bis 10 MB – das geht von deinem Datenvolumen ab. Ein iPhone verrät nicht, ob es im WLAN ist – dort entscheidet immer der Haken.')) ?></p>
                    <div class="button-row right">
                        <button type="submit" class="button subtle small" data-quiet-until-changed><?= icon('save') ?><?= h(t('Speichern')) ?></button>
                    </div>
                </form>
            <?php endif; ?>


                <?php /* TWO FORMS, NOT TWO BUTTONS IN ONE. The confirmation hangs
                     on the <form> (see below), so every submit of that form
                     asks "disconnect?" — a check button inside it would ask
                     the wrong question before doing the right thing. Exactly
                     that happened while building this. */ ?>
                <?php
                /* THE MOVE'S DOOR sits beside the connection's own buttons
                   (the draft, 26.09.2026): a move is rare, so it
                   gets no block of its own in the tab. Only where there is
                   somewhere to go — a released cloud other than this one —
                   and never during a move, like „Trennen". */
                $moveTargets = $move === null && cloud_moves_ready($pdo) ? cloud_move_targets($key) : [];
                ?>
                <div class="button-row right">
                    <form method="post" action="<?= h($target) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="cloud">
                        <input type="hidden" name="action" value="check">
                        <input type="hidden" name="provider" value="<?= h($key) ?>">
                        <button type="submit" class="button subtle small"><?= h(t('Verbindung prüfen')) ?></button>
                    </form>

                    <?php if ($moveTargets !== []): ?>
                        <button type="button" class="button subtle small" id="cloud-move-open"><?= h(t('In andere Cloud umziehen …')) ?></button>
                    <?php endif; ?>

                    <?php /* THE CONFIRMATION HANGS ON THE <form>, NOT ON THE
                         BUTTON: wireForms() in dialogs.js looks for
                         form[data-confirm]. On the button the attribute
                         does sit there but nobody reads it — and then a
                         single click disconnects wordlessly. Exactly that
                         happened here while building.

                         The text says explicitly that NOTHING is deleted.
                         "Trennen" otherwise sounds like "files gone", and
                         that is the kind of misunderstanding one only notices
                         when somebody did not dare.

                         AND SINCE 26.09.2026 IT COUNTS: what lies there stays,
                         but YASAIMAPP can no longer show it — the one thing
                         the old sentence left out, and the one that matters
                         (cloud_disconnect_question). Not during a move. */ ?>
                    <?php if ($move === null): ?>
                    <form method="post" action="<?= h($target) ?>"
                          data-confirm="<?= h(cloud_disconnect_question($a['name'],
                              cloud_ready($pdo) ? cloud_pictures_held($pdo, $userId, $key) : [], $moveTargets !== [])) ?>"
                          data-confirm-title="<?= h(t('Verbindung zu %s trennen?', $a['name'])) ?>"
                          data-confirm-button="<?= h(t('Trotzdem trennen')) ?>"
                          data-confirm-danger>
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="cloud">
                        <input type="hidden" name="action" value="disconnect">
                        <input type="hidden" name="provider" value="<?= h($key) ?>">
                        <button type="submit" class="button danger small"><?= h(t('Trennen')) ?></button>
                    </form>
                    <?php endif; ?>
                </div>

        </div>
    <?php endforeach; ?>
</div>

<?php if ($move !== null): ?>
    <?php cloud_move_render($pdo, $move, $target); ?>
<?php elseif ($connectedKey !== null && cloud_moves_ready($pdo) && cloud_move_targets((string) $connectedKey) !== []): ?>
    <?php cloud_move_window_render($pdo, $userId, (string) $connectedKey, $target); ?>
<?php endif; ?>

<?php /* „Wohin gelegt wird" — the storage's table of levels — stood here
         until 28.08.2026 and is removed entirely on the word. Its
         closing sentence („solange legt die Karte nichts von selbst an")
         had moreover long been outdated: the letter compartments are
         decided by counting, the place after the find day is the
         municipality, both in storage_find_folder(). The scheme itself
         sits at cloud_path_parts(), where it is built. */ ?>

<?php
// Whichever storage is connected — the backup does not care which
// (one per account, see cloud_storage).
cloud_backup_render($pdo, $userId, $connections === [] ? null : reset($connections));
?>
    <?php
}

/**
 * The data backup — state, and a button for the impatient. (A switch
 * stood first until 28.08.2026; see the form below for why it went.)
 *
 * ITS OWN FUNCTION and not one more block in cloud_render(): that one is
 * already long, and this is a separate promise — the one above is about
 * where PICTURES go, this one about the finds themselves.
 *
 * THE NUMBERS COME FROM OUR OWN TABLES (backup_summary), never from the
 * provider. A page load that asks a foreign server hangs on it, and this
 * tab has four other things to show.
 */
function cloud_backup_render(PDO $pdo, int $userId, ?array $connection): void
{
    $target  = url('pages/administration.php') . '#cloud';
    $summary = backup_summary($pdo, $userId);
    ?>

<div class="column-header">
    <h3><?= h(t('Datensicherung')) ?></h3>
</div>

<?php /* PLAIN WORDS (02.10.2026). What runs by itself and when
         stays with the explanation, not under the button (23.09.2026,
         sorting the page by weight); the cron file stays unnamed
         (28.08.2026: „Müssen wir da die PHP-Datei verraten?").
         THE OLD BOLD „Gelöscht wird in der Cloud nichts" WENT BECAUSE A
         BEGINNER READ IT WRONG: it said that no FILE is deleted, and it was
         read as „what I delete is still in the backup". The backup is a
         mirror (backup.php): a find put into the trash falls out of its
         month file in the next night. So the page says what protects
         against deleting by mistake — the trash — and that the backup does
         not. That the folder stays on „Trennen" is said by the question
         before it (cloud_disconnect_question). */ ?>
<p class="hint-text">
    <?= t('Jede Nacht legt YASAIMAPP eine Kopie deiner Funde in deine Cloud, in den Ordner <code>%s</code>. Für jeden Monat gibt es eine Tabelle. Du öffnest sie mit jedem Tabellenprogramm, zum Beispiel Excel. Über „Import" holst du sie wieder herein.', h(backup_folder_path($pdo, $userId))) ?>
</p>
<p class="hint-text">
    <?= h(t('Die Kopie ist kein Schutz vor versehentlichem Löschen. Dafür gibt es den Papierkorb: Dort bleibt jeder gelöschte Fund liegen, bis du ihn endgültig entfernst.')) ?>
</p>
<?php if (!backup_ready($pdo)): ?>
    <?php
    /* Standing in the flow and not floating: this is a STATE, not the answer
       to a click — it is the reason the whole block below looks empty, and it
       has to stay there until somebody applies the migration. */
    notice('warning', t('Die Datensicherung braucht Migration 0085 — die ist noch nicht eingespielt. Bis dahin wird nichts hochgeladen.'));
    ?>
<?php elseif ($connection === null): ?>
    <p class="hint-text">
        <?= h(t('Dafür verbinde oben zuerst eine Cloud.')) ?>
    </p>
<?php else: ?>

    <div class="table-frame">
        <table class="list">
            <tr>
                <th><?= h(t('Monate mit Funden')) ?></th>
                <td><?= (int) $summary['months'] ?></td>
            </tr>
            <tr>
                <th><?= h(t('davon gesichert')) ?></th>
                <td><?= (int) $summary['secured'] ?><?=
                    $summary['points'] > 0
                        ? ' <span class="quiet">(' . h((int) $summary['points'] === 1 ? t('1 Fund') : t('%d Funde', (int) $summary['points'])) . ')</span>'
                        : '' ?></td>
            </tr>
            <tr>
                <th><?= h(t('letzte Sicherung')) ?></th>
                <?php /* „noch nie" and not an empty field: a dash would leave
                     open whether nothing ran or there was nothing to show. */ ?>
                <td><?= $summary['at'] === null
                        ? '<span class="quiet">' . h(t('noch nie')) . '</span>'
                        : h(date('d.m.Y H:i', strtotime((string) $summary['at']))) ?></td>
            </tr>
        </table>
    </div>

    <?php /* No switch in front of it any more (28.08.2026: „Das
             ist ein Automatismus … Ungefragt.") — the connected cloud IS
             the consent, and the only off switch is Trennen. */ ?>
    <form method="post" action="<?= h($target) ?>" class="button-row right">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="cloud">
        <input type="hidden" name="action" value="backup_now">
        <button type="submit" class="button subtle small"><?= h(t('Jetzt sichern')) ?></button>
    </form>

<?php endif; ?>
    <?php
}
