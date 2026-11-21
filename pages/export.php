<?php
/**
 * Handing an export file over — the delivery end of includes/export.php.
 *
 * A PAGE AND NOT AN API ENDPOINT, because a download is a navigation: the
 * browser is supposed to open its save dialog, and for that the request
 * must come from a form, not from fetch(). Organisieren submits its hidden
 * export form here (organize.js), the file comes back as an attachment,
 * and the page the user was looking at stays where it was — a form with
 * target="_blank" leaves no navigation behind.
 *
 * POST AND CSRF, although this only reads: the request carries the chosen
 * point ids, and a link somebody could put into an <img> tag would let a
 * foreign page trigger downloads of one's own stock. Reading is not
 * harmless when the reading happens in a stranger's name.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/export.php';

$me = login_required();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Nur über das Formular.');
}
csrf_check();

$format = (string) ($_POST['format'] ?? '');
if (!isset(EXPORT_FORMATS[$format])) {
    http_response_code(422);
    exit('Dieses Format gibt es nicht.');
}

// The same reading as everywhere in Organisieren: a comma-separated list of ids,
// empty means everything.
$ids = array_values(array_filter(array_map(
    'intval', explode(',', (string) ($_POST['points'] ?? ''))), static fn(int $i): bool => $i > 0));

$points = export_points($pdo, (int) $me['id'], $ids);
if ($points === []) {
    http_response_code(422);
    exit('Nichts zu exportieren — die Auswahl ist leer.');
}

/* THE MYKIS SHEET NEEDS ONE NAME the other formats do without: whose
   finds they are, for rows whose finder field is empty. And a selection
   with no find MykIS can take (none in Germany, none dated, none named)
   says so instead of handing over a head without rows — the rules are
   mykis_eligible()'s. */
$options = [];
if ($format === 'mykis') {
    $holder = trim((string) ($me['last_name'] ?? '')) . ', ' . trim((string) ($me['first_name'] ?? ''));
    // How precisely and whose finds: the export window asks both
    // (organize.js, 27.09.2026) — mykis_manual_options reads the
    // answers, with the exclusions of the rules holding either way.
    $asked = array_filter(['precision' => $_POST['precision'] ?? null, 'determined' => $_POST['determined'] ?? null,
                           'foreign' => $_POST['foreign'] ?? null], 'is_string');
    $options = ['holder' => trim($holder, ', ')] + mykis_manual_options($me, $asked);
    if (mykis_rows($points, $options['holder'], null, $options['self'], $options['rules']) === []) {
        http_response_code(422);
        exit('Keiner der gewählten Funde passt für MykIS. Es braucht sicher bestimmte Funde aus Deutschland mit Fundtag und mit einem Namen aus der Artenliste der DGfM.');
    }
}

$file = export_build($points, $format, 'YASAIMAPP', $options);

header('Content-Type: ' . $file['mime']);
header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
header('Content-Length: ' . strlen($file['body']));
// A download of one's own find locations belongs in no cache along the way.
header('Cache-Control: private, no-store');
echo $file['body'];
