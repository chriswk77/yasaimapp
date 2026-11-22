<?php
/**
 * The cron jobs — every job as a function, the three cadences as bundles.
 *
 * FIVE FILES AND NO MORE (01.10.2026: „Ich dachte, wir hätten
 * nur noch diese Dateien. Wäre natürlich übersichtlicher"): _frame.php,
 * this file, and one file per cadence — every_5_minutes.php, hourly.php,
 * daily.php —, each of them one cron entry at manitu, which allows five
 * and calls a script by its path with nothing after it. Until that day
 * every job also had a script of its own and run.php took lists by URL;
 * the cron needed none of them, and every job but the rain has its button
 * in the administration, which runs the very same *_run() loop.
 *
 * A NEW JOB: a cron_job_<name>() here, an entry in cron_jobs(), and a
 * place in the bundle of its cadence (CRON_GROUPS). Only a cadence that
 * does not exist yet becomes a new file — count the slots first; the
 * cadence is the call.
 *
 * A job hands back what cron_text() (_frame.php) turns into its line:
 *   ['parts' => string[], 'open' => int]  — quiet on the CLI when empty
 *   ['line'  => string]                   — said as it is
 */

declare(strict_types=1);

require_once __DIR__ . '/_frame.php';

/**
 * The jobs by name: their label in the output, their lock (one per job —
 * two different jobs may run side by side, the same one may not; the
 * names are the ones the jobs' old scripts took, and the open Cloud tab
 * takes 'cloud_moves' too) and their body.
 *
 * WHAT EACH ONE COSTS AND WANTS, from the heads of the scripts they had
 * until 01.10.2026:
 *
 * species — no external service and no deadline: everything is read from
 *   the own database and written back, a few thousand points in seconds.
 *   It always goes over the whole stock, so the derived table and the
 *   title references stay in step even when a list was moved or a field
 *   re-marked without a point being touched. Reported is what changed.
 * backup — the finds into everyone's own storage. ONCE A NIGHT is the
 *   intended cadence (27.08.2026): more often costs two
 *   questions per account and buys nothing — a month is written when it
 *   changed. The accounts are visited longest-untouched first
 *   (backup_users), so one big stock fills one night and the next night
 *   everybody else stands in front of it. Mein Bereich → Cloud runs the
 *   same backup_run() for one account at once.
 * cloud_copies — one transfer is one download and one upload through this
 *   server; a shrunken micro photo a second or two, a macro original
 *   longer.
 * elevation — one request per point, about half a second with the pause:
 *   some two hundred points per 100 seconds.
 * municipalities — deliberately the slowest: Nominatim is donated, its
 *   rules allow one request per second, and the loop keeps that pace —
 *   about seventy points per run; a big stock takes days, the price of
 *   not exploiting someone else's service.
 * protected_areas — three requests at the BfN per find plus pauses, about
 *   two seconds a find, some fifty per run; a run that finds nothing costs
 *   one query.
 * cloud_moves — the moves to another cloud (0142); the open Cloud tab
 *   carries a move itself while it is open (api/v1/cloud_move.php),
 *   through the same cloud_moves_run() and under the same lock.
 * mykis — the DGfM delivery (0127): outside June and December it reads the
 *   calendar and goes home; in those months each call sends what was there
 *   on the 1st, at most MYKIS_MAILS_PER_CALL mails, and books each mail's
 *   finds as it leaves — a mail that did not leave waits for the next call.
 * rain — the rain picture (includes/rain.php): the first call fetches
 *   fourteen day files of ~300 KB, every later one the one new day; a
 *   call on the same day looks at its files and goes home.
 * cloud_names — the pictures' names and folders in the clouds (0151,
 *   includes/cloud_names.php): a move and a rename is one call, the sidecar
 *   two or three more — some sixty pictures per run while the whole stock
 *   is first renamed, afterwards the few finds somebody changed. Most of
 *   it happens right after the request that marked the find, where the
 *   server allows (cloud_names_soon); this catches the rest.
 * cloud_names_all — marks every find with a picture in a cloud once a day,
 *   so the five-minute job looks again; a find that is right costs it a
 *   few queries.
 * picture_stamps — the find's GPS and certain name inside its pictures
 *   (0152, includes/picture_stamps.php): a picture whose find changed is
 *   compared by a line in the database, and only a difference fetches the
 *   file, writes and puts it back — one download and one upload, a few
 *   pictures a day, the stock once after the migration.
 *
 * sidecar_move — ONCE (includes/sidecar_move.php, 10.11.2026): the old
 *   sidecars beside the pictures go down into each folder's „Bearbeitung";
 *   one listing per folder and a move per sidecar, a few hundred folders
 *   per run. When the walk has passed the last folder it notes „done", and
 *   every call after that is a file read.
 *
 * public_site — the public find book on wkpilz.de (includes/public_site.php,
 *   22.11.2026): without a new plate one look at the database and a file
 *   compare, about a second (measured live that day); a new plate page is
 *   one fetch from the cloud and two shrunk copies, a few seconds. HOURLY
 *   AND NOT EVERY FIVE MINUTES: a plate is drawn a few times a week, and a
 *   page the cloud refuses is tried again on every call — twelve times an
 *   hour would only knock twelve times. No site on the server, no work.
 *
 * thumbs — the pictures' small previews onto this server (includes/thumbs.php,
 *   10.10.2026): one request at the picture's cloud per preview and a short
 *   pause, some eighty per run while the stock is first filled; afterwards
 *   only the pictures nobody has looked at yet, and the sweep of previews
 *   whose picture is gone — that one asks the database alone.
 *
 * @return array<string, array{label: string, lock: string, run: callable}>
 */
