<?php
/**
 * Backfilling terrain elevations — processing and view.
 *
 * New points fetch their elevation themselves when created. Everything that
 * came before — taken over from Google, set by hand, from an import — has
 * none yet, or only the imprecise one from the satellite receiver. This
 * here fetches it after the fact.
 *
 * WHY NO MIGRATION
 * A .sql file cannot ask a web service. This here needs one outbound
 * request per point and thus time, which no migration has.
 *
 * IN CHUNKS, as with the taxonomy, and for the same reason: the hoster
 * limits the runtime of a call. Where it stands, it knows from the data
 * itself — it looks for points without a terrain elevation. Nothing that
 * could drift apart.
 *
 * WITH A PAUSE BETWEEN THE REQUESTS. The service belongs to someone else
 * and answers in around 330 ms; hammering it without pausing for breath
 * would be impolite and gain nothing, because the runtime is limited
 * anyway.
 *
 * POINTS OUTSIDE GERMANY get their elevation from the worldwide Copernicus
 * model — coarser, but there. Only what is not covered there either is
 * permanently recorded as 'outside', so it is not queried again on
 * every run.
 */

declare(strict_types=1);

require_once __DIR__ . '/elevation.php';

const ELEVATION_SECONDS = 15;
const ELEVATION_PAUSE_MS = 120;

/** Points that still need a terrain elevation. */
function elevation_pending(PDO $pdo): int
{
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM map_points
          WHERE deleted_at IS NULL
            AND (elevation_source IS NULL OR elevation_source NOT IN ('dgm1', 'worldwide', 'outside'))"
    )->fetchColumn();
}

/**
 * Work through pending points until the deadline — the shared core of the
 * tab's chunk button and the cron job (cronjobs/every_5_minutes.php). One loop
 * for both, or the two would drift apart in exactly the parts that matter:
 * the pause towards the service and the 'outside' bookkeeping.
 *
 * @return array{fetched: int, outside: int, failed: int}
 */
function elevation_run(PDO $pdo, float $deadline): array
{
    $fetched = 0;
    $outside = 0;
    $failed  = 0;

    $stmt = $pdo->query(
        "SELECT id, user_id, lat, lon FROM map_points
          WHERE deleted_at IS NULL
            AND (elevation_source IS NULL OR elevation_source NOT IN ('dgm1', 'worldwide', 'outside'))
          ORDER BY id
          LIMIT 500"
    );

    foreach ($stmt as $p) {
        if (microtime(true) >= $deadline) {
            break;
        }

        try {
            elevation_record($pdo, (int) $p['user_id'], (int) $p['id']);
            $fetched++;
        } catch (ElevationOutside $e) {
            if ($e->isOutside()) {
                // Record permanently. Otherwise every further run queries the
                // same points again, and progress would stand still.
                $pdo->prepare("UPDATE map_points SET elevation_source = 'outside' WHERE id = ?")
                    ->execute([$p['id']]);
                $outside++;
            } else {
                $failed++;
            }
        } catch (Throwable $e) {
            $failed++;
        }

        usleep(ELEVATION_PAUSE_MS * 1000);
    }

    return compact('fetched', 'outside', 'failed');
}

/**
 * Backfill one chunk. Must run BEFORE any output.
 *
 * @return array|null  message as [kind, text], or null if nothing ran
 */
function elevation_process(PDO $pdo): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'elevation') {
        return null;
    }
    csrf_check();

    ['fetched' => $fetched, 'outside' => $outside, 'failed' => $failed]
        = elevation_run($pdo, microtime(true) + ELEVATION_SECONDS);

    $parts = [];
    if ($fetched) $parts[] = t('%d Höhen eingetragen', $fetched);
    if ($outside) $parts[] = t('%d in keinem Höhenmodell erfasst', $outside);
    if ($failed)  $parts[] = t('%d fehlgeschlagen (kommen beim nächsten Lauf wieder dran)', $failed);

    $openCount = elevation_pending($pdo);
    return [
        $openCount === 0 ? 'success' : 'hint',
        ($parts === [] ? t('Nichts zu tun.') : implode(' · ', $parts))
            . ($openCount === 0 ? ' — ' . t('fertig.') : ' · ' . t('noch %d offen', $openCount)),
    ];
}

