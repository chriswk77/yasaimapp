<?php
/**
 * Backfilling municipalities — processing and view.
 *
 * New points fetch their municipality themselves when created. Everything
 * that came before — taken over from Google, set by hand, from an import —
 * has none yet. This here fetches it after the fact.
 *
 * ONE REQUEST PER SECOND, AND THAT IS THE UPPER LIMIT, NOT THE TARGET.
 * Nominatim is donated, not bought; the usage rules allow one request per
 * second and explicitly ask not to run bulk queries. This here IS the
 * borderline case: a stock of a few thousand points is exactly the mass
 * that is not meant.
 *
 * That is why it deliberately runs slowly — one chunk fetches at most a
 * dozen points, with a full second in between. Eight thousand points take
 * over two hours this way. That is not an oversight but the price of not
 * exploiting someone else's service. Whoever wants the whole stock in one
 * go builds the own boundary table; that is what it is for.
 *
 * IN CHUNKS, as with elevations and taxonomy, and for the same reason: the
 * hoster limits the runtime of a call. Where it stands, it knows from the
 * data itself — it looks for points without municipality_source. Nothing that
 * could drift apart.
 */

declare(strict_types=1);

require_once __DIR__ . '/municipality.php';
// For autolists_refresh() below — the administration loads it anyway, but
// a file that calls a function should say where it comes from.
require_once __DIR__ . '/data.php';

/**
 * How long one chunk runs.
 *
 * Shorter than with the elevations (15 s), because every request here drags
 * a second of pause behind it: twelve seconds is around ten points, and
 * that is the portion after which the page reports back.
 */
const MUNICIPALITIES_SECONDS = 12;
const MUNICIPALITIES_PAUSE_MS = 1050;

/** Are the columns there yet? The code goes live before the migration. */
function municipalities_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_points', 'municipality');
}

/** Points that still need a municipality. */
function municipalities_pending(PDO $pdo): int
{
    if (!municipalities_ready($pdo)) {
        return 0;
    }
    return (int) $pdo->query(
        'SELECT COUNT(*) FROM map_points
          WHERE deleted_at IS NULL AND municipality_source IS NULL'
    )->fetchColumn();
}

/**
 * Work through pending points until the deadline — the shared core of the
 * tab's chunk button and the cron job (cronjobs/every_5_minutes.php). One
 * loop for both, or the two would drift apart in the one part that really
 * matters here: the full second of pause Nominatim's usage rules demand.
 *
 * @return array{fetched: int, without: int, failed: int}
 */
function municipalities_run(PDO $pdo, float $deadline): array
{
    $fetched = 0;
    $without = 0;
    $failed  = 0;

    if (!municipalities_ready($pdo)) {
        return compact('fetched', 'without', 'failed');
    }

    // Keep LIMIT small: more than one chunk can manage would only be ballast
    // in memory — and the order by id ensures that every run continues where
    // the last one stopped, without having to remember anything.
    $stmt = $pdo->query(
        'SELECT id, user_id FROM map_points
          WHERE deleted_at IS NULL AND municipality_source IS NULL
          ORDER BY id
          LIMIT 100'
    );

    foreach ($stmt as $p) {
        if (microtime(true) >= $deadline) {
            break;
        }

        try {
            // Not silent: here we WANT to know why something did not work —
            // during creation it would be noise, here it is the purpose.
            municipality_record($pdo, (int) $p['user_id'], (int) $p['id']);
            /* AND THE AUTO LISTS RIGHT AFTER IT. A filter may ask for a
               region or a municipality (point_matches_filter in
               data.php) — and until this moment the point had neither,
               so it fell out of exactly that condition. Without this
               line the datum would arrive and the membership would not,
               until some other write access happened to touch the
               point.
               
               It has always been missing here; it only started to
               matter on 25.08.2026, when the import stopped fetching
               the municipality itself and left every imported point to
               this backfill. */
            autolists_refresh($pdo, (int) $p['user_id'], (int) $p['id']);
            $fetched++;
        } catch (NoMunicipality $e) {
            // municipality_record has already recorded that permanently.
            $without++;
        } catch (Throwable $e) {
            $failed++;
        }

        usleep(MUNICIPALITIES_PAUSE_MS * 1000);
    }

    return compact('fetched', 'without', 'failed');
}