function cron_jobs(): array
{
    return [
        'species'         => ['label' => 'Arten', 'lock' => 'species', 'run' => 'cron_job_species'],
        'backup'          => ['label' => 'Datensicherung', 'lock' => 'backup', 'run' => 'cron_job_backup'],
        'cloud_copies'    => ['label' => 'Kopien', 'lock' => 'cloud_copies', 'run' => 'cron_job_cloud_copies'],
        'elevation'       => ['label' => 'Höhen', 'lock' => 'elevation', 'run' => 'cron_job_elevation'],
        'municipalities'  => ['label' => 'Gemeinden', 'lock' => 'municipalities', 'run' => 'cron_job_municipalities'],
        // The hyphen is the old lock name, not a typo — see above.
        'protected_areas' => ['label' => 'Schutzgebiete', 'lock' => 'protected-areas', 'run' => 'cron_job_protected_areas'],
        'cloud_moves'     => ['label' => 'Umzüge', 'lock' => 'cloud_moves', 'run' => 'cron_job_cloud_moves'],
        'mykis'           => ['label' => 'Kartierung', 'lock' => 'mykis', 'run' => 'cron_job_mykis'],
        'rain'            => ['label' => 'Regen', 'lock' => 'rain', 'run' => 'cron_job_rain'],
        'login_attempts'  => ['label' => 'Anmeldeversuche', 'lock' => 'login_attempts', 'run' => 'cron_job_login_attempts'],
        'cloud_names'     => ['label' => 'Bildnamen', 'lock' => 'cloud_names', 'run' => 'cron_job_cloud_names'],
        'cloud_names_all' => ['label' => 'Bildnamen prüfen', 'lock' => 'cloud_names_all', 'run' => 'cron_job_cloud_names_all'],
        'picture_stamps'  => ['label' => 'Bilddaten', 'lock' => 'picture_stamps', 'run' => 'cron_job_picture_stamps'],
        'thumbs'          => ['label' => 'Vorschauen', 'lock' => 'thumbs', 'run' => 'cron_job_thumbs'],
        'picture_archives' => ['label' => 'Bildarchive', 'lock' => 'picture_archives', 'run' => 'cron_job_picture_archives'],
        'sidecar_move'    => ['label' => 'Bearbeitung', 'lock' => 'sidecar_move', 'run' => 'cron_job_sidecar_move'],
        'public_site'     => ['label' => 'Öffentliche Seite', 'lock' => 'public_site', 'run' => 'cron_job_public_site'],
    ];
}

/**
 * THE PICTURES' NAMES (includes/cloud_names.php): every five minutes the
 * finds marked due get their pictures moved and renamed — BEHIND the
 * backfills, because a whole stock to rename (after migration 0151, or a
 * new folder scheme) would otherwise sit on their share for hours. It is
 * no longer last: stamps, previews and sidecars came after it, and what
 * they leave reaches it through the second round (cron_run_many).
 */
