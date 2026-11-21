<?php
/**
 * Importing the taxonomy tables — processing and view.
 *
 * LINKED AGAIN, and deliberately so. The head of the old page said the
 * opposite: not linked, because the transfer is a one-off and a button you
 * never need again only invites accidental clicking. The thought was right,
 * the means too crude — it hid the whole process to protect a single
 * action.
 *
 * For what is dangerous here is not the importing. That can be triggered as
 * often as you like: it recounts what is already in and continues there.
 * Dangerous is the EMPTYING — a TRUNCATE after which 400,000 rows have to
 * be shoveled back in. The confirmation therefore sits right there and says
 * what it costs, instead of stowing the page as a whole behind an unnamed
 * address.
 *
 * Runs in the browser because the hoster provides no shell — and therefore
 * works in chunks: each call shovels rows for a few seconds and reports
 * back. The tab resubmits itself until everything is in.
 *
 * HOW IT FINDS ITS PLACE AGAIN AFTER AN ABORT
 * It doesn't — it simply recounts. How many rows already sit in the table,
 * the database knows itself; that many lines the file skips, then it
 * continues. No progress record that could drift apart from reality, no
 * file that would need cleaning up. Whoever closes the browser mid-run
 * simply presses "Einspielen" again later.
 *
 * WHAT THE TAB COSTS ON EVERY CALL: five COUNT(*), one per table. Locally
 * that is around 70 ms, most of it for the 400,000 rows of the GBIF table.
 * Count exactly it must: from "roughly 397,000" one could not read off
 * whether anything is still missing, and the whole bookkeeping of this
 * process hangs on that.
 */

declare(strict_types=1);

require_once __DIR__ . '/tax_transfer.php';

/** How long a call works at most. */
const TAX_TRANSFER_SECONDS = 15;

/**
 * Import one chunk or empty a table. Must run BEFORE any output.
 *
 * 'resume' tells the view whether it may resubmit itself. That is a value
 * of its own and not readable off the message, for both possible shortcuts
 * miss:
 *
 *   off the message kind   A chunk reports 'success' as soon as ONE table is
 *                          full — but there are five. The run would stop
 *                          after every finished table.
 *   off "still missing"    After emptying, everything is naturally missing.
 *                          The run would immediately re-import what was
 *                          just removed, and the emptying would be for
 *                          nothing.
 *
 * After an error 'resume' is likewise false. Otherwise a broken transfer —
 * a missing file, a line with the wrong field count — hammers out the same
 * exception every 800 ms until someone closes the window.
 *
 * @return array{message: array{0:string,1:string}, resume: bool}|null
 */
function tax_transfer_process(PDO $pdo): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'taxonomy') {
        return null;
    }
    csrf_check();

    try {
        if (($_POST['action'] ?? '') === 'clear') {
            $table = (string) ($_POST['table'] ?? '');
            if (!in_array($table, tax_transfer_tables(), true)) {
                throw new RuntimeException(t('Unbekannte Tabelle.'));
            }
            $pdo->exec("TRUNCATE TABLE $table");
            return ['message' => ['success', t('%s geleert.', $table)], 'resume' => false];
        }

        return [
            'message' => tax_transfer_chunk($pdo, TAX_TRANSFER_SECONDS),
            'resume'  => true,
        ];
    } catch (Throwable $e) {
        return ['message' => ['error', $e->getMessage()], 'resume' => false];
    }
}

