<?php
/**
 * Datensicherung — the finds as files in the user's own storage.
 *
 * WHAT FOR (27.08.2026). The finds live in one database on one
 * shared host. A copy of it exists as the database dump, but that is
 * something one fetches by hand and then has to keep somewhere — and
 * whatever has to be remembered every week is not a backup. Everyone here
 * already brings their own storage for the voucher photos; the finds
 * themselves can lie beside them, and then the data exists twice in two
 * places without anybody doing anything.
 *
 * THE MONTHS ARE THE UNIT, and they are not invented here. The application
 * already cuts the stock into month buckets for the offline devices
 * (bucket.php), each carrying a fingerprint over count, last change and a
 * checksum. "Did anything change in this month?" is exactly the question a
 * nightly upload has to ask, and asking it a second way would mean two
 * rules that drift apart — the very duplication bucket.php spends its
 * header warning about. So one month is one file, and a month that has not
 * changed is not touched.
 *
 * That is what makes this cheap enough to run every night: a stock of ten
 * years is a hundred and twenty files, of which exactly one — the current
 * month — normally changes. The past does not get uploaded again.
 *
 * TWO SOURCES DECIDE, NOT ONE. The fingerprint says what the database
 * looked like at the last upload; only the provider knows whether the file
 * is still lying over there. Whoever empties the folder in their storage
 * would otherwise never get anything back, because our side would go on
 * saying "already done". So the sweep reads the folder listing too, and a
 * missing file counts exactly like a changed month.
 *
 * WHAT GOES IN IS THE OWN STOCK, and nothing else. Finds that arrive
 * through containers and excursions belong to whoever made them and are
 * backed up in THEIR storage; a copy here would put one person's finds into
 * another person's cloud. It falls out by itself — the buckets only ever
 * cover map_points of one user.
 *
 * AND IT IS A MIRROR, NOT AN ARCHIVE. A find that goes into the trash falls
 * out of its month file at the next sweep, because the buckets carry the
 * living stock. That is the honest behaviour for something called a copy of
 * the database — but it means the folder is no protection against deleting
 * something by mistake. The trash is, and it stands in the application.
 * Liesmich.txt says so up there, where somebody would look.
 *
 * THE FORMAT IS OUR OWN CSV — the widest of the four writers (export.php):
 * every find field gets a column of its own, plus lists, tour, map sheet,
 * municipality, protected area, endangerment. It opens in a spreadsheet and
 * it comes back in through Verwaltung → Import without a mapping step. What
 * it cannot carry is what no line-per-point format can: the outline of an
 * area and the photos. The photos are already up there as pictures; the
 * outline is named as missing in Liesmich.txt rather than silently dropped.
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/export.php';

/**
 * The folder, at the root of the storage the app may see.
 *
 * IN THE ROOT AND NOT UNDER THE TARGET FOLDER (27.08.2026:
 * "ganz im Root"). The target folder carries the taxonomic filing of the
 * pictures — kingdom, group, division, letter, genus. A backup is not a
 * find and belongs in none of those levels; hung underneath them it would
 * read like one more taxon.
 *
 * INSIDE THE APP'S OWN FOLDER, and therefore always this name
 * (23.09.2026). pCloud and OneDrive give the app a folder of its own;
 * Google Drive and S3 do not, and there the app makes one, „YASAIMAPP"
 * (cloud_home_resolve in cloud.php). In the root of a Drive a bare
 * „Datensicherung" could have met the user's own — inside the app's
 * folder it meets nothing. For one afternoon this folder carried a name
 * of the user's own instead (0119); the app's folder is the right one to
 * name (0120).
 */
const BACKUP_FOLDER = 'Datensicherung';

/** The note that tells whoever finds the folder in three years what it is. */
const BACKUP_README = 'Liesmich.txt';

/**
 * How many months one sweep writes for one user at most.
 *
 * IT IS A BELT TO THE DEADLINE'S BRACES. The time budget already cuts every
 * run short; this bound exists for the case the budget does not bite —
 * a fast connection and a first seeding of ten years would otherwise send a
 * hundred and twenty files at a provider in one go. Two years a night is
 * plenty: after a week even a long-standing stock is complete, and from
 * then on it is one file a night.
 */
