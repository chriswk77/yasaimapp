<?php
/**
 * Backfilling the terrain elevations has moved into the administration.
 *
 * Not deleted but redirected: the path sits in bookmarks, and
 * includes/data.php points to it in a comment in two places. A 404 for
 * that would be a needless dead end.
 *
 * Content and processing live in includes/elevation_content.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Location: ' . url('pages/administration.php') . '#elevation', true, 301);
exit;
