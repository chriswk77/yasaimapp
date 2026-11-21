<?php
/**
 * Backfilling Messtischblätter — processing and view.
 *
 * New points get their sheet on creation, and whoever moves a marker gets
 * it recomputed. Everything that came into being before migration 0033 —
 * taken over from Google, set by hand, imported — has none yet.
 *
 * NO CHUNKING, unlike with the terrain elevations, and that is no
 * oversight: there every point needs a request to a third-party service,
 * here it is two divisions. Ten thousand points in one pass cost
 * fractions of a second; a progress display for that would be more
 * machinery than the work itself.
 *
 * ONLY WHAT IS MISSING GETS BACKFILLED. An existing sheet is not
 * overwritten — not because it could change (it hangs on the coordinate
 * alone), but so a second click touches nothing that is already right.
 * Whoever wants a recompute has the second button for that.
 */

declare(strict_types=1);

require_once __DIR__ . '/mtb.php';

/** Is the migration in? Otherwise there is nothing to do here. */
function mtb_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_points', 'mtb');
}

/** Points without a sheet that could get one. */
function mtb_pending(PDO $pdo): int
{
    if (!mtb_ready($pdo)) {
        return 0;
    }
    return (int) $pdo->query(
        'SELECT COUNT(*) FROM map_points
          WHERE deleted_at IS NULL AND mtb IS NULL
            AND lat BETWEEN ' . MTB_LAT_MIN . ' AND ' . MTB_LAT_MAX . '
            AND lon BETWEEN ' . MTB_LON_MIN . ' AND ' . MTB_LON_MAX
    )->fetchColumn();
}

/**
 * Backfill. Must run BEFORE any output.
 *
 * @return array|null message as [kind, text], or null if nothing ran
 */
function mtb_process(PDO $pdo): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'mtb') {
        return null;
    }
    csrf_check();

    if (!mtb_ready($pdo)) {
        return ['error', t('Migration 0033 ist noch nicht eingespielt.')];
    }

    $all = ($_POST['what'] ?? '') === 'alle';

    // ALL POINTS, THE DELETED ONES TOO. A point in the trash can be
    // brought back and would then stand there without a sheet — leaving
    // it out here would mean it never gets one.
    /* THE COUNTRY COMES ALONG, because the sheet only holds in Germany
       (25.08.2026). The bounding rectangle in mtb.php reaches
       deep into Austria, so a point in Tyrol used to get a number here
       that has never been printed. Where the country is already known,
       it decides; where it is not, the rectangle answers as before. */
    $withCountry = column_exists($pdo, 'map_points', 'country');
    $stmt = $pdo->query(
        'SELECT id, lat, lon' . ($withCountry ? ', country' : '') . ' FROM map_points'
        . ($all ? '' : ' WHERE mtb IS NULL')
    );

    $update = $pdo->prepare('UPDATE map_points SET mtb = ?, mtb_quadrant = ? WHERE id = ?');

    $set     = 0;
    $without = 0;
    $pdo->beginTransaction();
    try {
        foreach ($stmt as $p) {
            $sheet = mtb_from_coordinates((float) $p['lat'], (float) $p['lon'],
                $p['country'] ?? null);
            if ($sheet === null) {
                $without++;
                // When recomputing, also clear: whoever moved a point out
                // of Germany shall not keep the old sheet.
                if ($all) {
                    $update->execute([null, null, (int) $p['id']]);
                }
                continue;
            }
            $update->execute([
                $sheet['sheet'],
                $sheet['quadrant'] . $sheet['q16'] . $sheet['q64'],
                (int) $p['id'],
            ]);
            $set++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['error', t('Nachtragen gescheitert: %s', $e->getMessage())];
    }

    return ['success', ($set === 1 ? t('%d Punkt mit Blatt versehen', $set) : t('%d Punkte mit Blatt versehen', $set))
        . ($without > 0 ? ', ' . t('%d außerhalb Deutschlands', $without) : '') . '.'];
}

function mtb_render(PDO $pdo, ?array $message): void
{
    if (!mtb_ready($pdo)) {
        notice('hint', t('Migration 0033 ist noch nicht eingespielt — bis dahin gibt es keine Blätter.'));
        return;
    }

    $counts = $pdo->query(
        'SELECT
            SUM(mtb IS NOT NULL) AS with_sheet,
            SUM(mtb IS NULL)     AS without_sheet,
            COUNT(DISTINCT mtb)  AS sheets,
            COUNT(*)             AS total
           FROM map_points WHERE deleted_at IS NULL'
    )->fetch();

    $target = url('pages/administration.php') . '#mtb';
    ?>

<p class="hint-text">
    <?= h(t('Das Messtischblatt (TK25) ist die Bezugsgrösse der deutschen Kartierung — 10 Bogenminuten breit, 6 hoch, rund 11 × 11 km. Neue Punkte bekommen es beim Anlegen, verschobene beim Speichern. Hier lässt es sich für ältere Punkte nachtragen.')) ?>
</p>

<?php if ($message !== null) { notice($message[0], $message[1]); } ?>

<div class="table-frame">
    <table class="list">
        <tr><th><?= h(t('Punkte mit Blatt')) ?></th><td><?= (int) $counts['with_sheet'] ?></td></tr>
        <tr><th><?= h(t('ohne Blatt (meist außerhalb Deutschlands)')) ?></th><td><?= (int) $counts['without_sheet'] ?></td></tr>
        <tr><th><?= h(t('verschiedene Blätter')) ?></th><td><?= (int) $counts['sheets'] ?></td></tr>
        <tr><th><?= h(t('Punkte insgesamt')) ?></th><td><?= (int) $counts['total'] ?></td></tr>
    </table>
</div>

<form method="post" action="<?= h($target) ?>" style="margin-top:1.5rem">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="mtb">
    <button type="submit" class="button">
        <?= h(t('%d Punkte ohne Blatt nachtragen', mtb_pending($pdo))) ?>
    </button>
</form>

<?php /* The second button is for the case that something about the calculation
     changes — a corrected constant, a different datum transition. Then
     everything must be redone, not just what is missing. */ ?>
<?php /* data-confirm, not the old data-nachfrage: that attribute has no reader
     since the rename, and the button recalculated everything WITHOUT asking. */ ?>
<form method="post" action="<?= h($target) ?>" style="margin-top:.6rem"
      data-confirm="<?= h(t('Vorhandene Blätter werden überschrieben und neu berechnet.')) ?>"
      data-confirm-title="<?= h(t('Alle Blätter neu berechnen?')) ?>"
      data-confirm-button="<?= h(t('Neu berechnen')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="mtb">
    <input type="hidden" name="what" value="alle">
    <button type="submit" class="button subtle"><?= h(t('Alle neu berechnen')) ?></button>
</form>
    <?php
}