const BACKUP_PER_RUN = 24;

/** Table, buckets and cloud — all three have to be there. */
function backup_ready(PDO $pdo): bool
{
    // backup_at and not backup_enabled as the 0085 witness: the switch
    // column dies with migration 0087, the timestamp stays — a guard on
    // the dropped column would silently turn every backup off the moment
    // 0087 is applied.
    return cloud_ready($pdo)
        && bucket_ready($pdo)
        && table_exists($pdo, 'map_cloud_backups')
        && column_exists($pdo, 'map_cloud_accounts', 'backup_at');
}

/**
 * Where the backup lies, as the user sees it — for the texts of the tab:
 * „Datensicherung" in the provider's app folder, „YASAIMAPP/Datensicherung"
 * where the app makes its own (Drive, S3). Read from our tables, no
 * provider asked.
 */
function backup_folder_path(PDO $pdo, int $userId): string
{
    $provider = cloud_ready($pdo) ? array_key_first(cloud_connections($pdo, $userId)) : null;
    return ($provider !== null && cloud_has_own_home((string) $provider))
        ? cloud_home_name($pdo, $userId) . '/' . BACKUP_FOLDER
        : BACKUP_FOLDER;
}

/**
 * The guard in front of every action — throws with the reason.
 *
 * ITS OWN FUNCTION because the two branches in the tab would otherwise
 * carry the same three conditions twice, and the day a fourth one comes
 * along only one of them would get it. The wording names the migration:
 * the code goes live before it, and then the tab has to say WHY nothing
 * works instead of failing on a missing column.
 */
function backup_check(PDO $pdo, int $userId): void
{
    if (!cloud_ready($pdo)) {
        throw new RuntimeException(t('Die Tabelle für die Zugänge fehlt noch — Migration 0053 einspielen.'));
    }
    if (!backup_ready($pdo)) {
        throw new RuntimeException(t('Die Datensicherung braucht Migration 0085 — die ist noch nicht eingespielt.'));
    }
    if (cloud_connections($pdo, $userId) === []) {
        throw new RuntimeException(t('Dafür muss erst ein Speicher verbunden sein.'));
    }
}

/**
 * The file one month goes into.
 *
 * THE MONTH LEADS so the folder sorts itself; the word in front of it is
 * German because this name is read by a person in their own storage, not by
 * any code of ours. It is at the same time the key by which the sweep
 * recognizes its own files in the folder listing — whoever changes it makes
 * every existing file an orphan and writes the whole stock anew, once.
 */
function backup_filename(string $bucketKey): string
{
    return 'Funde ' . $bucketKey . '.csv';
}

/**
 * What the folder says about itself.
 *
 * WRITTEN OUT AND NOT LEFT AWAY. A folder full of CSVs in somebody's cloud
 * says nothing about where it came from, what it holds, or how to get the
 * finds back out of it — and the moment it matters is exactly the moment
 * this application is not available to ask. The gaps are named too: a
 * backup that quietly leaves something out is worse than one that says what
 * it leaves out.
 *
 * AND IT NAMES NO PROVIDER (27.08.2026: „Die Liesmich nicht zu
 * speziell. Wir wollen ja noch andere Cloud Services anbinden."). This text
 * lies in the user's storage and stays there for years — longer than the
 * assumption that there is only pCloud. Whatever holds for every provider
 * is said in general terms; the code around it may of course speak pCloud,
 * that is where the provider actually is.
 *
 * THE PARAGRAPHS GO THROUGH t() ONE BY ONE and are hard-wrapped here, not
 * in the catalog: a translation has other line lengths, and a key with
 * line breaks in it would never be found again after a rewrap. The title
 * keeps the FOLDER's name untranslated — the folder up there is called
 * that in every language, it is a stored value. The example file name
 * „Funde 2026-08.csv" is one too, so a translation keeps it verbatim.
 * The sweep compares this text by size (backup_run), so the one-time
 * rewrap after this change rewrites the note once and heals itself.
 */
