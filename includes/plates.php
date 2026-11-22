<?php
/**
 * Plates — A4 compositions of a find's pictures (migration 0155).
 *
 * THE GOAL OF THE PHOTO VIEWER (06.10.2026, two plates of the
 * late Matthias Reul in hand): pictures cut to rectangles, edge to edge
 * with a fine line between them, every one with its own scale bar, a few
 * texts and a field with the find's details — one A4 page, sometimes two.
 * The editor is assets/js/plates.js; this file keeps what is stored and
 * who may touch it.
 *
 * THE LAYOUT IS A TREE PER PAGE, not free boxes. A split runs across its
 * whole parent, row or column, and its children share that parent without
 * a gap or an overlap — the shape of every plate Reul made, and what lets
 * two pictures side by side stay the same height whatever one drags
 * (on the draft: „ein gleich hohes Rechteck … oder zwei
 * Rechtecke, die zusammen die gleiche Höhe ergeben"). Free placement was
 * the discarded alternative: gaps and overlaps would have to be avoided by
 * hand, and the fine lines would never line up.
 *
 *   {v: 1, gutter: mm, gutterColor: '#ffffff'|'#000000',
 *    pages: [{root: node}]}
 *   node  split {t: 'split', dir: 'row'|'col', kids: [node…], sizes: [share…]}
 *         cell  {t: 'cell', pic: 'photo:63'|'cloud:12'|null, z, cx, cy,
 *                bar: corner|'off', med: corner|'off', label: corner|'off', labelMarks: [mark…], info: details|null, block: block|null,
 *                items: [item…], notes: [layer…], measures: [layer…]}
 *   med       on a picture or row cell: the corner of the medium the picture lay in
 *             (its short labels, plateparts.js mediaGeometry) — 'off' unless
 *             asked for (06.11.2026)
 *   barOver   true where a row's bar lies over its pictures instead of a
 *             strip of its own (plateparts.js rowBarOver, 08.11.2026)
 *   medFull   true where the medium stands written out (media.js mediaLabel)
 *   medLine, labelLine  on a row cell: true where the medium or the label
 *             stands in a strip of its own instead of over the pictures
 *             (plateparts.js rowStrips, 22.11.2026)
 *   ink       {bar, med, label} → 'black' | 'white', each piece of writing
 *             on its own; absent means read off the picture. soft {bar,
 *             med, label} → true blurs the picture beneath it, fading out,
 *             false keeps it sharp; absent, medium and label are soft and
 *             the bar sharp (plate_writing, 09. and 20.11.2026)
 *   label     on a picture cell: the corner of the picture's name, labelMarks
 *             the marks of its measures shown behind it in brackets
 *             (plateparts.js pictureLabel, 08.11.2026); on a row
 *             cell the layer's name, and behind it its marks or its part
 *             (rowLabel, 09.11.2026) — a row's medium: its pictures'
 *   notes     on a picture cell: the measuring layers whose texts, arrows
 *             and counting boxes it shows (plateannot.js); the layout's
 *             notePt is the size of that writing, one for the plate
 *   measures  on a picture cell: the layers whose lines, bent lengths and
 *             length × breadth it draws with their numbers (30.10.2026),
 *             cleaned like notes — the same picture twice shows two layers
 *   item  {kind: 'text', s, x, y, pt, ground, groundOpacity}
 *         {kind: 'info', x, y, …details}
 *         {kind: 'values', layer, lines: ['range'|'qn'|'mean'|'spread'…], name, x, y, pt, ground, groundOpacity, straight}
 *         {kind: 'keying', key_id, show: 'both'|'path'|'remark', label: 'both'|'book'|'key', x, y, pt, ground, groundOpacity}
 *           — a keying way of the find (point_keyings.php), named by the key it ends in
 *   details {fields: [key…], labels, flow: 'lines'|'one', pt, ground, groundOpacity}
 *           groundOpacity: how much the ground covers a picture, 10–100 %, 100 unset
 *           as a tile also x, y — where its words stand, null at the padding
 *   inset {kind: 'inset', x, y, w, h, frame, cell} — an item: a smaller window on a
 *         filled cell, its own cell inside (a picture or a block, no words);
 *         frame false where it goes without the frame in the page's lines
 *   block {kind: 'plot'|'row', layer, sort: 'L'|'B'|'Q'}
 *         a plot also: straight, ground 'white'|'black', groundOpacity, spread (the line under it); a row: air (% or null), gap (mm), gapColor, pad (mm),
 *         crop (a cutout name: its picture cutouts instead of measures, or null)
 *         count (true: its counting frames and sectors instead of measures)
 *   part      on a block: one part measure's curve or row („Keimporus"),
 *             null for the structure's; a values text ticks parts as
 *             'part:<name>' among its lines
 *   mark      on a values text and every block: null = all of the layer,
 *             '' = the unmarked ones, else one mark („Chrysozystide")
 *   straight  true|false — a layer's bent lengths read end to end or along
 *             the bend (measurestats.js, frameValues); null = as the
 *             photo window reads them for the viewer's account
 *
 * THE MEASURES' BLOCKS (step 3) NAME A LAYER, not numbers: „Werte",
 * „Verteilung" and „in Reihe" are reckoned from the find's measures every
 * time the plate is drawn (measurestats.js), so a spore measured tomorrow
 * stands in the row and the range tomorrow.
 *
 * A CELL NAMES ITS PICTURE, it does not copy it: the plate draws it from
 * where it lies, the scale bar from the picture's own calibration — a
 * recalibrated picture corrects every plate it stands in. z, cx and cy are
 * the cutout (zoom over cover-fit, the centre in fractions of the picture),
 * so a cutout survives the picture coming as preview or original. A key
 * the find no longer holds is dropped to an empty cell on saving instead
 * of refusing the plate: a picture deleted on another device while the
 * editor stood open must not cost the whole plate.
 *
 * WHO: EACH WHO WORKS ON THE FIND HAS ONE PLATE OF HIS OWN
 * (06.10.2026, after a shared plate had been built for an hour: „sollen wir
 * für jeden Mitbesitzer … eine eigene Tafel erstellen? Wenn irgendjemand
 * mal mit einem bestimmten Stil angefangen hat … nach seinen
 * Schriftgrößen, Farben, Formen, Vorstellungen an Ordnungen" — a plate is
 * a hand, and on a shared one everybody rewrites the other's). The holder
 * and every co-owner make at most one plate per find — its pages carry
 * what a second would —, with all the pictures of the circle in it
 * („dafür gibt es ja den gemeinsamen Besitzer"); only its maker
 * changes or deletes it. Whoever may see the find sees all its plates
 * (point_view_scope — the gate the pictures themselves pass). A save names
 * the version it began from: the same plate open on two devices does not
 * overwrite itself unseen.
 */

