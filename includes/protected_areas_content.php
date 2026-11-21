<?php
/**
 * Backfilling protected areas — processing and view.
 *
 * A find fetches its protected area itself on creation (protected_areas.php).
 * Two paths around that remain, and this tool exists for those:
 *
 *   IMPORTS skip the note on purpose. A takeout brings hundreds of finds,
 *   and per find three requests go out to a foreign, public service — that
 *   does not belong in an import someone is waiting out in front of the
 *   screen.
 *
 *   POINTS SORTED IN AFTER THE FACT. Whoever uses Organisieren to move 143 points
 *   from "Ohne Liste" into a species-list branch thereby turns them into
 *   finds. Hanging the note onto the list change would be wrong: the same
 *   bulk action would then trigger 429 requests, right in the middle of
 *   sorting.
 *
 * Both are the same sort of gap as with elevation and municipality, which
 * is why this sits next to their tools in the same tab.
 *
 * HOW IT KNOWS WHERE IT STANDS: from the data itself — finds whose column
 * is still NULL. The column's three states carry that (see
 * protected_area_record): NULL means "never asked", the empty text means
 * "asked, lies in none". Without this separation every run would go over
 * the same points in the middle of the field again, and progress would
 * stand still.
 *
 * IN CHUNKS, as with the elevations and for the same reason: the hoster
 * limits a request's runtime. A find costs more here than an elevation —
 * three requests instead of one, measured around 0.7 s —, so it is more
 * like twenty points per chunk than a hundred.
 */

declare(strict_types=1);

require_once __DIR__ . '/protected_areas.php';

const PROTECTION_SECONDS = 15;

/**
 * Pause between two points — IN ADDITION to the one between a point's three
 * requests (PROTECTED_AREA_PAUSE_MS in protected_areas.php). The actual brake
 * sits there; this one is the paragraph break between two finds, so the
 * service is not clocked through without a gap.
 */
const PROTECTION_PAUSE_MS = 300;

/**
 * The condition "is a find" as an SQL fragment, values included.
 *
 * Null back if there are no species lists at all: an IN () without content
 * is a syntax error, and the honest answer is "nothing to do" anyway.
 *
 * @return array{0: string, 1: int[]}|null
 */
function protected_areas_find_condition(PDO $pdo): ?array
{
    $lists = protected_areas_species_lists($pdo);
    if ($lists === []) {
        return null;
    }
    $space = implode(',', array_fill(0, count($lists), '?'));
    return [
        "EXISTS (SELECT 1 FROM map_point_lists pl
                  WHERE pl.point_id = p.id AND pl.list_id IN ($space))",
        $lists,
    ];
}

/** Finds that have no note yet. */
function protected_areas_pending(PDO $pdo): int
{
    $b = protected_areas_find_condition($pdo);
    if ($b === null) {
        return 0;
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM map_points p
          WHERE p.deleted_at IS NULL AND p.protected_area IS NULL AND {$b[0]}"
    );
    $stmt->execute($b[1]);
    return (int) $stmt->fetchColumn();
}

/**
 * Work through pending finds until the deadline — the shared core of the
 * tab's chunk button and the cron job (cronjobs/every_5_minutes.php). One
 * loop for both, or the two would drift apart in exactly the parts that
 * matter: the pauses towards the BfN and the three-state bookkeeping.
 *
 * @return array{recorded: int, without: int, failed: int}
 */
function protected_areas_run(PDO $pdo, float $deadline): array
{
    $recorded = 0;
    $without  = 0;
    $failed   = 0;

    $b = protected_areas_find_condition($pdo);
    if ($b === null) {
        return compact('recorded', 'without', 'failed');
    }

    $stmt = $pdo->prepare(
        "SELECT p.id, p.user_id FROM map_points p
          WHERE p.deleted_at IS NULL AND p.protected_area IS NULL AND {$b[0]}
          ORDER BY p.id
          LIMIT 500"
    );
    $stmt->execute($b[1]);

    foreach ($stmt->fetchAll() as $p) {
        if (microtime(true) >= $deadline) {
            break;
        }

        // NOT silent: here, what fails is meant to be counted — unlike on
        // creation, where a silent service must not bother the saving.
        try {
            $value = protected_area_record($pdo, (int) $p['user_id'], (int) $p['id']);
            if ($value === null) {
                // No longer a find — someone took it out in the meantime.
                // Not an error, just nothing to do.
                continue;
            }
            $value === '' ? $without++ : $recorded++;
        } catch (Throwable $e) {
            $failed++;
        }

        usleep(PROTECTION_PAUSE_MS * 1000);
    }

    return compact('recorded', 'without', 'failed');
}

/**
 * Backfill one chunk. Must run BEFORE any output.
 *
 * @return array|null  Message as [kind, text], or null if nothing ran
 */
