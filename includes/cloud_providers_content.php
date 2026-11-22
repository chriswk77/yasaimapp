<?php
/**
 * Cloud-Anbieter — processing and view (Administration → Cloud-Anbieter).
 *
 * THE INSTALLATION'S REGISTRATIONS AT THE PROVIDERS, apart from anybody's
 * own connection. Until 02.10.2026 they stood in Mein Bereich → Cloud,
 * inside each provider's tile — and since one connected cloud hides every
 * other tile (the rule of 28.08.2026), an administrator connected
 * to pCloud could not reach the Google Drive registration at all. The maintainer
 * found exactly that the day he set Drive up, and had to write his Client-ID
 * into config.php instead. A registration belongs to the whole site, so it
 * lives here, where all four stand whatever one is connected to oneself.
 *
 * NO SWITCH FOR PICTURES (02.10.2026: „die Cloud ist für die Fotos
 * gedacht, nicht nur für die Sicherung … sind die Clouds eingerichtet, sind
 * die Fotos automatisch aktiv. Dafür gibt es die Clouds"). The draft asked
 * whether „Fotos freigeben" should become a switch per provider; it should
 * not. CLOUD_PICTURE_PROVIDERS in cloud.php is a test guard until a
 * provider's pictures (and previews) were tried with a real account, not a
 * setting — so the list says nothing about pictures either.
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';

/** The tidy button's time budget — the same a chunk of copies gets. */
const CLOUD_TIDY_SECONDS = 25;

/**
 * THE SWEEP over every connected cloud (storage_tidy explains what goes,
 * and why it is here a second time and stays). Here and not under
 * Nachtragen: it fills in no value for points, it tidies the clouds — the
 * clouds of everybody, which is why it sits with the administrator and
 * says only numbers, never whose or what. No cron twin: every deletion and
 * every renaming tidies its own way (storage_prune_empty,
 * cloud_names_sweep); this is for the day a rule changed for the whole
 * stock and left ways nobody walks again. Pressing it at any other time
 * does no harm — it finds what somebody left by hand.
 *
 * @return array{clouds: int, sidecars: int, folders: int, failed: int, done: bool}
 */
function cloud_tidy_run(PDO $pdo, float $deadline): array
{
    $sum = ['clouds' => 0, 'sidecars' => 0, 'folders' => 0, 'failed' => 0, 'done' => true];
    if (!cloud_ready($pdo)) {
        return $sum;
    }
    $accounts = $pdo->query('SELECT user_id, provider FROM map_cloud_accounts ORDER BY user_id, provider')->fetchAll();
    foreach ($accounts as $a) {
        if (microtime(true) >= $deadline) {
            $sum['done'] = false;
            break;
        }
        try {
            $s = cloud_storage_for($pdo, (int) $a['user_id'], (string) $a['provider']);
            if ($s === null) {
                $sum['failed']++;
                continue;
            }
            $one = storage_tidy($pdo, (int) $a['user_id'], $s, $deadline);
        } catch (RuntimeException $e) {
            error_log('cloud_tidy_run (' . $a['user_id'] . ' ' . $a['provider'] . '): ' . $e->getMessage());
            $sum['failed']++;
            continue;
        }
        $sum['clouds']++;
        $sum['sidecars'] += $one['sidecars'];
        $sum['folders']  += $one['folders'];
        if (!$one['done']) {
            $sum['done'] = false;
            break;
        }
    }
    return $sum;
}

/** What the tidy run says — one sentence for its toast. */
function cloud_tidy_message(array $sum): string
{
    $out = $sum['done']
        ? ($sum['sidecars'] + $sum['folders'] === 0
            ? t('Alles war schon aufgeräumt.')
            : t('Aufgeräumt: %d Messwert-Dateien ohne Bild und %d leere Ordner entfernt.', $sum['sidecars'], $sum['folders']))
        : t('Bis hierher %d Messwert-Dateien ohne Bild und %d leere Ordner entfernt. Es ist noch nicht alles durch. Drück noch einmal auf „Aufräumen".',
            $sum['sidecars'], $sum['folders']);
    if ($sum['failed'] === 1) {
        $out .= ' ' . t('Eine Cloud hat nicht geantwortet.');
    } elseif ($sum['failed'] > 1) {
        $out .= ' ' . t('%d Clouds haben nicht geantwortet.', $sum['failed']);
    }
    return $out;
}

