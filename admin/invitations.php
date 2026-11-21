<?php
/**
 * The invitations have moved into the administration.
 *
 * Not deleted but redirected: the path sits in bookmarks, and register.php
 * points to it in its header. A 404 for that would be a needless dead end.
 *
 * Content and processing live in includes/invitations_content.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Location: ' . url('pages/administration.php') . '#invitations', true, 301);
exit;
