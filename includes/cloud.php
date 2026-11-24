<?php
/**
 * Cloud — each user's own storage space.
 *
 * WHY THIS EXISTS
 * EVERYTHING about a find in full resolution belongs here: the voucher photo
 * from the phone as the original, micrographs, stacked camera shots, raw
 * files.
 *
 * Of the voucher photo, only the shrunken version stays on the server
 * (photos.php: 2048 px, shrunk in the browser so that uploading in the forest
 * gets through). That is no contradiction but the division of labor: the
 * small version is the one you see on the go and the one that gets synced;
 * the original weighs many times more and has no business on shared hosting
 * — a single user doing serious microscopy would fill it alone.
 *
 * That is why everyone brings their OWN storage. The server remembers where
 * it lies and how to get there; the files themselves never pass through it.
 *
 * TWO LEVELS, AND THEY MUST NOT BE CONFUSED (28.08.2026, after
 * the confusion had produced wrong sentences several times in one week —
 * "Das ist ein grundsätzliches Falschverstehen dieser Seite"):
 *
 *   THE APP REGISTRATION is per INSTALLATION. The administrator deposits
 *   client_id and secret for a provider once (the vault, further down),
 *   so this server can talk to that provider AT ALL. This is the only
 *   administrative act in the whole cloud story, and it grants the
 *   administrator access to nothing — it is a key to the provider's
 *   door, not to anybody's storage. AND IT HAS NO ADDRESSEE: it is done
 *   for the installation, never "for" a user — "die Google-App für den
 *   Freund registrieren" is already the wrong sentence, and it slipped
 *   through twice on the day this paragraph was written.
 *
 *   THE CONNECTION is per USER ACCOUNT, always. Every user connects
 *   THEIR OWN storage from THEIR OWN login. Nobody — the administrator
 *   included — can connect, inspect or manage a cloud for anybody else;
 *   there is no "einrichten für einen Freund", the friend logs in as
 *   themselves and presses Verbinden. The administrator's own connection
 *   is a user connection like any other, and the administrator has NO
 *   access to the clouds of the others.
 *
 * Whoever writes a sentence, a comment or a warning that has the
 * administrator doing something with another account's cloud has fallen
 * into exactly the hole this paragraph fences off.
 *
 * THE SECRET DOES NOT LEAVE THIS FILE
 * An OAuth token is a password equivalent: whoever has it gets at the user's
 * entire cloud storage — not just at what has to do with this application.
 * That is why a hard rule applies here:
 *
 *   The read functions DO NOT EVEN FETCH the 'secret' column.
 *   Whoever needs it calls cloud_secret() — and only the code that
 *   knocks at the provider's door with it does that.
 *
 * That is stricter than necessary and deliberately so: a value that never
 * sits in an array that goes out somewhere as JSON cannot go out by accident
 * either. Exactly this mistake otherwise happens at the next "just give me
 * all connections for the overview".
 *
 * AS OF 15.08.2026: THE SCAFFOLDING STANDS, NOTHING IS HOOKED UP YET.
 * The call was explicitly "only the scaffolding, providers later" —
 * tab, table and interface such that pCloud, Google Drive, OneDrive and S3
 * fit alongside, but without anything that needs credentials. What is
 * missing is stated per provider below in CLOUD_PROVIDERS.
 */

declare(strict_types=1);

require_once __DIR__ . '/picture_fence.php';

/**
 * The providers that are planned.
 *
 * TWO KINDS OF CONNECTION, and that is the reason 'kind' is here:
 *
 *   oauth      You are sent to the provider, say yes there, and come back
 *              with a token. Needs an app registered there
 *              (client_id + secret) and a fixed redirect address.
 *   schluessel You enter key and secret yourself — with S3 the usual way.
 *              No redirect, no registered app, but in return the credentials
 *              lie in plain text with the user until they enter them here.
 *
 * The interface must handle both forms; that is why the distinction sits
 * here and not as an if in the markup.
 *
 * 'ready' says whether the provider REALLY works already. As long as
 * nothing is hooked up, it is false everywhere — and the interface says so
 * honestly instead of offering a button that does nothing.
 *
 * TWO SENTENCES PER PROVIDER (23.09.2026): 'hint' speaks to whoever has
 * not connected yet (what „Verbinden" leads to), 'connected_hint' to
 * whoever has — the tile of a connected storage used to keep inviting its
 * owner to connect. What stays true after connecting (which folder the app
 * sees) is said in both; what goes into the storage is said beside them by
 * cloud_card_goods(), from the release lists.
 *
 * THE 'hint' SENTENCES ARE TRANSLATED AT THE RENDER SITE, not here: a
 * const cannot call t(). The German sentence below IS the catalog key —
 * whoever rewords one rewords the key in includes/lang/*.php with it.
 */
const CLOUD_PROVIDERS = [
    'pcloud' => [
        'name'    => 'pCloud',
        'kind'     => 'oauth',
        'ready'  => true,
        // pCloud runs two separate data centers with THEIR OWN addresses:
        // api.pcloud.com and eapi.pcloud.com. An account lives in exactly
        // one of them, and the wrong address does not answer with
        // "elsewhere" but with "login failed". Whoever does not know that
        // hunts for the mistake in the password. The hint said so until
        // 02.10.2026 („merkt sich die Karte selbst") and went: nobody has
        // to know it, the return journey remembers it (cloud_return.php).
        'hint' => 'Nach „Verbinden" fragt dich pCloud, ob YASAIMAPP an'
                   . ' deinen Speicher darf. Sag ja. Dann kommst du hierher'
                   . ' zurück, und alles ist fertig.',
        'connected_hint' => '',
    ],
    'gdrive' => [
        'name'    => 'Google Drive',
        'kind'     => 'oauth',
        'ready'  => true,
        // What goes here — backup, and pictures once released — is said by
        // cloud_card_goods() beside this, from the release list: a sentence
        // written into the hint would stay „vorerst nur zu pCloud" the day
        // the list changes.
        'hint' => 'Nach „Verbinden" fragt dich Google, ob YASAIMAPP an'
                   . ' deinen Speicher darf. Sag ja. Dann kommst du hierher'
                   . ' zurück, und alles ist fertig. YASAIMAPP sieht dort'
                   . ' nur einen eigenen Ordner, nicht deine anderen Dateien.',
        'connected_hint' => 'YASAIMAPP sieht dort nur einen eigenen Ordner,'
                   . ' nicht deine anderen Dateien.',
    ],
    'onedrive' => [
        'name'    => 'OneDrive',
        'kind'     => 'oauth',
        'ready'  => true,
        // ITS CLIENT SECRET EXPIRES — 24 months at most, and Microsoft
        // offers six as the default. After that refreshing fails and every
        // connection tears within the hour. So its window asks for the date
        // and the administrator is reminded a month before
        // (cloud_secret_warnings). Google's and pCloud's secrets do not
        // expire.
        'expiring_secret' => true,
        // What goes here is said by cloud_card_goods(), as at Drive.
        'hint' => 'Nach „Verbinden" fragt dich Microsoft, ob YASAIMAPP an'
                   . ' deinen Speicher darf. Sag ja. Dann kommst du hierher'
                   . ' zurück, und alles ist fertig. YASAIMAPP sieht dort'
                   . ' nur einen eigenen Ordner unter „Apps", nicht deine'
                   . ' anderen Dateien.',
        'connected_hint' => 'YASAIMAPP sieht dort nur einen eigenen Ordner'
                   . ' unter „Apps", nicht deine anderen Dateien.',
    ],
    's3' => [
        'name'    => 'S3-Speicher',
        'kind'     => 'key',
        'ready'  => true,
        // Deliberately not "Amazon S3": Hetzner, Wasabi, Backblaze and a
        // dozen others speak the same interface, and for the user that is
        // exactly the point.
        // „Für Fortgeschrittene" first: it tells a beginner at once that
        // this tile is not for them (02.10.2026).
        'hint' => 'Für Fortgeschrittene: ein Speicher mit S3-Zugang, etwa'
                   . ' bei Hetzner, Wasabi oder Backblaze. Hier fragt dich'
                   . ' niemand nach Zustimmung. Du trägst fünf Angaben selbst'
                   . ' ein.',
        // What goes here is said by cloud_card_goods(), as at Drive.
        'connected_hint' => '',
    ],
];

/**
 * THE CLOUDS PICTURES MAY GO TO — each only after somebody has tried it
 * with a real account (26.09.2026, when asked for test users:
 * „Finn könnte Google Drive testen"). The code speaks all four; these
 * lists say which have been PROVEN. A provider not on them keeps its
 * users' voucher photos on this server, as before any cloud: nothing is
 * lost, it waits, and the next sweep after the release takes it along.
 *
 * TWO LISTS, because two ways lead up: the voucher photos, which this
 * server pushes (photo_to_cloud), and the recordings and calibration
 * images, which the BROWSER uploads straight to the provider, each by its
 * own way in (cloud_upload_target, cloudsend.js) — two things to try, and
 * one may be proven before the other.
 *
 * WHOEVER RELEASES A PROVIDER adds it here; the texts follow by
 * themselves — the Cloud tab's cards (cloud_card_goods), the presets' gate
 * (cloud_names_or), the delete question and the folder link in map.js —
 * and so does the move: a cloud on BOTH lists is offered as a target
 * (cloud_move_targets).
 *
 * ONE THING DOES NOT FOLLOW: the co-owners' copies. Their backfill runs
 * when a cloud is CONNECTED (cloud_connect), so whoever connected this
 * provider before its release gets new pictures copied, but not the ones
 * already lying on his shared finds. Before a release, look who has it
 * connected (map_cloud_accounts) and run cloud_copies_backfill_user() for
 * each — on 26.09.2026 nobody live had Drive, OneDrive or S3 connected,
 * and Finn connects Drive only after its release.
 */
/* GOOGLE DRIVE ON BOTH SINCE 03.10.2026 („schalte Drive frei"),
   the day after he registered the app with Google — released AHEAD of
   Finn's test so that Finn connects into a cloud that already takes
   pictures and „Auswählen", and needs no backfill. The principle behind it
   (02.10.2026): a cloud is there for the pictures; these lists
   are a test guard, never a setting. */
const CLOUD_PICTURE_PROVIDERS = ['pcloud', 'gdrive'];
const CLOUD_UPLOAD_PROVIDERS  = ['pcloud', 'gdrive'];

/**
 * Is a cloud connected that takes PICTURES — one on the list above, with
 * a secret to knock with? Every gate that needs a place for a picture the
 * server pushes asks here, so a release is one line and every gate follows.
 */
function cloud_pictures_ready(PDO $pdo, int $userId): bool
{
    return cloud_released_provider($pdo, $userId, CLOUD_PICTURE_PROVIDERS) !== null;
}

/**
 * The storage NEW pictures go into, opened for use — the twin of the
 * question above, bound to the same list. A picture already lying
 * somewhere is read, moved and deleted where it lies (cloud_storage_for),
 * whatever this says.
 */
function cloud_pictures_storage(PDO $pdo, int $userId): ?array
{
    $provider = cloud_released_provider($pdo, $userId, CLOUD_PICTURE_PROVIDERS);
    return $provider === null ? null : cloud_storage_for($pdo, $userId, $provider);
}

/** Can the BROWSER upload straight into this user's cloud — recordings, calibration images? */
function cloud_uploads_ready(PDO $pdo, int $userId): bool
{
    return cloud_released_provider($pdo, $userId, CLOUD_UPLOAD_PROVIDERS) !== null;
}

/** The storage the browser uploads into, opened for use — the twin of the question above. */
function cloud_uploads_storage(PDO $pdo, int $userId): ?array
{
    $provider = cloud_released_provider($pdo, $userId, CLOUD_UPLOAD_PROVIDERS);
    return $provider === null ? null : cloud_storage_for($pdo, $userId, $provider);
}

/**
 * The type a picture's name says — for the clouds that hand back what they
 * were told (Drive, S3): the delivery serves only image/* as a picture.
 */
function cloud_picture_type(string $name): string
{
    return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'         => 'image/png',
        'heic'        => 'image/heic',
        'heif'        => 'image/heif',
        'webp'        => 'image/webp',
        'gif'         => 'image/gif',
        'tif', 'tiff' => 'image/tiff',
        'avif'        => 'image/avif',
        default       => 'application/octet-stream',
    };
}

/**
 * A file name as the browser sent it, made fit for every provider — what
 * file systems refuse goes, as in the photo names (photo_cloud_name).
 * Empty stays empty: the caller refuses it.
 */
function cloud_file_name_clean(string $raw): string
{
    $name = preg_replace('/[\/\\\\:*?"<>|\x00-\x1f]+/u', ' ', $raw) ?? '';
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '', " .\t");
    // A gap left before the extension by what was taken out goes too.
    $name = preg_replace('/\s+(\.[A-Za-z0-9]+)$/u', '$1', $name) ?? $name;
    return mb_substr($name, 0, 200);
}

/**
 * WHERE THE BROWSER SENDS ONE FILE — the provider's own way, opened here
 * (26.09.2026, step three of the picture plan). The browser module that
 * uses it is assets/js/cloudsend.js; together they are one protocol:
 *
 *   pcloud    POST to uploadfile with the token — the one provider whose
 *             token is fenced to a private app folder (api/v1/cloud.php
 *             says why that is acceptable there, and only there)
 *   gdrive    PUT to a resumable session opened here for this one file
 *   onedrive  PUT byte ranges to a pre-authenticated session address
 *   s3        PUT to an address signed in advance for this one key; the
 *             bucket's CORS rule is made sure of first, and — for
 *             recordings — the two preview addresses ride along, because
 *             S3 draws no previews of its own
 *
 * No token but pCloud's ever reaches the browser: the others hand out an
 * address good for exactly this file and nothing else.
 *
 * @param bool $previews S3 only: hand out addresses for the 320 and 2048
 *                       previews too (s3_preview_key)
 */
function cloud_upload_target(array $s, string|int $folderId, string $name, string $type,
                             int $size, ?int $mtime, ?string $home = null, bool $previews = false): array
{
    switch ($s['provider']) {
        case 'gdrive':
            return ['provider' => 'gdrive',
                    'url' => gdrive_upload_session($s['token'], (string) $folderId, $name, $type, $size, $mtime,
                        cloud_origin())];
        case 'onedrive':
            return ['provider' => 'onedrive',
                    'url' => onedrive_upload_session($s['token'], (string) $folderId, $name, $mtime)];
        case 's3':
            try {
                s3_cors_allow($s['token'], cloud_origin());
            } catch (RuntimeException $e) {
                // A key that may not touch the bucket's CORS: the rule may
                // have been set by hand — the upload tells, not this.
                error_log('s3_cors_allow: ' . $e->getMessage());
            }
            $at  = s3_upload_target($s['token'], (string) $folderId, $name);
            $out = ['provider' => 's3', 'url' => $at['url'], 'key' => $at['key']];
            if ($previews) {
                $out['previews'] = [
                    'small' => s3_presigned($s['token'], 'PUT', s3_preview_key((string) $home, $at['key'], 320), 3600),
                    'large' => s3_presigned($s['token'], 'PUT', s3_preview_key((string) $home, $at['key'], 2048), 3600),
                ];
            }
            return $out;
        default:
            return ['provider' => 'pcloud', 'url' => 'https://' . $s['host'] . '/uploadfile', 'token' => $s['token']];
    }
}

/** The first connected provider on a release list that has a secret — or null. */
function cloud_released_provider(PDO $pdo, int $userId, array $released): ?string
{
    foreach (cloud_connections($pdo, $userId) as $provider => $connection) {
        if (in_array((string) $provider, $released, true)
            && cloud_secret($pdo, $userId, (string) $provider) !== null) {
            return (string) $provider;
        }
    }
    return null;
}

/**
 * The cloud a running move is emptying, or null — the one account row
 * that is kept for its key and does not count as connected
 * (cloud_connections; includes/cloud_moves.php tells the switch).
 */
function cloud_retiring_provider(PDO $pdo, int $userId): ?string
{
    if (!table_exists($pdo, 'map_cloud_moves')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT from_provider FROM map_cloud_moves WHERE user_id = ? AND status = "running" LIMIT 1');
    $stmt->execute([$userId]);
    $provider = $stmt->fetchColumn();
    return $provider === false ? null : (string) $provider;
}

/** Does the table exist yet? The code is live before the migration. */
function cloud_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_cloud_accounts');
}

/**
 * The vault: what the administrator has entered through the interface.
 *
 * WHY A FILE AND NOT THE DATABASE. It holds the key with which the users'
 * tokens are encrypted. Lying next to the encrypted rows it would protect
 * nothing at all — whoever gets the dump would get the lock and its key in
 * one go, and the encryption would be decoration. Outside the database the
 * promise holds: a stolen dump yields noise.
 *
 * WHY UNDER data/. The directory is barred twice over — "Deny from all" in
 * its own .htaccess and a RedirectMatch 404 in the one at the root — and the
 * application already writes there (the users' photos). It is not in the
 * repository, so deployment neither carries it along nor overwrites it.
 *
 * PHP AND NOT JSON, for one reason: should the bars ever fail, a .php file is
 * executed and gives out nothing. A .json would be handed over verbatim.
 */
function cloud_vault_path(): string
{
    return PROJECT_PATH . '/data/cloud.php';
}

/** What is in the vault. Empty array if there is none. */
function cloud_vault(): array
{
    if (isset($GLOBALS['__cloud_vault'])) {
        return $GLOBALS['__cloud_vault'];
    }

    $path = cloud_vault_path();
    // include and not require: a missing vault is the normal state before
    // setup, not an error.
    $d = is_file($path) ? @include $path : null;

    return $GLOBALS['__cloud_vault'] = is_array($d) ? $d : [];
}

/**
 * Write the vault.
 *
 * WRITTEN BESIDE IT AND THEN MOVED OVER. A file that is written to directly
 * is briefly half there — and if the process dies in that moment, the key is
 * gone and with it every stored access. rename() within the same directory
 * is atomic: either the old file stands there or the new one, never a torn
 * one.
 *
 * @throws RuntimeException if the directory does not take it
 */
function cloud_vault_write(array $data): void
{
    $path = cloud_vault_path();
    $folder = dirname($path);

    if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
        throw new RuntimeException(t('Das Verzeichnis data/ lässt sich nicht anlegen.'));
    }

    $content = "<?php\n"
        . "// Written by the application (Administration → Cloud-Anbieter). Do not edit by\n"
        . "// hand unless you know what you are doing: 'key' unlocks the\n"
        . "// users' stored cloud tokens. Changing it makes every existing\n"
        . "// access unreadable.\n"
        . "return " . var_export($data, true) . ";\n";

    $temp = $path . '.' . bin2hex(random_bytes(4));
    if (@file_put_contents($temp, $content, LOCK_EX) === false || !@rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException(
            t('Die Datei data/cloud.php lässt sich nicht schreiben — dem Server fehlt das Recht dazu. Dann bleibt der Weg über die config.php.')
        );
    }

    @chmod($path, 0640);

    // The static cache in cloud_vault() is now stale — and the very next
    // thing the caller does is read it back to show the result.
    cloud_vault_reread();
}

/** Forget the cached vault. Only needed after writing. */
function cloud_vault_reread(): void
{
    // A static inside a function cannot be reset from outside; the vault is
    // therefore read through a function whose cache lives in a global.
    $GLOBALS['__cloud_vault'] = null;
}

/**
 * This installation's app credentials for a provider.
 *
 * THEY BELONG TO THE INSTALLATION, NOT TO THE USER: an app is registered once
 * at the provider, after that everyone connects their own account.
 *
 * TWO SOURCES, AND config.php WINS. Whoever has entered it by hand keeps
 * control — an interface that silently overrules a hand-written file would be
 * the worse surprise of the two.
 *
 * @return array{client_id: string, client_secret: string}|null
 */
function cloud_app(string $provider): ?array
{
    global $config;

    $a = $config['cloud'][$provider] ?? null;
    if (!is_array($a) || trim((string) ($a['client_id'] ?? '')) === '') {
        $a = cloud_vault()[$provider] ?? null;
    }
    if (!is_array($a)) {
        return null;
    }
    $id     = trim((string) ($a['client_id'] ?? ''));
    $secret = trim((string) ($a['client_secret'] ?? ''));

    // Half filled in is not set up. Otherwise the button would lead all the
    // way to the provider and only fail at the exchange — with a message that
    // looks like the user's mistake.
    return ($id !== '' && $secret !== '') ? ['client_id' => $id, 'client_secret' => $secret] : null;
}

/**
 * Until when a provider's client secret is valid, as Y-m-d — only for a
 * provider whose secret expires ('expiring_secret', OneDrive), and only
 * where the administrator wrote the date down. The same two sources in the
 * same order as cloud_app(): the date belongs to the secret it stands with.
 * In config.php it is 'secret_expires' beside client_id and client_secret.
 */
