<?php
/**
 * A PLATE ON THE PUBLIC SITE — released on purpose, under a licence, with
 * everybody whose pictures it carries cleared (migration 0201).
 *
 * „Ich habe auch schon überlegt, ob wir in YASAIMAPP nicht einen einfachen
 * Haken irgendwo einführen … zur Veröffentlichung freigegeben oder nicht
 * … Beim Veröffentlichen setze ich dort die Lizenz. Und wenn dort Bilder
 * drauf sind von Freunden, dann kann ich das natürlich vorher klären"
 * (25.11.2026). Until then the site (includes/public_site.php) took every
 * plate of its owner by itself.
 *
 * ONE LICENCE: CC BY-NC 4.0 — anybody may pass the plate on, changed too,
 * with the names and the licence, but not for money („kommerzielle
 * Nutzung würde ich ausschließen"). A free addition (licence_note) carries
 * the exceptions, e.g. a picture that came with a permission of its own.
 * Choosing between licences was the discarded alternative: one licence is
 * one sentence in the Impressum.
 *
 * A PICTURE BELONGS TO WHOEVER TOOK IT (CLAUDE.md). A plate carrying a
 * friend's picture cannot be licensed by its maker alone, so the window
 * names every other photographer and the maker confirms each one as
 * cleared. Whoever is not confirmed keeps the plate off the site — also a
 * picture added AFTER the release: the site asks plate_publish_state() on
 * every run, so a new friend's picture takes the plate down until he is
 * confirmed too. Measuring or editing somebody's picture does not make a
 * photographer — only taking it does, as with co-ownership.
 *
 * WHICH PICTURES A PLATE SHOWS: those its cells and insets name directly
 * („photo:63", „cloud:12", anywhere in the layout), and a ROW of a layer
 * shows cutouts of every picture of the find that holds measures in that
 * layer (plateparts.js rowsFor) — so those count too. A plot or a values
 * text shows numbers, not pictures, and counts for nobody.
 *
 * ONLY THE SITE'S OWNER SEES ANY OF THIS (public_site_may): on an
 * installation without a public site the columns mean nothing.
 */

declare(strict_types=1);

require_once __DIR__ . '/measures.php';

const PLATE_LICENCE = 'CC BY-NC 4.0';
const PLATE_LICENCE_URL = 'https://creativecommons.org/licenses/by-nc/4.0/deed.de';
const PLATE_LICENCE_NOTE_MAX = 500;

function plate_publish_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_plates', 'published_at');
}

/**
 * What a layout shows: the picture keys its cells name, and the layers
 * whose rows of cutouts it holds.
 *
 * @return array{keys: array<string, true>, rows: array<string, true>}
 */
function plate_layout_sources(array $layout): array
{
    $keys = [];
    $rows = [];
    $walk = static function (mixed $n) use (&$walk, &$keys, &$rows): void {
        if (!is_array($n)) {
            return;
        }
        if (is_string($n['pic'] ?? null) && preg_match('/^(photo|cloud):\d+$/', $n['pic'])) {
            $keys[$n['pic']] = true;
        }
        if (($n['kind'] ?? null) === 'row' && is_string($n['layer'] ?? null)) {
            $rows[$n['layer']] = true;
        }
        foreach ($n as $child) {
            if (is_array($child)) {
                $walk($child);
            }
        }
    };
    $walk($layout);
    return ['keys' => $keys, 'rows' => $rows];
}

/**
 * Everybody whose picture a plate shows, with his name — the maker first
 * where he has one on it. `name` is what the window shows (the account's
 * name, its address where it has none); `full` is the name the site may
 * print, empty where the account has none.
 *
 * @return array<int, array{id: int, name: string, full: string}>
 */
function plate_photographers(PDO $pdo, array $plate): array
{
    $layout = json_decode((string) $plate['layout'], true);
    if (!is_array($layout)) {
        return [];
    }
    ['keys' => $keys, 'rows' => $rows] = plate_layout_sources($layout);

    $owners = [];   // user id => true
    $tables = ['photo' => 'map_photos'];
    if (table_exists($pdo, 'map_cloud_files')) {
        $tables['cloud'] = 'map_cloud_files';
    }
    foreach ($tables as $kind => $table) {
        $stmt = $pdo->prepare("SELECT id, user_id, measures FROM $table WHERE point_id = ?");
        $stmt->execute([(int) $plate['point_id']]);
        foreach ($stmt as $r) {
            $shown = isset($keys[$kind . ':' . (int) $r['id']]);
            if (!$shown && $rows !== [] && $r['measures'] !== null) {
                foreach (json_decode((string) $r['measures'], true) ?: [] as $m) {
                    if (is_array($m) && isset($rows[measure_label($m)])) {
                        $shown = true;
                        break;
                    }
                }
            }
            if ($shown) {
                $owners[(int) $r['user_id']] = true;
            }
        }
    }
    if ($owners === []) {
        return [];
    }
    $ids = array_keys($owners);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare('SELECT u.id, ' . sql_display_name() . " AS name,
                                  TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS full
                             FROM map_users u WHERE u.id IN ($in)");
    $stmt->execute($ids);
    $out = [];
    foreach ($stmt as $r) {
        $out[(int) $r['id']] = ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'full' => (string) $r['full']];
    }
    $maker = (int) $plate['user_id'];
    uksort($out, static fn(int $a, int $b): int => [(int) ($b === $maker), $out[$a]['name']] <=> [(int) ($a === $maker), $out[$b]['name']]);
    return $out;
}