function cron_job_cloud_names(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/cloud_names.php';
    $r = cloud_names_run($pdo, $deadline);
    $parts = [];
    if ($r['renamed'] > 0) {
        $parts[] = $r['renamed'] . ' Bild' . ($r['renamed'] === 1 ? '' : 'er') . ' umbenannt';
    }
    if ($r['failed'] > 0) {
        $parts[] = $r['failed'] . ' Fehler — siehe Fehlerprotokoll';
    }
    return ['parts' => $parts, 'open' => $r['open']];
}

/**
 * Once a day every find with a picture in a cloud is marked again — the
 * safety net for whatever no hook saw (a GBIF renaming, a hand-moved
 * file). Cheap: a find whose pictures are named right costs the worker a
 * few queries and no call to any cloud.
 */
function cron_job_cloud_names_all(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/cloud_names.php';
    cloud_names_all($pdo);
    return ['parts' => [], 'open' => 0];
}

/**
 * THE FIND IN ITS PICTURES (includes/picture_stamps.php): GPS and, once
 * certain, the name into every picture whose find changed. Behind the
 * names, the last in the bundle: it writes into the file a name has
 * already settled, and it takes what the rest leaves.
 */
function cron_job_picture_stamps(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/picture_stamps.php';
    $r = picture_stamps_run($pdo, $deadline);
    $parts = [];
    if ($r['stamped'] > 0) {
        $parts[] = $r['stamped'] . ' Bild' . ($r['stamped'] === 1 ? '' : 'er') . ' mit Fundort beschrieben';
    }
    if ($r['failed'] > 0) {
        $parts[] = $r['failed'] . ' Fehler — siehe Fehlerprotokoll';
    }
    return ['parts' => $parts, 'open' => $r['open']];
}

/**
 * THE OLD SIDECARS INTO „BEARBEITUNG" (includes/sidecar_move.php) — a
 * one-time walk; last in the five-minute bundle, it takes what the others
 * leave and is a file read once it is through.
 */
function cron_job_sidecar_move(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/sidecar_move.php';
    $r = sidecar_move_run($pdo, $deadline);
    $parts = [];
    if ($r['moved'] > 0) {
        $parts[] = $r['moved'] . ' Begleitdatei' . ($r['moved'] === 1 ? '' : 'en') . ' nach „Bearbeitung“ geräumt';
    }
    if ($r['failed'] > 0) {
        $parts[] = $r['failed'] . ' Fehler — siehe Fehlerprotokoll';
    }
    return ['parts' => $parts, 'open' => $r['open']];
}

/**
 * THE PREVIEWS (includes/thumbs.php) — in the five-minute bundle and not
 * the hourly one: a backfill like the heights, idle once the stock is
 * filled, and at the hourly pace a stock of thousands of pictures would
 * take days. Last in the bundle: the moves and the names come first.
 */
function cron_job_thumbs(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/thumbs.php';
    $r = thumbs_run($pdo, $deadline);
    $parts = [];
    if ($r['made'] > 0) {
        $parts[] = $r['made'] . ' Vorschau' . ($r['made'] === 1 ? '' : 'en') . ' angelegt';
    }
    if ($r['failed'] > 0) {
        $parts[] = $r['failed'] . ' nicht anzulegen (nächster Versuch in einer Woche)';
    }
    if ($r['swept'] > 0) {
        $parts[] = $r['swept'] . ' verwaiste entfernt';
    }
    // The previews kept before 16.11.2026 getting their original's colour profile (thumbs_run).
    if (($r['profiled'] ?? 0) > 0) {
        $parts[] = $r['profiled'] . ' mit Farbprofil ergänzt';
    }
    return ['parts' => $parts, 'open' => thumbs_pending($pdo)];
}

/**
 * THE PICTURE ARCHIVES (includes/picture_archives.php): everybody's shared
 * folder of older pictures read anew once a day, so a folder renamed or
 * added on somebody's disk shows the morning after his backup carried it
 * up. pCloud hands a whole folder in one request — some 14 MB and a few
 * seconds for the maintainer's 11,000 pictures —, the least recently read
 * first, as many as the time allows.
 */
function cron_job_picture_archives(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/picture_archives.php';
    return archives_ready($pdo) ? archives_run($pdo, $deadline) : ['parts' => [], 'open' => 0];
}

function cron_job_rain(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/rain.php';
    return rain_run($deadline);
}