function cloud_app_expiry(string $provider): ?string
{
    global $config;

    if (empty(CLOUD_PROVIDERS[$provider]['expiring_secret'])) {
        return null;
    }
    $source = cloud_app_source($provider);
    $a = match ($source) {
        'config' => $config['cloud'][$provider] ?? [],
        'vault'  => cloud_vault()[$provider] ?? [],
        default  => [],
    };
    $date = trim((string) ($a['secret_expires'] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
}

/** How many days before the end the administrator is reminded — „ein Monat vorher". */
const CLOUD_SECRET_WARN_DAYS = 30;

/**
 * The client secrets that run out within CLOUD_SECRET_WARN_DAYS or already
 * have (02.10.2026: „benachrichtigst, wenn die vierundzwanzig
 * Monate um sind. Oder natürlich ein Monat vorher"). For the administrator
 * only — the map's reminder window and the administration's notice both
 * read this, so the two can never name different dates.
 *
 * @return array<int, array{provider: string, name: string, expires: string, expired: bool}>
 */
function cloud_secret_warnings(): array
{
    $today = new DateTimeImmutable('today');
    $out = [];
    foreach (CLOUD_PROVIDERS as $key => $a) {
        $date = cloud_app_expiry($key);
        if ($date === null) {
            continue;
        }
        $end = new DateTimeImmutable($date);
        if ($end > $today->modify('+' . CLOUD_SECRET_WARN_DAYS . ' days')) {
            continue;
        }
        $out[] = ['provider' => $key, 'name' => $a['name'], 'expires' => $date, 'expired' => $end < $today];
    }
    return $out;
}

/**
 * The warning sentence for one entry of cloud_secret_warnings() — one place,
 * because the map's window and the administration say the same.
 */
function cloud_secret_warning_text(array $w): string
{
    $when = date('d.m.Y', strtotime($w['expires']));
    return $w['expired']
        ? t('Das Client-Secret von %s ist am %s abgelaufen. Bis du ein neues einträgst, kann sich niemand mit %s verbinden, und auch bestehende Verbindungen reißen ab.', $w['name'], $when, $w['name'])
        : t('Das Client-Secret von %s läuft am %s ab. Leg vorher ein neues an und trag es unter Administration → Cloud-Anbieter ein. Sonst reißen alle Verbindungen zu %s ab.', $w['name'], $when, $w['name']);
}

/**
 * Where the registration of cloud_app() comes from: 'config', 'vault', or
 * null while there is none.
 *
 * SAID IN THE ADMINISTRATION (Cloud-Anbieter, 02.10.2026), because config.php
 * wins silently: whoever saved new values in the window while the file still
 * held old ones would see „Gespeichert" and nothing change. The same order
 * as cloud_app(), so the two can never disagree.
 */
function cloud_app_source(string $provider): ?string
{
    global $config;

    if (cloud_app($provider) === null) {
        return null;
    }
    $a = $config['cloud'][$provider] ?? null;
    return (is_array($a) && trim((string) ($a['client_id'] ?? '')) !== '') ? 'config' : 'vault';
}

/**
 * Is this provider REALLY usable?
 *
 * Two conditions, both of which must hold: the code has to support it
 * ('ready' in CLOUD_PROVIDERS) AND this installation has to be set up for it.
 * Without the second a freshly deployed server would show a connect button
 * leading nowhere.
 */
function cloud_provider_usable(string $provider): bool
{
    if (empty(CLOUD_PROVIDERS[$provider]['ready'])) {
        return false;
    }
    return CLOUD_PROVIDERS[$provider]['kind'] !== 'oauth' || cloud_app($provider) !== null;
}

/**
 * The address the provider sends the user back to after consent.
 *
 * IT MUST BE STORED AT THE PROVIDER WORD FOR WORD — scheme included, query
 * part excluded. pCloud compares it character by character and otherwise
 * refuses before the user gets to see anything at all.
 */
function cloud_return_url(): string
{
    /* ALSO ASK THE FORWARDED HEADER, not just $_SERVER['HTTPS']. Where the
       hoster terminates TLS in front of PHP — the usual arrangement on
       shared hosting — the variable stays empty although the visitor came
       over https. The address built from it would then say http://, the
       provider would compare it against the https one on file and refuse
       before the consent question ever appears. The header is only
       trustworthy behind such a proxy, and that is exactly where it is
       needed; a visitor cannot forge it into a REQUEST that reaches PHP
       here without passing that proxy. */
    $forwarded = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || $forwarded === 'https'
           || ((int) ($_SERVER['SERVER_PORT'] ?? 0)) === 443;

    return ($secure ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
         . url('pages/cloud_return.php');
}

/**
 * The origin the browser talks from — scheme and host, no path. Named to
 * a provider for the browser's own uploads (26.09.2026): Drive answers a
 * session only for the origin that opened it, and S3's CORS rule names it.
 * The same reasoning about the forwarded header as above.
 */
function cloud_origin(): string
{
    $forwarded = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || $forwarded === 'https'
           || ((int) ($_SERVER['SERVER_PORT'] ?? 0)) === 443;
    // Only what a host name may contain — the header comes from the request.
    $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    return ($secure ? 'https' : 'http') . '://' . $host;
}

/**
 * Bytes the way a human reads them.
 *
 * HERE AND NOT IN THE TAB'S VIEW, where it stood until 26.09.2026: the
 * copy worker and storage_file_bytes() name a file's size when it is too
 * big to carry, and they run in the cron job, which never loads the view
 * — the one message meant to explain a refusal would have died of an
 * undefined function instead.
 */
function cloud_size(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 1, ',', '.') . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    return number_format($bytes / 1024, 0, ',', '.') . ' kB';
}

/**
 * The providers' names as one says them — „pCloud", „pCloud oder Google
 * Drive", „pCloud, Google Drive oder OneDrive" — for a sentence that names
 * which clouds something works with.
 *
 * @param string[] $providers
 */
function cloud_names_or(array $providers): string
{
    $names = array_map(fn (string $p): string => CLOUD_PROVIDERS[$p]['name'] ?? $p, array_values($providers));
    $last  = array_pop($names);
    return $names === [] ? (string) $last : t('%s oder %s', implode(', ', $names), $last);
}

/**
 * WHAT GOES INTO THIS CLOUD, said on its card from the release lists
 * (26.09.2026) — the backup everywhere, the pictures once the provider is
 * released (CLOUD_PICTURE_PROVIDERS). Until then the card says plainly
 * that they wait, so nobody waits for pictures that never move.
 */
function cloud_card_goods(string $provider): string
{
    return in_array($provider, CLOUD_PICTURE_PROVIDERS, true)
        ? t('Hierher gehen die Datensicherung und deine Fotos.')
        : t('Hierher geht vorerst nur die Datensicherung. Deine Fotos bleiben noch auf dem Server, bis wir diesen Anbieter ausprobiert haben.');
}

/** Do we know this provider? */
function cloud_provider_valid(string $provider): bool
{
    return isset(CLOUD_PROVIDERS[$provider]);
}

// ---------------------------------------------------------------------------
// The secret at rest
// ---------------------------------------------------------------------------
//
// WHAT THIS PROTECTS AGAINST, AND WHAT IT DOES NOT. Say it plainly, because a
// half-understood promise is worse than none:
//
//   IT PROTECTS against a stolen database dump, against a backup at the host,
//   against SQL injection, against a curious look into the table. Without the
//   key from config.php the column is noise — and config.php is neither in the
//   repository nor in the dump nor, on the live server, inside the web root.
//
//   IT DOES NOT PROTECT against whoever operates this installation. They have
//   database AND config.php, so they can decrypt. There is no cryptography
//   against the operator in an application the operator runs; anyone claiming
//   otherwise is selling comfort. Whoever wants that needs the token never to
//   reach the server at all (OAuth token flow, secret stays in the browser) —
//   discussed and deliberately not chosen, because a connection would then be
//   per device.
//
// TWO PROCEDURES, because it cannot be measured what the host offers. libsodium
// ships with PHP by default and is the better choice; OpenSSL is the fallback.
// Both authenticate, so a manipulated value fails to decrypt rather than
// yielding rubbish. The stored value says which one made it, so the answer does
// not depend on what the server happens to have installed today.

/** Marker in front of every encrypted value — legacy plaintext has none. */
const CLOUD_MARK = 'enc:';

/**
 * The key from config.php, 32 bytes.
 *
 * NULL means: not set up. The callers decide what follows — storing refuses,
 * reading only fails for values that really are encrypted.
 */
function cloud_key(): ?string
{
    global $config;

    // Same order as with the app credentials: a hand-written config.php wins,
    // the vault written through the interface fills in. 'schluessel' is the
    // name both files carried before the move to English — they lie on the
    // servers, outside the deployment, so the old name keeps being read.
    $raw = trim((string) ($config['cloud']['key'] ?? $config['cloud']['schluessel'] ?? ''));
    if ($raw === '') {
        $vault = cloud_vault();
        $raw = trim((string) ($vault['key'] ?? $vault['schluessel'] ?? ''));
    }
    if ($raw === '') {
        return null;
    }

    $key = base64_decode($raw, true);

    // A key of the wrong length is a typo, not a key. Failing loudly here is
    // better than encrypting with something derived — that would still look
    // like it worked.
    return ($key !== false && strlen($key) === 32) ? $key : null;
}

/** Encrypt, with whatever this server has. */
function cloud_encrypt(string $plain): string
{
    $key = cloud_key();
    if ($key === null) {
        throw new RuntimeException(
            t('In der config.php fehlt der Schlüssel für die Cloud-Zugänge (cloud.key). Ohne ihn wird kein Token gespeichert.')
        );
    }

    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return CLOUD_MARK . 's1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }

    $nonce = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($cipher === false) {
        throw new RuntimeException(t('Das Token liess sich nicht verschlüsseln.'));
    }

    // Nonce, authentication tag and ciphertext in one piece — in this order,
    // because both parts have a fixed length and the rest is the ciphertext.
    return CLOUD_MARK . 'o1:' . base64_encode($nonce . $tag . $cipher);
}

/**
 * Decrypt — and hand plaintext from before the changeover straight back.
 *
 * THE LEGACY PATH IS NOT COMFORT, IT IS THE HOUSE RULE: the code goes live
 * before anyone edits config.php. In that window there are rows written
 * without a key, and a connection that suddenly says "invalid" would send its
 * owner hunting at pCloud. They are re-encrypted on the next write.
 */
function cloud_decrypt(string $stored): ?string
{
    if (!str_starts_with($stored, CLOUD_MARK)) {
        return $stored;
    }

    $key = cloud_key();
    if ($key === null) {
        throw new RuntimeException(
            t('Der Schlüssel für die Cloud-Zugänge fehlt in der config.php — die gespeicherte Verbindung lässt sich ohne ihn nicht lesen.')
        );
    }

    $rest = substr($stored, strlen(CLOUD_MARK));
    $kind = substr($rest, 0, 3);
    $raw  = base64_decode(substr($rest, 3), true);
    if ($raw === false) {
        return null;
    }

    if ($kind === 's1:') {
        if (!function_exists('sodium_crypto_secretbox_open')) {
            throw new RuntimeException(t('Dieser Zugang wurde mit libsodium verschlüsselt, das auf diesem Server fehlt.'));
        }
        $n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        $plain = sodium_crypto_secretbox_open(substr($raw, $n), substr($raw, 0, $n), $key);
        return $plain === false ? null : $plain;
    }

    if ($kind === 'o1:') {
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA,
                                substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }

    return null;
}

// ---------------------------------------------------------------------------
// pCloud
// ---------------------------------------------------------------------------

/**
 * The two data centres, and nothing else.
 *
 * THIS IS A SECURITY BOUNDARY, not a convenience. pCloud names the API host on
 * the way back from consent, as a plain query parameter — and a query
 * parameter is whatever the browser carries, not whatever pCloud wrote. Anyone
 * can send a user back with hostname=example.invalid, and the very next thing
 * this code does is POST the app secret to that host to exchange the code.
 *
 * So the value is not used, it is only RECOGNISED: what is not in this list
 * counts as absent, and then the US address applies as the documented default.
 */
const PCLOUD_HOSTS = ['api.pcloud.com', 'eapi.pcloud.com'];

/** Consent runs at my.pcloud.com — the same address for both data centres. */
const PCLOUD_CONSENT = 'https://my.pcloud.com/oauth2/authorize';

const PCLOUD_TIMEOUT = 15;

/** Keep the host if it is one of ours, otherwise fall back to the US one. */
function pcloud_host(?string $raw): string
{
    $raw = mb_strtolower(trim((string) $raw));

    return in_array($raw, PCLOUD_HOSTS, true) ? $raw : PCLOUD_HOSTS[0];
}

/**
 * Where the user is sent to say yes.
 *
 * $state travels there and comes back untouched — it is what ties the return
 * to this session. Whoever leaves it out accepts a code from anywhere.
 */
function pcloud_consent_url(string $state): string
{
    $app = cloud_app('pcloud');
    if ($app === null) {
        throw new RuntimeException(t('pCloud ist auf diesem Server nicht eingerichtet.'));
    }

    return PCLOUD_CONSENT . '?' . http_build_query([
        'client_id'     => $app['client_id'],
        'response_type' => 'code',
        'redirect_uri'  => cloud_return_url(),
        'state'         => $state,
    ], '', '&', PHP_QUERY_RFC3986);
}

/**
 * One call to the pCloud API.
 *
 * EVERY ANSWER CARRIES 'result', AND 0 MEANS GOOD. pCloud answers with HTTP
 * 200 even for errors and puts the actual outcome in the body — whoever checks
 * only the status code takes "login failed" for a success.
 */
function pcloud_call(string $host, string $method, array $parameters): array
{
    $url = 'https://' . $host . '/' . $method;

    $response = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => http_build_query($parameters),
            'timeout'       => PCLOUD_TIMEOUT,
            'ignore_errors' => true,
        ],
    ]));

    if ($response === false) {
        throw new RuntimeException(t('pCloud antwortet nicht. Später noch einmal versuchen.'));
    }

    $d = json_decode($response, true);
    if (!is_array($d)) {
        throw new RuntimeException(t('pCloud antwortet unverständlich.'));
    }

    if ((int) ($d['result'] ?? -1) !== 0) {
        // The provider's own wording goes along: "Invalid 'code' provided" says
        // more than any sentence written here — and the number makes it
        // findable in pCloud's documentation.
        throw new RuntimeException(t(
            'pCloud lehnt ab (%d): %s',
            (int) ($d['result'] ?? -1),
            (string) ($d['error'] ?? t('ohne Angabe'))
        ));
    }

    return $d;
}

/**
 * Trade the code for a token.
 *
 * THE EXCHANGE HAS TO GO TO THE HOST FROM THE RETURN, not always to
 * api.pcloud.com. The documentation names the US address, and for a European
 * account that address answers "invalid code" — the code belongs to the data
 * centre that issued it. That is the trap this whole 'region' column exists for.
 *
 * @return array{token: string, uid: int}
 */
function pcloud_token_get(string $host, string $code): array
{
    $app = cloud_app('pcloud');
    if ($app === null) {
        throw new RuntimeException(t('pCloud ist auf diesem Server nicht eingerichtet.'));
    }

    $d = pcloud_call($host, 'oauth2_token', [
        'client_id'     => $app['client_id'],
        'client_secret' => $app['client_secret'],
        'code'          => $code,
    ]);

    $token = (string) ($d['access_token'] ?? '');
    if ($token === '') {
        throw new RuntimeException(t('pCloud gibt kein Token heraus.'));
    }

    return ['token' => $token, 'uid' => (int) ($d['uid'] ?? 0)];
}

/**
 * The root the app may write into.
 *
 * folderid 0 IS THE RIGHT ANSWER, and it is not a placeholder: inside a
 * "Private" app pCloud reports its own folder as the root — measured on
 * 16.08.2026, listfolder on 0 answered path "/", real id 26055459772, while
 * the account really holds /Applications/<app name>. Inside the sandbox the
 * app folder IS the root, so the path cannot tell full access from a private
 * folder; only the id can (0 for the account root, a real one for the app
 * folder). Addressing 0 therefore lands in the app folder and nowhere else.
 * (Until 02.10.2026 „Verbindung prüfen" printed that root and its first
 * entries as a diagnosis — „ich brauche das nicht".)
 *
 * A target folder the user has set is not resolved here: it is a name, not an
 * id, and storage_path_ensure() walks it like every other level.
 */
function cloud_root_id(array $connection): int
{
    return 0;
}

/**
 * Resolve a name to the one GBIF considers valid today.
 *
 * THE FILING GOES BY THE CURRENT NAME, and GBIF is the authority for it. That
 * matters more than it sounds: of the DGfM synonyms with an accepted
 * counterpart, 29 percent carry a DIFFERENT GENUS — "Coprinus hepthemerus"
 * becomes "Coprinellus hepthemerus". Filing by the recorded name would put
 * the same fungus under two genera, depending on which name one happened to
 * use that day.
 *
 * THE RECORDED NAME DOES NOT APPEAR IN THE PATH AT ALL — decided on
 * 17.08.2026: "how it was recorded is irrelevant, we go by GBIF's species
 * concept". So no suffix on the find-day folder either, nothing like
 * "(als Collybia radicata)". The name stays at the point, where it belongs;
 * the archive knows only the valid one. That is what makes a genus folder
 * complete: everything of one fungus lies there, whatever it was called on
 * the day it was found.
 *
 * TWO STEPS, AND THE FIRST ONE ONLY EXISTS FOR FUNGI. A DGfM number reaches
 * GBIF through map_tax_links; once there, is_synonym says whether one
 * has to follow parent_key. Lichens and plants have no such linkage — for
 * them the recorded name stands, and that is not a gap but the state of the
 * data.
 *
 * THE COUNTER GUARDS AGAINST A CHAIN THAT BITES ITS OWN TAIL. GBIF is
 * curated, but this runs on a copy that is a year old at times, and a loop
 * here would hang the upload instead of showing an error.
 *
 * @return array{genus: string, epithet: string, abteilung: ?string}|null
 */
function cloud_gbif_valid(PDO $pdo, int $gbifKey): ?array
{
    if ($gbifKey <= 0 || !table_exists($pdo, 'map_tax_gbif')) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT gbif_key, is_synonym, parent_key, genus, epithet, phylum_key
           FROM map_tax_gbif WHERE gbif_key = ? LIMIT 1'
    );

    $row = null;
    $key = $gbifKey;
    for ($guard = 0; $guard < 12 && $key > 0; $guard++) {
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (!(int) $row['is_synonym']) {
            break;
        }
        $key = (int) $row['parent_key'];
    }
    if (!$row || (int) $row['is_synonym']) {
        return null;
    }

    // The division under exactly the name the folders carry — GBIF calls it
    // phylum and spells it the same as the DGfM does.
    $division = null;
    if ((int) $row['phylum_key'] > 0) {
        $p = $pdo->prepare('SELECT canonical FROM map_tax_gbif WHERE gbif_key = ? LIMIT 1');
        $p->execute([(int) $row['phylum_key']]);
        $division = trim((string) $p->fetchColumn()) ?: null;
    }

    $genus = trim((string) ($row['genus'] ?? ''));

    return $genus === '' ? null : [
        'genus'    => $genus,
        'species'  => trim((string) ($row['epithet'] ?? '')),
        'division' => $division,
    ];
}

/**
 * Which group does a bare genus belong to?
 *
 * FOR FINDS WITHOUT A REFERENCE. Whoever writes "Scutellinia spec." into the
 * title because the species will not come out has recorded no taxon — the
 * name is in no list, so there is no identifier, and the identifier is what
 * otherwise names the top folder. Without this lookup such a find could not
 * be filed at all, and that is precisely the case for which the storage
 * exists: micrographs and stacked shots are made when the naked eye is not
 * enough.
 *
 * THE GENUS IS ENOUGH TO ANSWER IT. It stands in the reference lists, and
 * which list holds it says what it is.
 *
 * THE SPECIALISED LISTS ARE ASKED FIRST, and that is a decision with a
 * measured reason: 180 genera stand in both the lichen and the fungus list
 * — lichenised and lichenicolous fungi, which they both rightly are. The
 * lichen list is small and curated, the DGfM catalogue broad; whoever has a
 * genus in the specialised one usually means it that way. It stays a guess,
 * which is why the target path is shown before the upload — a wrong guess
 * is then visible immediately instead of being found weeks later in the
 * cloud.
 *
 * @return array{kingdom: string, group: string, division: ?string}|null
 */
function cloud_genus_group(PDO $pdo, string $genus): ?array
{
    $genus = trim($genus);
    if ($genus === '') {
        return null;
    }

    $lists = [
        ['map_tax_lichens', 'Fungi (Pilze)',      'Lichenes'],
        ['map_tax_plants', 'Plantae (Pflanzen)', 'Plantae'],
        ['map_tax_animals',    'Animalia (Tiere)',   'Animalia'],
        ['map_tax_faeu',     'Animalia (Tiere)',   'Animalia'],
        ['map_tax_dgfm',     'Fungi (Pilze)',      'Fungi'],
    ];

    $found = null;
    foreach ($lists as [$table, $kingdom, $group]) {
        if (!table_exists($pdo, $table)) {
            continue;
        }
        $stmt = $pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE genus = ? LIMIT 1');
        $stmt->execute([$genus]);
        if ($stmt->fetchColumn() !== false) {
            $found = ['kingdom' => $kingdom, 'group' => $group, 'division' => null];
            break;
        }
    }
    if ($found === null) {
        return null;
    }

    $found['division'] = cloud_genus_division($pdo, $genus, $found['kingdom']);

    return $found;
}

/**
 * The kingdom folders and GBIF's keys of the kingdoms each takes — the
 * parent of every phylum row of them, the first preferred. THE FUNGI'S
 * FOLDER TAKES WHAT THE DGfM LIST TAKES: besides the true fungi (5) the
 * slime moulds (Protozoa 7, Mycetozoa — „Myxomycetes", a thousand species)
 * and the water moulds (Chromista 4, Peronosporales, Saprolegniales). They
 * are no fungi to GBIF, but mycologists work them and the list holds them.
 */
const CLOUD_GBIF_KINGDOMS = [
    'Fungi (Pilze)'           => [5, 7, 4],
    'Plantae (Pflanzen)'      => [6],
    'Animalia (Tiere)'        => [1],
    'Protozoa (Schleimpilze)' => [7],
    'Chromista (Eipilze)'     => [4],
];

/**
 * SLIME MOULDS AND WATER MOULDS IN THEIR OWN KINGDOMS
 * (18.11.2026: „Die gehören doch gar nicht ins Reich Fungi, oder? Ich würde
 * sie dort einsortieren, wo sie hingehören, ins richtige Reich"). The DGfM
 * list holds them among the fungi, and so did the folders — „Fungi
 * (Pilze)/Fungi/Mycetozoa/P/Physarum". To GBIF the Myxomycetes are Protozoa
 * and the Oomycota (Peronospora, Saprolegnia, Albugo) Chromista, and the
 * first folder is the kingdom. Their division names the kingdom: the
 * phylum's GBIF row has it as its parent (cloud_division_kingdom). The
 * same two levels as the fungi: the kingdom with its German word, then
 * the group, then the division.
 *
 * GBIF key of the kingdom => [kingdom folder, group folder]
 */
const CLOUD_OTHER_KINGDOMS = [
    7 => ['Protozoa (Schleimpilze)', 'Protozoa'],
    4 => ['Chromista (Eipilze)', 'Chromista'],
];

/** The kingdom a division belongs to, when it is one of CLOUD_OTHER_KINGDOMS — null for a fungus's. */
function cloud_division_kingdom(PDO $pdo, string $division): ?array
{
    if (trim($division) === '' || !table_exists($pdo, 'map_tax_gbif')) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT parent_key FROM map_tax_gbif
                            WHERE canonical = ? AND taxon_rank = 'PHYLUM' AND is_synonym = 0
                            ORDER BY parent_key = 5 DESC LIMIT 1");
    $stmt->execute([trim($division)]);
    return CLOUD_OTHER_KINGDOMS[(int) $stmt->fetchColumn()] ?? null;
}

