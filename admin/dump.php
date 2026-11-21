<?php
/**
 * Deliver the database dump.
 *
 * ITS OWN FILE AND NOT A TAB, because what comes out here is not a page but
 * a download: its own content type, its own header, not a single character
 * of HTML before or after. The form that sends here lives in the
 * administration.
 *
 * THE FILE NEVER COMES INTO BEING ON DISK. It is written into the response
 * piece by piece. That is not thrift but the heart of the matter: a dump in
 * the web directory would be secret only as long as nobody guesses its name
 * — and it contains every find location at once, plus the password hashes.
 * On this server the directory that config.sample.php suggests for
 * accessories sits on the open net; storing an intermediate file "just
 * briefly" would thus be exactly the mistake you make once.
 *
 * POST ONLY. A GET link could be embedded in a page or written into a
 * history; together with the CSRF token, the dump stays something someone
 * requests explicitly.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/dump.php';

admin_required();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit("Nur über das Formular in der Verwaltung.\n");
}

csrf_check();

// As long as nothing has been written, an error may still yield a readable
// page. From the first byte on, no more — then the message sits in the
// middle of the dump, and that is worse than an abort, because the file
// LOOKS usable. Hence collect until then and only then send off.
$dump = static function () use ($pdo): void {
    // No time limit: our own tables are small, but "small" is an
    // observation of today. set_time_limit is often locked on shared
    // servers — then the default remains, and that is fine.
    @set_time_limit(0);

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . dump_filename() . '"');
    // No cache, anywhere: neither in the browser nor at an intermediary in
    // between. The content is the most sensitive thing this application
    // yields.
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    // Drain the output buffers and leave them open, so the dump really
    // flows and does not end up in memory as a whole after all.
    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    dump_write($pdo, static function (string $chunk): void {
        echo $chunk;
        flush();
    });
};

try {
    $dump();
} catch (Throwable $e) {
    // If the header is already out, the file is broken open anyway — then
    // all that remains is to mark it recognizably as incomplete. Whoever
    // imports it otherwise runs into half a database.
    if (headers_sent()) {
        echo "\n\n-- ABGEBROCHEN. Dieser Abzug ist UNVOLLSTAENDIG und darf nicht\n";
        echo "-- eingespielt werden.\n";
    } else {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Der Abzug ist fehlgeschlagen.\n";
    }
    error_log('Dump fehlgeschlagen: ' . $e->getMessage());
}
