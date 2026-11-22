<?php
/**
 * Every hour, ONE cron entry: the species sweep, the public find book on
 * wkpilz.de and the cloud copies
 * (CRON_GROUPS['hourly'] in _jobs.php — what each costs stands there).
 *
 *   php cronjobs/hourly.php [--seconds=100]
 *   /web/wkpilz.de/map/cronjobs/hourly.php   (manitu, every hour on the hour)
 *
 * 01.10.2026: what runs at the same cadence goes into one
 * entry — five is manitu's limit. The species sweep runs FIRST (it takes
 * no deadline and is done in seconds), the public site second (a second
 * when no plate is new), the copies get the rest of the call's 100 seconds
 * and go on next hour with what they did not finish.
 */

declare(strict_types=1);

require_once __DIR__ . '/_jobs.php';

$deadline = cron_guard($config, 100);
cron_run_many($pdo, CRON_GROUPS['hourly'], $deadline);