/**
 * „MYXO SPEC." IS A SLIME MOULD NOBODY HAS NAMED YET — no genus. The maintainer
 * wrote it so (his pCloud, 18.11.2026: „Fungi (Pilze)/Fungi/M/Myxo/Myxo
 * spec.", „Myxo" taken from GBIF's search, where it is a genus of
 * bacteria). Such a title files like any find without a name, but in the
 * slime moulds' „Unbestimmt" (cloud_undetermined_top), and its pictures
 * keep the words he wrote (cloud_name_species). Only the word alone or
 * with an open epithet: „Myxomycetes" is no genus either way.
 */
function cloud_myxo_unnamed(string $title): bool
{
    $words = array_values(array_filter(preg_split('/\s+/u', trim($title)) ?: [],
        static fn(string $w): bool => $w !== '' && !cloud_open_mark_word($w)));
    if ($words === [] || preg_match('/^(myxos?|myxomycet(e|en|es)?|schleimpilz(e)?)\.?$/iu', $words[0]) !== 1) {
        return false;
    }
    return !isset($words[1]) || cloud_epithet_open($words[1]);
}

/**
 * The division a genus belongs to, straight from GBIF.
 *
 * WHY IT IS ASKED FOR EVERY ROUTE, not only for finds without a reference.
 * The division otherwise came along only where a GBIF key was reached —
 * for fungi through the linkage table, never for lichens. The same species
 * would then have landed in .../Lichenes/Ascomycota/P/Parmelia once and in
 * .../Lichenes/P/Parmelia the other time, purely by whether the name
 * happened to be in a list. One find split across two folders is worse than
 * no division at all.
 *
 * Only the accepted entry counts: a synonymous genus carries the phylum of
 * its own name, and that may be the outdated one.
 *
 * AND ONLY THE ONE OF THE FIND'S KINGDOM (18.11.2026, the maintainer's pCloud:
 * „Fungi (Pilze)/Fungi/Arthropoda/C/Crepidotus/C. mollis"). The GBIF table
 * holds every kingdom, not the fungi alone, and a genus name may be given
 * twice: Crepidotus is a fungus AND a beetle. The first accepted row was
 * taken, and that was the beetle's — a crepidotus filed among the
 * arthropods. The phylum's own row names its kingdom as its parent, so the
 * genus is looked for under the kingdoms of the folder it goes into
 * (CLOUD_GBIF_KINGDOMS); lichens are fungi there too.
 */
function cloud_genus_division(PDO $pdo, string $genus, string $kingdom = 'Fungi (Pilze)'): ?string
{
    if (trim($genus) === '' || !table_exists($pdo, 'map_tax_gbif')) {
        return null;
    }
    $kingdoms = CLOUD_GBIF_KINGDOMS[$kingdom] ?? [];
    if ($kingdoms === []) {
        return null;
    }
    $space = implode(',', array_fill(0, count($kingdoms), '?'));

    $stmt = $pdo->prepare(
        "SELECT g.gbif_key FROM map_tax_gbif g
           JOIN map_tax_gbif ph ON ph.gbif_key = g.phylum_key
          WHERE g.canonical = ? AND g.taxon_rank = 'GENUS' AND g.is_synonym = 0 AND ph.parent_key IN ($space)
          ORDER BY FIELD(ph.parent_key, $space)
          LIMIT 1"
    );
    $stmt->execute([trim($genus), ...$kingdoms, ...$kingdoms]);
    $key = (int) $stmt->fetchColumn();
    if ($key <= 0) {
        return null;
    }

    $valid = cloud_gbif_valid($pdo, $key);

    return $valid === null ? null : $valid['division'];
}

/**
 * Is this epithet an admission that the species is not settled?
 *
 * "Scutellinia spec." is not a species — it is a genus with a note. It must
 * not become the folder "S. spec.", it belongs under "Scutellinia spec.",
 * beside the determined species of the same genus. The abbreviations are
 * written every which way, hence lowercased and without the trailing dot.
 */
/**
 * A word of the open marks between or after the names — cf., aff., s. l.,
 * agg. and their kin — which name no taxon. mykis_open_marks() reads them
 * for what they MEAN; this only says that a word is one of them.
 */
function cloud_open_mark_word(string $word): bool
{
    $w = mb_strtolower(rtrim(trim($word), '.,'));
    return in_array($w, ['cf', 'conf', 'aff', 's', 'l', 'str', 'sl', 'sstr', 'lat', 'sensu', 'lato',
                         'stricto', 'ss', 'agg', 'aggr'], true);
}

function cloud_epithet_open(string $epithet): bool
{
    $t = mb_strtolower(rtrim(trim($epithet), '.'));
    return in_array($t, ['sp', 'spp', 'spec', 'species', 'indet', 'ined', 'agg'], true);
}

/**
 * Everything a path needs, read off a point.
 *
 * THE KINGDOM COMES FROM THE SOURCE OF THE IDENTIFIER, not from the name.
 * 'fungi:49097' says fungi, 'lichens:…' lichens, 'plants:…' plants — and
 * that is also what separates the three top folders.
 *
 * THE NAME COMES FROM GBIF WHERE IT CAN. The maintainer files by the name that is
 * valid today; see cloud_gbif_valid(). Where there is no linkage — lichens,
 * plants, or a fungus GBIF does not know — the recorded name stands.
 *
 * @return array|null null when the find carries no determined species
 */
function cloud_find_from_point(PDO $pdo, int $userId, array $point): ?array
{
    // THIS WANTS THE RAW ROW, NOT point_load()'s.
    //
    // point_prepare() builds the shape the browser gets, and 'extra_species'
    // is deliberately not in it. Handing that array in here was exactly the
    // mistake that made the button vanish on every single point: the
    // species were looked for in a field that had been dropped one layer
    // earlier, json_decode('') yielded null, and 'filable' came back false
    // however well determined the find was. tests/cloud.php now holds the
    // two together.
    $species = json_decode((string) ($point['extra_species'] ?? ''), true);

    // The title's species first, then the other fields. The empty field name
    // IS the title (see point_species_keys) — and the order matters: a find
    // record also has species fields for host and accompanying vegetation,
    // and the oak the fungus grew on must not name the folder.
    $name = '';
    $identifier = '';
    if (is_array($species)) {
        $fields = isset($species['']) && is_array($species[''])
            ? ['' => $species['']] + $species
            : $species;
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            foreach ($field as $n => $i) {
                if (trim((string) $n) !== '') {
                    $name = trim((string) $n);
                    $identifier = (string) $i;
                    break 2;
                }
            }
        }
    }

    // NO REFERENCE IS NOT NO NAME. Only what a list confirms goes into
    // extra_species (see point_species_references) — whatever is written free
    // stands in the title alone. That is the normal case for an open
    // determination: "Scutellinia spec." is in no list, and it is exactly
    // that find that carries micrographs worth keeping.
    if ($name === '') {
        $name = trim((string) ($point['title'] ?? ''));
    }
    if ($name === '' || cloud_myxo_unnamed($name)) {
        return null;
    }

    [$source, $key] = array_pad(explode(':', $identifier, 2), 2, '');
    $fromName = null;

    $groups = [
        'fungi'    => ['kingdom' => 'Fungi (Pilze)',      'group' => 'Fungi',
                       'table' => 'map_tax_dgfm',     'key' => 'taxnr'],
        /* LICHENS ARE FUNGI (18.11.2026: „Flechten … und normale
           Pilze im Hauptordner Fungi (Pilze)"): the first folder is the
           kingdom, and lichens belong to it; „Lichenes" beside „Fungi" one
           level down is the group. Until then they had a top folder of
           their own, „Fungi (Flechten)"; the renaming moves them over. */
        'lichens' => ['kingdom' => 'Fungi (Pilze)',      'group' => 'Lichenes',
                       'table' => 'map_tax_lichens', 'key' => 'uuid'],
        'plants' => ['kingdom' => 'Plantae (Pflanzen)', 'group' => 'Plantae',
                       'table' => 'map_tax_plants', 'key' => 'uuid'],
        'gbif'     => ['kingdom' => 'Fungi (Pilze)',      'group' => 'Fungi',
                       'table' => '',                 'key' => ''],
        // THE CLASS IS THE GROUP HERE, not the kingdom a second time. Under
        // 'Animalia' everything from the wren to the stag beetle would lie
        // in one heap; Aves, Insecta and Mammalia are the division one
        // actually walks through. It is filled in below, from the list
        // itself.
        'animals'    => ['kingdom' => 'Animalia (Tiere)',   'group' => 'Animalia',
                       'table' => 'map_tax_animals',    'key' => 'taxnr'],
        'faeu'     => ['kingdom' => 'Animalia (Tiere)',   'group' => 'Animalia',
                       'table' => 'map_tax_faeu',     'key' => 'taxnr'],
    ];
    /* A WORKING NAME (working_names.php, migration 0189) GETS A FOLDER OF
       ITS OWN: „Inocybe sp. nov. 1" beside „Inocybe sp. nov. 2", not both
       in the genus's „spec." heap, where their words („sp.") would send
       them. Its genus is the one it was coined in, its epithet everything
       after it. */
    $working = null;
    if ($source === 'working' && ctype_digit($key)) {
        require_once __DIR__ . '/working_names.php';
        $working = working_name($pdo, (int) $key);
    }

    // Without a usable reference the name has to carry it alone: the genus
    // is its first word, and which list holds that genus says the rest.
    $group = $groups[$source] ?? null;
    if ($group === null || $key === '') {
        $first = $working !== null ? (string) $working['genus']
            : (string) ((preg_split('/\s+/u', $name) ?: [])[0] ?? '');
        $derived = cloud_genus_group($pdo, $first);
        if ($derived === null) {
            return null;
        }
        $group = ['kingdom' => $derived['kingdom'], 'group' => $derived['group'],
                  'table' => '', 'key' => ''];
        $key = '';
        $source = '';
        $fromName = $derived;
    }

    $genus = '';
    $epithet = '';
    $division = $fromName['division'] ?? null;

    // The way to GBIF: directly for a GBIF identifier, through the linkage
    // table for a DGfM number.
    $gbifKey = 0;
    if ($source === 'gbif') {
        $gbifKey = (int) $key;
    } elseif ($source === 'fungi' && table_exists($pdo, 'map_tax_links')) {
        $stmt = $pdo->prepare('SELECT gbif_key FROM map_tax_links WHERE taxnr = ? LIMIT 1');
        $stmt->execute([(int) $key]);
        $gbifKey = (int) $stmt->fetchColumn();
    }

    if ($gbifKey > 0 && ($valid = cloud_gbif_valid($pdo, $gbifKey)) !== null) {
        $genus    = $valid['genus'];
        $epithet  = $valid['species'];
        $division = $valid['division'];
    }

    // No linkage, or GBIF does not know it: the recorded name stands. For
    // lichens and plants that is the normal case, not a fallback.
    if ($genus === '' && $group['table'] !== '' && table_exists($pdo, $group['table'])) {
        $stmt = $pdo->prepare(
            'SELECT genus, epithet FROM ' . $group['table']
            . ' WHERE ' . $group['key'] . ' = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        if ($row = $stmt->fetch()) {
            $genus   = trim((string) ($row['genus'] ?? ''));
            $epithet = trim((string) ($row['epithet'] ?? ''));
        }
    }

    // The animal's class, as the second level of the tree — see the note at
    // the group table above.
    if (in_array($source, ['animals', 'faeu'], true) && $key !== ''
        && table_exists($pdo, $group['table'])) {
        $stmt = $pdo->prepare('SELECT gruppe FROM ' . $group['table'] . ' WHERE taxnr = ? LIMIT 1');
        $stmt->execute([(int) $key]);
        $class = trim((string) $stmt->fetchColumn());
        if ($class !== '') {
            $group['group'] = $class;
        }
    }

    if ($working !== null) {
        $genus = (string) $working['genus'];
        $epithet = trim((string) $working['label'] . ' ' . (WORKING_NAME_STATUSES[$working['status']] ?? ''));
    }

    if ($genus === '') {
        // "Amanita muscaria" — the first word is the genus, the second the
        // epithet. Anything behind it (author, "cf.") does not belong in the
        // folder name. AND NOTHING BETWEEN THEM EITHER (04.10.2026): a free
        // "Boletus cf. edulis" took "cf." for the epithet and filed under
        // „B. cf."; the open marks are skipped, the file name puts them
        // back where they belong (cloud_name_species).
        $words = array_values(array_filter(preg_split('/\s+/u', $name) ?: [],
            static fn(string $w): bool => !cloud_open_mark_word($w)));
        $genus = (string) ($words[0] ?? '');
        $epithet = (string) ($words[1] ?? '');
    }

    if ($genus === '') {
        return null;
    }

    // "spec.", "sp.", "indet." — written where the species did not come out.
    // Dropped here rather than in the path, so everything downstream sees
    // the same thing a determination to genus sees.
    if ($working === null && cloud_epithet_open($epithet)) {
        $epithet = '';
    }

    // See cloud_genus_division(): the same genus must yield the same folder,
    // whichever route led here.
    if ($division === null) {
        $division = cloud_genus_division($pdo, $genus, (string) $group['kingdom']);
    }

    // A slime mould or water mould from the fungi's list goes to its own kingdom (CLOUD_OTHER_KINGDOMS).
    if ($group['group'] === 'Fungi' && $division !== null
        && ($other = cloud_division_kingdom($pdo, $division)) !== null) {
        [$group['kingdom'], $group['group']] = $other;
    }

    // THE DB COLUMNS ARE GERMAN, THE FIELDS HANDED ON ARE ENGLISH — this is
    // the layer where the two meet (see CLAUDE.md). Reading 'municipality' and
    // writing 'municipality' is therefore not an oversight but the rule.
    return [
        'kingdom'      => $group['kingdom'],
        'group'        => $group['group'],
        'division'     => $division,
        'genus'        => $genus,
        'species'      => $epithet,
        'found_at'     => (string) ($point['found_at'] ?? $point['created_at'] ?? ''),
        'municipality' => (string) ($point['municipality'] ?? ''),
        // The find's folder carries its number (cloud_path_parts) — from
        // the moment the names are kept (migration 0151), not before: the
        // old refile would otherwise move every picture at once.
        'number'       => cloud_names_ready($pdo) ? cloud_find_number($point) : '',
    ];
}

/**
 * ARE THE NAMES KEPT? Migration 0151 brings the queue and the name columns
 * (includes/cloud_names.php). Until it is applied everything stays as it
 * was — the folders without the find number, the old refile — so the code
 * can go live first, and the switch is the migration.
 */
function cloud_names_ready(PDO $pdo): bool
{
    static $ready = null;
    return $ready ??= table_exists($pdo, 'map_cloud_names_due')
        && column_exists($pdo, 'map_photos', 'cloud_name')
        && column_exists($pdo, 'map_cloud_copies', 'name')
        && column_exists($pdo, 'map_cloud_files', 'original_name');
}

/**
 * The find number a folder carries — the find's own, or YASAIMAPP's
 * „Y-2817" where it has none (find_number_fallback). A point not yet on
 * the server (negative id) has neither.
 */
function cloud_find_number(array $point): string
{
    require_once __DIR__ . '/find_number.php';
    /* AN AUFSAMMLUNG'S FOLDER CARRIES ITS BOX NUMBER (13.11.2026,
       „Wieso hat denn meine Aufsammlung von heute eine
       Yasai-Map-Nummer bekommen?") — it lies in a species list like a
       find, but „Y-<id>" is a find's mark, and the box has its own. */
    if (!empty($point['is_collection'])) {
        return trim((string) ($point['collection_number'] ?? ''));
    }
    $own = trim((string) ($point['find_number'] ?? ''));
    if ($own !== '') {
        return $own;
    }
    return (int) ($point['id'] ?? 0) > 0 ? find_number_fallback((int) $point['id']) : '';
}

/**
 * The recordings recorded for one find — mine first, then everybody
 * else's on it, as seen by $userId.
 *
 * THE GATE IS THE CALLER'S. This lists every row on the point; whether
 * $userId may look at the find at all is settled before the call —
 * api/v1/cloud.php asks point_attach_scope() or point_view_scope(), the
 * report walks a list that is visibility-sifted already. It used to be
 * asked in here too, as „the others' rows only for the circle", and
 * that was the wrong place: a share member had passed the gate outside
 * and still got an empty list, because he is not in the circle.
 */
function cloud_files_for_point(PDO $pdo, int $userId, int $pointId): array
{
    if (!table_exists($pdo, 'map_cloud_files')) {
        return [];
    }
    // For photo_scale_read — lazy, like photos.php pulls this file in.
    require_once __DIR__ . '/photos.php';
    return cloud_files_rows($pdo, $userId, $pointId);
}

/**
 * How many cloud recordings hang on each of these points — ONE query for
 * the whole inventory, the same sweep as photos_for_points.
 *
 * THE COUNT IS THE „BEARBEITET" MARK (30.08.2026: „sobald
 * Fotos ausgewählt werden zum Hochladen in die Cloud, bekommt der
 * Fundpunkt einen neuen Status … Mikrofotos, Stackingfotos,
 * Bestimmungs-Sachen — besseres Material als ein reiner Fundpunkt und
 * ein Handyfoto"). DERIVED, never stored: a status column would have to
 * be kept true through every upload and removal, this count cannot go
 * stale. It rides the point baggage; the sidebar row wears the word.
 *
 * EVERY RECORDING ON THE FIND COUNTS, whoever took it (06.10.2026). It
 * counted the viewer's own alone, and the shared finds came with a flat
 * 0 besides: Finn uploaded micrographs to the finds he shares with
 * the maintainer, and on the maintainer's map they stood unworked until he opened
 * one — the point window lists every picture of the find and put the
 * mark right, one find at a time. A co-owner's micrograph makes a find
 * worked as much as the holder's, and whoever sees the find sees all its
 * pictures anyway (cloud.php, 'files'). The callers hand in only points
 * the asker may see.
 *
 * @param int[] $pointIds
 * @return array<int,int> point_id => count (absent means none)
 */