function protected_areas_process(PDO $pdo): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'protected-areas') {
        return null;
    }
    csrf_check();

    if (protected_areas_find_condition($pdo) === null) {
        return ['hint', t('Es gibt keine Artenlisten — also auch keine Funde, für die das gälte.')];
    }

    ['recorded' => $recorded, 'without' => $without, 'failed' => $failed]
        = protected_areas_run($pdo, microtime(true) + PROTECTION_SECONDS);

    $parts = [];
    if ($recorded)   $parts[] = t('%d Funde mit Schutzgebiet vermerkt', $recorded);
    if ($without)       $parts[] = t('%d liegen in keinem', $without);
    if ($failed) $parts[] = t('%d fehlgeschlagen (kommen beim nächsten Lauf wieder dran)', $failed);

    $openCount = protected_areas_pending($pdo);
    return [
        $openCount === 0 ? 'success' : 'hint',
        ($parts === [] ? t('Nichts zu tun.') : implode(' · ', $parts))
            . ($openCount === 0 ? ' — ' . t('fertig.') : ' · ' . t('noch %d offen', $openCount)),
    ];
}

/** The view. $message comes from protected_areas_process(). */
function protected_areas_render(PDO $pdo, ?array $message): void
{
    $openCount = protected_areas_pending($pdo);
    $b     = protected_areas_find_condition($pdo);

    $counts = ['inside' => 0, 'outside' => 0, 'open' => $openCount, 'no_find' => 0];
    if ($b !== null) {
        $stmt = $pdo->prepare(
            "SELECT
                SUM(p.protected_area IS NOT NULL AND p.protected_area <> '') AS inside,
                SUM(p.protected_area = '')                                  AS outside
               FROM map_points p
              WHERE p.deleted_at IS NULL AND {$b[0]}"
        );
        $stmt->execute($b[1]);
        $row = $stmt->fetch();
        $counts['inside']  = (int) ($row['inside'] ?? 0);
        $counts['outside'] = (int) ($row['outside'] ?? 0);

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM map_points p
              WHERE p.deleted_at IS NULL AND NOT {$b[0]}"
        );
        $stmt->execute($b[1]);
        $counts['no_find'] = (int) $stmt->fetchColumn();
    } else {
        $counts['no_find'] = (int) $pdo->query(
            'SELECT COUNT(*) FROM map_points WHERE deleted_at IS NULL')->fetchColumn();
    }

    // The anchor in the target: without it, every chunk lands you on the
    // first tab — in a process that submits itself a hundred times.
    $target = url('pages/administration.php') . '#protected-areas';
    ?>

<p class="hint-text">
    <?= h(t('Ein Fund holt sein Schutzgebiet beim Anlegen selbst. Hier wird es für die nachgetragen, die daran vorbeikamen: importierte Funde und solche, die erst später in eine Artenliste einsortiert wurden. Gefragt wird beim Bundesamt für Naturschutz, in Häppchen von %d Sekunden. Ein Fund kostet drei Anfragen — je Gebietsart eine —, und zwischen allen liegt eine Pause: Der Dienst gehört jemand anderem, also gehen gut anderthalb Anfragen je Sekunde hinaus und nicht so viele wie möglich. Rechne mit etwa zwei Sekunden je Fund. Der Lauf darf jederzeit unterbrochen und wieder gestartet werden; zum Anhalten genügt es, die Seite zu verlassen.', PROTECTION_SECONDS)) ?>
    <?= t('Für große Bestände erledigt %s dasselbe unbeaufsichtigt — wie man es einrichtet, steht im Kopf der Datei.', '<code>cronjobs/every_5_minutes.php</code>') ?>
</p>

<?php if ($message !== null) { notice($message[0], $message[1]); } ?>

<div class="table-frame">
    <table class="list">
        <tr><th><?= h(t('Funde in einem Schutzgebiet')) ?></th><td><?= $counts['inside'] ?></td></tr>
        <tr><th><?= h(t('Funde außerhalb (geprüft)')) ?></th><td><?= $counts['outside'] ?></td></tr>
        <tr><th><?= h(t('Funde noch offen')) ?></th><td><?= $counts['open'] ?></td></tr>
        <?php /* Not a gap but intent: only finds get the note (the maintainer's
             call, 14.08.2026) — a placed waymark does not need it and
             triggers no request either. */ ?>
        <tr><th><?= h(t('Punkte außerhalb der Artenlisten')) ?></th><td><?= $counts['no_find'] ?> (<?= h(t('wird nicht gefragt')) ?>)</td></tr>
    </table>
</div>

<form method="post" id="protected-area-form" action="<?= h($target) ?>" style="margin-top:1.5rem">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="protected-areas">
    <button class="button" type="submit"><?= h($openCount > 0 ? t('Nachtragen (%d offen)', $openCount) : t('Noch einmal prüfen')) ?></button>
    <?php if ($openCount > 0): ?>
        <label class="checkbox" style="margin-top:.8rem">
            <input type="checkbox" id="protected-area-auto" checked>
            <span><?= h(t('Von selbst weitermachen, bis alle geprüft sind')) ?></span>
        </label>
    <?php endif; ?>
</form>

<?php if ($message !== null && $openCount > 0): ?>
<script>
/*
 * Continue, but only mid-run — the same rule as with the elevations: the
 * first request comes from the button, every further one from here.
 * Otherwise every visit to the administration would start querying a
 * foreign service unasked.
 */
if (document.getElementById('protected-area-auto')?.checked) {
    setTimeout(() => document.getElementById('protected-area-form').submit(), 800);
}
</script>
<?php endif; ?>

    <?php
}