/**
 * Backfill one chunk. Must run BEFORE any output.
 *
 * @return array|null  message as [kind, text], or null if nothing ran
 */
function municipalities_process(PDO $pdo): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'municipalities') {
        return null;
    }
    csrf_check();

    if (!municipalities_ready($pdo)) {
        return ['error', t('Die Spalten fehlen noch — erst Migration 0037 einspielen.')];
    }

    // Its own button, its own answer: resetting asks nobody outside, it
    // only marks. Afterwards the ordinary run picks those points up along
    // the way — hence no second mechanism that would do the same once more.
    if (($_POST['what'] ?? '') === 'veraltet') {
        $n = municipality_outdated($pdo, true);
        return [$n > 0 ? 'success' : 'hint', $n > 0
            ? t('%d Punkt(e) mit veraltetem Kürzel wieder als offen markiert — sie werden beim nächsten Nachtragen neu gefragt.', $n)
            : t('Kein Punkt trägt ein veraltetes Kürzel.')];
    }

    ['fetched' => $fetched, 'without' => $without, 'failed' => $failed]
        = municipalities_run($pdo, microtime(true) + MUNICIPALITIES_SECONDS);

    $parts = [];
    if ($fetched) $parts[] = t('%d Gemeinden eingetragen', $fetched);
    if ($without) $parts[] = t('%d ohne Gemeinde (Meer, Niemandsland)', $without);
    if ($failed)  $parts[] = t('%d fehlgeschlagen (kommen beim nächsten Lauf wieder dran)', $failed);

    $openCount = municipalities_pending($pdo);
    return [
        $openCount === 0 ? 'success' : 'hint',
        ($parts === [] ? t('Nichts zu tun.') : implode(' · ', $parts))
            . ($openCount === 0 ? ' — ' . t('fertig.') : ' · ' . t('noch %d offen', $openCount)),
    ];
}