function cloud_recordings_for_points(PDO $pdo, array $pointIds): array
{
    if ($pointIds === [] || !table_exists($pdo, 'map_cloud_files')) {
        return [];
    }
    $space = implode(',', array_fill(0, count($pointIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT point_id, COUNT(*) AS n
           FROM map_cloud_files
          WHERE point_id IN ($space)
          GROUP BY point_id"
    );
    $stmt->execute(array_values($pointIds));
    $out = [];
    foreach ($stmt as $row) {
        $out[(int) $row['point_id']] = (int) $row['n'];
    }
    return $out;
}

/** The inner half of cloud_files_for_point, split off above. */
function cloud_files_rows(PDO $pdo, int $userId, int $pointId): array
{
    // scale only from migration 0090 on, measures from 0092, the
    // original's dimensions from 0095 — the code is live before all.
    $hasScale    = column_exists($pdo, 'map_cloud_files', 'scale');
    $hasMeasures = column_exists($pdo, 'map_cloud_files', 'measures');
    $hasDims     = column_exists($pdo, 'map_cloud_files', 'width');
    // The picture's name from migration 0097 on — same live-before rule.
    $hasTitle    = column_exists($pdo, 'map_cloud_files', 'title');
    // The place in the gallery's order from 0148 on (photos_reorder).
    $hasOrder    = column_exists($pdo, 'map_cloud_files', 'sort_order');
    // Who took it, where not the row's user (0161, a copy kept after a split).
    $hasTakenBy  = column_exists($pdo, 'map_cloud_files', 'taken_by');
    // The media a micrograph lay in, from 0187 on.
    $hasMedia    = column_exists($pdo, 'map_cloud_files', 'media');
    // How it is developed (0190, the viewer's „Bearbeiten").
    $hasDevelop  = column_exists($pdo, 'map_cloud_files', 'develop');
    // The other edits beside it (0192).
    $hasVersions = column_exists($pdo, 'map_cloud_files', 'develop_versions');
    $stmt = $pdo->prepare(
        'SELECT id, provider, fileid, folderid, name, path, size, uploaded_at'
        . ($hasScale ? ', scale' : '') . ($hasMeasures ? ', measures' : '')
        . ($hasDims ? ', width, height' : '') . ($hasTitle ? ', title' : '')
        . ($hasOrder ? ', sort_order' : '') . ($hasTakenBy ? ', taken_by' : '')
        . ($hasMedia ? ', media' : '') . ($hasDevelop ? ', develop' : '')
        . ($hasVersions ? ', develop_versions' : '') . '
           FROM map_cloud_files
          WHERE user_id = ? AND point_id = ?
          ORDER BY uploaded_at DESC, id DESC'
    );
    $stmt->execute([$userId, $pointId]);

    $rows = $stmt->fetchAll();
    // My own name rides on my own rows too: the report prints it under
    // a picture on a find I do not hold — there it is not the holder's
    // picture, and nobody else can say whose it is.
    $q = $pdo->prepare('SELECT ' . sql_display_name() . ' FROM map_users u WHERE u.id = ?');
    $q->execute([$userId]);
    $myName = (string) $q->fetchColumn();
    foreach ($rows as &$row) {
        $row['mine']            = true;
        $row['photographer_id'] = $userId;
        // A copy kept after a split is mine to tend, but somebody else
        // took it — the name says who (0161); the rights stay with the id.
        $row['taken_by']        = ($row['taken_by'] ?? null) ?: null;
        $row['photographer']    = $row['taken_by'] ?? $myName;
    }
    unset($row);

    /* THE OTHERS' RECORDINGS ON THE FIND (11.09.2026) — as references:
       name, title, scale, measures and dimensions travel, the file stays
       in the photographer's cloud. 'copy' says whether MY cloud already
       holds a copy (the worker's status); the preview comes from it as
       soon as it does (pages/cloud_thumb.php).

       FOR EVERYONE WHO REACHED THIS LINE, not only the circle (14.09.2026).
       The first cut said „only for the circle: a member of a share sees
       the holder's list as before" — and there was no such before: the
       listing had always been keyed to the caller, so a share member got
       HIS rows on the holder's find, which are none. Whoever may see the
       find sees its recordings, the same rule as for the voucher photos;
       who may see is the caller's question (see cloud_files_for_point).
       A share member has no copy and never gets one — the LEFT JOIN
       comes back empty for him, and the preview takes the photographer's
       cloud. The copies table arrived with the co-ownership migration,
       hence the readiness check stays. */
    require_once __DIR__ . '/point_owners.php';
    if (point_owners_ready($pdo)) {
        $q = $pdo->prepare(
            'SELECT f.id, f.provider, f.fileid, f.folderid, f.name, f.path, f.size, f.uploaded_at, f.user_id'
            . ($hasScale ? ', f.scale' : '') . ($hasMeasures ? ', f.measures' : '')
            . ($hasDims ? ', f.width, f.height' : '') . ($hasTitle ? ', f.title' : '')
            . ($hasOrder ? ', f.sort_order' : '') . ($hasTakenBy ? ', f.taken_by' : '')
            . ($hasMedia ? ', f.media' : '') . ($hasDevelop ? ', f.develop' : '')
            . ($hasVersions ? ', f.develop_versions' : '') . ',
                    TRIM(CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\'))) AS photographer_name,
                    u.email AS photographer_email,
                    c.status AS copy_status, c.fileid AS copy_fileid
               FROM map_cloud_files f
               JOIN map_users u ON u.id = f.user_id
               LEFT JOIN map_cloud_copies c
                      ON c.origin_kind = "cloud" AND c.origin_id = f.id AND c.user_id = ?
              WHERE f.point_id = ? AND f.user_id <> ?
              ORDER BY f.uploaded_at DESC, f.id DESC'
        );
        $q->execute([$userId, $pointId, $userId]);
        foreach ($q->fetchAll() as $row) {
            $row['mine']            = false;
            $row['photographer_id'] = (int) $row['user_id'];
            $row['taken_by']        = ($row['taken_by'] ?? null) ?: null;
            $row['photographer']    = $row['taken_by']
                ?? ($row['photographer_name'] !== '' ? $row['photographer_name'] : $row['photographer_email']);
            $row['copy']            = $row['copy_status'] === null ? null
                : ['status' => $row['copy_status'], 'fileid' => $row['copy_fileid'] === null ? null : (string) $row['copy_fileid']];
            unset($row['user_id'], $row['photographer_name'], $row['photographer_email'], $row['copy_status'], $row['copy_fileid']);
            $rows[] = $row;
        }
    }

    foreach ($rows as &$row) {
        // Through the same gate as the photos' — a hand-bent row reads as
        // "not calibrated" / "nothing measured", never as garbage drawing.
        $row['scale']    = photo_scale_read($row['scale'] ?? null);
        $row['measures'] = photo_measures_read($row['measures'] ?? null);
        // The original's pixel grid (0095), or null for „noch nie
        // gemessen" — what lets a preset stamp onto the preview.
        $row['width']  = isset($row['width'])  ? (int) $row['width']  ?: null : null;
        $row['height'] = isset($row['height']) ? (int) $row['height'] ?: null : null;
        // The picture's name (0097), or null for „unbenannt".
        $row['title']  = $row['title'] ?? null;
        // The media it lay in (0187), or null for „nicht angegeben".
        $row['media']  = photo_media_read($row['media'] ?? null);
        // How it is developed (0190), or null.
        $row['develop'] = photo_develop_read($row['develop'] ?? null);
        // The other edits beside it (0192), or null for „only one".
        $row['develop_versions'] = photo_develop_versions_read($row['develop_versions'] ?? null);
        // Null until 0148: the gallery then leaves the tile unsortable.
        $row['sort_order'] = isset($row['sort_order']) ? (int) $row['sort_order'] : null;
    }
    unset($row);

    /* ONE ORDER ONCE THERE IS ONE (0148): the gallery's, the same for
       every viewer of the find — mine-first was the listing's own
       arrangement, and it would put a co-owner's micrograph behind mine
       in the report while the gallery shows it in front. Ties as in
       photos_reorder: the lower id first. Before the column, the old
       arrangement stands. */
    if ($hasOrder) {
        usort($rows, static fn(array $a, array $b): int =>
            [$a['sort_order'], (int) $a['id']] <=> [$b['sort_order'], (int) $b['id']]);
    }

    return $rows;
}

/**
 * Make sure a folder is there, and give back its id.
 *
 * createfolderifnotexists AND NOT createfolder, because two devices may hit
 * the same evening at the same second — the second one would get "already
 * exists" and the upload would break for a folder that is perfectly fine.
 * pCloud answers with the existing folder's metadata either way and says in
 * 'created' which of the two happened.
 *
 * @return array{folderid: int, created: bool}
 */
function pcloud_folder_ensure(string $host, string $token, int $parentId, string $name): array
{
    /* A NAME WITH AN UMLAUT IS LOOKED FOR BEFORE IT IS MADE (22.11.2026).
       pCloud keeps „ü" exactly as it is sent and holds its two spellings
       (cloud_name_key) for two names — so „2026-08-29 Krün wk-00033",
       asked for in the spelling this code sends, was created a SECOND time
       beside the folder of that name spelt the other way, and a plate
       saved on 20.11. lay alone in a twin of its find's folder; six such
       pairs stood in the maintainer's cloud. The folder that is there,
       however it is spelt, is the folder. One listing more, and only for
       names that carry anything beyond ASCII — every other name has one
       spelling. */
    if (preg_match('/[\x80-\xFF]/', $name) === 1) {
        $key = cloud_name_key($name);
        foreach (pcloud_subfolders($host, $token, $parentId) as $has => $id) {
            if ($id !== '' && cloud_name_key((string) $has) === $key) {
                return ['folderid' => (int) $id, 'created' => false];
            }
        }
    }

    $d = pcloud_call($host, 'createfolderifnotexists', [
        'access_token' => $token,
        'folderid'     => $parentId,
        'name'         => $name,
    ]);

    return [
        'folderid' => (int) ($d['metadata']['folderid'] ?? 0),
        'created'  => (bool) ($d['created'] ?? false),
    ];
}

/**
 * Walk a path segment by segment, creating what is missing.
 *
 * ONE CALL PER LEVEL, and there is no way around it: pCloud's 'path' variant
 * would take the whole thing at once but does not create intermediate levels.
 * Seven levels are seven calls the first time — afterwards they are the same
 * seven, each answering "already there" in a few milliseconds.
 *
 * @param string[] $segments from cloud_path_for()
 * @return int the id of the deepest folder
 */
function storage_path_ensure(array $s, string|int $rootId, array $segments): string|int
{
    $id = $rootId;
    foreach ($segments as $segment) {
        $id = storage_folder_ensure($s, $id, $segment)['folderid'];
        if ($id === 0 || $id === '') {
            throw new RuntimeException(t('Der Speicher nennt für „%s" keine Ordnerkennung.', $segment));
        }
    }
    return $id;
}

/**
 * Move a folder into another one, keeping its name.
 *
 * tofolderid WITHOUT toname — that is the documented way to move without
 * renaming. With both given one would rename at the same time, and a typo in
 * the second parameter would be hard to spot afterwards.
 */
function pcloud_folder_move(string $host, string $token, int $folderid, int $toFolderId): void
{
    pcloud_call($host, 'renamefolder', [
        'access_token' => $token,
        'folderid'     => $folderid,
        'tofolderid'   => $toFolderId,
    ]);
}

/**
 * The genus folders that lie directly under a letter.
 *
 * THIS IS THE COUNT THE SECOND LETTER HANGS ON, and it deliberately asks the
 * cloud instead of the database: what matters is what is THERE, not what has
 * been found. A find without an uploaded picture has no folder and does not
 * count — that is how the maintainer does it by hand, looking at the folders.
 *
 * Folders whose name is two letters long and starts with the letter itself
 * are left out: those are the second-letter compartments, not genera.
 *
 * @return array<string, int> genus name => folderid
 */
function storage_genus_folders(array $s, string|int $letterId, string $letter): array
{
    $out = [];
    foreach (storage_subfolders($s, $letterId) as $name => $id) {
        $name = (string) $name;
        if ($name === '' || (mb_strlen($name) === 2 && mb_strtoupper(mb_substr($name, 0, 1)) === $letter)) {
            continue;
        }
        $out[$name] = $id;
    }
    return $out;
}

/**
 * The FILES lying directly in a folder, by name.
 *
 * EVERY FILE, NOT THE FIRST FEW: the check's old root listing cut off after
 * eight entries — fed to a caller that draws conclusions from "is it
 * there?", such a list would report a backup as missing from the ninth
 * month on and upload it again every single night.
 *
 * THIS IS WHAT MAKES THE FINGERPRINT HONEST. The database remembers which
 * month it wrote out and under which print; only the provider knows whether
 * the file is still there. Whoever deletes the folder in their storage gets
 * everything written afresh, because the answer to "what is up there" comes
 * from up there.
 *
 * @return array<string, array{fileid: int, size: int, modified: string}>
 */
function pcloud_folder_files(string $host, string $token, int $folderid): array
{
    $d = pcloud_call($host, 'listfolder', [
        'access_token' => $token,
        'folderid'     => $folderid,
    ]);

    $out = [];
    foreach ((array) ($d['metadata']['contents'] ?? []) as $e) {
        if ((bool) ($e['isfolder'] ?? false)) {
            continue;
        }
        $name = (string) ($e['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $out[$name] = [
            'fileid'   => (int) ($e['fileid'] ?? 0),
            'size'     => (int) ($e['size'] ?? 0),
            'modified' => (string) ($e['modified'] ?? ''),
        ];
    }
    return $out;
}

/**
 * The compartment a genus belongs into under its letter — and if the letter
 * has just tipped over, everything already there moves along.
 *
 * WHY IT REARRANGES BY ITSELF. The rule counts what has a folder, so a letter
 * WILL tip one day — that is not a flaw, it is how the archive grows, and
 * the maintainer does exactly this by hand. What must not happen is the half state:
 * new finds under H/Hy/ while the old ones stay under H/. Then the same genus
 * lies in two places, and one notices at the next tidy-up, not before.
 *
 * SO THE MOVE HAPPENS BEFORE THE UPLOAD, not after. Whoever is interrupted
 * midway leaves folders half moved — but every one of them lies in a valid
 * place, and the next run finishes the job because it counts again. There is
 * no state in between that would need repairing.
 *
 * THE COUNT INCLUDES THE NEW GENUS, even when its folder does not exist yet:
 * otherwise the twelfth find would still go into the flat compartment and the
 * thirteenth would move it — one rearrangement later than one would expect.
 *
 * AT ANY STORAGE since 26.09.2026 — the pCloud calls it made are the
 * seam's pCloud side now, word for word; S3 is the one exception, below.
 *
 * @return array{folderid: string|int, moved: int} the compartment for
 *         $genus and how many folders were moved on the way
 */
function storage_letter_compartment(array $s, string|int $letterId, string $letter, string $genus): array
{
    /* S3 NEVER TIPS. A prefix cannot move — its name is in every key under
       it — so there the genus folders stay flat under their letter,
       however many: a bucket browser lists prefixes without choking, it is
       the file managers of the other clouds the compartments are for. And
       the listing is saved along with the move. */
    if ($s['provider'] === 's3') {
        return ['folderid' => $letterId, 'moved' => 0];
    }

    $genera = storage_genus_folders($s, $letterId, $letter);

    // The new one counts along, even before it exists.
    $count = count($genera) + (isset($genera[$genus]) ? 0 : 1);

    if ($count < cloud_letter_threshold()) {
        return ['folderid' => $letterId, 'moved' => 0];
    }

    // Tipped over: every genus already lying flat under the letter moves into
    // its two-letter compartment. Including the ones that are not this find's
    // — otherwise the letter would stay half sorted forever.
    $moved = 0;
    foreach ($genera as $name => $id) {
        $target = storage_folder_ensure($s, $letterId,
            $letter . mb_strtolower(mb_substr((string) $name, 1, 1)));
        if ($target['folderid'] === 0 || $target['folderid'] === '' || $id === 0 || $id === '') {
            continue;
        }
        storage_folder_move($s, $id, $target['folderid']);
        $moved++;
    }

    $own = storage_folder_ensure($s, $letterId,
        $letter . mb_strtolower(mb_substr($genus, 1, 1)));

    return ['folderid' => $own['folderid'], 'moved' => $moved];
}

/**
 * The folder a find belongs into — created, rearranged if need be, ready.
 *
 * THIS IS WHERE NAMING AND COUNTING MEET. cloud_path_for() names without
 * asking anyone; this here walks the path in the cloud, and at the letter it
 * stops to count, because only there does the shape depend on what is
 * already lying about.
 *
 * @return array{folderid: int, path: string[], moved: int}
 */
function storage_find_folder(array $s, string|int $rootId, array $find): array
{
    $parts = cloud_path_parts($find);
    if ($parts['letter'] === '') {
        throw new RuntimeException(t('Ohne bestimmte Gattung lässt sich kein Ordner bilden.'));
    }

    $genus = $parts['below'][0];

    // Everything above the letter is fixed, so it is simply walked.
    $id = storage_path_ensure($s, $rootId, array_merge($parts['above'], [$parts['letter']]));

    // Here and only here it depends on what is already lying about.
    $compartment = storage_letter_compartment($s, $id, $parts['letter'], $genus);

    // From the genus downwards it is fixed again.
    $id = storage_path_ensure($s, $compartment['folderid'], $parts['below']);

    return [
        'folderid'   => $id,
        'path'       => array_merge($parts['above'], [$parts['letter']], $parts['below']),
        'moved' => $compartment['moved'],
    ];
}

/**
 * The same, for content that never was a file.
 *
 * MULTIPART BY HAND, because that is what the provider's endpoint takes and
 * PHP without cURL builds no such body on its own.
 *
 * WHY THE SPLIT. The data backup (backup.php) writes its months in memory —
 * a CSV built out of the database. Putting it into a temporary file first
 * only to read it back would be two disk operations for nothing, and on a
 * shared host the temporary directory is the one place one does not want a
 * user's finds lying about, however briefly.
 *
 * renameifexists SAYS WHICH OF TWO PURPOSES THIS SERVES, and they are
 * opposites:
 *
 *   1 (the default) for the voucher photos — two devices may hand over the
 *     same evening, and the second one then gets its own name instead of an
 *     error. Nothing is silently overwritten.
 *   0 for the backup — "Funde 2026-08.csv" is ONE file that stands for one
 *     month and gets rewritten when that month changes. With renaming, every
 *     night would add "Funde 2026-08 (1).csv" beside it, and after a year
 *     nobody could tell which of three hundred files is the current one.
 *
 * @return array{fileid: int, name: string, size: int}
 * @throws RuntimeException
 */
function pcloud_upload_bytes(
    string $host,
    string $token,
    int $folderId,
    string $bytes,
    string $name,
    ?int $mtime = null,
    bool $replace = false
): array {
    if ($bytes === '') {
        throw new RuntimeException(t('Nichts zu übertragen: %s', $name));
    }

    $boundary = '----karte' . bin2hex(random_bytes(12));
    $body = "--$boundary\r\n"
          . 'Content-Disposition: form-data; name="filename"; filename="'
          . str_replace(['"', "\r", "\n"], '', $name) . "\"\r\n"
          . "Content-Type: application/octet-stream\r\n\r\n"
          . $bytes . "\r\n--$boundary--\r\n";

    $query = [
        'access_token'   => $token,
        'folderid'       => $folderId,
        'renameifexists' => $replace ? 0 : 1,
        'nopartial'      => 1,
    ];
    if ($mtime !== null) {
        $query['mtime'] = $mtime;
    }

    $answer = @file_get_contents(
        'https://' . $host . '/uploadfile?' . http_build_query($query),
        false,
        stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: multipart/form-data; boundary=$boundary\r\n",
            'content'       => $body,
            'timeout'       => PCLOUD_TIMEOUT,
            'ignore_errors' => true,
        ]])
    );
    if ($answer === false) {
        throw new RuntimeException(t('pCloud nimmt die Datei gerade nicht an.'));
    }

    $d = json_decode($answer, true);
    if (!is_array($d) || (int) ($d['result'] ?? -1) !== 0) {
        throw new RuntimeException(t(
            'pCloud lehnt ab (%d): %s',
            (int) ($d['result'] ?? -1),
            (string) ($d['error'] ?? t('ohne Angabe'))
        ));
    }

    $meta   = ($d['metadata'] ?? [])[0] ?? [];
    $fileid = (int) (($d['fileids'] ?? [])[0] ?? ($meta['fileid'] ?? 0));
    if ($fileid <= 0) {
        throw new RuntimeException(t('pCloud nennt keine Dateikennung.'));
    }

    // THE SIZE IS CHECKED, and that is not pedantry: the local copy is
    // deleted right after this. A truncated upload that still answered with
    // result 0 would then be the only copy there is.
    $size = (int) ($meta['size'] ?? 0);
    if ($size !== strlen($bytes)) {
        throw new RuntimeException(t(
            'pCloud hat %d von %d Bytes angenommen.', $size, strlen($bytes)
        ));
    }

    return [
        'fileid' => $fileid,
        'name'   => (string) ($meta['name'] ?? $name),
        'size'   => $size,
    ];
}

/**
 * Where a recording goes whose find has no name yet.
 *
 * THE CALL OF 19.08.2026: rather a folder of its own than nowhere.
 * Before this, a find without a genus could receive nothing at all — the
 * button said so plainly, but a micrograph of something one cannot name is
 * exactly the picture worth keeping, and it had no place to go.
 *
 * "Unbestimmt" and not "Undefiniert", although he said the latter: the
 * application says "nicht bestimmt" everywhere it speaks of this, and a
 * find that is not determined is what this is. One word, one meaning.
 *
 * THE LEAF IS THE SAME AS EVERYWHERE ELSE — find day, then the municipality.
 * That way the folder is walkable by the same habit as the taxonomic ones,
 * and the pictures of one afternoon stand together.
 *
 * @return string[]
 */
function cloud_undetermined_parts(array $point, string $number = '', ?PDO $pdo = null): array
{
    $day = substr((string) ($point['found_at'] ?? $point['created_at'] ?? ''), 0, 10);
    $leaf = cloud_leaf_folder($day, (string) ($point['municipality'] ?? ''), $number);
    $top = $pdo === null ? [] : cloud_undetermined_top($pdo, $point);

    return $leaf === '' ? [CLOUD_SPECIES_FOLDER, ...$top, 'Unbestimmt']
        : [CLOUD_SPECIES_FOLDER, ...$top, 'Unbestimmt', $leaf];
}

/**
 * „UNBESTIMMT" INSIDE ITS KINGDOM (18.11.2026: „wenn wir nach
 * Reich sortieren, dann sollten wir auch unbestimmt vielleicht nochmal
 * aufteilen", and of the two ways he chose „A ins Reich hinein"):
 *
 *     Arten/Fungi (Pilze)/Fungi/Unbestimmt/2026-08-02 Uder Y-3581/
 *     Arten/Fungi (Pilze)/Lichenes/Unbestimmt/…
 *     Arten/Plantae (Pflanzen)/Plantae/Unbestimmt/…
 *     Arten/Animalia (Tiere)/Aves/Unbestimmt/…
 *
 * A find without a name does not say what it is — its LIST does: „Was
 * enthält diese Liste?" (species_groups) names the groups a species list
 * holds. The first of the point's species lists decides, in the sidebar's
 * order; of its groups the first in species_groups_all's order — so a
 * list of fungi AND lichens files under the fungi. A list that never chose
 * holds fungi, as the species search assumes (the preselection). An animal
 * group goes to its first class, the folder its named finds have (Aves,
 * Insecta); „Übrige Tiere" has none and goes to Animalia. A point in no
 * species list goes to „Arten/Unbestimmt" — there is nothing to tell.
 *
 * @return string[] the kingdom and group folders, or none
 */
function cloud_undetermined_top(PDO $pdo, array $point): array
{
    // „Myxo spec." says what it is without a genus: the slime moulds' „Unbestimmt" (cloud_myxo_unnamed).
    if (cloud_myxo_unnamed((string) ($point['title'] ?? ''))) {
        return CLOUD_OTHER_KINGDOMS[7];
    }
    $id = (int) ($point['id'] ?? 0);
    if ($id <= 0 || !column_exists($pdo, 'map_lists', 'is_species')) {
        return [];
    }
    $groups = column_exists($pdo, 'map_lists', 'species_groups') ? 'l.species_groups' : 'NULL';
    $stmt = $pdo->prepare('SELECT ' . $groups . ' AS species_groups FROM map_point_lists pl
                             JOIN map_lists l ON l.id = pl.list_id
                            WHERE pl.point_id = ? AND l.deleted_at IS NULL AND l.is_species = 1
                            ORDER BY l.sort_order, l.id LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return [];
    }
    require_once __DIR__ . '/species.php';
    require_once __DIR__ . '/species_packages.php';
    $chosen = json_decode((string) ($row['species_groups'] ?? ''), true);
    $chosen = is_array($chosen) ? array_map('strval', $chosen) : [];
    $group = 'fungi';
    foreach (species_groups_all() as $g) {
        if (in_array($g, $chosen, true)) {
            $group = $g;
            break;
        }
    }
    return match ($group) {
        'fungi'   => ['Fungi (Pilze)', 'Fungi'],
        'lichens' => ['Fungi (Pilze)', 'Lichenes'],
        'plants'  => ['Plantae (Pflanzen)', 'Plantae'],
        default   => ['Animalia (Tiere)', ANIMAL_GROUPS[$group]['classes'][0] ?? 'Animalia'],
    };
}

/** Does a stored path lie in an „Unbestimmt" folder — the old one on top, or one inside a kingdom? */
function cloud_path_undetermined(string $path): bool
{
    return $path === 'Unbestimmt' || str_starts_with($path, 'Unbestimmt/')
        || str_contains($path, '/Unbestimmt/') || str_ends_with($path, '/Unbestimmt');
}

/** The find's own folder: „2026-08-29 Krün wk-00554" — day, municipality, number, each when there. */
function cloud_leaf_folder(string $day, string $municipality, string $number): string
{
    $parts = array_filter([trim($day), cloud_segment_clean(trim($municipality)), cloud_segment_clean(trim($number))],
        static fn(string $p): bool => $p !== '');
    return cloud_segment_clean(implode(' ', $parts));
}

/**
 * THE KINGDOMS LIE UNDER „ARTEN" (18.11.2026: „Wollen wir statt
 * die Reiche direkt in den Root der Freigabe zu platzieren, ein Verzeichnis
 * Arten machen und dort die Reiche dann rein, so wie das Verzeichnis
 * Listen?"). The app's folder holds two trees side by side — „Arten/" for
 * every find of a species list, determined or not, and „Listen/" for the
 * points of the other lists (cloud_list_parts) — and nothing else of them
 * on its top level. The find without any list goes to „Arten/Unbestimmt"
 * too: it is filed as a find. Asked when three people had about 220
 * species in the cloud between them; the renaming moved them over.
 */
const CLOUD_SPECIES_FOLDER = 'Arten';

/**
 * WHERE A POINT OUTSIDE EVERY SPECIES LIST GOES (18.11.2026:
 * „bei Bildern von irgendwelchen Gebieten oder Waldbrandflächen landen die
 * Bilder im Ordner unbestimmte Art. Obwohl sie in einer Liste sind, die
 * keine Artliste ist" — and his answer to the proposal: „nimm es Listen,
 * nenn es Listen … dort dann den Listennamen und dann den Fundpunktnamen
 * und darin das Bild"):
 *
 *     Listen/Waldbrände/Brandfläche Hagen/
 *
 * Until then the folder followed the NAME alone: a burn site's title
 * „Brandfläche Hagen" found no genus and went to „Unbestimmt", as if it
 * were a fungus nobody had named yet — and an area called „Russula-Wiese"
 * would even have been filed under Russula.
 *
 * A SPECIES LIST WINS. A point in a species list is a find, whatever else
 * it stands in; the branch is only for points in lists that hold no species
 * at all. A point in no list keeps the old way („Unbestimmt" or its name's
 * genus). Lists that fill themselves by a filter (auto lists) are no
 * filing place — what they show stands in another list besides. Of several
 * lists, the first by the sidebar's order names the folder.
 *
 * THE POINT'S NAME IS THE FOLDER, its number only where another point of
 * the same list carries the same name — two „Brandfläche" would otherwise
 * pour their pictures into one folder. Derived each time like every name
 * here (cloud_names.php): a second namesake coming in moves the first
 * one's pictures to the numbered folder by the next run. A point without a
 * name takes its number.
 *
 * @return string[]|null ['Listen', list, point] — null for a find
 */
function cloud_list_parts(PDO $pdo, array $point): ?array
{
    $id = (int) ($point['id'] ?? 0);
    if ($id <= 0 || !column_exists($pdo, 'map_lists', 'is_species')) {
        return null;
    }
    $auto = column_exists($pdo, 'map_lists', 'filter') ? ' AND l.filter IS NULL' : '';
    $stmt = $pdo->prepare('SELECT l.id, l.name, l.is_species FROM map_point_lists pl
                             JOIN map_lists l ON l.id = pl.list_id
                            WHERE pl.point_id = ? AND l.deleted_at IS NULL' . $auto . '
                            ORDER BY l.is_species DESC, l.sort_order, l.id');
    $stmt->execute([$id]);
    $lists = $stmt->fetchAll();
    if ($lists === [] || (int) $lists[0]['is_species'] === 1) {
        return null;
    }
    $list = cloud_segment_clean(trim((string) $lists[0]['name'])) ?: 'Ohne Namen';
    $title = trim((string) ($point['title'] ?? ''));
    $number = cloud_find_number($point);
    $leaf = cloud_segment_clean($title);
    if ($leaf === '') {
        $leaf = cloud_segment_clean($number);
    } else {
        $twin = $pdo->prepare('SELECT 1 FROM map_point_lists pl
                                 JOIN map_points p ON p.id = pl.point_id
                                WHERE pl.list_id = ? AND p.id <> ? AND p.deleted_at IS NULL
                                  AND LOWER(TRIM(p.title)) = LOWER(?) LIMIT 1');
        $twin->execute([(int) $lists[0]['id'], $id, $title]);
        if ($twin->fetchColumn() !== false && $number !== '') {
            $leaf = cloud_segment_clean($leaf . ' ' . $number);
        }
    }
    return ['Listen', $list, $leaf !== '' ? $leaf : 'Ohne Namen'];
}

/**
 * The folder for a find — the taxonomic one, or the catch-all.
 *
 * ONE PLACE FOR BOTH ROUTES. The browser upload and the photos moved on
 * from this server have to agree on where a picture belongs, and two
 * copies of that decision would drift apart on the first change.
 *
 * 'provisional' says which of the two it was. A picture in "Unbestimmt" is
 * not finished being filed: it moves on by itself once the find carries a
 * name (see photo_cloud.php).
 *
 * AT ANY STORAGE (26.09.2026, „die Ordnerstruktur anlegen" at every cloud):
 * $s is the storage, $rootId where the app writes in it —
 * cloud_home_resolve() says where that is.
 *
 * @return array{folderid: string|int, path: string[], moved: int, provisional: bool}
 */
function cloud_target_folder(
    array $s,
    string|int $rootId,
    PDO $pdo,
    int $userId,
    array $point
): array {
    // A point outside every species list: its list's branch (cloud_list_parts), filed for good.
    $listed = cloud_list_parts($pdo, $point);
    if ($listed !== null) {
        return [
            'folderid'    => storage_path_ensure($s, $rootId, $listed),
            'path'        => $listed,
            'moved'       => 0,
            'provisional' => false,
        ];
    }
    $find = cloud_find_from_point($pdo, $userId, $point);
    if ($find !== null) {
        return storage_find_folder($s, $rootId, $find) + ['provisional' => false];
    }

    $parts = cloud_undetermined_parts($point, cloud_names_ready($pdo) ? cloud_find_number($point) : '', $pdo);

    return [
        'folderid'    => storage_path_ensure($s, $rootId, $parts),
        'path'        => $parts,
        'moved'       => 0,
        'provisional' => true,
    ];
}

/**
 * Move a file into another folder.
 *
 * renamefile WITHOUT toname: pCloud reads that as "same name, new folder".
 * The same trick as pcloud_folder_move() one storey up — passing the name
 * along would rename it to itself and fail the moment two files of the same
 * name meet.
 */
function pcloud_file_move(string $host, string $token, int $fileId, int $toFolderId): void
{
    pcloud_call($host, 'renamefile', [
        'access_token' => $token,
        'fileid'       => $fileId,
        'tofolderid'   => $toFolderId,
    ]);
}

/* The pCloud side of the seam's picture family (26.09.2026) — the calls
   that stood inline at five places (stat, deletefile, getfilelink) with
   the same parameters, now each in one place. „(2009)" is pCloud's
   „file not found", and gone counts as an answer, not an error. */

/** @return array{name: string, size: int, parent: string, type: string}|null */
function pcloud_file_meta(string $host, string $token, int $fileId): ?array
{
    try {
        $meta = pcloud_call($host, 'stat', ['access_token' => $token, 'fileid' => $fileId])['metadata'] ?? [];
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), '(2009)')) {
            return null;
        }
        throw $e;
    }
    return [
        'name'   => (string) ($meta['name'] ?? ''),
        'size'   => (int) ($meta['size'] ?? 0),
        'parent' => (string) ($meta['parentfolderid'] ?? ''),
        'type'   => (string) ($meta['contenttype'] ?? ''),
    ];
}

