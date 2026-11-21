<?php
/**
 * The measuring overlay's style, written from the map.
 *
 *   POST measure_style.php   {label, spore_air}   one layer's margin
 *   POST measure_style.php   {label, spore_air, point_id}
 *                            one layer's margin on ONE find (migration 0156);
 *                            on the account as above until it is applied
 *   GET  measure_style.php?point=<find>
 *                            {air: {label: percent}} — one's own margins on
 *                            that find, {} before 0156 or without any
 *   POST measure_style.php   {micro_shrink_warned: 1}
 *                            the shrink warning waved off for good
 *   POST measure_style.php   {plate: {gutter?, gutter_color?, text_pt?, details_pt?, values_pt?}}
 *                            what the plate editor last set (plates.js)
 *   POST measure_style.php   {label, part_direction: right|left|up|down}
 *                            which way a part's line points in its tiles
 *   POST measure_style.php   {label, straight: 0|1}
 *                            one layer's bent lengths read along the bend
 *                            or straight, end to end (photowindow.js)
 *   POST measure_style.php   {export: {artist, copyright, owner_in_file, file_name, marks}}
 *                            the export style, whole (Einstellungen → Fotos →
 *                            Export, exportsettings.js); logos no template
 *                            uses any more are tidied away after it
 *   POST measure_style.php   {scale_objects: [{name, um}, …]}
 *                            one's own things of a known length, the whole
 *                            list at once (the scale window's buttons)
 *   POST measure_style.php   {photo_title: {name, kind: macro|micro}}
 *                            a picture name of one's own joins that list
 *                            (the viewer's „Eigener Name …", 15.11.2026)
 *   POST measure_style.php   {photo_title: {name, remove: true}}
 *                            one's own name leaves both lists again; a
 *                            stock name stays (the menu's bucket)
 *
 * ONE FIELD ONLY, deliberately: colours and font size live in the
 * administration's form (Einstellungen → Fotos → Messen) — but the margin around
 * the spore cut-outs is turned WHILE LOOKING at them, per layer
 * (28.08.2026: „Natürlich pro Ebene"), and a detour through
 * the administration for every twist of the slider would be the tool
 * nobody uses. The whole style is read, the one entry changed, and the
 * whole style written back through the same gate the form uses —
 * measure_style_write keeps only deviations, so a layer turned back to
 * the default 118 simply leaves the JSON again.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/photos.php';
require_once __DIR__ . '/../../includes/point_owners.php';

if (!column_exists($pdo, 'map_users', 'measure_style')) {
    json_error(t('Das braucht die Migration 0091 — sie ist noch nicht eingespielt.'), 503);
}
$userId = (int) $me['id'];

/* THE MARGINS OF ONE FIND (0156) — read when its pictures open. A find one
   may not see answers like one without margins: nothing to tell. */
if ($method === 'GET') {
    $pointId = (int) ($_GET['point'] ?? 0);
    $mine = $pointId > 0 && point_view_scope($pdo, $userId, $pointId) !== null;
    json_response(['air' => (object) ($mine ? measure_air_for_point($pdo, $userId, $pointId) : []),
        'ready' => measure_air_ready($pdo)]);
}
if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}

/* A MARGIN FOR ONE FIND goes into its own table and leaves the account's
   alone — the account's per-layer value stays the start for every find
   without one. Before 0156, or for a find one may not see, it falls
   through to the account as it always did. */
$pointId = (int) ($input['point_id'] ?? $_POST['point_id'] ?? 0);
if ($pointId > 0 && measure_air_ready($pdo) && point_view_scope($pdo, $userId, $pointId) !== null
    && ($input['spore_air'] ?? $_POST['spore_air'] ?? null) !== null) {
    try {
        measure_air_set($pdo, $userId, $pointId, (string) ($input['label'] ?? $_POST['label'] ?? ''),
            (int) ($input['spore_air'] ?? $_POST['spore_air']));
    } catch (InvalidArgumentException $e) {
        json_error($e->getMessage(), 422);
    }
    json_response(['air' => (object) measure_air_for_point($pdo, $userId, $pointId)]);
}

$stmt = $pdo->prepare('SELECT measure_style FROM map_users WHERE id = ?');
$stmt->execute([$userId]);
$style = measure_style_read($stmt->fetchColumn() ?: null);

