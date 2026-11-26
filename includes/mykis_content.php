<?php
/**
 * The DGfM delivery in the administration — the state coordinators, the
 * tick for each one who agreed, and the button beside the cron job
 * (migration 0127, includes/mykis.php).
 *
 * THE TICK WAS ASKED FOR (25.09.2026): „ich würde alle anschreiben und
 * mal nachfragen, und dann eben einen Haken setzen bei denen, die
 * zugestimmt haben". The list itself is the DGfM's, taken from its page
 * with the role addresses it publishes; nobody without a tick gets a mail.
 *
 * THE SAME LOOP AS THE CRON JOB (cronjobs/daily.php), the rule of
 * cronjobs/: the button runs mykis_deliver() for everything that waits,
 * the cron job for what was there on the 1st of June or December.
 */

declare(strict_types=1);

require_once __DIR__ . '/mykis.php';

/**
 * @return array{0: string, 1: string}|null  a toast — kind and text
 */
function mykis_process(PDO $pdo, int $userId): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'mapping') {
        return null;
    }
    csrf_check();
    if (!mykis_ready($pdo)) {
        return ['error', t('Die Kartierung braucht die Migration 0127 — sie ist noch nicht eingespielt.')];
    }

    switch ((string) ($_POST['action'] ?? '')) {
        case 'consent':
            // The hidden 0 stands before the box: an unticked box sends
            // nothing, and „nothing" must mean „no", not „no change".
            $on = ($_POST['consented'] ?? '0') === '1';
            $pdo->prepare('UPDATE map_mykis_coordinators SET consented_at = '
                . ($on ? 'COALESCE(consented_at, NOW())' : 'NULL') . ' WHERE id = ?')
                ->execute([(int) ($_POST['coordinator'] ?? 0)]);
            return ['success', $on ? t('Zustimmung eingetragen.') : t('Zustimmung entfernt.')];

        /* THE SAMPLE TO ONESELF (25.09.2026: „erstmal so eine
           Mail an meine Adresse, so wie wenn ich der Landeskoordinator von
           Hessen wäre"). Only ever to the administrator pressing the button
           — the address is read from the account, never taken from the
           form. Twenty invented finders, marked „[Beispiel]". */
        case 'sample':
            require_once __DIR__ . '/mail.php';
            require_once __DIR__ . '/mykis_sample.php';
            $to = (string) $pdo->query('SELECT email FROM map_users WHERE id = ' . $userId)->fetchColumn();
            $mails = mykis_sample_mails($pdo);
            foreach ($mails as $i => $mail) {
                if ($i > 0) {
                    usleep(MYKIS_PAUSE_MS * 1000);
                }
                if (!mail_send($to, $mail['subject'], $mail['body'], $mail['options'])) {
                    return ['error', t('Die Beispielmail ließ sich nicht senden.')];
                }
            }
            return ['success', t('%d Beispielmails sind an %s unterwegs.', count($mails), $to)];

        /* MYKIS' OWN LISTS — Wirt and substrate words — uploaded once,
           kept on the server only (mykis_hosts.php says why). */
        case 'lists':
            $upload = $_FILES['lists'] ?? null;
            $text = is_array($upload) && ($upload['error'] ?? 1) === UPLOAD_ERR_OK
                ? (string) file_get_contents((string) $upload['tmp_name']) : '';
            $counts = $text === '' ? null : mykis_lists_store($text);
            return $counts === null
                ? ['error', t('Das ist keine Wirtliste aus MykIS — es wurde nichts übernommen.')]
                : ['success', t('Wirtliste übernommen: %d Wirte und %d Substrate.', $counts['hosts'], $counts['organ'])];

        case 'deliver':
            $done = mykis_deliver($pdo, null, microtime(true) + 60);
            if ($done['mails'] === 0 && $done['confirmations'] === 0 && $done['failed'] === 0) {
                return ['hint', t('Es wartet nichts.')];
            }
            $text = $done['mails'] === 0 ? '' : ($done['mails'] === 1
                ? t('Eine Mail mit %d Funden ist unterwegs.', $done['finds'])
                : t('%d Mails mit %d Funden sind unterwegs.', $done['mails'], $done['finds']));
            if ($done['confirmations'] > 0) {
                $text = trim($text . ' ' . t('%d Kopie(n) an Finder sind unterwegs.', $done['confirmations']));
            }
            if ($done['failed'] > 0) {
                $text .= ' ' . t('%d Mail(s) ließen sich nicht senden — sie warten auf den nächsten Lauf.', $done['failed']);
            }
            if ($done['open'] > 0) {
                $text .= ' ' . t('%d weitere warten noch — die Zeit war um.', $done['open']);
            }
            return [$done['failed'] > 0 ? 'error' : 'success', $text];
    }
    return null;
}

