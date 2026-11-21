<?php
/**
 * Backfilling species — processing and view.
 *
 * New and changed points write their species into map_point_species themselves
 * on save. Everything created before migration 0036 has no rows yet — and
 * whoever marks list fields as species fields after the fact, or moves a
 * list elsewhere, changes what counts without any point being touched.
 *
 * THAT IS WHY THE SECOND BUTTON HERE IS NOT THE RARE CASE, but the normal
 * one: the table is fully derived and may be rebuilt at any time. There is
 * nothing in it that is not stored elsewhere.
 *
 * NO CHUNKING. Per point it is a few rows and one lookup into the name
 * list; with ten thousand points that takes seconds. A progress display
 * would be more work than the work itself.
 */

declare(strict_types=1);

require_once __DIR__ . '/point_species.php';

/**
 * Backfill. Must run BEFORE any output.
 *
 * @return array|null Message as [kind, text], or null if nothing ran
 */
function point_species_process(PDO $pdo): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'species') {
        return null;
    }
    csrf_check();

    if (!point_species_ready($pdo)) {
        return ['error', t('Migration 0036 ist noch nicht eingespielt.')];
    }

    try {
        ['points' => $points, 'names' => $names, 'linked' => $linked]
            = point_species_rebuild($pdo);
    } catch (Throwable $e) {
        return ['error', t('Nachtragen gescheitert: %s', $e->getMessage())];
    }

    return ['success', ($points === 1 ? t('%d Punkt durchgesehen', $points) : t('%d Punkte durchgesehen', $points))
        . ', ' . ($names === 1 ? t('%d Name eingetragen', $names) : t('%d Namen eingetragen', $names))
        . ', ' . ($linked === 1 ? t('%d Titel-Verweis angepasst', $linked) : t('%d Titel-Verweise angepasst', $linked)) . '.'];
}

/**
 * Re-derive everything — the shared core of the tab's button and the cron
 * job (cronjobs/hourly.php).
 *
 * ALWAYS EVERYTHING, and only for that reason is there a single button in
 * the tab.
 *
 * The first draft had two — "backfill" and "re-derive" — like the map
 * sheets. That did not work out: "not yet examined" could only be
 * recognized by a point having no row, and that is equally true of every
 * point that simply carries no species. After one complete run, the
 * button would thus have offered the same number forever, inviting a
 * click that does nothing.
 *
 * ALL POINTS, INCLUDING THE DELETED ONES — as with the map sheet. A
 * point from the trash can be restored and would otherwise stand there
 * without species.
 *
 * @return array{points: int, names: int, linked: int}
 */
function point_species_rebuild(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT p.id, p.user_id FROM map_points p');

    $points = 0;
    $names  = 0;
    $linked = 0;
    foreach ($stmt as $p) {
        // The title's reference first, the rows after it — the rows
        // carry source and identifier out of exactly what this writes.
        // This is also the catch-up for the whole legacy stock: finds
        // whose title was typed or moved in before the server resolved
        // references get theirs on this very run.
        if (title_references_sync($pdo, (int) $p['user_id'], (int) $p['id'])) {
            $linked++;
        }
        $names += point_species_set($pdo, (int) $p['user_id'], (int) $p['id']);
        $points++;
    }

    return compact('points', 'names', 'linked');
}

