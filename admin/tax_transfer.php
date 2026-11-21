<?php
/**
 * Importing the taxonomy has moved into the administration.
 *
 * Not deleted but redirected: this page was reachable only under its
 * address for a long time, and exactly for that reason it sits in notes and
 * in the header of admin/tax_export.php. A 404 for that would be a needless
 * dead end.
 *
 * Content and processing live in includes/tax_transfer_content.php. There it
 * also says why the process is now linked again and what has taken its
 * place to prevent accidental truncation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Location: ' . url('pages/administration.php') . '#taxonomy', true, 301);
exit;