function mykis_render(PDO $pdo, ?array $message): void
{
    $target = url('pages/administration.php') . '#mapping';
    if ($message !== null) {
        notice($message[0], $message[1], true);
    }
    if (!mykis_ready($pdo)) {
        notice('hint', t('Die Kartierung braucht die Migration 0127 — sie ist noch nicht eingespielt.'));
        return;
    }

    $coordinators = mykis_coordinators($pdo);
    $delivered = [];
    foreach ($pdo->query('SELECT coordinator_id, COUNT(*) AS finds, MAX(delivered_at) AS last
                            FROM map_mykis_deliveries GROUP BY coordinator_id') as $row) {
        $delivered[(int) $row['coordinator_id']] = $row;
    }
    $finders = (int) $pdo->query('SELECT COUNT(*) FROM map_users
        WHERE mykis_consent_at IS NOT NULL AND email_confirmed_at IS NOT NULL AND locked_at IS NULL')->fetchColumn();
    $pending = mykis_pending($pdo, null);
    $waiting = 0;
    foreach ($pending as $address) {
        foreach ($address['finders'] as $entry) {
            $waiting += count($entry['points']);
        }
    }
    $unconfirmed = mykis_confirm_ready($pdo) ? (int) $pdo->query(
        'SELECT COUNT(DISTINCT p.user_id) FROM map_mykis_deliveries d JOIN map_points p ON p.id = d.point_id
          WHERE d.confirmed_at IS NULL')->fetchColumn() : 0;
    ?>
<p class="hint-text">
    <?= h(t('Zweimal im Jahr, Anfang Juni und Anfang Dezember, gehen die eigenen Pilzfunde der Finder, die eingewilligt haben, an die Landeskoordination ihres Bundeslandes — als Tabellen für MykIS, jeder Fund nur einmal, so genau, wie jeder Finder es gewählt hat: ohne Wahl nur der Viertelquadrant des Messtischblatts, auf Wunsch feiner bis zu genauen Koordinaten, die die DGfM nicht veröffentlicht. Eine Sammelmail je Landeskoordination, alle Finder in einer gemeinsamen Tabelle — bei sehr vielen Funden in mehreren etwa gleich großen, jeder Finder ganz in einer; danach bekommt jeder Finder, der es angekreuzt hat, eine Kopie seiner Tabellen. Antworten der Koordination gehen an den ersten Administrator.')) ?>
</p>
<p class="hint-text">
    <?= h(t('Es bekommt nur, wer zugestimmt hat: den Haken setzt du, sobald die Zusage da ist. Unbeaufsichtigt läuft der Versand über cronjobs/daily.php — höchstens 20 Mails je Aufruf, der Rest beim nächsten; außerhalb von Juni und Dezember tut er nichts.')) ?>
</p>

<div class="table-frame">
    <table class="list">
        <thead>
        <tr>
            <th><?= h(t('Land')) ?></th>
            <th><?= h(t('Koordination')) ?></th>
            <th><?= h(t('E-Mail')) ?></th>
            <th><?= h(t('Zugestimmt')) ?></th>
            <th><?= h(t('Geliefert')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($coordinators as $c):
            $state = (MYKIS_STATES[$c['region']] ?? $c['region']) . ($c['part'] ? ' (' . $c['part'] . ')' : '');
            $done = $delivered[(int) $c['id']] ?? null; ?>
            <tr>
                <td><?= h($state) ?>
                    <?php if ($c['part']): ?>
                        <br><span class="hint-text small"><?= h(t('Grenze Nord/Süd noch offen — Funde von hier warten.')) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= h($c['name']) ?><?php if ($c['deputy']): ?><br><span class="hint-text small"><?= h(t('Vertretung: %s', $c['deputy'])) ?></span><?php endif; ?></td>
                <td><a href="mailto:<?= h($c['email']) ?>"><?= h($c['email']) ?></a></td>
                <td>
                    <form method="post" action="<?= h($target) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="mapping">
                        <input type="hidden" name="action" value="consent">
                        <input type="hidden" name="coordinator" value="<?= (int) $c['id'] ?>">
                        <input type="hidden" name="consented" value="0">
                        <input type="checkbox" name="consented" value="1" class="mykis-consent"
                               aria-label="<?= h(t('%s hat zugestimmt', $state)) ?>"
                               <?= $c['consented_at'] !== null ? 'checked' : '' ?>>
                    </form>
                </td>
                <td><?= $done === null ? '—' : h(t('%d · zuletzt %s', (int) $done['finds'], date('d.m.Y', strtotime((string) $done['last'])))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="column-header">
    <h3><?= h($waiting === 1
        ? t('Wartend: ein Fund')
        : t('Wartend: %d Funde von %d eingewilligten Findern', $waiting, $finders))
        . ($unconfirmed > 0 ? ' · ' . t('%d Kopie(n) offen', $unconfirmed) : '') ?></h3>
    <form method="post" action="<?= h($target) ?>"
          data-confirm="<?= h(t('Die wartenden Funde gehen jetzt per Mail an die Landeskoordination, die zugestimmt hat — jeder Fund nur einmal; was gesendet ist, lässt sich nicht zurückholen.')) ?>"
          data-confirm-button="<?= h(t('Jetzt senden')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="mapping">
        <input type="hidden" name="action" value="deliver">
        <button class="button small subtle" type="submit" <?= $waiting === 0 && $unconfirmed === 0 ? 'disabled' : '' ?>><?= h(t('Jetzt senden')) ?></button>
    </form>
</div>
<div class="column-header">
    <p class="hint-text"><?= h(t('Wie die Mail an eine Landeskoordination aussieht: ein Beispiel mit zwanzig erfundenen Findern in Hessen, an deine eigene Adresse, als „[Beispiel]“ markiert.')) ?></p>
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="mapping">
        <input type="hidden" name="action" value="sample">
        <button class="button small subtle" type="submit"><?= h(t('Beispiel an mich senden')) ?></button>
    </form>
</div>
<?php $lists = mykis_lists(); ?>
<div class="column-header">
    <p class="hint-text"><?= h($lists === null
        ? t('MykIS nimmt bei „Wirt“ und „organ_substrat“ nur Namen aus seinen eigenen Listen an. Ohne diese Listen steht das Feld „Substrat / Wirt“ deshalb in „substrat_text“, dort nimmt MykIS alles. Mit den Listen kommt ein Wirt wie „Fagus sylvatica“ in „Wirt“ und ein Wort wie „Laubstreu“ in „organ_substrat“.')
        : t('Wirtliste aus MykIS: %d Wirte und %d Substrate. Ein Wirt wie „Fagus sylvatica“ kommt in „Wirt“, ein Wort wie „Laubstreu“ in „organ_substrat“, alles andere in „substrat_text“.', $lists['counts']['hosts'], $lists['counts']['organ'])) ?></p>
    <form method="post" action="<?= h($target) ?>" enctype="multipart/form-data" class="mykis-lists">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="mapping">
        <input type="hidden" name="action" value="lists">
        <label class="button small subtle">
            <?= h($lists === null ? t('Wirtliste hochladen') : t('Neue Wirtliste hochladen')) ?>
            <input type="file" name="lists" accept=".json,application/json" hidden>
        </label>
    </form>
</div>
<p class="hint-text small provider-currency"><?= h(t('Liste nach dgfm-ev.de, Landeskoordinatoren für Pilzkartierung — Stand 25.09.2026.')) ?></p>

<script>
/* The tick submits itself — a save button per row would be seventeen
   buttons for one decision at a time. */
for (const box of document.querySelectorAll('.mykis-consent')) {
    box.addEventListener('change', () => box.form.requestSubmit());
}
// The file sends itself once chosen — the same: one step, no second button.
for (const file of document.querySelectorAll('.mykis-lists input[type=file]')) {
    file.addEventListener('change', () => file.files.length && file.form.requestSubmit());
}
</script>
    <?php
}