/* „NICHT MEHR ANZEIGEN" under the warning before a micrograph goes up
   shrunk (map.js, microShrinkWarning) — on the account, so no device asks
   again. Only ever set: bringing the warning back is not a thing the map
   offers. */
if (!empty($input['micro_shrink_warned'] ?? $_POST['micro_shrink_warned'] ?? null)) {
    $style['micro_shrink_warned'] = true;
} elseif (is_array($input['export'] ?? null)) {
    // The export style as the settings tab holds it — whole, like the own things.
    $style['export'] = export_style_clean($input['export']);
} elseif (is_array($input['scale_objects'] ?? null)) {
    // The whole list as the window holds it — made or removed there.
    $style['scale_objects'] = scale_objects_clean($input['scale_objects']);
} elseif (is_array($input['photo_title'] ?? null)) {
    /* „EIGENER NAME …" IN THE VIEWER (15.11.2026: „muss
       entscheiden, ob Makro oder Mikro") — appended to the list chosen,
       unless either list holds it already (in any case of letters). The
       list is then a deviation from the stock and is stored as one. */
    $name = mb_substr(trim((string) ($input['photo_title']['name'] ?? '')), 0, 120);
    $kind = (string) ($input['photo_title']['kind'] ?? '');
    if (!empty($input['photo_title']['remove']) && $name !== '') {
        // Only one's own: a stock name stays — there is no place left to bring it back.
        if (in_array($name, [...PHOTO_TITLES_MACRO, ...PHOTO_TITLES_MICRO], true)) {
            json_error(t('Feste Motivnamen bleiben.'), 422);
        }
        foreach (['photo_titles_macro', 'photo_titles_micro'] as $key) {
            $style[$key] = array_values(array_filter($style[$key], static fn(string $w): bool => $w !== $name));
        }
    } elseif ($name === '' || !in_array($kind, ['macro', 'micro'], true)) {
        json_error(t('Welcher Name, und Makro oder Mikro?'));
    } else {
        $known = array_map('mb_strtolower', [...$style['photo_titles_macro'], ...$style['photo_titles_micro']]);
        if (!in_array(mb_strtolower($name), $known, true)) {
            $style['photo_titles_' . $kind][] = $name;
        }
    }
} elseif (is_array($input['plate'] ?? null)) {
    // The plates' hand, one setting at a time — the rest stays as stored.
    $style['plate'] = plate_style_clean([...$style['plate'], ...$input['plate']]);
} elseif (($input['part_direction'] ?? $_POST['part_direction'] ?? null) !== null) {
    // One part's direction — „Sporen · Ornamenthöhe" => 'up'; the others stay.
    $label = mb_substr(trim((string) ($input['label'] ?? $_POST['label'] ?? '')), 0, 40);
    $value = (string) ($input['part_direction'] ?? $_POST['part_direction']);
    if ($label === '' || !in_array($value, ['right', 'left', 'up', 'down'], true)) {
        json_error(t('Welche Ebene?'));
    }
    $style['part_direction'][$label] = $value;
} elseif (($input['straight'] ?? $_POST['straight'] ?? null) !== null) {
    // One layer in or out of the list read straight — the others stay.
    $label = mb_substr(trim((string) ($input['label'] ?? $_POST['label'] ?? '')), 0, 40);
    if ($label === '') {
        json_error(t('Welche Ebene?'));
    }
    $others = array_values(array_filter($style['straight_length'], static fn(string $l): bool => $l !== $label));
    $style['straight_length'] = !empty($input['straight'] ?? $_POST['straight']) ? [...$others, $label] : $others;
} else {
    $label = mb_substr(trim((string) ($input['label'] ?? $_POST['label'] ?? '')), 0, 40);
    $air   = (int) ($input['spore_air'] ?? $_POST['spore_air'] ?? 0);
    if ($label === '' || $air < 100 || $air > 300) {
        json_error(t('Ebene und Rand (100–300) werden gebraucht.'));
    }
    $style['spore_air'][$label] = $air;
}

try {
    $stored = measure_style_write($style);
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
}
$pdo->prepare('UPDATE map_users SET measure_style = ? WHERE id = ?')
    ->execute([$stored, $userId]);
export_logos_tidy($userId, measure_style_read($stored)['export']);

json_response(['measure_style' => measure_style_read($stored)]);
