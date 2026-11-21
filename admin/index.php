<?php
/**
 * The old administration page. It has been absorbed into administration.php.
 *
 * Not deleted but redirected: the path sits in bookmarks and in older pages
 * of this application. A 404 for that would be a needless dead end.
 *
 * The content now stands under "Server" — next to it there is an area for
 * one's own account, which those who are not administrators see too. That
 * is exactly what did not work here: this page was locked to administrators
 * as a whole, and so for everyone else there was no administration at all.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Location: ' . url('pages/administration.php'), true, 301);
exit;
