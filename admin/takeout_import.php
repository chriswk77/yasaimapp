<?php
/**
 * Import a Google Takeout — via the command line.
 *
 *   php admin/takeout_import.php <zip> <user-email> [--really]
 *
 * THE USUAL WAY IS THE ADMINISTRATION: Mein Bereich → Import, with
 * instructions and preview. This script remains as the emergency exit for
 * archives too big for the upload — and as the way that has worked once
 * before.
 *
 * Without --really it only reports what would happen. The calculation
 * behind it is the SAME as in the administration (takeout_import in
 * includes/takeout.php) — only the output remains here.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/takeout.php';

$zipPath  = $argv[1] ?? '';
$email    = $argv[2] ?? '';
$really = in_array('--really', $argv, true) || in_array('--wirklich', $argv, true);

if ($zipPath === '' || $email === '') {
    fwrite(STDERR, "Aufruf: php admin/takeout_import.php <zip> <user-email> [--really]\n");
    exit(1);
}
if (!is_readable($zipPath)) {
    fwrite(STDERR, "Datei nicht lesbar: $zipPath\n");
    exit(1);
}

$stmt = $pdo->prepare('SELECT id, first_name, last_name, email FROM map_users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user) {
    fwrite(STDERR, "Kein Benutzer mit dieser Adresse: $email\n");
    exit(1);
}
$userId = (int) $user['id'];

$data   = takeout_read($zipPath);
$report = takeout_import($pdo, $userId, $data, $really);

$who = user_display_name($user);
echo $really ? "Einspielen für $who <$email>\n"
               : "PROBELAUF für $who <$email> — nichts wird geschrieben\n";
echo "Alles kommt in die Liste \"{$report['import']}\", je Takeout-Liste eine Unterliste.\n\n";

foreach ($report['lists'] as $l) {
    if ($l['empty']) {
        printf("  %-34s leer, übersprungen\n", mb_strimwidth($l['name'], 0, 33, '…'));
        continue;
    }
    printf("  %-34s %3d neu%s%s\n",
        mb_strimwidth($l['name'], 0, 33, '…'),
        $l['new'],
        $l['assigned'] ? sprintf(', %d zusätzlich zugeordnet', $l['assigned']) : '',
        $l['without'] ? sprintf(', %d ohne Position', $l['without']) : '');
}

printf("\n  %d Punkte angelegt, %d weiteren Listen zugeordnet, %d ohne Position\n",
    $report['created'], $report['assigned'], count($report['withoutPosition']));

if ($report['withoutPosition']) {
    echo "\nOhne Position — diese Einträge tragen bei Google nur einen internen\n"
       . "Ortsverweis, aus dem sich keine Koordinaten ableiten lassen:\n\n";
    foreach ($report['withoutPosition'] as $o) {
        printf("  [%-22s] %-32s %s\n",
            mb_strimwidth($o['list'], 0, 21, '…'),
            mb_strimwidth($o['title'], 0, 31, '…'),
            $o['url']);
    }
}

if (!$really) {
    echo "\nZum tatsächlichen Einspielen mit --really erneut aufrufen.\n";
}