/** The view. $message comes from elevation_process(). */
function elevation_render(PDO $pdo, ?array $message): void
{
    $openCount  = elevation_pending($pdo);
    $counts = $pdo->query(
        "SELECT
            SUM(elevation_source = 'dgm1')       AS dgm1,
            SUM(elevation_source = 'worldwide')   AS worldwide,
            SUM(elevation_source = 'gnss')       AS gnss,
            SUM(elevation_source = 'outside') AS outside,
            SUM(elevation_source IS NULL)        AS missing,
            COUNT(*)                          AS total
           FROM map_points WHERE deleted_at IS NULL"
    )->fetch();

    // The anchor in the target: without it you land in the first tab after
    // every chunk — and that in a process that submits itself a hundred times.
    $target = url('pages/administration.php') . '#elevation';
    ?>

<p class="hint-text">
    <?= h(t('Neue Punkte holen ihre Höhe beim Anlegen selbst. Hier werden sie für alle älteren nachgetragen — in Häppchen von %d Sekunden, mit einer kurzen Pause zwischen den Anfragen. Der Lauf darf jederzeit unterbrochen und wieder gestartet werden.', ELEVATION_SECONDS)) ?>
    <?= t('Für große Bestände erledigt %s dasselbe unbeaufsichtigt — wie man es einrichtet, steht im Kopf der Datei.', '<code>cronjobs/every_5_minutes.php</code>') ?>
</p>

<?php if ($message !== null) { notice($message[0], $message[1]); } ?>

<div class="table-frame">
    <table class="list">
        <tr><th><?= h(t('deutsches Geländemodell (±0,3 m)')) ?></th><td><?= (int) $counts['dgm1'] ?></td></tr>
        <tr><th><?= h(t('weltweites Modell (±5 m)')) ?></th><td><?= (int) $counts['worldwide'] ?></td></tr>
        <tr><th><?= h(t('nur aus dem Empfänger (±10 m)')) ?></th><td><?= (int) $counts['gnss'] ?></td></tr>
        <tr><th><?= h(t('ohne Höhe')) ?></th><td><?= (int) $counts['missing'] ?></td></tr>
        <tr><th><?= h(t('nirgends erfasst')) ?></th><td><?= (int) $counts['outside'] ?></td></tr>
        <tr><th><?= h(t('Punkte insgesamt')) ?></th><td><?= (int) $counts['total'] ?></td></tr>
    </table>
</div>

<form method="post" id="elevation-form" action="<?= h($target) ?>" style="margin-top:1.5rem">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="elevation">
    <button class="button" type="submit"><?= h($openCount > 0 ? t('Nachtragen (%d offen)', $openCount) : t('Noch einmal prüfen')) ?></button>
    <?php if ($openCount > 0): ?>
        <label class="checkbox" style="margin-top:.8rem">
            <input type="checkbox" id="elevation-auto" checked>
            <span><?= h(t('Von selbst weitermachen, bis alle erfasst sind')) ?></span>
        </label>
    <?php endif; ?>
</form>

<?php if ($message !== null && $openCount > 0): ?>
<script>
/*
 * Continue, but only mid-run.
 *
 * On its own page "on load, if checked" sufficed — whoever called it up
 * wanted to backfill. Here it is one tab among several: the same condition
 * would mean that EVERY visit to the administration starts querying
 * third-party services unasked, even if you only came to empty the trash.
 *
 * That is why reloading only continues if a chunk has just run — the first
 * request comes from the button, every further one from here. The checkbox
 * stops it: it is read before the next step, not memorized beforehand.
 */
if (document.getElementById('elevation-auto')?.checked) {
    setTimeout(() => document.getElementById('elevation-form').submit(), 800);
}
</script>
<?php endif; ?>

    <?php
}
