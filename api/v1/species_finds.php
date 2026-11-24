<?php
/**
 * The finds of a species with a picture that one may see, and the older
 * pictures of it from the picture archives (includes/species_finds.php).
 *
 *   GET species_finds.php?name=<species>   → { finds: [...], older: [...] }
 *
 * ONE ANSWER FOR BOTH, because the tab's count „Funde (3)" covers both: a
 * separate count would have to repeat what counts, and the two would drift.
 * Every picture address in it asks the rights again where it is served.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/species_finds.php';

if ($method !== 'GET') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
$name = mb_substr(trim((string) ($_GET['name'] ?? '')), 0, 190);
if ($name === '') {
    json_error(t('Keine Art angegeben.'), 422);
}
json_response([
    'finds' => species_finds($pdo, (int) $me['id'], $name),
    'older' => species_older_pictures($pdo, (int) $me['id'], $name),
]);