/** Into pCloud's trash — recoverable there. Gone already counts as deleted. */
function pcloud_file_delete(string $host, string $token, int $fileId): void
{
    try {
        pcloud_call($host, 'deletefile', ['access_token' => $token, 'fileid' => $fileId]);
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), '(2009)')) {
            throw $e;
        }
    }
}

/**
 * The address of the file itself — getfilelink and not getthumblink: the
 * file, not a rendering of it. Bound to the asking address like every
 * pCloud link (pcloud_deliver says why), so it is fetched here, never
 * handed to the browser. Null when there is none.
 */
function pcloud_file_link(string $host, string $token, int $fileId): ?string
{
    try {
        $d = pcloud_call($host, 'getfilelink', ['access_token' => $token, 'fileid' => $fileId]);
    } catch (RuntimeException $e) {
        error_log('getfilelink (' . $fileId . '): ' . $e->getMessage());
        return null;
    }
    $hosts = (array) ($d['hosts'] ?? []);
    return $hosts === [] ? null
         : 'https://' . rtrim((string) $hosts[0], '/') . '/' . ltrim((string) ($d['path'] ?? ''), '/');
}

/**
 * A folder's parent, its name and what lies in it — one listfolder. Null
 * when the folder is gone (2005).
 *
 * @return ?array{parent: string, name: string, entries: list<array{id: string, name: string, folder: bool}>}
 */
function pcloud_folder_state(string $host, string $token, int $folderId): ?array
{
    try {
        $d = pcloud_call($host, 'listfolder', ['access_token' => $token, 'folderid' => $folderId]);
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), '(2005)')) {
            return null;
        }
        throw $e;
    }
    $m = (array) ($d['metadata'] ?? []);
    $entries = [];
    foreach ((array) ($m['contents'] ?? []) as $e) {
        $folder = !empty($e['isfolder']);
        $entries[] = [
            'id'     => (string) ($folder ? ($e['folderid'] ?? '') : ($e['fileid'] ?? '')),
            'name'   => (string) ($e['name'] ?? ''),
            'folder' => $folder,
        ];
    }
    return [
        'parent'  => (string) ($m['parentfolderid'] ?? ''),
        'name'    => (string) ($m['name'] ?? ''),
        'entries' => $entries,
    ];
}

/**
 * The true number of the folder pCloud calls 0 — inside a private app the
 * app folder (cloud_root_id). The folders one storey down name THIS as
 * their parent, never 0, so whoever walks upwards must know it to stop.
 */
function pcloud_root_real_id(string $host, string $token): string
{
    $d = pcloud_call($host, 'listfolder', ['access_token' => $token, 'folderid' => 0, 'nofiles' => 1]);
    $id = (string) ($d['metadata']['folderid'] ?? '');
    if ($id === '') {
        throw new RuntimeException(t('pCloud nennt keine Kennung für den App-Ordner.'));
    }
    return $id;
}

/**
 * Delete a folder ONLY WHEN EMPTY — and pCloud keeps that promise itself:
 * deletefolder refuses a folder that holds anything (2006), so even a file
 * that arrived a moment after the look cannot be swept along. Into pCloud's
 * trash. Gone already (2005) counts as deleted.
 *
 * @return bool whether it is gone now
 */
function pcloud_folder_delete_empty(string $host, string $token, int $folderId): bool
{
    try {
        pcloud_call($host, 'deletefolder', ['access_token' => $token, 'folderid' => $folderId]);
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), '(2006)')) {
            return false;
        }
        if (!str_contains($e->getMessage(), '(2005)')) {
            throw $e;
        }
    }
    return true;
}

/** @return array<string, string> the folders in a folder, name => id */
function pcloud_subfolders(string $host, string $token, int $folderId): array
{
    $d = pcloud_call($host, 'listfolder', [
        'access_token' => $token, 'folderid' => $folderId, 'nofiles' => 1,
    ]);
    $out = [];
    foreach ((array) ($d['metadata']['contents'] ?? []) as $e) {
        if (!empty($e['isfolder']) && ($e['name'] ?? '') !== '') {
            $out[(string) $e['name']] = (string) ($e['folderid'] ?? '');
        }
    }
    return $out;
}

/**
 * Fetch a picture from the provider and hand it on to the browser.
 *
 * WHY THE SERVER FETCHES IT. pCloud makes its links out to the IP that
 * asked for them and answers a foreign address with "This link was
 * generated for another IP address" — measured 18.08.2026, documented
 * nowhere. So a link fetched here is useless to the browser, and whoever
 * asks for it has to be the one who collects the bytes.
 *
 * A 200 IS NOT SUCCESS. That very refusal arrives as plain text with status
 * 200; handed on unchecked it would reach the browser as a "picture", which
 * draws a broken frame and tells nobody why. Hence the content type has to
 * begin with image/.
 *
 * STREAMED, NOT READ INTO MEMORY: an original is whatever the camera made
 * of it, and reading a raw file into a string first would meet memory_limit
 * on a shared host — at exactly the files this exists for.
 *
 * @return bool true when bytes went out; false means: nothing was sent, the
 *              caller still owes the browser an answer.
 */
function pcloud_deliver(string $link, string $note = ''): bool
{
    return cloud_deliver([['url' => $link, 'headers' => '']], $note);
}

/**
 * The same for any provider (26.09.2026): the candidates storage_file_fetch()
 * names, tried in order — the first that answers with a picture goes out.
 * Drive wants its token as a header; the others' addresses carry
 * everything themselves.
 *
 * An id points at the same bytes for ever, so the browser keeps them a
 * year by default; whoever cannot promise that names a $cache of its own.
 * $withoutGps hands a JPEG on with its coordinates taken out
 * (picture_without_gps) and refuses any other kind, whose GPS this server
 * cannot take out — the caller then offers the preview instead.
 *
 * @param list<array{url: string, headers: string}> $candidates
 */
function cloud_deliver(array $candidates, string $note = '', string $cache = 'private, max-age=31536000, immutable',
    bool $withoutGps = false): bool
{
    foreach ($candidates as $at) {
        if (cloud_deliver_one((string) $at['url'], (string) ($at['headers'] ?? ''), $note,
                ($at['follow'] ?? true) === true, $cache, $withoutGps)) {
            return true;
        }
    }
    return false;
}

/** One address: fetched, checked for a picture, streamed on. See pcloud_deliver(). */
function cloud_deliver_one(string $link, string $headers, string $note, bool $follow = true,
    string $cache = 'private, max-age=31536000, immutable', bool $withoutGps = false): bool
{
    $stream = @fopen($link, 'rb', false, stream_context_create([
        'http' => ['timeout' => PCLOUD_TIMEOUT, 'ignore_errors' => true, 'header' => $headers,
                   'follow_location' => $follow ? 1 : 0],
    ]));

    $type   = '';
    $length = null;
    foreach ($http_response_header ?? [] as $header) {
        if (stripos($header, 'content-type:') === 0) {
            $type = strtolower(trim(substr($header, 13)));
        } elseif (stripos($header, 'content-length:') === 0) {
            $length = (int) trim(substr($header, 15));
        }
    }

    if ($stream === false || !str_starts_with($type, 'image/')) {
        error_log('cloud_deliver' . ($note === '' ? '' : ' (' . $note . ')')
            . ': provider answered "' . $type . '"');
        if ($stream !== false) {
            fclose($stream);
        }
        return false;
    }

    /* ONLY RASTER PICTURES GO OUT INLINE (security review, 23.09.2026),
       and since 01.11.2026 the BYTES decide, not the provider's word.
       „image/" also covers image/svg+xml — a picture that can carry
       script. The fileid behind a recording comes from the client, so
       anybody who may attach to a find could record an SVG of his own
       folder and send a viewer the link: the script would run in our
       origin, under the viewer's session. And a provider names the type
       by the file's NAME — an HTML page saved as „x.jpg" arrives as
       image/jpeg. So inline needs both: a raster type from the provider
       AND raster bytes (picture_type), and the type that goes out is the
       bytes'. Everything else — SVG, a page in disguise, the camera raw
       formats browsers do not draw anyway — goes out as an opaque
       download, in the sandbox of includes/picture_fence.php. */
    $head = '';
    while (strlen($head) < 16 && !feof($stream)) {
        $chunk = fread($stream, 16 - strlen($head));
        if ($chunk === false || $chunk === '') break;
        $head .= $chunk;
    }
    $bare   = trim(explode(';', $type)[0]);
    $raster = in_array($bare, ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp',
        'image/avif', 'image/heic', 'image/heif', 'image/tiff', 'image/bmp'], true);
    $real = $raster ? picture_type($head) : null;
    if ($raster && $real === null) {
        error_log('cloud_deliver' . ($note === '' ? '' : ' (' . $note . ')')
            . ': "' . $bare . '" whose bytes are no picture');
    }
    if ($withoutGps) {
        if ($real !== 'image/jpeg') {
            fclose($stream);
            return false;
        }
        $head = picture_without_gps(picture_jpeg_head($stream, $head));
    }
    picture_headers($real, $length, $cache);
    echo $head;
    fpassthru($stream);
    fclose($stream);

    return true;
}

/** The provider's ceiling for the height of a preview — see below. */
const PCLOUD_THUMB_MAX_HEIGHT = 1024;

/**
 * A ready-to-use address for the preview of one recording.
 *
 * THE LINK CARRIES NO TOKEN, and that is the whole reason previews are
 * resolved here on the server instead of in the browser. pCloud answers with
 * hosts and a path that together form a public address, valid until
 * 'expires'. So an <img src> works without the secret ever going near the
 * page — the browser only gets it for the upload itself, where there is no
 * way around it.
 *
 * SIZES ARE NOT FREE. pCloud takes 16 to 2048 wide and 16 to 1024 high, and
 * both have to be divisible by four or five. 320 satisfies both — a number
 * one cannot make up, hence it stands here and not at the call site.
 *
 * AND THE TWO LIMITS DIFFER, which is the trap: a square box built from the
 * width alone is fine at 320 and refused at 2048, because no picture may be
 * that tall. The box is therefore squared off against the height limit here
 * — the caller says how big, not how the provider counts. The picture keeps
 * its proportions either way; pCloud fits it inside the box rather than
 * stretching it.
 *
 * Returns null when there is no preview: a raw file the provider cannot read,
 * a deleted recording. That is not an error, it is an answer.
 */
function pcloud_thumb_link(
    string $host,
    string $token,
    int $fileid,
    int $size = 320,
    ?string &$problem = null
): ?string {
    $problem = null;

    try {
        $d = pcloud_call($host, 'getthumblink', [
            'access_token' => $token,
            'fileid'       => $fileid,
            'size'         => $size . 'x' . min($size, PCLOUD_THUMB_MAX_HEIGHT),
        ]);
    } catch (RuntimeException $e) {
        // A raw file the provider cannot read is the normal case here, and
        // the tile then shows the name. THE REASON GOES ALONG ALL THE SAME,
        // because from outside these look exactly alike: "this file has no
        // preview", "the token is no longer valid", "the provider is
        // down". The first needs no action, the second needs reconnecting,
        // and telling them apart by looking at a grey tile is impossible —
        // which cost an evening of guessing.
        error_log('getthumblink (' . $fileid . '): ' . $e->getMessage());
        $problem = $e->getMessage();
        return null;
    }

    $hosts = (array) ($d['hosts'] ?? []);
    $path  = (string) ($d['path'] ?? '');
    if ($hosts === [] || $path === '') {
        $problem = t('pCloud nennt keine Adresse für die Vorschau.');
        return null;
    }

    // The slash between the two is added here rather than assumed. Host and
    // path arrive as two separate values, and a link glued together wrong
    // fails as an unloadable picture — which looks exactly like a picture
    // that is not there yet, and is the harder of the two to tell apart.
    return 'https://' . rtrim((string) $hosts[0], '/') . '/' . ltrim($path, '/');
}

/**
 * Who is this, and how much room is left?
 *
 * The token goes along as 'access_token' — that is what the official PHP SDK
 * does. 'auth' is the parameter for tokens from the ordinary login and is a
 * different thing; mixing the two yields "Log in failed" with a valid token.
 *
 * The same keys as gdrive_userinfo() and onedrive_userinfo() — 'used'
 * was 'belegt' until 26.09.2026, the last German key of the family.
 *
 * @return array{email: string, quota: int, used: int}
 */
function pcloud_userinfo(string $host, string $token): array
{
    $d = pcloud_call($host, 'userinfo', ['access_token' => $token]);

    return [
        'email' => (string) ($d['email'] ?? ''),
        'quota' => (int) ($d['quota'] ?? 0),
        'used'  => (int) ($d['usedquota'] ?? 0),
    ];
}

/**
 * All of a user's connections, by provider.
 *
 * WITHOUT THE SECRET — see the long comment at the top. What comes out of
 * here may wander into a view without danger.
 *
 * THE CLOUD A MOVE IS EMPTYING IS NOT CONNECTED (26.09.2026): while a move
 * runs, the old account row stays for its key, and this list leaves it
 * out — so everything that asks „which cloud?" (the tile, the backup, the
 * gates for new pictures, the one-storage guard) sees exactly one. Only
 * the door a picture is read through where it lies asks for it back:
 * $withRetiring, from cloud_storage_for() and the token refreshers
 * (includes/cloud_moves.php tells the whole switch).
 *
 * @return array<string, array> provider key => row
 */
function cloud_connections(PDO $pdo, int $userId, bool $withRetiring = false): array
{
    if (!cloud_ready($pdo)) {
        return [];
    }

    // 'region' only from migration 0054 on — the code goes live before the
    // migration, and a missing column would break the whole tab, not just one
    // line of it.
    $hasOrt = column_exists($pdo, 'map_cloud_accounts', 'region');
    // And the moves only from 0142 on.
    $skipRetiring = !$withRetiring && table_exists($pdo, 'map_cloud_moves');

    $stmt = $pdo->prepare(
        'SELECT a.id, a.provider, ' . ($hasOrt ? 'a.region,' : 'NULL AS region,') . ' a.account, a.folder,
                a.expires_at, a.checked_at, a.created_at, a.updated_at
           FROM map_cloud_accounts a WHERE a.user_id = ?'
        . ($skipRetiring
            ? ' AND NOT EXISTS (SELECT 1 FROM map_cloud_moves m
                                 WHERE m.user_id = a.user_id AND m.status = "running"
                                   AND m.from_provider = a.provider)'
            : '')
    );
    $stmt->execute([$userId]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        // Unknown providers fall out: whoever enters a row by hand does not
        // get an interface for it as a gift.
        if (cloud_provider_valid((string) $row['provider'])) {
            $out[(string) $row['provider']] = $row;
        }
    }
    return $out;
}

/* ---------------------------------------------------------------------------
 * The OAuth pair as it lies in the secret column — shared by Google and
 * Microsoft, and by whoever comes third.
 *
 * PACKED IN ONE PLACE. Both providers store {access, refresh} as JSON in
 * the encrypted secret; two private copies of that shape were the pair
 * that drifts apart (they began as gdrive_secret_pack/parse and moved up
 * here when OneDrive arrived). pCloud stays outside: its secret is one
 * eternal token, a bare string, and wrapping it would change stored data
 * for the sake of symmetry.
 * ------------------------------------------------------------------------- */

function cloud_tokens_pack(string $access, string $refresh): string
{
    return json_encode(['access' => $access, 'refresh' => $refresh],
        JSON_UNESCAPED_SLASHES);
}

/** @return array{access: string, refresh: string}|null */
function cloud_tokens_parse(?string $secret): ?array
{
    $d = json_decode((string) $secret, true);
    if (!is_array($d) || ($d['access'] ?? '') === '' || ($d['refresh'] ?? '') === '') {
        return null;
    }
    return ['access' => (string) $d['access'], 'refresh' => (string) $d['refresh']];
}

/** The moment an access token dies, as expires_at stores it. */
function cloud_token_expiry(int $expiresIn): string
{
    // A minute of slack: a token that dies mid-upload fails the upload,
    // and the clock here and the provider's need not agree to the second.
    return date('Y-m-d H:i:s', time() + max(60, $expiresIn - 60));
}

