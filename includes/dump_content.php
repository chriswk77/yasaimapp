<?php
/**
 * Database dump — the view.
 *
 * Nothing is processed here: the dump itself comes from admin/dump.php,
 * because a file and a page cannot fall out of the same request. This piece
 * only shows what would come along and what would not — and does so
 * BEFOREHAND, because the only question one has for a dump is "is the thing
 * I need in there?".
 */

declare(strict_types=1);

require_once __DIR__ . '/dump.php';

function dump_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', '.') . ' kB';
    }
    return $bytes . ' B';
}

function dump_render(PDO $pdo): void
{
    $tables = dump_tables($pdo);

    $included = array_filter($tables, static fn($t) => $t['included']);
    $excluded = array_filter($tables, static fn($t) => !$t['included']);

    $bytesIncluded = array_sum(array_column($included, 'bytes'));
    $bytesExcluded = array_sum(array_column($excluded, 'bytes'));
    ?>

<p class="hint-text">
    <?= h(t('Ein Abzug der eigenen Tabellen als SQL — um örtlich denselben Stand zu haben wie hier. Die Datei wird unmittelbar heruntergeladen und liegt zu keinem Zeitpunkt auf dem Server.')) ?>
</p>

<p class="hint-text">
    <strong><?= h(t('Die Taxonomie bleibt draußen')) ?></strong> —
    <?= h(t('%s gegen %s für alles andere.', dump_size($bytesExcluded), dump_size($bytesIncluded))) ?>
    <?= h(t('Sie entsteht örtlich und wird von dort hochgespielt; sie zurückzuholen hieße, stundenlang abzuwarten, um zu bekommen, was man schon hat.')) ?>
    <?= t('Wer sie wirklich neu braucht, nimmt %s und den Reiter „Taxonomie einspielen".', '<code>admin/tax_export.php</code>') ?>
</p>

<div class="table-frame">
    <table class="list">
        <thead>
            <tr><th><?= h(t('Tabelle')) ?></th><th><?= h(t('Zeilen')) ?></th><th><?= h(t('Größe')) ?></th><th><?= h(t('im Abzug')) ?></th></tr>
        </thead>
        <tbody>
        <?php foreach ($tables as $name => $t): ?>
            <tr>
                <td><code><?= h($name) ?></code></td>
                <?php /* From information_schema and therefore an estimate. That
                     needs saying: whoever takes the number at face value and
                     compares it with a COUNT(*) will otherwise hunt for a bug
                     that does not exist. */ ?>
                <td style="text-align:right">~<?= number_format($t['rows'], 0, ',', '.') ?></td>
                <td style="text-align:right"><?= h(dump_size($t['bytes'])) ?></td>
                <td><?= $t['included'] ? h(t('ja')) : h(t('nein — %s', $t['reason'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<form method="post" action="<?= h(url('admin/dump.php')) ?>" style="margin-top:1.5rem">
    <?= csrf_field() ?>
    <button class="button" type="submit"><?= h(t('Abzug herunterladen')) ?></button>
</form>

<h3 style="margin-top:2rem"><?= h(t('Örtlich einspielen')) ?></h3>
<p class="hint-text">
    <?= t('Die aufgeführten Tabellen werden dabei %s — was örtlich darin steht, ist danach weg.', '<strong>' . h(t('geleert und neu angelegt')) . '</strong>') ?>
    <?= h(t('Die Taxonomietabellen rührt der Abzug nicht an, die bleiben stehen.')) ?>
</p>
<pre><code><?= h(t('mysql -u BENUTZER -p DATENBANK < karte-JJJJ-MM-TT-hhmm.sql')) ?></code></pre>
<p class="hint-text">
    <?php /* The note about the migrations is not an aside: the dump brings
         map_migrations along, and afterwards the server's state stands
         locally. Whoever developed a migration here that has not yet run
         there finds it marked open again afterwards — that is correct, but
         at first glance it looks like a loss. */ ?>
    <?= t('Danach steht örtlich auch %s auf dem Stand dieses Servers. Migrationen, die es nur örtlich schon gab, gelten dann wieder als offen und laufen beim nächsten Einspielen erneut.', '<code>map_migrations</code>') ?>
</p>

    <?php
}
