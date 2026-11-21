<?php
/**
 * Apply migrations — the emergency exit.
 *
 * The usual way is the tab in the administration. This page stays alongside
 * it, and for a reason that holds exactly when one needs it: if a migration
 * is missing, the database is not at the level of the program — and then
 * the administration's queries can stumble over a missing column. If
 * applying lived only there, the way back would be blocked in precisely the
 * moment it exists for. On the live server there is no shell; then nothing
 * would remain.
 *
 * This page therefore only asks for map_migrations and for the files —
 * nothing that a pending migration could concern. The error page in
 * bootstrap.php points here as well.
 *
 * The content itself is written once, in includes/migrations_content.php.
 * Two versions of the same form would be the sure way to let them drift
 * apart.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/migrations_content.php';
require_once __DIR__ . '/../includes/page.php';

admin_required();

// Before any output.
$report = migrations_process($pdo);

page_header('Migrationen');
?>
<h1>Migrationen</h1>

<?php migrations_render($pdo, $report, ''); ?>

<p style="margin-top:2rem">
    <a class="button subtle" href="<?= h(url('pages/administration.php') . '#migrations') ?>">Zur Verwaltung</a>
</p>

<?php page_footer(); ?>