/**
 * THE one connected storage — provider, connection and a usable token.
 *
 * ONE STORAGE PER ACCOUNT (27.08.2026: „man kann immer nur zu
 * einem connected sein"). The table could hold one row per provider — the
 * unique key was built that way — but the application never wanted two:
 * every upload would become the question "into which?", and the backup
 * would have to answer it every night without anyone there to ask.
 * cloud_process() enforces the rule at the connect button; this function
 * leans on it and simply takes the first valid connection it finds.
 *
 * THE TOKEN IS READY TO USE. For pCloud that is the stored secret as it
 * is; for Google and Microsoft it is a FRESH access token — the *_access()
 * gates refresh behind this call, so no caller ever thinks about expiry;
 * for S3 it is the parsed key bundle, static by nature. NULL as the whole
 * answer means: nothing connected, or the connection is no longer usable
 * (an OAuth refresh revoked) — the callers treat both as the ordinary
 * "no cloud".
 *
 * @return array{provider: string, connection: array, token: string|array,
 *               host: ?string}|null   host only for pCloud
 */
function cloud_storage(PDO $pdo, int $userId): ?array
{
    foreach (cloud_connections($pdo, $userId) as $provider => $connection) {
        $s = cloud_storage_open($pdo, $userId, (string) $provider, $connection);
        if ($s !== null) {
            return $s;
        }
    }
    return null;
}

/**
 * The storage of ONE NAMED provider — for a picture, which lives where it
 * was put: a picture row carries its provider, and reading, moving or
 * deleting it must knock at that door even when the account has since
 * connected another (the move tool's case, 26.09.2026). NULL
 * when that provider is not connected, or no longer usable.
 */
function cloud_storage_for(PDO $pdo, int $userId, string $provider): ?array
{
    // The retiring cloud too: its pictures are read where they lie until
    // the move has carried the last of them.
    $connection = cloud_connections($pdo, $userId, true)[$provider] ?? null;
    return $connection === null ? null : cloud_storage_open($pdo, $userId, $provider, $connection);
}

/** One connection opened for use — the token fresh (Drive, OneDrive) or parsed (S3). */
function cloud_storage_open(PDO $pdo, int $userId, string $provider, array $connection): ?array
{
    if ($provider === 'pcloud') {
        $token = cloud_secret($pdo, $userId, 'pcloud');
        return $token === null ? null : [
            'provider'   => 'pcloud',
            'connection' => $connection,
            'token'      => $token,
            'host'       => pcloud_host($connection['region'] ?? null),
        ];
    }
    if ($provider === 'gdrive') {
        require_once __DIR__ . '/gdrive.php';
        $access = gdrive_access($pdo, $userId);
        return $access === null ? null : [
            'provider'   => 'gdrive',
            'connection' => $connection,
            'token'      => $access,
            'host'       => null,
        ];
    }
    if ($provider === 'onedrive') {
        require_once __DIR__ . '/onedrive.php';
        $access = onedrive_access($pdo, $userId);
        return $access === null ? null : [
            'provider'   => 'onedrive',
            'connection' => $connection,
            'token'      => $access,
            'host'       => null,
        ];
    }
    if ($provider === 's3') {
        require_once __DIR__ . '/s3.php';
        // No refresh, no expiry: S3 keys are static. The whole bundle
        // rides in 'token' — the seam hands it to the s3_* family.
        $bundle = s3_bundle_parse(cloud_secret($pdo, $userId, 's3'));
        return $bundle === null ? null : [
            'provider'   => 's3',
            'connection' => $connection,
            'token'      => $bundle,
            'host'       => null,
        ];
    }
    return null;
}

/* ---------------------------------------------------------------------------
 * The storage seam: the operations that go to WHICHEVER provider is
 * connected. The backup spoke only these from the start; the pictures move
 * onto them since 26.09.2026 („alle Clouds richtig nutzen"),
 * and the picture family below the upload was added for them.
 *
 * IDS PASS THROUGH UNTYPED — pCloud numbers, Drive names. Whoever compares
 * or computes with one has already lost; they are handed back, nothing
 * else. The backup's column was widened for exactly this (migration 0086).
 * ------------------------------------------------------------------------- */

/**
 * Where the storage's writable top is — 0, 'root', 'approot', or for S3
 * the empty PREFIX: there are no folders there, only keys, and the top of
 * nothing is the empty string.
 */
function storage_root(array $s): string|int
{
    return match ($s['provider']) {
        'gdrive'   => 'root',
        'onedrive' => 'approot',
        's3'       => '',
        default    => cloud_root_id($s['connection']),
    };
}

/**
 * @return array{folderid: string|int, created: bool}
 *
 * For S3 this is pure string work — "Datensicherung" under '' becomes the
 * prefix "Datensicherung/", no request, nothing that could fail: a prefix
 * springs into being with its first object.
 */
function storage_folder_ensure(array $s, string|int $parentId, string $name): array
{
    return match ($s['provider']) {
        'gdrive'   => gdrive_folder_ensure($s['token'], (string) $parentId, $name),
        'onedrive' => onedrive_folder_ensure($s['token'], (string) $parentId, $name),
        's3'       => ['folderid' => $parentId . $name . '/', 'created' => false],
        default    => pcloud_folder_ensure($s['host'], $s['token'], (int) $parentId, $name),
    };
}

/** @return array<string, array{fileid: string|int, size: int, modified: string}> */
function storage_folder_files(array $s, string|int $folderId): array
{
    return match ($s['provider']) {
        'gdrive'   => gdrive_folder_files($s['token'], (string) $folderId),
        'onedrive' => onedrive_folder_files($s['token'], (string) $folderId),
        's3'       => s3_folder_files($s['token'], (string) $folderId),
        default    => pcloud_folder_files($s['host'], $s['token'], (int) $folderId),
    };
}

/**
 * The name a folder carries NOW, by its id — null when it is gone.
 *
 * FOR THE APP'S OWN FOLDER, where the app makes one (cloud_home_resolve):
 * the id is what it trusts, the name is what the user may have changed by
 * hand, so a folder renamed or moved in Drive is used where it lies
 * instead of a second one being created beside it. S3 has no ids — the
 * prefix IS the name. pCloud and OneDrive never get here: their app
 * folder is the provider's, not ours to find or rename.
 */
function storage_folder_name(array $s, string|int $folderId): ?string
{
    return match ($s['provider']) {
        'gdrive' => gdrive_folder_name($s['token'], (string) $folderId),
        's3'     => rtrim((string) $folderId, '/') ?: null,
        default  => throw new LogicException('Nur Drive und S3 haben einen eigenen App-Ordner der Karte.'),
    };
}

/**
 * Rename a folder where it lies — nothing is uploaded again. Only Drive:
 * an S3 prefix is part of every key under it, so a new name is a new
 * prefix there (cloud_home_rename says so).
 */
function storage_folder_rename(array $s, string|int $folderId, string $name): void
{
    match ($s['provider']) {
        'gdrive' => gdrive_folder_rename($s['token'], (string) $folderId, $name),
        default  => throw new LogicException('Umbenennen kann hier nur Google Drive.'),
    };
}

/* ---------------------------------------------------------------------------
 * The app's own folder (23.09.2026).
 *
 * pCloud (as a Private App) and OneDrive give the app a folder of its own —
 * Applications/<name>, Apps/<name> — and everything the app writes lives
 * inside it. Google Drive (drive.file) and S3 give none: the app's files
 * would lie loose in the root beside the user's own. So there the app MAKES
 * its folder, „YASAIMAPP", and puts everything into it: the backup's
 * „Datensicherung" now, the voucher photos the day they may go there. At
 * every provider the order is then the same — one folder of the app,
 * inside it what the app writes — and only the folder in the root can meet
 * a name of the user's; that one the user may rename (the Cloud tab).
 *
 * It sits on the connection (home_folder, home_folder_id — migration 0120):
 * disconnecting takes the arrangement with it, like the backup's.
 * ------------------------------------------------------------------------- */

const CLOUD_HOME_DEFAULT = 'YASAIMAPP';

/** Does the app make its own folder at this provider? */
function cloud_has_own_home(string $provider): bool
{
    return in_array($provider, ['gdrive', 's3'], true);
}

function cloud_home_ready(PDO $pdo): bool
{
    return cloud_ready($pdo) && column_exists($pdo, 'map_cloud_accounts', 'home_folder_id');
}

/**
 * The connection row — the one with a secret, as everywhere.
 *
 * BY PROVIDER where the caller knows it (26.09.2026): during a move two
 * rows exist, and the app's folder of the one cloud is not the other's —
 * „LIMIT 1" would have handed the new cloud the old one's folder id. The
 * tab, which means „the cloud", asks without and gets the connected one.
 */
function cloud_home_row(PDO $pdo, int $userId, ?string $provider = null): ?array
{
    if ($provider === null) {
        $provider = array_key_first(cloud_connections($pdo, $userId));
        if ($provider === null) {
            return null;
        }
    }
    $stmt = $pdo->prepare(
        'SELECT id, provider, home_folder, home_folder_id, backup_at FROM map_cloud_accounts
          WHERE user_id = ? AND provider = ? AND secret IS NOT NULL LIMIT 1'
    );
    $stmt->execute([$userId, $provider]);
    return $stmt->fetch() ?: null;
}

/** The app folder's name as we know it — without asking the provider, for the tab. */
function cloud_home_name(PDO $pdo, int $userId): string
{
    if (!cloud_home_ready($pdo)) {
        return CLOUD_HOME_DEFAULT;
    }
    return (string) (cloud_home_row($pdo, $userId)['home_folder'] ?? '') ?: CLOUD_HOME_DEFAULT;
}

/**
 * A name as the user typed it, made fit for every provider — the rule of
 * the photo folders (cloud_segment_clean): a slash becomes a space, what
 * file systems refuse goes. Only an empty result is refused.
 *
 * @throws InvalidArgumentException
 */
function cloud_home_clean(string $raw): string
{
    $name = trim(mb_substr(cloud_segment_clean($raw), 0, 100), " \t.");
    if ($name === '') {
        throw new InvalidArgumentException(t('Der Ordner braucht einen Namen.'));
    }
    return $name;
}

function cloud_home_save(PDO $pdo, int $accountId, string $name, string|int|null $folderId): void
{
    $pdo->prepare('UPDATE map_cloud_accounts SET home_folder = ?, home_folder_id = ? WHERE id = ?')
        ->execute([$name, $folderId === null ? null : (string) $folderId, $accountId]);
}

/**
 * Where the app writes at this storage — its own folder.
 *
 * pCloud and OneDrive: the provider's app folder, storage_root(). Drive
 * and S3: the folder the app makes — found by its id first (renamed or
 * moved by hand, it is used where it lies, and the new name is taken
 * over), created under the name on record when there is no id yet or it
 * is gone.
 *
 * @return string|int the id (or prefix) to put things into — the seam casts it
 */
function cloud_home_resolve(PDO $pdo, int $userId, array $s): string|int
{
    if (!cloud_has_own_home($s['provider'])) {
        return storage_root($s);
    }
    if (!cloud_home_ready($pdo)) {
        return storage_folder_ensure($s, storage_root($s), CLOUD_HOME_DEFAULT)['folderid'];
    }

    $row  = cloud_home_row($pdo, $userId, $s['provider']) ?? [];
    $name = (string) ($row['home_folder'] ?? '') ?: CLOUD_HOME_DEFAULT;
    $id   = (string) ($row['home_folder_id'] ?? '');

    if ($id !== '' && $s['provider'] !== 's3') {
        $now = storage_folder_name($s, $id);
        if ($now !== null) {
            if ($now !== ($row['home_folder'] ?? null)) {
                cloud_home_save($pdo, (int) $row['id'], $now, $id);
            }
            return $id;
        }
    }

    $made = storage_folder_ensure($s, storage_root($s), $name);
    if ($made['folderid'] === 0 || $made['folderid'] === '') {
        throw new RuntimeException(t('Der Speicher nennt für „%s" keine Ordnerkennung.', $name));
    }
    if (isset($row['id'])) {
        cloud_home_save($pdo, (int) $row['id'], $name, $made['folderid']);
    }
    return $made['folderid'];
}

/**
 * Give the app's folder a new name — from the Cloud tab.
 *
 * RENAMED WHERE IT LIES at Drive, nothing uploaded again. Before the first
 * sweep only the name is stored, without asking the provider — a hiccuping
 * connection must not stand in the way of that. S3 cannot rename a prefix:
 * the new name is a new folder, everything is written into it once more,
 * and the old one stays until the user deletes it — the message says so.
 *
 * @return string the message for the toast
 * @throws InvalidArgumentException|RuntimeException
 */
function cloud_home_rename(PDO $pdo, int $userId, string $raw): string
{
    $name = cloud_home_clean($raw);
    if (!cloud_home_ready($pdo)) {
        throw new RuntimeException(t('Den Ordnernamen zu ändern braucht Migration 0120 — die ist noch nicht eingespielt.'));
    }
    $row = cloud_home_row($pdo, $userId);
    if ($row === null) {
        throw new RuntimeException(t('Dafür muss erst ein Speicher verbunden sein.'));
    }
    if (!cloud_has_own_home((string) $row['provider'])) {
        throw new InvalidArgumentException(t('Hier schreibt die Karte in den App-Ordner, den der Anbieter ihr gibt — dessen Namen bestimmt die App-Anmeldung, nicht die Karte.'));
    }
    $current = (string) ($row['home_folder'] ?? '') ?: CLOUD_HOME_DEFAULT;
    if ($name === $current) {
        return t('Der Ordner heißt schon „%s".', $name);
    }

    $id = (string) ($row['home_folder_id'] ?? '');
    if ($row['provider'] === 's3' || ($id === '' && $row['backup_at'] === null)) {
        cloud_home_save($pdo, (int) $row['id'], $name, null);
        return ($row['provider'] === 's3' && $row['backup_at'] !== null)
            ? t('Gespeichert. Ab dem nächsten Durchgang schreibt die Karte nach „%s" und legt dort alles einmal neu ab. Der alte Ordner bleibt liegen, bis du ihn bei deinem Anbieter löschst.', $name)
            : t('Gespeichert — der Ordner entsteht beim nächsten Durchgang als „%s".', $name);
    }

    $s = cloud_storage($pdo, $userId);
    if ($s === null) {
        throw new RuntimeException(t('Die Verbindung zur Cloud trägt gerade nicht. Bitte später noch einmal versuchen.'));
    }
    $where = null;
    if ($id !== '' && storage_folder_name($s, $id) !== null) {
        $where = $id;
    } else {
        // Swept before an id was known: found once by its old name.
        $where = storage_folder_ensure($s, storage_root($s), $current)['folderid'];
    }
    if ($where === null || $where === 0 || $where === '') {
        cloud_home_save($pdo, (int) $row['id'], $name, null);
        return t('Gespeichert — der Ordner entsteht beim nächsten Durchgang als „%s".', $name);
    }

    storage_folder_rename($s, $where, $name);
    cloud_home_save($pdo, (int) $row['id'], $name, $where);
    return t('Umbenannt — der Ordner heißt in deiner Cloud jetzt „%s".', $name);
}

/**
 * @param ?string $type the picture's type — Drive and S3 hand back what
 *                      they were given, and the delivery serves only
 *                      image/* inline; pCloud and OneDrive read the name
 * @return array{fileid: string|int, name: string, size: int}
 */
function storage_upload_bytes(array $s, string|int $folderId, string $bytes,
                              string $name, ?int $mtime = null, bool $replace = false,
                              ?string $type = null): array
{
    return match ($s['provider']) {
        'gdrive'   => gdrive_upload_bytes($s['token'], (string) $folderId, $bytes, $name, $mtime, $replace, $type),
        'onedrive' => onedrive_upload_bytes($s['token'], (string) $folderId, $bytes, $name, $mtime, $replace),
        's3'       => s3_upload_bytes($s['token'], (string) $folderId, $bytes, $name, $mtime, $replace, $type),
        default    => pcloud_upload_bytes($s['host'], $s['token'], (int) $folderId, $bytes, $name, $mtime, $replace),
    };
}

/* ---------------------------------------------------------------------------
 * The picture family of the seam (26.09.2026): look a file up, delete it,
 * move it, list sub-folders, fetch it. Every id stays a string on the way
 * through — pCloud's numbers are cast at pCloud's door, nowhere else.
 * ------------------------------------------------------------------------- */

/**
 * Name, size and parent of one file — null when it is gone.
 *
 * @return array{name: string, size: int, parent: string, type: string}|null
 */
function storage_file_meta(array $s, string|int $fileId): ?array
{
    return match ($s['provider']) {
        'gdrive'   => gdrive_file_meta($s['token'], (string) $fileId),
        'onedrive' => onedrive_file_meta($s['token'], (string) $fileId),
        's3'       => s3_head($s['token'], (string) $fileId),
        default    => pcloud_file_meta($s['host'], $s['token'], (int) $fileId),
    };
}

/**
 * Delete one file. pCloud, Drive and OneDrive lay it into their trash; S3
 * deletes for good. A file already gone counts as deleted everywhere.
 */
function storage_file_delete(array $s, string|int $fileId): void
{
    match ($s['provider']) {
        'gdrive'   => gdrive_file_trash($s['token'], (string) $fileId),
        'onedrive' => onedrive_file_delete($s['token'], (string) $fileId),
        's3'       => s3_delete($s['token'], (string) $fileId),
        default    => pcloud_file_delete($s['host'], $s['token'], (int) $fileId),
    };
}

/**
 * Delete a PICTURE — the file, and at S3 also the previews this app laid
 * beside it into „.vorschau" (s3_preview_key). S3 draws none of its own,
 * so those are ours alone: nobody else would ever clear them away, and the
 * bucket would fill with small pictures of pictures long gone. Elsewhere
 * the provider's own previews leave with the file.
 *
 * A preview left behind is clutter, never a loss — the picture itself is
 * gone, which is what was asked, so a failure there is only logged.
 */
function storage_picture_delete(PDO $pdo, int $userId, array $s, string|int $fileId): void
{
    // Where it lay — asked BEFORE it goes; the folders above may follow it.
    $folder = null;
    if ($s['provider'] !== 's3') {
        try {
            $folder = storage_file_meta($s, $fileId)['parent'] ?? null;
        } catch (RuntimeException $e) {
            $folder = null;
        }
    }
    storage_file_delete($s, $fileId);
    if ($s['provider'] !== 's3') {
        // The empty folders it leaves behind go too (storage_prune_empty).
        if ($folder !== null && $folder !== '') {
            try {
                storage_prune_empty($pdo, $userId, $s, $folder);
            } catch (RuntimeException $e) {
                error_log('storage_picture_delete prune (' . $fileId . '): ' . $e->getMessage());
            }
        }
        return;
    }
    try {
        // Pure string work at S3 (storage_folder_ensure) — no request.
        $home = (string) cloud_home_resolve($pdo, $userId, $s);
        foreach ([320, 2048] as $edge) {
            s3_delete($s['token'], s3_preview_key($home, (string) $fileId, $edge));
        }
    } catch (RuntimeException $e) {
        error_log('storage_picture_delete previews (' . $fileId . '): ' . $e->getMessage());
    }
}

/**
 * HOW MUCH ROOM IS LEFT — for a move that waits because the new cloud was
 * full (includes/cloud_moves.php): one question per run instead of one
 * refused upload. Null where the provider names no limit (S3, an unlimited
 * Drive), and then the move simply tries.
 */
function storage_free(array $s): ?int
{
    $who = match ($s['provider']) {
        'gdrive'   => gdrive_userinfo($s['token']),
        'onedrive' => onedrive_userinfo($s['token']),
        's3'       => null,
        default    => pcloud_userinfo($s['host'], $s['token']),
    };
    if ($who === null || empty($who['quota'])) {
        return null;
    }
    return max(0, (int) $who['quota'] - (int) $who['used']);
}

/**
 * Does a refusal mean „the storage is full"? Each provider says it its
 * own way, and all of them end up in the message of the exception its
 * call throws: Drive's text, the codes Graph puts in brackets, pCloud's
 * result 2008, the S3 hosters' own words (Backblaze's cap, Wasabi's
 * quota). A move that recognises it waits for room instead of giving up
 * picture after picture.
 */
function cloud_full_error(string $message): bool
{
    return preg_match('/storage quota has been exceeded|storageQuotaExceeded|quotaLimitReached'
        . '|insufficientStorage|over quota|\\(2008\\)|QuotaExceeded|storage_cap_exceeded/i', $message) === 1;
}

/**
 * THE WEB ADDRESS OF A FOLDER at its provider — what „Ordner in der Cloud"
 * opens in the photo window (pages/cloud_folder.php), so the user lands
 * among the other shots of the same find instead of at the top of a tree
 * this app built for him. Null where there is none.
 *
 *   pcloud    written out — the folder's number in the file manager
 *   gdrive    written out — the folder's id
 *   onedrive  ASKED FOR: a personal account's folders live on
 *             onedrive.live.com, a work account's on its company's
 *             SharePoint, and the consent (/common/) lets both in — only
 *             Microsoft can name the address (Graph's webUrl)
 *   s3        none: a bucket's console belongs to whoever hosts it —
 *             Amazon, Hetzner, Wasabi, Backblaze — and none of them opens
 *             a prefix by address. The button is not offered there.
 *
 * An unknown folder ('' or '0') opens the provider's top instead.
 */
function cloud_folder_web(PDO $pdo, int $userId, string $provider, string $folderId): ?string
{
    $known = $folderId !== '' && $folderId !== '0';
    switch ($provider) {
        case 'pcloud':
            return $known && ctype_digit($folderId)
                ? 'https://my.pcloud.com/#/filemanager?folder=' . $folderId
                : 'https://my.pcloud.com/';
        case 'gdrive':
            return $known
                ? 'https://drive.google.com/drive/folders/' . rawurlencode($folderId)
                : 'https://drive.google.com/drive/my-drive';
        case 'onedrive':
            $s = cloud_storage_for($pdo, $userId, 'onedrive');
            if ($s === null) {
                return null;
            }
            try {
                $d = onedrive_call($s['token'], 'GET', ONEDRIVE_API
                    . onedrive_item($known ? $folderId : 'approot') . '?$select=webUrl');
            } catch (RuntimeException $e) {
                error_log('cloud_folder_web (onedrive ' . $folderId . '): ' . $e->getMessage());
                return null;
            }
            $web = (string) ($d['webUrl'] ?? '');
            return str_starts_with($web, 'https://') ? $web : null;
        default:
            return null;
    }
}

/**
 * Move one file into another folder, keeping its name.
 *
 * @return string the file's id afterwards — THE SAME at pCloud, Drive and
 *                OneDrive, A NEW ONE at S3, where the key is the id
 */