/**
 * Must run BEFORE any output. Saves a provider's Client-ID and Secret into
 * the vault (data/cloud.php, see cloud_vault_path()).
 *
 * @return array{message: ?string, hint: ?string, error: string[]}
 */
function cloud_providers_process(PDO $pdo): array
{
    $state = ['message' => null, 'hint' => null, 'error' => []];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'cloud_providers') {
        return $state;
    }
    csrf_check();

    try {
        if (!is_admin(user_current())) {
            throw new RuntimeException(t('Das darf nur ein Verwalter.'));
        }
        if (($_POST['action'] ?? '') === 'tidy') {
            $sum = cloud_tidy_run($pdo, microtime(true) + CLOUD_TIDY_SECONDS);
            // Yellow instead of green while it is not through, or a cloud did not answer.
            $state[$sum['done'] && $sum['failed'] === 0 ? 'message' : 'hint'] = cloud_tidy_message($sum);
            return $state;
        }
        $provider = (string) ($_POST['provider'] ?? '');
        if (!cloud_provider_valid($provider) || empty(CLOUD_PROVIDERS[$provider]['ready'])
            || CLOUD_PROVIDERS[$provider]['kind'] !== 'oauth') {
            throw new InvalidArgumentException(t('Unbekannter Anbieter.'));
        }
        // config.php wins (cloud_app) — a save here would change nothing,
        // and „Gespeichert" would be a lie. The window shows no fields then;
        // this answers a replayed form.
        if (cloud_app_source($provider) === 'config') {
            throw new RuntimeException(t('Diese Anmeldung steht in der config.php. Ändern geht nur dort.'));
        }

        $id     = trim((string) ($_POST['client_id'] ?? ''));
        $secret = trim((string) ($_POST['client_secret'] ?? ''));
        $name   = CLOUD_PROVIDERS[$provider]['name'];

        /* THE SECRET'S END, where it has one (OneDrive). Required with a new
           secret — without it the reminder a month before cannot come, and
           the reminder is the reason the field exists
           (02.10.2026). A date in the past is refused: it is a typo, not a
           secret one would knowingly store. */
        $expiring = !empty(CLOUD_PROVIDERS[$provider]['expiring_secret']);
        $expires  = trim((string) ($_POST['secret_expires'] ?? ''));
        if ($expiring) {
            $day = DateTimeImmutable::createFromFormat('!Y-m-d', $expires);
            if ($expires !== '' && ($day === false || $day->format('Y-m-d') !== $expires)) {
                throw new InvalidArgumentException(t('Das Datum stimmt nicht.'));
            }
            if ($expires !== '' && $day < new DateTimeImmutable('today')) {
                throw new InvalidArgumentException(t('Dieses Datum liegt schon in der Vergangenheit. Trag ein, bis wann das Secret gilt.'));
            }
        }

        $vault = cloud_vault();

        /* ONLY THE DATE, while the secret on file stays: a registration
           saved before the field existed gets its date without typing the
           secret anew — which Microsoft never shows a second time. */
        if ($expiring && $id === '' && $secret === '' && $expires !== ''
            && cloud_app_source($provider) === 'vault') {
            $vault[$provider]['secret_expires'] = $expires;
            cloud_vault_write($vault);
            $state['message'] = t('Gespeichert. YASAIMAPP erinnert dich einen Monat vor dem %s.', date('d.m.Y', strtotime($expires)));
            return $state;
        }

        if ($id === '' || $secret === '') {
            throw new InvalidArgumentException(
                t('Beides wird gebraucht: Client-ID und Client-Secret aus der %s-Registrierung.', $name));
        }
        if ($expiring && $expires === '') {
            throw new InvalidArgumentException(t('Trag noch ein, bis wann das Secret gilt. Dann erinnert dich YASAIMAPP einen Monat vorher.'));
        }

        // THE KEY IS MADE ONCE AND NEVER AGAIN. A new one would leave
        // every already stored token unreadable — the connections
        // would look intact and fail on first use. Whoever changes
        // the app credentials keeps the old key.
        // 'schluessel': a vault written before the move to English
        // holds its key under the old name — that one stays valid,
        // a second key beside it would lock all stored tokens.
        $vault['key'] ??= $vault['schluessel'] ?? base64_encode(random_bytes(32));
        $vault[$provider] = ['client_id' => $id, 'client_secret' => $secret]
            + ($expiring ? ['secret_expires' => $expires] : []);

        cloud_vault_write($vault);
        $state['message'] = t('Gespeichert. %s lässt sich jetzt verbinden.', $name);
    } catch (InvalidArgumentException | RuntimeException $e) {
        $state['error'][] = $e->getMessage();
    }

    return $state;
}