declare(strict_types=1);

require_once __DIR__ . '/point_owners.php';
// plate_writing lives with the hand it also cleans (photos.php).
require_once __DIR__ . '/photos.php';

const PLATE_PAGES_MAX = 6;
const PLATE_CELLS_MAX = 40;    // per page
const PLATE_DEPTH_MAX = 10;
const PLATE_ITEMS_MAX = 12;    // details, values and insets per cell
const PLATE_BYTES_MAX = 400000;
const PLATE_CORNERS   = ['br', 'bl', 'tr', 'tl', 'off'];
const PLATE_GROUNDS   = ['none', 'white', 'black'];
const PLATE_BLOCKS    = ['plot', 'row'];
const PLATE_LINES     = ['range', 'qn', 'mean', 'spread'];
const PLATE_SORTS     = ['L', 'B', 'Q'];
/* THE DETAILS A FIELD MAY SHOW, by key — the JavaScript twin is
   PLATE_DETAILS in assets/js/plates.js, which carries the labels and
   reads the values; a key added there is added here, or the server drops
   it on saving. */
const PLATE_DETAILS = [
    'species', 'number', 'date', 'place', 'place_full', 'mtb', 'gps', 'elevation', 'finder',
    'determiner', 'certainty', 'amount', 'substrate', 'vegetation', 'odour', 'taste', 'spore_print',
    'voucher', 'remark', 'note',
];
/* A LEERZEILE among them (plateparts.js, DETAIL_GAP, 09.11.2026): the one
   key that may stand more than once — each is half a line of air. */
const PLATE_DETAIL_GAP = 'gap';
const PLATE_DETAIL_GAPS_MAX = 10;

/** The details' keys in their order: known ones once each, Leerzeilen as often as set (up to a bound). */
function plate_detail_fields(mixed $fields): array
{
    $out = [];
    $gaps = 0;
    foreach (is_array($fields) ? $fields : [] as $f) {
        if ($f === PLATE_DETAIL_GAP) {
            if (++$gaps <= PLATE_DETAIL_GAPS_MAX) {
                $out[] = $f;
            }
        } elseif (is_string($f) && in_array($f, PLATE_DETAILS, true) && !in_array($f, $out, true)) {
            $out[] = $f;
        }
    }
    return $out;
}

function plates_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_plates');
}

