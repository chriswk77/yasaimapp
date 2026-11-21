<?php
/**
 * Set point titles from the note. Command line only.
 *
 *   php admin/title_from_note.php <user-email> "<listenname>" [--really]
 *
 * Find points taken over from Google usually carry only the coordinate or
 * "Gesetzte Markierung" as their title — the list on the left then shows
 * the same nothing 110 times. The species name, however, is in the note.
 * This script pulls it to the front; the note is preserved in full, red-list
 * citation and companion tree included.
 *
 * Points without a note have nothing to offer and move to the trash —
 * unless they still stand in another list. Then they are only released from
 * this one, so the other entry stays intact.
 *
 * Only what has a meaningless title is touched. A point already called
 * "Murnauer Moos" keeps its name.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';

$email    = $argv[1] ?? '';
$listName = $argv[2] ?? '';
$really = in_array('--really', $argv, true) || in_array('--wirklich', $argv, true);

if ($email === '' || $listName === '') {
    fwrite(STDERR, "Aufruf: php admin/title_from_note.php <user-email> \"<listenname>\" [--really]\n");
    exit(1);
}

$stmt = $pdo->prepare('SELECT id, name FROM map_users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user) {
    fwrite(STDERR, "Kein Benutzer mit dieser Adresse: $email\n");
    exit(1);
}
$userId = (int) $user['id'];

$stmt = $pdo->prepare('SELECT id FROM map_lists WHERE user_id = ? AND name = ? AND deleted_at IS NULL');
$stmt->execute([$userId, $listName]);
$listId = $stmt->fetchColumn();
if ($listId === false) {
    fwrite(STDERR, "Keine Liste dieses Namens: $listName\n");
    exit(1);
}
$listId = (int) $listId;

$stmt = $pdo->prepare(
    'SELECT p.id, p.title, p.note,
            (SELECT COUNT(*) FROM map_point_lists x WHERE x.point_id = p.id) listenzahl
     FROM map_points p
     JOIN map_point_lists pl ON pl.point_id = p.id
     WHERE pl.list_id = ? AND p.user_id = ? AND p.deleted_at IS NULL
     ORDER BY p.id'
);
$stmt->execute([$listId, $userId]);
$points = $stmt->fetchAll();

echo $really ? "Liste \"$listName\" für {$user['name']}\n\n"
               : "PROBELAUF — Liste \"$listName\", nichts wird geschrieben\n\n";

$renamed = $deleted = $released = $untouched = 0;

foreach ($points as $p) {
    if (!title_meaningless($p['title'])) {
        $untouched++;
        continue;
    }

    $species = species_from_note((string) $p['note']);

    if ($species === null) {
        // Nothing to gain. If the point still stands elsewhere, only release it here.
        if ((int) $p['list_count'] > 1) {
            $released++;
            printf("  aus Liste gelöst   %-30s (steht in %d Listen)\n",
                mb_strimwidth($p['title'], 0, 29, '…'), $p['list_count']);
            if ($really) {
                $w = $pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ? AND list_id = ?');
                $w->execute([$p['id'], $listId]);
            }
        } else {
            $deleted++;
            printf("  in den Papierkorb  %-30s\n", mb_strimwidth($p['title'], 0, 29, '…'));
            if ($really) {
                point_delete($pdo, $userId, (int) $p['id']);
            }
        }
        continue;
    }

    $renamed++;
    printf("  %-30s -> %s\n", mb_strimwidth($p['title'], 0, 29, '…'), $species);
    if ($really) {
        point_update($pdo, $userId, (int) $p['id'], ['title' => $species]);
    }
}

printf("\n  %d umbenannt, %d in den Papierkorb, %d nur aus der Liste gelöst, %d unberührt\n",
    $renamed, $deleted, $released, $untouched);

if (!$really) {
    echo "\nZum tatsächlichen Ausführen mit --really erneut aufrufen.\n";
}

/**
 * Titles that say nothing: the coordinate itself, Google's placeholder or a
 * plus code. Everything else is a chosen name and stays.
 */
function title_meaningless(string $title): bool
{
    $title = trim($title);

    return $title === ''
        || $title === 'Gesetzte Markierung'
        || preg_match('~^\d+°\d+\x27[\d.]+"[NS]~u', $title) === 1
        || preg_match('~^-?\d+\.\d+\s*,\s*-?\d+\.\d+$~', $title) === 1
        || preg_match('~^[23456789CFGHJMPQRVWX]{4,8}\+[23456789CFGHJMPQRVWX]{2,3}$~', $title) === 1;
}

/**
 * Extract the species name from the note.
 *
 * The notes follow no fixed scheme, so cutting happens from the back
 * instead of searching from the front:
 *
 *   "Conocybe aurea - RL G"                    -> Conocybe aurea
 *   "Hemileccinum depilatum RL G / vmtl. …"    -> Hemileccinum depilatum
 *   "Butyriboletus appendiculatus RL3 nach …"  -> Butyriboletus appendiculatus
 *   "Fayodia bisphaerigera / vmtl. erloschen"  -> Fayodia bisphaerigera
 *   "Cortinarius bibulus"                      -> Cortinarius bibulus
 *   "Tricholoma frondosae o. equestre / …"     -> Tricholoma frondosae o. equestre
 *   "Ramariopsis cf. luteo-ochracea"           -> Ramariopsis cf. luteo-ochracea
 *
 * What matters is the difference between the separating " - " with spaces
 * and the hyphen inside a name: "luteo-ochracea" must not be cut apart.
 */
function species_from_note(string $note): ?string
{
    // First line: note and comment were joined with a line break on import,
    // the species name is always in the first one.
    $species = trim(explode("\n", trim($note))[0]);
    if ($species === '') {
        return null;
    }

    $species = explode(' / ', $species)[0];              // companion remark after the slash
    $species = preg_split('~\s+-\s+~u', $species)[0];    // " - RL 3", " - unbekannt"
    $species = preg_split('~\s+RL\s*\d|\s+RL\s+[A-Z]~u', $species)[0];   // "… RL G", "… RL3"
    $species = trim(preg_replace('~\s+~u', ' ', $species));

    return $species !== '' ? mb_substr($species, 0, 200) : null;
}
