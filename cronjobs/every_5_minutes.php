<?php
/**
 * Every five minutes, ONE cron entry: the moves to another cloud,
 * heights, municipalities, protected areas (CRON_GROUPS['every_5_minutes']
 * in _jobs.php — what each costs stands there).
 *
 *   php cronjobs/every_5_minutes.php [--seconds=100]
 *   /web/wkpilz.de/map/cronjobs/every_5_minutes.php   (manitu, every 5 min)
 *
 * NAMED BY ITS CADENCE since 01.10.2026, as hourly.php and daily.php
 * („die Dateien gleich so benennen, wie der Zeitraum ist");
 * until then backfill.php. A file and not a URL with a job list, because
 * manitu's cron runs a script by its path with nothing after it — called
 * that way it is a command-line call and needs no key.
 *
 * FIVE MINUTES SUITS THEM: without a backlog a call only counts in the own
 * database and asks no foreign service; with one, they share the call's
 * 100 seconds (the host kills a call at 120), each a fair share of what is
 * left (cron_run_many). The moves run FIRST, so a running move gets at
 * least a quarter of the call.
 */

declare(strict_types=1);

require_once __DIR__ . '/_jobs.php';

$deadline = cron_guard($config, 100);
cron_run_many($pdo, CRON_GROUPS['every_5_minutes'], $deadline);
