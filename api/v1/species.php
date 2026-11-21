<?php
/**
 * Species suggestions for the title field.
 *
 *   GET species.php?q=bol+edu[&offset=50][&groups=fungi,lichens]
 *
 * Answers with at most a dozen suggestions: scientific name, author, German
 * name and red-list classification, as far as available.
 *
 * 'groups' IS WHAT THE USER RECORDS — the choice from the settings, tab
 * "Arten". It comes along with the request rather than lying on the server,
 * because it is a device setting like every other one here (settings.js).
 * Missing means all of them: a browser with an older program in its cache
 * must not suddenly find nothing.
 */

declare(strict_types=1);

// Excursion guests may search here: the species names are the world's,
// not a user's — the endpoint reads nothing personal, and a guest typing
// „Boletus" into his find's title deserves the same suggestions as
// everyone (his excursion IS a species list).
const EXCURSION_GUESTS_ALLOWED = true;

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/species.php';

if ($method !== 'GET') {
    json_error(t('Methode nicht unterstützt.'), 405);
}

$groups = isset($_GET['groups'])
    ? array_filter(array_map('trim', explode(',', (string) $_GET['groups'])))
    : null;

$result = species_search($pdo, (string) ($_GET['q'] ?? ''), (int) ($_GET['offset'] ?? 0), $groups);

json_response([
    'hits'  => $result['hits'],
    'total' => $result['total'],
]);