/** The view. $run comes from tax_transfer_process(). */
function tax_transfer_render(PDO $pdo, ?array $run): void
{
    $message = $run['message'] ?? null;
    $directory = tax_transfer_directory();
    $progress = [];
    $hasOpen = false;
    foreach (tax_transfer_tables() as $t) {
        $s = tax_transfer_progress($pdo, $t);
        $s['expected']  = (int) ($directory[$t] ?? 0);
        $s['missing'] = max(0, $s['expected'] - $s['loaded']);
        if ($s['missing'] > 0 && $s['present']) $hasOpen = true;
        $progress[] = $s;
    }

    // The anchor in the target: without it, every chunk lands you on the
    // first tab — and that in a process that submits itself dozens of times.
    $target = url('pages/administration.php') . '#taxonomy';
    ?>

<p class="hint-text">
    <?= h(t('Spielt die Namensverzeichnisse in die Datenbank dieses Servers. Läuft in Häppchen von %d Sekunden und setzt nach einem Abbruch dort fort, wo die Tabelle aufhört — einfach wieder starten. Gebraucht wird das nur, wenn die Verzeichnisse erneuert wurden.', TAX_TRANSFER_SECONDS)) ?>
</p>

<?php if (array_filter($progress, static fn($s) => !$s['present'])): ?>
<p class="hint-text">
    <strong><?= h(t('Dateien fehlen.')) ?></strong>
    <?= h(t('Sie liegen bewusst nicht im Repository — 19 MB, die sich nie ändern, würden jeden Push und jedes Aufspielen belasten.')) ?>
    <?= t('Örtlich mit %s erzeugen und einmalig nach %s auf diesen Server put.', '<code>php admin/tax_export.php --really</code>', '<code>data/taxonomie/uebertrag/</code>') ?>
    <?= h(t('Das Aufspielen fasst dieses Verzeichnis nicht an, die Dateien bleiben also liegen.')) ?>
</p>
<?php endif; ?>

<?php if ($message !== null) { notice($message[0], $message[1]); } ?>

<div class="table-frame">
    <table class="list">
        <tr><th><?= h(t('Tabelle')) ?></th><th><?= h(t('Datei')) ?></th><th><?= h(t('eingespielt')) ?></th><th><?= h(t('fehlt')) ?></th><th></th></tr>
        <?php foreach ($progress as $s): ?>
        <tr>
            <td><?= h($s['table']) ?></td>
            <td><?= $s['present'] ? sprintf('%.1f MB', $s['size'] / 1048576) : '— ' . h(t('fehlt')) ?></td>
            <td><?= number_format($s['loaded'], 0, ',', '.') ?> / <?= number_format($s['expected'], 0, ',', '.') ?></td>
            <td><?= $s['missing'] > 0 ? number_format($s['missing'], 0, ',', '.') : '—' ?></td>
            <td>
                <?php if ($s['loaded'] > 0): ?>
                <?php /* The only action here that breaks something — and since
                     this process is linked again, it is one click away
                     instead of behind a typed-in address. The confirmation
                     therefore states the number: "Leeren?" alone does not
                     say that hours of re-shoveling follow. */ ?>
                <form method="post" action="<?= h($target) ?>"
                      data-confirm="<?= h(t('Die %s Zeilen in %s werden gelöscht. Sie wieder einzuspielen dauert je nach Tabelle Minuten bis Stunden.', number_format($s['loaded'], 0, ',', '.'), $s['table'])) ?>"
                      data-confirm-title="<?= h(t('%s leeren?', $s['table'])) ?>"
                      data-confirm-button="<?= h(t('Leeren')) ?>"
                      data-confirm-danger>
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="taxonomy">
                    <input type="hidden" name="action" value="clear">
                    <input type="hidden" name="table" value="<?= h($s['table']) ?>">
                    <button class="button danger small" type="submit"><?= h(t('Leeren')) ?></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<form method="post" id="taxonomy-form" action="<?= h($target) ?>" style="margin-top:1.5rem">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="taxonomy">
    <button class="button" type="submit"><?= h($hasOpen ? t('Einspielen') : t('Noch einmal prüfen')) ?></button>
    <?php if ($hasOpen): ?>
        <label class="checkbox" style="margin-top:.8rem">
            <input type="checkbox" id="taxonomy-auto" checked>
            <span><?= h(t('Von selbst weitermachen, bis alles drin ist')) ?></span>
        </label>
    <?php endif; ?>
</form>

<?php if (!empty($run['resume']) && $hasOpen): ?>
<script>
/*
 * Continue, but only mid-run — as with backfilling the elevations and for
 * the same reason: on a page of its own, "on load, if checked" sufficed,
 * for whoever opened it wanted to import. As one tab among several, the
 * same condition would mean that EVERY visit to the administration starts
 * shoveling 400,000 rows unasked. When 'resume' holds is stated at
 * tax_transfer_process().
 */
if (document.getElementById('taxonomy-auto')?.checked) {
    setTimeout(() => document.getElementById('taxonomy-form').submit(), 800);
}
</script>
<?php endif; ?>

    <?php
}
