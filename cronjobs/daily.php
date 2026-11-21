<?php
/**
 * Once a day, ONE cron entry: the old failed logins go, then the rain
 * picture, the DGfM delivery and the backup among others
 * (CRON_GROUPS['daily'] in _jobs.php — what each costs stands there).
 *
 *   php cronjobs/daily.php [--seconds=100]
 *   /web/wkpilz.de/map/cronjobs/daily.php   (manitu, daily at about 00:15)
 *
 * SHORTLY AFTER MIDNIGHT: the rain picture's window moves at midnight —
 * made later, the picture shows yesterday's fortnight all day. The order is
 * rain, DGfM, backup: the first two are done in seconds, the backup takes
 * the rest of the call's 100 seconds and goes on the next night with what
 * it did not finish.
 *
 * ALSO BY HAND, for the first rain picture right away: run it once — the
 * rain comes first, and the other two do what they would do at night.
 */

declare(strict_types=1);

require_once __DIR__ . '/_jobs.php';

$deadline = cron_guard($config, 100);
cron_run_many($pdo, CRON_GROUPS['daily'], $deadline);
