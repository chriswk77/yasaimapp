<?php
/**
 * The shared frame of all cron jobs — access, lock, time budget, output.
 *
 * WHY THIS DIRECTORY EXISTS: the backfills in the administration keep the
 * browser tab open and click themselves onward in chunks — fine for a
 * hundred points, tiresome for an import of thousands. A cron job does the
 * same work unattended, in the same polite pace, because it runs the SAME
 * loop: each job (cron_jobs() in _jobs.php) calls the `*_run()` core its
 * administration tab uses (includes/*_content.php). Nothing is computed
 * differently, nothing can drift apart. The files that are called are the
 * three bundles, one per cadence (CRON_GROUPS).
 *
 * TWO WAYS TO CALL — both are needed, because hosters differ:
 *
 *   CLI (crontab, or a hoster that runs commands — manitu, by the path):
 *     php cronjobs/daily.php
 *     php cronjobs/daily.php --seconds=120
 *
 *   HTTP (a hoster whose cron can only call a URL):
 *     https://…/cronjobs/daily.php?key=…&seconds=120
 *
 *   The HTTP way requires 'cron_key' in the configuration (see
 *   config.sample.php) and refuses without it — an address anyone could
 *   call would let strangers direct traffic at foreign services on this
 *   installation's behalf. The CLI way needs no key: whoever can run
 *   commands on the server holds the configuration anyway.
 *
 * FRIENDLY TOWARDS THE OTHER SIDE, and that is the point of the exercise:
 * the pace lives in the shared run loops (a pause after every request —
 * Nominatim gets its full second), and the time budget below cuts every
 * invocation short. A job that finds more work than fits its budget simply
 * stops; the next invocation continues where it left off, because every
 * loop reads its progress from the data itself. Overlap is impossible: a
 * database lock lets a second invocation end immediately while the first
 * is still running — so a generous budget and a tight cron cadence cannot
 * pile up.
 *
 * OUTPUT is one line per run, German, for whoever reads the cron mail or
 * the hoster's log. When there was nothing to do, the CLI stays silent
 * (cron etiquette: no mail without news), while HTTP always answers with a
 * line — a URL cron that gets an empty response looks failed.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

/**
 * The key and the time budget of a call. The locks are taken per job
 * (cron_run_many in _jobs.php), since one call runs several.
 *
 * @return float the deadline for the whole call (microtime)
 */
function cron_guard(array $config, int $seconds): float
{
    if (PHP_SAPI !== 'cli') {
        header('Content-Type: text/plain; charset=utf-8');

        // hash_equals, not ===: a comparison that gives up at the first
        // wrong byte tells an attacker how many bytes were right.
        $key = (string) ($config['cron_key'] ?? '');
        /* THE KEY MAY ALSO COME AS A HEADER (security review, 23.09.2026):
           in the address it lands in every access log the host keeps.
           Whoever's cron can send one („X-Cron-Key: …", e.g. curl -H)
           should; ?key= stays for the hosters whose cron takes only a URL. */
        $sent = (string) ($_SERVER['HTTP_X_CRON_KEY'] ?? $_GET['key'] ?? '');
        if ($key === '' || !hash_equals($key, $sent)) {
            http_response_code(403);
            exit("Kein oder falscher Schlüssel. Für Aufrufe per URL muss 'cron_key' in der Konfiguration stehen und als ?key=… mitkommen.\n");
        }

        // The run should end on OUR deadline, not on the hoster's — a
        // killed request cannot say what it managed. The margin covers the
        // request that is in flight when the deadline strikes.
        ignore_user_abort(true);
        @set_time_limit($seconds + 60);
    }

    $requested = PHP_SAPI === 'cli'
        ? cron_option('seconds')
        : ($_GET['seconds'] ?? null);
    if ($requested !== null && (int) $requested > 0) {
        $seconds = min(600, (int) $requested);
    }

    return microtime(true) + $seconds;
}

/**
 * One job, one run. GET_LOCK is released when the connection ends, so
 * even a fatally dying run cannot leave the lock behind. Timeout 0: a
 * second invocation does not wait, it states the fact and goes — the work
 * is not lost, the next invocation gets it. One connection may hold the
 * locks of several jobs at once (MySQL 5.7+), which is what a bundle does.
 */
function cron_lock(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $stmt->execute(['map_cron_' . $name]);
    return (int) $stmt->fetchColumn() === 1;
}

/** A CLI option of the form --name=value, or null. */
function cron_option(string $name): ?string
{
    foreach ($GLOBALS['argv'] ?? [] as $arg) {
        if (str_starts_with($arg, "--$name=")) {
            return substr($arg, strlen($name) + 3);
        }
    }
    return null;
}

/** One line of report. */
function cron_say(string $line): void
{
    echo $line, "\n";
}

/**
 * The closing line of one job: what was done, what remains — or null when
 * nothing happened and the call came from the CLI (quiet when idle); HTTP
 * always gets a line.
 *
 * @param array{parts?: string[], open?: int, line?: string} $result
 *        what a job of _jobs.php hands back; 'line' is said as it is
 */
function cron_text(array $result): ?string
{
    if (isset($result['line'])) {
        return $result['line'];
    }
    $parts = $result['parts'] ?? [];
    $open  = (int) ($result['open'] ?? 0);
    if ($parts === []) {
        return PHP_SAPI !== 'cli' ? 'Nichts zu tun.' . ($open > 0 ? " Noch $open offen." : '') : null;
    }
    return implode(' · ', $parts) . ($open === 0 ? ' — fertig.' : " · noch $open offen");
}