function storage_file_move(array $s, string|int $fileId, string|int $toFolderId): string
{
    $id = (string) $fileId;
    switch ($s['provider']) {
        case 'gdrive':
            gdrive_move($s['token'], $id, (string) $toFolderId);
            return $id;
        case 'onedrive':
            onedrive_move($s['token'], $id, (string) $toFolderId);
            return $id;
        case 's3':
            $slash = strrpos($id, '/');
            return s3_move($s['token'], $id, (string) $toFolderId . ($slash === false ? $id : substr($id, $slash + 1)));
        default:
            pcloud_file_move($s['host'], $s['token'], (int) $fileId, (int) $toFolderId);
            return $id;
    }
}

/**
 * Move a file into a folder AND give it a name there — one call where the
 * provider allows it (pCloud's renamefile takes both, Drive's and
 * OneDrive's PATCH too); at S3 a copy to the new key. Returns the file's id
 * afterwards, which only S3 changes.
 *
 * THE CALLER LOOKS FIRST. pCloud REPLACES a file of the same name in the
 * target without a word („If the destination file already exists it will
 * be replaced atomically", its documentation, read 04.10.2026), S3's copy
 * overwrites too. So nobody calls this without having listed the target
 * folder and found the name free or the file's own (cloud_names.php).
 */
function storage_file_place(array $s, string|int $fileId, string|int $toFolderId, string $toName): string
{
    $id = (string) $fileId;
    switch ($s['provider']) {
        case 'gdrive':
            gdrive_place($s['token'], $id, (string) $toFolderId, $toName);
            return $id;
        case 'onedrive':
            onedrive_place($s['token'], $id, (string) $toFolderId, $toName);
            return $id;
        case 's3':
            return s3_move($s['token'], $id, (string) $toFolderId . $toName);
        default:
            pcloud_call($s['host'], 'renamefile', [
                'access_token' => $s['token'],
                'fileid'       => (int) $fileId,
                'tofolderid'   => (int) $toFolderId,
                'toname'       => $toName,
            ]);
            return $id;
    }
}

/**
 * Move a folder into another — the letter compartments of the genus tree.
 * Not at S3: a prefix is part of every key under it, so there the tree is
 * laid out so it never has to move (step two of the picture plan).
 */
function storage_folder_move(array $s, string|int $folderId, string|int $toParentId): void
{
    match ($s['provider']) {
        'gdrive'   => gdrive_move($s['token'], (string) $folderId, (string) $toParentId),
        'onedrive' => onedrive_move($s['token'], (string) $folderId, (string) $toParentId),
        's3'       => throw new LogicException('Ein S3-Ordner lässt sich nicht verschieben — sein Name steckt in jedem Schlüssel darunter.'),
        default    => pcloud_folder_move($s['host'], $s['token'], (int) $folderId, (int) $toParentId),
    };
}

/**
 * The folders lying directly in a folder, by name.
 *
 * @return array<string, string> name => id (at S3: the prefix)
 */
function storage_subfolders(array $s, string|int $folderId): array
{
    return match ($s['provider']) {
        'gdrive'   => gdrive_subfolders($s['token'], (string) $folderId),
        'onedrive' => onedrive_subfolders($s['token'], (string) $folderId),
        's3'       => s3_subprefixes($s['token'], (string) $folderId),
        default    => pcloud_subfolders($s['host'], $s['token'], (int) $folderId),
    };
}

/**
 * A folder's parent, its name and what lies in it. Null when the folder
 * is gone — and always at S3, which has no folders: a prefix vanishes with
 * its last key, there is nothing to tidy.
 *
 * @return ?array{parent: string, name: string, entries: list<array{id: string, name: string, folder: bool}>}
 */
function storage_folder_state(array $s, string|int $folderId): ?array
{
    return match ($s['provider']) {
        'gdrive'   => gdrive_folder_state($s['token'], (string) $folderId),
        'onedrive' => onedrive_folder_state($s['token'], (string) $folderId),
        's3'       => null,
        default    => pcloud_folder_state($s['host'], $s['token'], (int) $folderId),
    };
}

/**
 * Delete a folder that is empty — into the provider's trash. pCloud refuses
 * a full one by itself; OneDrive would take the contents along, so there
 * the folder is looked at once more right before (the narrow window that
 * leaves is a file arriving within that second, and the bin holds it then).
 *
 * NEVER AT GOOGLE DRIVE. The app sees there only what it made itself
 * (drive.file, the scope the maintainer chose for exactly that fence): a
 * species folder into which somebody laid a PDF of his own looks EMPTY to
 * us — and trashing a folder trashes everything in it, the PDF included.
 * pCloud and OneDrive show the app the whole of its folder, so there
 * „empty" means empty. At Drive the empty ways stay; the left-over
 * sidecars, which are ours, still go (storage_prune_empty).
 *
 * @return bool whether it is gone now
 */
function storage_folder_delete_empty(array $s, string|int $folderId): bool
{
    if ($s['provider'] === 's3' || $s['provider'] === 'gdrive') {
        return false;
    }
    if ($s['provider'] === 'pcloud') {
        return pcloud_folder_delete_empty($s['host'], $s['token'], (int) $folderId);
    }
    $now = storage_folder_state($s, $folderId);
    if ($now === null) {
        return true;
    }
    if ($now['entries'] !== []) {
        return false;
    }
    onedrive_file_delete($s['token'], (string) $folderId);
    return true;
}

/**
 * „BEARBEITUNG" — THE ONE SUBFOLDER OF A FIND'S FOLDER FOR EVERYTHING THAT
 * IS NOT A PICTURE (10.11.2026: „ein Unterordner für alle
 * Einstellungen … das sieht aufgeräumt aus"; the name his, „einfach nur was
 * er enthält"). Until then each picture's sidecar lay BESIDE it —
 * „… 01 Hauptmotiv.jpg.messwerte.json" between the pictures, one per
 * picture that was measured or edited. Now they lie one storey down, under
 * the same names, and the retouching's patches will join them there.
 *
 * ONE PLACE DECIDES WHERE: these helpers. The writer, the refiling, the
 * renaming and the tidying all go through them, so no caller spells the
 * folder's name or guesses where an old sidecar might lie.
 *
 * OLD PLACES ARE STILL READ. Sidecars written before 10.11.2026 lie beside
 * their picture until the one-time job (includes/sidecar_move.php) carries
 * them down; until then every path that deletes or moves a sidecar looks
 * in both places, so none is left behind to describe a picture that has
 * moved on — or worse, carried down later with data the user had reset.
 */
const SIDECAR_FOLDER = 'Bearbeitung';

/**
 * The „Bearbeitung" folder in a picture's folder — its id, or null where
 * there is none and none was asked for. At S3 a prefix is pure string work
 * and always answers (an empty prefix simply lists nothing).
 */
function storage_sidecar_folder(array $s, string|int $pictureFolder, bool $create): string|int|null
{
    if ($create || $s['provider'] === 's3') {
        return storage_folder_ensure($s, $pictureFolder, SIDECAR_FOLDER)['folderid'];
    }
    return storage_subfolders($s, $pictureFolder)[SIDECAR_FOLDER] ?? null;
}

/**
 * THE PATCHES OF „ENTFERNEN" (11.11.2026) lie in „Bearbeitung" too: one
 * 512 × 512 PNG per piece the AI filled (assets/js/develop.js, aiPieceApply
 * — the one kind of retouching that cannot be described, only kept as
 * pixels). NAMED AFTER THEIR PICTURE like the sidecar, „<picture>.flicken-
 * <id>.png", and for the same reason: every way that follows the sidecar —
 * the refiling, the renaming, the deleting, the stray rule — finds them by
 * that name and needs no list of its own. The id is the piece's in the
 * picture's `develop` (AI_PATCH_ID there).
 */
const DEVELOP_PATCH_PATTERN = '/^(.+)\.flicken-([a-z0-9]{8,32})\.png$/';

/** The file name of one patch of a picture. */
function develop_patch_name(string $pictureName, string $id): string
{
    return $pictureName . '.flicken-' . $id . '.png';
}

/**
 * THE MASK OF „EBENEN" (11.11.2026, includes/layer_sets.php) is the third
 * kind of file in „Bearbeitung": where a picture lies as a layer over
 * others, what was painted of it — one PNG per picture, „<picture>.ebene
 * .png". ONE NAME, NO ID, unlike the patches: a mask is painted on for an
 * hour and saved every few seconds, and each save replaces the file where
 * it lies instead of laying a new one beside it for somebody to tidy.
 */
const LAYER_MASK_SUFFIX = '.ebene.png';

/** The file name of a picture's layer mask. */
function layer_mask_name(string $pictureName): string
{
    return $pictureName . LAYER_MASK_SUFFIX;
}

/**
 * A file of „Bearbeitung" that hangs on a picture by its name, taken apart
 * — a patch („flicken", with its id) or a layer mask („ebene", id empty) —
 * or null for any other file. `kind` says which.
 *
 * @return ?array{picture: string, id: string, kind: string}
 */
function develop_patch_split(string $fileName): ?array
{
    if (preg_match(DEVELOP_PATCH_PATTERN, $fileName, $m) === 1) {
        return ['picture' => $m[1], 'id' => $m[2], 'kind' => 'flicken'];
    }
    if (str_ends_with($fileName, LAYER_MASK_SUFFIX) && strlen($fileName) > strlen(LAYER_MASK_SUFFIX)) {
        return ['picture' => substr($fileName, 0, -strlen(LAYER_MASK_SUFFIX)), 'id' => '', 'kind' => 'ebene'];
    }
    return null;
}

/**
 * Every copy of one picture's sidecar: in „Bearbeitung", and — from before
 * 10.11.2026 — beside the picture. Normally one; both where a sidecar was
 * written anew before the old one was carried down.
 *
 * AND ITS PATCHES (11.11.2026), marked with their id under `patch`: what
 * this list is asked for — moving a picture's sidecar along, deleting it
 * with the picture — is exactly what its patches need too, so the callers
 * carry them without knowing they exist. A patch is never „old". Its
 * layer mask comes the same way, `kind` „ebene" and `patch` empty.
 *
 * @return list<array{fileid: string|int, folder: string|int, old: bool, patch?: string, kind?: string}>
 */
function storage_sidecars_of(array $s, string|int $pictureFolder, string $pictureName): array
{
    require_once __DIR__ . '/photos.php';
    /* FOUND BY THE COMPARISON KEY (22.11.2026, cloud_name_key): the
       picture's name as the row has it and a sidecar or patch named while
       the provider still spelt umlauts the other way are one name here.
       Byte for byte, a renamed picture left its patches behind — and those
       are pixels nobody can write again. */
    $name = cloud_name_key(measure_sidecar_name($pictureName));
    $picture = cloud_name_key($pictureName);
    $out = [];
    $sub = storage_sidecar_folder($s, $pictureFolder, false);
    if ($sub !== null) {
        foreach (storage_folder_files($s, $sub) as $fileName => $f) {
            if (cloud_name_key((string) $fileName) === $name) {
                $out[] = ['fileid' => $f['fileid'], 'folder' => $sub, 'old' => false];
                continue;
            }
            $patch = develop_patch_split((string) $fileName);
            if ($patch !== null && cloud_name_key($patch['picture']) === $picture) {
                $out[] = ['fileid' => $f['fileid'], 'folder' => $sub, 'old' => false, 'patch' => $patch['id'], 'kind' => $patch['kind']];
            }
        }
    }
    foreach (storage_folder_files($s, $pictureFolder) as $fileName => $f) {
        if (cloud_name_key((string) $fileName) === $name) {
            $out[] = ['fileid' => $f['fileid'], 'folder' => $pictureFolder, 'old' => true];
        }
    }
    return $out;
}

/**
 * A picture's sidecar removed wherever it lies — its patches with it
 * (storage_sidecars_of; not its layer mask, see below), and „Bearbeitung" too
 * when that was its last file: an empty folder in every find that was once
 * edited and then reset would be the clutter the folder was made against.
 */
function storage_sidecar_delete(array $s, string|int $pictureFolder, string $pictureName): void
{
    $emptied = null;
    foreach (storage_sidecars_of($s, $pictureFolder, $pictureName) as $side) {
        /* THE LAYER MASK STAYS. This runs too when a picture merely has
           nothing left to say in its sidecar (measure_sidecar_write) — and
           an hour's painting must not go because a title was taken off.
           With the picture itself gone the mask is a stray, and the
           tidying takes it (storage_stray_sidecars). */
        if (($side['kind'] ?? '') === 'ebene') {
            continue;
        }
        storage_file_delete($s, $side['fileid']);
        if (!$side['old']) {
            $emptied = $side['folder'];
        }
    }
    // Drive and S3 never delete a folder here (storage_folder_delete_empty) — no need to look.
    if ($emptied !== null && in_array($s['provider'], ['pcloud', 'onedrive'], true)
        && storage_folder_files($s, $emptied) === []
        && storage_subfolders($s, $emptied) === []) {
        storage_folder_delete_empty($s, $emptied);
    }
}

/**
 * A name as it is COMPARED — never as it is written.
 *
 * TWO SPELLINGS OF ONE UMLAUT LIE IN THE CLOUDS (measured in the
 * maintainer's pCloud, 22.11.2026): „ü" as one sign (NFC — what the
 * database holds and this code sends) and as „u" with two dots after it
 * (NFD — 266 file names and 25 folder names up there, everything named
 * before about 20.11.2026). They look alike in every listing and are
 * different bytes. A write test the same day showed pCloud keeping each
 * spelling exactly as sent and treating the two as different names — so a
 * sidecar „… Krün 02 Sporen.jpg.messwerte.json" compared byte for byte
 * against its picture can come out as belonging to nothing, and the stray
 * rule would delete it.
 *
 * So whoever asks „is this the same name?" asks through this key. Where
 * the server has no Normalizer (the intl extension — the web side has it,
 * a cron's PHP may not) the name comes back as it is, and
 * cloud_name_comparable() says whether a judgement may be built on it.
 */
function cloud_name_key(string $name): string
{
    if ($name === '' || !preg_match('/[\x80-\xFF]/', $name) || !class_exists('Normalizer')) {
        return $name;
    }
    $key = Normalizer::normalize($name, Normalizer::FORM_C);
    return is_string($key) && $key !== '' ? $key : $name;
}

/** May two names be told apart by cloud_name_key()? Plain ASCII always; anything else only where it can normalise. */
function cloud_name_comparable(string $name): bool
{
    return !preg_match('/[\x80-\xFF]/', $name) || class_exists('Normalizer');
}

/**
 * The measure sidecars in a folder whose picture is not beside them — ids.
 * The patches of „Entfernen" likewise (storage_sidecars_of).
 *
 * A SIDECAR WITHOUT ITS PICTURE SAYS NOTHING ANY MORE: it is the safety
 * copy of one picture's measures (measure_sidecar_store), paired by name
 * in the same folder, and with the picture gone it describes nothing. Until
 * 26.09.2026 a cloud recording deleted at the find left its sidecar behind
 * (691caec sends it first since); the maintainer found those in his pCloud on
 * 03.10.2026. A picture renamed by hand orphans its sidecar too — and the
 * next scale change writes a fresh one under the new name.
 *
 * PAIRED BY THE COMPARISON KEY, NOT BY THE BYTES (22.11.2026,
 * cloud_name_key): a picture and its sidecar may carry the two spellings
 * of one umlaut, and a sidecar that only LOOKS unpaired must not go. Where
 * the names cannot be normalised here, a name with anything but ASCII in
 * it is never called a stray — left lying is the smaller harm.
 *
 * @param list<array{id: string, name: string, folder: bool}> $entries
 * @param ?list<string> $pictures the picture names to pair against, when
 *                                they lie elsewhere (the parent of „Bearbeitung")
 * @param bool $sidecarsOnly leave patches and masks alone, whatever they
 *                           pair with — for a sweep that may take only what
 *                           can be written anew (cloud_names_sweep)
 * @return list<string>
 */
function storage_stray_sidecars(array $entries, ?array $pictures = null, bool $sidecarsOnly = false): array
{
    require_once __DIR__ . '/photos.php';
    $suffix = measure_sidecar_name('');
    /* $pictures for a „Bearbeitung" folder: its sidecars belong to the
       pictures one storey UP, so their names are handed in from there. */
    $files = [];
    foreach ($pictures ?? [] as $name) {
        $files[cloud_name_key((string) $name)] = true;
    }
    foreach ($pictures === null ? $entries : [] as $e) {
        if (!$e['folder']) {
            $files[cloud_name_key($e['name'])] = true;
        }
    }
    $stray = [];
    foreach ($entries as $e) {
        if ($e['folder'] || !cloud_name_comparable($e['name'])) {
            continue;
        }
        if (str_ends_with($e['name'], $suffix) && strlen($e['name']) > strlen($suffix)) {
            if (!isset($files[cloud_name_key(substr($e['name'], 0, -strlen($suffix)))])) {
                $stray[] = $e['id'];
            }
            continue;
        }
        if ($sidecarsOnly) {
            continue;
        }
        // A patch of „Entfernen" or a mask of „Ebenen" whose picture is gone belongs to nothing any more (11.11.2026).
        /* ONLY IN „BEARBEITUNG" ($pictures handed in): that folder is the
           app's own. Beside the pictures a „… .ebene.png" may be a file the
           user laid there himself, and it is not ours to judge. */
        $patch = $pictures === null ? null : develop_patch_split($e['name']);
        if ($patch !== null && !isset($files[cloud_name_key($patch['picture'])])) {
            $stray[] = $e['id'];
        }
    }
    return $stray;
}

/**
 * NO WAY THAT LEADS TO NOTHING (03.10.2026: „leere Ordner
 * gleich mitlöschen, und gleich so weit runter mitlöschen, wie es keine
 * Informationen mehr gibt — die Art, die Gattung, etc. — sonst würde man
 * Wege gehen, die zu nichts führen"). From the folder a picture just left,
 * upwards: the species, the genus, the letter … each one deleted while
 * nothing lies in it, and the walk stops at the first that still holds
 * something.
 *
 * A SIDECAR WITHOUT ITS PICTURE COUNTS AS NOTHING (storage_stray_sidecars)
 * and goes first, in every folder the walk passes — also in one that keeps
 * its other pictures. Otherwise one left-over JSON would hold up a whole
 * empty branch, and the old ones heal where somebody is working anyway.
 * What lay on ways nobody walks again was swept ONCE, by a button under
 * Cloud-Anbieter that ran over the whole tree on 04.10.2026 (16 sidecars
 * and the empty folders in the maintainer's pCloud, the only cloud connected
 * before this walk existed) and went again afterwards on his word —
 * 42179d8 holds it, should a tree ever need sweeping again.
 *
 * NEVER THE APP'S OWN FOLDER, nor anything above it — the walk ends there
 * whatever it finds — and never the backup's or the calibration's folder,
 * which hold what no find shows. Three fences for the first: the home as
 * cloud_home_resolve() names it; its REAL id where the provider addresses
 * it by a stand-in (OneDrive's 'approot', pCloud's 0 — whose parentfolderid
 * one storey down is the app folder's true number, measured 16.08.2026,
 * see cloud_root_id); and a folder without a parent, which is a root.
 *
 * Best effort: a provider that refuses only leaves a folder standing, which
 * is where things were before. A target handed out a moment earlier (the
 * app waiting for Wi-Fi) may point into a folder that went meanwhile — its
 * upload fails, and the next round asks for a fresh one, which builds the
 * way anew.
 *
 * @return int how many folders went
 */
function storage_prune_empty(PDO $pdo, int $userId, array $s, string|int $folderId): int
{
    if ($s['provider'] === 's3' || (string) $folderId === '' || (string) $folderId === '0') {
        return 0;
    }
    $home = (string) cloud_home_resolve($pdo, $userId, $s);
    if ($s['provider'] === 'onedrive' && $home === 'approot') {
        $home = onedrive_folder_id($s['token'], 'approot');
    }
    $stop = [$home, (string) storage_root($s), 'approot', 'root', '', '0'];
    $keep = storage_kept_folders();
    $realRoot = $s['provider'] !== 'pcloud';   // pCloud's true number is asked only when the walk climbs

    $gone = 0;
    $at = (string) $folderId;
    for ($level = 0; $level < 16 && !in_array($at, $stop, true); $level++) {
        $state = storage_folder_state($s, $at);
        if ($state === null || $state['parent'] === '' || in_array($state['name'], $keep, true)) {
            break;
        }
        /* „BEARBEITUNG" IS LOOKED INTO (10.11.2026): its sidecars belong to
           the pictures of THIS folder; those whose picture is gone are
           strays like the old ones beside, and a „Bearbeitung" left with
           nothing goes — otherwise one folder of left-overs would hold up
           the whole empty branch above it. */
        $pictures = [];
        foreach ($state['entries'] as $e) {
            if (!$e['folder']) {
                $pictures[] = $e['name'];
            }
        }
        $gonePieces = 0;
        foreach ($state['entries'] as $e) {
            if (!$e['folder'] || $e['name'] !== SIDECAR_FOLDER) {
                continue;
            }
            $inner = storage_folder_state($s, $e['id']);
            if ($inner === null) {
                $gonePieces++;
                continue;
            }
            $innerStray = storage_stray_sidecars($inner['entries'], $pictures);
            foreach ($innerStray as $id) {
                storage_file_delete($s, $id);
            }
            if (count($innerStray) === count($inner['entries']) && storage_folder_delete_empty($s, $e['id'])) {
                $gonePieces++;
            }
        }
        $stray = storage_stray_sidecars($state['entries']);
        foreach ($stray as $id) {
            storage_file_delete($s, $id);
        }
        if (count($stray) + $gonePieces < count($state['entries'])) {
            break;   // something real still lies here
        }
        if (!storage_folder_delete_empty($s, $at)) {
            break;
        }
        $gone++;
        $at = $state['parent'];
        if (!$realRoot) {
            $stop[] = pcloud_root_real_id($s['host'], $s['token']);
            $realRoot = true;
        }
    }
    return $gone;
}

/**
 * The folders no tidying ever deletes, by name: the backup's and the
 * calibration's. They hold what no find shows, and an empty one is the
 * place the next sweep writes into.
 *
 * @return string[]
 */
function storage_kept_folders(): array
{
    return [defined('BACKUP_FOLDER') ? BACKUP_FOLDER : 'Datensicherung', 'Kalibrierung'];
}

/**
 * pCloud's whole tree below a folder — ONE listfolder with recursive=1.
 *
 * @return ?array{id: string, name: string, files: list<array{id: string, name: string}>, folders: list<array>}
 */