/**
 * How many accounts hold a connection, per provider. From our own table,
 * never from the providers — a page view that asks four foreign servers
 * hangs on the slowest of them.
 *
 * @return array<string, int>
 */
function cloud_providers_counts(PDO $pdo): array
{
    if (!cloud_ready($pdo)) {
        return [];
    }
    $counts = [];
    foreach ($pdo->query('SELECT provider, COUNT(*) AS n FROM map_cloud_accounts GROUP BY provider') as $row) {
        $counts[(string) $row['provider']] = (int) $row['n'];
    }
    return $counts;
}

/** The view. $state comes from cloud_providers_process(). */
function cloud_providers_render(PDO $pdo, array $state): void
{
    notice('error', $state['error'], true);
    if ($state['message'] !== null) {
        notice('success', $state['message'], true);
    }
    if (($state['hint'] ?? null) !== null) {
        notice('hint', $state['hint'], true);
    }
    $counts = cloud_providers_counts($pdo);
    // A secret running out is said at the head of the whole administration
    // (pages/administration.php), like a pending migration — not a second
    // time here.
    ?>
<p class="hint-text"><?= h(t('Hier meldest du YASAIMAPP bei den Cloud-Anbietern an. Das machst du einmal für die ganze Seite. Danach kann jeder im Reiter „Cloud" seine eigene Cloud verbinden.')) ?></p>
<p class="hint-text"><?= h(t('Du siehst dadurch nichts von den Clouds der anderen. Der Anbieter lernt nur YASAIMAPP kennen.')) ?></p>

<div class="column-header">
    <h3><?= h(t('Anbieter')) ?></h3>
</div>
<div class="table-frame">
    <table class="list">
        <tbody>
        <?php foreach (CLOUD_PROVIDERS as $key => $a): ?>
            <?php
            $source = $a['kind'] === 'oauth' ? cloud_app_source($key) : null;
            $parts  = [];
            if (!$a['ready']) {
                $parts[] = t('kommt später');
            } elseif ($a['kind'] !== 'oauth') {
                // S3 has nothing to register: every user brings a key.
                $parts[] = t('braucht keine Anmeldung, jeder trägt seinen eigenen Schlüssel ein');
            } else {
                $parts[] = match ($source) {
                    'config' => t('eingerichtet in der config.php'),
                    'vault'  => t('eingerichtet'),
                    default  => t('noch nicht eingerichtet'),
                };
            }
            $expires = cloud_app_expiry($key);
            if ($expires !== null) {
                $parts[] = t('Secret gilt bis %s', date('d.m.Y', strtotime($expires)));
            } elseif ($source !== null && !empty($a['expiring_secret'])) {
                // Set up before the field existed: say what is missing.
                $parts[] = t('Ablaufdatum des Secrets fehlt');
            }
            if ($a['ready']) {
                $n = $counts[$key] ?? 0;
                $parts[] = $n === 1 ? t('1 Nutzer verbunden') : t('%d Nutzer verbunden', $n);
            }
            ?>
            <tr>
                <td>
                    <strong class="import-row-name"><?= h($a['name']) ?></strong>
                    <span class="item-data import-row-data"><?= h(implode(' · ', $parts)) ?></span>
                </td>
                <td class="button-cell">
                    <?php if ($a['ready'] && $a['kind'] === 'oauth'): ?>
                        <?php /* All quiet: a provider not set up is a standing
                                 offer, not a duty — the draft's loud
                                 „Einrichten" at Drive was loud only because
                                 Drive was due that day. */ ?>
                        <button type="button" class="button subtle small"
                                data-open-window="cloud-provider-<?= h($key) ?>"><?= h($source === null ? t('Einrichten') : t('Ändern')) ?></button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php /* RARELY NEEDED, SO LAST AND QUIET (the page is sorted by weight):
         the explanation first, then the one button — subtle, because
         nothing is due; it is a standing offer for the day something was
         left lying. */ ?>
<div class="column-header">
    <h3><?= h(t('Aufräumen')) ?></h3>
</div>
<p class="hint-text"><?= h(t('Manchmal bleibt in einer Cloud etwas liegen, das zu nichts mehr gehört: leere Ordner und Messwert-Dateien ohne ihr Bild.')) ?></p>
<p class="hint-text"><?= h(t('Dieser Knopf räumt das weg, in den Clouds aller Nutzer. Es geht nur, was leer ist oder zu keinem Bild mehr gehört, und es landet im Papierkorb der Cloud. Bei Google Drive bleiben leere Ordner stehen.')) ?></p>
<form method="post" action="<?= h(url('pages/administration.php') . '#cloud_providers') ?>" class="button-row right">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="cloud_providers">
    <input type="hidden" name="action" value="tidy">
    <button type="submit" class="button subtle small"><?= h(t('Aufräumen')) ?></button>
</form>

<?php foreach (CLOUD_PROVIDERS as $key => $a): ?>
    <?php if ($a['ready'] && $a['kind'] === 'oauth'): ?>
        <?php cloud_provider_window_render($key); ?>
    <?php endif; ?>
<?php endforeach; ?>
    <?php
}