function point_species_render(PDO $pdo, ?array $message): void
{
    if (!point_species_ready($pdo)) {
        notice('hint', t('Migration 0036 ist noch nicht eingespielt — bis dahin gibt es keine Artentabelle.'));
        return;
    }

    // Over living points only: what lies in the trash does not belong in a
    // tally of what one knows.
    $counts = $pdo->query(
        'SELECT
            COUNT(*)                                        AS entries,
            COUNT(DISTINCT a.name)                          AS names,
            COUNT(DISTINCT NULLIF(a.genus, ""))           AS genera,
            COUNT(DISTINCT a.point_id)                      AS points,
            SUM(a.identifier <> "")                            AS with_reference
           FROM map_point_species a
           JOIN map_points p ON p.id = a.point_id AND p.deleted_at IS NULL'
    )->fetch();

    $total = (int) $pdo->query(
        'SELECT COUNT(*) FROM map_points WHERE deleted_at IS NULL'
    )->fetchColumn();

    // WHAT ANYTHING CAN ARISE FROM AT ALL. Without this number, "1 of 151"
    // reads like a bug, when it is in fact the correct answer: only one list
    // carries is_species, and three points sit in it. Whoever does not see
    // that alongside goes hunting for the bug in the derivation instead of
    // in the marking.
    $fromSpeciesLists = (int) $pdo->query(
        'SELECT COUNT(DISTINCT pl.point_id)
           FROM map_point_lists pl
           JOIN map_lists l ON l.id = pl.list_id AND l.is_species = 1
           JOIN map_points p ON p.id = pl.point_id AND p.deleted_at IS NULL'
    )->fetchColumn();

    // The most frequent genera — at the same time the check that the
    // derivation produced something sensible. A list full of German names
    // or substrates would mean the fields are marked wrong.
    $genera = $pdo->query(
        'SELECT a.genus, COUNT(DISTINCT a.point_id) AS n
           FROM map_point_species a
           JOIN map_points p ON p.id = a.point_id AND p.deleted_at IS NULL
          WHERE a.genus <> ""
          GROUP BY a.genus
          ORDER BY n DESC, a.genus
          LIMIT 12'
    )->fetchAll();

    $target = url('pages/administration.php') . '#species';
    ?>

<p class="hint-text">
    <?= h(t('Artnamen stehen am Punkt als Text — im Titel und in den Feldern, die als Artenfeld gekennzeichnet sind. Diese Tabelle stellt sie zusätzlich einzeln ab, mit Gattung und Verweis. Erst dadurch werden Fragen wie „alle Boletus" oder „wie viele Arten kenne ich" beantwortbar. Sie ist vollständig abgeleitet und lässt sich jederzeit neu bauen.')) ?>
    <?= t('%s erledigt dasselbe unbeaufsichtigt, jede Stunde — wie man es einrichtet, steht im Kopf der Datei.', '<code>cronjobs/hourly.php</code>') ?>
</p>

<?php if ($message !== null) { notice($message[0], $message[1]); } ?>

<div class="table-frame">
    <table class="list">
        <tr><th><?= h(t('Einträge')) ?></th><td><?= (int) $counts['entries'] ?></td></tr>
        <tr><th><?= h(t('verschiedene Namen')) ?></th><td><?= (int) $counts['names'] ?></td></tr>
        <tr><th><?= h(t('verschiedene Gattungen')) ?></th><td><?= (int) $counts['genera'] ?></td></tr>
        <tr><th><?= h(t('Punkte mit mindestens einer Art')) ?></th><td><?= (int) $counts['points'] ?></td></tr>
        <tr><th><?= h(t('davon mit Verweis in die Namensliste')) ?></th><td><?= (int) $counts['with_reference'] ?></td></tr>
        <tr><th><?= h(t('Punkte in einer Liste, deren Titel Arten sind')) ?></th><td><?= $fromSpeciesLists ?></td></tr>
        <tr><th><?= h(t('Punkte insgesamt')) ?></th><td><?= $total ?></td></tr>
    </table>
</div>

<?php if ($genera !== []) { ?>
<h3 style="margin-top:1.5rem"><?= h(t('Häufigste Gattungen')) ?></h3>
<div class="table-frame">
    <table class="list">
        <?php foreach ($genera as $g) { ?>
        <tr><th><?= h((string) $g['genus']) ?></th><td><?= h(t('%d Punkte', (int) $g['n'])) ?></td></tr>
        <?php } ?>
    </table>
</div>
<?php } ?>

<?php /* ONE BUTTON, and it always rebuilds everything.
     No confirmation: nothing is lost that would not come back into being the
     very same second — every row stems from the points' titles, fields and
     references. And because that is so, "rebuild" is also the right move
     after a field has been declared a species field or a list has been moved:
     then which names count changes without any point being touched. */ ?>
<form method="post" action="<?= h($target) ?>" style="margin-top:1.5rem">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="species">
    <button type="submit" class="button"><?= h(t('Arten aus allen Punkten neu ableiten')) ?></button>
</form>
    <?php
}