function pcloud_tree(string $host, string $token, int $folderId): ?array
{
    try {
        $d = pcloud_call($host, 'listfolder', ['access_token' => $token, 'folderid' => $folderId, 'recursive' => 1]);
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), '(2005)')) {
            return null;
        }
        throw $e;
    }
    $node = static function (array $m) use (&$node): array {
        $out = ['id' => (string) ($m['folderid'] ?? ''), 'name' => (string) ($m['name'] ?? ''),
                'files' => [], 'folders' => []];
        foreach ((array) ($m['contents'] ?? []) as $e) {
            if (!empty($e['isfolder'])) {
                $out['folders'][] = $node((array) $e);
            } else {
                $out['files'][] = ['id' => (string) ($e['fileid'] ?? ''), 'name' => (string) ($e['name'] ?? '')];
            }
        }
        return $out;
    };
    return $node((array) ($d['metadata'] ?? []));
}

/**
 * The whole tree below a folder, as the app can see it — for the sweep.
 * Null at S3 (no folders) and when the folder is gone.
 *
 * @return ?array{id: string, name: string, files: list<array{id: string, name: string}>, folders: list<array>}
 */
function storage_tree(array $s, string|int $folderId): ?array
{
    return match ($s['provider']) {
        'gdrive'   => gdrive_tree($s['token'], (string) $folderId),
        'onedrive' => onedrive_tree($s['token'], (string) $folderId),
        's3'       => null,
        default    => pcloud_tree($s['host'], $s['token'], (int) $folderId),
    };
}

/**
 * THE SWEEP OF A WHOLE CLOUD. storage_prune_empty() tidies only the way a
 * picture just left; what lies on ways nobody walks again stays. This
 * walks the whole tree below the app's folder once, from the leaves up,
 * by the same rules: a sidecar without its picture goes — in a folder
 * itself and in its „Bearbeitung", where the patches and masks of a
 * picture that is gone go too (storage_stray_sidecars) —, a folder goes
 * when nothing is left in it, the app's folder itself and the kept ones
 * never, and at Google Drive no folder at all
 * (storage_folder_delete_empty says why).
 *
 * IT CAME TWICE. First on 04.10.2026 („das einmalige Aufräumen auch
 * bauen"): empty folders after deleted test pictures and the sidecars of
 * recordings deleted before the deleting tidied its own way. It did its
 * one job and went again the same day. On 22.11.2026 it came back, because
 * a whole stock had moved in between („Arten/" and „Listen/", 18.11.2026)
 * and the old tree stood on, held up by sidecars a race had left under old
 * names and by folders no picture had ever lain in: „Wenn noch was in
 * Fungi Pilze ist im Grundverzeichnis, dann ist das ein Fehler." A rule
 * that changes for the whole stock leaves ways nobody walks again — so it
 * stays this time, as a button with the administrator
 * (cloud_providers_content.php).
 *
 * WHAT IT NEVER TOUCHES: a file that is neither a sidecar nor a patch.
 * A picture no row knows — the stump of a broken upload, a file the user
 * laid there — is not ours to judge by its name, and the folder it lies
 * in stays with it.
 *
 * The deadline cuts it anywhere; everything done stays done, and the next
 * run lists the tree anew and finds only what is still left.
 *
 * @return array{sidecars: int, folders: int, done: bool}
 */
function storage_tidy(PDO $pdo, int $userId, array $s, float $deadline): array
{
    $count = ['sidecars' => 0, 'folders' => 0, 'done' => true];
    if ($s['provider'] === 's3') {
        return $count;
    }
    $tree = storage_tree($s, cloud_home_resolve($pdo, $userId, $s));
    if ($tree !== null) {
        storage_tidy_node($s, $tree, true, null, $deadline, $count);
    }
    return $count;
}

/**
 * One folder of the sweep, its subfolders first.
 *
 * @param ?list<string> $pictures for a „Bearbeitung" folder: the file names one storey up, which its sidecars pair with
 * @return bool whether it went
 */
function storage_tidy_node(array $s, array $node, bool $top, ?array $pictures, float $deadline, array &$count): bool
{
    $names = array_map(static fn(array $f): string => (string) $f['name'], $node['files']);
    $left = 0;
    foreach ($node['folders'] as $sub) {
        if (in_array($sub['name'], storage_kept_folders(), true)
            || !storage_tidy_node($s, $sub, false, $sub['name'] === SIDECAR_FOLDER ? $names : null, $deadline, $count)) {
            $left++;
        }
        if (!$count['done']) {
            return false;
        }
    }
    $entries = array_map(static fn(array $f): array => $f + ['folder' => false], $node['files']);
    $stray = storage_stray_sidecars($entries, $pictures);
    foreach ($stray as $id) {
        if (microtime(true) >= $deadline) {
            $count['done'] = false;
            return false;
        }
        storage_file_delete($s, $id);
        $count['sidecars']++;
    }
    if ($top || $left > 0 || count($stray) < count($entries)) {
        return false;
    }
    if (microtime(true) >= $deadline) {
        $count['done'] = false;
        return false;
    }
    if (!storage_folder_delete_empty($s, $node['id'])) {
        return false;
    }
    $count['folders']++;
    return true;
}

/**
 * WHERE THE BYTES OF A PICTURE ARE FETCHED — the original, or a preview
 * fitted into $edge. A list of candidates, tried in order by
 * cloud_deliver(): at S3 the preview the app laid down comes first and the
 * original after it, for a picture that has none yet.
 *
 * Each provider's own rendering: pCloud's thumblink, Drive's thumbnail
 * (with the token as a header), OneDrive's pre-authenticated thumbnail,
 * S3's address signed for five minutes. An empty list means there is no
 * way to it — gone, or no preview where one was asked for.
 *
 * @param ?string $home S3 only: the app's prefix, which the preview keys
 *                      hang off (s3_preview_key)
 * @return list<array{url: string, headers: string, follow?: bool}>
 */
function storage_file_fetch(array $s, string|int $fileId, ?int $edge = null, ?string $home = null): array
{
    $id = (string) $fileId;
    switch ($s['provider']) {
        /* DRIVE AND ONEDRIVE DRAW THEIR PREVIEWS THEMSELVES — a moment
           after the upload, not with it. Until then the picture itself
           stands behind the preview, as at S3: a tile that loads the whole
           picture once beats one that stays empty. */
        case 'gdrive':
            $original = gdrive_file_fetch($s['token'], $id);
            if ($edge === null) {
                return [$original];
            }
            $thumb = gdrive_thumb_fetch($s['token'], $id, $edge);
            return $thumb === null ? [$original] : [$thumb, $original];
        case 'onedrive':
            $original = onedrive_file_fetch($s['token'], $id);
            if ($edge === null || $original === null) {
                return $original === null ? [] : [$original];
            }
            $thumb = onedrive_thumb_fetch($s['token'], $id, $edge);
            return $thumb === null ? [$original] : [$thumb, $original];
        case 's3':
            /* THE STORE IS THE USER'S OWN, typed in by him — so its host is
               guarded like every request there (s3_host_guard), and no
               redirect is followed: an answer „302 → http://10.0.0.1/"
               would carry the fetch into the host's own network. */
            s3_host_guard($s['token']['endpoint']);
            $out = [];
            if ($edge !== null) {
                $out[] = ['url' => s3_presigned($s['token'], 'GET', s3_preview_key($home ?? '', $id, $edge)),
                          'headers' => '', 'follow' => false];
            }
            $out[] = ['url' => s3_presigned($s['token'], 'GET', $id), 'headers' => '', 'follow' => false];
            return $out;
        default:
            $problem = null;
            $link = $edge === null
                ? pcloud_file_link($s['host'], $s['token'], (int) $fileId)
                : pcloud_thumb_link($s['host'], $s['token'], (int) $fileId, $edge, $problem);
            return $link === null ? [] : [['url' => $link, 'headers' => '']];
    }
}

/**
 * The bytes of one file, fetched here — for the co-owner copies, which go
 * from cloud to cloud through this server.
 *
 * READ WITH A CEILING (security review, 23.09.2026): one byte more than
 * allowed is all it takes to know a file is too big, and reading it whole
 * first would be the out-of-memory nobody can catch.
 *
 * @throws RuntimeException gone, refused, or larger than $maxBytes
 */
function storage_file_bytes(array $s, string|int $fileId, int $maxBytes): string
{
    foreach (storage_file_fetch($s, $fileId) as $at) {
        $bytes = @file_get_contents($at['url'], false, stream_context_create(['http' => [
            'timeout'         => 120,
            'header'          => $at['headers'],
            'follow_location' => ($at['follow'] ?? true) ? 1 : 0,
            'ignore_errors'   => false,
        ]]), 0, $maxBytes + 1);
        if ($bytes === false || $bytes === '') {
            continue;
        }
        if (strlen($bytes) > $maxBytes) {
            throw new RuntimeException(t('Die Datei ist zu groß zum Kopieren (%s).', cloud_size(strlen($bytes)) . '+'));
        }
        return $bytes;
    }
    throw new RuntimeException(t('Die Datei ließ sich nicht aus der Cloud laden.'));
}

/**
 * The secret of a connection — ONLY for the code that knocks at the
 * provider's door with it.
 *
 * Its own function and not one more column in cloud_connections(): this way it
 * stands visibly at every call site that something sensitive is being
 * fetched here, and you find them all with one search.
 */
function cloud_secret(PDO $pdo, int $userId, string $provider): ?string
{
    if (!cloud_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT secret FROM map_cloud_accounts WHERE user_id = ? AND provider = ?'
    );
    $stmt->execute([$userId, $provider]);
    $value = $stmt->fetchColumn();

    if ($value === false || $value === null || $value === '') {
        return null;
    }

    // Decryption happens HERE and nowhere else — the one gate the secret has
    // to pass through in either direction.
    return cloud_decrypt((string) $value);
}

/**
 * Create or refresh a connection.
 *
 * ONE CONNECTION PER PROVIDER (uq_cloud_user_anbieter): whoever reconnects
 * replaces the old one instead of laying a second one beside it. Two
 * accounts of the same provider would be a prompt at every upload.
 */
function cloud_connect(PDO $pdo, int $userId, string $provider, array $data): void
{
    if (!cloud_provider_valid($provider)) {
        throw new InvalidArgumentException(t('Unbekannter Anbieter.'));
    }
    if (!cloud_ready($pdo)) {
        throw new RuntimeException(t('Die Cloud-Tabelle fehlt noch — Migration 0053 einspielen.'));
    }

    $hasOrt = column_exists($pdo, 'map_cloud_accounts', 'region');

    $columns = ['user_id', 'provider', 'account', 'folder', 'secret', 'expires_at'];
    $values  = [
        $userId,
        $provider,
        mb_substr(trim((string) ($data['account'] ?? '')), 0, 190) ?: null,
        cloud_folder_clean((string) ($data['folder'] ?? '')),
        // Encrypted on the way in, and only here. Whoever hands a secret to
        // this function never has to think about it — and cannot forget.
        (string) ($data['secret'] ?? '') !== ''
            ? cloud_encrypt((string) $data['secret'])
            : null,
        $data['expires_at'] ?? null,
    ];

    // Without the column the connection still works — until the first call
    // goes to the wrong data centre. Better a connection that has to guess
    // once than a tab that refuses in the window before the migration.
    if ($hasOrt) {
        $columns[] = 'region';
        $values[]   = mb_substr(trim((string) ($data['region'] ?? '')), 0, 64) ?: null;
    }

    $pdo->prepare(
        'INSERT INTO map_cloud_accounts
                (' . implode(', ', $columns) . ', checked_at, created_at, updated_at)
         VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ', NOW(), NOW(), NOW())
         ON DUPLICATE KEY UPDATE
                account = VALUES(account), folder = VALUES(folder),
                secret = VALUES(secret), expires_at = VALUES(expires_at),'
                . ($hasOrt ? ' region = VALUES(region),' : '') . '
                checked_at = NOW(), updated_at = NOW()'
    )->execute($values);

    /* A CLOUD JUST ARRIVED — WHAT IS OWED TO IT? (co-ownership, step 5,
       11.09.2026). „Wer keine eigene Cloud hat, bekommt auch keine
       Kopie" — and whoever connects one now gets what he missed: every
       find in his circle with somebody else's pictures is queued. Best
       effort, after the row is safe: a hiccup in the queue must never
       undo a connection that just succeeded. Every cloud that takes
       pictures since 26.09.2026, not pCloud alone. */
    if (in_array($provider, CLOUD_PICTURE_PROVIDERS, true)) {
        try {
            require_once __DIR__ . '/cloud_copies_content.php';
            cloud_copies_backfill_user($pdo, $userId);
        } catch (Throwable $e) {
            error_log('cloud_copies_backfill_user (' . $userId . '): ' . $e->getMessage());
        }
    }
}

/**
 * Disconnect. Deletes ONLY the connection, never anything at the provider —
 * what lies there belongs to the user and stays put. The confirmation prompt
 * in the interface has to say that too, otherwise "Trennen" sounds like
 * "files gone".
 */
function cloud_disconnect(PDO $pdo, int $userId, string $provider): bool
{
    if (!cloud_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM map_cloud_accounts WHERE user_id = ? AND provider = ?');
    $stmt->execute([$userId, $provider]);

    /* AND OUR NOTE OF WHAT WAS BACKED UP THERE — which is a note and nothing
       else: not one file up there is touched by this, see the sentence
       above. It goes because it belongs to THIS connection. Whoever comes
       back with a different pCloud account would otherwise be told by our
       table that ten years are already secured, while over there the folder
       is empty. (The folder listing would notice that too — backup_due()
       asks both sides. This just keeps the bookkeeping from claiming
       something nobody can check.)

       FOR EVERY STORAGE PROVIDER — first pCloud alone, since 27.08.2026
       Google Drive beside it. The table still names no provider, and it
       may: one account holds ONE storage (cloud_storage explains the
       rule), so whatever bookkeeping lies here belongs to the connection
       being cut right now. */
    if (in_array($provider, ['pcloud', 'gdrive', 'onedrive', 's3'], true)
        && table_exists($pdo, 'map_cloud_backups')) {
        $pdo->prepare('DELETE FROM map_cloud_backups WHERE user_id = ?')->execute([$userId]);
    }

    return $stmt->rowCount() > 0;
}

/**
 * Bring the target folder into a shape that holds up with every provider.
 *
 * WITHOUT LEADING AND TRAILING SLASH, and without the characters file
 * systems take offense at. The user types "Pilze/Belege" or
 * "/Pilze/Belege/" here — both are supposed to mean the same. The levels
 * below it the application builds itself after the scheme of the voucher
 * photos (municipality_folder in municipality.php): country/region/municipality.
 */
/**
 * Clean ONE segment — a single folder name, not a path.
 *
 * THE DIFFERENCE MATTERS AND HAS ALREADY BITTEN. cloud_folder_clean() treats
 * the slash as a separator, which is right for a target folder the user types
 * as "Pilze/Belege". Fed a single name it does the opposite of cleaning: a
 * species written "cf/aeruginea" turned into two levels, and the find landed
 * one folder deeper than every other one. Caught by the test, not in the wild.
 *
 * So here the slash is not a separator but a character that has to go.
 */
function cloud_segment_clean(string $raw): string
{
    $piece = str_replace(['/', '\\'], ' ', $raw);
    $piece = preg_replace('/[\x00-\x1F<>:"|?*]+/u', '', $piece) ?? $piece;
    // Collapse the space the slash left behind, otherwise "cf / aeruginea"
    // keeps a gap nobody typed.
    $piece = preg_replace('/\s{2,}/u', ' ', $piece) ?? $piece;
    $piece = trim($piece, " \t.");
    // One spelling leaves this server (cloud_name_key): a name pasted from a Mac's file list may carry the other.
    $piece = cloud_name_key($piece);

    return ($piece === '.' || $piece === '..') ? '' : mb_substr($piece, 0, 255);
}

function cloud_folder_clean(string $raw): string
{
    $parts = [];
    foreach (explode('/', str_replace('\\', '/', $raw)) as $piece) {
        // The same cleaning as for the storage folders of the voucher
        // photos — two rules for the same purpose drifted apart.
        $piece = preg_replace('/[\x00-\x1F<>:"|?*]+/u', '', $piece) ?? $piece;
        $piece = trim($piece, " \t.");
        // ".." could otherwise lead out of the chosen folder.
        if ($piece !== '' && $piece !== '.' && $piece !== '..') {
            $parts[] = $piece;
        }
    }
    return mb_substr(implode('/', $parts), 0, 255);
}

/* cloud_levels() stood here until 28.08.2026 — the filing scheme as a
   display table for the tab, removed with its section on the maintainer's
   word. Its two load-bearing decisions moved to cloud_path_parts(),
   where the scheme is actually built; its "this file builds no path"
   paragraph had been wrong for a week by then. */

/**
 * How many genera under one letter before a second one is added.
 *
 * A SETTING AND NOT A CONSTANT, because the rule is a matter of feel and the
 * feel is the maintainer's. He has done it by hand until now: when many genera
 * gather under a letter, a second one joins. Twelve is the starting value,
 * not a finding.
 */
function cloud_letter_threshold(): int
{
    $n = (int) (cloud_vault()['letter_threshold'] ?? 12);

    // Below two the rule would mean "always two letters", above a hundred it
    // would never fire. Both are legitimate wishes — but then one says so
    // through the other option, not through a number nobody reads again.
    return max(2, min(100, $n));
}

/**
 * The folder path for one find, in three pieces.
 *
 * THE SCHEME IS TAXONOMIC, NOT GEOGRAPHIC — kingdom, group, division,
 * letter, genus, species, find day:
 *
 *   Fungi (Pilze)/Fungi/Basidiomycota/A/Am/Amanita/A. muscaria/
 *       2026-08-14 Goldsteintal/
 *
 * What stood first was country/region/municipality as with the voucher
 * photos — guessed wrong. The maintainer's grown archive orders by what one
 * SEARCHES for: where a find lay is on the point and filterable; what it
 * was is the question with which one steps up to the drive.
 *
 * IT IS THE SCHEME THAT IS COPIED, NOT THE PLACE. This map files into its
 * OWN directory and never touches the copy of the archive that already
 * lies on the cloud storage — the maintainer said so expressly. What the
 * levels buy is recognition: whoever later moves pictures over by hand
 * finds the same order they know, folder for folder.
 *
 * WHY IN PIECES AND NOT AS ONE LIST. Everything above the letter is fixed —
 * kingdom, group, and with fungi the division. Everything below it is fixed
 * too. Only the letter itself has a shape that depends on what is already
 * lying in the cloud, and that is the one place the caller has to stop and
 * count. Handing back one flat list would force it to FIND the letter again
 * — and searching for a one-character segment goes wrong the day a group is
 * called "A".
 *
 * THE COUNT COMES FROM OUTSIDE. Whether a letter carries a second one depends
 * on how many genera ALREADY HAVE A FOLDER there — not on how many the
 * reference list knows. Measured on the maintainer's finds: 20 genera all told,
 * the fattest letter carries four; the DGfM list meanwhile knows 3972.
 * Counting the wrong one would have produced a second level everywhere on
 * the first day.
 *
 * THE DIVISION ONLY APPLIES TO FUNGI. With lichens and plants the alphabet
 * starts right after the group folder — that is how the archive has grown,
 * and guessing otherwise once already put a level in here that nobody has.
 *
 * @param array{kingdom:string, group:string, division:?string, genus:string,
 *              species:string, found_at:?string, municipality:?string} $find
 * @return array{above: string[], letter: string, below: string[]}
 *         empty 'letter' means: no genus, no path
 */
function cloud_path_parts(array $find): array
{
    $empty = ['above' => [], 'letter' => '', 'below' => []];

    $genus = cloud_segment_clean(trim((string) ($find['genus'] ?? '')));
    if ($genus === '') {
        return $empty;
    }

    // Every kingdom under „Arten", beside „Listen" (CLOUD_SPECIES_FOLDER).
    $top = [CLOUD_SPECIES_FOLDER];
    foreach (['kingdom', 'group'] as $level) {
        $value = cloud_segment_clean(trim((string) ($find[$level] ?? '')));
        if ($value !== '') {
            $top[] = $value;
        }
    }

    // Only the fungi carry it, and only when it is known.
    $division = cloud_segment_clean(trim((string) ($find['division'] ?? '')));
    if ($division !== '') {
        $top[] = $division;
    }

    $bottom = [$genus];

    // "A. muscaria" — genus abbreviated, then the epithet.
    //
    // WITHOUT AN EPITHET IT IS "Scutellinia spec.", written out and with the
    // note. The wording: inside the genus folder Scutellinia there
    // is a subfolder Scutellinia spec., and the date folders sit in that.
    //
    // The genus name alone stood here before, and that read wrong twice
    // over: Scutellinia/Scutellinia looks like a slip, and it hides what it
    // actually says — that the species did not come out. Beside
    // "S. flammea" the open one now stands as what it is, and it sorts to
    // the same place.
    $epithet = cloud_segment_clean(trim((string) ($find['species'] ?? '')));
    $bottom[] = $epithet === ''
        ? $genus . ' spec.'
        : mb_substr($genus, 0, 1) . '. ' . $epithet;

    // The find day, and behind it the municipality when there is one. Date
    // first, because that is what sorts.
    //
    // AND THE FIND NUMBER LAST (04.10.2026: „nur den Ordnern an
    // sich hinten dran die Fundnummer stellen … die Dateinamen an sich
    // würde ich dann ohne Fundnummer belassen"): EVERY FIND ITS OWN FOLDER.
    // Before, two finds of one species on one day in one municipality
    // shared „2026-08-29 Krün", and their pictures — named alike since the
    // names are derived (cloud_names.php) — would have met there.
    $day = substr((string) ($find['found_at'] ?? ''), 0, 10);
    if ($day !== '') {
        $bottom[] = cloud_leaf_folder($day, (string) ($find['municipality'] ?? ''), (string) ($find['number'] ?? ''));
    }

    return [
        'above'      => $top,
        'letter' => mb_strtoupper(mb_substr($genus, 0, 1)),
        'below'     => array_values(array_filter($bottom, static fn(string $t): bool => $t !== '')),
    ];
}

/**
 * The same path as one list — for showing, and for the tests.
 *
 * $secondLetter says whether the letter already carries a two-letter
 * compartment. Only the display needs this; when really filing,
 * storage_find_folder() decides it by counting.
 *
 * @return string[]
 */
function cloud_path_for(array $find, bool $secondLetter = false): array
{
    $t = cloud_path_parts($find);
    if ($t['letter'] === '') {
        return [];
    }

    $genus = $t['below'][0] ?? '';
    $subject = ($secondLetter && mb_strlen($genus) >= 2)
        // "Am", not "AM": the second letter stays lowercase, the way one
        // writes it by hand.
        ? [$t['letter'] . mb_strtolower(mb_substr($genus, 1, 1))]
        : [];

    return array_merge($t['above'], [$t['letter']], $subject, $t['below']);
}