/**
 * THE FAILED LOGINS OLDER THAN A WEEK GO (login_attempts_purge in
 * includes/auth.php says why, 20.11.2026). One statement on a small table;
 * it stands first in the daily bundle and is done before the others begin.
 */
function cron_job_login_attempts(PDO $pdo, float $deadline): array
{
    $gone = login_attempts_purge($pdo);
    return ['parts' => $gone > 0 ? ["$gone ältere gelöscht"] : [], 'open' => 0];
}

/**
 * Named bundles — ONE PER CADENCE, each with a file of its own for manitu's
 * cron (which runs a script by its path, nothing after it):
 * every_5_minutes.php (until 01.10.2026 backfill.php),
 * hourly.php, daily.php.
 *
 * The maintainer decides how often each job runs (23.09.2026: „mach mir bitte
 * nicht einen Eintrag, ich möchte das schon noch steuern können … die
 * fünf-Minuten-Sachen fass zusammen, den Rest lass mich selbst
 * entscheiden"). On 01.10.2026, with the rain picture as a new daily job
 * and five slots in all, he asked for the next step himself: „Sachen, die
 * täglich laufen, sollten wir zusammenlegen. Und andere Sachen, die eben
 * im gleichen Zeitraum immer stattfinden sollen … alle Stunde … dass wir
 * solche Sachen einfach zusammenlegen." So now a bundle per cadence, and
 * the single scripts stay as manual twins.
 *
 * FIVE MINUTES SUITS THE BACKFILLS: without a backlog a call only counts
 * in the own database and asks no foreign service; with one, they SHARE a
 * call's 100 seconds — each service is asked less than when each job had
 * its own 100 seconds every five minutes. THE MOVES since 26.09.2026
 * (the choice A, „kein neuer Eintrag") ride there FIRST, so a
 * user's running move gets at least a quarter of the call and the rest
 * when the others idle (includes/cloud_moves.php).
 *
 * HOURLY: the species sweep FIRST — it takes no deadline and is done in
 * seconds, and named later it could start after the copies had spent the
 * time; then the cloud copies with the rest.
 *
 * DAILY, BEST SHORTLY AFTER MIDNIGHT: the failed logins older than a week
 * go first (one statement, 20.11.2026); the rain picture's window moves at
 * midnight (made later, it shows yesterday's fortnight all day), the DGfM
 * delivery only reads the calendar outside June and December, the older
 * pictures' index takes its few seconds, and the backup comes LAST and takes what the two left — nearly the whole call;
 * what it does not finish it continues the next night, as before.
 */
const CRON_GROUPS = [
    'every_5_minutes' => ['cloud_moves', 'elevation', 'municipalities', 'protected_areas', 'cloud_names', 'picture_stamps', 'thumbs', 'sidecar_move'],
    'hourly'   => ['species', 'public_site', 'cloud_copies'],
    'daily'    => ['login_attempts', 'cloud_names_all', 'rain', 'mykis', 'picture_archives', 'backup'],
];

/**
 * Several jobs in one call, one after another — the heart of the three
 * bundle files.
 *
 * THE BUDGET IS SHARED, NOT MULTIPLIED. The host cuts every call at 120
 * seconds, so the whole call gets 100, and each job a fair share of what
 * is LEFT when its turn comes: of three, the first gets a third; if it is
 * done in two seconds, the other two split the rest. A job with a backlog
 * of a thousand finds can therefore not starve the others — it takes its
 * share, stops, and continues next time, because every loop reads its
 * progress from the data. A job without a deadline (species) ignores its
 * share and sweeps in seconds — in a bundle, name it first.
 *
 * EACH JOB KEEPS ITS OWN LOCK: a job still running from another call is
 * skipped with a line, the others run. One line per job, prefixed with
 * its label; quiet on the CLI when no job had anything to do.
 *
 * @param string[] $names job names, already checked against cron_jobs()
 */