/**
 * The window of one provider: the steps, then the two fields, the save
 * button last (CLAUDE.md — saving is the last thing in a window).
 *
 * WIDE (.form-window.is-wide) because it shows an address that has to be
 * read whole and copied character for character.
 *
 * Once set up, the RETURN ADDRESS comes first: one opens this window again
 * mostly because the provider refuses, and then the address is what to
 * compare. The steps wait behind a question mark then. (the call of
 * 17.08.2026, when the card still vanished after saving and took the
 * address with it: it is not needed while setting up but AFTERWARDS, when
 * the provider refuses consent — exactly that happened after pages/ was
 * renamed, with „This 'redirect_uri' is not authorized", an error that
 * names the cause but not the current value.)
 *
 * THE ADDRESS IS COMPUTED, NOT WRITTEN OUT (cloud_return_url): it has to
 * match what the provider stores character for character and depends on
 * host and base path. A line copied from here is right by construction.
 */
function cloud_provider_window_render(string $key): void
{
    $name   = CLOUD_PROVIDERS[$key]['name'];
    $source = cloud_app_source($key);
    $app    = $source !== null ? cloud_app($key) : null;
    $target = url('pages/administration.php') . '#cloud_providers';
    ?>
<dialog class="form-window is-wide cloud-provider-window pinned-footer" id="cloud-provider-<?= h($key) ?>">
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="cloud_providers">
        <input type="hidden" name="provider" value="<?= h($key) ?>">
        <h2 class="dialog-title"><?= h($source === null ? t('%s einrichten', $name) : $name) ?></h2>

        <?php if ($source === null): ?>
            <p class="hint-text"><?= h(t('Melde YASAIMAPP in %d Schritten bei %s an. Am Ende trägst du zwei Angaben hier unten ein.',
                count(cloud_provider_steps($key)), $name)) ?></p>
            <ol class="cloud-steps">
                <?php foreach (cloud_provider_steps($key) as $step): ?><li><?= $step ?></li><?php endforeach; ?>
            </ol>
        <?php else: ?>
            <?php /* THE CLIENT ID MAY STAND THERE, the secret may not. It
                     stands openly in the provider's console too and is
                     exactly the detail one compares to see whether the same
                     app is stored here as there. */ ?>
            <p class="hint-text"><?= $source === 'config'
                ? t('Eingerichtet in der <code>config.php</code>, mit der Client-ID <code>%s</code>. Lehnt %s beim Verbinden ab, vergleiche dort die Rücksprungadresse mit dieser:', h($app['client_id']), h($name))
                : t('Eingerichtet mit der Client-ID <code>%s</code>. Lehnt %s beim Verbinden ab, vergleiche dort die Rücksprungadresse mit dieser:', h($app['client_id']), h($name)) ?></p>
            <pre class="cloud-code"><?= h(cloud_return_url()) ?></pre>
            <details class="info-fold">
                <summary><?= icon('help') ?><?= h(t('Wie wurde das eingerichtet?')) ?></summary>
                <ol class="cloud-steps">
                    <?php foreach (cloud_provider_steps($key) as $step): ?><li><?= $step ?></li><?php endforeach; ?>
                </ol>
            </details>
        <?php endif; ?>

        <?php $scope = cloud_provider_scope($key); ?>
        <?php if ($scope !== []): ?>
            <details class="info-fold">
                <summary><?= icon('help') ?><?= h(t('Was darf YASAIMAPP in %s?', $name)) ?></summary>
                <?php foreach ($scope as $sentence): ?><p class="hint-text"><?= h($sentence) ?></p><?php endforeach; ?>
            </details>
        <?php endif; ?>

        <?php if ($source === 'config'): ?>
            <p class="hint-text"><?= h(t('Ändern geht nur in der config.php auf dem Server.')) ?></p>
            <?php if (!empty(CLOUD_PROVIDERS[$key]['expiring_secret'])): ?>
                <p class="hint-text"><?= t('Bis wann das Secret gilt, steht dort als <code>\'secret_expires\' => \'2028-10-02\'</code> neben der Client-ID. Dann erinnert dich YASAIMAPP einen Monat vorher.') ?></p>
            <?php endif; ?>
            <div class="dialog-footer">
                <button type="button" class="button subtle" data-close-window><?= h(t('Schließen')) ?></button>
            </div>
        <?php else: ?>
            <?php if ($source === 'vault'): ?>
                <p class="hint-text"><?= h(t('Neue Angaben ersetzen die alten:')) ?></p>
            <?php endif; ?>
            <?php /* Required only for a first registration: once one is on
                     file, an empty pair keeps it, so the date alone can be
                     saved (cloud_providers_process). */ ?>
            <?php $required = $source === null ? ' required' : ''; ?>
            <div class="field">
                <label for="client-id-<?= h($key) ?>"><?= h(t('Client-ID')) ?></label>
                <input type="text" id="client-id-<?= h($key) ?>" name="client_id" autocomplete="off"
                       spellcheck="false"<?= $required ?>>
            </div>
            <div class="field">
                <label for="client-secret-<?= h($key) ?>"><?= h(t('Client-Secret')) ?></label>
                <?php /* type=password, so that it is not read along in passing —
                     and autocomplete off, because no password manager is
                     meant to suggest anything here. */ ?>
                <input type="password" id="client-secret-<?= h($key) ?>" name="client_secret"
                       autocomplete="new-password" spellcheck="false"<?= $required ?>>
            </div>
            <?php if (!empty(CLOUD_PROVIDERS[$key]['expiring_secret'])): ?>
                <?php /* „GÜLTIG BIS", NOT „ERSTELLT AM" (the maintainer asked for
                         the creation day, 02.10.2026): Microsoft offers six
                         months as the default and lets one pick 3 to 24, so
                         creation day plus 24 months would remind up to
                         eighteen months too late. The end date stands in
                         Microsoft's list of secrets and is the one number
                         that cannot be wrong. */ ?>
                <div class="field">
                    <label for="secret-expires-<?= h($key) ?>"><?= h(t('Secret gilt bis')) ?></label>
                    <input type="date" id="secret-expires-<?= h($key) ?>" name="secret_expires"
                           value="<?= h((string) cloud_app_expiry($key)) ?>"<?= $required ?>>
                    <p class="hint-text small"><?= h(t('Das Datum zeigt dir Microsoft in der Liste der geheimen Clientschlüssel. Einen Monat vorher erinnert dich YASAIMAPP, auf der Karte und hier.')) ?></p>
                </div>
            <?php endif; ?>
            <div class="dialog-footer">
                <button type="button" class="button subtle" data-close-window><?= h(t('Abbrechen')) ?></button>
                <button type="submit" class="button" data-quiet-until-changed><?= h(t('Speichern')) ?></button>
            </div>
        <?php endif; ?>
    </form>
</dialog>
    <?php
}