function backup_readme(): string
{
    // wordwrap counts bytes, not characters — with umlauts a line comes
    // out a touch short of 72, never broken mid-word.
    $wrap = static fn(string $text): string => wordwrap($text, 72, "\r\n", false);

    $title = BACKUP_FOLDER . ' — YASAIMAPP';

    $blocks = [
        $title . "\r\n" . str_repeat('=', mb_strlen($title)),
        $wrap(t('In diesem Ordner liegen deine Fundpunkte als Tabellen, eine Datei je Monat („Funde 2026-08.csv"). Sie werden automatisch geschrieben, immer dann, wenn sich in einem Monat etwas geändert hat. Ein Monat, in dem nichts passiert ist, wird nicht noch einmal hochgeladen.')),
        t('WIEDER EINLESEN') . "\r\n"
            . $wrap(t('Die Dateien gehen in der Karte unter Verwaltung → Import wieder hinein, ohne Umweg: die Spaltennamen sind dieselben, die der Import erwartet. Genauso öffnen sie sich in jedem Tabellenprogramm (Semikolon als Trennzeichen, UTF-8).')),
        t('DAS MTB IN DER VORSCHAU') . "\r\n"
            . $wrap(t('Manche Tabellenanzeiger — auch die Schnellansicht mancher Cloud — halten „5815/431" für eine Rechnung: sie teilen 5815 durch 431, bekommen 13,4919 und zeigen davon einen gekürzten Bruch, nämlich „27/2". Andere Blätter sehen dann zwei- oder dreistellig aus. In der Datei steht der richtige Wert — nur die Anzeige rechnet. Wer sichergehen will, öffnet sie in einem Tabellenprogramm oder sieht sie als reinen Text an.')),
        $wrap(t('Die drei Ziffern hinter dem Schrägstrich sind Viertel, Sechzehntel und Vierundsechzigstel des Blattes, jeweils 1=NW 2=NO 3=SW 4=SO.')),
        t('WAS DRIN STEHT') . "\r\n"
            . $wrap(t('Deine eigenen Funde. Was dir über Freigaben oder Exkursionen nur angezeigt wird, gehört den anderen und wird in DEREN Cloud gesichert.')),
        t('WAS NICHT DRIN STEHT') . "\r\n"
            . $wrap(t('Der Umriss einer Fläche — eine Zeile je Punkt kann nur einen Ort tragen, dann steht dort die Mitte. Und die Fotos: die liegen als Bilder in den Artordnern nebenan.')),
        t('KEIN SCHUTZ VOR VERSEHENTLICHEM LÖSCHEN') . "\r\n"
            . $wrap(t('Diese Dateien spiegeln den Stand der Karte. Wer einen Fund löscht, findet ihn beim nächsten Durchgang auch hier nicht mehr. Dafür ist der Papierkorb in der Karte da, nicht dieser Ordner.')),
        $wrap(t('Der Ordner darf gelöscht werden. Er entsteht dann von selbst neu, und alle Monate werden einmal frisch geschrieben.')),
    ];

    return implode("\r\n\r\n", $blocks) . "\r\n";
}

/**
 * Which months need writing — the whole decision, and no network in it.
 *
 * A MONTH IS DONE WHEN BOTH SIDES SAY SO: our table has to hold the current
 * fingerprint for it, AND the file has to be lying in the folder. Either
 * one alone is a half truth —
 *
 *   only the fingerprint  →  somebody empties the folder in their storage
 *                            and never gets anything back, because our side
 *                            goes on saying "already done".
 *   only the listing      →  a file called "Funde 2026-08.csv" is there, so
 *                            the month counts as secured no matter how much
 *                            has changed in it since.
 *
 * THE FILE IS LOOKED FOR UNDER THE NAME WE WROTE IT AS, not under the name
 * we would give it today. Those are the same thing right now, and the day
 * backup_filename() changes they are not — then every month is written once
 * more under the new name, which is exactly right, and no month is silently
 * held against a file that was never called that.
 *
 * @param array<string,string> $finger bucket key => fingerprint now
 * @param array<string,array>  $known  what our table says went up
 * @param array<string,array>  $there  what the provider's folder holds
 * @return string[] bucket keys, in the order they came in
 */