function cron_run_many(PDO $pdo, array $names, float $deadline): void
{
    $jobs = cron_jobs();
    /* A SECOND ROUND FOR WHAT IS STILL OPEN (19.11.2026: „der
       Umzug dauert sehr lange" — the cloud folders' new scheme, every find
       to move). The fair share is reckoned BEFORE the later jobs have run:
       the names worker, fifth of eight, got a quarter of what was left
       even when the three behind it had nothing to do and handed their
       time back unused — some 25 of 100 seconds, while 70 lay idle at the
       end of the call. Now whatever the first round leaves goes to the
       jobs that made progress and still have work, shared fairly again.
       ONLY THOSE THAT MOVED: a job whose open work cannot advance (a
       provider down, every item waiting out its retries) does nothing in
       round one and so gets no second turn to spin in. */
    $again = [];
    foreach ([$names, null] as $round => $list) {
        if ($round === 1) {
            if ($again === [] || $deadline - microtime(true) < 5.0) {
                break;
            }
            $list = $again;
        }
        $left = count($list);
        foreach ($list as $name) {
            $job = $jobs[$name];
            $now = microtime(true);
            $until = $now + max(0.0, ($deadline - $now) / $left);
            $left--;

            if (!cron_lock($pdo, $job['lock'])) {
                if ($round === 0) {
                    cron_say($job['label'] . ': läuft schon — diesmal übersprungen.');
                }
                continue;
            }
            try {
                $result = ($job['run'])($pdo, $until);
                $text = cron_text($result);
                if ($round === 0 && ($result['parts'] ?? []) !== [] && (int) ($result['open'] ?? 0) > 0) {
                    $again[] = $name;
                }
            } catch (Throwable $e) {
                // One job failing must not cost the others their turn.
                error_log('cron ' . $name . ': ' . $e->getMessage());
                $text = 'Fehler — siehe Fehlerprotokoll.';
            }
            if ($text !== null) {
                cron_say($job['label'] . ($round === 1 ? ' (2. Runde)' : '') . ': ' . $text);
            }
        }
    }
}

/**
 * THE PUBLIC FIND BOOK (includes/public_site.php): the site's own refresh,
 * given what is left of this job's share. Quiet when the finds did not
 * change — the run is hourly and mostly finds nothing.
 */
function cron_job_public_site(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/public_site.php';
    $r = public_site_refresh($pdo, max(1.0, $deadline - microtime(true)));
    if ($r === null) {
        return ['parts' => []];
    }
    if ($r['held']) {
        return ['line' => 'nicht aktualisiert — ' . ($r['notes'][0] ?? 'zu viele Tafeln ließen sich nicht holen.')];
    }
    $parts = [];
    if ($r['made'] > 0) {
        $parts[] = $r['made'] . ' Tafelbild' . ($r['made'] === 1 ? '' : 'er') . ' geholt';
    }
    if ($r['changed']) {
        $parts[] = $r['finds'] . ' Funde veröffentlicht';
    }
    return ['parts' => $parts, 'open' => $r['done'] ? 0 : max(0, $r['left'])];
}

function cron_job_species(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/point_species.php';
    require_once PROJECT_PATH . '/includes/data.php';
    require_once PROJECT_PATH . '/includes/point_species_content.php';

    if (!point_species_ready($pdo)) {
        return ['line' => 'Migration 0036 ist noch nicht eingespielt — nichts zu tun.'];
    }
    ['points' => $points, 'names' => $names, 'linked' => $linked] = point_species_rebuild($pdo);

    // The full sweep always "does" something (it rewrites every row), so
    // the quiet-when-idle rule does not fit — the line itself says whether
    // anything of note happened, and only the reference count can.
    return ['line' => "$points Punkte durchgesehen, $names Namen eingetragen, "
        . "$linked Titel-Verweis" . ($linked === 1 ? '' : 'e') . ' angepasst.'];
}

function cron_job_backup(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/backup.php';

    if (!backup_ready($pdo)) {
        return ['line' => 'Migration 0085 ist noch nicht eingespielt — nichts zu tun.'];
    }

    $accounts = 0;
    $written  = 0;
    $bytes    = 0;
    $open     = 0;
    $failed   = 0;

    foreach (backup_users($pdo) as $userId) {
        // NOT `>=`, and the difference matters on an empty run: a call that
        // arrives exactly on its deadline should still look at one account
        // and find nothing to do, rather than report "still open" for
        // everybody.
        if ($accounts > 0 && microtime(true) >= $deadline) {
            $open++;
            continue;
        }

        $run = backup_run($pdo, $userId, $deadline);
        $accounts++;
        $written += $run['written'];
        $bytes   += $run['bytes'];
        $open    += $run['open'];
        if ($run['error'] !== null) {
            $failed++;
            // The message goes into the log, not into the line: it comes
            // from a foreign server and can be long. The line says how many
            // accounts it hit, which is what one reads a cron mail for.
            error_log('cron backup (' . $userId . '): ' . $run['error']);
        }
    }

    $parts = [];
    if ($written) {
        $parts[] = $written . ' Monat' . ($written === 1 ? '' : 'e') . ' gesichert'
                 . ' (' . bytes_readable($bytes) . ')';
    }
    if ($failed) {
        $parts[] = $failed . ' Zugang' . ($failed === 1 ? '' : 'zugänge') . ' meldete einen Fehler'
                 . ' — siehe Fehlerprotokoll';
    }
    return ['parts' => $parts, 'open' => $open];
}