/**
 * The steps at the provider, in plain words (02.10.2026). Each
 * one is OUR OWN MARKUP inside the t() key where a tag sits mid-sentence —
 * a sentence cut at a <code> could never be reordered by a translation —
 * so the caller does NOT h() them; every value put in is h()'d here.
 *
 * WHAT EACH STEP GUARDS, measured once and the reason it is said at all:
 *   pCloud    compares the return address character for character and
 *             refuses BEFORE the consent question appears.
 *   Drive     the API must be switched on, or every request is refused even
 *             with a valid token; „App veröffentlichen", or the refresh
 *             tokens die after seven days in test mode and every connection
 *             tears after a week without a word; the authorised domain is
 *             the registrable one (wkpilz.de, not www.); the secret is shown
 *             once since 2025. GOOGLE MOVED THE ROOMS IN 2025 (checked
 *             23.09.2026, when Finn was the first to try): the consent
 *             screen is the „Google Auth Platform" now, with Branding,
 *             Zielgruppe and Clients.
 *   OneDrive  the account type „… persönliche Microsoft-Konten", or a
 *             private OneDrive is refused with a message that does not name
 *             the setting; the secret lives at most 24 months.
 *
 * @return string[]
 */
function cloud_provider_steps(string $key): array
{
    $return = '<pre class="cloud-code">' . h(cloud_return_url()) . '</pre>';

    if ($key === 'gdrive') {
        $domain = (string) parse_url(cloud_return_url(), PHP_URL_HOST);
        if (substr_count($domain, '.') > 1) {
            $domain = implode('.', array_slice(explode('.', $domain), -2));
        }
        return [
            t('<strong>Projekt anlegen.</strong> Geh auf <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">console.cloud.google.com</a> und lege ein Projekt an. Ein kostenloses Google-Konto genügt.'),
            t('<strong>„Google Drive API" einschalten.</strong> Unter <em>APIs und Dienste → Bibliothek</em>. Ohne diesen Schritt lehnt Google später alles ab.'),
            t('<strong>Die App beschreiben und veröffentlichen.</strong> In der <em>Google Auth Platform</em> (im Menü links). Google fragt nach App-Name, Support-Adresse und Kontakt. Wähle als Zielgruppe <strong>„Extern"</strong>. Den App-Namen sehen die Nutzer später beim Verbinden.')
                . '<br>' . t('Unter <em>Branding → Autorisierte Domains</em> trägst du ein: <code>%s</code>', h($domain))
                . '<br>' . t('<strong>Wichtig:</strong> Drück danach unter <em>Zielgruppe</em> auf „App veröffentlichen". Sonst reißt jede Verbindung nach sieben Tagen ab, ohne Meldung.'),
            t('<strong>Den Zugang anlegen.</strong> Unter <em>Clients → Client erstellen</em>, Typ <em>Webanwendung</em>. Als <em>autorisierte Weiterleitungs-URI</em> trägst du genau diese Adresse ein:')
                . $return
                . t('Google zeigt dir danach die Client-ID und das Client-Secret. <strong>Das Secret siehst du nur dieses eine Mal.</strong> Kopiere es sofort.'),
        ];
    }
    if ($key === 'onedrive') {
        return [
            t('<strong>App bei Microsoft anmelden.</strong> Geh auf <a href="https://entra.microsoft.com/" target="_blank" rel="noopener noreferrer">entra.microsoft.com</a> zu <em>App-Registrierungen → Neue Registrierung</em>. Ein kostenloses Microsoft-Konto genügt. Wähle bei den Kontotypen <strong>„Konten in einem beliebigen Organisationsverzeichnis und persönliche Microsoft-Konten"</strong>. Sonst lehnt Microsoft ein privates OneDrive ab.'),
            t('<strong>Die Rücksprungadresse eintragen.</strong> Bei der Registrierung oder später unter <em>Authentifizierung</em>, Plattform <em>Web</em>, genau diese Adresse:')
                . $return,
            t('<strong>Ein Client-Secret anlegen.</strong> Unter <em>Zertifikate &amp; Geheimnisse → Neuer geheimer Clientschlüssel</em>. Den <em>Wert</em> zeigt Microsoft nur einmal. Kopiere ihn sofort.')
                . '<br>' . t('<strong>Wichtig:</strong> Wähle dabei die längste Laufzeit, 24 Monate. Microsoft schlägt sonst nur 6 Monate vor. Bis wann das Secret gilt, trägst du hier unten ein. Dann erinnert dich YASAIMAPP rechtzeitig.'),
            t('<strong>Die Client-ID abschreiben.</strong> Sie heißt bei Microsoft <em>Anwendungs-ID (Client)</em> und steht auf der Übersicht der Registrierung.'),
        ];
    }
    return [
        t('<strong>App bei pCloud anlegen.</strong> Melde dich auf <a href="https://docs.pcloud.com/my_apps/" target="_blank" rel="noopener noreferrer">docs.pcloud.com/my_apps</a> an und wähle „New app". Den Namen der App sehen die Nutzer später beim Verbinden.'),
        t('<strong>Die Rücksprungadresse eintragen.</strong> Als <em>Redirect URI</em> trägst du genau diese Adresse ein:')
            . $return
            . t('Ohne Schrägstrich am Ende. pCloud vergleicht Zeichen für Zeichen und lehnt sonst ab.'),
        t('<strong>Client-ID und Client-Secret abschreiben.</strong> pCloud zeigt beides bei der App an.'),
    ];
}

/**
 * What the app may do in the provider's storage — behind a question mark in
 * the window, because it answers a worry, not a step.
 *
 * @return string[]
 */
function cloud_provider_scope(string $key): array
{
    return match ($key) {
        'gdrive' => [
            t('Nur die Dateien, die YASAIMAPP selbst anlegt. Alles andere im Drive bleibt unsichtbar, auch Fotos und Dokumente. Genau das steht auch auf der Frage, die Google beim Verbinden stellt. Enger geht es bei Google nicht.'),
            t('Darum prüft Google die App nicht. Eine Warnung vor einer „nicht überprüften App" ist nicht zu erwarten.'),
        ],
        'onedrive' => [
            t('Nur einen eigenen Ordner unter „Apps". Das übrige OneDrive bleibt unsichtbar. Du musst dafür keine Berechtigungen freigeben, und die Nutzer müssen nichts extra erlauben.'),
        ],
        default => [],
    };
}