/** The pictures of a find, as the keys a cell names them by. @return array<string, true> */
function plate_pictures(PDO $pdo, int $pointId): array
{
    $keys = [];
    $stmt = $pdo->prepare('SELECT id FROM map_photos WHERE point_id = ?');
    $stmt->execute([$pointId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $keys['photo:' . (int) $id] = true;
    }
    if (table_exists($pdo, 'map_cloud_files')) {
        $stmt = $pdo->prepare('SELECT id FROM map_cloud_files WHERE point_id = ?');
        $stmt->execute([$pointId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $keys['cloud:' . (int) $id] = true;
        }
    }
    return $keys;
}

/** A number into its range, rounded — whatever came in. */
function plate_number(mixed $v, float $min, float $max, float $default, int $digits = 4): float
{
    $v = is_numeric($v) ? (float) $v : $default;
    if (!is_finite($v)) {
        $v = $default;
    }
    return round(min($max, max($min, $v)), $digits);
}

/** How much of a words' ground covers the picture, in per cent (plates.js,
 *  opacityControl): 10 to 100 in steps of five, 100 where it was never set. */
function plate_ground_opacity(array $item): int
{
    return (int) (round(plate_number($item['groundOpacity'] ?? 100, 10, 100, 100, 0) / 5) * 5);
}

/** A details field's settings; with $placed its position on the picture too. */
function plate_details(mixed $d, bool $placed): array
{
    $d = is_array($d) ? $d : [];
    $out = [
        'fields' => plate_detail_fields($d['fields'] ?? null),
        // A word, a sign ('icons', plateparts.js DETAIL_ICONS, 09.11.2026) or nothing in front.
        'labels' => ($d['labels'] ?? true) === 'icons' ? 'icons' : (bool) ($d['labels'] ?? true),
        'flow'   => ($d['flow'] ?? '') === 'one' ? 'one' : 'lines',
        'pt'     => (int) plate_number($d['pt'] ?? 9, 4, 72, 9, 0),
        'ground' => in_array($d['ground'] ?? '', PLATE_GROUNDS, true) ? $d['ground'] : 'none',
        'groundOpacity' => plate_ground_opacity($d),
    ];
    if ($placed) {
        $out = ['kind' => 'info', 'x' => plate_number($d['x'] ?? 0, 0, 1, 0), 'y' => plate_number($d['y'] ?? 0, 0, 1, 0)] + $out;
    } else {
        // A tile's words: where they were moved to, or null — at the tile's padding.
        $out['x'] = is_numeric($d['x'] ?? null) ? plate_number($d['x'], 0, 1, 0) : null;
        $out['y'] = is_numeric($d['y'] ?? null) ? plate_number($d['y'], 0, 1, 0) : null;
    }
    return $out;
}

/** One node of a page's tree, cleaned; counts the cells into $cells. */
function plate_node(mixed $n, int $depth, array $pictures, int &$cells): array
{
    if (!is_array($n) || $depth > PLATE_DEPTH_MAX) {
        throw new InvalidArgumentException(t('Die Tafel ist beschädigt und lässt sich so nicht speichern.'));
    }
    if (($n['t'] ?? '') === 'split') {
        $kids  = is_array($n['kids'] ?? null) ? array_values($n['kids']) : [];
        $sizes = is_array($n['sizes'] ?? null) ? array_values($n['sizes']) : [];
        // Too many children is the cell count's to say, in its own words.
        if (count($kids) < 2) {
            throw new InvalidArgumentException(t('Die Tafel ist beschädigt und lässt sich so nicht speichern.'));
        }
        $out = [];
        $shares = [];
        foreach ($kids as $i => $kid) {
            $out[] = plate_node($kid, $depth + 1, $pictures, $cells);
            $shares[] = plate_number($sizes[$i] ?? 1, .01, 1000, 1);
        }
        // Shares, not sizes: whatever they summed to, they sum to one now.
        $sum = array_sum($shares);
        return [
            't'     => 'split',
            'dir'   => ($n['dir'] ?? '') === 'col' ? 'col' : 'row',
            'kids'  => $out,
            'sizes' => array_map(static fn(float $s): float => round($s / $sum, 5), $shares),
        ];
    }

    if (++$cells > PLATE_CELLS_MAX) {
        throw new InvalidArgumentException(t('Eine Seite hat höchstens %d Kacheln.', PLATE_CELLS_MAX));
    }
    $pic = is_string($n['pic'] ?? null) && isset($pictures[$n['pic']]) ? $n['pic'] : null;
    $items = [];
    foreach (is_array($n['items'] ?? null) ? array_values($n['items']) : [] as $item) {
        if (count($items) >= PLATE_ITEMS_MAX || !is_array($item)) {
            continue;
        }
        if (($item['kind'] ?? '') === 'info') {
            $items[] = plate_details($item, true);
            continue;
        }
        if (($item['kind'] ?? '') === 'inset') {
            $inset = plate_inset($item, $pictures);
            if ($inset !== null) {
                $items[] = $inset;
            }
            continue;
        }
        /* A KEYING WAY (plateparts.js, keyingRows; 09.11.2026): the key its
           confirmed way ends in, what of it shows, under which name. The way
           and its remark are read from the find's keyings when drawn —
           stored here is only which one and how. */
        if (($item['kind'] ?? '') === 'keying') {
            $keyId = mb_substr(trim((string) ($item['key_id'] ?? '')), 0, 120);
            if ($keyId === '') {
                continue;
            }
            $items[] = [
                'kind'   => 'keying',
                'key_id' => $keyId,
                'show'   => in_array($item['show'] ?? '', ['both', 'path', 'remark'], true) ? $item['show'] : 'both',
                // The book and the key by default since 09.11.2026; one of them alone where chosen.
                'label'  => in_array($item['label'] ?? '', ['book', 'key'], true) ? $item['label'] : 'both',
                'x'      => plate_number($item['x'] ?? 0, 0, 1, 0),
                'y'      => plate_number($item['y'] ?? 0, 0, 1, 0),
                'pt'     => (int) plate_number($item['pt'] ?? 9, 4, 72, 9, 0),
                'ground' => in_array($item['ground'] ?? '', PLATE_GROUNDS, true) ? $item['ground'] : 'none',
                'groundOpacity' => plate_ground_opacity($item),
            ];
            continue;
        }
        if (($item['kind'] ?? '') === 'values') {
            $layer = plate_layer($item['layer'] ?? null);
            if ($layer === null) {
                continue;
            }
            $items[] = [
                'kind'   => 'values',
                'layer'  => $layer,
                'lines'  => plate_value_lines($item['lines'] ?? null),
                'name'   => (bool) ($item['name'] ?? true),
                'x'      => plate_number($item['x'] ?? 0, 0, 1, 0),
                'y'      => plate_number($item['y'] ?? 0, 0, 1, 0),
                'pt'     => (int) plate_number($item['pt'] ?? 9, 4, 72, 9, 0),
                'ground' => in_array($item['ground'] ?? '', PLATE_GROUNDS, true) ? $item['ground'] : 'none',
                'groundOpacity' => plate_ground_opacity($item),
                'straight' => is_bool($item['straight'] ?? null) ? $item['straight'] : null,
                'mark'   => plate_mark($item['mark'] ?? null),
            ];
            continue;
        }
        /* NOTHING ELSE LIES ON A CELL — the free text went on 09.11.2026
           (the maintainer: a plate „trägt keine weiteren Informationen" than
           what the find and its pictures hold). One stored before, or sent
           by a page still open from before, is dropped here. */
    }
    // A picture, a details tile and a block exclude each other — in that order.
    $info = $pic === null && is_array($n['info'] ?? null) ? plate_details($n['info'], false) : null;
    $block = $pic === null && $info === null ? plate_block($n['block'] ?? null) : null;
    /* MEDIUM AND LABEL on a picture and on a ROW (09.11.2026:
       „bei einer Reihe … auch Medium anzeigen lassen und das Label") — a
       row's medium is its pictures', its label its layer's name
       (plateparts.js, rowMediaLabel, rowLabel). */
    $named = $pic !== null || ($block['kind'] ?? '') === 'row';
    return [
        't'     => 'cell',
        'pic'   => $pic,
        'z'     => plate_number($n['z'] ?? 1, 1, 40, 1),
        'cx'    => plate_number($n['cx'] ?? .5, 0, 1, .5),
        'cy'    => plate_number($n['cy'] ?? .5, 0, 1, .5),
        'bar'   => in_array($n['bar'] ?? '', PLATE_CORNERS, true) ? $n['bar'] : 'br',
        'barOver' => ($n['barOver'] ?? false) === true,
        'med'   => $named && in_array($n['med'] ?? '', PLATE_CORNERS, true) ? $n['med'] : 'off',
        'medFull' => $named && ($n['medFull'] ?? false) === true,
        // A row's medium and label in a line of their own (22.11.2026; plateparts.js, rowStrips).
        'medLine'   => ($block['kind'] ?? '') === 'row' && ($n['medLine'] ?? false) === true,
        'labelLine' => ($block['kind'] ?? '') === 'row' && ($n['labelLine'] ?? false) === true,
        'label' => $named && in_array($n['label'] ?? '', PLATE_CORNERS, true) ? $n['label'] : 'off',
        'labelMarks' => $named ? plate_notes($n['labelMarks'] ?? null) : [],
        /* As objects, also when empty, so what is stored reads {}. Reading
           it back (plate_prepare, json_decode as arrays) still turns an
           empty one into [] — the editor's bare() takes both as one, or
           its autosave saves again every few seconds
           (09.11.2026). */
        'ink'   => (object) plate_writing($n['ink'] ?? null, 'ink'),
        'soft'  => (object) plate_writing($n['soft'] ?? null, 'soft'),
        'notes' => $pic === null ? [] : plate_notes($n['notes'] ?? null),
        'measures' => $pic === null ? [] : plate_notes($n['measures'] ?? null),
        'info'  => $info,
        'block' => $block,
        'items' => $items,
    ];
}

/**
 * AN INSET — a picture or a block lying on a filled cell as a smaller
 * window of its own (07.10.2026: „wenn ich ein Bild in die
 * Mitte einer Kachel ziehe, dass es ein Inline-Bild wird … die
 * Ascusspitzen in ein größeres Bild einfügen, das einen Ascus zeigt").
 * Where it lies and how large, as shares of its cell; inside, a cell of
 * its own without words: a picture with its cutout and bar, or a block.
 * Null where it holds neither — a picture the find no longer has.
 */
function plate_inset(array $item, array $pictures): ?array
{
    $inner = is_array($item['cell'] ?? null) ? $item['cell'] : [];
    $pic = is_string($inner['pic'] ?? null) && isset($pictures[$inner['pic']]) ? $inner['pic'] : null;
    $block = $pic === null ? plate_block($inner['block'] ?? null) : null;
    if ($pic === null && $block === null) {
        return null;
    }
    $named = $pic !== null || ($block['kind'] ?? '') === 'row';
    $w = plate_number($item['w'] ?? .36, .05, 1, .36);
    $h = plate_number($item['h'] ?? .3, .05, 1, .3);
    return [
        'kind' => 'inset',
        // Its frame in the page's lines, unless switched off (09.11.2026).
        'frame' => ($item['frame'] ?? true) !== false,
        'x'    => plate_number($item['x'] ?? 0, 0, 1 - $w, 0),
        'y'    => plate_number($item['y'] ?? 0, 0, 1 - $h, 0),
        'w'    => $w,
        'h'    => $h,
        'cell' => [
            't'     => 'cell',
            'pic'   => $pic,
            'z'     => plate_number($inner['z'] ?? 1, 1, 40, 1),
            'cx'    => plate_number($inner['cx'] ?? .5, 0, 1, .5),
            'cy'    => plate_number($inner['cy'] ?? .5, 0, 1, .5),
            'bar'   => in_array($inner['bar'] ?? '', PLATE_CORNERS, true) ? $inner['bar'] : 'br',
            'barOver' => ($inner['barOver'] ?? false) === true,
            'med'   => $named && in_array($inner['med'] ?? '', PLATE_CORNERS, true) ? $inner['med'] : 'off',
            'medFull' => $named && ($inner['medFull'] ?? false) === true,
            'medLine'   => ($block['kind'] ?? '') === 'row' && ($inner['medLine'] ?? false) === true,
            'labelLine' => ($block['kind'] ?? '') === 'row' && ($inner['labelLine'] ?? false) === true,
            'label' => $named && in_array($inner['label'] ?? '', PLATE_CORNERS, true) ? $inner['label'] : 'off',
            'labelMarks' => $named ? plate_notes($inner['labelMarks'] ?? null) : [],
            'ink'   => (object) plate_writing($inner['ink'] ?? null, 'ink'),
            'soft'  => (object) plate_writing($inner['soft'] ?? null, 'soft'),
            'notes' => $pic === null ? [] : plate_notes($inner['notes'] ?? null),
            'measures' => $pic === null ? [] : plate_notes($inner['measures'] ?? null),
            'info'  => null,
            'block' => $block,
            'items' => [],
        ],
    ];
}

/** The layers whose writing a picture cell shows (08.10.2026): names, each once. */
function plate_notes(mixed $notes): array
{
    $out = [];
    foreach (is_array($notes) ? $notes : [] as $layer) {
        $layer = plate_layer($layer);
        if ($layer !== null && !in_array($layer, $out, true) && count($out) < 30) {
            $out[] = $layer;
        }
    }
    return $out;
}

/** A measures' layer by its name — a word the person chose, kept short. */
function plate_layer(mixed $layer): ?string
{
    $layer = is_string($layer) ? mb_substr(trim($layer), 0, 80) : '';
    return $layer === '' ? null : $layer;
}

/** A values text's lines: the structure's in their own order, then its
 *  part measures as 'part:<name>' (07.10.2026), each once. */
function plate_value_lines(mixed $lines): array
{
    $lines = is_array($lines) ? $lines : [];
    $parts = [];
    foreach ($lines as $key) {
        if (is_string($key) && str_starts_with($key, 'part:') && mb_strlen($key) > 5 && count($parts) < 20) {
            $parts[] = mb_substr($key, 0, 45);
        }
    }
    return [...array_values(array_intersect(PLATE_LINES, $lines)), ...array_values(array_unique($parts))];
}

/** Which of a layer's measures a values text or a block shows: null all of
 *  them, '' the unmarked, else one mark (measureMark in measurestats.js). */
function plate_mark(mixed $mark): ?string
{
    return is_string($mark) ? mb_substr(trim($mark), 0, 40) : null;
}

/** A block from the measures — the distribution or the row of one layer. */
function plate_block(mixed $b): ?array
{
    if (!is_array($b) || !in_array($b['kind'] ?? '', PLATE_BLOCKS, true)) {
        return null;
    }
    $layer = plate_layer($b['layer'] ?? null);
    if ($layer === null) {
        return null;
    }
    $out = [
        'kind'  => $b['kind'],
        'layer' => $layer,
        'sort'  => in_array($b['sort'] ?? '', PLATE_SORTS, true) ? $b['sort'] : 'L',
        'mark'  => plate_mark($b['mark'] ?? null),
        // A part measure's block (07.10.2026): its curve, its row of lines.
        'part'  => plate_mark($b['part'] ?? null) ?: null,
    ];
    if ($b['kind'] === 'plot') {
        // Its lengths along the bend or straight — the plate's own choice, or none.
        $out['straight'] = is_bool($b['straight'] ?? null) ? $b['straight'] : null;
        // Its ground, white or black (plateparts.js, plotGround, 09.11.2026).
        $out['ground'] = ($b['ground'] ?? '') === 'black' ? 'black' : 'white';
        // The line of count, spread and outliers under it — on unless switched off.
        $out['spread'] = ($b['spread'] ?? true) !== false;
        // How much its ground covers a picture beneath it, as an inset (plotGroundOpacity).
        $out['groundOpacity'] = plate_ground_opacity($b);
    }
    if ($b['kind'] === 'row') {
        /* The row's look (plateparts.js, rowLook): its own air or none —
           then the photo window's per layer —, the lines between the
           spores, their colour, the margin around them. */
        $air = $b['air'] ?? null;
        $out += [
            'air'      => is_numeric($air) && (int) $air >= 100 && (int) $air <= 300 ? (int) $air : null,
            'gap'      => plate_number($b['gap'] ?? .6, 0, 5, .6, 1),
            'gapColor' => ($b['gapColor'] ?? '') === '#000000' ? '#000000' : '#ffffff',
            // „schmal" is 0.6 mm since 07.10.2026, as wide as the fine lines
            // (plateparts.js, ROW_PAD_THIN); the 2 mm it was is read as it.
            'pad'      => (float) ($b['pad'] ?? null) === 2.0 ? 0.6 : plate_number($b['pad'] ?? .6, 0, 10, .6, 1),
            /* A ROW OF PICTURE CUTOUTS instead of measures (07.10.2026):
               the name the cutouts carry in this layer — '' for those
               without one —, null for a row of the layer's measures. */
            'crop'     => plate_mark($b['crop'] ?? null),
            /* A ROW OF COUNTS (18.11.2026): the layer's counting frames and
               sectors as photographed, instead of its measures
               (measurestats.js, countRows). */
            'count'    => ($b['count'] ?? false) === true,
        ];
    }
    return $out;
}

/**
 * A layout as the browser sent it, cleaned into the stored shape — every
 * number into its range, every key into its list, unknown pictures out.
 * Throws InvalidArgumentException with a sentence for the user where the
 * shape itself is broken or too big.
 *
 * @param array<string, true> $pictures the find's picture keys (plate_pictures)
 */
function plate_clean(mixed $layout, array $pictures): array
{
    if (!is_array($layout) || !is_array($layout['pages'] ?? null) || $layout['pages'] === []) {
        throw new InvalidArgumentException(t('Die Tafel ist beschädigt und lässt sich so nicht speichern.'));
    }
    if (count($layout['pages']) > PLATE_PAGES_MAX) {
        throw new InvalidArgumentException(t('Eine Tafel hat höchstens %d Seiten.', PLATE_PAGES_MAX));
    }
    $pages = [];
    foreach (array_values($layout['pages']) as $page) {
        $cells = 0;
        $pages[] = ['root' => plate_node(is_array($page) ? ($page['root'] ?? null) : null, 0, $pictures, $cells)];
    }
    return [
        'v'           => 1,
        'gutter'      => plate_number($layout['gutter'] ?? .6, 0, 5, .6, 1),
        'gutterColor' => ($layout['gutterColor'] ?? '') === '#000000' ? '#000000' : '#ffffff',
        // The size of the writing on the pictures, in points; null follows the account's text size.
        'notePt'      => is_numeric($layout['notePt'] ?? null) ? (int) plate_number($layout['notePt'], 4, 72, 10, 0) : null,
        'pages'       => $pages,
    ];
}

/** A stored row as the browser gets it. */
function plate_prepare(array $row, int $viewerId, bool $mayEdit, array $images = []): array
{
    $layout = json_decode((string) $row['layout'], true);
    return [
        // The pages drawn into the maker's cloud (plate_images.php): [{page, hash, width, height}].
        'images'     => $images,
        'id'         => (int) $row['id'],
        'point_id'   => (int) $row['point_id'],
        'user_id'    => (int) $row['user_id'],
        'maker'      => (string) ($row['maker'] ?? ''),
        'mine'       => (int) $row['user_id'] === $viewerId,
        'may_edit'   => $mayEdit,
        'layout'     => is_array($layout) ? $layout : ['v' => 1, 'pages' => []],
        'updated_at' => (string) $row['updated_at'],
    ];
}

/** The plates of a find the viewer may see — all of them, or none. */
function plates_for_point(PDO $pdo, int $viewerId, int $pointId): array
{
    if (!plates_ready($pdo) || point_view_scope($pdo, $viewerId, $pointId) === null) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT p.*, ' . sql_display_name() . ' AS maker
           FROM map_plates p JOIN map_users u ON u.id = p.user_id
          WHERE p.point_id = ?
          ORDER BY p.created_at, p.id'
    );
    $stmt->execute([$pointId]);
    $inCircle = point_in_circle($pdo, $viewerId, $pointId);
    $rows = $stmt->fetchAll();
    require_once __DIR__ . '/plate_images.php';
    $images = plate_images_of($pdo, array_map(static fn(array $r): int => (int) $r['id'], $rows));
    $plates = array_map(static fn(array $r): array => plate_prepare($r, $viewerId,
        $inCircle && (int) $r['user_id'] === $viewerId, $images[(int) $r['id']] ?? []), $rows);
    // The viewer's own first — the one he opens to work on.
    usort($plates, static fn(array $a, array $b): int => (int) $b['mine'] <=> (int) $a['mine']);
    return $plates;
}

/**
 * Save a plate — a new one when $id is null. Returns it as the browser
 * gets it. Throws InvalidArgumentException for the user.
 *
 * @param ?string $since the updated_at the editor opened — a plate saved
 *        since by somebody else is not overwritten
 */
function plate_save(PDO $pdo, int $userId, int $pointId, ?int $id, mixed $layout, ?string $since = null): array
{
    if (!point_in_circle($pdo, $userId, $pointId)) {
        throw new InvalidArgumentException(t('Tafeln machen der Besitzer und die Mitbesitzer eines Funds.'));
    }
    if ($id !== null) {
        $stmt = $pdo->prepare('SELECT user_id, point_id, updated_at FROM map_plates WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false || (int) $row['user_id'] !== $userId || (int) $row['point_id'] !== $pointId) {
            throw new InvalidArgumentException(t('Diese Tafel ist nicht deine — oder es gibt sie nicht mehr.'));
        }
        if ($since !== null && $since !== (string) $row['updated_at']) {
            throw new InvalidArgumentException(t('Die Tafel wurde inzwischen auf einem anderen Gerät gespeichert. Schließe sie und öffne sie neu, dann siehst du die neue Fassung.'));
        }
    }
    /* ONE PLATE PER PERSON AND FIND (06.10.2026: „Wir haben
       immer nur eine Tafel pro Fund", then each co-owner his own) — its
       pages carry what a second plate would. Said here and not only by the
       window, which offers no second: two devices pressing „Neue Tafel
       erstellen" at once would otherwise both get one. */
    if ($id === null) {
        $stmt = $pdo->prepare('SELECT 1 FROM map_plates WHERE point_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$pointId, $userId]);
        if ($stmt->fetchColumn() !== false) {
            throw new InvalidArgumentException(t('Du hast an diesem Fund schon eine Tafel.'));
        }
    }
    $clean = plate_clean($layout, plate_pictures($pdo, $pointId));
    $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
    if ($json === false || strlen($json) > PLATE_BYTES_MAX) {
        throw new InvalidArgumentException(t('Die Tafel ist zu groß zum Speichern.'));
    }
    if ($id === null) {
        $pdo->prepare('INSERT INTO map_plates (point_id, user_id, layout, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())')
            ->execute([$pointId, $userId, $json]);
        $id = (int) $pdo->lastInsertId();
    } else {
        $pdo->prepare('UPDATE map_plates SET layout = ?, updated_at = NOW() WHERE id = ?')->execute([$json, $id]);
    }
    // A page taken away takes its picture in the cloud along (plate_images.php).
    require_once __DIR__ . '/plate_images.php';
    plate_images_prune($pdo, $userId, $id, count($clean['pages']));
    $stmt = $pdo->prepare(
        'SELECT p.*, ' . sql_display_name() . ' AS maker FROM map_plates p JOIN map_users u ON u.id = p.user_id WHERE p.id = ?'
    );
    $stmt->execute([$id]);
    return plate_prepare($stmt->fetch(), $userId, true, plate_images_of($pdo, [$id])[$id] ?? []);
}

/**
 * A NAME OF PICTURE CUTOUTS RENAMED (22.11.2026: „Ausschnitte
 * nachträglich umbenennen … auch natürlich schon vergebene Namen"). A row
 * of cutouts on a plate knows its cutouts by their layer and their name
 * ({kind: 'row', layer, crop}), so when the photo window gives every cutout
 * of a name a new one, every plate of the find has to follow — or its row
 * would stand there empty. EVERY plate, not only the renamer's: the name is
 * the find's data, not a maker's hand, and a co-owner's row of the same
 * cutouts would be left empty just the same. A plate changed here counts as
 * saved (updated_at), so a device that still holds the old version is told
 * so rather than saving the old name back.
 *
 * @return int how many plates changed
 */
function plates_crop_rename(PDO $pdo, int $userId, int $pointId, string $layer, string $from, string $to): int
{
    if (!plates_ready($pdo)) {
        return 0;
    }
    if (!point_in_circle($pdo, $userId, $pointId)) {
        throw new InvalidArgumentException(t('Tafeln machen der Besitzer und die Mitbesitzer eines Funds.'));
    }
    $layer = plate_layer($layer);
    $from = plate_mark($from);
    $to = plate_mark($to);
    if ($layer === null || $from === null || $to === null || $from === $to) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT id, layout FROM map_plates WHERE point_id = ?');
    $stmt->execute([$pointId]);
    $changed = 0;
    foreach ($stmt->fetchAll() as $row) {
        /* AS OBJECTS, NOT ARRAYS: an empty {} (a cell's ink, a field's
           labels) would come back as [] and change the stored shape. */
        $layout = json_decode((string) $row['layout']);
        if (!is_object($layout) || !is_array($layout->pages ?? null)) {
            continue;
        }
        $hit = false;
        foreach ($layout->pages as $page) {
            if (is_object($page) && is_object($page->root ?? null)) {
                $hit = plate_crop_rename_node($page->root, $layer, $from, $to) || $hit;
            }
        }
        if ($hit) {
            $pdo->prepare('UPDATE map_plates SET layout = ?, updated_at = NOW() WHERE id = ?')
                ->execute([json_encode($layout, JSON_UNESCAPED_UNICODE), (int) $row['id']]);
            $changed++;
        }
    }
    return $changed;
}

/** One node of a page, its kids and the cells lying on it as insets — true where a row was renamed. */
function plate_crop_rename_node(object $n, string $layer, string $from, string $to): bool
{
    $hit = false;
    if (($n->t ?? '') === 'split') {
        foreach (is_array($n->kids ?? null) ? $n->kids : [] as $kid) {
            if (is_object($kid)) {
                $hit = plate_crop_rename_node($kid, $layer, $from, $to) || $hit;
            }
        }
        return $hit;
    }
    $block = $n->block ?? null;
    if (is_object($block) && ($block->layer ?? null) === $layer && ($block->crop ?? null) === $from) {
        $block->crop = $to;
        $hit = true;
    }
    foreach (is_array($n->items ?? null) ? $n->items : [] as $item) {
        if (is_object($item) && ($item->kind ?? '') === 'inset' && is_object($item->cell ?? null)) {
            $hit = plate_crop_rename_node($item->cell, $layer, $from, $to) || $hit;
        }
    }
    return $hit;
}

/** Only the maker deletes his plate. */
function plate_delete(PDO $pdo, int $userId, int $id): bool
{
    if (!plates_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM map_plates WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    if ($stmt->fetchColumn() === false) {
        return false;
    }
    // Its pages in the cloud go with it — the cloud's trash keeps them a while.
    require_once __DIR__ . '/plate_images.php';
    plate_images_prune($pdo, $userId, $id, 0);
    $stmt = $pdo->prepare('DELETE FROM map_plates WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    return $stmt->rowCount() > 0;
}