function cron_job_cloud_copies(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/cloud_copies_content.php';
    return ['parts' => cloud_copies_summary(cloud_copies_run($pdo, $deadline)),
            'open'  => cloud_copies_pending($pdo)];
}

function cron_job_cloud_moves(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/cloud_moves.php';
    return ['parts' => cloud_moves_summary(cloud_moves_run($pdo, $deadline)),
            'open'  => cloud_moves_pending($pdo)];
}

function cron_job_elevation(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/elevation_content.php';
    ['fetched' => $fetched, 'outside' => $outside, 'failed' => $failed] = elevation_run($pdo, $deadline);

    $parts = [];
    if ($fetched) $parts[] = "$fetched Höhen eingetragen";
    if ($outside) $parts[] = "$outside in keinem Höhenmodell erfasst";
    if ($failed)  $parts[] = "$failed fehlgeschlagen (kommen beim nächsten Lauf wieder dran)";
    return ['parts' => $parts, 'open' => elevation_pending($pdo)];
}

function cron_job_municipalities(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/municipalities_content.php';
    ['fetched' => $fetched, 'without' => $without, 'failed' => $failed] = municipalities_run($pdo, $deadline);

    $parts = [];
    if ($fetched) $parts[] = "$fetched Gemeinden eingetragen";
    if ($without) $parts[] = "$without ohne Gemeinde (Meer, Niemandsland)";
    if ($failed)  $parts[] = "$failed fehlgeschlagen (kommen beim nächsten Lauf wieder dran)";
    return ['parts' => $parts, 'open' => municipalities_pending($pdo)];
}

function cron_job_protected_areas(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/protected_areas_content.php';
    ['recorded' => $recorded, 'without' => $without, 'failed' => $failed] = protected_areas_run($pdo, $deadline);

    $parts = [];
    if ($recorded) $parts[] = "$recorded Funde mit Schutzgebiet vermerkt";
    if ($without)  $parts[] = "$without liegen in keinem";
    if ($failed)   $parts[] = "$failed fehlgeschlagen (kommen beim nächsten Lauf wieder dran)";
    return ['parts' => $parts, 'open' => protected_areas_pending($pdo)];
}

/**
 * The finds of the finders who agreed, to the state coordinators who
 * agreed — early in June and early in December (includes/mykis.php).
 * Quiet outside those months and when nothing waits.
 */
function cron_job_mykis(PDO $pdo, float $deadline): array
{
    require_once PROJECT_PATH . '/includes/mykis.php';

    if (!mykis_ready($pdo)) {
        return ['line' => 'Migration 0127 ist noch nicht eingespielt — nichts zu tun.'];
    }
    $cutoff = mykis_cutoff(new DateTimeImmutable('now'));
    if ($cutoff === null) {
        return ['parts' => [], 'open' => 0];
    }

    $done = mykis_deliver($pdo, $cutoff, $deadline);
    $parts = [];
    if ($done['mails'] > 0) {
        $parts[] = $done['mails'] . ' Mail' . ($done['mails'] === 1 ? '' : 's') . ' mit '
                 . $done['finds'] . ' Fund' . ($done['finds'] === 1 ? '' : 'en') . ' an die Landeskoordination';
    }
    if ($done['confirmations'] > 0) {
        $parts[] = $done['confirmations'] . ' Kopie' . ($done['confirmations'] === 1 ? '' : 'n') . ' an Finder';
    }
    if ($done['failed'] > 0) {
        // Not recorded as delivered — they wait for tomorrow's run.
        $parts[] = $done['failed'] . ' Mail' . ($done['failed'] === 1 ? '' : 's') . ' ließ sich nicht senden';
    }
    return ['parts' => $parts, 'open' => $done['open']];
}