/**
 * Where a plate stands: released or not, its licence and addition, and
 * every other photographer with whether he is cleared. `shown` says
 * whether the site may show it now — released AND everybody cleared.
 * Before migration 0201 every plate counts as released, so only the
 * pictures of others can keep one off.
 *
 * @return array{published: bool, licence: string, licence_url: string, note: string, others: list<array{id: int, name: string, full: string, cleared: bool}>, credit: list<string>, shown: bool}
 */
function plate_publish_state(PDO $pdo, array $plate): array
{
    $ready = array_key_exists('published_at', $plate);
    $cleared = $ready ? (json_decode((string) ($plate['cleared_users'] ?? ''), true) ?: []) : [];
    $cleared = array_map('intval', is_array($cleared) ? $cleared : []);
    $maker = (int) $plate['user_id'];
    $others = [];
    $credit = [];
    foreach (plate_photographers($pdo, $plate) as $id => $p) {
        if ($p['full'] !== '') {
            $credit[] = $p['full'];
        }
        if ($id !== $maker) {
            $others[] = $p + ['cleared' => in_array($id, $cleared, true)];
        }
    }
    $published = $ready ? $plate['published_at'] !== null : true;
    $allCleared = array_filter($others, static fn(array $o): bool => !$o['cleared']) === [];
    return [
        'published'   => $published,
        'licence'     => (string) (($ready ? $plate['licence'] : null) ?: PLATE_LICENCE),
        'licence_url' => PLATE_LICENCE_URL,
        'note'        => (string) ($ready ? ($plate['licence_note'] ?? '') : ''),
        'others'      => $others,
        'credit'      => $credit,
        'shown'       => $published && $allCleared,
    ];
}

/**
 * Release a plate or take it back — its maker alone, and only where
 * plate_publish_ready(). $cleared: the other photographers the maker
 * confirms; releasing with one of them missing is refused, so the window
 * cannot release what the site would hold back. Leaves updated_at alone:
 * the editor's next save compares it (plate_save, $since).
 *
 * Throws InvalidArgumentException for the user.
 */
function plate_publish_set(PDO $pdo, int $userId, int $plateId, bool $on, string $note, array $cleared): array
{
    $stmt = $pdo->prepare('SELECT * FROM map_plates WHERE id = ?');
    $stmt->execute([$plateId]);
    $plate = $stmt->fetch();
    if ($plate === false || (int) $plate['user_id'] !== $userId) {
        throw new InvalidArgumentException(t('Diese Tafel ist nicht deine — oder es gibt sie nicht mehr.'));
    }
    $note = trim(preg_replace('/\s+/u', ' ', $note) ?? '');
    if (mb_strlen($note) > PLATE_LICENCE_NOTE_MAX) {
        throw new InvalidArgumentException(t('Der Zusatz ist zu lang.'));
    }
    $others = array_keys(array_diff_key(plate_photographers($pdo, $plate), [$userId => true]));
    // Only the photographers the plate really carries are kept — a name confirmed once and gone from the plate goes too.
    $cleared = array_values(array_intersect($others, array_map('intval', $cleared)));
    if ($on && count($cleared) < count($others)) {
        throw new InvalidArgumentException(t('Auf der Tafel sind Bilder von anderen. Bestätige erst, dass sie einverstanden sind.'));
    }
    $pdo->prepare('UPDATE map_plates SET published_at = ?, licence = ?, licence_note = ?, cleared_users = ? WHERE id = ?')
        ->execute([
            $on ? ($plate['published_at'] ?? date('Y-m-d H:i:s')) : null,
            $on ? PLATE_LICENCE : $plate['licence'],
            $note !== '' ? $note : null,
            $cleared !== [] ? json_encode($cleared) : null,
            $plateId,
        ]);
    $stmt->execute([$plateId]);
    return plate_publish_state($pdo, $stmt->fetch());
}