function backup_due(array $finger, array $known, array $there): array
{
    $due = [];
    foreach ($finger as $key => $print) {
        $row = $known[(string) $key] ?? null;
        $done = $row !== null
            && (string) $row['fingerprint'] === (string) $print
            && isset($there[(string) $row['filename']]);
        if (!$done) {
            $due[] = (string) $key;
        }
    }
    return $due;
}

/**
 * What we believe is lying up there, per month.
 *
 * @return array<string, array{fingerprint:string, filename:string, total:int,
 *                             uploaded_at:string}>
 */
function backup_state(PDO $pdo, int $userId): array
{
    if (!backup_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT bucket_key, fingerprint, filename, total, uploaded_at
           FROM map_cloud_backups WHERE user_id = ?'
    );
    $stmt->execute([$userId]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(string) $row['bucket_key']] = $row;
    }
    return $out;
}

/**
 * Is there a storage to back up into?
 *
 * THAT IS THE WHOLE QUESTION since 28.08.2026 („habe ich eine
 * Cloud, werden meine Daten dort auch gesichert. Ungefragt."). A switch
 * stood beside it for one day — 0085 argued it in, 0087 argues it out:
 * a connected storage IS the consent, and an off-switch on a backup is
 * an invitation to regret it the one time it would have mattered.
 *
 * PROVIDER-BLIND since Google Drive came along (27.08.2026): one account
 * holds one storage (cloud_storage), so "the row with a secret" IS the
 * storage, whichever provider it belongs to.
 */
