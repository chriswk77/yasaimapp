<?php
/**
 * Migrations — processing and view.
 *
 * THIS ONE HAS TWO HOMES, unlike the trash and the invitations: as a tab
 * in the administration, and still under admin/migrations.php. That is
 * no oversight but the emergency exit.
 *
 * Because when a migration is missing, the database is not at the
 * program's level — and exactly then the administration's queries can
 * trip over a missing column. If applying lived only there, the only way
 * back would be blocked precisely in the moment it exists for. On the
 * live server there is no shell; then nothing would remain.
 *
 * The standalone page therefore asks only about map_migrations and the
 * files — nothing a pending migration could affect. The error page in
 * bootstrap.php points there too.
 *
 * $target is the difference between the two: where the form goes.
 */

declare(strict_types=1);

require_once __DIR__ . '/migrations.php';

/**
 * Apply open migrations. Must run BEFORE any output.
 *
 * @return array  report per file, empty if nothing was applied
 */
function migrations_process(PDO $pdo): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'migrations') {
        return [];
    }
    csrf_check();

    return migrations_apply($pdo, migrations_pending($pdo));
}

/**
 * The view. $report comes from migrations_process(), $target is the
 * address the form goes to — with an anchor where there are tabs.
 */
function migrations_render(PDO $pdo, array $report, string $target): void
{
    $pending       = migrations_pending($pdo);
    $applied = migrations_applied($pdo);
    ?>

<p class="hint-text">
    <?= t('Dateien aus %s, die noch nicht in %s stehen. Sie laufen in der Reihenfolge ihrer Namen.', '<code>migrations/</code>', '<code>map_migrations</code>') ?>
    <?= h(t('Bei einem Fehler bricht der Vorgang ab, die betroffene Datei bleibt offen — MySQL kann Schemaänderungen nicht zurückrollen, deshalb muss jede Migration vorher lokal ausprobiert worden sein.')) ?>
</p>

<?php if ($report !== []): ?>
    <?php
    $failed = array_filter($report, static fn(array $row) => !$row['ok']);
    notice(
        $failed ? 'error' : 'success',
        array_map(static fn(array $row) => $row['file'] . ' — ' . $row['message'], $report)
    );
    ?>
<?php endif; ?>

<h2><?= h(t('Offen')) ?></h2>

<?php if ($pending === []): ?>
    <p><?= h(t('Nichts offen — alles eingespielt.')) ?></p>
<?php else: ?>
    <?php /* Without a target: to the page itself. An empty action attribute
         would do the same, but per the spec is not one. */ ?>
    <form method="post"<?= $target === '' ? '' : ' action="' . h($target) . '"' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="migrations">
        <div class="button-row" style="margin-top:0">
            <button class="button" type="submit">
                <?= h(t('%d Migration(en) einspielen', count($pending))) ?>
            </button>
        </div>
    </form>

    <?php foreach ($pending as $file): ?>
        <h3 style="margin-top:1.6rem"><code><?= h($file) ?></code></h3>
        <pre><?= h((string) file_get_contents((string) migration_path($file))) ?></pre>
    <?php endforeach; ?>
<?php endif; ?>

<h2><?= h(t('Bereits eingespielt')) ?></h2>

<?php if ($applied === []): ?>
    <p class="hint-text"><?= h(t('Noch nichts.')) ?></p>
<?php else: ?>
    <div class="table-frame">
        <table class="list">
            <thead><tr><th><?= h(t('Datei')) ?></th><th><?= h(t('Eingespielt')) ?></th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($applied, true) as $file => $timestamp): ?>
                <tr>
                    <td><code><?= h($file) ?></code></td>
                    <td><?= h(date('d.m.Y H:i', strtotime($timestamp))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

    <?php
}
