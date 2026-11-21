<?php
/**
 * The web addresses of one species, grouped (includes/web_links.php).
 *
 *   GET web_links.php?name=Boletus%20edulis&gbif=5249365&dgfm=1234
 *
 * Any of the three is enough; with the keys the stored portrait links of
 * Pilz-Link's export come along (migration 0166), with the name alone the
 * written links and whatever the export knows by that name. Asked only
 * when somebody presses „Internet-Links" (assets/js/weblinks.js) — the
 * list is never loaded in advance.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/web_links.php';

if ($method !== 'GET') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
$name = mb_substr(trim((string) ($_GET['name'] ?? '')), 0, 190);
$gbif = (int) ($_GET['gbif'] ?? 0);
$dgfm = (int) ($_GET['dgfm'] ?? 0);
if ($name === '' && $gbif <= 0 && $dgfm <= 0) {
    json_error(t('Keine Art angegeben.'), 422);
}
json_response(['groups' => web_links($pdo, $name, $gbif ?: null, $dgfm ?: null)]);
