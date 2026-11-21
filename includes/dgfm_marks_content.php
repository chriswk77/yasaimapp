<?php
/**
 * The DGfM lists in the administration — processing and view.
 *
 * Under Nachtragen → Arten, below the species table: one button reads
 * data/dgfm/marks.csv and rebuilds map_dgfm_marks (dgfm_marks_import),
 * and the view says what came of it — per list how many rows and how
 * many found their taxon, and BY NAME what did not. The unmatched names
 * are the whole point of showing anything here: a PDF written for people
 * spells a species the way its authors do (Agaricus silvaticus where the
 * reference says sylvaticus, Tubera where it says Tuber), and only a
 * person can say whether that is a typo worth an alias or a species
 * Germany does not have.
 *
 * NO CHUNKING: four hundred and fifty rows, one lookup each.
 */

declare(strict_types=1);

require_once __DIR__ . '/dgfm_marks.php';

/**
 * Import. Must run BEFORE any output.
 *
 * @return array|null Message as [kind, text], or null if nothing ran
 */
function dgfm_marks_process(PDO $pdo): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'dgfm_marks') {
        return null;
    }
    csrf_check();

    if (!dgfm_marks_ready($pdo)) {
        return ['error', t('Migration 0108 ist noch nicht eingespielt.')];
    }

    try {
        $r = dgfm_marks_import($pdo);
    } catch (Throwable $e) {
        return ['error', t('Einlesen gescheitert: %s', $e->getMessage())];
    }

    return ['success', t('%d Zeilen gelesen, %d Arten und %d Gattungen zugeordnet (%d davon über die Schreibweisen-Tabelle), %d Gattungszeilen meinen nur einen Teil und setzen keine Marke, %d Namen nicht gefunden.',
        $r['rows'], $r['matched'] - $r['genera'], $r['genera'], $r['aliased'], $r['subsets'], count($r['unmatched']))];
}

function dgfm_marks_render(PDO $pdo, ?array $message): void
{
    ?>
    <h3 style="margin-top:2.4rem"><?= h(t('DGfM-Listen')) ?></h3>
    <?php
    if (!dgfm_marks_ready($pdo)) {
        notice('hint', t('Migration 0108 ist noch nicht eingespielt — bis dahin gibt es keine DGfM-Marken.'));
        return;
    }
    if ($message !== null) {
        notice($message[0], $message[1], float: true);
    }

    // Per list: rows, matched, the source's date — and the names that
    // found no taxon, in the source's own spelling.
    $stmt = $pdo->query(
        "SELECT list, COUNT(*) AS rows_n,
                SUM(matched <> 'none') AS matched_n,
                SUM(matched = 'genus') AS genera_n,
                MAX(source_date) AS source_date
           FROM map_dgfm_marks GROUP BY list"
    );
    $per = [];
    foreach ($stmt as $z) {
        $per[$z['list']] = $z;
    }
    $unmatched = $pdo->query(
        "SELECT list, name FROM map_dgfm_marks WHERE matched = 'none' ORDER BY list, name"
    )->fetchAll();
    $subsets = dgfm_marks_subset_ready($pdo) ? $pdo->query(
        "SELECT list, name, remark FROM map_dgfm_marks WHERE matched = 'subset' ORDER BY list, name"
    )->fetchAll() : [];
    $csv = dgfm_marks_csv();
    ?>
    <p class="hint-text"><?= t('Die vier Listen des DGfM-Fachausschusses Pilzverwertung und Toxikologie und die Verantwortungsarten — als Marken an der Art, für den Filter (Speisewert, Verantwortungsart), das Punktfenster und den Fundbericht. Die Namen werden gegen die Pilzreferenz aufgelöst; was nicht passt, steht unten.') ?></p>

    <?php if ($per === []): ?>
        <p class="hint-text"><?= t('Noch nichts eingelesen.') ?></p>
    <?php else: ?>
        <table class="list">
            <thead><tr>
                <th><?= h(t('Liste')) ?></th>
                <th><?= h(t('Stand')) ?></th>
                <th><?= h(t('Zeilen')) ?></th>
                <th><?= h(t('zugeordnet')) ?></th>
                <th><?= h(t('davon Gattungen')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach (DGFM_LISTS as $list => $word): $z = $per[$list] ?? null; ?>
                <tr>
                    <td><?= h(t($word)) ?></td>
                    <td><?= h($z && $z['source_date'] ? date('d.m.Y', strtotime((string) $z['source_date'])) : '—') ?></td>
                    <td><?= $z ? (int) $z['rows_n'] : 0 ?></td>
                    <td><?= $z ? (int) $z['matched_n'] : 0 ?></td>
                    <td><?= $z ? (int) $z['genera_n'] : 0 ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php $probe = dgfm_marks_probe($pdo); ?>
        <p class="hint-text"><?= t('Probe: Boletus edulis an der Referenz → %s. Funde, deren Titelart eine Marke trägt: %d von %d mit Artverweis.',
            $probe['sample'] !== '' ? $probe['sample'] : t('keine Marke'), $probe['marked'], $probe['referenced']) ?></p>
        <?php if ($subsets !== []): ?>
            <p class="hint-text"><?= t('Gattungszeilen, die laut ihrer Bemerkung nur einen Teil der Gattung meinen — sie setzen keine Marke, denn die Liste nennt die Arten nicht:') ?></p>
            <ul class="hint-text">
                <?php foreach ($subsets as $z): ?>
                    <li><?= h($z['name']) ?> (<?= h(t(DGFM_LISTS[$z['list']] ?? $z['list'])) ?>): <?= h((string) $z['remark']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($unmatched !== []): ?>
            <p class="hint-text"><?= t('Nicht in der Pilzreferenz gefunden — die Schreibweise der DGfM, wie sie in der Liste steht. Diese Namen gelten über den Fundtitel: Beginnt der Titel eines Fundes mit dem Namen, trägt er die Marke trotzdem.') ?></p>
            <ul class="hint-text">
                <?php foreach ($unmatched as $z): ?>
                    <li><?= h($z['name']) ?> (<?= h(t(DGFM_LISTS[$z['list']] ?? $z['list'])) ?>)</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" class="button-row" style="margin-top:1rem">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="dgfm_marks">
        <button type="submit" class="button"><?= h(t('Listen einlesen')) ?></button>
        <span class="hint-text"><?= is_file($csv)
            ? t('aus %s, Datei vom %s', 'data/dgfm/marks.csv', date('d.m.Y', (int) filemtime($csv)))
            : t('data/dgfm/marks.csv fehlt') ?></span>
    </form>
    <?php
}