function backup_wanted(PDO $pdo, int $userId): bool
{
    if (!backup_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM map_cloud_accounts
          WHERE user_id = ? AND secret IS NOT NULL LIMIT 1'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchColumn() !== false;
}

/**
 * The accounts a sweep has to visit — longest untouched first.
 *
 * THE ORDER IS THE FAIRNESS. One user with ten years of finds fills a whole
 * run on their first night; ordering by the last sweep puts everybody else
 * in front of them the next night, and the big stock comes back around when
 * it is once more the one that has waited longest. A fixed order would let
 * the same account eat every run while the last one never gets a turn.
 *
 * @return int[] user ids
 */
function backup_users(PDO $pdo): array
{
    if (!backup_ready($pdo)) {
        return [];
    }
    /* GROUPED, NOT FILTERED BY PROVIDER (27.08.2026): one account holds
       one storage, so the row with a secret is it — but an account from
       before the one-storage rule could still hold two rows, and without
       the GROUP BY it would be visited twice a night. MAX(backup_at):
       never swept sorts first, NULL being the longest wait of all. */
    $stmt = $pdo->query(
        'SELECT user_id FROM map_cloud_accounts
          WHERE secret IS NOT NULL
          GROUP BY user_id
          ORDER BY MAX(backup_at) IS NOT NULL, MAX(backup_at), user_id'
    );

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * The points of one month, ready for the writers.
 *
 * SORTED BY FIND TIME, not by the order the database hands them over
 * (points_load: updated_at DESC). A file a person opens should read like a
 * month — and the order has to be STABLE, otherwise editing a single find
 * would shuffle every line and the same content would look like a different
 * file every night.
 *
 * @param array<int,string> $listNames  list id => name, gathered once per run
 */
function backup_points(PDO $pdo, int $userId, string $bucketKey, array $listNames): array
{
    $points = points_load($pdo, $userId, ['buckets' => [$bucketKey]]);

    foreach ($points as &$p) {
        $p['list_names'] = array_values(array_filter(array_map(
            static fn($id): ?string => $listNames[(int) $id] ?? null,
            (array) ($p['lists'] ?? []))));
    }
    unset($p);

    /* THE SAME FALLBACK THAT PUT THE POINT IN THIS FILE — found_at, and
       created_at where there is none (bucket_key_of). Sorting by found_at
       alone would drop every point without a find time to the top in one
       silent heap, although the month it stands in was decided by its
       creation day. The empty string is caught as well as null: that is how
       bucket_key_of does it, and the two have to answer alike. */
    $when = static function (array $p): string {
        $time = $p['found_at'] ?? null;
        return (string) ($time === null || $time === '' ? ($p['created_at'] ?? '') : $time);
    };

    // The id decides ties, so the order is the same on every run — otherwise
    // the same content would look like a changed file every night.
    usort($points, static function (array $a, array $b) use ($when): int {
        return [$when($a), (int) $a['id']] <=> [$when($b), (int) $b['id']];
    });

    return $points;
}

/**
 * One sweep for one account.
 *
 * THE FINGERPRINT IS READ BEFORE THE POINTS, for the same reason
 * bucket_points() states at length: if somebody writes in between, we store
 * newer points under an older print and the month simply comes again next
 * time. The other order would file older points under a newer print, and
 * the backup would consider itself current for ever.
 *
 * NOTHING IS DELETED UP THERE. A month whose finds are all gone keeps its
 * file — the folder belongs to the user, and this code has no business
 * removing anything from it. The rule for the connection says the
 * same thing ("Deine Dateien dort bleiben unberührt"), and it applies twice
 * over to something that calls itself a backup.
 *
 * @param float $deadline microtime after which no new upload is begun
 * @return array{written:int, bytes:int, open:int, error:?string}
 */
function backup_run(PDO $pdo, int $userId, float $deadline): array
{
    $result = ['written' => 0, 'bytes' => 0, 'open' => 0, 'error' => null];

    if (!backup_wanted($pdo, $userId)) {
        return $result;
    }

    /* WHICHEVER STORAGE IS CONNECTED — the seam in cloud.php answers, and
       everything below speaks storage_*, never a provider's name. That is
       the whole change Google Drive asked of this file. */
    $s = cloud_storage($pdo, $userId);
    if ($s === null) {
        return $result;
    }

    // The fingerprints FIRST — see above.
    $status = bucket_status($pdo, $userId);
    $known  = backup_state($pdo, $userId);

    try {
        // Into the app's own folder — the provider's, or the one the app
        // makes where there is none (cloud_home_resolve).
        $folder = storage_folder_ensure($s, cloud_home_resolve($pdo, $userId, $s), BACKUP_FOLDER);
        $folderId = $folder['folderid'];
        if ($folderId === 0 || $folderId === '') {
            throw new RuntimeException(t('Der Speicher nennt für „%s" keine Ordnerkennung.', BACKUP_FOLDER));
        }
        $there = storage_folder_files($s, $folderId);
    } catch (RuntimeException $e) {
        $result['error'] = $e->getMessage();
        return $result;
    }

    $listNames = [];
    foreach (lists_load($pdo, $userId) as $l) {
        $listNames[(int) $l['id']] = (string) $l['name'];
    }

    $write = $pdo->prepare(
        'INSERT INTO map_cloud_backups
                (user_id, bucket_key, fingerprint, total, filename, fileid, bytes, uploaded_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
                fingerprint = VALUES(fingerprint), total = VALUES(total),
                filename = VALUES(filename), fileid = VALUES(fileid),
                bytes = VALUES(bytes), uploaded_at = NOW()'
    );

    // NEWEST FIRST — bucket_status already sorts that way, and backup_due()
    // keeps the order. When a run is cut short, what got through is the part
    // somebody would miss first; the months from before the last ice age can
    // wait a night.
    foreach (backup_due($status['finger'], $known, $there) as $key) {
        $name   = backup_filename($key);
        $finger = $status['finger'][$key];

        if ($result['written'] >= BACKUP_PER_RUN || microtime(true) >= $deadline) {
            $result['open']++;
            continue;
        }

        try {
            $points = backup_points($pdo, $userId, $key, $listNames);
            $csv    = export_csv($points);

            $up = storage_upload_bytes($s, $folderId, $csv, $name, null, true);
        } catch (RuntimeException $e) {
            // The month stays open and comes again next time; one unwilling
            // month must not stop the other eleven.
            error_log('backup_run (' . $userId . ' ' . $key . '): ' . $e->getMessage());
            $result['error'] ??= $e->getMessage();
            $result['open']++;
            continue;
        }

        $write->execute([
            $userId, $key, $finger, $status['counts'][$key] ?? count($points),
            // As a string always: pCloud numbers, Drive names — the column
            // takes both since migration 0086 and nothing computes with it.
            $up['name'], (string) $up['fileid'], $up['size'],
        ]);

        $result['written']++;
        $result['bytes'] += $up['size'];
    }

    /* The note, last and only when it is missing or out of date — and
       self-healing like everything else here: whoever deletes it gets it
       back on the next sweep. LAST, because a folder holding nothing but an
       explanation would be a lie for as long as the first upload takes; so
       it needs something to explain, either written just now or already
       lying there.

       BY THE SIZE, and that is not laziness. The note gets new sentences
       whenever somebody stumbles over something — the first addition was
       the fraction trap of the provider's own preview
       (27.08.2026) — and a "only when missing" rule would leave every
       already-uploaded folder on the first version for ever. The size is
       what the listing already carries; the only change it misses is one
       that keeps the byte count exactly, and for a text where sentences
       come and go that does not happen twice. */
    $note = backup_readme();
    $stale = ($there[BACKUP_README]['size'] ?? -1) !== strlen($note);
    if ($stale && ($result['written'] > 0 || $there !== [])) {
        try {
            storage_upload_bytes($s, $folderId, $note, BACKUP_README, null, true);
        } catch (RuntimeException $e) {
            // Not worth a failure: the finds are up, the explanation is not.
            error_log('backup_run readme (' . $userId . '): ' . $e->getMessage());
        }
    }

    $pdo->prepare(
        // On every row of the account — should a second one linger from
        // before the one-storage rule, the fairness order still advances.
        'UPDATE map_cloud_accounts SET backup_at = NOW() WHERE user_id = ?'
    )->execute([$userId]);

    return $result;
}

/**
 * What the tab shows: how much is secured, how much is waiting, since when.
 *
 * READ FROM OUR OWN TABLES, without asking the provider. A page load must
 * not hang on a foreign server — that is the same reason "Verbindung
 * prüfen" is a button and not something that happens by itself.
 *
 * @return array{months:int, secured:int, points:int, at:?string, enabled:bool}
 */
function backup_summary(PDO $pdo, int $userId): array
{
    $empty = ['months' => 0, 'secured' => 0, 'points' => 0, 'at' => null];
    if (!backup_ready($pdo)) {
        return $empty;
    }

    // The row with a secret is the storage — see backup_wanted().
    $stmt = $pdo->prepare(
        'SELECT backup_at FROM map_cloud_accounts
          WHERE user_id = ? AND secret IS NOT NULL LIMIT 1'
    );
    $stmt->execute([$userId]);
    /* ITS OWN NAME, NOT $row — the loop below reuses $row for the backup
       rows, and exactly that clobbering is why the tab showed „noch nie"
       after every run and the day-old switch looked unsaved: enabled and
       backup_at were read off whatever backup row came last. The maintainer
       reported the unsaved switch on 28.08.2026; this line was the bug. */
    $lastSweep = $stmt->fetchColumn();
    if ($lastSweep === false) {
        return $empty;
    }

    $status = bucket_status($pdo, $userId);
    $known  = backup_state($pdo, $userId);

    /* THE SAME RULE AS THE SWEEP, through the same function — a second
       comparison written out here would be the pair that drifts apart, and
       the drift would show as a tab claiming everything is secured while
       the sweep uploads every night.

       WITHOUT THE FOLDER LISTING, though, and deliberately: a page load must
       not hang on a foreign server. So every file we recorded stands in as
       present. The tab may therefore say "secured" about a file somebody has
       deleted over there — the sweep finds that out, with the real listing in
       hand, and writes it again. */
    $there = [];
    foreach ($known as $row) {
        $there[(string) $row['filename']] = true;
    }
    $open = backup_due($status['finger'], $known, $there);

    $secured = count($status['finger']) - count($open);
    $points  = 0;
    foreach ($status['counts'] as $key => $count) {
        if (!in_array((string) $key, $open, true)) {
            $points += (int) $count;
        }
    }

    return [
        'months'  => count($status['finger']),
        'secured' => $secured,
        'points'  => $points,
        'at'      => $lastSweep ?: null,
    ];
}