/** The view. $message comes from municipalities_process(). */
function municipalities_render(PDO $pdo, ?array $message): void
{
    if (!municipalities_ready($pdo)) {
        notice('hint', t('Die Spalten für die Gemeinde fehlen noch. Erst Migration 0037 einspielen, dann steht dieses Werkzeug bereit.'));
        return;
    }

    $openCount  = municipalities_pending($pdo);
    $counts = $pdo->query(
        "SELECT
            SUM(municipality_source = 'osm')  AS recorded,
            SUM(municipality_source = 'none') AS without_any,
            SUM(municipality_source IS NULL)  AS still_open,
            -- NOT COUNT(DISTINCT municipality). Municipality names are not
            -- unique: the German-speaking area has dozens of Neustadt,
            -- Bergheim exists in Bavaria and in Salzburg country. Counted
            -- by the bare name every second Neustadt drops out, and the
            -- number would be too small without showing it.
            --
            -- COALESCE, because COUNT(DISTINCT a,b,c) in MySQL discards
            -- every row in which even one part is NULL — and region is
            -- regularly NULL outside DACH.
            COUNT(DISTINCT country, COALESCE(region, ''), municipality) AS municipalities,
            COUNT(*)                      AS total
           FROM map_points WHERE deleted_at IS NULL"
    )->fetch();

    // The most common subdivisions — the number that shows at a glance
    // whether the code table is doing the right thing. A point in "IT/32"
    // stands out here immediately, a misassigned federal state too.
    $parts = $pdo->query(
        "SELECT country, region, COUNT(*) AS n
           FROM map_points
          WHERE deleted_at IS NULL AND country IS NOT NULL
          GROUP BY country, region
          ORDER BY n DESC, country, region
          LIMIT 12"
    )->fetchAll();

    // The anchor in the target: without it you land in the first tab after
    // every chunk — and that in a process that submits itself a hundred times.
    $target = url('pages/administration.php') . '#municipalities';
    ?>

<p class="hint-text">
    <?= t('Staat, Landesteil und Gemeinde zu jedem Punkt — dasselbe Schema, nach dem die Belegfotos auf der Festplatte liegen (%s).', '<code>DE/BAY/Mittenwald</code>') ?>
    <?= h(t('Neue Punkte holen es beim Anlegen selbst; hier wird es für alle älteren nachgetragen.')) ?>
</p>
<p class="hint-text">
    <strong><?= h(t('Das dauert.')) ?></strong>
    <?= h(t('Die Auskunft kommt von Nominatim, dem Ortsdienst von OpenStreetMap — gestiftet, nicht gekauft. Er erlaubt eine Anfrage je Sekunde, und daran hält sich dieses Werkzeug: rund zehn Punkte je Häppchen von %d Sekunden. Der Lauf darf jederzeit unterbrochen und wieder gestartet werden.', MUNICIPALITIES_SECONDS)) ?>
    <?= t('Für große Bestände erledigt %s dasselbe unbeaufsichtigt, im selben Takt — wie man es einrichtet, steht im Kopf der Datei.', '<code>cronjobs/every_5_minutes.php</code>') ?>
</p>

<?php if ($message !== null) { notice($message[0], $message[1]); } ?>

<div class="table-frame">
    <table class="list">
        <tr><th><?= h(t('mit Gemeinde')) ?></th><td><?= (int) $counts['recorded'] ?></td></tr>
        <tr><th><?= h(t('verschiedene Gemeinden')) ?></th><td><?= (int) $counts['municipalities'] ?></td></tr>
        <tr><th><?= h(t('ohne Gemeinde (Meer, Niemandsland)')) ?></th><td><?= (int) $counts['without_any'] ?></td></tr>
        <tr><th><?= h(t('noch nicht gefragt')) ?></th><td><?= (int) $counts['still_open'] ?></td></tr>
        <tr><th><?= h(t('Punkte insgesamt')) ?></th><td><?= (int) $counts['total'] ?></td></tr>
    </table>
</div>

<?php if ($parts !== []): ?>
<div class="table-frame" style="margin-top:1rem">
    <table class="list">
        <?php foreach ($parts as $t): ?>
        <tr>
            <th><code><?= h(((string) $t['country']) . '/' . ((string) ($t['region'] ?? '—'))) ?></code></th>
            <td><?= (int) $t['n'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php $outdated = municipality_outdated($pdo); ?>
<?php if ($outdated > 0): ?>
<div class="notice notice-hint" style="margin-top:1rem">
    <p>
        <strong><?= h(t('%d Punkt(e) tragen ein Kürzel, das es in der Tabelle ihres Landes nicht gibt', $outdated)) ?></strong> —
        <?= t('sie stammen aus einer Zeit, in der es für dieses Land noch keine gab, und stehen deshalb mit dem blossen ISO-Rest da (%s statt %s).', '<code>IT/82</code>', '<code>IT/SIC</code>') ?>
        <?= h(t('Einmal zurücksetzen, dann holt der Lauf oben sie mit.')) ?>
    </p>
    <form method="post" action="<?= h($target) ?>" style="margin-top:.6rem">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="municipalities">
        <input type="hidden" name="what" value="veraltet">
        <button class="button subtle" type="submit"><?= h(t('Diese %d neu erfragen', $outdated)) ?></button>
    </form>
</div>
<?php endif; ?>

<form method="post" id="municipality-form" action="<?= h($target) ?>" style="margin-top:1.5rem">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="municipalities">
    <button class="button" type="submit"><?= h($openCount > 0 ? t('Nachtragen (%d offen)', $openCount) : t('Noch einmal prüfen')) ?></button>
    <?php if ($openCount > 0): ?>
        <label class="checkbox" style="margin-top:.8rem">
            <input type="checkbox" id="municipality-auto" checked>
            <span><?= h(t('Von selbst weitermachen, bis alle erfasst sind')) ?></span>
        </label>
    <?php endif; ?>
</form>

<?php if ($message !== null && $openCount > 0): ?>
<script>
/* Continue, but only mid-run — for the same reason as with the elevation
   backfill: otherwise EVERY visit to the administration would start querying
   a third-party service unasked, even if you only came to empty the trash. */
if (document.getElementById('municipality-auto')?.checked) {
    setTimeout(() => document.getElementById('municipality-form').submit(), 800);
}
</script>
<?php endif; ?>

    <?php
}
